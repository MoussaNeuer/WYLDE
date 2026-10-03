<?php
/**
 * Liste des clients.
 *
 * @var array<int, array<string, mixed>> $rows
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var string $query
 * @var string|null $prevUrl
 * @var string|null $nextUrl
 */
component('toast');
?>
<div class="admin-page">

    <header class="admin-page__head">
        <h1 class="admin-page__title"><?= e(__('admin.customers')) ?></h1>
        <div class="admin-page__tools">
            <span class="pill"><?= e((string) $total) ?></span>
        </div>
    </header>

    <form class="admin-filter" method="get" action="<?= e(url('/admin/customers')) ?>" role="search">
        <div class="admin-filter__form" style="grid-template-columns:1fr auto">
            <label class="admin-field">
                <span class="visually-hidden"><?= e(__('common.search')) ?></span>
                <input type="search" name="q" value="<?= e($query) ?>"
                       placeholder="<?= e(__('common.search')) ?>">
            </label>

            <div class="admin-filter__actions">
                <button type="submit" class="btn btn-sm btn-light"><?= e(__('common.search')) ?></button>
                <?php if ($query !== ''): ?>
                    <a class="btn btn-sm btn-outline-light" href="<?= e(url('/admin/customers')) ?>"><?= e(__('common.reset')) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <section class="admin-panel">
        <div class="table-scroll">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th><?= e(__('admin.order.customer')) ?></th>
                        <th><?= e(__('common.email')) ?></th>
                        <th><?= e(__('common.phone')) ?></th>
                        <th><?= e(__('common.city')) ?></th>
                        <th class="is-end"><?= e(__('admin.orders')) ?></th>
                        <th class="is-end"><?= e(__('admin.customer.lifetime')) ?></th>
                        <th><?= e(__('common.date')) ?></th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('/admin/customers/' . $row['id'])) ?>">
                                    <?= e(trim($row['first_name'] . ' ' . $row['last_name'])) ?>
                                </a>
                                <?php if ($row['user_id'] === null): ?>
                                    <span class="pill"><?= e(__('admin.order.guest')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><a href="mailto:<?= e((string) $row['email']) ?>"><?= e((string) $row['email']) ?></a></td>
                            <td><?= e((string) $row['phone']) ?></td>
                            <td><?= e((string) $row['city']) ?></td>
                            <td class="is-end"><?= e((string) $row['orders_count']) ?></td>
                            <td class="is-end"><?= e(money($row['lifetime_value'])) ?></td>
                            <td><?= e(format_date($row['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($rows === []): ?>
            <?php component('empty-state', ['title' => __('admin.empty.customers')]); ?>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
            <footer class="admin-panel__foot">
                <?php component('pagination', [
                    'current' => $page,
                    'last'    => $pages,
                    'prevUrl' => $prevUrl,
                    'nextUrl' => $nextUrl,
                ]); ?>
            </footer>
        <?php endif; ?>
    </section>
</div>