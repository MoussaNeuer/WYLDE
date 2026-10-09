/* WYLDE — favoris (aucun compte, stockage navigateur).
 *
 * Les favoris ne vivent pas en base : ils sont rangés dans le
 * localStorage de l'appareil. Le serveur ne fait que rendre les cartes
 * demandées par identifiant, ce qui évite d'inventer un compte client
 * pour une liste de coursework.
 *
 * Les cœurs sont masqués dans le HTML et révélés ici : sans
 * JavaScript, un bouton inerte serait un contrôle qui ne fait rien.
 */
(function () {
    'use strict';

    const KEY = 'wylde.favorites';
    // Garde-fou : le serveur refuse de rendre plus de 60 cartes d'un coup.
    const MAX = 60;

    let state = read();

    /* ── Lecture / écriture ─────────────────────────────────── */

    function read() {
        try {
            const raw = window.localStorage.getItem(KEY);

            if (!raw) {
                return [];
            }

            const parsed = JSON.parse(raw);

            if (!Array.isArray(parsed)) {
                return [];
            }

            return parsed
                .map((id) => parseInt(id, 10))
                .filter((id) => Number.isInteger(id) && id > 0)
                .slice(0, MAX);
        } catch (e) {
            // Mode privé, quota plein, stockage désactivé : les favoris
            // deviennent indisponibles, la page reste utilisable.
            return [];
        }
    }

    function write(ids) {
        try {
            window.localStorage.setItem(KEY, JSON.stringify(ids.slice(0, MAX)));

            return true;
        } catch (e) {
            return false;
        }
    }

    /* Les textes viennent du HTML : un dictionnaire en JavaScript
       serait dupliqué et traduisible deux fois. */
    function message(name) {
        const link = document.querySelector('[data-favorites-link]');

        return link ? link.dataset[name] || '' : '';
    }

    function toast(text, type) {
        if (text) {
            Wylde.toast(text, type);
        }
    }

    /* ── Compteur de l'en-tête ───────────────────────────────── */

    function syncCount() {
        const count = state.length;

        document.querySelectorAll('[data-favorites-count]').forEach((badge) => {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.dataset.count = String(count);
            badge.hidden = count === 0;

            const link = badge.closest('[data-favorites-link]');

            if (link) {
                link.classList.toggle('has-favorites', count > 0);
            }
        });
    }

    /* ── État des cœurs présents dans la page ─────────────────── */

    function syncHearts() {
        document.querySelectorAll('[data-favorite]').forEach((button) => {
            const active = state.indexOf(parseInt(button.dataset.favorite, 10)) !== -1;
            const label = active ? button.dataset.labelOff : button.dataset.labelOn;

            button.hidden = false;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');

            if (label) {
                button.setAttribute('aria-label', label);
            }
        });
    }

    /* ── Clic ────────────────────────────────────────────────── */

    function toggle(id) {
        const index = state.indexOf(id);
        const link = document.querySelector('[data-favorites-link]');

        // Un cœur retiré sur la page des favoris doit disparaître de la
        // grille : la page rend les serveurs les cartes de cet écran.
        const onFavoritesPage = !!document.querySelector('[data-favorites-page]');
        const added = index === -1;

        if (added && state.length >= MAX) {
            return false;
        }

        if (added) {
            state.push(id);
        } else {
            state.splice(index, 1);
        }

        if (!write(state)) {
            toast(message('toastUnavailable'), 'error');

            return false;
        }

        syncCount();
        syncHearts();

        if (onFavoritesPage) {
            if (added) {
                window.WyldeFavorites.render();
            } else {
                const card = document.querySelector(
                    '[data-product-id="' + id + '"]'
                );

                if (card && card.parentNode) {
                    card.parentNode.removeChild(card);
                }

                window.WyldeFavorites.render();
            }
        }

        toast(
            added ? link?.dataset.toastAdd : link?.dataset.toastRemoved,
            added ? 'success' : 'info'
        );

        return true;
    }

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-favorite]');

        if (!button) {
            return;
        }

        // Le cœur est hors du lien de la carte : le clic ne doit pas
        // ouvrir la fiche produit.
        event.preventDefault();
        event.stopPropagation();

        toggle(parseInt(button.dataset.favorite, 10));
    });

    /* ── Page des favoris ────────────────────────────────────── */

    /* Les cartes viennent du serveur : le HTML reste la source de la
       mise en page, et un produit retiré de la vente disparaît de la
       page au lieu d'y laisser un lien mort. */
    function renderPage() {
        const page = document.querySelector('[data-favorites-page]');

        if (!page) {
            return;
        }

        const grid = page.querySelector('[data-favorites-grid]');
        const empty = page.querySelector('[data-favorites-empty]');
        const loading = page.querySelector('[data-favorites-loading]');
        const error = page.querySelector('[data-favorites-error]');
        const summary = page.querySelector('[data-favorites-summary]');
        const countLabel = page.dataset.countOne || '';
        const countMany = page.dataset.countMany || '';

        const show = (node, visible) => {
            if (node) {
                node.hidden = !visible;
            }
        };

        if (error) {
            error.hidden = true;
            error.textContent = '';
        }

        if (summary) {
            const count = state.length;

            if (count === 0) {
                summary.hidden = true;
            } else {
                summary.textContent = fill(count > 1 ? countMany : countLabel, count);
                summary.hidden = false;
            }
        }

        if (state.length === 0) {
            grid.textContent = '';
            show(loading, false);
            show(empty, true);

            return;
        }

        show(empty, false);
        show(loading, true);

        Wylde.api(Wylde.apiUrl('/api/products/cards?ids=' + state.join(',')))
            .then((payload) => {
                const missing = Array.isArray(payload.missing) ? payload.missing : [];

                // Un produit archivé ne doit pas rester dans la liste :
                // il reviendrait à chaque rechargement.
                if (missing.length > 0) {
                    state = state.filter((id) => missing.indexOf(id) === -1);
                    write(state);
                    syncCount();

                    if (error) {
                        error.textContent = page.dataset.unavailable || '';
                        error.hidden = !error.textContent;
                    }
                }

                grid.innerHTML = payload.html || '';

                if (window.Wylde.refresh) {
                    window.Wylde.refresh();
                }

                syncHearts();

                show(empty, grid.children.length === 0);
            })
            .catch(() => {
                show(loading, false);

                if (error) {
                    error.textContent = page.dataset.unavailable || '';
                    error.hidden = !error.textContent;
                }
            })
            .then(() => {
                show(loading, false);
            });
    }

    function fill(template, value) {
        return template.replace('{}', value);
    }

    /* ── Amorçage ─────────────────────────────────────────────── */

    function render() {
        renderPage();
    }

    window.WyldeFavorites = {
        list: () => state.slice(),
        toggle,
        render,
        refresh: () => {
            state = read();
            syncCount();
            syncHearts();
            renderPage();
        }
    };

    function boot() {
        syncCount();
        syncHearts();
        renderPage();
    }

    // Après une navigation interne, le cœur du nouvel en-tête, celui des
    // nouvelles cartes et la page des favoris repartent du navigateur.
    Wylde.onSwap(boot);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();