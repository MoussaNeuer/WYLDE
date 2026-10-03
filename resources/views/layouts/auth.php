<?php
/**
 * Layout authentification (client et admin).
 * Centré, minimal, sans navigation.
 *
 * @var string $content
 */
use App\Core\Csrf;

$title = $title ?? config('app.name', 'WYLDE');
$brand = $brand ?? config('app.name', 'WYLDE');
?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>" data-locale="<?= e(locale()) ?>" data-csrf="<?= e(Csrf::token()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#000000">
    <meta name="robots" content="noindex, nofollow">

    <title><?= e($title) ?> — <?= e($brand) ?></title>
    <link rel="icon" href="<?= e(asset('assets/images/logo/favicon.svg')) ?>" type="image/svg+xml">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= e(asset('assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/components.css')) ?>">
</head>
<body class="auth-body">

<main class="auth-shell">
    <a class="auth-shell__brand" href="<?= e(url('/')) ?>"><?= e($brand) ?></a>

    <div class="auth-card">
        <?= $content ?>
    </div>

    <p class="auth-shell__foot">
        <a href="<?= e(url('/locale/' . alt_locale())) ?>" lang="<?= e(alt_locale()) ?>">
            <?= e(strtoupper(alt_locale())) ?>
        </a>
        <span aria-hidden="true">·</span>
        <a href="<?= e(url('/')) ?>"><?= e(__('errors.go_home')) ?></a>
    </p>
</main>

<div class="toast-stack" id="toast-stack" aria-live="polite" aria-atomic="true"></div>

<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
