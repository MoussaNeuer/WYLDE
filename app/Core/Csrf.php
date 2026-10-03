<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Jeton CSRF à durée de vie limitée (cf. §13.3).
 *
 * Le jeton est lié à l'identifiant de session : un jeton volé sur un autre
 * Navigateur est rejeté.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf';

    public static function token(): string
    {
        self::ensure();

        $lifetime = (int) Config::get('security.csrf.lifetime', 7200);
        $data     = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_array($data) || !isset($data['token'], $data['hash']) || !is_string($data['token'])) {
            return self::regenerate();
        }

        if (time() - (int) ($data['created'] ?? 0) > $lifetime) {
            return self::regenerate();
        }

        return $data['token'];
    }

    public static function regenerate(): string
    {
        self::ensure();

        $token = bin2hex(random_bytes(32));

        $_SESSION[self::SESSION_KEY] = [
            'token'   => $token,
            'hash'    => self::sessionFingerprint(),
            'created' => time(),
        ];

        return $token;
    }

    public static function verify(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        self::ensure();

        $data = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_array($data) || !isset($data['token'], $data['hash'], $data['created'])) {
            return false;
        }

        $lifetime = (int) Config::get('security.csrf.lifetime', 7200);

        if (time() - (int) $data['created'] > $lifetime) {
            return false;
        }

        // Le jeton doit appartenir à la session courante.
        if (!hash_equals((string) $data['hash'], self::sessionFingerprint())) {
            return false;
        }

        return hash_equals((string) $data['token'], $token);
    }

    /**
     * Vérifie le jeton présent dans le corps de la requête ou l'en-tête
     * X-CSRF-Token (requêtes Fetch/AJAX).
     */
    public static function verifyRequest(Request $request): bool
    {
        $field  = (string) Config::get('security.csrf.token_name', '_token');
        $header = (string) Config::get('security.csrf.header_name', 'X-CSRF-Token');

        $token = $request->post($field);

        if (!is_string($token) || $token === '') {
            $fromHeader = $request->header($header);
            $token      = is_string($fromHeader) ? $fromHeader : null;
        }

        return self::verify($token);
    }

    /**
     * Recalcule l'empreinte après une régénération de l'identifiant de
     * session (connexion, rotation périodique) en conservant le jeton
     * courant : sans cela, tout formulaire déjà ouvert deviendrait
     * irremplaçable et l'empreinte resterait liée à un identifiant mort.
     */
    public static function rebind(): void
    {
        if (!Session::isStarted()) {
            return;
        }

        $data = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_array($data) || !isset($data['token']) || !is_string($data['token'])) {
            return;
        }

        $data['hash'] = self::sessionFingerprint();

        $_SESSION[self::SESSION_KEY] = $data;
    }

    public static function invalidate(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    private static function ensure(): void
    {
        if (Session::isStarted()) {
            return;
        }

        Session::start();
    }

    private static function sessionFingerprint(): string
    {
        return hash('sha256', session_id() . '|' . (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    }
}
