<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Limitation de débit sur disque, par IP et par clé de quota.
 *
 * Utilisée pour les tentatives de connexion (cf. §13.1) et les
 * formulaires publics. Le stockage fichier évite toute dépendance.
 */
final class RateLimiter
{
    private const PREFIX = 'rl_';

    /**
     * Consomme un jeton. Retourne false si le quota est atteint.
     *
     * @param string $bucket  ex. 'login:admin', 'contact'
     */
    public static function attempt(string $bucket, ?string $identifier = null, int $max = 0, int $window = 0): bool
    {
        $max    = $max > 0 ? $max : (int) Config::get('security.rate_limit.max', 60);
        $window = $window > 0 ? $window : (int) Config::get('security.rate_limit.window', 60);

        $key      = self::key($bucket, $identifier);
        $file     = self::file($key);
        $now      = time();
        $attempts = self::read($file, $now - $window);

        if (count($attempts) >= $max) {
            return false;
        }

        $attempts[] = $now;
        self::write($file, $attempts);

        return true;
    }

    /** Nombre de tentatives encore disponibles. */
    public static function remaining(string $bucket, ?string $identifier = null, int $max = 0, int $window = 0): int
    {
        $max    = $max > 0 ? $max : (int) Config::get('security.rate_limit.max', 60);
        $window = $window > 0 ? $window : (int) Config::get('security.rate_limit.window', 60);

        $key  = self::key($bucket, $identifier);
        $file = self::file($key);
        $now  = time();

        self::write($file, self::read($file, $now - $window));

        return max(0, $max - count(self::read($file, $now - $window)));
    }

    /** Le quota est-il déjà atteint ? */
    public static function tooManyAttempts(string $bucket, ?string $identifier = null, int $max = 0, int $window = 0): bool
    {
        return self::remaining($bucket, $identifier, $max, $window) <= 0;
    }

    /** Secondes restantes avant réinitialisation. */
    public static function availableIn(string $bucket, ?string $identifier = null, int $window = 0): int
    {
        $window = $window > 0 ? $window : (int) Config::get('security.rate_limit.window', 60);
        $file   = self::file(self::key($bucket, $identifier));
        $now    = time();

        $attempts = self::read($file, $now - $window);

        if ($attempts === []) {
            return 0;
        }

        return max(0, (min($attempts) + $window) - $now);
    }

    public static function clear(string $bucket, ?string $identifier = null): void
    {
        $file = self::file(self::key($bucket, $identifier));

        if (is_file($file)) {
            @unlink($file);
        }
    }

    /** Purge les compteurs expirés. */
    public static function prune(): void
    {
        $dir = self::dir();

        if (!is_dir($dir)) {
            return;
        }

        $cutoff = time() - 86400;

        foreach (glob($dir . self::PREFIX . '*.json') ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    private static function key(string $bucket, ?string $identifier): string
    {
        $identifier ??= Request::capture()->ip();

        return hash('sha256', $bucket . '|' . $identifier);
    }

    private static function file(string $key): string
    {
        return self::dir() . self::PREFIX . $key . '.json';
    }

    private static function dir(): string
    {
        $dir = rtrim((string) Config::get('app.paths.cache', ''), '/\\') . DIRECTORY_SEPARATOR . 'rate';

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /**
     * @return array<int, int>
     */
    private static function read(string $file, int $since): array
    {
        if (!is_file($file)) {
            return [];
        }

        $raw = @file_get_contents($file);

        if ($raw === false || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return [];
        }

        return array_values(array_filter(
            array_map('intval', $data),
            static fn (int $ts): bool => $ts > $since
        ));
    }

    /** @param array<int, int> $attempts */
    private static function write(string $file, array $attempts): void
    {
        @file_put_contents($file, json_encode(array_values($attempts)), LOCK_EX);
    }
}
