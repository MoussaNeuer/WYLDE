<?php
/** @var string $title
 *  @var string|null $reference
 *  @var \App\Models\Order|null $order
 *  @var string|null $whatsappLink
 *  @var string|null $waveLink
 */
$isWhatsApp = $whatsappLink !== null;

$paymentMethod = (string) ($order?->getAttribute('payment_method') ?? '');
$paymentStatus = (string) ($order?->getAttribute('payment_status') ?? '');
$proofPath     = trim((string) ($order?->getAttribute('payment_proof_path') ?? ''));
$canUpload     = $paymentMethod === 'wave' && $paymentStatus === 'unpaid';

component('toast');
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

                <?php /* La preuve Wave complète le paiement : une capture du
                         reçu accélère la vérification côté boutique. On la
                         propose tant que la commande attend son encaissement. */ ?>
                <?php if ($canUpload || $proofPath !== ''): ?>
                    <div class="checkout-success__proof">
                        <?php if ($canUpload): ?>
                            <h2 class="checkout-success__proof-title"><?= e(__('checkout.wave_proof_title')) ?></h2>
                            <p class="checkout-success__proof-text"><?= e(__('checkout.wave_proof_text')) ?></p>

                            <?php if ($proofPath !== ''): ?>
                                <div class="checkout-success__proof-current">
                                    <img src="<?= e(upload_url($proofPath)) ?>" width="180" height="240"
                                         alt="<?= e(__('checkout.wave_proof_current')) ?>" loading="lazy">
                                    <p class="checkout-success__hint"><?= e(__('checkout.wave_proof_replace')) ?></p>
                                </div>
                            <?php endif; ?>

                            <form class="checkout-success__proof-form" method="post" enctype="multipart/form-data"
                                  action="<?= e(url('/order/success/' . rawurlencode((string) $reference) . '/proof')) ?>">
                                <?= csrf_field() ?>

                                <label class="checkout-success__proof-label" for="wave-proof">
                                    <?= e(__('checkout.wave_proof_field')) ?>
                                </label>
                                <input class="checkout-success__proof-input" id="wave-proof" type="file"
                                       name="proof" accept="image/jpeg,image/png,image/webp" required>
                                <p class="checkout-success__hint"><?= e(__('checkout.wave_proof_hint')) ?></p>

                                <button class="btn btn-primary btn-lg" type="submit">
                                    <?= e(__('checkout.wave_proof_submit')) ?>
                                </button>
                            </form>
                        <?php elseif ($proofPath !== ''): ?>
                            <h2 class="checkout-success__proof-title"><?= e(__('checkout.wave_proof_title')) ?></h2>
                            <p class="checkout-success__proof-text"><?= e(__('checkout.wave_proof_sent')) ?></p>
                            <img class="checkout-success__proof-current"
                                 src="<?= e(upload_url($proofPath)) ?>" width="180" height="240"
                                 alt="<?= e(__('checkout.wave_proof_current')) ?>" loading="lazy">
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

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
