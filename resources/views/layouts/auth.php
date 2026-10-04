<?php
/**
 * Layout authentification (client et admin).
 * Split-screen : panneau de marque à gauche, formulaire à droite.
 *
 * @var string $content
 */
use App\Core\Csrf;

$title = $title ?? config('app.name', 'WYLDE');
$brand = $brand ?? config('app.name', 'WYLDE');
$credit = site_credit();
?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>" data-locale="<?= e(locale()) ?>" data-csrf="<?= e(Csrf::token()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#FFFFFF">
    <meta name="robots" content="noindex, nofollow">

    <title><?= e($title) ?> — <?= e($brand) ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= e(asset('assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/auth.css')) ?>">

    <?php component('app-icons'); ?>

    <?php section_if('head'); ?>
</head>
<body class="auth-body">

<div class="auth-layout">

    <aside class="auth-aside" aria-hidden="true">
        <img src="<?= e(asset('assets/images/logo/logo-white.svg')) ?>" alt="" width="110" height="44">

        <div class="auth-aside__content">
            <p class="auth-aside__tagline"><?= e(__('app.tagline')) ?></p>
            <ul class="auth-aside__list">
                <li><?= e($brand) ?> — <?= e(strtoupper(date('Y'))) ?></li>
                <?php if ($credit['name'] !== ''): ?>
                    <li>
                        <?= e(__('page.credit_by')) ?>
                        <?php if ($credit['url'] !== null): ?>
                            <a class="auth-aside__credit" href="<?= e($credit['url']) ?>"
                               rel="noopener nofollow" target="_blank"><?= e($credit['name']) ?></a>
                        <?php else: ?>
                            <strong class="auth-aside__credit"><?= e($credit['name']) ?></strong>
                        <?php endif; ?>
                    </li>
                <?php endif; ?>
            </ul>
            <p class="auth-aside__foot"><?= e((string) config('app.name', 'WYLDE')) ?></p>
        </div>
    </aside>

    <main class="auth-panel">
        <div class="auth-panel__inner">
            <a class="auth-mobile-brand" href="<?= e(url('/')) ?>">
                <img src="<?= e(asset('assets/images/logo/logo.svg')) ?>" alt="<?= e($brand) ?>" width="85" height="34">
            </a>

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
        </div>
    </main>
</div>

<div class="toast-stack" id="toast-stack" aria-live="polite" aria-atomic="true"></div>

<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
