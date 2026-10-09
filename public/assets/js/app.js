/* ══════════════════════════════════════════════════════════════════
   WYLDE — app.js
   JavaScript natif, sans dépendance. Chargé en différé.
   Couvre : jeton CSRF, fetch, toasts, compteur de panier,
   révélation au défilement, confirmations.
   ══════════════════════════════════════════════════════════════════ */

(function () {
    'use strict';

    const root = document.documentElement;
    let CSRF = root.dataset.csrf || '';
    const LOCALE = root.dataset.locale || 'fr';

    /* ── URL de base : indispensable en installation sous-dossier ──
       app_base_url() est rendu par le serveur sur <html data-base>.
       Sans lui, une API appelée en '/api/cart' casserait dès que le
       site est servi depuis /WYLDE/public/. Le repli sur '/' ne sert
       qu'au cas où l'attribut manquerait.                        */

    let BASE = root.dataset.base || '/';

    /** Construit une URL absolue à partir d'un chemin interne. */
    function apiUrl(path) {
        const target = new URL(path.replace(/^\/+/, ''), 'https://wylde.invalid/');

        return BASE.replace(/\/*$/, '/') + target.pathname + target.search;
    }

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

    window.Wylde = { api, csrf, locale: LOCALE, apiUrl };

    // Un échange de page peut changer de préfixe : la base suit le DOM.
    window.Wylde.syncBase = () => {
        if (root.dataset.base) {
            BASE = root.dataset.base;
        }
    };

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

    /* ── Toasts rendus côté serveur ──────────────────────────────── */

    // Fermeture déléguée : fonctionne aussi pour les toasts créés par
    // Wylde.toast() et survit aux échanges de page sans re-binding.
    let toastCloseBound = false;

    function initToastClose() {
        if (toastCloseBound) {
            return;
        }

        toastCloseBound = true;

        document.addEventListener('click', (event) => {
            const close = event.target.closest('.toast__close');

            if (close) {
                dismiss(close.closest('.toast'));
            }
        });
    }

    // Disparition automatique des toasts pré-rendus par le serveur.
    function initBakedToasts() {
        document.querySelectorAll('.toast:not([data-baked])').forEach((el) => {
            el.dataset.baked = '1';
            window.setTimeout(() => dismiss(el), 6000);
        });
    }

    /* ── Copie d'une référence en un tap (page de commande) ──────── */

    let copyButtonsBound = false;

    function initCopyButtons() {
        if (copyButtonsBound) {
            return;
        }

        copyButtonsBound = true;

        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-copy-button]');

            if (!button) {
                return;
            }

            const source = document.querySelector('[data-copy-source]');

            if (!source) {
                return;
            }

            const idleLabel = button.textContent;
            const doneLabel = button.dataset.copiedLabel || idleLabel;
            const text = source.textContent.trim();

            // Sur le réseau local (http://192.168.x.x) l'API Clipboard
            // n'existe pas : repli execCommand.
            const fallbackCopy = () => {
                const field = document.createElement('textarea');

                field.value = text;
                field.setAttribute('readonly', '');
                field.style.position = 'fixed';
                field.style.top = '-1000px';
                field.style.opacity = '0';

                document.body.appendChild(field);
                field.select();
                field.setSelectionRange(0, text.length);

                let done = false;

                try {
                    done = document.execCommand('copy');
                } catch (error) {
                    done = false;
                }

                document.body.removeChild(field);

                return done;
            };

            let copied = false;

            if (navigator.clipboard && window.isSecureContext) {
                try {
                    await navigator.clipboard.writeText(text);
                    copied = true;
                } catch (error) {
                    copied = fallbackCopy();
                }
            } else {
                copied = fallbackCopy();
            }

            button.textContent = copied ? doneLabel : idleLabel;
            button.classList.toggle('is-copied', copied);
        });
    }

    /* ── Compteur de panier ─────────────────────────────────────── */

    async function refreshCartCount() {
        const badge = document.querySelector('[data-cart-count]');

        if (!badge) {
            return;
        }

        try {
            const data = await api(apiUrl('/api/cart'));
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

    let confirmationsBound = false;

    function initConfirmations() {
        if (confirmationsBound) {
            return;
        }

        confirmationsBound = true;

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

    let loadingBound = false;

    function initLoading() {
        if (loadingBound) {
            return;
        }

        loadingBound = true;

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

    let headerBound = false;

    function initHeader() {
        const header = document.querySelector('[data-header]');

        if (!header) {
            return;
        }

        const onScroll = () => {
            const current = document.querySelector('[data-header]');

            if (current) {
                current.classList.toggle('is-stuck', window.scrollY > 8);
            }
        };

        onScroll();

        if (headerBound) {
            return;
        }

        headerBound = true;

        window.addEventListener('scroll', onScroll, { passive: true });
    }

    /* ── Menu mobile : hamburger animé + overlay plein écran ────── */

    let mobileNavBound = false;

    function initMobileNav() {
        const panel = document.querySelector('#mobileNav');
        const burger = document.querySelector('.site-header__burger');

        // L'offcanvas est recréé à chaque échange de page : on marque l'état
        // sur <html>, qui survit au remplacement du <body>.
        if (panel && panel.classList.contains('show')) {
            root.classList.add('is-nav-open');
        }

        if (mobileNavBound) {
            return;
        }

        mobileNavBound = true;

        // Événements Bootstrap : ils remontent jusqu'au document, donc ils
        // survivent au remplacement de l'offcanvas.
        document.addEventListener('show.bs.offcanvas', (event) => {
            if (event.target && event.target.id === 'mobileNav') {
                root.classList.add('is-nav-open');
                bodyLock(true);
            }
        });

        document.addEventListener('hidden.bs.offcanvas', (event) => {
            if (event.target && event.target.id === 'mobileNav') {
                root.classList.remove('is-nav-open');
                bodyLock(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && root.classList.contains('is-nav-open')) {
                const instance = window.bootstrap
                    && window.bootstrap.Offcanvas
                    && window.bootstrap.Offcanvas.getInstance(document.querySelector('#mobileNav'));

                if (instance) {
                    instance.hide();
                }
            }
        });

        function bodyLock(locked) {
            if (locked) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }

        // Le focus revient sur le bouton qui a ouvert le panneau.
        document.addEventListener('hidden.bs.offcanvas', (event) => {
            if (event.target && event.target.id === 'mobileNav' && burger) {
                burger.focus({ preventScroll: true });
            }
        });
    }

    /* ══════════════════════════════════════════════════════════════
       NAVIGATION INSTANTANÉE
       Les liens internes sont récupérés en arrière-plan (au survol, au
       focus ou au toucher) puis la page est remplacée sans rechargement.
       C'est un simple confort : sans JavaScript, ou en cas d'échec du
       réseau, le navigateur reprend la main avec location.assign.
       ══════════════════════════════════════════════════════════════ */

    const NAV_MAX_CACHE = 24;
    const NAV_TIMEOUT = 9000;

    // Les scripts déjà chargés ne sont pas rejoués lors d'un échange.
    const loaded = new Set(
        Array.from(document.querySelectorAll('script[src]')).map((s) => s.src)
    );

    const cache = new Map();
    const hooks = [];
    let navReady = false;
    let progress = null;

    function progressBar() {
        if (progress) {
            return progress;
        }

        progress = document.createElement('div');
        progress.className = 'nav-progress';
        progress.setAttribute('aria-hidden', 'true');
        progress.innerHTML = '<span></span>';

        // Rattachée à <html> et non à <body> : un échange de page remplace
        // le contenu du body, la barre doit survivre.
        document.documentElement.appendChild(progress);

        return progress;
    }

    function progressOn() {
        progressBar().classList.add('is-active');
    }

    function progressOff() {
        progressBar().classList.remove('is-active');
    }

    /**
     * Un lien est-il eligible à la navigation instantanée ?
     */
    function navUrl(link) {
        if (!link || link.hasAttribute('download') || link.hasAttribute('target')) {
            return null;
        }

        if (link.dataset.noFastNav !== undefined) {
            return null;
        }

        // Une confirmation doit passer par un vrai clic (fenêtre système).
        if (link.dataset.confirmLink !== undefined) {
            return null;
        }

        const raw = link.getAttribute('href');

        // Les ancres restent au navigateur : elles ne changent pas de page.
        if (!raw || raw === '#' || raw.startsWith('#')) {
            return null;
        }

        let url;

        try {
            url = new URL(link.href, window.location.href);
        } catch (e) {
            return null;
        }

        if (url.origin !== window.location.origin) {
            return null;
        }

        // Le changement de langue et la déconnexion modifient des cookies :
        // on laisse le navigateur faire son travail.
        if (url.pathname.startsWith('/locale/') || url.pathname === '/logout') {
            return null;
        }

        return url;
    }

    async function fetchPage(url) {
        const cached = cache.get(url.href);

        if (cached) {
            // Rafraîchit la position LRU.
            cache.delete(url.href);
            cache.set(url.href, cached);

            return cached;
        }

        const controller = typeof AbortController === 'function' ? new AbortController() : null;
        const timer = window.setTimeout(() => controller && controller.abort(), NAV_TIMEOUT);

        try {
            const response = await fetch(url.href, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                // On court-circuite le cache navigateur : ces pages sont
                // liées à la session (panier, commande…) et le cache LRU
                // ci-dessus reste la seule source de mise en cache. Sans
                // cela, une réponse « private, max-age » erronée ou un
                // retour arrière servait un HTML périmé.
                cache: 'no-store',
                signal: controller ? controller.signal : undefined
            });

            if (!response.ok) {
                return null;
            }

            // Une redirection vers la connexion doit être suivie normalement.
            if (response.redirected && response.url !== url.href) {
                return null;
            }

            const html = await response.text();

            if (html.indexOf('<html') === -1 && html.indexOf('<body') === -1) {
                return null;
            }

            const doc = new DOMParser().parseFromString(html, 'text/html');

            cache.set(url.href, doc);

            if (cache.size > NAV_MAX_CACHE) {
                cache.delete(cache.keys().next().value);
            }

            return doc;
        } catch (e) {
            return null;
        } finally {
            window.clearTimeout(timer);
        }
    }

    /** Ajoute les feuilles de style absentes (passage boutique → admin). */
    function syncStyles(doc) {
        doc.querySelectorAll('link[rel="stylesheet"][href]').forEach((link) => {
            if (document.querySelector('link[rel="stylesheet"][href="' + link.href + '"]')) {
                return;
            }

            const fresh = document.createElement('link');
            fresh.rel = 'stylesheet';
            fresh.href = link.href;

            if (link.integrity) {
                fresh.integrity = link.integrity;
                fresh.crossOrigin = link.crossOrigin || 'anonymous';
            }

            document.head.appendChild(fresh);
        });
    }

    /** Rejoue les scripts : les externes inconnus et les blocs inline. */
    function runScripts(doc) {
        doc.querySelectorAll('script[src]').forEach((old) => {
            if (loaded.has(old.src)) {
                return;
            }

            loaded.add(old.src);

            const fresh = document.createElement('script');
            fresh.src = old.src;

            if (old.integrity) {
                fresh.integrity = old.integrity;
                fresh.crossOrigin = old.crossOrigin || 'anonymous';
            }

            document.body.appendChild(fresh);
        });

        // Les blocs inline (notifications flash) sont recréés à la main :
        // un nœud importé est inerte.
        doc.querySelectorAll('script:not([src])').forEach((old) => {
            const fresh = document.createElement('script');

            if (old.type) {
                fresh.type = old.type;
            }

            fresh.textContent = old.textContent;

            document.body.appendChild(fresh);
        });
    }

    function swap(doc, url) {
        document.title = doc.title;

        const description = doc.querySelector('meta[name="description"]');

        if (description) {
            let meta = document.querySelector('meta[name="description"]');

            if (!meta) {
                meta = document.createElement('meta');
                meta.setAttribute('name', 'description');
                document.head.appendChild(meta);
            }

            meta.setAttribute('content', description.getAttribute('content') || '');
        }

        // Jeton CSRF et langue suivent la nouvelle page.
        if (doc.documentElement.dataset.csrf) {
            CSRF = doc.documentElement.dataset.csrf;
            root.dataset.csrf = CSRF;
        }

        if (doc.documentElement.dataset.locale) {
            root.dataset.locale = doc.documentElement.dataset.locale;
        }

        // Préfixe d'installation : la base est reconstruite sur chaque échange.
        if (doc.documentElement.dataset.base) {
            root.dataset.base = doc.documentElement.dataset.base;
            window.Wylde.syncBase();
        }

        if (doc.documentElement.dataset.scope !== undefined) {
            root.dataset.scope = doc.documentElement.dataset.scope;
        }

        syncStyles(doc);

        // Les scripts sont rejoués séparément (importNode ne les exécute pas).
        const nodes = Array.from(doc.body.childNodes)
            .filter((node) => node.nodeName !== 'SCRIPT')
            .map((node) => document.importNode(node, true));

        document.body.replaceChildren(...nodes);

        runScripts(doc);

        if (url) {
            history.pushState({ scroll: 0 }, '', url.href);
        }

        window.scrollTo(0, 0);

        hooks.forEach((hook) => {
            try {
                hook(doc);
            } catch (e) { /* un module ne doit pas casser la navigation */ }
        });

        refresh();

        const main = document.querySelector('main, [role="main"]');

        if (main) {
            main.setAttribute('tabindex', '-1');
            main.focus({ preventScroll: true });
        }
    }

    let busy = false;

    async function navigate(url) {
        if (busy) {
            return;
        }

        busy = true;
        progressOn();

        const doc = await fetchPage(url);

        progressOff();
        busy = false;

        if (!doc) {
            window.location.assign(url.href);
            return;
        }

        history.replaceState({ scroll: window.scrollY }, '', window.location.href);

        swap(doc, url);
    }

    function initFastNav() {
        if (navReady) {
            return;
        }

        navReady = true;

        if (!('fetch' in window) || !('DOMParser' in window)) {
            return;
        }

        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }

        let timer = null;

        const warm = (link) => {
            const url = navUrl(link);

            if (!url || url.href === window.location.href) {
                return;
            }

            if (cache.has(url.href)) {
                return;
            }

            if (timer) {
                window.clearTimeout(timer);
            }

            timer = window.setTimeout(() => { fetchPage(url); }, 90);
        };

        document.addEventListener('pointerover', (event) => {
            if (event.pointerType === 'touch') {
                return;
            }

            warm(event.target.closest('a[href]'));
        }, { passive: true });

        document.addEventListener('focusin', (event) => {
            warm(event.target.closest('a[href]'));
        });

        document.addEventListener('touchstart', (event) => {
            const link = event.target.closest('a[href]');
            const url = link ? navUrl(link) : null;

            if (url && !cache.has(url.href)) {
                fetchPage(url);
            }
        }, { passive: true });

        document.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0) {
                return;
            }

            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            const link = event.target.closest('a[href]');
            const url = navUrl(link);

            if (!url || url.href === window.location.href) {
                return;
            }

            event.preventDefault();

            navigate(url);
        });

        window.addEventListener('popstate', (event) => {
            const url = new URL(window.location.href);

            fetchPage(url).then((doc) => {
                if (!doc) {
                    window.location.reload();
                    return;
                }

                progressOn();
                swap(doc, null);
                progressOff();

                window.scrollTo(0, (event.state && event.state.scroll) || 0);
            });
        });

        progressBar();
    }

    window.Wylde.nav = {
        go: (href) => {
            const url = new URL(href, window.location.href);

            navigate(url);
        },
        preload: (href) => fetchPage(new URL(href, window.location.href)),
        clear: () => cache.clear()
    };

    window.Wylde.onSwap = (hook) => hooks.push(hook);

    /* ── Amorçage ───────────────────────────────────────────────── */

    function refresh() {
        initHeader();
        initMobileNav();
        initReveal();
        initCountUp();
        initBakedToasts();

        // Bouton WhatsApp : la redirection est déjà déclenchée par le
        // contrôleur, on prépare simplement le repli visible.
        document.querySelectorAll('[data-wa-fallback]:not(.is-ready)').forEach((el) => {
            el.classList.add('is-ready');
        });
    }

    function boot() {
        // Signale à la feuille de style que le script tourne : les effets
        // de révélation ne masquent le contenu que dans ce cas.
        root.classList.add('js');

        refresh();
        initConfirmations();
        initLoading();
        initLocale();
        initToastClose();
        initCopyButtons();
        refreshCartCount();
        initFastNav();
    }

    window.Wylde.refresh = refresh;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
