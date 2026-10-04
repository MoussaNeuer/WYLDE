<?php
/**
 * Layout de la connexion à l'administration.
 *
 * Page autonome, pensée d'abord pour le mobile : fond noir animé,
 * carte en verre dépoli, animations d'entrée et de focus. Le back-office
 * n'est jamais accessible en navigation instantanée (aucun lien interne),
 * la page est donc rendue comme un document classique.
 *
 * @var string $content
 */
use App\Core\Csrf;

$title  = $title ?? __('admin.login_title');
$credit = site_credit();
?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>" data-locale="<?= e(locale()) ?>" data-csrf="<?= e(Csrf::token()) ?>" data-scope="admin-login">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0A0A0A">
    <meta name="robots" content="noindex, nofollow">

    <title><?= e($title) ?> — <?= e(config('app.name', 'WYLDE')) ?></title>

    <?php component('app-icons'); ?>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= e(asset('assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/admin-auth.css')) ?>">

    <?php section_if('head'); ?>
</head>
<body class="admin-login-body">

<div class="alx" data-admin-login>

    <div class="alx__bg" aria-hidden="true">
        <span class="alx__orb alx__orb--a"></span>
        <span class="alx__orb alx__orb--b"></span>
        <span class="alx__orb alx__orb--c"></span>
        <span class="alx__grid"></span>
        <span class="alx__sweep"></span>
    </div>

    <main class="alx__main">
        <div class="alx__card" data-alx-card>
            <?= $content ?>
        </div>

        <p class="alx__foot">
            <a class="alx__lang" href="<?= e(url('/locale/' . alt_locale())) ?>" lang="<?= e(alt_locale()) ?>">
                <?= e(strtoupper(alt_locale())) ?>
            </a>
            <span aria-hidden="true">·</span>
            <a href="<?= e(url('/')) ?>"><?= e(__('admin.login_back_to_shop')) ?></a>
            <?php if ($credit['name'] !== ''): ?>
                <span aria-hidden="true">·</span>
                <span class="alx__credit">
                    <?= e(__('page.credit_by')) ?>
                    <?php if ($credit['url'] !== null): ?>
                        <a href="<?= e($credit['url']) ?>" rel="noopener nofollow" target="_blank"><?= e($credit['name']) ?></a>
                    <?php else: ?>
                        <?= e($credit['name']) ?>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
        </p>
    </main>
</div>

<div class="toast-stack" id="toast-stack" aria-live="polite" aria-atomic="true"></div>

<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/admin-login.js')) ?>" defer></script>
<?php section_if('scripts'); ?>
</body>
</html>
