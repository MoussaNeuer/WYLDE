<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Autoloader PSR-4 minimal pour le namespace App\.
 *
 * Le projet n'a aucune dépendance externe : Composer reste optionnel
 * (utile pour l'autocomplétion IDE) mais n'est pas requis à l'exécution.
 * Si vendor/autoload.php est présent il est utilisé, sinon ce chargeur
 * prend le relais.
 */
final class Autoloader
{
    /** @var array<string, string> */
    private static array $prefixes = [];

    public static function register(): void
    {
        self::$prefixes['App\\'] = dirname(__DIR__) . DIRECTORY_SEPARATOR;

        spl_autoload_register([self::class, 'load'], true, false);
    }

    public static function load(string $class): bool
    {
        foreach (self::$prefixes as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $relative = substr($class, strlen($prefix));
            $file     = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

            if (is_file($file)) {
                require $file;

                return true;
            }
        }

        return false;
    }

    /** Charge les fichiers de fonctions globales. */
    public static function loadHelpers(): void
    {
        $file = dirname(__DIR__) . '/Helpers/functions.php';

        if (is_file($file)) {
            require_once $file;
        }
    }
}
