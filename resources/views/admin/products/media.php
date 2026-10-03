<?php
/**
 * Galerie d'un produit : dépôt, réorganisation, image principale.
 *
 * Le réordonnancement se fait par glisser-déposer ou avec les flèches du
 * clavier (admin.js) ; l'ordre courant est de toute façon soumis par POST,
 * donc l'image principale et la suppression fonctionnent sans JavaScript.
 *
 * @var \App\Models\Product $product
 * @var array<int, \App\Models\ProductImage> $images
 */
component('toast');

$mediaUrl = url('/admin/products/' . $product->id() . '/media');
?>
<div class="admin-page">

    <a class="admin-page__back" href="<?= e(url('/admin/products')) ?>">← <?= e(__('admin.products')) ?></a>

    <header class="admin-page__head">
        <h1 class="admin-page__title"><?= e(__('admin.media')) ?></h1>
        <div class="admin-page__tools">
            <a class="btn btn-outline-light btn-sm"
               href="<?= e(url('/admin/products/' . $product->id() . '/edit')) ?>"><?= e(__('common.edit')) ?></a>
        </div>
    </header>

    <p><strong><?= e($product->name) ?></strong></p>

    <form method="post" action="<?= e($mediaUrl) ?>" enctype="multipart/form-data" data-media-upload>
        <?= csrf_field() ?>

        <label class="media-drop" for="f-images" data-drop>
            <strong><?= e(__('admin.product.images')) ?></strong>
            <span><?= e(__('admin.media.hint')) ?></span>
            <input type="file" id="f-images" name="images[]" accept="image/*" multiple hidden>
        </label>

        <?php if (has_error('files')): ?>
            <p class="admin-field__error"><?= e((string) error_for('files')) ?></p>
        <?php endif; ?>

        <button type="submit" class="btn btn-light btn-sm"><?= e(__('common.save')) ?></button>
    </form>

    <?php if ($images === []): ?>
        <?php component('empty-state', [
            'title' => __('admin.media.empty'),
        ]); ?>
    <?php else: ?>
        <form method="post" action="<?= e(url('/admin/products/' . $product->id() . '/media/reorder')) ?>"
              data-media-reorder>
            <?= csrf_field() ?>

            <ul class="media-grid is-sortable" data-media-list>
                <?php foreach ($images as $image): ?>
                    <li data-media-item data-image-id="<?= e((string) $image->id) ?>">
                        <img src="<?= e(upload_url((string) $image->path)) ?>" alt="" loading="lazy">

                        <?php if ((int) $image->is_primary === 1): ?>
                            <span class="media-grid__primary"><?= e(__('admin.media.primary')) ?></span>
                        <?php endif; ?>

                        <div class="media-grid__actions">
                            <input type="hidden" name="order[]" value="<?= e((string) $image->id) ?>">

                            <?php if ((int) $image->is_primary !== 1): ?>
                                <button type="submit" class="btn btn-sm btn-outline-light"
                                        form="primaryForm<?= e((string) $image->id) ?>"
                                        aria-label="<?= e(__('admin.media.make_primary')) ?>"
                                        title="<?= e(__('admin.media.make_primary')) ?>">
                                    ★
                                </button>
                            <?php endif; ?>

                            <button type="submit" class="btn btn-sm btn-danger"
                                    form="deleteForm<?= e((string) $image->id) ?>"
                                    aria-label="<?= e(__('common.delete')) ?>"
                                    data-confirm="<?= e(__('common.confirm')) ?>">×</button>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <button type="submit" class="btn btn-light btn-sm" data-media-save>
                <?= e(__('admin.media.save_order')) ?>
            </button>
        </form>

        <?php foreach ($images as $image): ?>
            <?php if ((int) $image->is_primary !== 1): ?>
                <form id="primaryForm<?= e((string) $image->id) ?>" method="post" hidden
                      action="<?= e(url('/admin/products/' . $product->id() . '/media/' . $image->id . '/primary')) ?>">
                    <?= csrf_field() ?>
                </form>
            <?php endif; ?>

            <form id="deleteForm<?= e((string) $image->id) ?>" method="post" hidden
                  action="<?= e(url('/admin/products/' . $product->id() . '/media/' . $image->id . '/delete')) ?>">
                <?= csrf_field() ?>
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
</div>