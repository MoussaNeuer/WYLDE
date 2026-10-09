<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cache fichier simple, pour les données de page qui changent peu.
 *
 * Pas de dépendance externe : un fichier JSON par clé dans
 * storage/cache/page, avec un horodatage d'expiration. Une expiration
 * courte (60 s) protège la base des rafales de visites sans risquer
 * d'afficher un catalogue périmé plus de soixante secondes.
 *
 * Les entrées ne doivent jamais contenir de données de session ou
 * d'identifiants : le cache est partagé entre visiteurs.
 */
final class Cache
{
    /** Sous-dossier des entrées de page (à distinguer du rate limiter). */
    private const SUBDIR = 'page';

    /** Clés valides : minuscules, chiffres, tirets, points. */
    private const KEY_PATTERN = '/^[a-z0-9._-]{1,120}$/';

    /**
     * Retourne la valeur mise en cache, ou l'exécute et la stocke.
     *
     * @template T
     * @param  callable(): T $producer
     * @return T
     */
    public static function remember(string $key, int $ttl, callable $producer): mixed
    {
        $cached = self::get($key);

        if ($cached !== null) {
            return $cached['value'];
        }

        $value = $producer();

        // Un résultat vide ou null n'est pas mis en cache : cela évite
        // de figer un état d'erreur transitoire (base en panne, etc.).
        if ($value !== null && $value !== [] && $value !== false && $value !== '') {
            self::put($key, $value, $ttl);
        }

        return $value;
    }

    /** @return array{value: mixed, expires_at: int}|null */
    private static function get(string $key): ?array
    {
        $file = self::path($key);

        if ($file === null || !is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);

        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);

        if (!is_array($data) || !array_key_exists('value', $data) || !array_key_exists('expires_at', $data)) {
            @unlink($file);

            return null;
        }

        if ((int) $data['expires_at'] <= time()) {
            @unlink($file);

            return null;
        }

        return ['value' => $data['value'], 'expires_at' => (int) $data['expires_at']];
    }

    private static function put(string $key, mixed $value, int $ttl): void
    {
        $file = self::path($key);

        if ($file === null) {
            return;
        }

        $dir = dirname($file);

        if (!is_dir($dir)) {
            @mkdir($dir, 0o775, true);
        }

        $payload = json_encode([
            'value'      => $value,
            'expires_at' => time() + max(1, $ttl),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            return;
        }

        // Écriture atomique : un lecteur ne doit jamais voir un fichier
        // à moitié écrit.
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (@file_put_contents($tmp, $payload, LOCK_EX) !== false) {
            @rename($tmp, $file);
        } else {
            @unlink($tmp);
        }
    }

    private static function path(string $key): ?string
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            return null;
        }

        $base = rtrim((string) Config::get('app.paths.cache', ''), '/\\');

        if ($base === '') {
            return null;
        }

        return $base . DIRECTORY_SEPARATOR . self::SUBDIR
            . DIRECTORY_SEPARATOR . $key . '.json';
    }
}