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

// Une taille épuisée doit rester visible mais non cliquable : sans cela
// le client ne sait pas que la taille existe.
$hasOutOfStock = false;

foreach ($variants as $variant) {
    if (!$variant->isAvailable()) {
        $hasOutOfStock = true;
        break;
    }
}
$effective = $product->effectivePrice();
$hasDiscount = $product->hasDiscount();
$stock     = $product->totalStock();

// Prix et stock par variante : le select public les affiche, et product.js
// reprend ces valeurs au changement de taille. Le libellé et le seuil de
// stock viennent de stock_status(), le JS ne les recalcule pas.
$variantData = [];
$scarcityData = [];

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

    // Message de rareté par taille, rendu par le serveur : le JS ne
    // reformule aucune donnée de stock.
    $scarcity = scarcity_message((int) $variant->stock, (string) $variant->size);

    if ($scarcity !== null) {
        $scarcityData[(string) $variant->id] = $scarcity['text'];
    }
}

$sizePayload = [
    'base'     => (int) $effective,
    'variants' => $variantData,
    'scarcity' => $scarcityData,
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
                    <div class="product-gallery__main" data-gallery-main>
                        <?php if (count($allImages) > 2): ?>
                            <div class="product-gallery__progress" data-gallery-progress aria-hidden="true">
                                <?php foreach ($allImages as $index => $path): ?>
                                    <span class="product-gallery__progress-bar<?= $index === 0 ? ' is-current' : '' ?>">
                                        <span class="product-gallery__progress-fill"></span>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <img src="<?= e(upload_url((string) $allImages[0])) ?>"
                             <?= \App\Services\ImageService::srcsetFor((string) $allImages[0]) !== '' ? 'srcset="' . e(\App\Services\ImageService::srcsetFor((string) $allImages[0])) . '"' : '' ?>
                             sizes="(min-width: 992px) 50vw, 100vw"
                             alt="<?= e($product->localizedName()) ?>"
                             width="600" height="750" decoding="async"
                             fetchpriority="high"
                             data-gallery-image>
                        <button class="product-gallery__zoom" type="button" data-gallery-zoom
                                aria-label="<?= e(__('product.gallery_zoom')) ?>">
                            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
                                <circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="1.7"/>
                                <path d="M16 16l4.5 4.5M11 8.5v5M8.5 11h5" fill="none"
                                      stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                        </button>
                        <?php if (count($allImages) > 1): ?>
                            <span class="product-gallery__count" data-gallery-count
                                  aria-label="<?= e(__('product.gallery_position', ['index' => 1, 'count' => count($allImages)])) ?>">1 / <?= (int) count($allImages) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (count($allImages) > 1): ?>
                        <div class="product-gallery__nav">
                            <button class="product-gallery__arrow" type="button" data-gallery-prev
                                    aria-label="<?= e(__('product.gallery_prev')) ?>">
                                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
                                    <path d="M14.5 5l-7 7 7 7" fill="none" stroke="currentColor"
                                          stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                            <button class="product-gallery__arrow" type="button" data-gallery-next
                                    aria-label="<?= e(__('product.gallery_next')) ?>">
                                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
                                    <path d="M9.5 5l7 7-7 7" fill="none" stroke="currentColor"
                                          stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                        </div>
                        <div class="product-gallery__thumbs">
                            <?php foreach ($allImages as $index => $path): ?>
                                <button class="product-gallery__thumb<?= $index === 0 ? ' is-active' : '' ?>"
                                        type="button"
                                        data-gallery-thumb
                                        data-src="<?= e(upload_url((string) $path)) ?>"
                                        aria-label="<?= e(__('product.gallery_thumb', ['index' => $index + 1])) ?>"
                                        aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>">
                                    <img src="<?= e(upload_url(\App\Services\ImageService::bestFor((string) $path, 200))) ?>"
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

                <?php /* Message de rareté : affiché seulement si le stock est
                         bas, pour la variante par défaut ou la seule variante.
                         Jamais de mesure inventée : le texte vient du stock réel. */ ?>
                <?php
                $scarcityVariant = $hasSizes ? $defaultVariant : ($variants[0] ?? null);
                $scarcity = $scarcityVariant
                    ? scarcity_message((int) $scarcityVariant->stock, $hasSizes ? (string) $scarcityVariant->size : null)
                    : null;
                ?>
                <?php if ($scarcity !== null): ?>
                    <p class="scarcity scarcity--<?= e($scarcity['level']) ?>" data-product-scarcity>
                        <?= e($scarcity['text']) ?>
                    </p>
                <?php endif; ?>

                <?php if ($product->short_description !== null && $product->short_description !== ''): ?>
                    <p class="product-info__description"><?= e($product->short_description) ?></p>
                <?php elseif ($product->description !== null && $product->description !== ''): ?>
                    <p class="product-info__description"><?= e(mb_substr($product->description, 0, 220)) ?></p>
                <?php endif; ?>

                <form method="post" action="<?= e(url('/cart/add')) ?>" class="product-form">
                    <?= csrf_field() ?>

                    <?php if ($variants !== []): ?>
                        <?php /* Pastilles plutôt qu'un <select> : le client choisit
                                 au doigt et la valeur reste visible. Le <select>
                                 est conservé, masqué, pour rester la source de
                                 vérité du formulaire et pour les lecteurs d'écran
                                 qui préfèrent une liste. Le bloc est rendu même
                                 pour une taille unique : le client doit toujours
                                 voir quelle taille il ajoute au panier. */ ?>
                        <fieldset class="form-group">
                            <legend class="form-label"><?= e(__('product.size')) ?></legend>

                            <div class="size-pills<?= $hasOutOfStock ? ' size-pills--with-hint' : '' ?>"
                                 data-size-pills>
                                <?php foreach ($variants as $variant): ?>
                                    <?php $available = $variant->isAvailable(); ?>
                                    <button class="size-pill<?= $available ? '' : ' size-pill--out' ?>"
                                            type="button"
                                            data-size-pill
                                            data-variant-id="<?= e((string) $variant->id) ?>"
                                            aria-pressed="<?= $variant->id === ($defaultVariant?->id) ? 'true' : 'false' ?>"
                                            <?= $available ? '' : 'disabled' ?>>
                                        <?= e(size_label($variant->size)) ?>
                                        <?php if (!$available): ?>
                                            <span class="size-pill__hint"><?= e(__('product.only_left')) ?></span>
                                        <?php endif; ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>

                            <select class="sr-only" id="product-size" name="variant_id" required
                                    data-product-size aria-label="<?= e(__('product.size')) ?>">
                                <option value=""><?= e(__('product.size_select')) ?></option>
                                <?php foreach ($variants as $variant): ?>
                                    <option value="<?= e((string) $variant->id) ?>"
                                        <?= $variant->id === ($defaultVariant?->id) ? 'selected' : '' ?>
                                        <?= !$variant->isAvailable() ? 'disabled' : '' ?>>
                                        <?= e(size_label($variant->size)) ?>
                                        <?php if ($variant->isAvailable()): ?>
                                            — <?= e($variantData[(string) $variant->id]['price']) ?>
                                        <?php else: ?>
                                            — <?= e($variantData[(string) $variant->id]['label']) ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if ($hasSizes): ?>
                                <button class="size-guide-link" type="button" data-size-guide-open>
                                    <?= e(__('product.size_guide')) ?>
                                </button>
                            <?php endif; ?>

                            <?php if ($variantData !== []): ?>
                                <script type="application/json" data-product-sizes><?= json_encode(
                                    $sizePayload,
                                    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
                                ) ?></script>
                            <?php endif; ?>
                        </fieldset>
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

                <div class="product-trust">
                    <h2 class="product-trust__title"><?= e(__('product.trust_title')) ?></h2>
                    <ul class="product-trust__list">
                        <li class="product-trust__item">
                            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
                                <path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z" fill="none" stroke="currentColor"
                                      stroke-width="1.5" stroke-linejoin="round"/>
                                <circle cx="7" cy="18" r="1.8" fill="none" stroke="currentColor" stroke-width="1.5"/>
                                <circle cx="17.5" cy="18" r="1.8" fill="none" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                            <span>
                                <strong><?= e(__('product.trust_shipping')) ?></strong>
                                <?= e(__('product.trust_shipping_t')) ?>
                            </span>
                        </li>
                        <li class="product-trust__item">
                            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
                                <rect x="3" y="6" width="18" height="12" rx="2" fill="none"
                                      stroke="currentColor" stroke-width="1.5"/>
                                <path d="M3 10h18" fill="none" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                            <span>
                                <strong><?= e(__('product.trust_payment')) ?></strong>
                                <?= e(__('product.trust_payment_t')) ?>
                            </span>
                        </li>
                        <li class="product-trust__item">
                            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
                                <path d="M20.5 11.5a8.5 8.5 0 1 1-3.2-6.1" fill="none"
                                      stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                <path d="M8.5 11.5l3.2 3.2 8.8-8.8" fill="none" stroke="currentColor"
                                      stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>
                                <strong><?= e(__('product.trust_confirm')) ?></strong>
                                <?= e(__('product.trust_confirm_t')) ?>
                            </span>
                        </li>
                        <li class="product-trust__item">
                            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
                                <path d="M4 9l4-4 4 4M8 5v9" fill="none" stroke="currentColor"
                                      stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M13 7h7M13 12h7M13 17h7" fill="none" stroke="currentColor"
                                      stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <span>
                                <strong><?= e(__('product.trust_returns')) ?></strong>
                                <?= e(__('product.trust_returns_t')) ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($related !== []): ?>
    <section class="section section--muted reveal">
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

<?php if ($stock > 0): ?>
    <div class="buybar" data-buybar hidden>
        <div class="buybar__inner">
            <div class="buybar__meta">
                <p class="buybar__name"><?= e($product->localizedName()) ?></p>
                <p class="buybar__price" data-buybar-price><?= e(money($effective)) ?></p>
            </div>

            <?php if ($hasSizes): ?>
                <label class="visually-hidden" for="buybar-size"><?= e(__('product.size')) ?></label>
                <select class="form-select buybar__size" id="buybar-size" data-buybar-size>
                    <option value=""><?= e(__('product.size_select')) ?></option>
                    <?php foreach ($variants as $variant): ?>
                        <option value="<?= e((string) $variant->id) ?>"
                            <?= !$variant->isAvailable() ? 'disabled' : '' ?>>
                            <?= e($variant->size) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <form method="post" action="<?= e(url('/cart/add')) ?>" class="buybar__form" data-buybar-form>
                <?= csrf_field() ?>
                <input type="hidden" name="variant_id" value="<?= e((string) ($defaultVariant?->id ?? '')) ?>"
                       data-buybar-variant>
                <input type="hidden" name="quantity" value="1" data-buybar-qty>
                <button class="btn btn-light buybar__cta" type="submit" <?= $hasSizes ? 'disabled' : '' ?>>
                    <?= e(__('product.buybar_title')) ?>
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php /* Galerie, visionneuse et barre d'achat : nécessaires sur toute fiche produit. */ ?>
<?php App\Core\View::start('scripts'); ?>
<?php /* Guide des tailles : les mesures réelles sont saisies dans l'admin.
         Tant qu'elles manquent, la fenêtre ne liste que les tailles
         disponibles — aucune valeur n'est inventée. */ ?>
<?php if ($hasSizes && $variants !== []): ?>
<div class="modal fade" id="sizeGuide" tabindex="-1" role="dialog" aria-hidden="true"
     aria-labelledby="sizeGuideTitle" data-size-guide>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="sizeGuideTitle"><?= e(__('product.size_guide_title')) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="<?= e(__('product.lightbox_close')) ?>"></button>
            </div>
            <div class="modal-body">
                <p class="size-guide__hint"><?= e(__('product.size_guide_hint')) ?></p>
                <ul class="size-guide__list">
                    <?php foreach ($variants as $variant): ?>
                        <li class="size-guide__item<?= $variant->isAvailable() ? '' : ' size-guide__item--out' ?>">
                            <span class="size-guide__label"><?= e($variant->size) ?></span>
                            <span class="size-guide__stock">
                                <?= e($variantData[(string) $variant->id]['label'] ?? '') ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="<?= e(asset('assets/js/product.js')) ?>" defer></script>
<?php App\Core\View::stop(); ?>

<div class="lightbox" data-lightbox hidden>
    <button class="lightbox__close" type="button" data-lightbox-close
            aria-label="<?= e(__('product.lightbox_close')) ?>">&times;</button>
    <img class="lightbox__image" data-lightbox-image src="" alt="">
    <p class="lightbox__hint"><?= e(__('product.lightbox_hint')) ?></p>
</div>
