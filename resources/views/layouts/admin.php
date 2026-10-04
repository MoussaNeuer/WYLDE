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

/** Icônes au trait (stroke), sans dépendance externe. */
$icon = static function (string $name): string {
    $paths = [
        'dashboard'  => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'products'   => '<path d="M3 7.5 12 3l9 4.5-9 4.5-9-4.5Z"/><path d="M3 7.5V16l9 4.5 9-4.5V7.5"/><path d="M12 12v8.5"/>',
        'categories' => '<path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="M3 13l9 5 9-5"/><path d="M3 18l9 5 9-5"/>',
        'inventory'  => '<rect x="3" y="4" width="18" height="6" rx="1.5"/><rect x="3" y="14" width="18" height="6" rx="1.5"/><path d="M7 7h.01M7 17h.01"/>',
        'orders'     => '<path d="M6 2h12a1 1 0 0 1 1 1v18l-3-2-3 2-3-2-3 2V3a1 1 0 0 1 1-1Z"/><path d="M9 7h6M9 11h6M9 15h3"/>',
        'customers'  => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M17 11a3 3 0 1 0-1.5-5.6M21 20a5 5 0 0 0-4-4.9"/>',
        'analytics'  => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'settings'   => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1"/>',
        'shipping'   => '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7z"/><circle cx="7" cy="18" r="1.8"/><circle cx="17.5" cy="18" r="1.8"/>',
        'profile'    => '<circle cx="12" cy="8" r="3.4"/><path d="M5 20a7 7 0 0 1 14 0"/>',
        'security'   => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'external'   => '<path d="M14 4h6v6M20 4l-8 8M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'menu'       => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    ];

    return '<svg class="admin-sidebar__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . ($paths[$name] ?? '') . '</svg>';
};

$nav = [
    ['url' => '/admin', 'label' => __('admin.dashboard'), 'icon' => 'dashboard', 'match' => $route === 'admin'],
    ['group' => __('admin.products')],
    ['url' => '/admin/products', 'label' => __('admin.products'), 'icon' => 'products', 'match' => (bool) is_active('admin/products')],
    ['url' => '/admin/categories', 'label' => __('admin.categories'), 'icon' => 'categories', 'match' => (bool) is_active('admin/categories')],
    ['url' => '/admin/inventory', 'label' => __('admin.inventory'), 'icon' => 'inventory', 'match' => (bool) is_active('admin/inventory')],
    ['group' => __('admin.orders')],
    ['url' => '/admin/orders', 'label' => __('admin.orders'), 'icon' => 'orders', 'match' => (bool) is_active('admin/orders')],
    ['url' => '/admin/customers', 'label' => __('admin.customers'), 'icon' => 'customers', 'match' => (bool) is_active('admin/customers')],
    ['group' => __('admin.analytics')],
    ['url' => '/admin/analytics', 'label' => __('admin.analytics'), 'icon' => 'analytics', 'match' => (bool) is_active('admin/analytics')],
    ['group' => __('admin.settings')],
    ['url' => '/admin/settings', 'label' => __('admin.settings'), 'icon' => 'settings', 'match' => (bool) is_active('admin/settings')],
    ['url' => '/admin/shipping-zones', 'label' => __('admin.shipping.title'), 'icon' => 'shipping', 'match' => (bool) is_active('admin/shipping-zones')],
    ['url' => '/admin/profile', 'label' => __('admin.profile'), 'icon' => 'profile', 'match' => (bool) is_active('admin/profile')],
    ['url' => '/admin/security', 'label' => __('admin.security'), 'icon' => 'security', 'match' => (bool) is_active('admin/security')],
];
?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>" data-locale="<?= e(locale()) ?>" data-csrf="<?= e(Csrf::token()) ?>" data-scope="admin">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#FFFFFF">
    <meta name="robots" content="noindex, nofollow">

    <title><?= e($title) ?> — <?= e(config('app.name', 'WYLDE')) ?></title>

    <?php component('app-icons'); ?>

    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
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
            <a href="<?= e(url('/admin')) ?>">
                <img src="<?= e(asset('assets/images/logo/logo.png')) ?>"
                     alt="<?= e(config('app.name', 'WYLDE')) ?>" width="65" height="26">
            </a>
        </div>

        <nav class="admin-sidebar__nav" aria-label="<?= e(__('common.main_menu')) ?>">
            <?php foreach ($nav as $item): ?>
                <?php if (isset($item['group'])): ?>
                    <p class="admin-sidebar__group"><?= e($item['group']) ?></p>
                    <?php continue; ?>
                <?php endif; ?>

                <a href="<?= e(url($item['url'])) ?>"
                   class="<?= $item['match'] ? 'is-active' : '' ?>">
                    <?= $icon($item['icon']) ?>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="admin-sidebar__foot">
            <a href="<?= e(url('/')) ?>">
                <?= $icon('external') ?>
                <span><?= e(__('admin.view_site')) ?></span>
            </a>
        </div>
    </aside>

    <div class="admin-main">

        <header class="admin-header">
            <button class="admin-header__burger" type="button" data-sidebar-toggle
                    aria-controls="adminSidebar" aria-expanded="false"
                    aria-label="<?= e(__('common.toggle_menu')) ?>">
                <?= $icon('menu') ?>
            </button>

            <a class="admin-header__brand" href="<?= e(url('/admin')) ?>" aria-label="<?= e(config('app.name', 'WYLDE')) ?>">
                <img src="<?= e(asset('assets/images/logo/logo.png')) ?>" alt="" width="55" height="22">
            </a>

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
