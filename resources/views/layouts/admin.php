<?php
/**
 * Layout back-office.
 *
 * Desktop : sidebar fixe. Mobile (priorité) : la sidebar devient un drawer
 * plein écran qui regroupe la recherche, la navigation, le profil de
 * l'administrateur et la déconnexion. La barre du haut ne garde que trois
 * actions tactiles : menu, recherche, compte.
 *
 * @var string $content
 */
use App\Core\Csrf;

$title = $title ?? config('app.name', 'WYLDE');
$user  = auth();
$route = current_route() ?? '';
$q     = (string) ($_GET['q'] ?? '');

/** Icônes au trait (stroke), sans dépendance externe. */
$icon = static function (string $name, string $class = 'admin-nav__icon'): string {
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
        'close'      => '<path d="M6 6l12 12M18 6 6 18"/>',
        'search'     => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'logout'     => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 8 6 12l4 4"/><path d="M6 12h9"/>',
        'user'       => '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>',
        'chevron'    => '<path d="m9 6 6 6-6 6"/>',
        'bell'       => '<path d="M18 8.5a6 6 0 1 0-12 0c0 5-2 6.5-2 6.5h16s-2-1.5-2-6.5Z"/><path d="M10.3 19a2 2 0 0 0 3.4 0"/>',
        'help'       => '<circle cx="12" cy="12" r="8.5"/><path d="M9.6 9.4a2.5 2.5 0 1 1 3.3 2.4c-.6.2-.9.8-.9 1.4v.4"/><path d="M12 17h.01"/>',
        'plus'       => '<path d="M12 5v14M5 12h14"/>',
        'keyboard'   => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><path d="M6 10h.01M9.5 10h.01M13 10h.01M16.5 10h.01M8 14h8"/>',
        'sizes'      => '<path d="M3 6h18M3 12h18M3 18h18"/><path d="M7 3v6M17 9v6M11 15v6"/>',
        'mail'       => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
    ];

    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . ($paths[$name] ?? '') . '</svg>';
};

// Notifications : déduites de l'état réel de la boutique (voir
// NotificationService). Uniquement les trois plus urgentes dans le menu,
// la page dédiée affiche tout.
$alerts = \App\Services\NotificationService::alerts();
$badge  = array_sum(array_column($alerts, 'count'));
$urgent = array_slice($alerts, 0, 3);

// Messages de contact : badge de non-lus, indépendant des alertes métier.
$messages = \App\Models\ContactMessage::countUnread();

$nav = [
    ['url' => '/admin', 'label' => __('admin.dashboard'), 'icon' => 'dashboard', 'match' => $route === 'admin', 'key' => '1'],
    ['group' => __('admin.products')],
    ['url' => '/admin/products', 'label' => __('admin.products'), 'icon' => 'products', 'match' => (bool) is_active('admin/products')],
    ['url' => '/admin/categories', 'label' => __('admin.categories'), 'icon' => 'categories', 'match' => (bool) is_active('admin/categories')],
    ['url' => '/admin/inventory', 'label' => __('admin.inventory_title'), 'icon' => 'inventory', 'match' => (bool) is_active('admin/inventory')],
    ['group' => __('admin.orders')],
    ['url' => '/admin/orders', 'label' => __('admin.orders'), 'icon' => 'orders', 'match' => (bool) is_active('admin/orders')],
    ['url' => '/admin/customers', 'label' => __('admin.customers'), 'icon' => 'customers', 'match' => (bool) is_active('admin/customers')],
    ['group' => __('admin.messages.title')],
    ['url' => '/admin/messages', 'label' => __('admin.messages.title'), 'icon' => 'mail', 'match' => (bool) is_active('admin/messages'), 'badge' => $messages],
    ['group' => __('admin.analytics_title')],
    ['url' => '/admin/analytics', 'label' => __('admin.analytics_title'), 'icon' => 'analytics', 'match' => (bool) is_active('admin/analytics')],
    ['group' => __('admin.notifications.title')],
    ['url' => '/admin/notifications', 'label' => __('admin.notifications.title'), 'icon' => 'bell', 'match' => (bool) is_active('admin/notifications'), 'badge' => $badge],
    ['url' => '/admin/help', 'label' => __('admin.help.title'), 'icon' => 'help', 'match' => (bool) is_active('admin/help')],
    ['group' => __('admin.settings')],
    ['url' => '/admin/settings', 'label' => __('admin.settings'), 'icon' => 'settings', 'match' => (bool) is_active('admin/settings')],
    ['url' => '/admin/shipping-zones', 'label' => __('admin.shipping.title'), 'icon' => 'shipping', 'match' => (bool) is_active('admin/shipping-zones')],
    ['url' => '/admin/sizes', 'label' => __('admin.sizes.title'), 'icon' => 'sizes', 'match' => (bool) is_active('admin/sizes')],
    ['url' => '/admin/profile', 'label' => __('admin.profile'), 'icon' => 'profile', 'match' => (bool) is_active('admin/profile')],
    ['url' => '/admin/security', 'label' => __('admin.security_title'), 'icon' => 'security', 'match' => (bool) is_active('admin/security')],
];

// Raccourcis : les écrans qu'on ouvre dix fois par jour, en un geste.
// 'key' est la touche réellement écoutée par admin.js, 'hint' ce qui est
// affiché sur la tuile.
$quick = [
    ['url' => '/admin/products/create', 'label' => __('admin.quick_actions.add_product'), 'icon' => 'plus', 'key' => 'n', 'hint' => 'N'],
    ['url' => '/admin/orders',           'label' => __('admin.quick_actions.view_orders'),  'icon' => 'orders', 'key' => '2', 'hint' => 'Alt+2'],
    ['url' => '/admin/inventory',        'label' => __('admin.quick_actions.manage_stock'), 'icon' => 'inventory', 'key' => '3', 'hint' => 'Alt+3'],
    ['url' => '/admin/customers',        'label' => __('admin.customers'),   'icon' => 'customers', 'key' => '4', 'hint' => 'Alt+4'],
    ['url' => '/admin/analytics',        'label' => __('admin.analytics_title'), 'icon' => 'analytics', 'key' => '5', 'hint' => 'Alt+5'],
    ['url' => '/admin/help',             'label' => __('admin.help.title'),  'icon' => 'help', 'key' => '?', 'hint' => '?'],
];

$email = (string) ($user?->email ?? '');
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

    <link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">

    <?php section_if('head'); ?>
</head>
<body class="admin-body">

<div class="admin-shell" data-shell>

    <div class="sidebar-backdrop" data-sidebar-backdrop hidden></div>

    <aside class="admin-sidebar" id="adminSidebar" data-sidebar aria-label="<?= e(__('common.main_menu')) ?>">

        <div class="admin-sidebar__top">
            <a class="admin-sidebar__brand" href="<?= e(url('/admin')) ?>">
                <img src="<?= e(asset('assets/images/logo/logo.svg')) ?>"
                     alt="<?= e(config('app.name', 'WYLDE')) ?>" width="65" height="26">
            </a>

            <button class="admin-sidebar__close" type="button" data-sidebar-close
                    aria-label="<?= e(__('admin.close_menu')) ?>">
                <?= $icon('close') ?>
            </button>
        </div>

        <form class="admin-search" action="<?= e(url('/admin/products')) ?>" method="get" role="search"
              data-admin-search>
            <label class="visually-hidden" for="adminSearch"><?= e(__('common.search')) ?></label>
            <input type="search" id="adminSearch" name="q" inputmode="search"
                   autocomplete="off"
                   placeholder="<?= e(__('admin.search_placeholder')) ?>"
                   aria-label="<?= e(__('admin.search_hint')) ?>"
                   value="<?= e($q) ?>">
            <span class="admin-search__icon" aria-hidden="true"><?= $icon('search', 'admin-search__glyph') ?></span>
        </form>

        <!-- Notifications, accès rapide et navigation partagent un seul
             défilement : sur un écran court, rien ne peut être coupé. -->
        <div class="admin-drawer__scroll">

        <div class="admin-drawer__block">
            <a class="admin-bell" href="<?= e(url('/admin/notifications')) ?>">
                <span class="admin-bell__icon" aria-hidden="true"><?= $icon('bell', 'admin-bell__glyph') ?></span>
                <span class="admin-bell__text">
                    <strong><?= e(__('admin.notifications.title')) ?></strong>
                    <small>
                        <?php if ($badge === 0): ?>
                            <?= e(__('admin.notifications.all_clear')) ?>
                        <?php else: ?>
                            <?= e(__('admin.notifications.summary', ['count' => $badge])) ?>
                        <?php endif; ?>
                    </small>
                </span>
                <?php if ($badge > 0): ?>
                    <span class="admin-bell__badge admin-bell__badge--<?= e(\App\Services\NotificationService::worstLevel()) ?>"><?= $badge > 99 ? '99+' : $badge ?></span>
                <?php endif; ?>
            </a>

            <?php if ($urgent !== []): ?>
                <ul class="admin-alerts">
                    <?php foreach ($urgent as $alert): ?>
                        <li>
                            <a href="<?= e(url((string) $alert['url'])) ?>">
                                <span class="admin-alerts__dot is-<?= e((string) $alert['level']) ?>" aria-hidden="true"></span>
                                <span class="admin-alerts__label"><?= e((string) $alert['label']) ?></span>
                                <span class="admin-alerts__count"><?= (int) $alert['count'] ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="admin-drawer__block">
            <p class="admin-block__label"><?= e(__('admin.quick_access')) ?></p>
            <div class="admin-quick">
                <?php foreach ($quick as $item): ?>
                    <a class="admin-quick__tile" href="<?= e(url($item['url'])) ?>" data-key="<?= e($item['key']) ?>">
                        <span class="admin-quick__icon" aria-hidden="true"><?= $icon($item['icon'], 'admin-quick__glyph') ?></span>
                        <span class="admin-quick__label"><?= e($item['label']) ?></span>
                        <kbd class="admin-quick__key"><?= e($item['hint']) ?></kbd>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <nav class="admin-nav" aria-label="<?= e(__('common.main_menu')) ?>">
            <?php foreach ($nav as $item): ?>
                <?php if (isset($item['group'])): ?>
                    <p class="admin-nav__group"><?= e($item['group']) ?></p>
                    <?php continue; ?>
                <?php endif; ?>

                <a href="<?= e(url($item['url'])) ?>"
                   class="admin-nav__link<?= $item['match'] ? ' is-active' : '' ?>"
                   <?= $item['match'] ? 'aria-current="page"' : '' ?>
                   <?= isset($item['key']) ? 'data-key="' . e($item['key']) . '"' : '' ?>>
                    <?= $icon($item['icon']) ?>
                    <span><?= e($item['label']) ?></span>
                    <?php if (!empty($item['badge'])): ?>
                        <span class="admin-nav__badge"><?= (int) $item['badge'] > 99 ? '99+' : (int) $item['badge'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        </div>

        <div class="admin-account" data-sidebar-account>
            <p class="admin-account__label"><?= e(__('admin.account')) ?></p>

            <a class="admin-account__card" href="<?= e(url('/admin/profile')) ?>">
                <span class="admin-account__avatar" aria-hidden="true"><?= e($user?->initials() ?? '?') ?></span>
                <span class="admin-account__meta">
                    <strong><?= e((string) ($user?->name ?? '')) ?></strong>
                    <small><?= e($email) ?></small>
                </span>
                <?= $icon('chevron', 'admin-account__chevron') ?>
            </a>

            <div class="admin-account__links">
                <a href="<?= e(url('/admin/profile')) ?>">
                    <?= $icon('profile') ?><span><?= e(__('admin.profile')) ?></span>
                </a>
                <a href="<?= e(url('/admin/security')) ?>">
                    <?= $icon('security') ?><span><?= e(__('admin.security_title')) ?></span>
                </a>
                <a href="<?= e(url('/admin/help')) ?>" data-key="?">
                    <?= $icon('help') ?><span><?= e(__('admin.help.title')) ?></span>
                </a>
                <a href="<?= e(url('/')) ?>" data-no-fast-nav>
                    <?= $icon('external') ?><span><?= e(__('admin.view_site')) ?></span>
                </a>
            </div>

            <details class="admin-keys">
                <summary class="admin-keys__summary">
                    <?= $icon('keyboard') ?><span><?= e(__('admin.shortcuts')) ?></span>
                </summary>
                <ul class="admin-keys__list">
                    <li><kbd>Alt</kbd><kbd>1</kbd><span><?= e(__('admin.help.shortcut_nav')) ?></span></li>
                    <li><kbd>/</kbd><span><?= e(__('admin.help.shortcut_search')) ?></span></li>
                    <li><kbd>N</kbd><span><?= e(__('admin.help.shortcut_new')) ?></span></li>
                    <li><kbd>B</kbd><span><?= e(__('admin.help.shortcut_menu')) ?></span></li>
                    <li><kbd>?</kbd><span><?= e(__('admin.help.shortcut_help')) ?></span></li>
                    <li><kbd>Échap</kbd><span><?= e(__('admin.help.shortcut_close')) ?></span></li>
                </ul>
            </details>

            <form method="post" action="<?= e(url('/logout')) ?>" class="admin-account__logout-form">
                <?= csrf_field() ?>
                <button type="submit" class="admin-account__logout">
                    <?= $icon('logout') ?><span><?= e(__('admin.logout')) ?></span>
                </button>
            </form>
        </div>
    </aside>

    <div class="admin-main">

        <header class="admin-header">
            <button class="admin-header__burger" type="button" data-sidebar-toggle
                    aria-controls="adminSidebar" aria-expanded="false"
                    aria-label="<?= e(__('admin.open_menu')) ?>">
                <?= $icon('menu', 'admin-header__glyph') ?>
            </button>

            <div class="admin-header__title">
                <span class="admin-header__title-text"><?= e($title) ?></span>
            </div>

            <form class="admin-search admin-search--inline" action="<?= e(url('/admin/products')) ?>" method="get" role="search">
                <label class="visually-hidden" for="adminSearchInline"><?= e(__('common.search')) ?></label>
                <input type="search" id="adminSearchInline" name="q" inputmode="search"
                       autocomplete="off"
                       placeholder="<?= e(__('admin.search_placeholder')) ?>"
                       value="<?= e($q) ?>">
                <span class="admin-search__icon" aria-hidden="true"><?= $icon('search', 'admin-search__glyph') ?></span>
            </form>

            <div class="admin-header__right">
                <button class="admin-header__icon-btn admin-header__icon-btn--search" type="button" data-sidebar-open="search"
                        aria-label="<?= e(__('common.search')) ?>">
                    <?= $icon('search', 'admin-header__glyph') ?>
                </button>

                <a class="admin-header__icon-btn" href="<?= e(url('/admin/profile')) ?>"
                   data-sidebar-open="account" aria-label="<?= e(__('admin.account')) ?>">
                    <span class="admin-header__initials" aria-hidden="true"><?= e($user?->initials() ?? '?') ?></span>
                </a>
            </div>
        </header>

        <main class="admin-content" id="adminContent">
            <?= $content ?>
        </main>
    </div>
</div>

<div class="toast-stack" id="toast-stack" aria-live="polite" aria-atomic="true"></div>

<script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/admin.js')) ?>" defer></script>
<?php section_if('scripts'); ?>
</body>
</html>
