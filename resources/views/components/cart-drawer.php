<?php
/**
 * Mini-panier en tiroir (offcanvas Bootstrap).
 *
 * Le contenu est rendu vide côté serveur : c'est /api/cart qui fournit
 * les lignes au chargement puis après chaque mutation. Sans JavaScript,
 * le lien « Voir le panier » mène à la page panier complète (§ B5).
 *
 * Aucun accès base ici : le tiroir n'existe qu'avec JavaScript, et
 * app.js interroge déjà /api/cart au démarrage. Le compteur de l'en-tête
 * et la barre de livraison offerterecupèrent le même état.
 */
?>
<div class="offcanvas offcanvas-end cart-drawer" tabindex="-1" id="cartDrawer"
     aria-labelledby="cartDrawerTitle" data-cart-drawer>
    <div class="offcanvas-header cart-drawer__header">
        <h2 class="offcanvas-title cart-drawer__title" id="cartDrawerTitle">
            <?= e(__('cart.mini_title')) ?>
            <span class="cart-drawer__count" data-cart-drawer-count hidden></span>
        </h2>
        <button type="button" class="cart-drawer__close" data-bs-dismiss="offcanvas"
                data-cart-drawer-close aria-label="<?= e(__('cart.mini_title')) ?>">&times;</button>
    </div>

    <div class="offcanvas-body cart-drawer__body">
        <div class="cart-drawer__shipping" data-cart-drawer-shipping hidden>
            <p class="cart-drawer__shipping-text" data-cart-drawer-shipping-text></p>
            <div class="cart-drawer__shipping-track" role="progressbar"
                 aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"
                 data-cart-drawer-shipping-bar>
                <span class="cart-drawer__shipping-fill" style="width: 0%"></span>
            </div>
        </div>

        <div class="cart-drawer__items" data-cart-drawer-items></div>

        <p class="cart-drawer__empty" data-cart-drawer-empty>
            <?= e(__('cart.mini_empty')) ?>
        </p>
    </div>

    <div class="cart-drawer__footer" data-cart-drawer-footer hidden>
        <div class="cart-drawer__total">
            <span><?= e(__('cart.subtotal')) ?></span>
            <strong data-cart-drawer-total>0</strong>
        </div>

        <a class="btn btn-primary cart-drawer__checkout" href="<?= e(url('/checkout')) ?>"
           data-cart-drawer-checkout><?= e(__('cart.mini_checkout')) ?></a>

        <a class="cart-drawer__view" href="<?= e(url('/cart')) ?>"
           data-cart-drawer-view><?= e(__('cart.mini_open')) ?></a>
    </div>
</div>