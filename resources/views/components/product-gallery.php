<?php
/**
 * Galerie produit.
 *
 * Une image principale et ses vignettes. Le composant fonctionne sans
 * JavaScript : sans JS, les vignettes restent des liens vers la même
 * page ; avec app.js, elles pilotent l'image affichée.
 *
 * @var array<int, string> $images  Chemins relatifs (product_images.path)
 * @var string            $name    Nom du produit, pour les textes alternatifs
 */
$images = array_values(array_filter((array) ($images ?? []), static fn (mixed $path): bool => is_string($path) && trim($path) !== ''));
$name   = (string) ($name ?? '');

if ($images === []) {
    $images = [null];
}
?>
<div class="product-gallery" data-gallery>
    <div class="product-gallery__main">
        <img src="<?= e(upload_url($images[0])) ?>"
             alt="<?= e($name) ?>"
             width="800" height="1000" loading="lazy" decoding="async"
             data-gallery-main>
    </div>

    <?php if (count($images) > 1): ?>
        <ul class="product-gallery__thumbs">
            <?php foreach ($images as $index => $path): ?>
                <li>
                    <button type="button"
                            class="product-gallery__thumb<?= $index === 0 ? ' is-active' : '' ?>"
                            data-gallery-thumb="<?= e(upload_url($path)) ?>"
                            aria-label="<?= e($name) ?> <?= e((string) ($index + 1)) ?>">
                        <img src="<?= e(upload_url($path)) ?>" alt="" width="120" height="150" loading="lazy" decoding="async">
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>