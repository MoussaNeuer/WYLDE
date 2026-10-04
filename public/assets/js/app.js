/* ══════════════════════════════════════════════════════════════════
   WYLDE — app.js
   JavaScript natif, sans dépendance. Chargé en différé.
   Couvre : jeton CSRF, fetch, toasts, compteur de panier,
   révélation au défilement, confirmations.
   ══════════════════════════════════════════════════════════════════ */

(function () {
    'use strict';

    const root = document.documentElement;
    const CSRF = root.dataset.csrf || '';
    const LOCALE = root.dataset.locale || 'fr';

    /* ── Jeton CSRF : lu et mis à jour après chaque réponse ─────── */

    const csrf = {
        get: () => CSRF,
        refresh: (token) => {
            if (token) {
                root.dataset.csrf = token;
            }
        }
    };

    /* ── fetch avec CSRF et gestion des erreurs ─────────────────── */

    async function api(url, options = {}) {
        const config = Object.assign({ credentials: 'same-origin' }, options);

        config.headers = Object.assign(
            { Accept: 'application/json' },
            options.body ? { 'Content-Type': 'application/json' } : {},
            { 'X-Requested-With': 'XMLHttpRequest' },
            config.headers || {}
        );

        const method = (config.method || 'GET').toUpperCase();

        if (method !== 'GET' && method !== 'HEAD') {
            config.headers['X-CSRF-Token'] = csrf.get();
        }

        const response = await fetch(url, config);

        // Le serveur peut renouveler le jeton à chaque réponse.
        const fresh = response.headers.get('X-CSRF-Token');
        if (fresh) {
            csrf.refresh(fresh);
        }

        let payload = null;

        try {
            payload = await response.json();
        } catch (e) {
            payload = null;
        }

        if (!response.ok) {
            const error = new Error(
                (payload && payload.error) || 'HTTP ' + response.status
            );
            error.status = response.status;
            error.payload = payload;
            throw error;
        }

        return payload || {};
    }

    window.Wylde = { api, csrf, locale: LOCALE };

    /* ── Toasts ─────────────────────────────────────────────────── */

    const ICONS = {
        success: '✓',
        error: '×',
        warning: '!',
        info: 'i'
    };

    function toast(message, type = 'info', timeout = 4500) {
        const stack = document.getElementById('toast-stack');

        if (!stack) {
            return;
        }

        const el = document.createElement('div');
        el.className = 'toast toast--' + type;
        el.setAttribute('role', type === 'error' ? 'alert' : 'status');

        const icon = document.createElement('span');
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = ICONS[type] || ICONS.info;

        const text = document.createElement('span');
        text.textContent = message;

        const close = document.createElement('button');
        close.className = 'toast__close';
        close.type = 'button';
        close.setAttribute('aria-label', 'Fermer');
        close.textContent = '×';

        close.addEventListener('click', () => dismiss(el));

        el.append(icon, text, close);
        stack.appendChild(el);

        if (timeout > 0) {
            setTimeout(() => dismiss(el), timeout);
        }
    }

    function dismiss(el) {
        el.classList.add('is-leaving');

        el.addEventListener('animationend', () => el.remove(), { once: true });

        // Filet de sécurité si l'animation ne se déclenche pas.
        setTimeout(() => el.remove(), 400);
    }

    window.Wylde.toast = toast;

    /* ── Compteur de panier ─────────────────────────────────────── */

    async function refreshCartCount() {
        const badge = document.querySelector('[data-cart-count]');

        if (!badge) {
            return;
        }

        try {
            const data = await api(window.location.origin + '/api/cart');
            const count = (data && data.count) || 0;

            badge.dataset.count = String(count);
            badge.textContent = String(count);
        } catch (e) {
            // Panier indisponible : on laisse l'état initial.
        }
    }

    window.Wylde.refreshCartCount = refreshCartCount;

    /* ── Révélation progressive des sections (§9.2) ─────────────── */

    function initReveal() {
        const items = document.querySelectorAll('.reveal:not(.is-visible)');

        if (items.length === 0) {
            return;
        }

        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (reduce || !('IntersectionObserver' in window)) {
            items.forEach((el) => el.classList.add('is-visible'));
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            },
            { rootMargin: '0px 0px -10% 0px', threshold: 0.05 }
        );

        items.forEach((el) => observer.observe(el));
    }

    /* ── Confirmation avant action destructive (§16) ────────────── */

    function initConfirmations() {
        document.addEventListener('submit', (event) => {
            const form = event.target;

            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            const message = (event.submitter && event.submitter.dataset.confirm) || form.dataset.confirm;

            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-confirm-link]');

            if (trigger && !window.confirm(trigger.dataset.confirmLink)) {
                event.preventDefault();
            }
        });
    }

    /* ── État de chargement des boutons de formulaire ───────────── */

    function initLoading() {
        document.addEventListener('submit', (event) => {
            if (event.defaultPrevented) {
                return;
            }

            const form = event.target;

            if (!(form instanceof HTMLFormElement) || form.dataset.noLoading !== undefined) {
                return;
            }

            const submit = event.submitter || form.querySelector('button[type="submit"]');

            if (!submit || submit.disabled || submit.dataset.loading === '1') {
                return;
            }

            window.requestAnimationFrame(() => {
                submit.dataset.loading = '1';
                submit.disabled = true;
            });
        });
    }

    /* ── Compteur animé des indicateurs (stat-card) ──────────────── */

    /**
     * Anime les nombres des cartes d'indicateur en conservant la mise en
     * forme d'origine (séparateurs de milliers, suffixe de devise).
     */
    function initCountUp() {
        const nodes = document.querySelectorAll('[data-count-up]');

        if (nodes.length === 0) {
            return;
        }

        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (reduce || !('IntersectionObserver' in window)) {
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                observer.unobserve(entry.target);
                countUp(entry.target);
            });
        }, { threshold: 0.2 });

        nodes.forEach((node) => observer.observe(node));

        function countUp(el) {
            const text = (el.textContent || '').trim();

            // On ne s'intéresse qu'à un entier, avec ou sans séparateurs.
            const match = text.match(/\d[\d    ]*\d|\d/);

            if (!match) {
                return;
            }

            const raw = match[0];

            if (!/^\d[\d\s]*\d?$/.test(raw)) {
                return; // séparateur inattendu : valeur laissée intacte
            }

            // « 3,50 » ou « 1,234.56 » : la virgule suit le groupe, donc
            // le nombre n'est pas un entier et ne doit pas être animé.
            if (/^[.,]\d/.test(text.slice(match.index + raw.length))) {
                return;
            }

            const target = parseInt(raw.replace(/[\s   ]/g, ''), 10);

            if (!Number.isFinite(target) || target === 0) {
                return;
            }

            const width = raw.replace(/[^0-9]/g, '').length;
            const render = (value) => {
                let digits = String(value).padStart(width, '0');
                let index = 0;
                let out = '';

                for (const char of raw) {
                    out += char >= '0' && char <= '9' ? digits[index++] : char;
                }

                return text.slice(0, match.index) + out + text.slice(match.index + raw.length);
            };

            const duration = 750;
            const start = performance.now();

            const step = (now) => {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);

                el.textContent = render(Math.round(target * eased));

                if (progress < 1) {
                    requestAnimationFrame(step);
                }
            };

            requestAnimationFrame(step);
        }
    }

    /* ── Bascule de langue : persistance immédiate ──────────────── */

    function initLocale() {
        document.querySelectorAll('a[href*="/locale/"]').forEach((link) => {
            link.addEventListener('click', () => {
                // Le cookie est posé par le serveur ; on évite le cache
                // en navigation arrière.
                if ('requestAnimationFrame' in window) {
                    window.requestAnimationFrame(() => { /* no-op */ });
                }
            });
        });
    }

    /* ── Barre d'en-tête : ombre au défilement ──────────────────── */

    function initHeader() {
        const header = document.querySelector('[data-header]');

        if (!header) {
            return;
        }

        const onScroll = () => {
            header.classList.toggle('is-stuck', window.scrollY > 8);
        };

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    /* ── Amorçage ───────────────────────────────────────────────── */

    function boot() {
        initHeader();
        initReveal();
        initCountUp();
        initConfirmations();
        initLoading();
        initLocale();
        refreshCartCount();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
