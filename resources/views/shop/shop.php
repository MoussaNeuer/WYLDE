<?php
/**
 * Boutique : filtres, résultats et chargement progressif.
 *
 * Le formulaire fonctionne sans JavaScript (navigation classique). Le
 * « Charger plus » et la recherche d'en-tête ne sont que des
 * améliorations : sans elles, la pagination serveur prend le relais.
 *
 * @var string                      $title
 * @var \App\Models\Product[]       $products
 * @var int                         $total
 * @var int                         $shown
 * @var int                         $perPage
 * @var \App\Models\Category[]      $categories
 * @var \App\Models\Category|null   $category
 * @var \App\Models\ProductSize[]   $sizes
 * @var array{min: int, max: int}   $bounds
 * @var array<string, mixed>        $filters
 * @var array<int, array{key: string, label: string, url: string}> $chips
 */

$sort  = (string) ($filters['sort'] ?? 'recent');
$page  = max(1, (int) ($filters['page'] ?? 1));
$path  = $category !== null ? url('/collection/' . $category->slug) : url('/shop');

/* Chaîne de requête de la sélection, sans la page : « Charger plus »
   n'a plus qu'à y ajouter `page=`. Le chemin courant porte déjà le
   préfixe d'installation, le script n'a donc rien à recomposer. */
$query = array_filter(
    $filters,
    static fn ($value): bool => $value !== '' && $value !== null && $value !== false
);
unset($query['page']);
$loadMoreQuery = http_build_query($query);
?>
<section class="section shop" data-shop
         data-load-more-query="<?= e($loadMoreQuery) ?>"
         data-page="<?= e((string) $page) ?>"
         data-total="<?= e((string) $total) ?>"
         data-shown="<?= e((string) $shown) ?>"
         data-total-label="<?= e(__('shop.results', ['count' => $total])) ?>"
         data-counter-template="<?= e(__('shop.counter')) ?>">

    <div class="container">
        <header class="page-head">
            <h1 class="page-head__title"><?= e($category?->localizedName() ?? $title) ?></h1>
            <p class="page-head__text" data-result-count><?= e(__('shop.results', ['count' => $total])) ?></p>
        </header>

        <?php /* Le tiroir mobile est un <details> natif : il se replie sans
                 JavaScript, et le compteur de filtres actifs est lisible
                 avant même de l'ouvrir. */ ?>
        <details class="shop-filters" data-filters <?= $chips !== [] ? 'open' : '' ?>>
            <summary class="shop-filters__toggle">
                <span class="shop-filters__toggle-label"><?= e(__('shop.filters')) ?></span>
                <?php if ($chips !== []): ?>
                    <span class="shop-filters__badge"><?= e((string) count($chips)) ?></span>
                <?php endif; ?>
            </summary>

            <form class="shop-filters__body" method="get" action="<?= e($path) ?>" data-filter-form>
                <div class="shop-filters__group">
                    <label class="form-label" for="shop-q"><?= e(__('common.search')) ?></label>
                    <input class="form-control"
                           type="search"
                           id="shop-q"
                           name="q"
                           value="<?= e((string) ($filters['q'] ?? '')) ?>"
                           placeholder="<?= e(__('shop.search_placeholder')) ?>">
                </div>

                <div class="shop-filters__group">
                    <label class="form-label" for="shop-sort"><?= e(__('common.sort')) ?></label>
                    <select class="form-select" id="shop-sort" name="sort">
                        <?php foreach (['recent', 'price_asc', 'price_desc', 'popular'] as $key): ?>
                            <option value="<?= e($key) ?>" <?= $sort === $key ? 'selected' : '' ?>>
                                <?= e(__('shop.sort.' . $key)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="shop-filters__group">
                    <label class="form-label" for="shop-category"><?= e(__('shop.filter.category')) ?></label>
                    <select class="form-select" id="shop-category" name="category_id">
                        <option value=""><?= e(__('shop.filter.all_categories')) ?></option>
                        <?php foreach ($categories as $item): ?>
                            <?php $catId = (int) $item->id(); ?>
                            <option value="<?= e((string) $catId) ?>"
                                    <?= (int) ($filters['category_id'] ?? 0) === $catId ? 'selected' : '' ?>>
                                <?= e($item->localizedName()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php /* Le catalogue peut n'avoir aucune taille publiée :
                         afficher « XXL » mènerait à une page vide. */ ?>
                <?php if ($sizes !== []): ?>
                    <fieldset class="shop-filters__group shop-filters__sizes">
                        <legend class="form-label"><?= e(__('shop.filter.size')) ?></legend>
                        <div class="shop-filters__pills">
                            <?php foreach ($sizes as $size): ?>
                                <?php $sizeLabel = (string) $size->label; ?>
                                <label class="shop-filters__pill">
                                    <input type="radio"
                                           name="size"
                                           value="<?= e($sizeLabel) ?>"
                                           <?= (string) ($filters['size'] ?? '') === $sizeLabel ? 'checked' : '' ?>>
                                    <span><?= e($sizeLabel) ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if (($filters['size'] ?? '') !== ''): ?>
                                <label class="shop-filters__pill">
                                    <input type="radio" name="size" value="" checked>
                                    <span><?= e(__('shop.filter.any_size')) ?></span>
                                </label>
                            <?php endif; ?>
                        </div>
                    </fieldset>
                <?php endif; ?>

                <div class="shop-filters__group">
                    <span class="form-label"><?= e(__('shop.filter.price')) ?></span>
                    <div class="shop-filters__prices">
                        <label class="sr-only" for="shop-min"><?= e(__('shop.filter.min_price')) ?></label>
                        <input class="form-control"
                               type="number"
                               id="shop-min"
                               name="min_price"
                               inputmode="numeric"
                               min="0"
                               step="100"
                               placeholder="<?= e((string) ($bounds['min'] ?? 0)) ?>"
                               value="<?= e((string) ($filters['min_price'] ?? '')) ?>">
                        <span class="shop-filters__sep" aria-hidden="true">–</span>
                        <label class="sr-only" for="shop-max"><?= e(__('shop.filter.max_price')) ?></label>
                        <input class="form-control"
                               type="number"
                               id="shop-max"
                               name="max_price"
                               inputmode="numeric"
                               min="0"
                               step="100"
                               placeholder="<?= e((string) ($bounds['max'] ?? 0)) ?>"
                               value="<?= e((string) ($filters['max_price'] ?? '')) ?>">
                    </div>
                </div>

                <label class="shop-filters__check">
                    <input type="checkbox"
                           name="in_stock"
                           value="1"
                           <?= !empty($filters['in_stock']) ? 'checked' : '' ?>>
                    <span><?= e(__('shop.filter.in_stock')) ?></span>
                </label>

                <div class="shop-filters__actions">
                    <button class="btn btn-dark" type="submit"><?= e(__('common.apply')) ?></button>
                    <?php if ($chips !== []): ?>
                        <a class="btn btn-outline-light" href="<?= e($path) ?>"><?= e(__('shop.clear_filters')) ?></a>
                    <?php endif; ?>
                </div>
            </form>
        </details>

        <?php if ($chips !== []): ?>
            <div class="shop-chips">
                <?php foreach ($chips as $chip): ?>
                    <a class="shop-chip" href="<?= e($chip['url']) ?>">
                        <span><?= e($chip['label']) ?></span>
                        <span class="shop-chip__x" aria-hidden="true">×</span>
                        <span class="sr-only"><?= e(__('shop.chip_remove')) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($products === []): ?>
            <div class="empty-state">
                <h2 class="empty-state__title"><?= e(__('shop.no_results')) ?></h2>
                <a class="btn btn-light" href="<?= e($path) ?>"><?= e(__('shop.clear_filters')) ?></a>
            </div>
        <?php else: ?>
            <div class="product-grid" data-product-grid>
                <?php foreach ($products as $product): ?>
                    <?php view_partial('components/product-card', ['product' => $product]); ?>
                <?php endforeach; ?>
            </div>

            <div class="shop-more">
                <p class="shop-more__counter" data-counter hidden>
                    <?= e(__('shop.counter', ['shown' => $shown, 'total' => $total])) ?>
                </p>

                <?php /* Repli sans JavaScript : la pagination serveur reste
                         dans le document, shop.js la retire seulement quand
                         le « Charger plus » est disponible. */ ?>
                <?php if ($page > 1 || $page * $perPage < $total): ?>
                    <nav class="shop-pagination" data-pagination
                         aria-label="<?= e(__('shop.pagination')) ?>">
                        <?php component('pagination', ['current' => $page, 'pages' => (int) ceil($total / $perPage)]); ?>
                    </nav>
                <?php endif; ?>

                <button class="btn btn-outline-dark shop-more__button"
                        type="button"
                        data-load-more
                        hidden><?= e(__('shop.load_more')) ?></button>

                <p class="shop-more__error" data-load-more-error role="status" hidden></p>
            </div>
        <?php endif; ?>
    </div>
</section>