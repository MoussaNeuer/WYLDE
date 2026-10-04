<?php
/**
 * En-tête de la boutique.
 *
 * Menu mobile en offcanvas (§10), sélecteur de langue FR/EN conservé en
 * session et en cookie, compteur de panier alimenté en Ajax.
 *
 * @var string|null $currentPath
 */
$locale      = locale();
$isEn        = $locale === 'en';
$cartCount   = (int) (\App\Core\View::getShared('cart_count') ?? 0);
$user        = auth();
?>
<header class="site-header" data-header>
    <div class="container">
        <div class="site-header__inner">

            <button class="site-header__burger"
                    type="button"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#mobileNav"
                    aria-controls="mobileNav"
                    aria-label="<?= e(__('common.toggle_menu')) ?>">
                <span class="burger-line" aria-hidden="true"></span>
                <span class="burger-line" aria-hidden="true"></span>
                <span class="burger-line" aria-hidden="true"></span>
            </button>

            <a class="site-logo" href="<?= e(url('/')) ?>" aria-label="<?= e(config('app.name', 'WYLDE')) ?>">
                <img src="<?= e(asset('assets/images/logo/logo.png')) ?>"
                     alt="<?= e(config('app.name', 'WYLDE')) ?>" width="60" height="24">
            </a>

            <div class="site-header__actions">

                <a class="header-action" href="<?= e(url($user ? '/account' : '/register')) ?>"
                   aria-label="<?= e($user ? __('account.title') : __('account.register')) ?>"
                   title="<?= e($user ? __('account.title') : __('account.register')) ?>">
                    <?php if ($user): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.5" aria-hidden="true">
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M4 20c0-4 3.6-6 8-6s8 2 8 6"/>
                        </svg>
                    <?php else: ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.5" aria-hidden="true">
                            <circle cx="9.5" cy="8" r="3.8"/>
                            <path d="M2.5 20c0-3.9 3.1-5.8 7-5.8 1.1 0 2.1.15 3 .43"/>
                            <path d="M17.5 13.5v6M14.5 16.5h6"/>
                        </svg>
                    <?php endif; ?>
                </a>

                <a class="header-action header-action--cart"
                   href="<?= e(url('/cart')) ?>"
                   aria-label="<?= e(__('nav.cart')) ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.5" aria-hidden="true">
                        <path d="M6 7h12l-1 13H7L6 7z"/>
                        <path d="M9 7V5a3 3 0 0 1 6 0v2"/>
                    </svg>
                    <span class="cart-count" data-cart-count
                          data-count="<?= e($cartCount) ?>"
                          aria-live="polite"><?= e((string) $cartCount) ?></span>
                </a>

            </div>
        </div>
    </div>
</header>

<?php if ($user && $user->isStaff()): ?>
    <div class="staff-bar">
        <div class="container">
            <a href="<?= e(url('/admin')) ?>"><?= e(__('admin.dashboard')) ?></a>
            <span class="staff-bar__sep" aria-hidden="true">·</span>
            <a href="<?= e(url('/')) ?>"><?= e(__('admin.view_site')) ?></a>
        </div>
    </div>
<?php endif; ?>

<div class="offcanvas offcanvas-end mobile-nav" tabindex="-1" id="mobileNav"
     aria-labelledby="mobileNavLabel">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title h5" id="mobileNavLabel"><?= e(__('nav.menu')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                aria-label="<?= e(__('nav.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <nav class="mobile-nav__links" aria-label="<?= e(__('common.main_menu')) ?>">
            <a href="<?= e(url('/shop')) ?>"><?= e(__('nav.shop')) ?></a>
            <a href="<?= e(url('/about')) ?>"><?= e(__('nav.about')) ?></a>
            <a href="<?= e(url('/contact')) ?>"><?= e(__('nav.contact')) ?></a>
            <a href="<?= e(url('/account')) ?>"><?= e(__('nav.account')) ?></a>
            <a href="<?= e(url('/cart')) ?>"><?= e(__('nav.cart')) ?></a>
        </nav>

        <div class="mobile-nav__lang" role="group" aria-label="<?= e(__('nav.language')) ?>">
            <span class="mobile-nav__lang-label"><?= e(__('nav.language')) ?></span>
            <div class="mobile-nav__lang-options">
                <a href="<?= e(url('/locale/fr')) ?>" lang="fr" hreflang="fr" data-no-csrf
                   class="<?= e($locale === 'fr' ? 'is-current' : '') ?>"
                   <?= $locale === 'fr' ? 'aria-current="true"' : '' ?>>Français</a>
                <a href="<?= e(url('/locale/en')) ?>" lang="en" hreflang="en" data-no-csrf
                   class="<?= e($locale === 'en' ? 'is-current' : '') ?>"
                   <?= $locale === 'en' ? 'aria-current="true"' : '' ?>>English</a>
            </div>
        </div>
    </div>
</div>
