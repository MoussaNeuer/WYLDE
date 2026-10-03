<?php
/**
 * Layout back-office.
 *
 * Sidebar fixe sur desktop, drawer animé sur mobile (§8.1).
 *
 * @var string $content
 */
use App\Core\Csrf;

$title = $title ?? config('app.name', 'WYLDE');
$user  = auth();
$route = current_route() ?? '';
?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>" data-locale="<?= e(locale()) ?>" data-csrf="<?= e(Csrf::token()) ?>" data-scope="admin">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#000000">
    <meta name="robots" content="noindex, nofollow">

    <title><?= e($title) ?> — <?= e(config('app.name', 'WYLDE')) ?></title>

    <link rel="icon" href="<?= e(asset('assets/images/logo/favicon.svg')) ?>" type="image/svg+xml">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= e(asset('assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">

    <?php section_if('head'); ?>
</head>
<body class="admin-body">

<div class="admin-shell" data-shell>

    <aside class="admin-sidebar" id="adminSidebar" data-sidebar>
        <div class="admin-sidebar__brand">
            <a href="<?= e(url('/admin')) ?>"><?= e(config('app.name', 'WYLDE')) ?></a>
        </div>

        <nav class="admin-sidebar__nav" aria-label="<?= e(__('common.main_menu')) ?>">
            <a href="<?= e(url('/admin')) ?>"
               class="<?= e($route === 'admin' ? 'is-active' : '') ?>">
                <?= e(__('admin.dashboard')) ?>
            </a>

            <p class="admin-sidebar__group"><?= e(__('admin.products')) ?></p>
            <a href="<?= e(url('/admin/products')) ?>"
               class="<?= e(is_active('admin/products')) ?>"><?= e(__('admin.products')) ?></a>
            <a href="<?= e(url('/admin/categories')) ?>"
               class="<?= e(is_active('admin/categories')) ?>"><?= e(__('admin.categories')) ?></a>
            <a href="<?= e(url('/admin/inventory')) ?>"
               class="<?= e(is_active('admin/inventory')) ?>"><?= e(__('admin.inventory')) ?></a>

            <p class="admin-sidebar__group"><?= e(__('admin.orders')) ?></p>
            <a href="<?= e(url('/admin/orders')) ?>"
               class="<?= e(is_active('admin/orders')) ?>"><?= e(__('admin.orders')) ?></a>
            <a href="<?= e(url('/admin/customers')) ?>"
               class="<?= e(is_active('admin/customers')) ?>"><?= e(__('admin.customers')) ?></a>

            <p class="admin-sidebar__group"><?= e(__('admin.analytics')) ?></p>
            <a href="<?= e(url('/admin/analytics')) ?>"
               class="<?= e(is_active('admin/analytics')) ?>"><?= e(__('admin.analytics')) ?></a>

            <p class="admin-sidebar__group"><?= e(__('admin.settings')) ?></p>
            <a href="<?= e(url('/admin/settings')) ?>"
               class="<?= e(is_active('admin/settings')) ?>"><?= e(__('admin.settings')) ?></a>
            <a href="<?= e(url('/admin/shipping-zones')) ?>"
               class="<?= e(is_active('admin/shipping-zones')) ?>"><?= e(__('admin.shipping.title')) ?></a>
            <a href="<?= e(url('/admin/profile')) ?>"
               class="<?= e(is_active('admin/profile')) ?>"><?= e(__('admin.profile')) ?></a>
            <a href="<?= e(url('/admin/security')) ?>"
               class="<?= e(is_active('admin/security')) ?>"><?= e(__('admin.security')) ?></a>
        </nav>

        <div class="admin-sidebar__foot">
            <a href="<?= e(url('/')) ?>"><?= e(__('admin.view_site')) ?></a>
        </div>
    </aside>

    <div class="admin-main">

        <header class="admin-header">
            <button class="admin-header__burger" type="button" data-sidebar-toggle
                    aria-controls="adminSidebar" aria-expanded="false"
                    aria-label="<?= e(__('common.toggle_menu')) ?>">
                <span aria-hidden="true">☰</span>
            </button>

            <form class="admin-header__search" action="<?= e(url('/admin/products')) ?>" method="get" role="search">
                <label class="visually-hidden" for="adminSearch"><?= e(__('common.search')) ?></label>
                <input type="search" id="adminSearch" name="q"
                       placeholder="<?= e(__('common.search')) ?>"
                       value="<?= e((string) ($_GET['q'] ?? '')) ?>">
            </form>

            <div class="admin-header__right">
                <a class="admin-header__profile" href="<?= e(url('/admin/profile')) ?>">
                    <span class="admin-header__initials" aria-hidden="true"><?= e($user?->initials() ?? '?') ?></span>
                    <span class="admin-header__name"><?= e((string) ($user?->name ?? '')) ?></span>
                </a>

                <form method="post" action="<?= e(url('/logout')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="admin-header__logout"><?= e(__('admin.logout')) ?></button>
                </form>
            </div>
        </header>

        <main class="admin-content">
            <?= $content ?>
        </main>
    </div>
</div>

<div class="sidebar-backdrop" data-sidebar-backdrop hidden></div>

<div class="toast-stack" id="toast-stack" aria-live="polite" aria-atomic="true"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous" defer></script>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/admin.js')) ?>" defer></script>
<?php section_if('scripts'); ?>
</body>
</html>
