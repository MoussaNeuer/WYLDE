<?php
/**
 * Accueil — version Phase 1.
 *
 * Vérifie la chaîne complète : route, contrôleur, base, vue, i18n.
 * La composition réelle (hero, storytelling, best-sellers) est prévue
 * en Phase 3.
 *
 * @var int                     $productsCount
 * @var array<int, array>       $newProducts
 * @var array<int, array>       $bestSellers
 * @var bool                    $dbOk
 */
?>
<section class="hero">
    <div class="container">
        <p class="hero__eyebrow">
            <img src="<?= e(asset('assets/images/logo/logo.png')) ?>" alt="<?= e(config('app.name', 'WYLDE')) ?>" width="85" height="34">
        </p>
        <h1 class="hero__title"><?= e(__('app.tagline')) ?></h1>
        <a href="<?= e(url('/shop')) ?>" class="btn btn-light btn-lg"><?= e(__('nav.shop')) ?></a>
    </div>
</section>

<?php if (!$dbOk): ?>
    <div class="container">
        <div class="alert alert--error" role="alert">
            <?= e(__('errors.server')) ?>
        </div>
    </div>
<?php endif; ?>

<section class="section">
    <div class="container">
        <header class="section__head">
            <h2 class="section__title"><?= e(__('nav.new')) ?></h2>
            <a href="<?= e(url('/shop?label=new')) ?>" class="link-more">
                <?= e(__('nav.shop')) ?>
            </a>
        </header>

        <?php if ($newProducts === []): ?>
            <?php view_partial('components/empty-state', [
                'title' => __('admin.empty.products'),
                'action' => url('/shop'),
                'actionLabel' => __('nav.shop'),
            ]); ?>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($newProducts as $product): ?>
                    <?php view_partial('components/product-card', ['product' => $product]); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($bestSellers !== []): ?>
    <section class="section section--muted">
        <div class="container">
            <header class="section__head">
                <h2 class="section__title"><?= e(__('nav.best')) ?></h2>
            </header>
            <div class="product-grid">
                <?php foreach ($bestSellers as $product): ?>
                    <?php view_partial('components/product-card', ['product' => $product]); ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
