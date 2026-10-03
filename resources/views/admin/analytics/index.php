<?php
/**
 * Analytics : tendances, répartition et meilleurs clients.
 *
 * @var int   $days
 * @var array{current: array<string, int>, previous: array<string, int>, delta: array<string, mixed>} $comparison
 * @var array<int, array{date: string, label: string, revenue: int, orders: int}> $series
 * @var array<int, array<string, mixed>> $weekdays
 * @var array<int, array<string, mixed>> $topProducts
 * @var array<int, array<string, mixed>> $topCustomers
 * @var array<string, int> $byStatus
 * @var array<int, string> $orderStatuses
 */
component('toast');

$current  = $comparison['current'];
$previous = $comparison['previous'];
$delta    = $comparison['delta'];

$maxRevenue = 0;
$maxOrders  = 0;

foreach ($series as $point) {
    $maxRevenue = max($maxRevenue, (int) $point['revenue']);
}

foreach ($weekdays as $day) {
    $maxOrders = max($maxOrders, (int) ($day['orders'] ?? 0));
}

$trend = static function (mixed $value): string {
    $number = (int) $value;

    return $number > 0 ? 'up' : ($number < 0 ? 'down' : 'flat');
};
?>
<div class="admin-page">

    <header class="admin-page__head">
        <h1 class="admin-page__title"><?= e(__('admin.analytics')) ?></h1>

        <div class="admin-page__tools">
            <?php foreach ([7 => __('admin.period.week'), 30 => __('admin.period.month'), 90 => '90 j'] as $value => $text): ?>
                <a class="btn btn-sm <?= $days === $value ? 'btn-light' : 'btn-outline-light' ?>"
                   href="<?= e(url('/admin/analytics?days=' . $value)) ?>"><?= e($text) ?></a>
            <?php endforeach; ?>
        </div>
    </header>

    <div class="stat-grid">
        <?php component('stat-card', [
            'label' => __('admin.kpi.revenue'),
            'value' => money($current['revenue']),
            'hint'  => __('admin.analytics.vs_previous') . ' ' . ($delta['revenue'] ?? 0) . ' %',
            'trend' => $trend($delta['revenue'] ?? 0),
        ]); ?>

        <?php component('stat-card', [
            'label' => __('admin.kpi.orders'),
            'value' => (string) $current['orders'],
            'hint'  => __('admin.analytics.vs_previous') . ' ' . ($delta['orders'] ?? 0) . ' %',
            'trend' => $trend($delta['orders'] ?? 0),
        ]); ?>

        <?php component('stat-card', [
            'label' => __('admin.analytics.average'),
            'value' => money($current['average']),
            'hint'  => __('admin.analytics.average_hint'),
        ]); ?>

        <?php component('stat-card', [
            'label' => __('admin.analytics.cancelled'),
            'value' => (string) $current['cancelled'],
            'tone'  => $current['cancelled'] > 0 ? 'down' : 'flat',
        ]); ?>
    </div>

    <div class="admin-cols">
        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.kpi.revenue')) ?></h2>
            </header>

            <div class="chart">
                <div class="chart__bars">
                    <?php foreach ($series as $point): ?>
                        <div class="chart__col"
                             title="<?= e($point['date'] . ' · ' . money($point['revenue']) . ' · ' . $point['orders'] . ' ' . __('admin.kpi.orders')) ?>">
                            <span class="chart__bar"
                                  style="height: <?= $maxRevenue > 0
                                      ? max(2, (int) round(((int) $point['revenue'] / $maxRevenue) * 100))
                                      : 2 ?>%"></span>
                            <span class="chart__label"><?= e($point['label']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p class="chart__caption">
                    <?= e(money($current['revenue'])) ?> — <?= e(__('admin.period.month')) ?> ·
                    <?= e(__('admin.orders')) ?> : <?= e((string) $current['orders']) ?>
                </p>
            </div>
        </section>

        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.analytics.weekday')) ?></h2>
            </header>

            <div class="chart">
                <div class="chart__bars">
                    <?php foreach ($weekdays as $day): ?>
                        <div class="chart__col"
                             title="<?= e($day['label'] . ' · ' . $day['orders'] . ' ' . __('admin.kpi.orders')) ?>">
                            <span class="chart__bar"
                                  style="height: <?= $maxOrders > 0
                                      ? max(2, (int) round(((int) $day['orders'] / $maxOrders) * 100))
                                      : 2 ?>%"></span>
                            <span class="chart__label"><?= e($day['label']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </div>

    <div class="admin-cols">
        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.analytics.top_products')) ?></h2>
            </header>

            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th><?= e(__('common.product')) ?></th>
                            <th class="is-end"><?= e(__('admin.analytics.sold')) ?></th>
                            <th class="is-end"><?= e(__('common.total')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProducts as $row): ?>
                            <tr>
                                <td>
                                    <a href="<?= e(url('/admin/products/' . $row['id'] . '/edit')) ?>">
                                        <?= e(localized($row, 'name')) ?>
                                    </a>
                                </td>
                                <td class="is-end"><?= e((string) $row['sold']) ?></td>
                                <td class="is-end"><?= e(money($row['revenue'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($topProducts === []): ?>
                <?php component('empty-state', ['title' => __('admin.empty.products')]); ?>
            <?php endif; ?>
        </section>

        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.analytics.top_customers')) ?></h2>
            </header>

            <ul class="admin-list">
                <?php foreach ($topCustomers as $row): ?>
                    <li>
                        <a href="mailto:<?= e((string) $row['customer_email']) ?>">
                            <span class="admin-list__label"><?= e((string) $row['customer_email']) ?></span>
                            <span class="admin-list__value">
                                <?= e((string) $row['orders']) ?> · <?= e(money($row['spent'])) ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>

                <?php if ($topCustomers === []): ?>
                    <li class="admin-list__empty"><?= e(__('admin.empty.customers')) ?></li>
                <?php endif; ?>
            </ul>
        </section>

        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.orders')) ?></h2>
            </header>

            <ul class="admin-list">
                <?php foreach ($orderStatuses as $status): ?>
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
</div>