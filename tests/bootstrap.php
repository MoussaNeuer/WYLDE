<?php

declare(strict_types=1);

/**
 * Amorçage de la suite de tests.
 *
 * Reproduit le démarrage de public/index.php sans session, sans envoi
 * d'en-têtes et sans routeur : uniquement l'autoload, les helpers, la
 * configuration et l'environnement.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/Autoloader.php';

App\Core\Autoloader::register();
App\Core\Autoloader::loadHelpers();

App\Core\Env::load(BASE_PATH . '/.env');
App\Core\Config::load(BASE_PATH . '/config');

date_default_timezone_set((string) App\Core\Config::get('app.timezone', 'UTC'));
mb_internal_encoding('UTF-8');

if (!function_exists('__')) {
    fwrite(STDERR, 'Helpers non chargés : impossible de lancer les tests.' . PHP_EOL);
    exit(1);
}
