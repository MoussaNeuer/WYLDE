<?php
/**
 * Détail d'une commande.
 *
 * @var \App\Models\Order $order
 * @var array<int, \App\Models\OrderItem> $items
 * @var array<int, array<string, mixed>> $history
 * @var array<int, string> $warnings
 * @var array<int, string> $transitions
 * @var array<int, string> $paymentStatuses
 */
use App\Models\Order;

component('toast');

$customer = $order->customer();
?>
<div class="admin-page">

    <a class="admin-page__back" href="<?= e(url('/admin/orders')) ?>">← <?= e(__('admin.orders')) ?></a>

    <header class="admin-page__head">
        <h1 class="admin-page__title">#<?= e($order->reference) ?></h1>
        <div class="admin-page__tools">
            <?php component('badge', ['status' => (string) $order->status]); ?>
            <?php component('badge', ['status' => (string) $order->payment_status]); ?>
        </div>
    </header>

    <?php if ($warnings !== []): ?>
        <div class="order-warning">
            <strong><?= e(__('admin.order.warnings')) ?></strong>
            <ul>
                <?php foreach ($warnings as $warning): ?>
                    <li><?= e($warning) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="admin-cols">
        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.order.items')) ?></h2>
            </header>

            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th><?= e(__('common.product')) ?></th>
                            <th><?= e(__('product.size')) ?></th>
                            <th class="is-end"><?= e(__('admin.order.unit_price')) ?></th>
                            <th class="is-end"><?= e(__('common.quantity')) ?></th>
                            <th class="is-end"><?= e(__('common.total')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <?php $product = $item->product(); ?>
                                    <?php if ($product !== null): ?>
                                        <a href="<?= e(url('/admin/products/' . $product->id() . '/edit')) ?>">
                                            <?= e($item->localizedName()) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= e($item->localizedName()) ?>
                                        <span class="pill"><?= e(__('admin.order.deleted_product')) ?></span>
                                    <?php endif; ?>
                                    <?php if ((string) $item->sku !== ''): ?>
                                        <br><small class="admin-field__hint"><?= e((string) $item->sku) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($item->sizeLabel()) ?></td>
                                <td class="is-end"><?= e(money($item->unit_price)) ?></td>
                                <td class="is-end"><?= e((string) $item->quantity) ?></td>
                                <td class="is-end"><?= e(money($item->line_total)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="admin-panel__foot">
                <div class="order-total">
                    <span><?= e(__('admin.order.subtotal')) ?></span>
                    <span><?= e(money($order->subtotal)) ?></span>
                </div>
                <div class="order-total">
                    <span><?= e(__('admin.order.shipping')) ?></span>
                    <span><?= e(money($order->shipping_cost)) ?></span>
                </div>
                <div class="order-total order-total--grand">
                    <span><?= e(__('admin.order.total')) ?></span>
                    <span><?= e(money($order->total)) ?></span>
                </div>
            </div>
        </section>

        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.order.customer')) ?></h2>
            </header>

            <dl class="admin-panel__head" style="display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;padding:1.2rem">
                <div>
                    <dt><?= e(__('admin.order.customer')) ?></dt>
                    <dd><?= e($order->customerName()) ?></dd>
                </div>
                <div>
                    <dt><?= e(__('common.email')) ?></dt>
                    <dd>
                        <?php if ($customer !== null): ?>
                            <a href="mailto:<?= e((string) $customer->email) ?>"><?= e((string) $customer->email) ?></a>
                        <?php else: ?>
                            <?= e((string) $order->customer_email) ?>
                        <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt><?= e(__('common.phone')) ?></dt>
                    <dd><?= e((string) ($order->customer_phone ?? '—')) ?></dd>
                </div>
                <div>
                    <dt><?= e(__('admin.order.zone')) ?></dt>
                    <dd><?= e((string) ($order->zone()?->label ?? '—')) ?></dd>
                </div>
                <div style="grid-column:1/-1">
                    <dt><?= e(__('common.address')) ?></dt>
                    <dd><?= e($order->shippingAddress()) ?></dd>
                </div>
                <div>
                    <dt><?= e(__('order.payment_method.cod')) ?></dt>
                    <dd><?= e(__('order.payment_method.' . $order->payment_method)) ?></dd>
                </div>
                <div>
                    <dt><?= e(__('order.placed_on')) ?></dt>
                    <dd><?= e(format_date($order->created_at, 'd/m/Y H:i')) ?></dd>
                </div>
                <div style="grid-column:1/-1">
                    <dt><?= e(__('admin.order.placed_by')) ?></dt>
                    <dd>
                        <?php $user = $order->user(); ?>
                        <?= e($user !== null ? (string) $user->name : __('admin.order.guest_note')) ?>
                    </dd>
                </div>
            </dl>

            <?php if (trim((string) ($order->customer_note ?? '')) !== ''): ?>
                <div class="admin-panel__foot">
                    <p class="admin-field__hint"><?= e(__('admin.order.order_note')) ?></p>
                    <p><?= e((string) $order->customer_note) ?></p>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="admin-cols">
        <section class="admin-panel" id="status">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.order.change_status')) ?></h2>
            </header>

            <?php if ($transitions === []): ?>
                <p class="admin-panel__head" style="padding:1.2rem"><?= e(__('admin.order.no_transition')) ?></p>
            <?php else: ?>
                <form class="admin-filter__form" method="post"
                      action="<?= e(url('/admin/orders/' . $order->id() . '/status')) ?>">
                    <?= csrf_field() ?>

                    <?php if (has_error('status')): ?>
                        <p class="admin-field__error"><?= e((string) error_for('status')) ?></p>
                    <?php endif; ?>

                    <div class="admin-field">
                        <label for="f-status"><?= e(__('common.status')) ?></label>
                        <select id="f-status" name="status" required>
                            <?php foreach ($transitions as $status): ?>
                                <option value="<?= e($status) ?>"><?= e(__('order.status.' . $status)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="admin-field">
                        <label for="f-note"><?= e(__('admin.order.status_note')) ?></label>
                        <input type="text" id="f-note" name="note" maxlength="255"
                               value="<?= e((string) old('note')) ?>">
                    </div>

                    <?php if (in_array('cancelled', $transitions, true)): ?>
                        <label class="admin-check">
                            <input type="checkbox" name="confirm" value="1" required>
                            <?= e(__('admin.order.confirm_cancel')) ?>
                        </label>
                    <?php endif; ?>

                    <div class="admin-filter__actions">
                        <button type="submit" class="btn btn-sm btn-light"><?= e(__('common.update')) ?></button>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <section class="admin-panel" id="payment">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.order.mark_paid')) ?></h2>
            </header>

            <form class="admin-filter__form" method="post"
                  action="<?= e(url('/admin/orders/' . $order->id() . '/payment')) ?>">
                <?= csrf_field() ?>

                <?php if (has_error('payment_status')): ?>
                    <p class="admin-field__error"><?= e((string) error_for('payment_status')) ?></p>
                <?php endif; ?>

                <div class="admin-field">
                    <label for="f-payment"><?= e(__('common.status')) ?></label>
                    <select id="f-payment" name="payment_status">
                        <?php foreach ($paymentStatuses as $payment): ?>
                            <option value="<?= e($payment) ?>"<?= (string) $order->payment_status === $payment ? ' selected' : '' ?>>
                                <?= e(__('order.payment_status.' . $payment)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="admin-filter__actions">
                    <button type="submit" class="btn btn-sm btn-light"><?= e(__('common.update')) ?></button>
                </div>
            </form>
        </section>

        <section class="admin-panel" id="notes">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.order.internal_notes')) ?></h2>
            </header>

            <form class="admin-filter__form" method="post"
                  action="<?= e(url('/admin/orders/' . $order->id() . '/notes')) ?>">
                <?= csrf_field() ?>

                <div class="admin-field">
                    <label for="f-notes"><?= e(__('admin.order.internal_notes')) ?></label>
                    <textarea id="f-notes" name="admin_notes" rows="4"><?= e((string) $order->admin_notes) ?></textarea>
                    <p class="admin-field__hint"><?= e(__('admin.order.internal_notes_hint')) ?></p>
                </div>

                <div class="admin-field">
                    <label for="f-tracking"><?= e(__('admin.order.tracking')) ?></label>
                    <input type="text" id="f-tracking" name="tracking_number" maxlength="120"
                           value="<?= e((string) $order->tracking_number) ?>">
                </div>

                <div class="admin-filter__actions">
                    <button type="submit" class="btn btn-sm btn-light"><?= e(__('common.save')) ?></button>
                </div>
            </form>
        </section>

        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.order.history')) ?></h2>
            </header>

            <div class="admin-panel__head" style="padding:1.2rem">
                <?php if ($history === []): ?>
                    <p class="admin-field__hint"><?= e(__('admin.empty.orders')) ?></p>
                <?php else: ?>
                    <ul class="timeline">
                        <?php foreach ($history as $entry): ?>
                            <li>
                                <strong><?= e(__('order.status.' . $entry['status'])) ?></strong>
                                <?php if (trim((string) ($entry['note'] ?? '')) !== ''): ?>
                                    — <?= e((string) $entry['note']) ?>
                                <?php endif; ?>
                                <span class="timeline__time">
                                    <?= e(format_date($entry['created_at'], 'd/m/Y H:i')) ?>
                                    <?php if (($entry['changed_by_name'] ?? null) !== null): ?>
                                        · <?= e((string) $entry['changed_by_name']) ?>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>