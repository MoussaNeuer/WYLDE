<?php
/**
 * Accueil — sections par catégorie.
 *
 * Le héros reste sur une seule idée (la marque), sans paragraphe.
 * Chaque section présente ensuite une catégorie réelle de la boutique :
 * « Nouveautés » et « Best-sellers » sont des étiquettes de tri, pas
 * des rubriques — la catégorie, elle, a du sens d'achat.
 *
 * @var array<int, array{id: int, slug: string, name: string, products: array<int, \App\Models\Product>}> $sections
 * @var bool $dbOk
 */
?>
<section class="hero">
    <div class="hero__media" aria-hidden="true">
        <img class="hero__image"
             src="<?= e(asset('assets/images/hero/background.jpeg')) ?>"
             alt="" width="1080" height="607"
             fetchpriority="high" decoding="async">
    </div>
    <div class="hero__veil" aria-hidden="true"></div>

    <div class="container hero__inner">
        <p class="hero__eyebrow">
            <img src="<?= e(asset('assets/images/logo/logo.svg')) ?>" alt="<?= e(config('app.name', 'WYLDE')) ?>" width="85" height="34">
        </p>
        <h1 class="hero__title"><?= e(__('app.tagline')) ?></h1>
        <div class="hero__actions">
            <a href="<?= e(url('/shop')) ?>" class="btn btn-light btn-lg"><?= e(__('home.hero_cta')) ?></a>
        </div>
    </div>

    <a class="hero__scroll" href="#collections">
        <span><?= e(__('home.hero_scroll')) ?></span>
        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
            <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </a>
</section>

<?php if (!$dbOk): ?>
    <div class="container">
        <div class="alert alert--error" role="alert">
            <?= e(__('errors.server')) ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($sections === [] && $dbOk): ?>
    <section class="section">
        <div class="container">
            <?php view_partial('components/empty-state', [
                'title'      => __('admin.empty.products'),
                'action'     => url('/admin/products'),
                'actionLabel'=> __('admin.products'),
            ]); ?>
        </div>
    </section>
<?php endif; ?>

<div id="collections">
    <?php foreach ($sections as $index => $section): ?>
        <section class="section<?= $index % 2 === 1 ? ' section--muted' : '' ?> reveal">
            <div class="container">
                <header class="section__head">
                    <h2 class="section__title"><?= e($section['name']) ?></h2>
                    <a href="<?= e(url('/collection/' . $section['slug'])) ?>" class="link-more">
                        <?= e(__('nav.shop')) ?>
                    </a>
                </header>

                <div class="product-grid stagger">
                    <?php foreach ($section['products'] as $product): ?>
                        <?php view_partial('components/product-card', ['product' => $product]); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<div class="story reveal">
    <div class="container story__grid">
        <div class="story__text">
            <h2 class="story__title"><?= e(__('home.story_title')) ?></h2>
            <p class="story__lead"><?= e(__('home.story_text')) ?></p>
            <a href="<?= e(url('/about')) ?>" class="link-more"><?= e(__('home.story_cta')) ?></a>
        </div>
    </div>
</div>