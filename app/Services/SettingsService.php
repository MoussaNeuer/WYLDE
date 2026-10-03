<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Setting;

/**
 * Lecture des paramètres de boutique.
 *
 * Les paramètres sont chargés une fois par requête puis mis en cache :
 * le header, le footer et les pages produit les sollicitent tous.
 */
final class SettingsService
{
    /** @var array<string, string>|null */
    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        $value = $all[$key] ?? null;

        if ($value === null || trim($value) === '') {
            return $default;
        }

        return $value;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            return self::$cache = Setting::pairs();
        } catch (\Throwable) {
            // La base peut être indisponible au tout premier démarrage :
            // on renvoie un jeu vide plutôt que de casser l'affichage.
            return self::$cache = [];
        }
    }

    public static function put(string $key, mixed $value): void
    {
        Setting::put($key, $value);
        self::$cache = null;
    }

    /** @param array<string, mixed> $pairs */
    public static function putMany(array $pairs): void
    {
        Setting::putMany($pairs);
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    /** Lien de paiement Wave global. Vide si non configuré. */
    public static function waveLink(): string
    {
        $link = self::get('wave_payment_link', config('app.payment.wave_link', ''));

        return is_string($link) ? trim($link) : '';
    }

    public static function hasWaveLink(): bool
    {
        return self::waveLink() !== '';
    }

    public static function shopEmail(): string
    {
        return (string) self::get('shop_email', '');
    }

    public static function shopPhone(): string
    {
        return (string) self::get('shop_phone', '');
    }
}
