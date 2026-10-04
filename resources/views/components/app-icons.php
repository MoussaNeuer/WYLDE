<?php
/**
 * Icône de l'application, source unique pour les trois layouts.
 *
 * Le même dessin sert partout : onglet de navigateur, écran d'accueil iOS,
 * installation Android (PWA), tuile Windows. Le SVG est la version de
 * référence, les PNG ne sont que des replis pour les navigateurs anciens.
 *
 * Les fichiers sont générés à partir de la géométrie de favicon.svg.
 */
$appName = (string) config('app.name', 'WYLDE');
?>
<link rel="icon" href="<?= e(asset('assets/images/logo/favicon.svg')) ?>" type="image/svg+xml">
<link rel="icon" href="<?= e(asset('assets/images/logo/favicon-48.png')) ?>" sizes="48x48" type="image/png">
<link rel="icon" href="<?= e(asset('assets/images/logo/favicon-32.png')) ?>" sizes="32x32" type="image/png">
<link rel="icon" href="<?= e(asset('assets/images/logo/favicon-16.png')) ?>" sizes="16x16" type="image/png">
<link rel="apple-touch-icon" href="<?= e(asset('assets/images/logo/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(url('/site.webmanifest')) ?>">

<meta name="application-name" content="<?= e($appName) ?>">
<meta name="apple-mobile-web-app-title" content="<?= e($appName) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="msapplication-TileColor" content="#FFFFFF">
<meta name="msapplication-TileImage" content="<?= e(asset('assets/images/logo/mstile-150.png')) ?>">
