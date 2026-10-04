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

    function initSidebar() {
        const shell  = document.querySelector('[data-shell]');
        const toggle = document.querySelector('[data-sidebar-toggle]');
        const shade  = document.querySelector('[data-sidebar-backdrop]');

        if (!shell || !toggle) {
            return;
        }

        const setOpen = (open) => {
            shell.classList.toggle('is-sidebar-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');

            if (shade) {
                shade.hidden = !open;
            }
        };

        toggle.addEventListener('click', () => {
            setOpen(!shell.classList.contains('is-sidebar-open'));
        });

        if (shade) {
            shade.addEventListener('click', () => setOpen(false));
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
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
        initBulk();
        initVariants();
        initMediaManager();
        initLoading();
        initStock();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
