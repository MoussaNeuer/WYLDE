<?php
/**
 * Formulaire produit : création et édition.
 *
 * Les variantes sont soumises en tableaux parallèles (sizes[],
 * stock_per_size[], sku_per_size[], price_per_size[]) associés par
 * index à variant_ids[], ce qui permet au contrôleur de mettre à jour
 * les lignes existantes sans les dupliquer.
 *
 * Les tailles viennent du catalogue admin (ProductSize) : le champ est
 * une liste déroulante, pas une saisie libre, et ProductValidator refuse
 * toute valeur absente du catalogue.
 *
 * @var \App\Models\Product|null $product
 * @var array<int, \App\Models\Category> $categories
 * @var array<int, string> $statuses
 * @var array<int, string> $labels
 * @var array<int, string> $sizes
 * @var array<int, mixed>|null $variants
 */
use App\Models\Product;

component('toast');

$isEdit  = $product !== null;
$action  = $isEdit ? url('/admin/products/' . $product->id()) : url('/admin/products');

/** Valeur courante d'un champ : saisieprevious → base. */
$value = static fn (string $key, mixed $fallback = null): mixed => old_raw()[$key] ?? $fallback;

$hasSizes = (bool) $value('has_sizes', $isEdit ? (int) $product->has_sizes === 1 : false);

$rows = [];

if ($isEdit && $variants !== null && $variants !== []) {
    foreach ($variants as $variant) {
        $rows[] = [
            'id'    => (int) $variant->id,
            'size'  => (string) $variant->size,
            'sku'   => (string) $variant->sku,
            'stock' => (int) $variant->stock,
            'price' => $variant->price_override,
        ];
    }
} else {
    // Nouvelle fiche : une ligne vide prête à saisir.
    $rows[] = ['id' => 0, 'size' => '', 'sku' => '', 'stock' => 0, 'price' => null];
}

$statusMap = [
    'draft'     => __('admin.product_status.draft'),
    'published' => __('admin.product_status.published'),
    'hidden'    => __('admin.product_status.hidden'),
    'archived'  => __('admin.product_status.archived'),
];

/**
 * Options d'une ligne de variante : le catalogue, plus la valeur déjà
 * enregistrée si elle n'y est plus. Sans cette option de secours,
 * enregistrer le produit changerait silencieusement la taille : la
 * ligne est signalée pour que l'admin la remette au catalogue.
 */
$sizeOptions = static function (string $current) use ($sizes): array {
    $options = [];

    foreach ($sizes as $label) {
        $options[$label] = $label;
    }

    if ($current !== '' && !isset($options[$current])) {
        $options[$current] = $current . ' — ' . __('admin.sizes.not_in_catalog');
    }

    return $options;
};
?>
<div class="admin-page">

    <a class="admin-page__back" href="<?= e(url('/admin/products')) ?>">← <?= e(__('admin.products')) ?></a>

    <header class="admin-page__head">
        <h1 class="admin-page__title"><?= e($isEdit ? $product->name : __('admin.product.new')) ?></h1>
        <div class="admin-page__tools">
            <?php if ($isEdit): ?>
                <a class="btn btn-outline-light btn-sm"
                   href="<?= e(url('/admin/products/' . $product->id() . '/media')) ?>"><?= e(__('admin.media_title')) ?></a>
            <?php endif; ?>
            <a class="btn btn-outline-light btn-sm" href="<?= e(url('/shop/' . ($isEdit ? $product->slug : ''))) ?>">
                <?= e(__('admin.view_site')) ?>
            </a>
        </div>
    </header>

    <form class="admin-form" method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>

        <section class="admin-panel">
            <div class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.product.name')) ?></h2>
            </div>

            <div class="admin-panel__head" style="display:block;padding:1.2rem">
                <div class="admin-form__grid">
                    <div class="admin-field">
                        <label for="f-name"><?= e(__('admin.product.name')) ?> *</label>
                        <input type="text" id="f-name" name="name" required maxlength="180"
                               value="<?= e((string) $value('name', $isEdit ? $product->name : '')) ?>">
                        <?php if (has_error('name')): ?>
                            <p class="admin-field__error"><?= e((string) error_for('name')) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="admin-field">
                        <label for="f-name-en"><?= e(__('admin.product.name_en')) ?></label>
                        <input type="text" id="f-name-en" name="name_en" maxlength="180"
                               value="<?= e((string) $value('name_en', $isEdit ? $product->name_en : '')) ?>">
                        <p class="admin-field__hint"><?= e(__('admin.product.name_en_hint')) ?></p>
                    </div>

                    <div class="admin-field">
                        <label for="f-category"><?= e(__('admin.product.category')) ?></label>
                        <select id="f-category" name="category_id">
                            <option value="">—</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= e((string) $category->id) ?>"
                                    <?= (int) $value('category_id', $isEdit ? $product->category_id : 0) === (int) $category->id ? ' selected' : '' ?>>
                                    <?= e($category->name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="admin-field">
                        <label for="f-sku"><?= e(__('admin.product.sku')) ?></label>
                        <input type="text" id="f-sku" name="sku" maxlength="64"
                               value="<?= e((string) $value('sku', $isEdit ? $product->sku : '')) ?>">
                    </div>
                </div>

                <div class="admin-field" style="margin-top:1rem">
                    <label for="f-short"><?= e(__('admin.product.short_description')) ?></label>
                    <textarea id="f-short" name="short_description" rows="2" maxlength="320"><?= e((string) $value('short_description', $isEdit ? $product->short_description : '')) ?></textarea>
                </div>

                <div class="admin-field" style="margin-top:1rem">
                    <label for="f-desc"><?= e(__('admin.product.description')) ?></label>
                    <textarea id="f-desc" name="description" rows="6"><?= e((string) $value('description', $isEdit ? $product->description : '')) ?></textarea>
                </div>
            </div>
        </section>

        <section class="admin-panel" data-media-manager
                 data-mode="<?= $isEdit ? 'async' : 'deferred' ?>"
                 data-product-id="<?= $isEdit ? e((string) $product->id()) : '' ?>"
                 data-upload-url="<?= e(url('/api/admin/products/' . ($isEdit ? (int) $product->id() : 0) . '/media')) ?>"
                 data-reorder-url="<?= e(url('/api/admin/products/' . ($isEdit ? (int) $product->id() : 0) . '/media/reorder')) ?>">
            <div class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.product.images')) ?></h2>
                <?php if ($isEdit): ?>
                    <div class="admin-panel__actions">
                        <a class="btn btn-sm btn-outline-light"
                           href="<?= e(url('/admin/products/' . $product->id() . '/media')) ?>"><?= e(__('admin.media_title')) ?></a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="admin-panel__head" style="display:block;padding:1.2rem">
                <label class="media-drop" data-media-drop for="f-images">
                    <span class="media-drop__icon" aria-hidden="true">↑</span>
                    <strong><?= e(__('admin.product.images')) ?></strong>
                    <span class="media-drop__hint"><?= e(__('admin.media.hint')) ?></span>
                    <input type="file" id="f-images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple hidden>
                </label>

                <ul class="media-grid is-sortable" data-media-gallery>
                    <?php foreach (($images ?? []) as $image): ?>
                        <li data-media-item data-image-id="<?= e((string) $image->id) ?>">
                            <img src="<?= e(upload_url((string) $image->path)) ?>" alt="" loading="lazy">
                            <?php if ((int) $image->is_primary === 1): ?>
                                <span class="media-grid__primary"><?= e(__('admin.media.primary')) ?></span>
                            <?php endif; ?>
                            <div class="media-tile__overlay">
                                <?php if ((int) $image->is_primary !== 1): ?>
                                    <button type="button" class="icon-btn" data-media-primary
                                            title="<?= e(__('admin.media.make_primary')) ?>"
                                            aria-label="<?= e(__('admin.media.make_primary')) ?>">★</button>
                                <?php endif; ?>
                                <button type="button" class="icon-btn icon-btn--danger" data-media-delete
                                        data-confirm="<?= e(__('common.confirm')) ?>"
                                        title="<?= e(__('common.delete')) ?>"
                                        aria-label="<?= e(__('common.delete')) ?>">×</button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <p class="media-drop__hint" data-media-empty<?= ($images ?? []) === [] ? '' : ' hidden' ?>>
                    <?= e(__('admin.media.empty')) ?>
                </p>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.variants.add')) ?></h2>
            </div>

            <div class="admin-panel__head" style="display:block;padding:1.2rem">
                <div class="admin-form__grid">
                    <div class="admin-field">
                        <label for="f-price"><?= e(__('admin.product.price')) ?> *</label>
                        <input type="number" id="f-price" name="price" min="0" step="100" required
                               value="<?= e((string) $value('price', $isEdit ? $product->price : '')) ?>">
                    </div>

                    <div class="admin-field">
                        <label for="f-sale"><?= e(__('admin.product.sale_price')) ?></label>
                        <input type="number" id="f-sale" name="sale_price" min="0" step="100"
                               value="<?= e((string) $value('sale_price', $isEdit ? $product->sale_price : '')) ?>">
                    </div>

                    <div class="admin-field">
                        <label for="f-cost"><?= e(__('admin.product.cost_price')) ?></label>
                        <input type="number" id="f-cost" name="cost_price" min="0" step="100"
                               value="<?= e((string) $value('cost_price', $isEdit ? $product->cost_price : '')) ?>">
                    </div>
                </div>

                <div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-top:1rem">
                    <label class="admin-check">
                        <input type="checkbox" name="has_sizes" value="1" data-has-sizes<?= $hasSizes ? ' checked' : '' ?>>
                        <?= e(__('admin.product.has_sizes')) ?>
                    </label>

                    <label class="admin-check">
                        <input type="checkbox" name="is_featured" value="1"<?= (int) $value('is_featured', $isEdit ? (int) $product->is_featured : 0) === 1 ? ' checked' : '' ?>>
                        <?= e(__('admin.sort.featured')) ?>
                    </label>
                </div>

                <div class="admin-form__grid" style="margin-top:1.2rem">
                    <div class="admin-field" data-single-stock<?= $hasSizes ? ' hidden' : '' ?>>
                        <label for="f-stock"><?= e(__('admin.product.stock')) ?></label>
                        <input type="number" id="f-stock" name="stock" min="0"
                               value="<?= e((string) $value('stock', $isEdit ? (string) $product->totalStock() : '0')) ?>">
                    </div>

                    <div class="admin-field">
                        <label for="f-status"><?= e(__('admin.product.status')) ?></label>
                        <select id="f-status" name="status">
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= e($status) ?>"
                                    <?= (string) $value('status', $isEdit ? $product->status : 'draft') === $status ? ' selected' : '' ?>>
                                    <?= e($statusMap[$status] ?? $status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="admin-field">
                        <label for="f-label"><?= e(__('admin.product.label')) ?></label>
                        <select id="f-label" name="label">
                            <option value="none"<?= (string) $value('label', $isEdit ? $product->label : 'none') === 'none' ? ' selected' : '' ?>>
                                <?= e(__('common.none')) ?>
                            </option>
                            <?php foreach (array_diff($labels, ['none']) as $label): ?>
                                <option value="<?= e($label) ?>"
                                    <?= (string) $value('label', $isEdit ? $product->label : 'none') === $label ? ' selected' : '' ?>>
                                    <?= e(__('product.' . $label)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </section>

        <section class="admin-panel" data-variant-panel<?= $hasSizes ? '' : ' hidden' ?>>
            <div class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.product.sizes')) ?></h2>
                <div class="admin-panel__actions">
                    <a class="btn btn-sm btn-light" href="<?= e(url('/admin/sizes')) ?>"
                       target="_blank" rel="noopener">
                        <?= e(__('admin.sizes.title')) ?>
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-light" data-add-variant
                            <?= $sizes === [] ? ' disabled' : '' ?>>
                        + <?= e(__('admin.variants.add')) ?>
                    </button>
                </div>
            </div>

            <div class="table-scroll">
                <table class="admin-table" data-variant-table data-no-cards>
                    <thead>
                        <tr>
                            <th><?= e(__('admin.variants.size')) ?></th>
                            <th><?= e(__('admin.variants.sku')) ?></th>
                            <th><?= e(__('admin.variants.stock')) ?></th>
                            <th><?= e(__('admin.variants.price_override')) ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-variant-rows>
                        <?php foreach ($rows as $row): ?>
                            <tr data-variant-row>
                                <td>
                                    <input type="hidden" name="variant_ids[]" value="<?= e((string) $row['id']) ?>">
                                    <select name="sizes[]" data-variant-size
                                            aria-label="<?= e(__('admin.variants.size')) ?>">
                                        <?php if ($row['size'] === ''): ?>
                                            <option value="" selected><?= e(__('admin.variants.pick_size')) ?></option>
                                        <?php endif; ?>
                                        <?php foreach ($sizeOptions((string) $row['size']) as $optionValue => $optionLabel): ?>
                                            <option value="<?= e($optionValue) ?>"<?= (string) $row['size'] === $optionValue ? ' selected' : '' ?>>
                                                <?= e($optionLabel) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="sku_per_size[]" maxlength="64"
                                           value="<?= e((string) $row['sku']) ?>"
                                           aria-label="<?= e(__('admin.variants.sku')) ?>">
                                </td>
                                <td>
                                    <input type="number" name="stock_per_size[]" min="0"
                                           value="<?= e((string) $row['stock']) ?>"
                                           aria-label="<?= e(__('admin.variants.stock')) ?>">
                                </td>
                                <td>
                                    <input type="number" name="price_per_size[]" min="0" step="100"
                                           value="<?= e($row['price'] === null ? '' : (string) $row['price']) ?>"
                                           aria-label="<?= e(__('admin.variants.price_override')) ?>">
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-light" data-remove-variant
                                            aria-label="<?= e(__('common.delete')) ?>">×</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="admin-field__hint" style="padding:0.8rem 1.2rem">
                <?= e($sizes === [] ? __('admin.sizes.empty_hint_form') : __('admin.variants.empty')) ?>
            </p>

            <template data-variant-template>
                <tr data-variant-row>
                    <td>
                        <input type="hidden" name="variant_ids[]" value="0">
                        <select name="sizes[]" data-variant-size aria-label="<?= e(__('admin.variants.size')) ?>">
                            <option value="" selected><?= e(__('admin.variants.pick_size')) ?></option>
                            <?php foreach ($sizes as $label): ?>
                                <option value="<?= e($label) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="text" name="sku_per_size[]" maxlength="64" aria-label="<?= e(__('admin.variants.sku')) ?>"></td>
                    <td><input type="number" name="stock_per_size[]" min="0" value="0" aria-label="<?= e(__('admin.variants.stock')) ?>"></td>
                    <td><input type="number" name="price_per_size[]" min="0" step="100" aria-label="<?= e(__('admin.variants.price_override')) ?>"></td>
                    <td><button type="button" class="btn btn-sm btn-outline-light" data-remove-variant
                                aria-label="<?= e(__('common.delete')) ?>">×</button></td>
                </tr>
            </template>
        </section>

        <section class="admin-panel">
            <div class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.product.seo')) ?></h2>
            </div>

            <div class="admin-panel__head" style="display:block;padding:1.2rem">
                <div class="admin-form__grid">
                    <div class="admin-field">
                        <label for="f-seo-title"><?= e(__('admin.product.seo_title')) ?></label>
                        <input type="text" id="f-seo-title" name="seo_title" maxlength="190"
                               value="<?= e((string) $value('seo_title', $isEdit ? $product->seo_title : '')) ?>">
                    </div>

                    <div class="admin-field">
                        <label for="f-slug">Slug</label>
                        <input type="text" id="f-slug" name="slug" maxlength="200"
                               value="<?= e((string) $value('slug', $isEdit ? $product->slug : '')) ?>">
                        <p class="admin-field__hint"><?= e($isEdit ? (string) $product->slug : 'slugify automatique') ?></p>
                    </div>
                </div>

                <div class="admin-field" style="margin-top:1rem">
                    <label for="f-seo-desc"><?= e(__('admin.product.seo_description')) ?></label>
                    <textarea id="f-seo-desc" name="seo_description" rows="2" maxlength="320"><?= e((string) $value('seo_description', $isEdit ? $product->seo_description : '')) ?></textarea>
                </div>
            </div>
        </section>

        <div class="admin-form__actions">
            <button type="submit" class="btn btn-light"><?= e(__('common.save')) ?></button>
            <a class="btn btn-outline-light" href="<?= e(url('/admin/products')) ?>"><?= e(__('common.cancel')) ?></a>

            <?php if ($isEdit): ?>
                <button type="submit" class="btn btn-danger"
                        form="deleteProductForm" data-confirm="<?= e(str_replace(':name', $product->name, __('admin.product.confirm_delete'))) ?>">
                    <?= e(__('common.delete')) ?>
                </button>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($isEdit): ?>
        <form id="deleteProductForm" method="post" action="<?= e(url('/admin/products/' . $product->id() . '/delete')) ?>" hidden>
            <?= csrf_field() ?>
        </form>
    <?php endif; ?>
</div>