/* WYLDE — boutique : filtres et chargement progressif.
 *
 * Le formulaire de filtres est un vrai GET : sans JavaScript il
 * navigue normalement. Ce script ne fait que construire l'URL à partir
 * des champs, ce qui évite un rechargement de page à chaque changement.
 *
 * « Charger plus » demande la page suivante au serveur et insère le
 * HTML renvoyé : la carte n'est jamais reconstruite en JavaScript.
 */
(function () {
    'use strict';

    let busy = false;

    function section() {
        return document.querySelector('[data-shop]');
    }

    function form() {
        const box = section();

        return box ? box.querySelector('[data-filter-form]') : null;
    }

    /* ── URL de la sélection courante ─────────────────────────── */

    function buildQuery(root) {
        const params = new URLSearchParams();

        root.querySelectorAll('input, select').forEach((field) => {
            if (!field.name || field.disabled) {
                return;
            }

            if (field.type === 'checkbox' && !field.checked) {
                return;
            }

            // Un groupe de boutons radio ne fournit qu'une valeur ; les
            // autres sont décochés par le navigateur.
            if (field.type === 'radio' && !field.checked) {
                return;
            }

            const value = field.value.trim();

            if (value !== '') {
                params.set(field.name, value);
            }
        });

        return params.toString();
    }

    /* ── Soumission sans rechargement ─────────────────────────── */

    function submit(root) {
        const query = buildQuery(root);

        Wylde.nav.go(query === '' ? window.location.pathname : window.location.pathname + '?' + query);
    }

    /* ── « Charger plus » ─────────────────────────────────────── */

    function updateCounter(box, shown, total) {
        const counter = box.querySelector('[data-counter]');
        const count = box.querySelector('[data-result-count]');
        const template = box.dataset.counterTemplate || '';
        const label = box.dataset.totalLabel || '';

        if (counter) {
            counter.textContent = template
                .replace(':shown', String(shown))
                .replace(':total', String(total));
            counter.hidden = false;
        }

        if (count) {
            count.textContent = label;
        }
    }

    async function loadMore(button) {
        const box = section();

        if (!box || busy) {
            return;
        }

        const base = box.dataset.loadMoreQuery || '';
        const next = parseInt(box.dataset.page || '1', 10) + 1;
        const grid = box.querySelector('[data-product-grid]');
        const original = button.textContent;

        busy = true;
        button.disabled = true;
        button.classList.add('is-loading');

        try {
            // Le chemin courant porte déjà le préfixe d'installation
            // (/WYLDE/public) : inutile de le recomposer.
            const params = new URLSearchParams(base);
            params.set('page', String(next));
            const payload = await Wylde.api(
                Wylde.apiUrl(window.location.pathname + '?' + params.toString())
            );

            if (payload.html) {
                // Les cartes arrivent du serveur : le HTML est inséré tel
                // quel, les cœurs sont resynchronisés juste après.
                grid.insertAdjacentHTML('beforeend', payload.html);

                if (window.Wylde.refresh) {
                    window.Wylde.refresh();
                }

                if (window.WyldeFavorites) {
                    window.WyldeFavorites.refresh();
                }
            }

            box.dataset.page = String(next);
            updateCounter(box, payload.shown || 0, payload.total || 0);

            // Le compteur du document suit : un partage de lien ou un
            // rechargement doit retrouver la bonne page.
            const url = new URL(window.location.href);

            url.searchParams.set('page', String(next));
            window.history.replaceState(window.history.state, '', url);

            if (payload.has_more) {
                button.hidden = false;
            } else {
                button.hidden = true;
            }
        } catch (error) {
            const box2 = box.querySelector('[data-load-more-error]');

            if (box2) {
                box2.textContent =
                    error && error.message ? error.message : String(error);
                box2.hidden = false;
            }

            button.textContent = original;
        } finally {
            busy = false;
            button.disabled = false;
            button.classList.remove('is-loading');
        }
    }

    /* ── Amorçage ─────────────────────────────────────────────── */

    function boot() {
        const root = form();

        if (!root) {
            return;
        }

        const box = section();
        const button = box ? box.querySelector('[data-load-more]') : null;
        const pagination = box ? box.querySelector('[data-pagination]') : null;

        root.addEventListener('submit', (event) => {
            event.preventDefault();
            submit(root);
        });

        root.addEventListener('change', (event) => {
            const field = event.target;

            if (!field.name || field.type === 'text' || field.type === 'search') {
                return;
            }

            submit(root);
        });

        // Frappe : on attend une pause, sinon « S », « M », « XL »
        // enverrait trois recherches successives.
        let typing = null;

        root.addEventListener('input', (event) => {
            if (event.target.type !== 'search') {
                return;
            }

            if (typing) {
                window.clearTimeout(typing);
            }

            typing = window.setTimeout(() => submit(root), 450);
        });

        if (button) {
            // Le repli sans JavaScript est la pagination serveur : elle n'est
            // retirée du document que si le « Charger plus » est
            // réellement disponible. Le serveur a déjà calculé le nombre
            // de produits affichés, ce qui reste juste quand la dernière
            // page est incomplète.
            const total = parseInt(box.dataset.total || '0', 10);
            const shown = parseInt(box.dataset.shown || '0', 10);

            if (shown < total) {
                button.hidden = false;

                if (pagination) {
                    pagination.hidden = true;
                }
            } else if (pagination) {
                button.hidden = true;
            }

            button.addEventListener('click', () => loadMore(button));
        }
    }

    Wylde.onSwap(boot);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();