<?php
/**
 * Galerie d'un produit : dépôt, réorganisation, image principale.
 *
 * La galerie fonctionne en asynchrone (admin.js) : le dépôt par
 * glisser-déposer, la promotion en image principale, la suppression et le
 * réordonnancement mettent à jour la page sans rechargement.
 *
 * @var \App\Models\Product $product
 * @var array<int, \App\Models\ProductImage> $images
 */
component('toast');

$productId = (int) $product->id();
?>
<div class="admin-page">

    <a class="admin-page__back" href="<?= e(url('/admin/products')) ?>">← <?= e(__('admin.products')) ?></a>

    <header class="admin-page__head">
        <h1 class="admin-page__title"><?= e(__('admin.media_title')) ?></h1>
        <div class="admin-page__tools">
            <a class="btn btn-outline-light btn-sm"
               href="<?= e(url('/admin/products/' . $productId . '/edit')) ?>"><?= e(__('common.edit')) ?></a>
        </div>
    </header>

    <section class="admin-panel" data-media-manager
             data-mode="async"
             data-product-id="<?= e((string) $productId) ?>"
             data-upload-url="<?= e(url('/api/admin/products/' . $productId . '/media')) ?>"
             data-reorder-url="<?= e(url('/api/admin/products/' . $productId . '/media/reorder')) ?>">
        <div class="admin-panel__head">
            <h2 class="admin-panel__title"><?= e($product->name) ?></h2>
        </div>

        <div class="admin-panel__head" style="display:block;padding:1.2rem">
            <label class="media-drop" data-media-drop for="f-images">
                <span class="media-drop__icon" aria-hidden="true">↑</span>
                <strong><?= e(__('admin.product.images')) ?></strong>
                <span class="media-drop__hint"><?= e(__('admin.media.hint')) ?></span>
                <input type="file" id="f-images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple hidden>
            </label>

            <ul class="media-grid is-sortable" data-media-gallery>
                <?php foreach ($images as $image): ?>
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

            <p class="media-drop__hint" data-media-empty<?= $images === [] ? '' : ' hidden' ?>>
                <?= e(__('admin.media.empty')) ?>
            </p>
        </div>
    </section>
</div>
