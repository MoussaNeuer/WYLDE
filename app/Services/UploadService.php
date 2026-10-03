<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;

/**
 * Écriture des médias produits.
 *
 * Les fichiers atterrissent dans storage/uploads, hors document root :
 * ils ne sont servis que par /media/{chemin}, qui revalide le chemin à
 * chaque requête (§13.4). Les noms sont regénérés (jamais le nom d'origine)
 * pour éviter les collisions et les extensions détournées.
 */
final class UploadService
{
    /** @return array<int, string> */
    public static function allowedExtensions(): array
    {
        $extensions = (array) Config::get('app.uploads.extensions', ['jpg', 'jpeg', 'png', 'webp', 'avif']);

        return array_values(array_map(
            static fn (mixed $extension): string => strtolower(trim((string) $extension, ". \t\n\r")),
            $extensions
        ));
    }

    public static function maxSize(): int
    {
        return max(1, (int) Config::get('app.uploads.max_size', 5 * 1024 * 1024));
    }

    /**
     * Enregistre un fichier téléversé.
     *
     * @param  array<string, mixed> $file  Entrée normalisée de $_FILES
     * @return array{ok: bool, error: string, path: string}
     */
    public static function store(array $file, string $folder = 'products', ?string $baseName = null): array
    {
        $error   = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $size    = (int) ($file['size'] ?? 0);
        $name    = (string) ($file['name'] ?? '');

        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => self::errorMessage($error), 'path' => ''];
        }

        if ($tmpName === '' || !is_file($tmpName)) {
            return ['ok' => false, 'error' => 'fichier introuvable', 'path' => ''];
        }

        if ($size <= 0 || $size > self::maxSize()) {
            return [
                'ok'    => false,
                'error' => 'trop volumineux (max ' . self::formatSize(self::maxSize()) . ')',
                'path'  => '',
            ];
        }

        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        if (!in_array($extension, self::allowedExtensions(), true)) {
            return [
                'ok'    => false,
                'error' => 'format non autorisé (' . implode(', ', self::allowedExtensions()) . ')',
                'path'  => '',
            ];
        }

        // Le type MIME réel prime sur l'extension annoncée par le client.
        $mime = self::detectMime($tmpName);

        if ($mime === null || !self::isAllowedMime($mime)) {
            return ['ok' => false, 'error' => 'ce fichier n\'est pas une image valide', 'path' => ''];
        }

        if (!self::isSafeImage($tmpName)) {
            return ['ok' => false, 'error' => 'image refusée (contenu suspect)', 'path' => ''];
        }

        $relativeDir = trim($folder, '/') . '/' . date('Y/m');
        $base        = $baseName !== null && slugify($baseName) !== '' ? slugify($baseName) : 'media';

        $fileName = $base . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        $relative = $relativeDir . '/' . $fileName;

        $absolute = self::basePath() . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        $directory = dirname($absolute);

        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            Logger::error('Création du dossier media impossible', ['directory' => $directory]);

            return ['ok' => false, 'error' => 'dossier de destination indisponible', 'path' => ''];
        }

        if (!@move_uploaded_file($tmpName, $absolute)) {
            // move_uploaded_file échoue hors contexte HTTP (tests CLI) :
            // on retombe sur un renommage classique.
            if (!@rename($tmpName, $absolute)) {
                return ['ok' => false, 'error' => 'enregistrement du fichier impossible', 'path' => ''];
            }
        }

        @chmod($absolute, 0o644);

        return ['ok' => true, 'error' => '', 'path' => $relative];
    }

    /**
     * Supprime un média stocké.
     * Ne peut jamais sortir de storage/uploads : le chemin est révoqué
     * avant d'être recombiné.
     */
    public static function delete(?string $relativePath): bool
    {
        if ($relativePath === null || trim($relativePath) === '') {
            return false;
        }

        $relative = str_replace('\\', '/', trim($relativePath));

        if (str_contains($relative, '..') || str_contains($relative, "\0") || str_starts_with($relative, '/')) {
            return false;
        }

        $base    = self::basePath();
        $absolute = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        $realBase  = realpath($base);
        $realFile  = realpath($absolute);

        if ($realBase === false || $realFile === false || !is_file($realFile)) {
            return false;
        }

        if (!str_starts_with($realFile, $realBase . DIRECTORY_SEPARATOR)) {
            Logger::warning('Suppression media hors storage/uploads refusee', ['path' => $relativePath]);

            return false;
        }

        return @unlink($realFile);
    }

    public static function basePath(): string
    {
        return rtrim((string) Config::get('app.paths.uploads', ''), '/\\');
    }

    public static function formatSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . ' Mo';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' Ko';
        }

        return $bytes . ' o';
    }

    private static function detectMime(string $path): ?string
    {
        if (!function_exists('finfo_open')) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return null;
        }

        $mime = finfo_file($finfo, $path);

        finfo_close($finfo);

        return is_string($mime) ? $mime : null;
    }

    private static function isAllowedMime(string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/avif'], true);
    }

    /**
     * Vérifie que le fichier est bien une image exploitable.
     * getimagesize échoue sur une charge utile tronquée ou un fichier
     * renommé en .jpg : c'est exactement ce qu'on veut rejeter.
     */
    private static function isSafeImage(string $path): bool
    {
        $info = @getimagesize($path);

        if ($info === false || (int) $info[0] < 1 || (int) $info[1] < 1) {
            return false;
        }

        $maxWidth = max(600, (int) Config::get('app.uploads.max_width', 3000));

        return (int) $info[0] <= $maxWidth;
    }

    private static function errorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'fichier trop volumineux',
            UPLOAD_ERR_PARTIAL                        => 'téléversement interrompu',
            UPLOAD_ERR_NO_FILE                        => 'aucun fichier sélectionné',
            UPLOAD_ERR_NO_TMP_DIR                    => 'dossier temporaire indisponible',
            UPLOAD_ERR_CANT_WRITE                    => 'écriture impossible sur le disque',
            default                                   => 'téléversement refusé',
        };
    }
}
