/* ══════════════════════════════════════════════════════════════════
   WYLDE — quick-add.js
   Ajout au panier depuis les cartes produit : le mini-panier s'ouvre
   ensuite tout seul. Sans JavaScript, la carte reste un simple lien.
   ══════════════════════════════════════════════════════════════════ */

(function () {
    'use strict';

    const Wylde = window.Wylde;

    if (!Wylde || typeof Wylde.api !== 'function') {
        return;
    }

    const api = Wylde.api;
    const apiUrl = Wylde.apiUrl;

    async function addToCart(variantId, quantity, trigger) {
        if (!trigger) {
            trigger = { dataset: {}, tagName: 'SPAN' };
        }

        if (trigger.dataset.quickBusy === '1') {
            return;
        }

        trigger.dataset.quickBusy = '1';

        if (trigger.tagName === 'BUTTON') {
            trigger.disabled = true;
        }

        try {
            const payload = await api(apiUrl('/api/cart/add'), {
                method: 'POST',
                body: JSON.stringify({ variant_id: variantId, quantity: quantity })
            });

            if (typeof Wylde.renderCartDrawer === 'function') {
                Wylde.renderCartDrawer(payload);
            }

            // Le tiroir prend le relais : le client voit ce qu'il vient
            // d'ajouter et peut enchaîner sur « Commander ».
            if (payload.open_drawer) {
                document.dispatchEvent(new CustomEvent('wylde:cart-added', {
                    detail: payload
                }));
            } else {
                Wylde.toast(payload.just_added || '', 'success', 3000);
            }
        } catch (error) {
            Wylde.toast(error.message || 'Erreur', 'error');
        } finally {
            delete trigger.dataset.quickBusy;

            if (trigger.tagName === 'BUTTON') {
                trigger.disabled = false;
            }
        }
    }

    /* ── Fiche produit : ajout sans rechargement ──────────────────
       Le formulaire produit et la barre d'achat posteront vers
       /cart/add. On les intercepte pour passer par l'API : le tiroir
       s'ouvre alors sur place, sans laisser la page se recharger.
       Sans JavaScript, la soumission classique prend le relais. */

    function variantIdFromForm(form) {
        const field = form.querySelector('[name="variant_id"]');

        return field ? Number(field.value) || 0 : 0;
    }

    function quantityFromForm(form) {
        const field = form.querySelector('[name="quantity"]');

        return Math.max(1, Number(field && field.value) || 1);
    }

    function bindForms() {
        document.querySelectorAll('form.product-form, form[data-buybar-form]').forEach((form) => {
            if (form.dataset.quickWired === '1') {
                return;
            }

            form.dataset.quickWired = '1';

            form.addEventListener('submit', (event) => {
                const variantId = variantIdFromForm(form);

                // Sans taille choisie, on laisse le navigateur afficher
                // son message de validation sur le champ obligatoire.
                if (variantId <= 0) {
                    return;
                }

                event.preventDefault();

                addToCart(variantId, quantityFromForm(form), event.submitter);
            });
        });
    }

    function init() {
        bindForms();

        // Le clic est délégué au document, donc l'écouteur survivrait aux
        // navigations instantanées : sans ce garde-fou, un second passage
        // de la fiche produit doublerait chaque ajout au panier.
        if (document.documentElement.dataset.quickAddBound === '1') {
            return;
        }

        document.documentElement.dataset.quickAddBound = '1';

        document.addEventListener('click', (event) => {
            // L'icône panier de la carte déplie simplement les tailles.
            const toggle = event.target.closest('[data-quick-toggle]');

            if (toggle) {
                event.preventDefault();

                const sizes = toggle.closest('.product-card__quick')
                    ?.querySelector('[data-quick-sizes]');
                const hint  = toggle.closest('.product-card__quick')
                    ?.querySelector('[data-quick-hint]');

                if (!sizes) {
                    return;
                }

                const open = sizes.hidden;

                sizes.hidden = !open;

                if (hint) {
                    hint.hidden = !open;
                }

                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                return;
            }

            // Taille unique : ajout direct.
            const single = event.target.closest('[data-quick-single]');

            if (single) {
                event.preventDefault();
                addToCart(Number(single.dataset.quickSingle), 1, single);
                return;
            }

            // Plusieurs tailles : la pastille ajoute directement la
            // taille cliquée. Aucun second clic, aucune fenêtre.
            const pill = event.target.closest('[data-quick-variant]');

            if (pill) {
                event.preventDefault();

                if (pill.disabled) {
                    return;
                }

                // La taille choisie est ajoutée : la liste n'a plus lieu
                // d'être ouverte.
                const group = pill.closest('.product-card__quick');
                const sizes = group?.querySelector('[data-quick-sizes]');

                if (sizes) {
                    sizes.hidden = true;
                }

                group?.querySelector('[data-quick-toggle]')
                    ?.setAttribute('aria-expanded', 'false');

                addToCart(Number(pill.dataset.quickVariant), 1, pill);
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