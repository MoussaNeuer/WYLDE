<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Internationalisation de l'interface.
 *
 * Deux mécanismes distincts :
 *  1. Les libellés d'interface vivent dans resources/lang/{locale}.php
 *     et sont traduits via __().
 *  2. Les contenus rédigés (produits, catégories) portent des colonnes
 *     name_en / description_en. Si la colonne EN est vide, on retombe
 *     sur le français. Voir Lang::field().
 *
 * La langue est mémorisée en session puis en cookie ; les URLs restent
 * identiques quelle que soit la langue.
 */
final class Lang
{
    private const SESSION_KEY = 'locale';

    private static ?string $locale = null;

    /** @var array<string, array<string, mixed>> */
    private static array $loaded = [];

    private static function defaultLocale(): string
    {
        return (string) Config::get('app.locale', 'fr');
    }

    private static function fallbackLocale(): string
    {
        return (string) Config::get('app.fallback_locale', 'fr');
    }

    /** @return array<int, string> */
    private static function available(): array
    {
        $locales = Config::get('app.available_locales', ['fr', 'en']);

        return is_array($locales) ? array_values(array_map('strval', $locales)) : ['fr'];
    }

    public static function isAvailable(string $locale): bool
    {
        return in_array($locale, self::available(), true);
    }

    public static function setLocale(string $locale): void
    {
        if (!self::isAvailable($locale)) {
            return;
        }

        self::$locale = $locale;
        Session::set(self::SESSION_KEY, $locale);
    }

    public static function locale(): string
    {
        if (self::$locale !== null) {
            return self::$locale;
        }

        return self::defaultLocale();
    }

    public static function fallback(): string
    {
        return self::fallbackLocale();
    }

    public static function isDefaultLocale(): bool
    {
        return self::locale() === self::defaultLocale();
    }

    /**
     * Détermine la langue au premier affichage :
     * session → cookie → en-tête Accept-Language → langue par défaut.
     * Le cookie est écrit par le contrôleur de bascule.
     */
    public static function boot(Request $request): void
    {
        $available = self::available();

        $fromSession = Session::get(self::SESSION_KEY);
        if (is_string($fromSession) && self::isAvailable($fromSession)) {
            self::$locale = $fromSession;

            return;
        }

        $cookieName = (string) Config::get('app.locale_cookie', 'wylde_locale');
        $fromCookie = $_COOKIE[$cookieName] ?? null;

        if (is_string($fromCookie) && self::isAvailable($fromCookie)) {
            self::$locale = $fromCookie;
            Session::set(self::SESSION_KEY, $fromCookie);

            return;
        }

        $accepted = strtolower((string) $request->header('Accept-Language', ''));

        if ($accepted !== '') {
            foreach (explode(',', $accepted) as $chunk) {
                $code = substr(trim(explode(';', $chunk)[0]), 0, 2);

                if (in_array($code, $available, true)) {
                    self::$locale = $code;
                    Session::set(self::SESSION_KEY, $code);

                    return;
                }
            }
        }

        self::$locale = self::defaultLocale();
    }

    /**
     * Traduit une clé de libellé. Les remplacements utilisent la syntaxe
     * :name,:param.
     *
     * @param array<string, string|int|float> $replace
     */
    public static function get(string $key, array $replace = []): string
    {
        $line = self::lookup(self::locale(), $key);

        if ($line === null) {
            $line = self::lookup(self::fallbackLocale(), $key);
        }

        if ($line === null) {
            // Clé absente : on la renvoie telle quelle pour rester débogable.
            return $key;
        }

        foreach ($replace as $name => $value) {
            $line = str_replace(':' . $name, (string) $value, $line);
        }

        // Pluriel à deux formes séparées par « | » : ':count produit|:count produits'.
        if (str_contains($line, '|')) {
            $count = isset($replace['count']) ? (int) $replace['count'] : 1;
            $forms = explode('|', $line);

            $line = match (self::locale()) {
                'fr'    => $count > 1 ? ($forms[1] ?? $line) : ($forms[0] ?? $line),
                default => $count === 1 ? ($forms[0] ?? $line) : ($forms[1] ?? $line),
            };
        }

        return $line;
    }

    private static function lookup(string $locale, string $key): ?string
    {
        $messages = self::load($locale);

        if ($messages === null) {
            return null;
        }

        $value = $messages;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return is_string($value) ? $value : null;
    }

    /** @return array<string, mixed>|null */
    private static function load(string $locale): ?array
    {
        if (array_key_exists($locale, self::$loaded)) {
            return self::$loaded[$locale];
        }

        $dir  = (string) Config::get('app.paths.lang', '');
        $file = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $locale . '.php';

        $messages = is_file($file) ? require $file : null;

        self::$loaded[$locale] = is_array($messages) ? $messages : null;

        return self::$loaded[$locale];
    }

    /**
     * Champ localisé d'une entité (produit, catégorie).
     *
     * Règle : en locale EN, utiliser la colonne *_en si elle est renseignée,
     * sinon retomber sur la colonne française (jamais de page vide).
     *
     * @param array<string, mixed> $row
     */
    public static function field(array $row, string $field): string
    {
        $primary = $row[$field] ?? null;

        if (self::locale() !== 'en') {
            return self::toString($primary);
        }

        $translated = $row[$field . '_en'] ?? null;

        if (is_string($translated) && trim($translated) !== '') {
            return trim($translated);
        }

        return self::toString($primary);
    }

    /** Un champ EN renseigné existe-t-il pour cette entité ? */
    public static function hasTranslation(array $row, string $field): bool
    {
        $translated = $row[$field . '_en'] ?? null;

        return is_string($translated) && trim($translated) !== '';
    }

    private static function toString(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * Translittère une chaîne en ASCII pour la génération de slugs.
     *
     * intl (normalizer) est utilisé quand disponible, sinon une table de
     * correspondance des accents suffit pour le français et l'anglais.
     */
    public static function transliterate(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        // Cas général : décomposition Unicode puis suppression des diacritiques.
        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($value, \Normalizer::FORM_D);

            if (is_string($decomposed) && $decomposed !== '') {
                $value = $decomposed;
            }
        }

        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if (is_string($converted) && $converted !== '') {
            $value = $converted;
        }

        // Accents restants si iconv est indisponible.
        $value = strtr($value, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae',
            'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'œ' => 'oe',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE',
            'Ç' => 'C',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ñ' => 'N',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Œ' => 'OE',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ý' => 'Y',
            'ß' => 'ss', 'SS' => 'SS',
        ]);

        return $value;
    }

    /** URL de bascule de langue : même page, locale opposée. */
    public static function alternate(): string
    {
        return self::locale() === 'en' ? 'fr' : 'en';
    }

    public static function reset(): void
    {
        self::$locale = null;
        self::$loaded = [];
    }
}
