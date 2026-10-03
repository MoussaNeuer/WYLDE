<?php
/** @var string $title */
?>
<section class="section">
    <div class="container">
        <header class="page-head">
            <h1 class="page-head__title"><?= e($title) ?></h1>
        </header>

        <div class="prose">
            <p><?= e(__('page.terms_intro')) ?></p>

            <h2><?= e(__('page.terms_seller_title')) ?></h2>
            <p><?= e(__('page.terms_seller_text')) ?></p>

            <h2><?= e(__('page.terms_orders_title')) ?></h2>
            <p><?= e(__('page.terms_orders_text')) ?></p>

            <h2><?= e(__('page.terms_pricing_title')) ?></h2>
            <p><?= e(__('page.terms_pricing_text')) ?></p>

            <h2><?= e(__('page.terms_shipping_title')) ?></h2>
            <p><?= e(__('page.terms_shipping_text')) ?></p>

            <h2><?= e(__('page.terms_payment_title')) ?></h2>
            <p><?= e(__('page.terms_payment_text')) ?></p>

            <h2><?= e(__('page.terms_returns_title')) ?></h2>
            <p><?= e(__('page.terms_returns_text')) ?></p>
        </div>
    </div>
</section>
