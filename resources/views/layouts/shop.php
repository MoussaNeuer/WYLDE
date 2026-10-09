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
<html lang="<?= e($locale) ?>" data-locale="<?= e($locale) ?>" data-csrf="<?= e(Csrf::token()) ?>"
      data-base="<?= e(app_base_url()) ?>">
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

    <link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/shop.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/cart.css')) ?>">

    <?php section_if('head'); ?>
</head>
<body class="wylde <?= e($bodyClass) ?>">

    <a href="#main" class="skip-link"><?= e(__('common.skip_to_content')) ?></a>

    <?php view_partial('components/header'); ?>

  <?php view_partial('components/search-overlay'); ?>

    <main id="main" class="site-main">
        <?= $content ?>
    </main>

    <?php view_partial('components/footer'); ?>

    <?php view_partial('components/cart-drawer'); ?>

    <div class="toast-stack" id="toast-stack" aria-live="polite" aria-atomic="true"></div>

    <script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>" defer></script>
    <script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
    <script src="<?= e(asset('assets/js/cart-drawer.js')) ?>" defer></script>
    <script src="<?= e(asset('assets/js/quick-add.js')) ?>" defer></script>
  <script src="<?= e(asset('assets/js/search.js')) ?>" defer></script>
  <script src="<?= e(asset('assets/js/favorites.js')) ?>" defer></script>
  <script src="<?= e(asset('assets/js/shop.js')) ?>" defer></script>
    <?php section_if('scripts'); ?>
</body>
</html>
