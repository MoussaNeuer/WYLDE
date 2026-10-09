<?php
/**
 * Carte produit réutilisée en accueil, shop, collection et fiche.
 * Accepte indifféremment un modèle Product ou une ligne brute (array).
 *
 * @var \App\Models\Product|array<string, mixed> $product
 */
if ($product instanceof \App\Models\Product) {
    $name      = $product->localizedName();
    $price     = (int) $product->price;
    $sale      = $product->hasDiscount() ? (int) $product->sale_price : null;
    $effective = $sale ?? $price;
    $slug      = (string) ($product->slug ?? '');
    $image     = $product->primaryImage();
    $label     = (string) ($product->label ?? 'none');
    $stock     = $product->totalStock();
    $id        = (int) $product->id();
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
    $id    = (int) ($product['id'] ?? 0);
}

/* Variantes de la carte : ajout rapide au panier sans quitter la page.
   Le contrôleur a déjà chargé les variantes de toute la page ; on ne
   retombe sur une requête individuelle que si la carte est rendue
   seule (page produit, appel direct), jamais pour une grille.

   Le stock par variante est la source de vérité : sur l'accueil, la
   requête des produits ne fournit pas de total, mais les variantes,
   elles, disent ce qui est réellement vendable.

   Une seule taille → ajout immédiat. Plusieurs tailles → les pastilles
   s'affichent. Toutes épuisées → la carte reste en lecture seule. */
$quickVariants = [];
$quickSizes    = false;
$quickAddable  = false;

if ($id > 0) {
    $preloaded = \App\Core\View::getShared('quick_variants', []);
    $rows     = is_array($preloaded[$id] ?? null)
        ? $preloaded[$id]
        : (\App\Models\Variant::forProducts([$id])[$id] ?? []);

    foreach ($rows as $variant) {
        $quickVariants[] = $variant;
    }

    // « UNIQUE » n'est pas une taille : on ne fait pas choisir l'inutile.
    $sized = array_values(array_filter(
        $quickVariants,
        static fn (array $v): bool => $v['size'] !== 'UNIQUE'
    ));

    if ($sized === []) {
        $quickSizes = false;

        foreach ($quickVariants as $variant) {
            if ($variant['available']) {
                $quickAddable = true;
                break;
            }
        }
    } else {
        $quickSizes = true;

        foreach ($sized as $variant) {
            if ($variant['available']) {
                $quickAddable = true;
                break;
            }
        }
    }
}

// Taille unique : la carte l'affiche (le client sait ce qu'il ajoute)
// et l'ajout se fait en un seul geste.
$quickSingle = null;

if ($quickAddable && !$quickSizes) {
    foreach ($quickVariants as $variant) {
        if ($variant['available']) {
            $quickSingle = $variant;
            break;
        }
    }
}

/* Deuxième photo, pour l'effet de survol du bureau.
   Elle n'est rendue que si le produit en possède réellement une : une
   carte ne doit pas réserver d'espace pour une image qui n'existe pas. */
$hoverImage = \App\Core\View::getShared('card_images', [])[$id] ?? null;

$labelText = match ($label) {
    'new'       => __('product.new'),
    'bestseller'=> __('product.bestseller'),
    'limited'   => __('product.limited'),
    default     => null,
};

$status = $stock === null ? null : stock_status($stock);
?>
<article class="product-card reveal" data-product-card data-product-id="<?= e((string) $id) ?>">

    <?php /* Le cœur est hors du lien principal : le client doit pouvoir
             enregistrer un produit sans quitter la grille. Son état actif
             est posé par favorites.js, après la lecture du stockage. */ ?>
    <?php if ($id > 0): ?>
        <button class="product-card__fav" type="button"
                data-favorite="<?= e((string) $id) ?>"
                data-label-on="<?= e(__('favorites.add')) ?>"
                data-label-off="<?= e(__('favorites.remove')) ?>"
                hidden
                aria-pressed="false"
                aria-label="<?= e(__('favorites.add')) ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"
                 fill="none" stroke="currentColor" stroke-width="1.6"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 20.5S3.5 15 3.5 9.2A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.5 2.8c0 5.8-8.5 11.3-8.5 11.3z"/>
            </svg>
        </button>
    <?php endif; ?>

    <a class="product-card__link" href="<?= e(url('/product/' . $slug)) ?>">

        <div class="product-card__media">
            <?php if ($image): ?>
                <img class="product-card__img product-card__img--primary"
                     src="<?= e(upload_url((string) $image)) ?>"
                     <?= \App\Services\ImageService::srcsetFor((string) $image) !== '' ? 'srcset="' . e(\App\Services\ImageService::srcsetFor((string) $image)) . '"' : '' ?>
                     sizes="(min-width: 1200px) 25vw, (min-width: 768px) 33vw, (min-width: 576px) 50vw, 100vw"
                     alt="<?= e($name) ?>"
                     width="600" height="750"
                     loading="lazy" decoding="async">

                <?php if ($hoverImage !== null): ?>
                    <?php /* Deuxième angle : chargée en différé pour ne pas
                             ralentir l'affichage de la grille. Le balayage
                             sur mobile est volontairement absent — il
                             conforterait le tap sur « Ajouter ». */ ?>
                    <img class="product-card__img product-card__img--hover"
                         src="<?= e(upload_url((string) $hoverImage)) ?>"
                         <?= \App\Services\ImageService::srcsetFor((string) $hoverImage) !== '' ? 'srcset="' . e(\App\Services\ImageService::srcsetFor((string) $hoverImage)) . '"' : '' ?>
                         sizes="(min-width: 1200px) 25vw, (min-width: 768px) 33vw, (min-width: 576px) 50vw, 100vw"
                         alt=""
                         width="600" height="750"
                         loading="lazy" decoding="async"
                         aria-hidden="true">
                <?php endif; ?>
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

            <?php if ($quickSingle !== null): ?>
                <p class="product-card__size"><?= e(size_label((string) $quickSingle['size'])) ?></p>
            <?php endif; ?>

            <?php if ($status !== null && $status['level'] !== 'in'): ?>
                <p class="product-card__stock product-card__stock--<?= e($status['level']) ?>">
                    <?= e($status['label']) ?>
                </p>
            <?php endif; ?>
        </div>
    </a>

    <?php /* Ajout rapide : le bouton ne doit pas faire partie du lien
             principal, sinon le clic navigue vers la fiche au lieu
             d'ajouter l'article. On le place donc après </a>. */ ?>
    <?php if ($quickAddable): ?>
        <?php if ($quickSizes): ?>
            <div class="product-card__quick" data-quick-add
                 data-product-id="<?= e((string) $id) ?>">
                <?php /* L'icône panier ouvre la liste des tailles : le client
                         voit d'abord qu'il peut choisir, sans avoir à
                         ouvrir la fiche produit. */ ?>
                <button class="product-card__quick-toggle" type="button"
                        data-quick-toggle
                        aria-expanded="false"
                        aria-label="<?= e(__('product.quick_add_sizes')) ?>">
                    <span class="product-card__quick-plus" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.6" aria-hidden="true"
                             focusable="false">
                            <path d="M6 7h12l-1 13H7L6 7z"/>
                            <path d="M9 7V5a3 3 0 0 1 6 0v2"/>
                        </svg>
                    </span>
                </button>

                <div class="size-pills size-pills--compact" role="group"
                     aria-label="<?= e(__('product.choose_size')) ?>"
                     data-quick-sizes hidden>
                    <?php foreach ($quickVariants as $variant): ?>
                        <?php if ($variant['size'] === 'UNIQUE') { continue; } ?>
                        <button class="size-pill size-pill--xs"
                                type="button"
                                data-quick-variant="<?= e((string) $variant['id']) ?>"
                                <?= $variant['available'] ? '' : 'disabled' ?>
                                aria-label="<?= e($variant['size']) ?><?= $variant['available'] ? '' : ' — ' . e(__('product.only_left')) ?>">
                            <?= e($variant['size']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <span class="product-card__quick-hint" data-quick-hint
                      hidden><?= e(__('product.choose_size')) ?></span>
            </div>
        <?php else: ?>
            <?php /* Taille unique : un seul geste, l'icône panier. */ ?>
            <?php if ($quickSingle !== null): ?>
                <button class="product-card__quick-add" type="button"
                        data-quick-add
                        data-quick-single="<?= e((string) $quickSingle['id']) ?>"
                        aria-label="<?= e(size_label((string) $quickSingle['size'])) ?>">
                    <span class="product-card__quick-plus" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.6" aria-hidden="true"
                             focusable="false">
                            <path d="M6 7h12l-1 13H7L6 7z"/>
                            <path d="M9 7V5a3 3 0 0 1 6 0v2"/>
                        </svg>
                    </span>
                </button>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</article>
