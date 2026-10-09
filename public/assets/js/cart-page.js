/* ══════════════════════════════════════════════════════════════════
   WYLDE — cart-page.js
   Page panier : quantité ± sans rechargement, retrait par ligne,
   barre de livraison offerte. Les mêmes endpoints servent le tiroir
   et cette page : un seul jeu de règles côté serveur.
   ══════════════════════════════════════════════════════════════════ */

(function () {
    'use strict';

    const Wylde = window.Wylde;

    if (!Wylde || typeof Wylde.api !== 'function') {
        return;
    }

    const api = Wylde.api;
    const apiUrl = Wylde.apiUrl;
    const busy = new Set();

    /* ── Rendu ──────────────────────────────────────────────────── */

    function table() {
        return document.querySelector('[data-cart-table]');
    }

    function syncRows(payload) {
        const items = Array.isArray(payload.items) ? payload.items : [];

        items.forEach((item) => {
            const variantId = Number(item.variant_id);
            const quantity = Number(item.quantity) || 0;
            const max = Math.max(1, Number(item.stock) || 1);

            const value = document.querySelector('[data-cart-qty="' + variantId + '"]');
            const hidden = document.querySelector('[data-cart-qty-input="' + variantId + '"]');
            const total = document.querySelector('[data-cart-line-total="' + variantId + '"]');

            if (value) {
                value.textContent = String(quantity);
            }

            // Le champ caché est la source de vérité du formulaire de repli.
            if (hidden) {
                hidden.value = String(quantity);
            }

            if (total) {
                total.textContent = item.line_total_text || item.price_text || '';
            }

            // Au stock, « + » n'a plus rien à ajouter.
            document.querySelectorAll('[data-cart-step="' + variantId + '"]').forEach((btn) => {
                if (btn.dataset.cartDelta === '1') {
                    btn.disabled = quantity >= max;
                }
            });
        });

        // Une ligne retirée disparaît du panier : on retire sa ligne du
        // tableau plutôt que de recharger la page.
        const rows = table();

        if (rows) {
            rows.querySelectorAll('[data-cart-row]').forEach((row) => {
                const variantId = Number(row.dataset.cartRow);

                if (!items.some((item) => Number(item.variant_id) === variantId)) {
                    row.remove();

                    const orphan = document.getElementById('cart-remove-' + variantId);

                    if (orphan) {
                        orphan.remove();
                    }
                }
            });
        }
    }

    function render(payload) {
        if (!payload) {
            return;
        }

        const count = Number(payload.count) || 0;

        document.querySelectorAll('[data-cart-count]').forEach((node) => {
            node.textContent = String(count);
            node.dataset.count = String(count);
        });

        document.querySelectorAll('[data-cart-total]').forEach((node) => {
            node.textContent = payload.total_text || '0';
        });

        renderShipping(payload.shipping);

        syncRows(payload);

        // Panier vide : on laisse la page se recharger pour afficher
        // l'état vide rendu par le serveur (titre, lien vers la boutique).
        // Le cache LRU de la navigation est purgé d'abord : un document
        // de la page panier déjà préchargé serait périmé après retrait.
        if (payload.empty) {
            window.Wylde.nav.clear();
            window.Wylde.nav.go(payload.cart_url);
        }
    }

    function renderShipping(shipping) {
        const box = document.querySelector('[data-cart-freeship]');

        if (!box || !shipping) {
            return;
        }

        if (!shipping.enabled) {
            box.hidden = true;
            return;
        }

        box.hidden = false;

        const text = box.querySelector('[data-cart-freeship-text]');
        const bar = box.querySelector('[data-cart-freeship-bar]');
        const fill = box.querySelector('.freeship__fill');

        if (text) {
            text.textContent = shipping.text || '';
        }

        if (bar) {
            bar.setAttribute('aria-valuenow', String(shipping.percent));
        }

        if (fill) {
            fill.style.width = shipping.percent + '%';
        }

        box.classList.toggle('freeship--reached', Boolean(shipping.reached));
    }

    /* ── Mutations ──────────────────────────────────────────────── */

    async function setQuantity(variantId, quantity, trigger) {
        if (busy.has(variantId)) {
            return;
        }

        busy.add(variantId);

        if (trigger) {
            trigger.disabled = true;
        }

        let ok = false;

        try {
            const payload = await api(apiUrl('/api/cart/update'), {
                method: 'POST',
                body: JSON.stringify({ variant_id: variantId, quantity: quantity })
            });

            // syncRows() recalculé le « + » à partir du stock renvoyé par
            // le serveur : c'est lui qui fait foi, pas notre estimation.
            render(payload);
            ok = true;

            if (payload.just_removed) {
                Wylde.toast(payload.just_removed, 'info', 3000);
            }
        } catch (error) {
            Wylde.toast(error.message || 'Erreur', 'error');
        } finally {
            busy.delete(variantId);

            // Après un échec, le bouton resterait bloqué : on le libère.
            if (!ok && trigger && document.contains(trigger)) {
                trigger.disabled = false;
            }
        }
    }

    async function removeLine(variantId, trigger) {
        const form = document.getElementById('cart-remove-' + variantId);

        if (!form) {
            return;
        }

        try {
            const payload = await api(apiUrl('/api/cart/remove'), {
                method: 'POST',
                body: JSON.stringify({ variant_id: variantId })
            });

            render(payload);

            if (payload.just_removed) {
                Wylde.toast(payload.just_removed, 'info', 3000);
            }
        } catch (error) {
            Wylde.toast(error.message || 'Erreur', 'error');
        } finally {
            if (trigger) {
                trigger.disabled = false;
            }
        }
    }

    /* ── Écouteurs ──────────────────────────────────────────────── */

    function init() {
        const root = document.querySelector('[data-cart-table]');

        if (!root) {
            return;
        }

        // Avec JavaScript, le formulaire ne sert plus que de repli : on
        // neutralise sa soumission au clavier et on affiche le bouton
        // « Mettre à jour » seulement si l'API est indisponible.
        const form = document.querySelector('[data-cart-form]');

        if (form) {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
            });
        }

        root.addEventListener('click', (event) => {
            const step = event.target.closest('[data-cart-step]');

            if (step) {
                event.preventDefault();

                const variantId = Number(step.dataset.cartStep);
                const delta = Number(step.dataset.cartDelta);

                const current = document.querySelector('[data-cart-qty="' + variantId + '"]');
                const next = (Number(current && current.textContent) || 1) + delta;

                if (next < 1) {
                    // Le « − » sur une quantité de 1 retire la ligne :
                    // le serveur fait la bascule avec l'API de retrait.
                    removeLine(variantId, step);
                    return;
                }

                setQuantity(variantId, next, step);
                return;
            }

            const drop = event.target.closest('[data-cart-remove]');

            if (drop) {
                event.preventDefault();

                // Avec JavaScript, la ligne part directement par l'API.
                // Pas de confirm() système : sur mobile cette fenêtre peut
                // être ignorée ou bloquée (webview, iframe), et le retrait
                // se terminait sans rien faire. Le repli sans JavaScript
                // conserve sa confirmation (data-confirm du formulaire).
                const variantId = Number(drop.dataset.cartRemove);

                drop.disabled = true;
                removeLine(variantId, drop);
            }
        });
    }

    if (typeof Wylde.onSwap === 'function') {
        Wylde.onSwap(init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();