/* ══════════════════════════════════════════════════════════════════
   WYLDE — product.js
   JavaScript natif, sans dépendance. Chargé en différé sur la fiche
   produit uniquement.
   Couvre : le sélecteur de taille (prix, stock, quantité).
   ══════════════════════════════════════════════════════════════════ */

(function () {
    'use strict';

    const L = (document.documentElement.dataset.locale === 'en')
        ? {
            soldOut:   'Out of stock',
            addToCart: 'Add to cart',
            priceFor:  'Price for size {size}'
        }
        : {
            soldOut:   'Rupture de stock',
            addToCart: 'Ajouter au panier',
            priceFor:  'Prix pour la taille {size}'
        };

    /**
     * Données des variantes, déposées par le template en JSON.
     * Le seuil et les libellés de stock sont calculés par stock_status()
     * côté serveur : le JS ne fait que les recopier.
     */
    function readPayload() {
        const node = document.querySelector('[data-product-sizes]');

        if (!node) {
            return null;
        }

        try {
            const parsed = JSON.parse(node.textContent || 'null');

            if (!parsed || typeof parsed.variants !== 'object' || parsed.variants === null) {
                return null;
            }

            return parsed;
        } catch (error) {
            return null;
        }
    }

    function initSizeSelector() {
        const select = document.querySelector('[data-product-size]');
        const payload = readPayload();

        if (!select || !payload) {
            return;
        }

        const form   = select.closest('form');
        const price  = document.querySelector('.product-info__price');
        const stock  = document.querySelector('.product-info__stock');
        const final  = price ? price.querySelector('.product-info__price-final') : null;
        const old    = price ? price.querySelector('.product-info__price-old') : null;
        const badge  = price ? price.querySelector('.badge') : null;
        const qty    = form ? form.querySelector('#product-qty') : null;
        const submit = form ? form.querySelector('button[type="submit"]') : null;

        if (!form || !price || !stock || !final || !submit) {
            return;
        }

        const apply = () => {
            const variant = payload.variants[select.value];

            if (!variant) {
                return;
            }

            final.textContent = variant.price;

            // La remise affichée vient du produit, pas de la variante :
            // on ne la montre que si le prix de la taille est celui du
            // produit, sinon l'ancien prix et le pourcentage mentiraient.
            const samePrice = Number(variant.amount) === Number(payload.base);

            if (old) {
                old.hidden = !samePrice;
            }

            if (badge) {
                badge.hidden = !samePrice;
            }

            stock.textContent = variant.label;
            stock.classList.remove('product-info__stock--in', 'product-info__stock--low', 'product-info__stock--out');
            stock.classList.add('product-info__stock--' + variant.level);

            if (qty) {
                qty.max      = String(Math.max(1, variant.stock));
                qty.disabled = !variant.available;
                qty.value    = '1';
            }

            submit.disabled    = !variant.available;
            submit.textContent = variant.available ? L.addToCart : L.soldOut;

            price.setAttribute('aria-label', L.priceFor.replace('{size}', variant.size));
        };

        select.addEventListener('change', apply);

        // La taille par défaut est déjà rendue par le serveur : on
        // aligne quand même le stock, la quantité et l'état du bouton.
        apply();
    }

    function init() {
        initSizeSelector();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
