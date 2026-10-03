<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Façade d'authentification.
 *
 * L'utilisateur courant est mis en cache en mémoire et reconstruit depuis
 * la session à la demande. Le rôle est vérifié côté serveur sur chaque
 * action sensible : masquer un bouton n'est pas une autorisation.
 */
final class Auth
{
    private static ?User $user = null;

    private static bool $resolved = false;

    private const SESSION_KEY = '_auth_user_id';

    public static function user(): ?User
    {
        if (self::$resolved) {
            return self::$user;
        }

        self::$resolved = true;

        $id = Session::get(self::SESSION_KEY);

        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            return self::$user = null;
        }

        $user = User::find((int) $id);

        if ($user === null || !$user->isActive()) {
            // Compte supprimé, suspendu ou rôle révoqué : on invalide.
            self::logout();

            return self::$user = null;
        }

        return self::$user = $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return self::user()?->id();
    }

    public static function isAdmin(): bool
    {
        return self::user()?->isAdmin() ?? false;
    }

    public static function isStaff(): bool
    {
        return self::user()?->isStaff() ?? false;
    }

    public static function attempt(string $email, string $password): ?User
    {
        $user = User::findByEmail($email);

        if ($user === null) {
            // Comparaison factice pour ne pas révéler par le temps
            // si l'e-mail existe.
            password_verify($password, '$2y$12$usesomesillystringfoeriodimideandthesalt.0000000000000000000000');

            return null;
        }

        if (!password_verify($password, (string) $user->getAttribute('password_hash'))) {
            return null;
        }

        if (!$user->isActive()) {
            return null;
        }

        return $user;
    }

    public static function login(User $user): void
    {
        Session::regenerate();
        Session::set(self::SESSION_KEY, $user->id());

        $user->setAttribute('last_login_at', date('Y-m-d H:i:s'));
        $user->save();

        self::$user     = $user;
        self::$resolved = true;
    }

    public static function logout(): void
    {
        Csrf::invalidate();
        Session::forget(self::SESSION_KEY);
        Session::destroy();

        self::$user     = null;
        self::$resolved = true;
    }

    public static function refresh(): void
    {
        self::$user     = null;
        self::$resolved = false;
    }

    /** Purge l'état en mémoire (tests, changement de contexte). */
    public static function reset(): void
    {
        self::$user     = null;
        self::$resolved = false;
    }
}
