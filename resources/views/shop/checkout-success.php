<?php
/** @var string $title
 *  @var string|null $reference
 *  @var \App\Models\Order|null $order
 *  @var string|null $whatsappLink
 *  @var string|null $waveLink
 */
$isWhatsApp = $whatsappLink !== null;
?>
<section class="section">
    <div class="container">
        <div class="empty-state checkout-success<?= $isWhatsApp ? ' checkout-success--wa' : '' ?>">

            <div class="checkout-success__badge" aria-hidden="true">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 12.5 9 17.5 20 6.5"/>
                </svg>
            </div>

            <h1 class="checkout-success__title"><?= e(__('checkout.success_title')) ?></h1>

            <p class="checkout-success__text">
                <?= e($isWhatsApp ? __('checkout.whatsapp_pending') : __('checkout.success_text')) ?>
            </p>

            <?php if ($isWhatsApp): ?>
                <p class="checkout-success__text checkout-success__text--muted">
                    <?= e(__('checkout.whatsapp_summary')) ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($reference)): ?>
                <?php /* La référence est le seul moyen de retrouver la commande
                         quand le client écrit au support. La rendre copiable
                         en un tap lui évite de la recopier à la main. */ ?>
                <div class="checkout-success__reference-box">
                    <span class="checkout-success__reference-label"><?= e(__('checkout.reference')) ?></span>
                    <span class="checkout-success__reference-value" data-copy-source><?= e((string) $reference) ?></span>
                    <button class="checkout-success__copy" type="button"
                            data-copy-button
                            data-copied-label="<?= e(__('checkout.copied')) ?>">
                        <?= e(__('checkout.copy_reference')) ?>
                    </button>
                </div>
            <?php endif; ?>

            <div class="checkout-success__actions">
                <?php /* Le lien Wave reste sur cette page tant que la
                         commande n'est pas encaissée : c'est le seul
                         endroit où le client peut encore payer. */ ?>
                <?php if ($waveLink !== null): ?>
                    <a class="btn btn-primary btn-lg checkout-success__wave"
                       href="<?= e($waveLink) ?>"
                       target="_blank" rel="noopener">
                        <?= e(__('checkout.wave_cta')) ?>
                    </a>

                    <p class="checkout-success__hint">
                        <?= e(__('checkout.wave_hint')) ?>
                    </p>
                <?php endif; ?>

                <?php if ($isWhatsApp): ?>
                    <a class="btn btn-whatsapp"
                       href="<?= e((string) $whatsappLink) ?>"
                       rel="noopener"
                       data-wa-fallback>
                        <?= e(__('checkout.whatsapp_cta')) ?>
                    </a>

                    <p class="checkout-success__hint">
                        <?= e(__('checkout.whatsapp_fallback')) ?>
                    </p>
                <?php endif; ?>

                <a class="btn btn-light" href="<?= e(url('/')) ?>"><?= e(__('nav.home')) ?></a>
                <a class="btn btn-outline-light" href="<?= e(url('/shop')) ?>"><?= e(__('nav.shop')) ?></a>
            </div>
        </div>
    </div>
</section>

<?php if ($isWhatsApp): ?>
    <?php App\Core\View::start('scripts'); ?>
    <script>
        /* Redirection automatique vers WhatsApp : elle est déjà effectuée par
           le contrôleur, cette page n'est atteinte que si le client revient
           en arrière. On ne ré-trigger rien, on laisse le bouton visible. */
        (function () {
            var link = document.querySelector('[data-wa-fallback]');

            if (link) {
                link.classList.add('is-ready');
            }
        }());
    </script>
    <?php App\Core\View::stop(); ?>
<?php endif; ?>

<?php if (!empty($reference)): ?>
    <?php App\Core\View::start('scripts'); ?>
    <script>
        /* Copie de la référence en un tap.
           Sur le réseau local (http://192.168.x.x) l'API Clipboard n'existe
           pas : elle exige un contexte sécurisé. On garde donc l'ancien
           execCommand en repli, sinon le bouton ne fonctionnerait que sur
           localhost et pas sur le téléphone du client. */
        (function () {
            var button = document.querySelector('[data-copy-button]');
            var source = document.querySelector('[data-copy-source]');

            if (!button || !source) {
                return;
            }

            var idleLabel = button.textContent;
            var doneLabel = button.dataset.copiedLabel || idleLabel;

            var fallbackCopy = function (text) {
                var field = document.createElement('textarea');

                field.value = text;
                field.setAttribute('readonly', '');
                field.style.position = 'fixed';
                field.style.top = '-1000px';
                field.style.opacity = '0';

                document.body.appendChild(field);
                field.select();
                field.setSelectionRange(0, text.length);

                var done = false;

                try {
                    done = document.execCommand('copy');
                } catch (error) {
                    done = false;
                }

                document.body.removeChild(field);

                return done;
            };

            button.addEventListener('click', function () {
                var text = source.textContent.trim();
                var copied = false;

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(function () {
                        copied = true;
                    }, function () {
                        copied = fallbackCopy(text);
                    }).then(function () {
                        button.textContent = copied ? doneLabel : idleLabel;
                        button.classList.toggle('is-copied', copied);
                    });
                } else {
                    copied = fallbackCopy(text);
                    button.textContent = copied ? doneLabel : idleLabel;
                    button.classList.toggle('is-copied', copied);
                }
            });
        }());
    </script>
    <?php App\Core\View::stop(); ?>
<?php endif; ?>
