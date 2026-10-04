<?php
/**
 * Fiche produit.
 *
 * @var string                  $title
 * @var \App\Models\Product     $product
 * @var \App\Models\Variant[]   $variants
 * @var \App\Models\Variant|null $defaultVariant
 * @var \App\Models\Product[]   $related
 */
$images    = $product->images();
$primary   = $product->primaryImage();
$allImages = [];
foreach ($images as $image) {
    $allImages[] = (string) $image->path;
}
if ($primary !== null && !in_array($primary, $allImages, true)) {
    array_unshift($allImages, $primary);
}

$hasSizes = (bool) ($product->has_sizes ?? false);
$effective = $product->effectivePrice();
$hasDiscount = $product->hasDiscount();
$stock     = $product->totalStock();

// Prix et stock par variante : le select public les affiche, et product.js
// reprend ces valeurs au changement de taille. Le libellé et le seuil de
// stock viennent de stock_status(), le JS ne les recalcule pas.
$variantData = [];

foreach ($variants as $variant) {
    $status = stock_status((int) $variant->stock);

    $variantData[(string) $variant->id] = [
        'price'     => money($variant->effectivePrice($product)),
        'amount'    => (int) $variant->effectivePrice($product),
        'stock'     => (int) $variant->stock,
        'level'     => $status['level'],
        'label'     => $status['label'],
        'available' => $variant->isAvailable(),
        'size'      => (string) $variant->size,
    ];
}

$sizePayload = [
    'base'     => (int) $effective,
    'variants' => $variantData,
];
?>
<section class="section">
    <div class="container">
        <nav class="breadcrumb-category" aria-label="<?= e(__('common.actions')) ?>">
            <a href="<?= e(url('/shop')) ?>"><?= e(__('nav.shop')) ?></a>
            <?php if ($product->category() !== null): ?>
                <span aria-hidden="true">·</span>
                <a href="<?= e(url('/collection/' . $product->category()->slug)) ?>">
                    <?= e($product->category()->localizedName()) ?>
                </a>
            <?php endif; ?>
        </nav>

        <div class="product-detail">
            <div class="product-gallery">
                <?php if ($allImages === []): ?>
                    <div class="product-gallery__main">
                        <span class="product-card__placeholder" aria-hidden="true"></span>
                    </div>
                <?php else: ?>
                    <div class="product-gallery__main">
                        <img src="<?= e(upload_url((string) $allImages[0])) ?>"
                             alt="<?= e($product->localizedName()) ?>"
                             width="600" height="750" decoding="async">
                    </div>
                    <?php if (count($allImages) > 1): ?>
                        <div class="product-gallery__thumbs">
                            <?php foreach ($allImages as $index => $path): ?>
                                <button class="product-gallery__thumb<?= $index === 0 ? ' is-active' : '' ?>"
                                        type="button"
                                        data-gallery-thumb
                                        data-src="<?= e(upload_url((string) $path)) ?>"
                                        aria-label="<?= e(__('product.details')) ?>">
                                    <img src="<?= e(upload_url((string) $path)) ?>"
                                         alt="" loading="lazy" width="80" height="100">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="product-info">
                <h1 class="product-info__title"><?= e($product->localizedName()) ?></h1>

                <p class="product-info__price">
                    <?php if ($hasDiscount): ?>
                        <span class="product-info__price-final"><?= e(money($effective)) ?></span>
                        <s class="product-info__price-old"><?= e(money((int) $product->price)) ?></s>
                        <span class="badge badge--tag"><?= e(__('product.save_percent', ['percent' => $product->discountPercent()])) ?></span>
                    <?php else: ?>
                        <span class="product-info__price-final"><?= e(money($effective)) ?></span>
                    <?php endif; ?>
                </p>

                <p class="product-info__stock product-info__stock--<?= e(stock_status($stock)['level']) ?>">
                    <?= e(stock_status($stock)['label']) ?>
                </p>

                <?php if ($product->short_description !== null && $product->short_description !== ''): ?>
                    <p class="product-info__description"><?= e($product->short_description) ?></p>
                <?php elseif ($product->description !== null && $product->description !== ''): ?>
                    <p class="product-info__description"><?= e(mb_substr($product->description, 0, 220)) ?></p>
                <?php endif; ?>

                <form method="post" action="<?= e(url('/cart/add')) ?>" class="product-form">
                    <?= csrf_field() ?>

                    <?php if ($hasSizes && $variants !== []): ?>
                        <div class="form-group">
                            <label class="form-label" for="product-size"><?= e(__('product.size')) ?></label>
                            <select class="form-select" id="product-size" name="variant_id" required
                                    data-product-size>
                                <option value=""><?= e(__('product.size_select')) ?></option>
                                <?php foreach ($variants as $variant): ?>
                                    <option value="<?= e((string) $variant->id) ?>"
                                        <?= $variant->id === ($defaultVariant?->id) ? 'selected' : '' ?>
                                        <?= !$variant->isAvailable() ? 'disabled' : '' ?>>
                                        <?= e($variant->size) ?>
                                        <?php if ($variant->isAvailable()): ?>
                                            — <?= e($variantData[(string) $variant->id]['price']) ?>
                                        <?php else: ?>
                                            — <?= e($variantData[(string) $variant->id]['label']) ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($variantData !== []): ?>
                                <script type="application/json" data-product-sizes><?= json_encode(
                                    $sizePayload,
                                    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
                                ) ?></script>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="variant_id" value="<?= e((string) ($defaultVariant?->id ?? ($variants[0]->id ?? ''))) ?>">
                    <?php endif; ?>

                    <div class="form-group form-group--inline">
                        <label class="form-label" for="product-qty"><?= e(__('product.quantity')) ?></label>
                        <input class="form-control qty-input"
                               type="number"
                               id="product-qty"
                               name="quantity"
                               value="1"
                               min="1"
                               max="<?= e((string) max(1, $stock)) ?>">
                    </div>

                    <button class="btn btn-light btn-block" type="submit" <?= $stock < 1 ? 'disabled' : '' ?>>
                        <?= e($stock < 1 ? __('product.out_of_stock') : __('product.add_to_cart')) ?>
                    </button>
                </form>

                <?php if ($product->description !== null && $product->description !== ''): ?>
                    <details class="product-info__details">
                        <summary><?= e(__('product.details')) ?></summary>
                        <p><?= nl2br(e($product->description)) ?></p>
                    </details>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php if ($related !== []): ?>
    <section class="section section--muted">
        <div class="container">
            <header class="section__head">
                <h2 class="section__title"><?= e(__('product.related')) ?></h2>
            </header>
            <div class="product-grid">
                <?php foreach ($related as $relatedProduct): ?>
                    <?php view_partial('components/product-card', ['product' => $relatedProduct]); ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($hasSizes && $variantData !== []): ?>
    <?php App\Core\View::start('scripts'); ?>
    <script src="<?= e(asset('assets/js/product.js')) ?>" defer></script>
    <?php App\Core\View::stop(); ?>
<?php endif; ?>
