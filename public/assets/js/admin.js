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

    /* ── Médias : dépôt et réorganisation ─────────────────────── */

    function initMedia() {
        const drop = document.querySelector('[data-drop]');
        const input = document.getElementById('f-images');

        if (drop && input) {
            ['dragenter', 'dragover'].forEach((type) => {
                drop.addEventListener(type, (event) => {
                    event.preventDefault();
                    drop.classList.add('is-over');
                });
            });

            ['dragleave', 'drop'].forEach((type) => {
                drop.addEventListener(type, (event) => {
                    event.preventDefault();
                    drop.classList.remove('is-over');
                });
            });

            drop.addEventListener('drop', (event) => {
                if (event.dataTransfer && event.dataTransfer.files.length > 0) {
                    input.files = event.dataTransfer.files;
                }
            });
        }

        const list = document.querySelector('[data-media-list]');
        const form = document.querySelector('[data-media-reorder]');

        if (!list || !form) {
            return;
        }

        // Glisser-déposer HTML5 : chaque item porte son id d'image, et les
        // champs cachés order[] suivent le nouvel ordre.
        let dragged = null;

        list.addEventListener('dragstart', (event) => {
            const item = event.target.closest('[data-media-item]');

            if (!item) {
                return;
            }

            dragged = item;
            item.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', item.dataset.imageId || '');
        });

        list.addEventListener('dragend', () => {
            if (dragged) {
                dragged.classList.remove('is-dragging');
            }

            dragged = null;
            syncOrder();
        });

        list.addEventListener('dragover', (event) => {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';

            const over = event.target.closest('[data-media-item]');

            if (!over || !dragged || over === dragged) {
                return;
            }

            const box = over.getBoundingClientRect();
            const after = event.clientY > box.top + box.height / 2;

            list.insertBefore(dragged, after ? over.nextSibling : over);
        });

        // Sans glisser-déposer, les flèches du clavier réordonnent aussi.
        list.addEventListener('keydown', (event) => {
            const item = event.target.closest('[data-media-item]');

            if (!item || (event.key !== 'ArrowUp' && event.key !== 'ArrowDown')) {
                return;
            }

            event.preventDefault();

            if (event.key === 'ArrowUp' && item.previousElementSibling) {
                list.insertBefore(item, item.previousElementSibling);
            } else if (event.key === 'ArrowDown' && item.nextElementSibling) {
                list.insertBefore(item.nextElementSibling, item);
            }

            item.focus();
            syncOrder();
        });

        list.querySelectorAll('[data-media-item]').forEach((item) => {
            item.setAttribute('tabindex', '0');
            item.setAttribute('draggable', 'true');
        });

        function syncOrder() {
            list.querySelectorAll('[data-media-item]').forEach((item) => {
                const field = item.querySelector('input[name="order[]"]');

                if (field) {
                    field.value = item.dataset.imageId || '';
                }
            });
        }

        syncOrder();
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
        initMedia();
        initStock();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
