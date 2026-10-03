<?php

declare(strict_types=1);

/**
 * Exécuteur de tests.
 *
 *   php tests/run.php
 *   composer test
 *
 * Regroupe toutes les suites (unit, functional, security).
 * Code de sortie 0 si tous les tests passent, 1 sinon.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/T.php';

$files = [];
foreach (['unit', 'functional', 'security'] as $suite) {
    foreach (glob(__DIR__ . '/' . $suite . '/*.php') ?: [] as $file) {
        $files[] = $file;
    }
}

sort($files);

foreach ($files as $file) {
    require $file;
}

exit(Tests\T::summary());
