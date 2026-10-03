<?php
/**
 * Badge de statut coloré.
 *
 * @var string      $label
 * @var string      $status Valeur brute (order_status, product_status…).
 * @var array<string,string> $map Statut => libellé traduit.
 */
$label = $label ?? '';
$status = $status ?? '';

$map = $map ?? [
    'unpaid'    => 'order.payment_status.unpaid',
    'paid'      => 'order.payment_status.paid',
    'refunded'  => 'order.payment_status.refunded',
    'pending'   => 'order.status.pending',
    'confirmed' => 'order.status.confirmed',
    'preparing' => 'order.status.preparing',
    'shipped'   => 'order.status.shipped',
    'delivered' => 'order.status.delivered',
    'cancelled' => 'order.status.cancelled',
];

$label = $label !== '' ? $label : __($map[$status] ?? 'order.status.pending');

$tone = match (strtolower((string) $status)) {
    'paid', 'delivered', 'active', 'published', 'shipped' => 'success',
    'unpaid', 'pending', 'low_stock', 'processing'       => 'warning',
    'cancelled', 'refunded', 'disabled', 'archived'      => 'danger',
    default                                               => 'default',
};
?>
<span class="badge badge--<?= e($tone) ?>"><?= e($label) ?></span>
