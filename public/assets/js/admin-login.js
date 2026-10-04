/* ══════════════════════════════════════════════════════════════════
   WYLDE — admin-login.js
   Connexion au back-office : affichage du mot de passe, détection de
   la touche Verr. Maj, retour visuel d'erreur, verrouillage pendant
   l'envoi. Chargé en différé sur la seule page de connexion admin.
   ══════════════════════════════════════════════════════════════════ */

(function () {
    'use strict';

    const root = document.querySelector('[data-admin-login]');

    if (!root) {
        return;
    }

    const L = (window.Wylde && window.Wylde.locale === 'en')
        ? { show: 'Show password', hide: 'Hide password', caps: 'Caps Lock is on' }
        : { show: 'Afficher le mot de passe', hide: 'Masquer le mot de passe', caps: 'Verr. Maj activée' };

    /* ── Afficher / masquer le mot de passe ────────────────────── */

    root.querySelectorAll('[data-alx-toggle-password]').forEach((button) => {
        const field  = button.closest('[data-alx-field]');
        const input  = field ? field.querySelector('input[type="password"], input[type="text"]') : null;

        if (!field || !input) {
            return;
        }

        button.addEventListener('click', () => {
            const shown = input.type === 'text';

            input.type = shown ? 'password' : 'text';
            button.setAttribute('aria-pressed', shown ? 'false' : 'true');
            button.setAttribute('aria-label', shown ? L.show : L.hide);
            button.classList.toggle('is-on', !shown);

            // Conserve la position du curseur pour ne pas perdre la saisie.
            const end = input.value.length;

            input.focus({ preventScroll: true });

            try {
                input.setSelectionRange(end, end);
            } catch (e) { /* type password non sélectionnable sur iOS */ }

            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    /* ── Libellé flottant : aussi après restauration / auto-fill ─ */

    const syncFilled = () => {
        root.querySelectorAll('[data-alx-field]').forEach((field) => {
            const input = field.querySelector('.alx-input');

            if (input) {
                field.classList.toggle('is-filled', input.value !== '');
            }
        });
    };

    syncFilled();

    /* ── Touche Verr. Maj ──────────────────────────────────────── */

    const caps = root.querySelector('[data-alx-caps]');
    const password = root.querySelector('#admin-password');

    if (caps && password) {
        const syncCaps = (event) => {
            const on = event.getModifierState
                && event.getModifierState('CapsLock')
                && password.type === 'password';

            caps.hidden = !on;
        };

        password.addEventListener('keyup', syncCaps);
        password.addEventListener('keydown', syncCaps);
        password.addEventListener('blur', () => { caps.hidden = true; });
    }

    /* ── Erreur : secousse du champ fautif ─────────────────────── */

    root.querySelectorAll('.alx-field.is-error').forEach((field) => {
        const input = field.querySelector('.alx-input');
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (!input || reduce) {
            return;
        }

        field.classList.add('is-shaking');

        input.addEventListener('animationend', () => field.classList.remove('is-shaking'), { once: true });

        // Curseur posé sur le champ fautif : l'erreur est immédiatement visible.
        input.focus({ preventScroll: true });
        input.setSelectionRange(input.value.length, input.value.length);
    });

    /* ── Envoi : verrouillage + défilement de la vague ─────────── */

    const form = root.querySelector('form');

    if (form) {
        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented) {
                return;
            }

            const submit = form.querySelector('[data-alx-submit]');

            if (!submit || submit.dataset.loading === '1') {
                return;
            }

            // Double soumission impossible pendant l'aller-retour réseau.
            window.requestAnimationFrame(() => {
                submit.dataset.loading = '1';
                submit.setAttribute('aria-busy', 'true');
            });
        });
    }
})();
