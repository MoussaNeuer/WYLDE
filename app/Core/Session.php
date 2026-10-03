<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session durcie : cookie HttpOnly, SameSite, mode strict,
 * régénération de l'identifiant à la connexion (cf. §13.1).
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;

            return;
        }

        if (headers_sent()) {
            return;
        }

        $name    = (string) Config::get('security.session.name', 'wylde_session');
        $lifetime = (int) Config::get('security.session.lifetime', 7200);

        session_name($name);

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => (string) Config::get('security.session.cookie_path', '/'),
            'domain'   => '',
            'secure'   => (bool) Config::get('security.session.secure', false),
            'httponly' => (bool) Config::get('security.session.httponly', true),
            'samesite' => (string) Config::get('security.session.samesite', 'Lax'),
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        // session.sid_length et session.sid_bits_per_character sont
        // dépréciés depuis PHP 8.4 : les valeurs par défaut suffisent.

        session_start();
        self::$started = true;

        self::enforceIdleTimeout($lifetime);
        self::rotatePeriodically();
    }

    private static function enforceIdleTimeout(int $lifetime): void
    {
        $now  = time();
        $seen = (int) ($_SESSION['_last_seen'] ?? 0);

        if ($seen !== 0 && ($now - $seen) > $lifetime) {
            self::destroy();

            return;
        }

        $_SESSION['_last_seen'] = $now;
    }

    private static function rotatePeriodically(): void
    {
        $now       = time();
        $lastRegen = (int) ($_SESSION['_regen_at'] ?? 0);

        if ($lastRegen === 0) {
            $_SESSION['_regen_at'] = $now;

            return;
        }

        if (($now - $lastRegen) > 900) {
            session_regenerate_id(true);
            $_SESSION['_regen_at'] = $now;
            Csrf::rebind();
        }
    }

    /** À appeler juste après une authentification réussie. */
    public static function regenerate(): void
    {
        if (self::$started) {
            session_regenerate_id(true);
            $_SESSION['_regen_at'] = time();
            Csrf::rebind();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Lecture à usage unique : renvoie la valeur puis l'efface.
     * Utilisé notamment pour l'URL de destination après connexion.
     */
    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[$key] ?? $default;

        unset($_SESSION[$key]);

        return $value;
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return $_SESSION;
    }

    // ── Messages flash ───────────────────────────────────────

    public static function flash(string $type, string $message): void
    {
        $messages = $_SESSION['_flash'] ?? [];
        $messages[] = ['type' => $type, 'message' => $message];
        $_SESSION['_flash'] = $messages;
    }

    public static function flashSuccess(string $message): void
    {
        self::flash('success', $message);
    }

    public static function flashError(string $message): void
    {
        self::flash('error', $message);
    }

    /** @return array<int, array{type: string, message: string}> */
    public static function pullFlash(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return $messages;
    }

    // ── Erreurs de validation ────────────────────────────────

    /** @param array<string, string> $errors */
    public static function flashErrors(array $errors, array $oldInput = []): void
    {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old']     = $oldInput;
    }

    /** @return array<string, string> */
    public static function pullErrors(): array
    {
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);

        return $errors;
    }

    /** @return array<string, mixed> */
    public static function pullOldInput(): array
    {
        $old = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);

        return $old;
    }

    public static function id(): string
    {
        return self::$started ? session_id() : '';
    }

    public static function destroy(): void
    {
        if (!self::$started) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies') && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(
                session_name() ?: (string) Config::get('security.session.name', 'wylde_session'),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        self::$started = false;
    }

    public static function isStarted(): bool
    {
        return self::$started;
    }
}
