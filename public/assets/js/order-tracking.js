/* ═══════════════════════════════════════════════════════════════════
 * Suivi de commande en direct.
 *
 * L'état est rendu par le serveur puis rafraîchi par polling sur le
 * même point (Accept: application/json). Aucun rechargement de page :
 * dès que l'équipe met à jour la commande dans le back-office, le
 * client voit la progression sans rien faire.
 * ═══════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var root = document.querySelector('[data-tracking-page]');
    if (!root) {
        return;
    }

    var POLL_MS = 4000;
    var baseUrl = root.getAttribute('data-poll-url') || window.location.href;

    var statusPill = document.querySelector('[data-track-status]');
    var stepper = document.querySelector('[data-stepper]');
    var paymentPill = document.querySelector('[data-payment-status]');
    var trackNumber = document.querySelector('[data-track-number]');
    var cancelBanner = document.querySelector('[data-cancel-banner]');
    var deliveredBanner = document.querySelector('[data-delivered-banner]');
    var waveHint = document.querySelector('[data-wave-hint]');

    var running = true;
    var inFlight = false;
    var lastStatus = statusPill ? statusPill.textContent.trim() : '';

    /* ---------------------------------------------------------------
     * Application d'un payload à la page.
     * ------------------------------------------------------------- */

    function setStepStates(payload) {
        if (!stepper) {
            return;
        }

        var steps = payload.steps || [];
        var current = payload.cancelled ? null : payload.phase;

        Array.prototype.forEach.call(stepper.children, function (li, index) {
            var state = steps[index] ? steps[index].state : 'todo';
            var isActive = state === 'active';
            var isDone = state === 'done';

            li.classList.toggle('is-active', isActive);
            li.classList.toggle('is-done', isDone);
            li.classList.toggle('is-todo', state === 'todo');

            if (current && index + 1 === current) {
                li.setAttribute('aria-current', 'step');
            } else {
                li.removeAttribute('aria-current');
            }
        });
    }

    function applyPayload(payload) {
        if (!payload || payload.ok === false) {
            return;
        }

        var statusChanged = !!statusPill && payload.statusLabel &&
            statusPill.textContent.trim() !== payload.statusLabel;

        if (statusChanged) {
            statusPill.textContent = payload.statusLabel;

            var tones = statusPill.className.match(/tracking-pill--[a-z-]+/g) || [];
            tones.forEach(function (tone) {
                statusPill.classList.remove(tone);
            });
            statusPill.classList.add('tracking-pill--' + (payload.status || ''));

            if (stepper) {
                stepper.classList.add('tracker--transition');
            }
        }

        setStepStates(payload);

        if (payload.cancelled) {
            if (deliveredBanner) {
                deliveredBanner.hidden = true;
            }
            if (cancelBanner) {
                cancelBanner.hidden = false;
                var note = cancelBanner.querySelector('[data-cancel-note]');
                if (note && payload.cancelNote) {
                    note.textContent = payload.cancelNote;
                }
            }
        } else if (payload.delivered) {
            if (cancelBanner) {
                cancelBanner.hidden = true;
            }
            if (deliveredBanner) {
                deliveredBanner.hidden = false;
            }
        }

        if (paymentPill && payload.payment) {
            if (paymentPill.textContent.trim() !== payload.payment.statusLabel) {
                paymentPill.textContent = payload.payment.statusLabel;

                var ptones = paymentPill.className.match(/tracking-pill--[a-z-]+/g) || [];
                ptones.forEach(function (tone) {
                    paymentPill.classList.remove(tone);
                });
                paymentPill.classList.add('tracking-pill--payment-' + (payload.payment.status || ''));
            }

            var unsettled = payload.payment.method === 'wave' &&
                payload.payment.status === 'unpaid';
            if (waveHint) {
                waveHint.hidden = !unsettled;
            }
        }

        if (trackNumber && payload.tracking && payload.tracking.number) {
            trackNumber.textContent = payload.tracking.number;
        }

        if (statusChanged && stepper) {
            window.setTimeout(function () {
                stepper.classList.remove('tracker--transition');
            }, 700);
        }
    }

    /* ---------------------------------------------------------------
     * Polling.
     * ------------------------------------------------------------- */

    function poll() {
        if (!running || inFlight) {
            return;
        }
        inFlight = true;

        fetch(baseUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.documentElement.getAttribute('data-csrf') || ''
            }
        })
            .then(function (res) {
                return res.status === 200 ? res.json() : null;
            })
            .then(function (payload) {
                if (payload && payload.ok !== false) {
                    applyPayload(payload);
                    if (payload.ended) {
                        running = false;
                    }
                }
            })
            .catch(function () {
                /* Réseau indisponible : le prochain tick réessaye. */
            })
            .then(function () {
                inFlight = false;
            });
    }

    function start() {
        running = true;
        window.setInterval(poll, POLL_MS);
        window.setTimeout(poll, 1200);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            return;
        }
        if (!running) {
            return;
        }
        poll();
    });

    /* ---------------------------------------------------------------
     * Copie de la référence.
     * ------------------------------------------------------------- */

    var copyBtn = document.querySelector('[data-tracking-copy]');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var ref = document.querySelector('[data-tracking-reference]');
            var text = ref ? ref.textContent.trim() : '';
            if (!text) {
                return;
            }

            var copied = function () {
                copyBtn.textContent = copyBtn.getAttribute('data-copied-label') ||
                    'Copié';
                copyBtn.classList.add('is-copied');
                window.setTimeout(function () {
                    copyBtn.classList.remove('is-copied');
                }, 1800);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(copied, function () {
                    legacyCopy(text, copied);
                });
            } else {
                legacyCopy(text, copied);
            }
        });
    }

    function legacyCopy(text, onDone) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
        } catch (_err) {
            /* clipboard inaccessible */
        }
        document.body.removeChild(ta);
        if (typeof onDone === 'function') {
            onDone();
        }
    }

    start();
})();