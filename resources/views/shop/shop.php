<?php
/**
 * Boutique : liste de produits avec tri et pagination.
 *
 * @var string                   $title
 * @var \App\Models\Product[]    $products
 * @var int                      $total
 * @var int                      $page
 * @var int                      $pages
 * @var int                      $perPage
 * @var \App\Models\Category[]   $categories
 * @var \App\Models\Category|null $category
 * @var array<string, mixed>     $filters
 */
$sort = (string) ($filters['sort'] ?? 'recent');
?>
<section class="section">
    <div class="container">
        <header class="page-head">
            <h1 class="page-head__title"><?= e($category?->localizedName() ?? $title) ?></h1>
            <p class="page-head__text"><?= e(__('shop.results', ['count' => $total])) ?></p>
        </header>

        <form method="get" action="<?= e(url('/shop')) ?>" class="shop-toolbar">
            <div class="form-group">
                <label class="sr-only" for="shop-q"><?= e(__('common.search')) ?></label>
                <input class="form-control"
                       type="search"
                       id="shop-q"
                       name="q"
                       value="<?= e((string) ($filters['q'] ?? '')) ?>"
                       placeholder="<?= e(__('common.search')) ?>">
            </div>

            <div class="form-group">
                <label class="sr-only" for="shop-sort"><?= e(__('common.sort')) ?></label>
                <select class="form-select" id="shop-sort" name="sort">
                    <?php foreach (['recent', 'price_asc', 'price_desc', 'popular'] as $key): ?>
                        <option value="<?= e($key) ?>" <?= $sort === $key ? 'selected' : '' ?>>
                            <?= e(__('shop.sort.' . $key)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="btn btn-outline-light" type="submit"><?= e(__('common.apply')) ?></button>
        </form>

        <?php if ($category !== null && $category->parent_id !== null): ?>
            <nav class="breadcrumb-category" aria-label="<?= e(__('common.actions')) ?>">
                <a href="<?= e(url('/shop')) ?>"><?= e(__('nav.shop')) ?></a>
            </nav>
        <?php endif; ?>

        <?php if ($products === []): ?>
            <div class="empty-state">
                <h2 class="empty-state__title"><?= e(__('shop.no_results')) ?></h2>
                <a class="btn btn-light" href="<?= e(url('/shop')) ?>"><?= e(__('shop.clear_filters')) ?></a>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <?php view_partial('components/product-card', ['product' => $product]); ?>
                <?php endforeach; ?>
            </div>

            <?php component('pagination', [
                'current' => $page,
                'pages'   => $pages,
            ]); ?>
        <?php endif; ?>
    </div>
</section>