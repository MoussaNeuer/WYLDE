<?php
/**
 * Historique des commandes du client.
 *
 * @var string $title
 * @var array<int, \App\Models\Order> $orders
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var string|null $prevUrl
 * @var string|null $nextUrl
 */
?>
<section class="section">
    <div class="container">
        <header class="page-head">
            <h1 class="page-head__title"><?= e(__('account.orders')) ?></h1>
            <p class="page-head__text">
                <?= e(trans_choice('account.orders_count', $total)) ?>
            </p>
        </header>

        <?php if ($orders === []): ?>
            <div class="empty-state">
                <p class="empty-state__title"><?= e(__('account.no_orders')) ?></p>
                <p class="empty-state__text"><?= e(__('account.no_orders_text')) ?></p>
                <a class="btn btn-light" href="<?= e(url('/shop')) ?>">
                    <?= e(__('cart.continue')) ?>
                </a>
            </div>
        <?php else: ?>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?= e(__('order.reference')) ?></th>
                            <th><?= e(__('order.placed_on')) ?></th>
                            <th><?= e(__('common.status')) ?></th>
                            <th><?= e(__('cart.total')) ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td data-label="<?= e(__('order.reference')) ?>">
                                    <?= e((string) $order->reference) ?>
                                </td>
                                <td data-label="<?= e(__('order.placed_on')) ?>">
                                    <?= e(format_date((string) $order->created_at)) ?>
                                </td>
                                <td data-label="<?= e(__('common.status')) ?>">
                                    <?php component('badge', ['status' => (string) $order->status]); ?>
                                </td>
                                <td data-label="<?= e(__('cart.total')) ?>">
                                    <?= e(money((int) $order->total)) ?>
                                </td>
                                <td>
                                    <a class="btn btn-sm btn-outline-light"
                                       href="<?= e(url('/account/orders/' . $order->id())) ?>">
                                        <?= e(__('account.view_detail')) ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1): ?>
                <nav class="pagination" aria-label="<?= e(__('account.orders')) ?>">
                    <?php if ($prevUrl !== null): ?>
                        <a href="<?= e(url($prevUrl)) ?>" rel="prev"><?= e(__('common.previous')) ?></a>
                    <?php else: ?>
                        <span class="is-disabled"><?= e(__('common.previous')) ?></span>
                    <?php endif; ?>

                    <span class="is-current">
                        <span><?= e(__('common.page_number', ['%d' => $page])) ?></span>
                    </span>

                    <?php if ($nextUrl !== null): ?>
                        <a href="<?= e(url($nextUrl)) ?>" rel="next"><?= e(__('common.next')) ?></a>
                    <?php else: ?>
                        <span class="is-disabled"><?= e(__('common.next')) ?></span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
