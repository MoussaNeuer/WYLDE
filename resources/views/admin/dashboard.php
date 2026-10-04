<?php
/**
 * Tableau de bord.
 *
 * @var array<string, int>        $summary
 * @var array<int, \App\Models\Order> $orders
 * @var array<string, int>        $byStatus
 * @var array<int, array<string, mixed>> $topProducts
 * @var array<int, array<string, string|int>> $series
 */
use App\Models\Order;

component('toast');

$maxRevenue = 0;

foreach ($series as $point) {
    $maxRevenue = max($maxRevenue, (int) $point['revenue']);
}
?>
<div class="admin-page">

    <header class="admin-page__head">
        <h1 class="admin-page__title"><?= e(__('admin.dashboard')) ?></h1>
        <div class="admin-page__tools">
            <a href="<?= e(url('/admin/products/create')) ?>" class="btn btn-light btn-sm">
                <?= e(__('admin.quick_actions.add_product')) ?>
            </a>
            <a href="<?= e(url('/admin/orders?status=pending')) ?>" class="btn btn-outline-light btn-sm">
                <?= e(__('admin.quick_actions.view_orders')) ?>
                <?php if ($summary['actionable'] > 0): ?>
                    <span class="pill"><?= e((string) $summary['actionable']) ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= e(url('/admin/inventory?level=low')) ?>" class="btn btn-outline-light btn-sm">
                <?= e(__('admin.quick_actions.manage_stock')) ?>
            </a>
        </div>
    </header>

    <div class="stat-grid">
        <?php component('stat-card', [
            'label' => __('admin.kpi.revenue'),
            'value' => money($summary['revenue_today']),
            'hint'  => __('admin.kpi.orders') . ' : ' . $summary['orders_today'],
            'url'   => url('/admin/analytics?days=7'),
        ]); ?>

        <?php component('stat-card', [
            'label' => __('admin.kpi.orders') . ' (7 j)',
            'value' => (string) $summary['orders_week'],
            'hint'  => $summary['pending_orders'] > 0
                ? $summary['pending_orders'] . ' ' . __('order.status.pending')
                : null,
            'url'   => url('/admin/orders'),
        ]); ?>

        <?php component('stat-card', [
            'label' => __('admin.kpi.customers'),
            'value' => (string) $summary['customers_total'],
            'hint'  => '+' . $summary['customers_week'] . ' / 7 j',
            'url'   => url('/admin/customers'),
        ]); ?>

        <?php component('stat-card', [
            'label' => __('admin.kpi.active_products'),
            'value' => (string) $summary['products_live'],
            'hint'  => $summary['products_draft'] . ' ' . __('common.status'),
            'url'   => url('/admin/products'),
        ]); ?>

        <?php component('stat-card', [
            'label' => __('admin.kpi.low_stock'),
            'value' => (string) $summary['low_stock'],
            'tone'  => $summary['low_stock'] > 0 ? 'down' : 'flat',
            'url'   => url('/admin/inventory?level=low'),
        ]); ?>

        <?php component('stat-card', [
            'label' => __('admin.kpi.out_of_stock'),
            'value' => (string) $summary['out_of_stock'],
            'tone'  => $summary['out_of_stock'] > 0 ? 'down' : 'flat',
            'url'   => url('/admin/inventory?level=out'),
        ]); ?>
    </div>

    <div class="admin-cols">
        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.analytics_title')) ?></h2>
                <a href="<?= e(url('/admin/analytics')) ?>" class="admin-panel__link"><?= e(__('common.details')) ?></a>
            </header>

            <div class="chart" data-chart>
                <div class="chart__bars">
                    <?php foreach ($series as $point): ?>
                        <div class="chart__col" title="<?= e(format_date($point['date'], 'd/m/Y') . ' : ' . money($point['revenue'])) ?>">
                            <span class="chart__bar"
                                  style="height: <?= $maxRevenue > 0
                                      ? max(2, (int) round(((int) $point['revenue'] / $maxRevenue) * 100))
                                      : 2 ?>%"></span>
                            <span class="chart__label"><?= e($point['label']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="chart__caption"><?= e(money($summary['revenue_week'])) ?> — <?= e(__('admin.period.week')) ?></p>
            </div>
        </section>

        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.orders')) ?></h2>
                <a href="<?= e(url('/admin/orders')) ?>" class="admin-panel__link"><?= e(__('common.all')) ?></a>
            </header>

            <ul class="admin-list">
                <?php foreach (Order::statuses() as $status): ?>
                    <li>
                        <a href="<?= e(url('/admin/orders?status=' . $status)) ?>">
                            <span class="admin-list__label"><?= e(__('order.status.' . $status)) ?></span>
                            <span class="admin-list__value"><?= e((string) ($byStatus[$status] ?? 0)) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>

    <div class="admin-cols">
        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.orders')) ?></h2>
                <a href="<?= e(url('/admin/orders')) ?>" class="admin-panel__link"><?= e(__('common.all')) ?></a>
            </header>

            <?php component('admin-table', [
                'title'  => '',
                'rows'   => $orders,
                'head'   => '<th>' . e(__('order.reference')) . '</th>'
                         . '<th>' . e(__('admin.order.customer')) . '</th>'
                         . '<th>' . e(__('common.status')) . '</th>'
                         . '<th class="is-end">' . e(__('common.total')) . '</th>',
                'row'    => function ($order): string {
                    ob_start();
                    ?>
                    <td><a href="<?= e(url('/admin/orders/' . $order->id())) ?>"><?= e((string) $order->reference) ?></a></td>
                    <td><?= e($order->customerName()) ?></td>
                    <td><?php component('badge', ['status' => (string) $order->status]); ?></td>
                    <td class="is-end"><?= e(money($order->total)) ?></td>
                    <?php
                    return (string) ob_get_clean();
                },
                'emptyTitle' => __('admin.empty.orders'),
            ]); ?>
        </section>

        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.analytics_title')) ?></h2>
            </header>

            <ul class="admin-list">
                <?php if ($topProducts === []): ?>
                    <li class="admin-list__empty"><?= e(__('admin.empty.products')) ?></li>
                <?php endif; ?>

                <?php foreach ($topProducts as $row): ?>
                    <li>
                        <a href="<?= e(url('/admin/products?q=' . urlencode((string) $row['slug']))) ?>">
                            <span class="admin-list__label"><?= e((string) $row['name']) ?></span>
                            <span class="admin-list__value"><?= e((string) $row['sold']) ?> · <?= e(money($row['revenue'])) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
</div>