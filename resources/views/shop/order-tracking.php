<?php
/**
 * Suivi de commande : récapitulatif public + progression en direct.
 *
 * Le statut se met à jour sans rechargement : l'état de départ est
 * injecté ici, puis order-tracking.js interroge le même point en JSON.
 *
 * @var string $title
 * @var \App\Models\Order $order
 * @var array<int, \App\Models\OrderItem> $items
 * @var array<string, mixed> $payload
 */

component('toast');

$steps           = $payload['steps'];
$cancelled       = $payload['cancelled'];
$delivered       = $payload['delivered'];
$proofPath       = $payload['payment']['proof'];
$trackingNumber  = $payload['tracking']['number'];
$isWave          = ($payload['payment']['method'] ?? '') === 'wave';
$waveUnpaid      = $isWave && ($payload['payment']['status'] ?? '') === 'unpaid';
$cancelNote      = trim((string) ($order->getAttribute('cancelled_reason') ?? ''));
$zone            = $order->zone();
$shippingFree    = (int) $order->shipping_cost === 0;
$trackingUrl     = url('/order/tracking/' . rawurlencode($payload['reference']));
?>
<header class="tracking-top">
    <a class="tracking-top__brand" href="<?= e(url('/')) ?>">WYLDE</a>
    <a class="tracking-top__shop" href="<?= e(url('/shop')) ?>"><?= e(__('nav.shop')) ?></a>
</header>

<div class="tracking-shell" data-tracking-page data-poll-url="<?= e($trackingUrl) ?>">

    <div class="tracking-intro">
        <p class="tracking-eyebrow">
            <?= e(__('tracking.eyebrow')) ?>
            <span class="tracking-live" data-live>
                <span class="tracking-live__dot" aria-hidden="true"></span>
                <?= e(__('tracking.live')) ?>
            </span>
        </p>

        <h1 class="tracking-ref">
            #<span data-tracking-reference><?= e($payload['reference']) ?></span>
        </h1>

        <p class="tracking-meta" data-tracking-placed>
            <?= e(__('tracking.placed_on')) ?>
            <time datetime="<?= e(format_date((string) $order->created_at, 'Y-m-d')) ?>"><?= e($payload['placedAt']) ?></time>
        </p>

        <button class="tracking-copy" type="button"
                data-tracking-copy
                data-copied-label="<?= e(__('tracking.copied')) ?>">
            <?= e(__('tracking.copy')) ?>
        </button>
    </div>

    <section class="tracking-card">
        <div class="tracking-status__row">
            <span class="tracking-status__label"><?= e(__('tracking.status')) ?></span>
            <span class="tracking-pill tracking-pill--<?= e($payload['status']) ?>"
                  data-track-status role="status" aria-live="polite">
                <?= e($payload['statusLabel']) ?>
            </span>
        </div>

        <ol class="tracker" data-stepper aria-label="<?= e(__('tracking.status')) ?>">
            <?php foreach ($steps as $index => $step): ?>
                <li class="tracker__step is-<?= e($step['state']) ?>"
                    data-step="<?= e($step['key']) ?>" style="--i: <?= e((string) $index) ?>">
                    <span class="tracker__node" aria-hidden="true">
                        <span class="tracker__num"><?= e((string) ($index + 1)) ?></span>
                        <svg class="tracker__check" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2.4"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 13l4 4L19 7"/>
                        </svg>
                    </span>
                    <span class="tracker__label"><?= e($step['label']) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <div class="tracking-alert tracking-alert--danger" data-cancel-banner <?= $cancelled ? '' : 'hidden' ?>>
        <strong><?= e(__('tracking.cancelled_title')) ?></strong>
        <p data-cancel-note><?= e($cancelNote) ?></p>
    </div>

    <div class="tracking-alert tracking-alert--success" data-delivered-banner <?= $delivered ? '' : 'hidden' ?>>
        <strong><?= e(__('tracking.delivered_title')) ?></strong>
        <p><?= e(__('tracking.delivered_text')) ?></p>
    </div>

    <section class="tracking-card" aria-labelledby="tracking-recap-title">
        <h2 class="tracking-card__title" id="tracking-recap-title"><?= e(__('tracking.recap')) ?></h2>

        <ul class="tracking-items">
            <?php foreach ($items as $item): ?>
                <li class="tracking-item">
                    <?php if (trim((string) $item->image_path) !== ''): ?>
                        <span class="tracking-item__thumb">
                            <img src="<?= e(upload_url((string) $item->image_path)) ?>" alt="" width="64" height="80"
                                 loading="lazy" decoding="async">
                        </span>
                    <?php else: ?>
                        <span class="tracking-item__thumb tracking-item__thumb--blank" aria-hidden="true"></span>
                    <?php endif; ?>

                    <div class="tracking-item__main">
                        <p class="tracking-item__name"><?= e($item->localizedName()) ?></p>
                        <p class="tracking-item__meta">
                            <?php if (trim((string) $item->size) !== ''): ?>
                                <span class="tracking-item__size"><?= e($item->sizeLabel()) ?></span>
                            <?php endif; ?>
                            <span>× <?= e((string) $item->quantity) ?></span>
                        </p>
                    </div>

                    <span class="tracking-item__price"><?= e(money((int) $item->line_total)) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="tracking-totals">
            <div class="tracking-totals__row">
                <span><?= e(__('cart.subtotal')) ?></span>
                <span class="tracking-totals__amount"><?= e(money((int) $order->subtotal)) ?></span>
            </div>
            <div class="tracking-totals__row">
                <span><?= e(__('cart.shipping')) ?></span>
                <span class="tracking-totals__amount">
                    <?= $shippingFree ? e(__('cart.shipping_free')) : e(money((int) $order->shipping_cost)) ?>
                </span>
            </div>
            <div class="tracking-totals__row tracking-totals__row--grand">
                <span><?= e(__('cart.total')) ?></span>
                <span class="tracking-totals__amount"><?= e(money((int) $order->total)) ?></span>
            </div>
        </div>
    </section>

    <section class="tracking-card" aria-labelledby="tracking-delivery-title">
        <h2 class="tracking-card__title" id="tracking-delivery-title"><?= e(__('tracking.delivery')) ?></h2>

        <dl class="tracking-grid">
            <div>
                <dt><?= e(__('tracking.customer')) ?></dt>
                <dd><?= e($order->customerName()) ?></dd>
            </div>
            <div>
                <dt><?= e(__('common.phone')) ?></dt>
                <dd>
                    <a href="tel:<?= e((string) preg_replace('/[^+0-9]/', '', (string) $order->customer_phone)) ?>">
                        <?= e((string) $order->customer_phone) ?>
                    </a>
                </dd>
            </div>
            <div>
                <dt><?= e(__('common.email')) ?></dt>
                <dd>
                    <a href="mailto:<?= e((string) $order->customer_email) ?>"><?= e((string) $order->customer_email) ?></a>
                </dd>
            </div>
            <div>
                <dt><?= e(__('common.address')) ?></dt>
                <dd><?= e($order->shippingAddress()) ?></dd>
            </div>
            <div>
                <dt><?= e(__('admin.order.zone')) ?></dt>
                <dd><?= e((string) ($zone?->label ?? ($order->countryName() ?? '—'))) ?></dd>
            </div>
        </dl>
    </section>

    <section class="tracking-card" aria-labelledby="tracking-payment-title">
        <h2 class="tracking-card__title" id="tracking-payment-title"><?= e(__('tracking.payment')) ?></h2>

        <dl class="tracking-grid">
            <div>
                <dt><?= e(__('checkout.payment_method')) ?></dt>
                <dd data-payment-method><?= e($payload['payment']['methodLabel']) ?></dd>
            </div>
            <div>
                <dt><?= e(__('common.payment_status')) ?></dt>
                <dd>
                    <span class="tracking-pill tracking-pill--payment-<?= e($payload['payment']['status']) ?>"
                          data-payment-status role="status" aria-live="polite">
                        <?= e($payload['payment']['statusLabel']) ?>
                    </span>
                </dd>
            </div>
            <div>
                <dt><?= e(__('tracking.tracking_number')) ?></dt>
                <dd>
                    <span data-track-number><?= $trackingNumber !== '' ? e($trackingNumber) : '—' ?></span>
                </dd>
            </div>
        </dl>

        <?php if ($waveUnpaid): ?>
            <p class="tracking-hint" data-wave-hint><?= e(__('tracking.wave_unpaid')) ?></p>
        <?php endif; ?>

        <?php if ($proofPath !== null): ?>
            <div class="tracking-proof" data-proof-holder>
                <span class="tracking-proof__label"><?= e(__('tracking.proof')) ?></span>
                <img class="tracking-proof__img" src="<?= e(upload_url($proofPath)) ?>" alt="<?= e(__('tracking.proof')) ?>"
                     width="120" height="160" loading="lazy" decoding="async">
            </div>
        <?php endif; ?>
    </section>

    <p class="tracking-foot">
        <a href="<?= e(url('/order/success/' . rawurlencode($payload['reference']))) ?>">
            ← <?= e(__('order.track_order')) ?>
        </a>
    </p>
</div>