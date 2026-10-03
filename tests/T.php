<?php

declare(strict_types=1);

namespace Tests;

use RuntimeException;
use Throwable;

/**
 * Micro framework de test, sans aucune dépendance externe.
 *
 * Usage :
 *   T::group('Helpers');
 *   T::it('e() échappe le HTML', static function (): void {
 *       T::same('&lt;b&gt;', e('<b>'));
 *   });
 */
final class T
{
    public static int $passed = 0;

    public static int $failed = 0;

    /** @var array<int, string> */
    public static array $failures = [];

    private static string $group = '(général)';

    private static int $assertions = 0;

    public static function group(string $name): void
    {
        self::$group = $name;
        echo PHP_EOL . $name . PHP_EOL;
    }

    public static function it(string $name, callable $fn): void
    {
        self::$assertions = 0;

        try {
            $fn();
        } catch (Throwable $e) {
            self::$failed++;
            self::$failures[] = self::$group . ' / ' . $name . ' : ' . $e->getMessage();
            echo '  [FAIL] ' . $name . PHP_EOL . '         ' . $e->getMessage() . PHP_EOL;

            return;
        }

        if (self::$assertions === 0) {
            echo '  [vide] ' . $name . ' (aucune assertion)' . PHP_EOL;

            return;
        }

        self::$passed++;
        echo '  [ok]   ' . $name . PHP_EOL;
    }

    public static function same(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::$assertions++;

        if ($expected !== $actual) {
            $prefix = $message !== '' ? $message . ' — ' : '';

            throw new RuntimeException($prefix . 'attendu ' . self::describe($expected) . ', reçu ' . self::describe($actual));
        }
    }

    public static function true(bool $condition, string $message = ''): void
    {
        self::$assertions++;

        if (!$condition) {
            throw new RuntimeException($message !== '' ? $message : 'condition attendue vraie');
        }
    }

    public static function false(bool $condition, string $message = ''): void
    {
        self::true(!$condition, $message !== '' ? $message : 'condition attendue fausse');
    }

    public static function null(mixed $value, string $message = ''): void
    {
        self::$assertions++;

        if ($value !== null) {
            throw new RuntimeException(($message !== '' ? $message . ' — ' : '') . 'attendu null, reçu ' . self::describe($value));
        }
    }

    public static function notNull(mixed $value, string $message = ''): void
    {
        self::$assertions++;

        if ($value === null) {
            throw new RuntimeException($message !== '' ? $message : 'valeur non nulle attendue');
        }
    }

    public static function contains(string $needle, string $haystack, string $message = ''): void
    {
        self::$assertions++;

        if (!str_contains($haystack, $needle)) {
            $prefix = $message !== '' ? $message . ' — ' : '';

            throw new RuntimeException($prefix . self::describe($haystack) . ' ne contient pas ' . self::describe($needle));
        }
    }

    public static function matches(string $pattern, string $subject, string $message = ''): void
    {
        self::$assertions++;

        if (preg_match($pattern, $subject) !== 1) {
            $prefix = $message !== '' ? $message . ' — ' : '';

            throw new RuntimeException($prefix . self::describe($subject) . ' ne correspond pas à ' . $pattern);
        }
    }

    /**
     * Exécute $fn et vérifie qu'elle lève une exception du type attendu.
     */
    public static function throws(callable $fn, string $exceptionClass, string $message = ''): Throwable
    {
        self::$assertions++;

        try {
            $fn();
        } catch (Throwable $e) {
            if (!$e instanceof $exceptionClass) {
                $prefix = $message !== '' ? $message . ' — ' : '';

                throw new RuntimeException($prefix . 'attendu ' . $exceptionClass . ', reçu ' . $e::class);
            }

            return $e;
        }

        $prefix = $message !== '' ? $message . ' — ' : '';

        throw new RuntimeException($prefix . 'aucune exception ' . $exceptionClass . ' levée');
    }

    public static function summary(): int
    {
        $total = self::$passed + self::$failed;

        echo PHP_EOL . str_repeat('-', 60) . PHP_EOL;

        if (self::$failed > 0) {
            echo 'ECHEC : ' . self::$failed . ' test(s) sur ' . $total . PHP_EOL;

            foreach (self::$failures as $failure) {
                echo '  - ' . $failure . PHP_EOL;
            }

            return 1;
        }

        echo 'SUCCES : ' . $total . ' test(s)' . PHP_EOL;

        return 0;
    }

    private static function describe(mixed $value): string
    {
        if (is_string($value)) {
            return "'" . $value . "'";
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: 'array';
        }

        return (string) $value;
    }
}
