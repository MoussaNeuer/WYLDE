<?php
/**
 * Liste des commandes.
 *
 * @var array<int, \App\Models\Order> $orders
 * @var array<string, mixed> $filters
 * @var array<int, string> $statuses
 * @var array<int, string> $paymentStatuses
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var string|null $prevUrl
 * @var string|null $nextUrl
 */
use App\Models\Order;

component('toast');

$orderSorts = [
    'recent'     => __('admin.sort.recent'),
    'oldest'     => __('admin.sort.oldest'),
    'total_desc' => __('admin.sort.total_desc'),
    'total_asc'  => __('admin.sort.total_asc'),
    'status'     => __('common.status'),
];

ob_start();
?>
<label class="admin-field">
    <span><?= e(__('order.reference')) ?></span>
    <input type="search" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>"
           placeholder="WYL-… / e-mail">
</label>

<label class="admin-field">
    <span><?= e(__('common.status')) ?></span>
    <select name="status">
        <option value=""><?= e(__('common.all')) ?></option>
        <?php foreach ($statuses as $status): ?>
            <option value="<?= e($status) ?>"<?= ($filters['status'] ?? '') === $status ? ' selected' : '' ?>>
                <?= e(__('order.status.' . $status)) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label class="admin-field">
    <span><?= e(__('admin.order.mark_paid')) ?></span>
    <select name="payment_status">
        <option value=""><?= e(__('common.all')) ?></option>
        <?php foreach ($paymentStatuses as $payment): ?>
            <option value="<?= e($payment) ?>"<?= ($filters['payment_status'] ?? '') === $payment ? ' selected' : '' ?>>
                <?= e(__('order.payment_status.' . $payment)) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label class="admin-field">
    <span><?= e(__('order.payment_method.cod')) ?></span>
    <select name="payment_method">
        <option value=""><?= e(__('common.all')) ?></option>
        <?php foreach (['cod', 'wave'] as $method): ?>
            <option value="<?= e($method) ?>"<?= ($filters['payment_method'] ?? '') === $method ? ' selected' : '' ?>>
                <?= e(__('order.payment_method.' . $method)) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<div class="admin-form__grid">
    <label class="admin-field">
        <span><?= e(__('common.date')) ?></span>
        <input type="date" name="from" value="<?= e((string) ($filters['from'] ?? '')) ?>">
    </label>

    <label class="admin-field">
        <span>→</span>
        <input type="date" name="to" value="<?= e((string) ($filters['to'] ?? '')) ?>">
    </label>
</div>

<label class="admin-field">
    <span><?= e(__('common.sort')) ?></span>
    <select name="sort">
        <?php foreach ($orderSorts as $value => $text): ?>
            <option value="<?= e($value) ?>"<?= ($filters['sort'] ?? 'recent') === $value ? ' selected' : '' ?>>
                <?= e($text) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>
<?php
$filterFields = (string) ob_get_clean();
?>
<div class="admin-page">

    <header class="admin-page__head">
        <h1 class="admin-page__title"><?= e(__('admin.orders')) ?></h1>
        <div class="admin-page__tools">
            <span class="pill"><?= e((string) $total) ?></span>
        </div>
    </header>

    <div class="admin-cols">
        <?php component('sidebar', [
            'title'   => __('common.filter'),
            'action'  => url('/admin/orders'),
            'content' => $filterFields,
        ]); ?>

        <?php ob_start(); ?>
        <?php component('admin-table', [
            'rows'  => $orders,
            'head'  => '<th>' . e(__('order.reference')) . '</th>'
                    . '<th>' . e(__('admin.order.customer')) . '</th>'
                    . '<th>' . e(__('order.placed_on')) . '</th>'
                    . '<th>' . e(__('common.status')) . '</th>'
                    . '<th>' . e(__('admin.order.mark_paid')) . '</th>'
                    . '<th class="is-end">' . e(__('common.total')) . '</th>'
                    . '<th class="is-end">' . e(__('common.actions')) . '</th>',
            'row'   => static function (Order $order): string {
                ob_start();
                ?>
                <td>
                    <a href="<?= e(url('/admin/orders/' . $order->id())) ?>">#<?= e($order->reference) ?></a>
                    <?php if ($order->isGuest()): ?>
                        <span class="pill"><?= e(__('admin.order.guest')) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= e($order->customerName()) ?></td>
                <td><?= e(format_date($order->created_at, 'd/m/Y H:i')) ?></td>
                <td><?php component('badge', ['status' => (string) $order->status]); ?></td>
                <td><?php component('badge', ['status' => (string) $order->payment_status]); ?></td>
                <td class="is-end"><?= e(money($order->total)) ?></td>
                <td class="is-end">
                    <a class="btn btn-sm btn-outline-light"
                       href="<?= e(url('/admin/orders/' . $order->id())) ?>"><?= e(__('common.view')) ?></a>
                </td>
                <?php
                return (string) ob_get_clean();
            },
            'emptyTitle' => __('admin.empty.orders'),
        ]); ?>
        <?php
        component('pagination', [
            'current' => $page,
            'last'    => $pages,
            'prevUrl' => $prevUrl,
            'nextUrl' => $nextUrl,
        ]);
        echo (string) ob_get_clean();
        ?>
    </div>
</div>