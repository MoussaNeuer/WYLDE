<?php

declare(strict_types=1);

/**
 * Régénération des variantes WebP (400 / 800 / 1600).
 *
 * Lancez-le une fois après le déploiement, ou après avoir ajouté des
 * images par l'admin :
 *     php tools/thumbnails.php
 *
 * Le script parcourt storage/uploads et génère les variantes manquantes.
 * Il est idempotent : les variantes déjà présentes sont ignorées.
 */

// Amorçage du framework (autoload, config, environnement).
// Reproduit le démarrage de public/index.php sans session ni routeur.
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();
\App\Core\Autoloader::loadHelpers();

\App\Core\Env::load(BASE_PATH . '/.env');
\App\Core\Config::load(BASE_PATH . '/config');

date_default_timezone_set((string) \App\Core\Config::get('app.timezone', 'UTC'));
mb_internal_encoding('UTF-8');

use App\Services\ImageService;
use App\Services\UploadService;

if (!ImageService::webpAvailable()) {
    fwrite(STDERR, "GD n'a pas le support WebP : aucune variante ne sera créée.\n");
    exit(1);
}

$base = UploadService::basePath();

if ($base === '' || !is_dir($base)) {
    fwrite(STDERR, "Dossier uploads introuvable : {$base}\n");
    exit(1);
}

$widths = implode(', ', ImageService::widths());
echo "Variantes WebP — largeurs : {$widths}\n";
echo "Base : {$base}\n\n";

$created  = 0;
$existing = 0;
$errors   = 0;
$files    = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
);

/** @var SplFileInfo $file */
foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $extension = strtolower($file->getExtension());

    // On ne génère des variantes que depuis les formats source.
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'avif'], true)) {
        continue;
    }

    $files++;

    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
    $stem     = preg_replace('/\.[^.]+$/', '', $relative) ?? $relative;

    $before = count(ImageService::variantsFor($relative));
    $result = ImageService::generateVariants($relative);
    $after  = count($result);

    if ($after > $before) {
        $created += $after - $before;
        $existing += $before;
        echo "  + " . ($after - $before) . " variante(s)  {$relative}\n";
    } elseif ($after > 0) {
        $existing += $after;
    } else {
        $errors++;
        echo "  ! échec  {$relative}\n";
    }
}

echo "\n" . str_repeat('-', 50) . "\n";
echo "Images analysées : {$files}\n";
echo "Variantes créées  : {$created}\n";
echo "Variantes déjà présentes : {$existing}\n";

if ($errors > 0) {
    echo "Échecs : {$errors} (images non décodables ?)\n";
    exit(1);
}

echo "Terminé.\n";