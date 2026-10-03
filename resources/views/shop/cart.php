<?php
/** @var string $title
 *  @var \App\Models\Cart $cart
 */
$items = $cart->items();
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
            <form method="post" action="<?= e(url('/cart/update')) ?>" class="cart-form">
                <?= csrf_field() ?>

                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><?= e(__('common.product')) ?></th>
                                <th><?= e(__('common.price')) ?></th>
                                <th><?= e(__('common.quantity')) ?></th>
                                <th><?= e(__('common.total')) ?></th>
                                <th><span class="sr-only"><?= e(__('common.actions')) ?></span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td data-label="<?= e(__('common.product')) ?>">
                                        <a class="cart-item__name" href="<?= e(url('/product/' . $item['product_slug'])) ?>">
                                            <?= e($item['name']) ?>
                                        </a>
                                        <?php if (!empty($item['size']) && $item['size'] !== 'UNIQUE'): ?>
                                            <span class="cart-item__meta"><?= e($item['size']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="<?= e(__('common.price')) ?>"><?= money($item['price']) ?></td>
                                    <td data-label="<?= e(__('common.quantity')) ?>">
                                        <label class="sr-only" for="qty-<?= e((string) $item['variant_id']) ?>">
                                            <?= e(__('common.quantity')) ?>
                                        </label>
                                        <input class="form-control qty-input"
                                               type="number"
                                               id="qty-<?= e((string) $item['variant_id']) ?>"
                                               name="quantity[<?= e((string) $item['variant_id']) ?>]"
                                               value="<?= e((string) $item['quantity']) ?>"
                                               min="1"
                                               max="<?= e((string) max(1, $item['stock'])) ?>"
                                               data-cart-qty>
                                    </td>
                                    <td data-label="<?= e(__('common.total')) ?>"><?= money($item['quantity'] * $item['price']) ?></td>
                                    <td data-label="<?= e(__('common.actions')) ?>">
                                        <form method="post" action="<?= e(url('/cart/remove')) ?>" data-confirm="<?= e(__('cart.confirm_remove')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="variant_id" value="<?= e((string) $item['variant_id']) ?>">
                                            <button class="btn btn-ghost btn-sm" type="submit"><?= e(__('cart.remove')) ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="cart-foot">
                    <a class="btn btn-ghost" href="<?= e(url('/shop')) ?>"><?= e(__('cart.continue')) ?></a>
                    <button class="btn btn-outline-light" type="submit" data-cart-update>
                        <?= e(__('cart.update')) ?>
                    </button>
                    <a class="btn btn-light" href="<?= e(url('/checkout')) ?>"><?= e(__('cart.checkout')) ?></a>
                </div>

                <div class="summary">
                    <div class="summary__row">
                        <span><?= e(__('cart.item_count')) ?></span>
                        <span class="amount"><?= e((string) $cart->count()) ?></span>
                    </div>
                    <div class="summary__row summary__row--total">
                        <span><?= e(__('common.total')) ?></span>
                        <span class="amount"><?= money($cart->total()) ?></span>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>