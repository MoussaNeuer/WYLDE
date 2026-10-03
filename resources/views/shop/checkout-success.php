<?php
/** @var string $title
 *  @var string|null $reference
 */
?>
<section class="section">
    <div class="container">
        <div class="empty-state checkout-success">
            <h1 class="checkout-success__title"><?= e(__('checkout.success_title')) ?></h1>

            <p class="checkout-success__text">
                <?= e(__('checkout.success_text')) ?>
            </p>

            <?php if (!empty($reference)): ?>
                <p class="checkout-success__reference">
                    <?= e(__('checkout.reference')) ?> :
                    <strong><?= e((string) $reference) ?></strong>
                </p>
            <?php endif; ?>

            <div class="checkout-success__actions">
                <a class="btn btn-light" href="<?= e(url('/')) ?>"><?= e(__('nav.home')) ?></a>
                <a class="btn btn-outline-light" href="<?= e(url('/shop')) ?>"><?= e(__('nav.shop')) ?></a>
            </div>
        </div>
    </div>
</section>