<?php
/**
 * « Mes favoris ».
 *
 * Aucun produit n'est rendu ici : la sélection vit dans le navigateur du
 * visiteur. Le script de la page demande les cartes correspondantes et
 * les insère dans la grille.
 */
?>
<section class="section favorites" data-favorites-page
         data-count-one="<?= e(__('favorites.count_one', ['count' => '{}'])) ?>"
         data-count-many="<?= e(__('favorites.count_many', ['count' => '{}'])) ?>"
         data-unavailable="<?= e(__('favorites.unavailable')) ?>">
    <div class="container">
        <header class="page-head">
            <h1 class="page-head__title"><?= e(__('favorites.title')) ?></h1>
            <p class="page-head__text"><?= e(__('favorites.intro')) ?></p>
            <p class="favorites__counter" data-favorites-summary hidden></p>
        </header>

        <p class="favorites__loading" data-favorites-loading role="status">
            <?= e(__('common.loading')) ?>
        </p>

        <p class="favorites__error" data-favorites-error role="status" hidden></p>

        <div class="product-grid" data-favorites-grid></div>

        <div class="empty-state" data-favorites-empty hidden>
            <h2 class="empty-state__title"><?= e(__('favorites.empty')) ?></h2>
            <p class="empty-state__text"><?= e(__('favorites.empty_hint')) ?></p>
            <a class="btn btn-dark" href="<?= e(url('/shop')) ?>"><?= e(__('home.hero_cta')) ?></a>
        </div>
    </div>
</section>