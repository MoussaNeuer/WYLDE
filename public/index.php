<?php

declare(strict_types=1);

/**
 * Point d'entrée unique de l'application.
 *
 * Tout le trafic est réécrit vers ce fichier par public/.htaccess :
 * aucun fichier de app/, config/, storage/ ou database/ n'est
 * directement accessible.
 */

use App\Core\Autoloader;
use App\Core\Config;
use App\Core\Env;
use App\Core\ErrorHandler;
use App\Core\Lang;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\SecurityHeaders;
use App\Core\Session;

define('APP_START', microtime(true));
define('BASE_PATH', dirname(__DIR__));

// 1. Autoloading. Composer est optionnel : le projet n'a aucune
//    dépendance externe, un chargeur PSR-4 suffit.
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
} else {
    require BASE_PATH . '/app/Core/Autoloader.php';
    Autoloader::register();
    Autoloader::loadHelpers();
}

if (!function_exists('__')) {
    http_response_code(500);
    exit('Erreur fatale : helpers non chargés.');
}

// 2. Environnement et configuration.
Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));
mb_internal_encoding('UTF-8');

if (Config::isDebug()) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    // Les erreurs ne sont jamais affichées au visiteur (cf. §13.5).
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

// 3. Gestion centralisée des erreurs, avant tout le reste.
ErrorHandler::register();

// 4. Session durcie et langue.
Session::start();

$request = Request::capture();
Lang::boot($request);

// 5. Sécurité : toujours envoyer les headers de base.
SecurityHeaders::send();

try {
    $router = new Router();

    require BASE_PATH . '/routes/web.php';

    $response = $router->dispatch($request);
} catch (Throwable $e) {
    ErrorHandler::handleException($e);

    exit;
}

// 6. Journalisation de la requête si nécessaire.
if (Config::isDebug()) {
    Logger::debug(sprintf(
        '%s %s -> %d en %.1fms (%d requêtes SQL)',
        $request->method(),
        $request->path(),
        $response->status(),
        (microtime(true) - APP_START) * 1000,
        \App\Core\Database::queryCount()
    ));
}

$response->send();
