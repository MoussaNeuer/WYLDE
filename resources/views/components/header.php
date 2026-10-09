<?php
/**
 * En-tête de la boutique.
 *
 * Menu mobile en overlay plein écran (§10), recherche accessible
 * depuis ce menu, sélecteur de langue FR/EN conservé en session et en
 * cookie, compteur de panier alimenté en Ajax.
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
                <img src="<?= e(asset('assets/images/logo/logo.svg')) ?>"
                     alt="<?= e(config('app.name', 'WYLDE')) ?>" width="60" height="24">
            </a>

<div class="site-header__actions">

                  <?php /* La recherche vit dans le menu hamburger :
                           le bouton de l'en-tête serait de trop sur
                           mobile et le champ de la boutique ne couvre
                           pas les autres pages. */ ?>
                  <a class="header-action header-action--fav"
                     href="<?= e(url('/favorites')) ?>"
                     data-favorites-link
                     data-toast-add="<?= e(__('favorites.added')) ?>"
                     data-toast-removed="<?= e(__('favorites.removed')) ?>"
                     data-toast-unavailable="<?= e(__('favorites.unavailable')) ?>"
                     aria-label="<?= e(__('favorites.open')) ?>"
                     title="<?= e(__('favorites.title')) ?>">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                           stroke-width="1.5" aria-hidden="true">
                          <path d="M12 20.5S3.5 15 3.5 9.2A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8c0 5.8-8.5 11.3-8.5 11.3z"/>
                      </svg>
                      <span class="header-action__count" data-favorites-count
                            data-count="0" hidden aria-hidden="true"></span>
                  </a>

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

<?php /* Menu : overlay plein écran, la recherche en est le premier
         élément — un geste, un champ, des résultats. */ ?>
<div class="offcanvas offcanvas-end mobile-nav" tabindex="-1" id="mobileNav"
     aria-labelledby="mobileNavLabel">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title h5" id="mobileNavLabel"><?= e(__('nav.menu')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                aria-label="<?= e(__('nav.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">

        <?php /* Recherche intégrée au menu : ouvre l'overlay dédié,
                 qui gère la saisie instantanée et les résultats. */ ?>
        <button class="mobile-nav__search" type="button"
                data-search-open
                aria-haspopup="dialog"
                aria-controls="searchOverlay"
                aria-label="<?= e(__('search.open')) ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.5" aria-hidden="true">
                <circle cx="11" cy="11" r="6.5"/>
                <path d="M16 16l4.5 4.5"/>
            </svg>
            <span><?= e(__('search.title')) ?></span>
        </button>

<nav class="mobile-nav__links" aria-label="<?= e(__('common.main_menu')) ?>">
              <a href="<?= e(url('/shop')) ?>"><?= e(__('nav.shop')) ?></a>
              <a href="<?= e(url('/favorites')) ?>"><?= e(__('nav.favorites')) ?></a>
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