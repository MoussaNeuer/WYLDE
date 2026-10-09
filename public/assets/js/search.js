/* WYLDE — overlay de recherche.
 *
 * Deux lettres suffisent pour déclencher la requête : au-delà d'un
 * caractère, chaque frappe enverrait une requête pour un prefixe trop
 * large, et le rate limit de /api/search s'en voudrait.
 *
 * Les éléments sont relus à chaque ouverture : la navigation interne
 * remplace tout le body, une référence conservée pointerait vers un
 * nœud détaché.
 */
(function () {
    'use strict';

    const MIN_CHARS = 2;
    const DEBOUNCE = 180;
    const MAX_RESULTS = 8;

    let timer = null;
    let inflight = null; // AbortController de la requête en cours.
    let token = 0;       // Empêche une réponse ancienne d'écraser la dernière.
    let activeIndex = -1;
    let lastQuery = '';
    let trigger = null;

    /* ── Accès au DOM ────────────────────────────────────────── */

    function overlay() {
        return document.querySelector('[data-search-overlay]');
    }

    function input() {
        return document.querySelector('[data-search-input]');
    }

    function labels() {
        // Les textes sont portés par l'overlay lui-même : un dictionnaire
        // en JavaScript serait dupliqué et traduit deux fois.
        const box = overlay();
        const get = (name) => (box ? box.dataset[name] || '' : '');

        return {
            hint: get('hint'),
            idle: get('idle'),
            resultsOne: get('resultsOne'),
            resultsMany: get('resultsMany'),
            empty: get('empty'),
            emptyHint: get('emptyHint'),
            all: get('all'),
            unavailable: get('unavailable')
        };
    }

    /* Remplace le marqueur `{}` des gabarits traduits. */
    function fill(template, value) {
        return template.replace('{}', value);
    }

    /* ── Ouverture / fermeture ───────────────────────────────── */

    function open(source) {
        const box = overlay();

        if (!box) {
            return;
        }

        const field = input();

        trigger = source || trigger;

        box.hidden = false;
        // Le CSS pilote l'ouverture ; la classe sert à l'animation.
        box.classList.add('is-open');
        document.documentElement.classList.add('has-overlay-open');

        const text = labels();

        status(field && field.value.trim().length >= MIN_CHARS ? '' : text.idle);
        render(null);

        if (field) {
            field.focus();
            field.select();
        }
    }

    function close() {
        const box = overlay();

        if (!box || box.hidden) {
            return;
        }

        box.hidden = true;
        box.classList.remove('is-open');
        document.documentElement.classList.remove('has-overlay-open');

        const field = input();

        if (field) {
            field.value = '';
        }

        lastQuery = '';
        activeIndex = -1;
        render(null);

        if (trigger && document.contains(trigger)) {
            trigger.focus();
        }
    }

    /* ── Rendu ───────────────────────────────────────────────── */

    function status(text) {
        const node = document.querySelector('[data-search-status]');

        if (node) {
            node.textContent = text;
            node.hidden = text === '';
        }
    }

    function render(results) {
        const list = document.querySelector('[data-search-results]');
        const all = document.querySelector('[data-search-all]');
        const text = labels();

        if (!list) {
            return;
        }

        list.textContent = '';
        activeIndex = -1;

        if (!results) {
            list.hidden = true;

            if (all) {
                all.hidden = true;
            }

            const field = input();

            if (!field || field.value.trim().length < MIN_CHARS) {
                status(text.hint);
            }

            return;
        }

        list.hidden = false;

        results.forEach((item, index) => {
            const li = document.createElement('li');
            const link = document.createElement('a');

            link.className = 'search-result';
            link.href = item.url;
            link.setAttribute('role', 'option');
            link.id = 'search-result-' + index;

            const thumb = document.createElement('span');
            thumb.className = 'search-result__thumb';

            if (item.image) {
                const img = document.createElement('img');
                img.src = item.image;
                img.alt = '';
                img.width = 48;
                img.height = 60;
                img.loading = 'lazy';
                img.decoding = 'async';
                thumb.appendChild(img);
            }

            const body = document.createElement('span');
            body.className = 'search-result__body';

            const name = document.createElement('span');
            name.className = 'search-result__name';
            name.textContent = item.name;

            const price = document.createElement('span');
            price.className = 'search-result__price';
            price.textContent = item.price_text || '';

            body.appendChild(name);
            body.appendChild(price);

            link.appendChild(thumb);
            link.appendChild(body);

            if (item.has_discount && item.list_price_text) {
                const old = document.createElement('s');
                old.className = 'search-result__old';
                old.textContent = item.list_price_text;
                body.appendChild(old);
            }

            li.appendChild(link);
            list.appendChild(li);
        });

        if (results.length === 0) {
            status(fill(text.empty, lastQuery) + ' ' + text.emptyHint);

            if (all) {
                all.hidden = true;
            }
        } else {
            const template = results.length > 1 ? text.resultsMany : text.resultsOne;

            status(fill(template, results.length));
        }

        if (all) {
            all.hidden = results.length === 0;
            all.href = Wylde.apiUrl('/shop?q=' + encodeURIComponent(lastQuery));
            all.textContent = text.all;
        }

        const field = input();

        if (field) {
            field.setAttribute('aria-expanded', 'true');
        }
    }

    /* ── Requête ─────────────────────────────────────────────── */

    function search(query) {
        lastQuery = query;

        if (inflight) {
            inflight.abort();
        }

        const current = ++token;
        const controller = new AbortController();

        inflight = controller;

        Wylde.api(Wylde.apiUrl('/api/search?q=' + encodeURIComponent(query)), {
            signal: controller.signal
        })
            .then((payload) => {
                // Une réponse arrivée après une frappe plus récente ne doit
                // jamais remplacer les résultats affichés.
                if (current !== token) {
                    return;
                }

                const results = Array.isArray(payload.results)
                    ? payload.results.slice(0, MAX_RESULTS)
                    : [];

                render(results);
            })
            .catch((error) => {
                if (error.name === 'AbortError' || current !== token) {
                    return;
                }

                const text = labels();

                status(text.unavailable);
            })
            .then(() => {
                if (inflight === controller) {
                    inflight = null;
                }
            });
    }

    /* ── Clavier ─────────────────────────────────────────────── */

    function move(step) {
        const list = document.querySelector('[data-search-results]');

        if (!list || list.hidden) {
            return;
        }

        const links = Array.from(list.querySelectorAll('a[href]'));

        if (links.length === 0) {
            return;
        }

        if (activeIndex >= 0 && links[activeIndex]) {
            links[activeIndex].classList.remove('is-active');
            links[activeIndex].removeAttribute('aria-selected');
        }

        activeIndex = (activeIndex + step + links.length) % links.length;

        const link = links[activeIndex];

        link.classList.add('is-active');
        link.setAttribute('aria-selected', 'true');

        if (link.scrollIntoView) {
            link.scrollIntoView({ block: 'nearest' });
        }
    }

    document.addEventListener('keydown', (event) => {
        const box = overlay();
        const isOpen = box && !box.hidden;

        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();

            if (isOpen) {
                close();
            } else {
                open(document.querySelector('[data-search-open]'));
            }

            return;
        }

        if (!isOpen) {
            return;
        }

        const field = input();

        if (event.key === 'Escape') {
            event.preventDefault();
            close();

            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            move(1);

            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            move(-1);

            return;
        }

        if (event.key === 'Enter' && activeIndex >= 0) {
            const list = document.querySelector('[data-search-results]');
            const links = list ? list.querySelectorAll('a[href]') : [];
            const link = links[activeIndex];

            if (link) {
                event.preventDefault();
                close();
                Wylde.nav.go(link.href);
            }

            return;
        }

        // Le focus ne doit pas s'échapper du dialogue.
        if (event.key === 'Tab' && field) {
            const focusables = box.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled])'
            );

            if (focusables.length === 0) {
                return;
            }

            const first = focusables[0];
            const last = focusables[focusables.length - 1];

            if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            } else if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            }
        }
    });

    /* ── Ouverture depuis n'importe quel déclencheur ─────────── */

    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-search-open]');

        if (opener) {
            event.preventDefault();

            // Déclenché depuis le menu : on referme l'offcanvas avant
            // d'ouvrir l'overlay, sinon le voile du menu reste au-dessus.
            const nav = opener.closest('.offcanvas.show');

            if (nav && window.bootstrap && window.bootstrap.Offcanvas) {
                window.bootstrap.Offcanvas.getOrCreateInstance(nav).hide();

                // Laisse le panneau se retirer avant de poser l'overlay,
                // sans quoi les deux animations se chevauchent.
                window.setTimeout(() => open(opener), 220);
            } else {
                open(opener);
            }

            return;
        }

        if (event.target.closest('[data-search-close]')) {
            event.preventDefault();
            close();
        }
    });

    document.addEventListener('input', (event) => {
        if (!input() || event.target !== input()) {
            return;
        }

        const query = event.target.value.trim();

        if (timer) {
            window.clearTimeout(timer);
        }

        if (query.length < MIN_CHARS) {
            if (inflight) {
                inflight.abort();
            }

            render(null);

            return;
        }

        timer = window.setTimeout(() => search(query), DEBOUNCE);
    });

    /* ── Amorçage ─────────────────────────────────────────────── */

    function revealTriggers() {
        // Le bouton n'existe qu'avec JavaScript : sans lui, l'overlay
        // resterait inaccessible depuis l'en-tête.
        document.querySelectorAll('[data-search-open]').forEach((button) => {
            button.hidden = false;
        });
    }

    function boot() {
        revealTriggers();

        const field = input();

        if (field && field.value.trim().length >= MIN_CHARS) {
            search(field.value.trim());
        }
    }

    Wylde.onSwap(() => {
        close();
        boot();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();