<?php
/**
 * Page panier.
 *
 * Mise en page en cartes (plus de tableau) : chaque ligne devient une
 * carte produit avec photo, taille, prix unitaire, quantité ± et total
 * de ligne. Sur grand écran, un résumé latéral reste collé au défilement ;
 * sur téléphone, il passe sous les articles.
 *
 * Tous les repères data-* attendus par cart-page.js sont conservés :
 * le JavaScript pilote les quantités et le retrait sans rechargement,
 * le <form> principal reste le repli sans JavaScript.
 *
 * @var string            $title
 * @var \App\Models\Cart  $cart
 */
$items     = $cart->items();
$total     = $cart->total();
$shipping  = free_shipping_progress($total);
$removeUrl = url('/cart/remove');
?>
<section class="section">
    <div class="container">
        <header class="page-head">
            <h1 class="page-head__title"><?= e($title) ?></h1>
        </header>

        <?php if ($cart->isEmpty()): ?>
            <div class="empty-state">
                <h2 class="empty-state__title"><?= e(__('cart.empty')) ?></h2>
                <p class="empty-state__text"><?= e(__('cart.empty_text')) ?></p>
                <a class="btn btn-light" href="<?= e(url('/shop')) ?>"><?= e(__('nav.shop')) ?></a>
            </div>
        <?php else: ?>
            <?php if ($shipping['enabled']): ?>
                <div class="freeship<?= $shipping['reached'] ? ' freeship--reached' : '' ?>"
                     data-cart-freeship>
                    <p class="freeship__text" data-cart-freeship-text>
                        <?= e($shipping['reached']
                            ? __('cart.free_shipping.reached')
                            : __('cart.free_shipping.remaining', ['amount' => $shipping['remaining_text']])) ?>
                    </p>
                    <div class="freeship__track" role="progressbar"
                         aria-valuemin="0" aria-valuemax="100"
                         aria-valuenow="<?= e((string) $shipping['percent']) ?>"
                         aria-label="<?= e(__('cart.free_shipping.reached')) ?>"
                         data-cart-freeship-bar>
                        <span class="freeship__fill" style="width: <?= e((string) $shipping['percent']) ?>%"></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error = error_for('quantity')): ?>
                <div class="alert alert--warning" role="status" data-cart-alert><?= e($error) ?></div>
            <?php endif; ?>

            <div class="cart-layout">
                <?php /* Le <form> principal enveloppe les lignes : sans
                         JavaScript, les champs quantité sont soumis et le
                         bouton « Mettre à jour » applique la saisie. */ ?>
                <form id="cart-update" method="post" action="<?= e(url('/cart/update')) ?>"
                      class="cart-form" data-cart-form>
                    <?= csrf_field() ?>

                    <div class="cart-lines" data-cart-table data-cart-rows>
                        <?php foreach ($items as $item): ?>
                            <?php $variantId = (int) $item['variant_id']; ?>
                            <article class="cart-line" data-cart-row="<?= e((string) $variantId) ?>">
                                <?php if ($item['image_path'] !== null): ?>
                                    <a class="cart-line__thumb"
                                       href="<?= e(url('/product/' . $item['product_slug'])) ?>"
                                       aria-hidden="true" tabindex="-1">
                                        <img src="<?= e(upload_url((string) $item['image_path'])) ?>"
                                             alt="<?= e($item['name']) ?>"
                                             width="80" height="100"
                                             loading="lazy" decoding="async">
                                    </a>
                                <?php else: ?>
                                    <span class="cart-line__thumb cart-line__thumb--blank"></span>
                                <?php endif; ?>

                                <div class="cart-line__main">
                                    <div class="cart-line__top">
                                        <div class="cart-line__info">
                                            <a class="cart-line__name"
                                               href="<?= e(url('/product/' . $item['product_slug'])) ?>">
                                                <?= e($item['name']) ?>
                                            </a>
                                            <div class="cart-line__meta">
                                                <?php if (!empty($item['size'])): ?>
                                                    <span class="cart-line__size"><?= e(size_label((string) $item['size'])) ?></span>
                                                <?php endif; ?>
                                                <span class="cart-line__unit"
                                                      data-cart-price="<?= e((string) $variantId) ?>">
                                                    <?= e(money($item['price'])) ?>
                                                </span>
                                            </div>
                                        </div>

                                        <?php /* Le retrait vit dans un <form>
                                                 externe : le bouton en fait le
                                                 lien via son attribut form=. */ ?>
                                        <button class="cart-line__remove"
                                                type="submit"
                                                form="cart-remove-<?= e((string) $variantId) ?>"
                                                data-cart-remove="<?= e((string) $variantId) ?>"
                                                aria-label="<?= e(__('cart.remove')) ?> — <?= e($item['name']) ?>">
                                            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
                                                <path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor"
                                                      stroke-width="1.8" stroke-linecap="round"/>
                                            </svg>
                                        </button>
                                    </div>

                                    <div class="cart-line__bottom">
                                        <?php /* Champ caché : source de vérité du
                                                 formulaire de repli. Le contrôle ±
                                                 le recopie et passe par l'API. */ ?>
                                        <input type="hidden"
                                               name="quantity[<?= e((string) $variantId) ?>]"
                                               value="<?= e((string) $item['quantity']) ?>"
                                               data-cart-qty-input="<?= e((string) $variantId) ?>">

                                        <div class="qty-stepper qty-stepper--sm" data-cart-stepper>
                                            <button type="button"
                                                    class="qty-stepper__btn"
                                                    data-cart-step="<?= e((string) $variantId) ?>"
                                                    data-cart-delta="-1"
                                                    aria-label="<?= e(__('common.quantity')) ?> −">−</button>
                                            <span class="qty-stepper__value"
                                                  aria-live="polite"
                                                  data-cart-qty="<?= e((string) $variantId) ?>"><?= e((string) $item['quantity']) ?></span>
                                            <button type="button"
                                                    class="qty-stepper__btn"
                                                    data-cart-step="<?= e((string) $variantId) ?>"
                                                    data-cart-delta="1"
                                                    aria-label="<?= e(__('common.quantity')) ?> +"
                                                    <?= $item['quantity'] >= $item['stock'] ? 'disabled' : '' ?>>+</button>
                                        </div>

                                        <strong class="cart-line__total"
                                                data-cart-line-total="<?= e((string) $variantId) ?>">
                                            <?= e(money($item['quantity'] * $item['price'])) ?>
                                        </strong>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php /* Repli sans JavaScript : sans lui, « Mettre à jour »
                             reste la seule façon d'appliquer une saisie. */ ?>
                    <button class="btn btn-outline-light btn-block" type="submit"
                            data-cart-update hidden><?= e(__('cart.update')) ?></button>
                </form>

                <aside class="cart-aside">
                    <div class="summary cart-summary">
                        <h2 class="cart-summary__title"><?= e(__('cart.title')) ?></h2>

                        <div class="summary__row">
                            <span><?= e(__('cart.item_count')) ?></span>
                            <span class="amount" data-cart-count><?= e((string) $cart->count()) ?></span>
                        </div>
                        <div class="summary__row summary__row--total">
                            <span><?= e(__('common.total')) ?></span>
                            <span class="amount" data-cart-total><?= e(money($total)) ?></span>
                        </div>

                        <a class="btn btn-light btn-block cart-summary__checkout"
                           href="<?= e(url('/checkout')) ?>"><?= e(__('cart.checkout')) ?></a>
                        <a class="cart-summary__continue"
                           href="<?= e(url('/shop')) ?>"><?= e(__('cart.continue')) ?></a>
                    </div>
                </aside>
            </div>

            <?php foreach ($items as $item): ?>
                <form id="cart-remove-<?= e((string) $item['variant_id']) ?>"
                      method="post"
                      action="<?= e($removeUrl) ?>"
                      data-confirm="<?= e(__('cart.confirm_remove')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="variant_id" value="<?= e((string) $item['variant_id']) ?>">
                </form>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<?php /* Contrôles quantité sans rechargement : la page panier en a besoin,
         le tiroir et la fiche produit non. */ ?>
<?php App\Core\View::start('scripts'); ?>
<script src="<?= e(asset('assets/js/cart-page.js')) ?>" defer></script>
<?php App\Core\View::stop(); ?>