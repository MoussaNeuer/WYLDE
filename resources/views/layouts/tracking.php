<?php
/**
 * Layout « suivi de commande ».
 *
 * Page à part entière SANS header ni footer : le client est concentré
 * sur l'état de sa commande. Chargée en scripts/CSS légers, elle reste
 * cohérente avec la charte (base.css + composants partagés).
 *
 * @var string $content
 */
use App\Core\Csrf;

$title       = $title ?? config('app.name', 'WYLDE');
$description = $metaDescription ?? __('tracking.title');
$bodyClass   = $bodyClass ?? 'tracking-page';
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
    <meta name="robots" content="noindex, nofollow">

    <title><?= e($title) ?></title>

    <meta name="description" content="<?= e($description) ?>">
    <link rel="canonical" href="<?= e(url($currentPath ?? '')) ?>">

    <meta property="og:site_name" content="<?= e(config('app.name', 'WYLDE')) ?>">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="<?= e(str_replace('-', '_', $locale)) ?>">

    <?php component('app-icons'); ?>

    <link rel="stylesheet" href="<?= e(asset('assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/tracking.css')) ?>">

    <?php section_if('head'); ?>
</head>
<body class="wylde <?= e($bodyClass) ?>">

    <a href="#tracking" class="skip-link"><?= e(__('common.skip_to_content')) ?></a>

    <main id="tracking" class="tracking-main">
        <?= $content ?>
    </main>

    <div class="toast-stack" id="toast-stack" aria-live="polite" aria-atomic="true"></div>

    <script src="<?= e(asset('assets/js/order-tracking.js')) ?>" defer></script>
    <?php section_if('scripts'); ?>
</body>
</html>