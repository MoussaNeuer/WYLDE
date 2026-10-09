<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Variantes d'image : WebP 400 / 800 / 1600 px.
 *
 * Le fichier d'origine est conservé tel quel (c'est la seule chose que
 * le client peut vouloir afficher en pleine résolution). Les variantes
 * sont générées une fois, à l'upload ou par le script de régénération,
 * et stockées à côté de l'original avec un suffixe de largeur :
 *
 *     products/2026/10/photo-abc.jpeg          ← original
 *     products/2026/10/photo-abc-400.webp      ← vignette
 *     products/2026/10/photo-abc-800.webp
 *     products/2026/10/photo-abc-1600.webp
 *
 * La carte produit affiche ~300 px : le navigateur choisira 400. La
 * fiche produit occupe la moitié de l'écran sur mobile : 800 suffit.
 * Le 1600 ne sert qu'aux grands écrans de bureau.
 */
final class ImageService
{
    /** Repli si la configuration media.widths est absente ou invalide. */
    private const DEFAULT_WIDTHS = [400, 800, 1600];

    /**
     * Largeurs des variantes générées, de la plus petite à la plus grande.
     *
     * @return array<int, int>
     */
    public static function widths(): array
    {
        $configured = (array) Config::get('media.widths', self::DEFAULT_WIDTHS);

        $widths = array_values(array_unique(array_filter(array_map(
            static fn (mixed $w): int => (int) $w,
            $configured
        ), static fn (int $w): bool => $w >= 100)));

        sort($widths);

        return $widths !== [] ? $widths : self::DEFAULT_WIDTHS;
    }

    /** Qualité WebP (configurable). */
    private const DEFAULT_QUALITY = 82;

    /**
     * GD est-il capable d'écrire du WebP ?
     *
     * Sans cette extension, les variantes restent en JPEG : le srcset
     * pointe alors sur les fichiers d'origine, ce qui fonctionne quand
     * même mais sans gain de poids.
     */
    public static function webpAvailable(): bool
    {
        return function_exists('imagewebp');
    }

    private static function quality(): int
    {
        return max(1, min(100, (int) Config::get('media.quality', self::DEFAULT_QUALITY)));
    }

    /**
     * Ré-encode le fichier téléversé dans son format d'origine.
     *
     * GD redessine l'image depuis le tampon de pixels : toute charge
     * utile embarquée (PHP, scripts, données parasites dans les blocs
     * de commentaires…) disparaît. Le résultat est écrit par-dessus
     * l'original, mêmes dimensions et même format, puisqu'il s'agit
     * d'une image que l'upload a déjà validée.
     *
     * @return bool false si l'image n'est pas décodable (l'upload sera refusé)
     */
    public static function reencode(string $absolute, string $mime): bool
    {
        $source = @imagecreatefromstring((string) @file_get_contents($absolute));

        if ($source === false) {
            return false;
        }

        $width  = imagesx($source);
        $height = imagesy($source);

        if ($width < 1 || $height < 1) {
            imagedestroy($source);

            return false;
        }

        $target = imagecreatetruecolor($width, $height);

        if ($target === false) {
            imagedestroy($source);

            return false;
        }

        // Le PNG/WebP/AVIF peuvent porter de la transparence : on la
        // préserve. Le JPEG n'en a pas : fond blanc pour un ré-encodage
        // propre (un fond transparent deviendrait noir ailleurs).
        if ($mime === 'image/jpeg') {
            $background = imagecolorallocate($target, 255, 255, 255);
            imagefilledrectangle($target, 0, 0, $width, $height, $background);
        } else {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefill($target, 0, 0, $transparent);
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $width, $height);

        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($target, $absolute, 90),
            'image/png'  => imagepng($target, $absolute, 9),
            'image/webp' => imagewebp($target, $absolute, self::quality()),
            'image/avif' => function_exists('imageavif')
                ? imageavif($target, $absolute, self::quality())
                : false,
            default      => false,
        };

        imagedestroy($target);
        imagedestroy($source);

        return $ok;
    }

    /**
     * Chemins des variantes WebP existantes pour un fichier d'origine.
     *
     * @param  string $relative  Chemin relatif à storage/uploads (avec /)
     * @return array<int, string>  [largeur => chemin relatif]
     */
    public static function variantsFor(string $relative): array
    {
        if (!self::webpAvailable() || trim($relative) === '') {
            return [];
        }

        $relative = str_replace('\\', '/', $relative);
        $info     = @getimagesize(UploadService::basePath() . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative));

        if ($info === false) {
            return [];
        }

        $sourceWidth = (int) $info[0];

        if ($sourceWidth < 1) {
            return [];
        }

        $stem = preg_replace('/\.[^.]+$/', '', $relative) ?? $relative;
        $found = [];

        foreach (self::widths() as $width) {
            // Ne jamais créer une variante plus large que la source :
            // redimensionner vers le haut alourdit sans rien apporter.
            if ($width > $sourceWidth) {
                continue;
            }

            $candidate = $stem . '-' . $width . '.webp';

            if (is_file(self::absolute($candidate))) {
                $found[$width] = $candidate;
            }
        }

        return $found;
    }

    /**
     * Génère les variantes manquantes d'un fichier déjà stocké.
     *
     * @return array<int, string>  [largeur => chemin relatif créé ou existant]
     */
    public static function generateVariants(string $relative): array
    {
        if (!self::webpAvailable() || trim($relative) === '') {
            return [];
        }

        $relative = str_replace('\\', '/', $relative);
        $absolute = self::absolute($relative);

        if (!is_file($absolute)) {
            return [];
        }

        $source = @imagecreatefromstring((string) @file_get_contents($absolute));

        if ($source === false) {
            return [];
        }

        $sourceWidth  = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($sourceWidth < 1 || $sourceHeight < 1) {
            imagedestroy($source);

            return [];
        }

        $stem  = preg_replace('/\.[^.]+$/', '', $relative) ?? $relative;
        $quality = self::quality();
        $created = [];

        foreach (self::widths() as $width) {
            if ($width > $sourceWidth) {
                continue;
            }

            $target = $stem . '-' . $width . '.webp';

            if (is_file(self::absolute($target))) {
                $created[$width] = $target;

                continue;
            }

            $height = (int) round($sourceHeight * ($width / $sourceWidth));
            $targetImage = imagecreatetruecolor($width, max(1, $height));

            if ($targetImage === false) {
                continue;
            }

            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            imagecopyresampled(
                $targetImage,
                $source,
                0, 0, 0, 0,
                $width, max(1, $height),
                $sourceWidth, $sourceHeight
            );

            $targetAbsolute = self::absolute($target);

            if (imagewebp($targetImage, $targetAbsolute, $quality)) {
                @chmod($targetAbsolute, 0o644);
                $created[$width] = $target;
            }

            imagedestroy($targetImage);
        }

        imagedestroy($source);

        return $created;
    }

    /**
     * Attribut srcset pour une image, basé sur les variantes existantes.
     *
     * Si aucune variante n'existe (WebP absent, ou image trop petite),
     * la sortie est vide : le navigateur utilise simplement `src`.
     *
     * @return string Ex. "…-400.webp 400w, …-800.webp 800w"
     */
    public static function srcsetFor(string $relative): string
    {
        $variants = self::variantsFor($relative);

        if ($variants === []) {
            return '';
        }

        ksort($variants);

        $entries = [];

        foreach ($variants as $width => $path) {
            $entries[] = self::url($path) . ' ' . $width . 'w';
        }

        return implode(', ', $entries);
    }

    /**
     * Meilleure variante pour une largeur cible donnée.
     *
     * Retourne la variante la plus proche sans dépasser la cible ; si
     * aucune variante ne convient, retourne le chemin d'origine.
     */
    public static function bestFor(string $relative, int $targetWidth): string
    {
        $variants = self::variantsFor($relative);

        if ($variants === []) {
            return $relative;
        }

        ksort($variants);
        $best = $relative;

        foreach ($variants as $width => $path) {
            if ($width <= $targetWidth) {
                $best = $path;
            } else {
                break;
            }
        }

        return $best;
    }

    /**
     * Supprime les variantes d'un fichier supprimé.
     */
    public static function deleteVariants(string $relative): void
    {
        $relative = str_replace('\\', '/', $relative);
        $stem     = preg_replace('/\.[^.]+$/', '', $relative) ?? $relative;

        foreach (self::widths() as $width) {
            $path = $stem . '-' . $width . '.webp';

            if (is_file(self::absolute($path))) {
                @unlink(self::absolute($path));
            }
        }
    }

    /** URL publique d'un média (via /media/{chemin}). */
    private static function url(string $relative): string
    {
        return url('media/' . ltrim(str_replace('\\', '/', $relative), '/'));
    }

    private static function absolute(string $relative): string
    {
        return UploadService::basePath() . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}