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

    /**
     * Pastilles de tailles : elles pilotent le <select> caché.
     *
     * Le <select> reste la source de vérité du formulaire, donc de la
     * valeur envoyée au serveur ; les pastilles ne font que le reflector.
     */
    function initSizePills() {
        const pills = document.querySelector('[data-size-pills]');
        const select = document.querySelector('[data-product-size]');
        const payload = readPayload();

        if (!pills || !select) {
            return;
        }

        if (pills.dataset.pillsWired === '1') {
            return;
        }

        pills.dataset.pillsWired = '1';

        const selectFor = (variantId) =>
            select.querySelector('option[value="' + variantId + '"]');

        const press = (variantId) => {
            pills.querySelectorAll('[data-size-pill]').forEach((pill) => {
                pill.setAttribute(
                    'aria-pressed',
                    pill.dataset.variantId === String(variantId) ? 'true' : 'false'
                );
            });
        };

        // Un clic sur une pastille équivaut à choisir l'option correspondante :
        // le <select> déclenche alors la mise à jour du prix et du stock.
        pills.addEventListener('click', (event) => {
            const pill = event.target.closest('[data-size-pill]');

            if (!pill || pill.disabled) {
                return;
            }

            const variantId = pill.dataset.variantId;
            const option = selectFor(variantId);

            if (!option || option.disabled) {
                return;
            }

            select.value = variantId;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            press(variantId);
        });

        // Le guide des tailles, si la fenêtre existe.
        document.addEventListener('click', (event) => {
            const opener = event.target.closest('[data-size-guide-open]');
            const modal = document.querySelector('[data-size-guide]');

            if (!opener || !modal || !window.bootstrap || !window.bootstrap.Modal) {
                return;
            }

            event.preventDefault();

            window.bootstrap.Modal.getOrCreateInstance(modal).show();
        });

        // Un retour arrière ou un changement via le <select> doit rester
        // cohérent avec les pastilles.
        select.addEventListener('change', () => {
            press(select.value);
        });

        press(select.value);

        // Le message de rareté suit la taille choisie.
        if (payload) {
            const scarcity = document.querySelector('[data-product-scarcity]');

            if (!scarcity) {
                return;
            }

            const texts = payload.scarcity || {};

            select.addEventListener('change', () => {
                const variant = payload.variants[select.value];
                const text = variant ? texts[String(variant.id)] : null;

                scarcity.hidden = !text;
                scarcity.textContent = text || '';
            });
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

        // La navigation instantanée remplace le <body> sans recharger ce
        // fichier : on marque le <select> pour ne jamais l'écouter deux fois,
        // et on ré-initialise au hook d'échange (voir plus bas).
        if (select.dataset.sizeWired === '1') {
            return;
        }

        select.dataset.sizeWired = '1';

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

    /* ── Galerie : vignettes, flèches, visionneuse, défilement auto ── */

    const GALLERY_AUTOPLAY_MS = 4000;

    let galleryTimer = null;
    let galleryCurrent = null;
    let galleryVisibilityBound = false;

    function galleryStop() {
        if (galleryTimer !== null) {
            clearInterval(galleryTimer);
            galleryTimer = null;
        }
    }

    function gallerySchedule(fn) {
        galleryStop();
        galleryTimer = setInterval(fn, GALLERY_AUTOPLAY_MS);
    }

    function initGallery() {
        // La navigation instantanée ré-exécute init() sans recharger le
        // JS : on coupe d'abord le défilement éventuel de la page précédente.
        galleryStop();
        galleryCurrent = null;

        const main = document.querySelector('[data-gallery-main]');
        const image = document.querySelector('[data-gallery-image]');
        const thumbs = Array.from(document.querySelectorAll('[data-gallery-thumb]'));

        if (!main || !image || thumbs.length === 0) {
            return;
        }

        const lightbox = document.querySelector('[data-lightbox]');
        const lightboxImage = document.querySelector('[data-lightbox-image]');
        const counter = document.querySelector('[data-gallery-count]');
        const progressBars = Array.from(
            document.querySelectorAll('[data-gallery-progress] .product-gallery__progress-bar')
        );
        let index = 0;

        const show = (next) => {
            const count = thumbs.length;

            index = ((next % count) + count) % count;

            const src = thumbs[index].dataset.src;

            if (!src) {
                return;
            }

            // Le <img> principal porte un srcset (variantes WebP de la
            // PREMIÈRE vue). Dès le premier changement, ce srcset doit
            // partir : il décrit une seule photo et le navigateur
            // continuerait de la servir, quel que soit le src assigné —
            // c'est pour cela que les boutons semblaient ne rien faire.
            image.removeAttribute('srcset');
            image.src = src;

            thumbs.forEach((thumb, i) => {
                const active = i === index;

                thumb.classList.toggle('is-active', active);
                thumb.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            // La vignette active reste visible dans la bande.
            const thumb = thumbs[index];

            if (thumb && typeof thumb.scrollIntoView === 'function') {
                thumb.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            }

            if (counter) {
                counter.textContent = (index + 1) + ' / ' + count;
            }

            if (progressBars.length > 0) {
                progressBars.forEach((bar, i) => {
                    bar.classList.toggle('is-seen', i < index);
                    bar.classList.toggle('is-current', i === index);
                });

                const fill = progressBars[index]
                    ? progressBars[index].querySelector('.product-gallery__progress-fill')
                    : null;

                if (fill) {
                    // Redémarre la barre du défilement auto, même après un
                    // retour vers une barre déjà remplie.
                    fill.style.animation = 'none';
                    void fill.offsetWidth;
                    fill.style.animation = '';
                }
            }

            // La vue suivante est préchargée : sa bascule sera immédiate.
            const nextIndex = (index + 1) % count;
            const nextSrc = thumbs[nextIndex].dataset.src;

            if (nextSrc) {
                const preload = new Image();
                preload.src = nextSrc;
            }

            // Apparition douce de la vue (sauf mouvement réduit).
            if (!reducedMotion) {
                image.classList.remove('gallery-photo-in');
                void image.offsetWidth;
                image.classList.add('gallery-photo-in');
            }
        };

        // Au-delà de deux photos, la galerie défile toute seule : le client
        // voit chaque vue sans avoir à cliquer. Toute interaction relance le
        // compte à rebours (la photo choisie ne s'efface pas aussitôt) et
        // certains états suspendent le défilement : survol, visionneuse,
        // onglet en arrière-plan, mouvement réduit de l'utilisateur.
        const reducedMotion = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const canAutoplay = thumbs.length > 2 && !reducedMotion;

        const resume = () => {
            if (canAutoplay) {
                gallerySchedule(() => show(index + 1));
            }
        };

        const pause = () => galleryStop();

        galleryCurrent = { resume, pause };

        thumbs.forEach((thumb, i) => {
            thumb.addEventListener('click', () => {
                show(i);
                resume();
            });
        });

        const prev = document.querySelector('[data-gallery-prev]');
        const next = document.querySelector('[data-gallery-next]');

        if (prev) {
            prev.addEventListener('click', () => {
                show(index - 1);
                resume();
            });
        }

        if (next) {
            next.addEventListener('click', () => {
                show(index + 1);
                resume();
            });
        }

        // Balayage sur l'image principale : le geste le plus naturel sur
        // mobile pour feuilleter les photos.
        let touchX = null;

        main.addEventListener('touchstart', (event) => {
            touchX = event.touches[0].clientX;
        }, { passive: true });

        main.addEventListener('touchend', (event) => {
            if (touchX === null) {
                return;
            }

            const delta = event.changedTouches[0].clientX - touchX;

            touchX = null;

            if (Math.abs(delta) < 40) {
                return;
            }

            show(delta < 0 ? index + 1 : index - 1);
            resume();
        }, { passive: true });

        // Survol : on ne défile pas sous le curseur.
        const canHover = window.matchMedia
            && window.matchMedia('(hover: hover)').matches;

        if (canHover) {
            main.addEventListener('mouseenter', pause);
            main.addEventListener('mouseleave', resume);
        }

        // Onglet en arrière-plan : inutile de défiler ce que l'on ne voit pas.
        if (!galleryVisibilityBound) {
            galleryVisibilityBound = true;

            document.addEventListener('visibilitychange', () => {
                if (!galleryCurrent) {
                    return;
                }

                if (document.hidden) {
                    galleryCurrent.pause();
                } else {
                    galleryCurrent.resume();
                }
            });
        }

        // ── Visionneuse ──
        if (!lightbox || !lightboxImage) {
            resume();

            return;
        }

        let lastFocus = null;

        const open = () => {
            pause();

            lastFocus = document.activeElement;

            lightboxImage.src = image.src;
            lightboxImage.alt = image.alt || '';
            lightbox.hidden = false;
            lightbox.classList.add('is-open');

            document.body.style.overflow = 'hidden';

            const close = lightbox.querySelector('[data-lightbox-close]');

            if (close) {
                close.focus();
            }
        };

        const close = () => {
            lightbox.classList.remove('is-open');
            lightbox.hidden = true;
            lightboxImage.src = '';

            document.body.style.overflow = '';

            if (lastFocus && typeof lastFocus.focus === 'function') {
                lastFocus.focus({ preventScroll: true });
            }

            resume();
        };

        const zoom = document.querySelector('[data-gallery-zoom]');

        if (zoom) {
            zoom.addEventListener('click', open);
        }

        lightbox.addEventListener('click', (event) => {
            // Un clic sur l'image elle-même doit aussi refermer.
            if (event.target === lightbox || event.target === lightboxImage) {
                close();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (lightbox.hidden) {
                return;
            }

            if (event.key === 'Escape') {
                close();
            } else if (event.key === 'ArrowLeft') {
                show(index - 1);
            } else if (event.key === 'ArrowRight') {
                show(index + 1);
            }
        });

        resume();
    }

    /* ── Barre d'achat fixe (mobile) ─────────────────────────────── */

    let buybarBound = false;

    /**
     * La barre n'apparaît que lorsque le vrai bouton « Ajouter au panier »
     * a quitté l'écran, et reflète fidèlement taille, prix et quantité.
     */
    function syncBuybar() {
        const bar = document.querySelector('[data-buybar]');

        if (!bar) {
            return;
        }

        const anchor = document.querySelector('.product-form button[type="submit"]');

        if (anchor) {
            const box = anchor.getBoundingClientRect();
            const offscreen = box.bottom < 0 || box.top > window.innerHeight;

            bar.hidden = !offscreen;
        } else {
            bar.hidden = true;
        }
    }

    function initBuybar() {
        if (!document.querySelector('[data-buybar]')) {
            return;
        }

        const barSelect = document.querySelector('[data-buybar-size]');
        const barVariant = document.querySelector('[data-buybar-variant]');
        const barQty = document.querySelector('[data-buybar-qty]');
        const barPrice = document.querySelector('[data-buybar-price]');
        const barCta = document.querySelector('.buybar__cta');

        const mainSelect = document.querySelector('[data-product-size]');
        const mainQty = document.querySelector('#product-qty');
        const mainPrice = document.querySelector('.product-info__price-final');

        // Recalcule le contenu de la barre depuis le formulaire principal.
        const mirror = () => {
            if (barVariant && mainSelect) {
                barVariant.value = mainSelect.value;
            }

            if (barQty && mainQty) {
                barQty.value = mainQty.value || '1';
            }

            if (barPrice && mainPrice) {
                barPrice.textContent = mainPrice.textContent;
            }

            // Sans taille choisie, l'achat direct est bloqué.
            if (barCta && mainSelect) {
                const option = mainSelect.options[mainSelect.selectedIndex];
                const usable = !!option && !option.disabled;

                barCta.disabled = !usable;
            }

            if (barSelect && mainSelect) {
                barSelect.value = mainSelect.value;
            }
        };

        if (barSelect && mainSelect) {
            barSelect.addEventListener('change', () => {
                if (!barSelect.value) {
                    return;
                }

                mainSelect.value = barSelect.value;
                mainSelect.dispatchEvent(new Event('change', { bubbles: true }));

                syncBuybar();
            });
        }

        if (mainSelect) {
            mainSelect.addEventListener('change', () => {
                mirror();
                syncBuybar();
            });
        }

        if (mainQty) {
            mainQty.addEventListener('change', () => {
                mirror();
                syncBuybar();
            });
        }

        mirror();

        if (!buybarBound) {
            buybarBound = true;

            window.addEventListener('scroll', syncBuybar, { passive: true });
            window.addEventListener('resize', syncBuybar, { passive: true });
        }

        syncBuybar();
    }

    function init() {
        initSizeSelector();
        initSizePills();
        initGallery();
        initBuybar();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Nouvelle page servie par la navigation instantanée : on rebrosse.
    if (window.Wylde && typeof window.Wylde.onSwap === 'function') {
        window.Wylde.onSwap(init);
    }
}());
