<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lecteur du fichier .env.
 *
 * Format supporté : CLE=valeur, # commentaires, guillemets optionnels.
 * Les valeurs sont mises en cache et converties à la demande.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $vars = [];

    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $pos = strpos($line, '=');

            if ($pos === false) {
                continue;
            }

            $key   = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            if ($key === '') {
                continue;
            }

            self::$vars[$key] = self::unquote($value);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, self::$vars)) {
            return $default;
        }

        $value = self::$vars[$key];

        if ($value === '') {
            return $default;
        }

        return match (strtolower($value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$vars);
    }

    private static function unquote(string $value): string
    {
        $len = strlen($value);

        if ($len >= 2) {
            $first = $value[0];
            $last  = $value[$len - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        // Résolution des séquences \u{...} et \" parasites.
        return strtr($value, [
            '\\n'   => "\n",
            '\\r'   => "\r",
            '\\"'   => '"',
            "\\'"   => "'",
            '\\\\'   => '\\',
        ]);
    }
}
