<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\RateLimiter;

/**
 * Anti brute-force sur les connexions (§13.1).
 *
 * Le middleware 'rate_limit' des routes de connexion limite déjà les
 * requêtes par adresse IP ; ici on compte uniquement les ÉCHECS, avec une
 * clé qui combine l'e-mail ET l'adresse IP : un attaquant ne peut pas
 * verrouiller un compte tiers ni contourner le blocage en changeant
 * d'e-mail, et deux personnes derrière la même adresse IP ne se
 * bloquent pas l'une l'autre.
 *
 * Le compteur est partagé entre client et admin via deux buckets
 * distincts : forcer la porte admin ne draine pas le quota client.
 */
final class LoginThrottle
{
    private function __construct()
    {
        // Classe statique, pas d'instance.
    }

    private static function config(): array
    {
        $max     = (int) Config::get('security.login.max_attempts', 5);
        $minutes = (int) Config::get('security.login.lockout_min', 15);

        return [$max, $minutes * 60];
    }

    private static function identifier(string $email, string $ip): string
    {
        return mb_strtolower(trim($email), 'UTF-8') . '|' . $ip;
    }

    /**
     * Secondes de blocage restantes (0 si aucune).
     */
    public static function lockedFor(string $bucket, string $email, string $ip): int
    {
        [$max, $window] = self::config();

        if (!RateLimiter::tooManyAttempts($bucket, self::identifier($email, $ip), $max, $window)) {
            return 0;
        }

        return RateLimiter::availableIn($bucket, self::identifier($email, $ip), $window);
    }

    /** Consomme un jeton après un échec d'authentification. */
    public static function recordFailure(string $bucket, string $email, string $ip): void
    {
        [$max, $window] = self::config();

        RateLimiter::attempt($bucket, self::identifier($email, $ip), $max, $window);
    }

    /** Réinitialise le compteur après une connexion réussie. */
    public static function forget(string $bucket, string $email, string $ip): void
    {
        RateLimiter::clear($bucket, self::identifier($email, $ip));
    }
}