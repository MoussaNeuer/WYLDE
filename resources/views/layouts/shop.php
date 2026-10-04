<?php
/**
 * Layout boutique (client).
 *
 * Variables attendues : $content, $title, $metaDescription.
 * Utilise e() partout : aucune donnée n'est injectée sans échappement.
 *
 * @var string $content
 */
use App\Core\Csrf;

$title       = $title ?? config('app.name', 'WYLDE');
$description = $metaDescription ?? null;
$bodyClass   = $bodyClass ?? '';
$locale      = locale();
$isEn        = $locale === 'en';
?>
<!DOCTYPE html>
<html lang="<?= e($locale) ?>" data-locale="<?= e($locale) ?>" data-csrf="<?= e(Csrf::token()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#FFFFFF">

    <title><?= e($title) ?></title>

    <meta name="description" content="<?= e($description ?? __('app.tagline')) ?>">
    <link rel="canonical" href="<?= e(url($currentPath ?? '/')) ?>">

    <meta property="og:site_name" content="<?= e(config('app.name', 'WYLDE')) ?>">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($description ?? __('app.tagline')) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e(url($currentPath ?? '/')) ?>">
    <meta property="og:locale" content="<?= e(str_replace('-', '_', $locale)) ?>">
    <meta property="og:image" content="<?= e(asset('assets/images/logo/og-image.png')) ?>">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?= e(config('app.name', 'WYLDE')) ?>">

    <meta name="twitter:card" content="summary_large_image">

    <?php component('app-icons'); ?>

    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= e(asset('assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/shop.css')) ?>">

    <?php section_if('head'); ?>
</head>
<body class="wylde <?= e($bodyClass) ?>">

    <a href="#main" class="skip-link"><?= e(__('common.skip_to_content')) ?></a>

    <?php view_partial('components/header'); ?>

    <main id="main" class="site-main">
        <?= $content ?>
    </main>

    <?php view_partial('components/footer'); ?>

    <div class="toast-stack" id="toast-stack" aria-live="polite" aria-atomic="true"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous" defer></script>
    <script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
    <?php section_if('scripts'); ?>
</body>
</html>
