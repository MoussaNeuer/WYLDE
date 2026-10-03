<?php
/**
 * Carte produit réutilisée en accueil, shop, collection et fiche.
 * Accepte indifféremment un modèle Product ou une ligne brute (array).
 *
 * @var \App\Models\Product|array<string, mixed> $product
 */
if ($product instanceof \App\Models\Product) {
    $name      = $product->localizedName();
    $price     = (int) ($product->price ?? 0);
    $sale      = isset($product->sale_price) && (int) $product->sale_price > 0 ? (int) $product->sale_price : null;
    $effective = $sale ?? $price;
    $slug      = (string) ($product->slug ?? '');
    $image     = $product->primaryImage();
    $label     = (string) ($product->label ?? 'none');
    $stock     = $product->totalStock();
} else {
    $name     = localized($product, 'name');
    $price    = money_int($product['price'] ?? 0);
    $sale     = isset($product['sale_price']) && (int) $product['sale_price'] > 0
        ? (int) $product['sale_price']
        : null;
    $effective = $sale !== null ? $sale : $price;
    $slug     = (string) ($product['slug'] ?? '');
    $image    = $product['image_path'] ?? ($product['path'] ?? null);
    $label    = (string) ($product['label'] ?? 'none');

    $stock = isset($product['stock']) ? (int) $product['stock'] : null;
}

$labelText = match ($label) {
    'new'       => __('product.new'),
    'bestseller'=> __('product.bestseller'),
    'limited'   => __('product.limited'),
    default     => null,
};

$status = $stock === null ? null : stock_status($stock);
?>
<article class="product-card">
    <a class="product-card__link" href="<?= e(url('/product/' . $slug)) ?>">

        <div class="product-card__media">
            <?php if ($image): ?>
                <img src="<?= e(upload_url((string) $image)) ?>"
                     alt="<?= e($name) ?>"
                     width="600" height="750"
                     loading="lazy" decoding="async">
            <?php else: ?>
                <span class="product-card__placeholder" aria-hidden="true"></span>
            <?php endif; ?>

            <?php if ($labelText !== null): ?>
                <span class="product-card__label"><?= e($labelText) ?></span>
            <?php endif; ?>

            <?php if ($status !== null && $status['level'] === 'out'): ?>
                <span class="product-card__soldout"><?= e(__('product.stock_out')) ?></span>
            <?php endif; ?>
        </div>

        <div class="product-card__body">
            <h3 class="product-card__name"><?= e($name) ?></h3>

            <p class="product-card__price">
                <?php if ($sale !== null): ?>
                    <span class="product-card__price-final"><?= e(money($effective)) ?></span>
                    <s class="product-card__price-old"><?= e(money($price)) ?></s>
                <?php else: ?>
                    <span class="product-card__price-final"><?= e(money($effective)) ?></span>
                <?php endif; ?>
            </p>

            <?php if ($status !== null && $status['level'] !== 'in'): ?>
                <p class="product-card__stock product-card__stock--<?= e($status['level']) ?>">
                    <?= e($status['label']) ?>
                </p>
            <?php endif; ?>
        </div>
    </a>
</article>
