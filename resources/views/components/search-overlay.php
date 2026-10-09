<?php
/**
 * Overlay de recherche.
 *
 * Masqué tant que JavaScript n'a pas annoncé le prendre en charge :
 * sans script, le bouton de l'en-tête n'existerait pas et la recherche
 * resterait accessible par le champ de la page boutique.
 *
 * Le panneau est un dialogue : le focus y entre, y reste (Tab est
 * piégé) et revient au bouton d'origine à la fermeture.
 */
?>
<?php /* Les libellés qui doivent rester des gabarits sont marqués par
       `{}` : search.js remplace ensuite ce marqueur par la saisie ou le
       nombre de résultats, sans réécrire les phrases en JavaScript. */ ?>
<div class="search-overlay" id="searchOverlay" data-search-overlay hidden
     data-hint="<?= e(__('search.hint')) ?>"
     data-idle="<?= e(__('search.idle')) ?>"
     data-results-one="<?= e(__('search.results_one', ['count' => '{}'])) ?>"
     data-results-many="<?= e(__('search.results_many', ['count' => '{}'])) ?>"
     data-empty="<?= e(__('search.empty', ['query' => '{}'])) ?>"
     data-empty-hint="<?= e(__('search.empty_hint')) ?>"
     data-all="<?= e(__('search.view_all')) ?>"
     data-unavailable="<?= e(__('search.unavailable')) ?>">
    <div class="search-overlay__backdrop" data-search-close></div>

    <div class="search-overlay__panel"
         role="dialog"
         aria-modal="true"
         aria-label="<?= e(__('search.title')) ?>"
         data-search-panel>

        <div class="search-overlay__field">
            <svg class="search-overlay__icon" width="20" height="20" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <circle cx="11" cy="11" r="6.5"/>
                <path d="M16 16l4.5 4.5"/>
            </svg>

            <input class="search-overlay__input"
                   type="search"
                   data-search-input
                   placeholder="<?= e(__('search.placeholder')) ?>"
                   autocomplete="off"
                   autocapitalize="off"
                   autocorrect="off"
                   spellcheck="false"
                   aria-controls="searchResults"
                   aria-expanded="false"
                   aria-describedby="searchStatus">

            <button class="search-overlay__close" type="button" data-search-close>
                <span aria-hidden="true">&times;</span>
                <span class="sr-only"><?= e(__('search.close')) ?></span>
            </button>
        </div>

        <p class="search-overlay__status" id="searchStatus" data-search-status role="status">
            <?= e(__('search.hint')) ?>
        </p>

        <ul class="search-overlay__results" id="searchResults" data-search-results hidden></ul>

        <a class="search-overlay__all" data-search-all hidden>
            <?= e(__('search.view_all')) ?>
        </a>
    </div>
</div>