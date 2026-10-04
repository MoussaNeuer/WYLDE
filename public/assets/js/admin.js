/* ══════════════════════════════════════════════════════════════════
   WYLDE — admin.js
   JavaScript natif, sans dépendance. Chargé en différé.
   app.js fournit déjà : Wylde.api, Wylde.csrf, Wylde.toast et la
   confirmation [data-confirm]. Ce fichier ajoute uniquement ce qui est
   propre au back-office.
   ══════════════════════════════════════════════════════════════════ */

(function () {
    'use strict';

    /* ── Drawer latéral (mobile) ──────────────────────────────── */

    const MOBILE = '(max-width: 900px)';
    const FOCUSABLE = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(', ');

    // Référence sur le shell courant : les écouteurs globaux ne sont
    //Branchés qu'une fois, ils relisent cette variable après un swap.
    let drawer = null;
    let globalBound = false;
    let shortcutsBound = false;
    let variantsBound = false;
    let loadingBound = false;

    function shell() {
        return document.querySelector('[data-shell]');
    }

    /**
     * Raccourcis clavier du back-office.
     *
     * Les cibles sont résolues dans le DOM via data-key plutôt que
     * codées en dur : les URL (base, locale) restent celles du rendu et la
     * navigation instantanée du site intercepte le clic normalement.
     */
    function bindShortcuts() {
        if (shortcutsBound) {
            return;
        }

        shortcutsBound = true;

        const TYPING = 'input:not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]), textarea, select, [contenteditable=""], [contenteditable="true"]';

        const isTyping = (node) => Boolean(node && node.closest && node.closest(TYPING));

        // Alt+1 à 5, N et ?: le lien est celui déjà rendu, donc cliquable.
        const press = (key) => {
            const link = document.querySelector('[data-key="' + key + '"]');

            if (link) {
                link.click();
                return true;
            }

            return false;
        };

        const focusSearch = () => {
            const current = shell();

            if (!current) {
                return;
            }

            if (window.matchMedia(MOBILE).matches && drawer && !drawer.isOpen()) {
                drawer.setOpen(true);
            }

            const field = current.querySelector('#adminSearch');

            if (field) {
                window.setTimeout(() => field.focus({ preventScroll: true }), 60);
            }
        };

        document.addEventListener('keydown', (event) => {
            if (event.ctrlKey || event.metaKey || isTyping(event.target)) {
                return;
            }

            const key = event.key;

            // Alt+1 à 5 : navigation entre les écrans principaux.
            if (event.altKey) {
                if (key >= '1' && key <= '5' && press(key)) {
                    event.preventDefault();
                }

                return;
            }

            if (key === '/') {
                event.preventDefault();
                focusSearch();
                return;
            }

            if (key === '?') {
                if (press('?')) {
                    event.preventDefault();
                }

                return;
            }

            if (key === 'b' || key === 'B') {
                if (!drawer) {
                    return;
                }

                event.preventDefault();
                drawer.setOpen(!drawer.isOpen());
                return;
            }

            if ((key === 'n' || key === 'N') && press('n')) {
                event.preventDefault();
            }
        });
    }

    function bindGlobalSidebar() {
        if (globalBound) {
            return;
        }

        globalBound = true;

        // Échap referme, Tab reste piégé dans le menu ouvert.
        document.addEventListener('keydown', (event) => {
            if (!drawer) {
                return;
            }

            if (event.key === 'Escape') {
                drawer.setOpen(false);
                return;
            }

            if (event.key !== 'Tab' || !drawer.isOpen()) {
                return;
            }

            const items = Array.from(drawer.sidebar.querySelectorAll(FOCUSABLE))
                .filter((node) => node.offsetParent !== null);

            if (items.length === 0) {
                return;
            }

            const first = items[0];
            const last  = items[items.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        // Les pastilles recherche / compte du header ouvrent le menu déjà
        // positionné sur la bonne section (le lien reste normal au desktop).
        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-sidebar-open]');

            if (!trigger || !window.matchMedia(MOBILE).matches) {
                return;
            }

            event.preventDefault();

            if (!drawer) {
                return;
            }

            drawer.setOpen(true);

            const target = trigger.dataset.sidebarOpen === 'search'
                ? drawer.sidebar.querySelector('#adminSearch')
                : drawer.sidebar.querySelector('[data-sidebar-account]');

            if (!target) {
                return;
            }

            target.scrollIntoView({ block: 'center', behavior: 'smooth' });

            const field = target.matches('input') ? target : target.querySelector('a, button');

            if (field) {
                window.setTimeout(() => field.focus({ preventScroll: true }), 220);
            }
        });
    }

    function initSidebar() {
        const current = shell();
        const sidebar = current ? current.querySelector('[data-sidebar]') : null;
        const toggle  = current ? current.querySelector('[data-sidebar-toggle]') : null;

        if (!current || !sidebar || !toggle) {
            return;
        }

        const shade = current.querySelector('[data-sidebar-backdrop]');
        const isOpen = () => current.classList.contains('is-sidebar-open');

        let lastFocus = null;

        const setOpen = (open) => {
            if (open === isOpen()) {
                return;
            }

            current.classList.toggle('is-sidebar-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.classList.toggle('is-nav-locked', open);

            if (shade) {
                shade.hidden = !open;
            }

            if (open) {
                lastFocus = document.activeElement;

                const closer = sidebar.querySelector('[data-sidebar-close]');

                if (closer) {
                    closer.focus({ preventScroll: true });
                }
            } else if (lastFocus && document.contains(lastFocus)) {
                lastFocus.focus({ preventScroll: true });
                lastFocus = null;
            }
        };

        drawer = { sidebar, setOpen, isOpen };

        // Un shell déjà branché (swap instantané) ne reboucle pas ses
        // écouteurs ; seul le pointeur `drawer` est rafraîchi.
        if (current.dataset.navReady === '1') {
            return;
        }

        current.dataset.navReady = '1';

        toggle.addEventListener('click', () => setOpen(!isOpen()));

        if (shade) {
            shade.addEventListener('click', () => setOpen(false));
        }

        sidebar.querySelectorAll('[data-sidebar-close]').forEach((node) => {
            node.addEventListener('click', () => setOpen(false));
        });

        // Choisir une destination referme le menu avant le changement de page.
        sidebar.addEventListener('click', (event) => {
            const link = event.target.closest('a[href]');

            if (link && !link.hasAttribute('data-no-close')) {
                setOpen(false);
            }
        });

        /* Balayage horizontal pour refermer, comme dans une application. */

        let startX = 0;
        let startY = 0;
        let tracking = false;

        const reset = () => {
            tracking = false;
            sidebar.style.transition = '';
            sidebar.style.transform = '';
        };

        sidebar.addEventListener('touchstart', (event) => {
            if (!isOpen() || event.touches.length !== 1) {
                return;
            }

            startX = event.touches[0].clientX;
            startY = event.touches[0].clientY;
            tracking = true;
        }, { passive: true });

        sidebar.addEventListener('touchmove', (event) => {
            if (!tracking || event.touches.length !== 1) {
                return;
            }

            const dx = event.touches[0].clientX - startX;
            const dy = Math.abs(event.touches[0].clientY - startY);

            // Un défilement vertical ne doit jamais fermer le menu.
            if (dy > Math.abs(dx)) {
                reset();
                return;
            }

            if (dx > -10) {
                return;
            }

            sidebar.style.transition = 'none';
            sidebar.style.transform = 'translateX(' + (dx * 0.55) + 'px)';
        }, { passive: true });

        sidebar.addEventListener('touchend', (event) => {
            if (!tracking) {
                return;
            }

            const touch = event.changedTouches ? event.changedTouches[0] : null;
            const dx = touch ? touch.clientX - startX : 0;

            reset();

            if (dx < -64) {
                setOpen(false);
            }
        }, { passive: true });

        sidebar.addEventListener('touchcancel', reset, { passive: true });

        // Retour en paysage large : le menu n'a plus lieu d'être.
        const wide = window.matchMedia('(min-width: 901px)');
        const onWide = (event) => { if (event.matches) { setOpen(false); } };

        if (wide.addEventListener) {
            wide.addEventListener('change', onWide);
        } else if (wide.addListener) {
            wide.addListener(onWide);
        }

        bindGlobalSidebar();
    }

    /* ── Tableaux denses : libellés pour la version carte ─────── */

    function initTables() {
        document.querySelectorAll('.admin-table').forEach((table) => {
            // Les grilles éditables (tailles/variantes) restent des tableaux.
            if (table.dataset.cardsReady === '1' || table.hasAttribute('data-no-cards')) {
                return;
            }

            const heads = Array.from(table.querySelectorAll('thead tr:first-child > *'))
                .map((cell) => (cell.textContent || '').trim());

            if (heads.length === 0) {
                return;
            }

            table.dataset.cardsReady = '1';
            table.classList.add('admin-table--cards');

            table.querySelectorAll('tbody tr').forEach((row) => {
                const cells = Array.from(row.children).filter((cell) => cell.tagName === 'TD');

                cells.forEach((cell, index) => {
                    if (index === 0) {
                        // La première colonne sert de titre à la carte.
                        cell.classList.add('is-head');
                        cell.dataset.label = '';
                        return;
                    }

                    if (cell.dataset.label === undefined) {
                        cell.dataset.label = heads[index] || '';
                    }
                });
            });
        });
    }

    /* ── Filtres de liste : repliés sur mobile ─────────────────── */

    function initFilters() {
        const mobile = window.matchMedia(MOBILE);
        const label  = (window.Wylde && window.Wylde.locale === 'en') ? 'Filters' : 'Filtres';

        document.querySelectorAll('form.admin-filter').forEach((form) => {
            if (form.dataset.filterReady === '1') {
                return;
            }

            form.dataset.filterReady = '1';

            const button = document.createElement('button');

            button.type = 'button';
            button.className = 'admin-filter__toggle';
            button.setAttribute('aria-expanded', 'false');
            button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
                + '<path d="m6 9 6 6 6-6"/></svg><span></span>';
            button.querySelector('span').textContent = label;

            const apply = () => {
                const collapsed = mobile.matches;

                form.classList.toggle('is-collapsed', collapsed);
                button.hidden = !collapsed;
                button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            };

            button.addEventListener('click', () => {
                const open = button.getAttribute('aria-expanded') === 'true';

                form.classList.toggle('is-collapsed', open);
                button.setAttribute('aria-expanded', open ? 'false' : 'true');
            });

            if (mobile.addEventListener) {
                mobile.addEventListener('change', apply);
            } else if (mobile.addListener) {
                mobile.addListener(apply);
            }

            if (form.parentNode) {
                form.parentNode.insertBefore(button, form);
            }

            apply();
        });
    }

    /* ── Sélection groupée ────────────────────────────────────── */

    function initBulk() {
        document.querySelectorAll('[data-bulk-form]').forEach((form) => {
            const bar   = form.querySelector('[data-bulkbar]');
            const all   = form.querySelector('[data-check-all]');
            const rows  = form.querySelectorAll('[data-check-row]');
            const count = form.querySelector('[data-bulk-count]');

            if (!bar || !all || rows.length === 0) {
                return;
            }

            const selected = () => form.querySelectorAll('[data-check-row]:checked');

            const refresh = () => {
                const n = selected().length;

                bar.hidden = n === 0;

                if (count) {
                    count.textContent = String(n);
                }

                all.checked = n === rows.length;
                all.indeterminate = n > 0 && n < rows.length;
            };

            all.addEventListener('change', () => {
                rows.forEach((row) => { row.checked = all.checked; });
                refresh();
            });

            rows.forEach((row) => {
                row.addEventListener('change', refresh);
            });

            refresh();
        });
    }

    /* ── Formulaire produit : tailles et variantes ─────────────── */

    function initVariants() {
        const toggle = document.querySelector('[data-has-sizes]');
        const panel  = document.querySelector('[data-variant-panel]');
        const single = document.querySelector('[data-single-stock]');
        const add    = document.querySelector('[data-add-variant]');
        const body   = document.querySelector('[data-variant-rows]');
        const tpl    = document.querySelector('[data-variant-template]');

        const apply = () => {
            if (!toggle || !panel) {
                return;
            }

            const on = toggle.checked;

            panel.hidden = !on;

            if (single) {
                single.hidden = on;
            }
        };

        if (toggle) {
            toggle.addEventListener('change', apply);
            apply();
        }

        if (add && body && tpl) {
            add.addEventListener('click', () => {
                body.appendChild(tpl.content.cloneNode(true));
            });
        }

        if (variantsBound) {
            return;
        }

        variantsBound = true;

        // Suppression d'une ligne. La dernière ligne est vidée plutôt que
        // retirée : une taille vide est ignorée par le validateur, et
        // l'absence de l'identifiant dans le POST fait supprimer la variante
        // en base (ProductController::syncVariants).
        document.addEventListener('click', (event) => {
            const remove = event.target.closest('[data-remove-variant]');

            if (!remove) {
                return;
            }

            const row = remove.closest('[data-variant-row]');
            const rows = document.querySelectorAll('[data-variant-row]');

            if (rows.length > 1) {
                row.remove();
                return;
            }

            row.querySelectorAll('input').forEach((input) => {
                if (input.type === 'hidden') {
                    return;
                }

                input.value = input.type === 'number' ? '0' : '';
            });

            row.querySelector('input[name="sizes[]"]').focus();
        });
    }

    /* ── Médias : dépôt, réorganisation, principale, suppression ── */

    function initMediaManager() {
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const api    = window.Wylde ? window.Wylde.api : null;
        const csrf   = window.Wylde ? window.Wylde.csrf : null;
        const toast  = (message, type) => {
            if (window.Wylde && window.Wylde.toast) {
                window.Wylde.toast(message, type);
            }
        };

        const L = (window.Wylde && window.Wylde.locale === 'en')
            ? { saved: 'Image saved.', removed: 'Image removed.', deleted: 'Image deleted.', primary: 'Primary image updated.', order: 'Order updated.' }
            : { saved: 'Image enregistrée.', removed: 'Image retirée.', deleted: 'Image supprimée.', primary: 'Image principale mise à jour.', order: 'Ordre mis à jour.' };

        document.querySelectorAll('[data-media-manager]').forEach((manager) => {
            const mode    = manager.dataset.mode === 'async' ? 'async' : 'deferred';
            const input   = manager.querySelector('input[type="file"][name="images[]"]');
            const drop    = manager.querySelector('[data-media-drop]');
            const gallery = manager.querySelector('[data-media-gallery]');
            const empty   = manager.querySelector('[data-media-empty]');

            if (!input || !drop || !gallery) {
                return;
            }

            const uploadUrl  = manager.dataset.uploadUrl || '';
            const reorderUrl = manager.dataset.reorderUrl || '';

            const star0        = manager.querySelector('[data-media-primary]');
            const del0         = manager.querySelector('[data-media-delete]');
            const primaryLabel = (star0 && star0.title) || '★';
            const deleteLabel  = (del0 && del0.title) || '×';
            const deleteAction = (del0 && del0.dataset.confirm) || '';
            const badge0       = manager.querySelector('.media-grid__primary');
            const primaryBadge = (badge0 && badge0.textContent) || '●';

            let pending = [];

            const items = () => Array.from(gallery.querySelectorAll('[data-media-item]'));

            const refreshEmpty = () => {
                if (empty) {
                    empty.hidden = items().length > 0;
                }
            };

            const syncInput = () => {
                try {
                    const dt = new DataTransfer();
                    pending.forEach((file) => dt.items.add(file));
                    input.files = dt.files;
                } catch (e) { /* ancien navigateur : envoi natif conservé */ }
            };

            const makeDraggable = (item) => {
                if (mode !== 'async' || item.dataset.preview) {
                    return;
                }

                item.setAttribute('draggable', 'true');
                item.tabIndex = 0;
            };

            const buildOverlay = (imageId, isPrimary) => {
                const overlay = document.createElement('div');
                overlay.className = 'media-tile__overlay';

                if (!isPrimary) {
                    const star = document.createElement('button');
                    star.type = 'button';
                    star.className = 'icon-btn';
                    star.dataset.mediaPrimary = String(imageId);
                    star.title = primaryLabel;
                    star.setAttribute('aria-label', primaryLabel);
                    star.textContent = '★';
                    overlay.appendChild(star);
                }

                const del = document.createElement('button');
                del.type = 'button';
                del.className = 'icon-btn icon-btn--danger';
                del.dataset.mediaDelete = String(imageId);
                del.dataset.confirm = deleteAction;
                del.title = deleteLabel;
                del.setAttribute('aria-label', deleteLabel);
                del.textContent = '×';
                overlay.appendChild(del);

                return overlay;
            };

            const applyPrimaryState = (primaryId) => {
                items().forEach((item) => {
                    const id        = item.dataset.imageId;
                    const isPrimary = String(id) === String(primaryId);

                    item.querySelectorAll('.media-grid__primary, .media-tile__overlay')
                        .forEach((node) => node.remove());

                    if (isPrimary) {
                        const badge = document.createElement('span');
                        badge.className = 'media-grid__primary';
                        badge.textContent = primaryBadge;
                        item.appendChild(badge);
                    }

                    item.appendChild(buildOverlay(id, isPrimary));
                });
            };

            const previewTile = (file) => {
                const item = document.createElement('li');
                item.dataset.mediaItem = '';
                item.dataset.preview = '1';
                item.classList.add('media-tile--enter');
                item.__file = file;

                const img = document.createElement('img');
                img.alt = '';
                img.src = URL.createObjectURL(file);

                const overlay = document.createElement('div');
                overlay.className = 'media-tile__overlay';

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'icon-btn icon-btn--danger';
                remove.dataset.mediaRemove = '';
                remove.setAttribute('aria-label', deleteLabel);
                remove.textContent = '×';
                overlay.appendChild(remove);

                item.append(img, overlay);
                gallery.appendChild(item);
            };

            const acceptDeferred = (files) => {
                Array.from(files).forEach((file) => {
                    if (!file.type.startsWith('image/')) {
                        return;
                    }

                    pending.push(file);
                    previewTile(file);
                });

                syncInput();
                refreshEmpty();
            };

            const uploadingTile = (file) => {
                const item = document.createElement('li');
                item.dataset.mediaItem = '';
                item.classList.add('media-tile--enter', 'is-uploading');

                const img = document.createElement('img');
                img.alt = '';
                img.src = URL.createObjectURL(file);
                item.appendChild(img);
                gallery.appendChild(item);
                refreshEmpty();

                return item;
            };

            const hydrateTile = (tile, image) => {
                tile.classList.remove('is-uploading');
                tile.dataset.imageId = String(image.id);
                tile.removeAttribute('data-preview');

                const img = document.createElement('img');
                img.alt = '';
                img.loading = 'lazy';
                img.src = image.url;

                tile.innerHTML = '';
                tile.appendChild(img);
                tile.appendChild(buildOverlay(image.id, !!image.is_primary));
                makeDraggable(tile);

                if (image.is_primary) {
                    const badge = document.createElement('span');
                    badge.className = 'media-grid__primary';
                    badge.textContent = primaryBadge;
                    tile.insertBefore(badge, tile.firstChild);
                    applyPrimaryState(image.id);
                }
            };

            const acceptAsync = (files) => {
                const list = Array.from(files).filter((file) => file.type.startsWith('image/'));

                if (list.length === 0) {
                    return;
                }

                const tiles = list.map(uploadingTile);
                const data  = new FormData();
                list.forEach((file) => data.append('images[]', file));

                fetch(uploadUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrf ? csrf.get() : ''
                    },
                    body: data
                })
                    .then((response) => response.json().then((payload) => ({ response, payload })))
                    .then(({ response, payload }) => {
                        if (!response.ok) {
                            throw new Error((payload && payload.error) || 'HTTP ' + response.status);
                        }

                        const images = (payload && payload.images) || [];

                        tiles.forEach((tile, index) => {
                            if (images[index]) {
                                hydrateTile(tile, images[index]);
                            } else {
                                tile.remove();
                            }
                        });

                        refreshEmpty();
                        toast(L.saved, 'success');
                    })
                    .catch((error) => {
                        tiles.forEach((tile) => tile.remove());
                        refreshEmpty();
                        toast(error.message, 'error');
                    })
                    .finally(() => {
                        input.value = '';
                        syncInput();
                    });
            };

            const accept = (files) => {
                if (!files || files.length === 0) {
                    return;
                }

                if (mode === 'async') {
                    acceptAsync(files);
                } else {
                    acceptDeferred(files);
                }
            };

            /* Dépôt ---------------------------------------------------- */

            ['dragenter', 'dragover'].forEach((type) => {
                drop.addEventListener(type, (event) => {
                    event.preventDefault();
                    drop.classList.add('is-over');
                });
            });

            ['dragleave', 'drop'].forEach((type) => {
                drop.addEventListener(type, (event) => {
                    event.preventDefault();

                    if (type === 'dragleave' && drop.contains(event.relatedTarget)) {
                        return;
                    }

                    drop.classList.remove('is-over');
                });
            });

            drop.addEventListener('drop', (event) => {
                if (event.dataTransfer && event.dataTransfer.files.length) {
                    accept(event.dataTransfer.files);
                }
            });

            input.addEventListener('change', () => accept(input.files));

            /* Actions de la galerie ------------------------------------ */

            gallery.addEventListener('click', (event) => {
                const remove = event.target.closest('[data-media-remove]');

                if (remove) {
                    const item = remove.closest('[data-media-item]');
                    pending = pending.filter((file) => file !== item.__file);
                    item.remove();
                    syncInput();
                    refreshEmpty();
                    toast(L.removed, 'info');
                    return;
                }

                if (!api) {
                    return;
                }

                const star = event.target.closest('[data-media-primary]');

                if (star) {
                    const item = star.closest('[data-media-item]');
                    star.disabled = true;

                    api('/api/admin/media/' + item.dataset.imageId + '/primary', { method: 'POST' })
                        .then(() => {
                            applyPrimaryState(item.dataset.imageId);
                            toast(L.primary, 'success');
                        })
                        .catch((error) => {
                            star.disabled = false;
                            toast(error.message, 'error');
                        });
                    return;
                }

                const del = event.target.closest('[data-media-delete]');

                if (!del) {
                    return;
                }

                if (del.dataset.confirm && !window.confirm(del.dataset.confirm)) {
                    return;
                }

                const item = del.closest('[data-media-item]');
                del.disabled = true;

                api('/api/admin/media/' + item.dataset.imageId + '/delete', { method: 'POST' })
                    .then((payload) => {
                        const finish = () => {
                            item.remove();
                            applyPrimaryState((payload && payload.primary) || 0);
                            refreshEmpty();
                        };

                        item.classList.add('is-leaving');

                        if (reduce) {
                            finish();
                        } else {
                            item.addEventListener('animationend', finish, { once: true });
                            setTimeout(finish, 400);
                        }

                        toast(L.deleted, 'success');
                    })
                    .catch((error) => {
                        del.disabled = false;
                        toast(error.message, 'error');
                    });
            });

            /* Réorganisation par glisser-déposer ----------------------- */

            let dragged = null;

            const syncOrder = () => {
                if (!api || mode !== 'async') {
                    return;
                }

                const order = items()
                    .map((item) => parseInt(item.dataset.imageId, 10))
                    .filter((id) => id > 0);

                if (order.length === 0) {
                    return;
                }

                api(reorderUrl, { method: 'POST', body: JSON.stringify({ order }) })
                    .then(() => toast(L.order, 'info'))
                    .catch((error) => toast(error.message, 'error'));
            };

            gallery.addEventListener('dragstart', (event) => {
                const item = event.target.closest('[data-media-item]');

                if (!item || item.dataset.preview) {
                    return;
                }

                dragged = item;
                item.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', item.dataset.imageId || '');
            });

            gallery.addEventListener('dragend', () => {
                if (dragged) {
                    dragged.classList.remove('is-dragging');
                }

                dragged = null;
                syncOrder();
            });

            gallery.addEventListener('dragover', (event) => {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';

                const over = event.target.closest('[data-media-item]');

                if (!over || !dragged || over === dragged) {
                    return;
                }

                const box   = over.getBoundingClientRect();
                const after = event.clientY > box.top + box.height / 2;
                gallery.insertBefore(dragged, after ? over.nextSibling : over);
            });

            items().forEach(makeDraggable);
            refreshEmpty();
        });
    }

    /* ── État de chargement des boutons de formulaire ──────────── */

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

    /* ── Ajustement de stock rapide ────────────────────────────── */

    function initStock() {
        document.querySelectorAll('[data-delta-form]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const field = form.querySelector('input[name="delta"]');
                const value = field ? parseInt(field.value, 10) : 0;

                if (!value) {
                    event.preventDefault();

                    if (window.Wylde && window.Wylde.toast) {
                        window.Wylde.toast('Saisissez un ajustement différent de 0.', 'error');
                    }
                }
            });
        });
    }

    /* ── Amorçage ─────────────────────────────────────────────── */

    function boot() {
        initSidebar();
        initTables();
        initFilters();
        initBulk();
        initVariants();
        initMediaManager();
        initLoading();
        initStock();
        bindShortcuts();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    // La navigation instantanée remplace le DOM : elle redemande un
    // amorçage plutôt que de recharger les scripts.
    window.Wylde = window.Wylde || {};

    window.Wylde.admin = Object.assign(window.Wylde.admin || {}, {
        refresh: boot
    });

    if (typeof window.Wylde.onSwap === 'function') {
        window.Wylde.onSwap(boot);
    }
})();
