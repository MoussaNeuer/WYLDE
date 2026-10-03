<?php
/**
 * Fiche client : identité, historique d'achats.
 *
 * La fiche est en lecture seule en V1 (cf. CustomerController).
 *
 * @var \App\Models\Customer $customer
 * @var array<int, \App\Models\Order> $orders
 */
component('toast');

$user   = $customer->user();
$totals = ['count' => 0, 'spent' => 0, 'items' => 0];

foreach ($orders as $order) {
    $totals['count']++;

    if ((string) $order->status !== 'cancelled' && (string) $order->payment_status === 'paid') {
        $totals['spent'] += (int) $order->total;
    }

    foreach ($order->items() as $item) {
        $totals['items'] += (int) $item->quantity;
    }
}
?>
<div class="admin-page">

    <a class="admin-page__back" href="<?= e(url('/admin/customers')) ?>">← <?= e(__('admin.customers')) ?></a>

    <header class="admin-page__head">
        <h1 class="admin-page__title"><?= e($customer->fullName()) ?></h1>
        <div class="admin-page__tools">
            <a class="btn btn-outline-light btn-sm"
               href="mailto:<?= e((string) $customer->email) ?>"><?= e(__('common.email')) ?></a>
        </div>
    </header>

    <div class="stat-grid">
        <?php component('stat-card', [
            'label' => __('admin.kpi.orders'),
            'value' => (string) $totals['count'],
        ]); ?>

        <?php component('stat-card', [
            'label' => __('admin.customer.lifetime'),
            'value' => money($totals['spent']),
        ]); ?>

        <?php component('stat-card', [
            'label' => __('common.quantity'),
            'value' => (string) $totals['items'],
        ]); ?>

        <?php component('stat-card', [
            'label' => __('admin.profile'),
            'value' => $user !== null ? __('common.yes') : __('admin.order.guest'),
        ]); ?>
    </div>

    <div class="admin-cols">
        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.order.customer')) ?></h2>
            </header>

            <dl class="admin-panel__head" style="display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;padding:1.2rem">
                <div>
                    <dt><?= e(__('admin.product.name')) ?></dt>
                    <dd><?= e($customer->fullName()) ?></dd>
                </div>
                <div>
                    <dt><?= e(__('common.email')) ?></dt>
                    <dd><a href="mailto:<?= e((string) $customer->email) ?>"><?= e((string) $customer->email) ?></a></dd>
                </div>
                <div>
                    <dt><?= e(__('common.phone')) ?></dt>
                    <dd><?= e((string) $customer->phone) ?></dd>
                </div>
                <div>
                    <dt><?= e(__('common.city')) ?></dt>
                    <dd><?= e((string) $customer->city) ?></dd>
                </div>
                <div style="grid-column:1/-1">
                    <dt><?= e(__('common.address')) ?></dt>
                    <dd><?= e((string) $customer->address) ?></dd>
                </div>
                <div>
                    <dt><?= e(__('common.country')) ?></dt>
                    <dd><?= e((string) $customer->country_code) ?></dd>
                </div>
                <div>
                    <dt><?= e(__('common.date')) ?></dt>
                    <dd><?= e(format_date($customer->created_at)) ?></dd>
                </div>
            </dl>
        </section>

        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.orders')) ?></h2>
            </header>

            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th><?= e(__('order.reference')) ?></th>
                            <th><?= e(__('order.placed_on')) ?></th>
                            <th><?= e(__('common.status')) ?></th>
                            <th class="is-end"><?= e(__('common.total')) ?></th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <a href="<?= e(url('/admin/orders/' . $order->id())) ?>">#<?= e($order->reference) ?></a>
                                </td>
                                <td><?= e(format_date($order->created_at, 'd/m/Y')) ?></td>
                                <td><?php component('badge', ['status' => (string) $order->status]); ?></td>
                                <td class="is-end"><?= e(money($order->total)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($orders === []): ?>
                <?php component('empty-state', ['title' => __('admin.empty.orders')]); ?>
            <?php endif; ?>
        </section>
    </div>
</div>