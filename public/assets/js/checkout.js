/* ══════════════════════════════════════════════════════════════════
   WYLDE — checkout.js
   JavaScript natif, sans dépendance. Chargé en différé sur /checkout.

   Le canal de confirmation (site ou WhatsApp) pilote le libellé du
   bouton : le client doit savoir, avant de valider, qu'il va être
   redirigé vers WhatsApp et que sa commande sera déjà enregistrée.
   ══════════════════════════════════════════════════════════════════ */

(function () {
    'use strict';

    /**
     * Libellé du bouton selon le canal sélectionné.
     *
     * Les textes viennent des attributs data-* posés par le template :
     * une seule source de vérité, donc rien à resynchroniser en JS.
     */
    function syncSubmitLabel(form) {
        const submit = form.querySelector('[data-checkout-submit]');
        const label  = form.querySelector('[data-checkout-submit-label]');

        const chosen = form.querySelector('[data-channel-option]:checked');
        const isWhatsApp = !!(chosen && chosen.value === 'whatsapp');

        // La feuille de style colore le bouton selon le canal.
        form.dataset.channel = isWhatsApp ? 'whatsapp' : 'site';

        if (!submit || !label) {
            return;
        }

        // Le bouton nomme le moyen de paiement choisi : Wave, paiement à
        // la livraison, ou WhatsApp si le client a choisi ce canal.
        // « Valider la commande » seul ne l'apprend pas au client.
        const payment = form.querySelector('[data-payment-option]:checked');
        const method = payment ? payment.value : 'cod';

        let text;

        if (isWhatsApp) {
            text = submit.dataset.labelWhatsapp;
        } else if (method === 'wave') {
            text = submit.dataset.labelWave;
        } else {
            text = submit.dataset.labelCod || submit.dataset.labelSite;
        }

        if (text) {
            label.textContent = text;
        }

        // Le libellé change, l'icône aussi : on repart d'un état propre.
        submit.classList.toggle('is-whatsapp', isWhatsApp);
    }

    function init() {
        const form = document.querySelector('.checkout-form');

        if (!form || form.dataset.channelBound === '1') {
            return;
        }

        form.dataset.channelBound = '1';

        form.addEventListener('change', (event) => {
            if (event.target.matches('[data-channel-option], [data-payment-option]')) {
                syncSubmitLabel(form);
            }
        });

        form.addEventListener('submit', () => {
            const chosen = form.querySelector('[data-channel-option]:checked');

            // Petit retour immédiat : la redirection WhatsApp demande
            // quelques secondes sur mobile, le bouton ne doit pas rester
            // figé sans explication.
            if (chosen && chosen.value === 'whatsapp') {
                form.classList.add('is-sending');
            }
        });

        initSummaryToggle(form);
        syncSubmitLabel(form);
        initSteps(form);
    }

    /**
     * Bandeau de total repliable sur téléphone.
     *
     * Le total du bandeau suit le devis de livraison : sans cela, le
     * client verrait un montant différent de celui qu'il va payer.
     */
    function initSummaryToggle(form) {
        const aside = form.querySelector('[data-checkout-summary]');
        const toggle = form.querySelector('[data-summary-toggle]');

        if (!aside || !toggle) {
            return;
        }

        toggle.addEventListener('click', () => {
            const open = aside.classList.toggle('is-open');

            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        const total = form.querySelector('[data-total]');
        const mirror = form.querySelector('[data-summary-total]');

        if (!total || !mirror) {
            return;
        }

        new MutationObserver(() => {
            mirror.textContent = total.textContent;
        }).observe(total, { childList: true, characterData: true, subtree: true });
    }

    /* ══════════════════════════════════════════════════════════════
       ÉTAPES : Contact → Livraison → Paiement
       Un seul formulaire, trois panneaux affichés à tour de rôle.
       Les champs restent présents dans le HTML : sans JavaScript, les
       trois étapes sont visibles d'affilée et le formulaire se soumet
       normalement. JavaScript ne fait que guider la lecture.
       ══════════════════════════════════════════════════════════════ */

    const STEP_COUNT = 3;

    function initSteps(form) {
        const panels = Array.from(form.querySelectorAll('[data-step-panel]'));
        const items  = Array.from(document.querySelectorAll('[data-step-indicator]'));
        const prev   = form.querySelector('[data-step-prev]');
        const next   = form.querySelector('[data-step-next]');

        if (panels.length < 2 || !next) {
            return;
        }

        // Champs de chaque étape, pour valider avant d'avancer.
        const fieldsOf = (step) =>
            panels
                .filter((panel) => panel.dataset.stepPanel === String(step))
                .flatMap((panel) => Array.from(panel.querySelectorAll('input, select, textarea')));

        let current = Number(form.dataset.checkoutStep) || 1;

        /** L'étape est-elle remplie correctement ? Sinon, signale la
            première erreur et retourne false. */
        const validateStep = (step) => {
            let firstInvalid = null;

            fieldsOf(step).forEach((field) => {
                if (firstInvalid || !field.willValidate || field.checkValidity()) {
                    return;
                }

                firstInvalid = field;
            });

            if (firstInvalid) {
                firstInvalid.reportValidity();

                return false;
            }

            return true;
        };

        const show = (step, opts = {}) => {
            current = Math.min(Math.max(1, step), STEP_COUNT);

            form.dataset.checkoutStep = String(current);

            panels.forEach((panel) => {
                panel.hidden = panel.dataset.stepPanel !== String(current);
            });

            items.forEach((item) => {
                const index = Number(item.dataset.stepIndicator);

                item.classList.toggle('is-current', index === current);
                item.classList.toggle('is-done', index < current);
            });

            // Les deux boutons sont masqués dans le HTML : sans JavaScript,
            // le formulaire se présente d'un seul bloc et « Valider » suffit.
            if (prev) {
                prev.hidden = current === 1;
            }

            next.hidden = current === STEP_COUNT;

            // Au chargement, ne pas donner la main au premier champ : sur
            // mobile le clavier virtuel s'ouvrirait sans que le client le
            // demande. On ne recentre que lors d'un déplacement explicite.
            if (opts.focus !== false) {
                const first = fieldsOf(current).find((field) => field.offsetParent !== null);

                if (first && first.focus) {
                    first.focus({ preventScroll: true });
                }
            }
        };

        next.addEventListener('click', () => {
            if (current >= STEP_COUNT) {
                return;
            }

            // On n'avance qu'avec une étape valide : le client corrige
            // tout de suite plutôt qu'après l'envoi.
            if (validateStep(current)) {
                show(current + 1);
            }
        });

        if (prev) {
            prev.addEventListener('click', () => show(current - 1));
        }

        // Un clic sur une étape déjà franchie revient en arrière.
        items.forEach((item) => {
            item.addEventListener('click', () => {
                const index = Number(item.dataset.stepIndicator);

                if (index < current) {
                    show(index);
                }
            });
        });

        form.addEventListener('submit', (event) => {
            // Étapes 1 et 2 : on ne soumet jamais le formulaire ici. La
            // validation native s'étendrait aux panneaux masqués (étapes
            // suivantes) et ferait sauter la page vers un champ invisible.
            // On avance d'une étape, comme le bouton « Suivant ».
            if (current < STEP_COUNT) {
                event.preventDefault();

                if (validateStep(current)) {
                    show(current + 1);
                }
            }
        });

        show(current, { focus: false });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    if (window.Wylde && typeof window.Wylde.onSwap === 'function') {
        window.Wylde.onSwap(init);
    }
}());
