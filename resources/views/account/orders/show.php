<?php
/**
 * Détail d'une commande pour le client.
 *
 * Le client voit l'état de sa commande et sa livraison ; les champs
 * internes (IP, hash, coût de livraison appliqué côté back-office) ne
 * sont pas exposés ici.
 *
 * @var string $title
 * @var \App\Models\Order $order
 */
$items    = $order->items();
$history  = $order->history();
$tracking = trim((string) $order->tracking_number);
?>
<section class="section">
    <div class="container">
        <a class="link-more" href="<?= e(url('/account/orders')) ?>">
            ← <?= e(__('account.back_to_orders')) ?>
        </a>

        <header class="page-head">
            <h1 class="page-head__title"><?= e((string) $order->reference) ?></h1>
            <p class="page-head__text">
                <?= e(__('order.placed_on')) ?>
                <?= e(format_date((string) $order->created_at)) ?>
            </p>
        </header>

        <div class="card-surface">
            <div class="card-surface__body">
                <dl class="summary">
                    <div class="summary__row">
                        <span><?= e(__('common.status')) ?></span>
                        <span><?php component('badge', ['status' => (string) $order->status]); ?></span>
                    </div>
                    <div class="summary__row">
                        <span><?= e(__('checkout.payment_method')) ?></span>
                        <span><?= e(__('order.payment_method.' . $order->payment_method)) ?></span>
                    </div>
                    <div class="summary__row">
                        <span><?= e(__('common.payment_status')) ?></span>
                        <span><?php component('badge', ['status' => (string) $order->payment_status]); ?></span>
                    </div>
                    <div class="summary__row">
                        <span><?= e(__('account.tracking')) ?></span>
                        <span>
                            <?= e($tracking !== '' ? $tracking : __('account.tracking_none')) ?>
                        </span>
                    </div>
                </dl>
            </div>
        </div>

        <h2><?= e(__('account.order_items')) ?></h2>

        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?= e(__('common.product')) ?></th>
                        <th><?= e(__('common.quantity')) ?></th>
                        <th><?= e(__('common.price')) ?></th>
                        <th><?= e(__('cart.total')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td data-label="<?= e(__('common.product')) ?>">
                                <?= e($item->localizedName()) ?>
                                <?php if (trim((string) $item->size) !== ''): ?>
                                    <br>
                                    <small>
                                        <?= e(__('common.size')) ?> :
                                        <?= e($item->sizeLabel()) ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?= e(__('common.quantity')) ?>">
                                <?= e((string) $item->quantity) ?>
                            </td>
                            <td data-label="<?= e(__('common.price')) ?>">
                                <?= e(money((int) $item->unit_price)) ?>
                            </td>
                            <td data-label="<?= e(__('cart.total')) ?>">
                                <?= e(money((int) $item->line_total)) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="summary">
            <div class="summary__row">
                <span><?= e(__('cart.subtotal')) ?></span>
                <span class="amount"><?= e(money((int) $order->subtotal)) ?></span>
            </div>
            <div class="summary__row">
                <span><?= e(__('cart.shipping')) ?></span>
                <span class="amount">
                    <?= (int) $order->shipping_price === 0
                        ? e(__('cart.shipping_free'))
                        : e(money((int) $order->shipping_price)) ?>
                </span>
            </div>
            <div class="summary__row summary__row--total">
                <span><?= e(__('cart.total')) ?></span>
                <span class="amount"><?= e(money((int) $order->total)) ?></span>
            </div>
        </div>

        <h2><?= e(__('account.delivery_address')) ?></h2>
        <p><?= nl2br(e($order->shippingAddress())) ?></p>

        <?php if ($history !== []): ?>
            <h2><?= e(__('account.order_history')) ?></h2>
            <ol class="summary">
                <?php foreach (array_reverse($history) as $line): ?>
                    <li class="summary__row">
                        <span>
                            <?php component('badge', ['status' => (string) $line['status']]); ?>
                            <?php if (trim((string) ($line['note'] ?? '')) !== ''): ?>
                                <span><?= e((string) $line['note']) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="amount"><?= e(format_date((string) $line['created_at'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</section>
