<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute uniquement en ligne de commande.\n");
}

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "[ERREUR] L'extension ZIP de PHP est absente.\n");
    exit(1);
}

$root   = dirname(__DIR__);
$target = $root . '/deploy-wylde.zip';

$withMedia = in_array('--with-media', $argv, true);

$excludeDirs = ['.git', '.idea', '.vscode', 'vendor', 'node_modules', 'tests'];

$excludeFiles = [
    '/Cahier_des_charges_WYLDE.docx',
    '/WLOGO.png',
    '/background.jpeg',
];

function relativePath(string $root, string $path): string
{
    return '/' . str_replace('\\', '/', substr($path, strlen($root) + 1));
}

function isExcluded(string $rel, bool $isDir, bool $withMedia): bool
{
    foreach ($GLOBALS['excludeDirs'] as $dir) {
        if ($rel === '/' . $dir || str_starts_with($rel, '/' . $dir . '/')) {
            return true;
        }
    }

    if ($isDir) {
        return false;
    }

    if (in_array($rel, $GLOBALS['excludeFiles'], true)) {
        return true;
    }

    $name = basename($rel);

    if ($rel === '/.env' || (str_starts_with($name, '.env.') && !str_ends_with($name, '.example'))) {
        return true;
    }

    if (str_starts_with($name, 'deploy-') && str_ends_with($name, '.zip')) {
        return true;
    }

    if (str_starts_with($rel, '/storage/logs/') && $name !== '.gitkeep') {
        return true;
    }

    if (str_starts_with($rel, '/storage/cache/') && $name !== '.gitkeep') {
        return true;
    }

    if (str_starts_with($rel, '/storage/uploads/')) {
        if ($name === '.gitkeep' || $name === '.htaccess') {
            return false;
        }

        if (str_starts_with($rel, '/storage/uploads/products/')) {
            return !$withMedia;
        }

        return true;
    }

    return false;
}

$directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
$filter    = new RecursiveCallbackFilterIterator(
    $directory,
    static function (SplFileInfo $current) use ($root, $withMedia): bool {
        return !isExcluded(relativePath($root, $current->getPathname()), $current->isDir(), $withMedia);
    }
);
$iterator  = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);

if (is_file($target)) {
    unlink($target);
}

$zip = new ZipArchive();

if ($zip->open($target, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "[ERREUR] Impossible de créer {$target}.\n");
    exit(1);
}

$count = 0;
$top   = [];

foreach ($iterator as $item) {
    $rel = relativePath($root, $item->getPathname());

    if ($item->isDir()) {
        $zip->addEmptyDir($rel);
        continue;
    }

    $zip->addFile($item->getPathname(), $rel);
    $count++;

    $parts = explode('/', ltrim($rel, '/'));
    if (!isset($top[$parts[0]])) {
        $top[$parts[0]] = 0;
    }
    $top[$parts[0]]++;
}

$zip->close();

$size = filesize($target);

fwrite(STDOUT, PHP_EOL);
fwrite(STDOUT, "  Archive créée : " . basename($target) . PHP_EOL);
fwrite(STDOUT, "  Fichiers      : {$count}" . PHP_EOL);
fwrite(STDOUT, "  Taille        : " . number_format($size / 1024, 0, ',', ' ') . " Ko" . PHP_EOL);
fwrite(STDOUT, "  Médias        : " . ($withMedia ? 'inclus' : 'exclus (--with-media pour les inclure)') . PHP_EOL);
fwrite(STDOUT, PHP_EOL . "  Répertoires de premier niveau :" . PHP_EOL);

ksort($top);
foreach ($top as $dir => $total) {
    fwrite(STDOUT, "    - {$dir} ({$total})" . PHP_EOL);
}

fwrite(STDOUT, PHP_EOL);

exit(0);
