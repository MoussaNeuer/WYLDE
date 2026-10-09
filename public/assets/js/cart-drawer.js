/* ══════════════════════════════════════════════════════════════════
   WYLDE — cart-drawer.js
   Mini-panier en tiroir : lignes, quantités ±, total et barre de
   livraison offerte. Consomme le payload partagé de /api/cart.
   ══════════════════════════════════════════════════════════════════ */

(function () {
    'use strict';

    const Wylde = window.Wylde;

    if (!Wylde || typeof Wylde.api !== 'function') {
        return;
    }

    const api = Wylde.api;
    const apiUrl = Wylde.apiUrl;

    /* ── Textes, localized côté serveur pour éviter deux tables ── */

    const L = {};

    function loadStrings(root) {
        root.querySelectorAll('[data-cart-drawer-text]').forEach((node) => {
            L[node.dataset.cartDrawerText] = node.textContent.trim();
        });
    }

    /** Le HTML est la source des libellés ; on n'en duplique aucun. */
    function label(root, name, fallback) {
        const node = root.querySelector('[data-cart-drawer-text="' + name + '"]');

        return (node && node.textContent.trim()) || fallback;
    }

    /* ── Instance Bootstrap, créée à la demande ────────────────── */

    let drawer = null;

    function panel() {
        return document.querySelector('[data-cart-drawer]');
    }

    function instance() {
        const el = panel();

        if (!el || !window.bootstrap || !window.bootstrap.Offcanvas) {
            return null;
        }

        if (!drawer) {
            drawer = window.bootstrap.Offcanvas.getOrCreateInstance(el);
        }

        return drawer;
    }

    function open() {
        const inst = instance();

        if (inst) {
            inst.show();
        }
    }

    Wylde.openCartDrawer = open;

    /* ── Rendu ──────────────────────────────────────────────────── */

    function render(payload) {
        const el = panel();

        if (!el || !payload) {
            return;
        }

        loadStrings(el);

        const items = Array.isArray(payload.items) ? payload.items : [];

        const itemsBox = el.querySelector('[data-cart-drawer-items]');
        const empty = el.querySelector('[data-cart-drawer-empty]');
        const footer = el.querySelector('[data-cart-drawer-footer]');
        const totalBox = el.querySelector('[data-cart-drawer-total]');
        const countBox = el.querySelector('[data-cart-drawer-count]');

        if (totalBox) {
            totalBox.textContent = payload.total_text || '0';
        }

        if (countBox) {
            const count = Number(payload.count) || 0;

            countBox.textContent = String(count);
            countBox.hidden = count < 1;
        }

        if (empty) {
            empty.hidden = items.length > 0;
        }

        if (footer) {
            footer.hidden = items.length === 0;
        }

        renderItems(itemsBox, items);

        renderShipping(el, payload.shipping);

        // Le compteur de l'en-tête suit immédiatement : inutile d'aller
        // redemander l'état à /api/cart juste pour lui.
        const badge = document.querySelector('[data-cart-count]');

        if (badge) {
            const count = Number(payload.count) || 0;

            badge.dataset.count = String(count);
            badge.textContent = String(count);
        }
    }

    function renderItems(box, items) {
        if (!box) {
            return;
        }

        box.textContent = '';

        items.forEach((item) => {
            box.appendChild(row(item));
        });
    }

    /** Construit une ligne : photo, nom, taille, quantité ±, total. */
    function row(item) {
        const variantId = Number(item.variant_id) || 0;
        const quantity = Math.max(0, Number(item.quantity) || 0);
        const max = Math.max(1, Number(item.stock) || 1);

        const article = document.createElement('article');
        article.className = 'cart-drawer__row';
        article.dataset.variant = String(variantId);

        // Photo — ou un simple emplacement si le produit n'a pas d'image.
        if (item.image) {
            const media = document.createElement('a');
            media.className = 'cart-drawer__thumb';
            media.href = item.url;

            const img = document.createElement('img');
            img.src = item.image;
            img.alt = item.name || '';
            img.width = 72;
            img.height = 90;
            img.loading = 'lazy';
            img.decoding = 'async';

            media.appendChild(img);
            article.appendChild(media);
        } else {
            const blank = document.createElement('span');
            blank.className = 'cart-drawer__thumb cart-drawer__thumb--blank';
            blank.setAttribute('aria-hidden', 'true');
            article.appendChild(blank);
        }

        const body = document.createElement('div');
        body.className = 'cart-drawer__row-body';

        const title = document.createElement('a');
        title.className = 'cart-drawer__row-name';
        title.href = item.url;
        title.textContent = item.name || '';

        body.appendChild(title);

        // La taille est toujours informée : « Taille unique » remplace
        // le marqueur interne UNIQUE (libellé préparé côté serveur).
        if (item.size_label || item.size) {
            const size = document.createElement('span');
            size.className = 'cart-drawer__row-size';
            size.textContent = item.size_label || item.size;
            body.appendChild(size);
        }

        const controls = stepper(variantId, quantity, max);

        body.appendChild(controls);

        const lineTotal = document.createElement('span');
        lineTotal.className = 'cart-drawer__row-total';
        lineTotal.dataset.cartDrawerLineTotal = String(variantId);
        lineTotal.textContent = item.line_total_text || item.price_text || '';

        body.appendChild(lineTotal);

        article.appendChild(body);

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'cart-drawer__row-remove';
        remove.dataset.cartDrawerRemove = String(variantId);
        remove.setAttribute('aria-label', label(panel(), 'remove', L.remove || 'Retirer'));
        remove.textContent = '×';

        article.appendChild(remove);

        return article;
    }

    /** Contrôle quantité : « − », valeur, « + ». */
    function stepper(variantId, quantity, max) {
        const box = document.createElement('div');
        box.className = 'qty-stepper qty-stepper--sm';

        const minus = document.createElement('button');
        minus.type = 'button';
        minus.className = 'qty-stepper__btn';
        minus.dataset.cartDrawerStep = String(variantId);
        minus.dataset.cartDrawerDelta = '-1';
        minus.textContent = '−';
        minus.setAttribute('aria-label', '−');

        const value = document.createElement('span');
        value.className = 'qty-stepper__value';
        value.dataset.cartDrawerQty = String(variantId);
        value.textContent = String(quantity);
        // aria-live : le changement est annoncé sans déplacer le focus.
        value.setAttribute('aria-live', 'polite');

        const plus = document.createElement('button');
        plus.type = 'button';
        plus.className = 'qty-stepper__btn';
        plus.dataset.cartDrawerStep = String(variantId);
        plus.dataset.cartDrawerDelta = '1';
        plus.textContent = '+';
        plus.setAttribute('aria-label', '+');
        // Au stock, le « + » n'a plus rien à ajouter : on le neutralise
        // plutôt que de laisser une erreur serveur au clic suivant.
        plus.disabled = quantity >= max;

        box.append(minus, value, plus);

        return box;
    }

    function renderShipping(el, shipping) {
        const box = el.querySelector('[data-cart-drawer-shipping]');
        const text = el.querySelector('[data-cart-drawer-shipping-text]');
        const bar = el.querySelector('[data-cart-drawer-shipping-bar]');
        const fill = el.querySelector('.cart-drawer__shipping-fill');

        if (!box || !shipping) {
            if (box) {
                box.hidden = true;
            }

            return;
        }

        if (!shipping.enabled) {
            box.hidden = true;
            return;
        }

        box.hidden = false;

        if (text) {
            // La服务器 renvoie déjà la phrase traduite dans payload.shipping.
            text.textContent = shipping.text || '';
        }

        if (bar) {
            bar.setAttribute('aria-valuenow', String(shipping.percent));
        }

        if (fill) {
            fill.style.width = shipping.percent + '%';
        }
    }

    /* ── Mutations ──────────────────────────────────────────────── */

    const busy = new Set();

    async function setQuantity(variantId, quantity, trigger) {
        if (busy.has(variantId)) {
            return;
        }

        busy.add(variantId);

        if (trigger) {
            trigger.disabled = true;
        }

        try {
            const payload = await api(apiUrl('/api/cart/update'), {
                method: 'POST',
                body: JSON.stringify({ variant_id: variantId, quantity: quantity })
            });

            render(payload);

            if (payload.just_removed) {
                Wylde.toast(payload.just_removed, 'info', 3000);
            }

            if (payload.message) {
                Wylde.toast(payload.message, 'warning', 3500);
            }
        } catch (error) {
            Wylde.toast(error.message || 'Erreur', 'error');
        } finally {
            busy.delete(variantId);

            if (trigger && document.contains(trigger)) {
                trigger.disabled = false;
            }
        }
    }

    /* ── Écouteurs : délégués, le tiroir étant recréé à chaque page ── */

    function init() {
        const el = panel();

        if (!el) {
            return;
        }

        loadStrings(el);

        el.addEventListener('click', (event) => {
            const step = event.target.closest('[data-cart-drawer-step]');
            const drop = event.target.closest('[data-cart-drawer-remove]');

            if (step) {
                event.preventDefault();

                const variantId = Number(step.dataset.cartDrawerStep);
                const delta = Number(step.dataset.cartDrawerDelta);

                const current = el.querySelector(
                    '[data-cart-drawer-qty="' + variantId + '"]'
                );

                const next = (Number(current && current.textContent) || 1) + delta;

                // 0 ou moins : on retire la ligne, le serveur le fait.
                setQuantity(variantId, next, step);

                return;
            }

            if (drop) {
                event.preventDefault();
                setQuantity(Number(drop.dataset.cartDrawerRemove), 0, drop);
            }
        });
    }

    /* ── Chargement initial ─────────────────────────────────────── */

    let loaded = false;

    async function load() {
        if (loaded || !panel()) {
            return;
        }

        loaded = true;

        try {
            render(await api(apiUrl('/api/cart')));
        } catch (error) {
            // Panier indisponible : le tiroir restera vide, sans casser la page.
            loaded = false;
        }
    }

    /* ── Ouverture après un ajout ─────────────────────────────────
       Le serveur signale un ajout volontaire via open_drawer : le
       tiroir s'ouvre alors, que l'ajout vienne de la fiche produit
       ou d'une carte. Sans ce signal, on ne l'ouvre pas : un panier
       rafraîchi en arrière-plan ne doit pas interrompre la lecture. */

    document.addEventListener('wylde:cart-added', (event) => {
        const payload = (event && event.detail) || null;

        if (payload && payload.open_drawer === false) {
            return;
        }

        Wylde.cartDrawerSeen = true;

        load().then(() => open());
    });

    // Ouverture manuelle depuis l'en-tête : on mémorise le geste pour
    // que les ajouts suivants ouvrent le tiroir d'eux-mêmes.
    document.addEventListener('show.bs.offcanvas', (event) => {
        if (event.target && event.target.id === 'cartDrawer') {
            Wylde.cartDrawerSeen = true;
        }
    });

    if (typeof Wylde.onSwap === 'function') {
        Wylde.onSwap(() => {
            loaded = false;
            init();
            load();
        });
    }

    window.Wylde.renderCartDrawer = render;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    load();
})();
