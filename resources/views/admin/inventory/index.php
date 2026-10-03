<?php
/**
 * Gestion du stock : alertes et ajustements par variante.
 *
 * @var array<int, array<string, mixed>> $rows
 * @var string $level
 * @var int    $threshold
 * @var array<int, int> $deltas
 * @var int    $lowCount
 * @var int    $outCount
 * @var array<int, \App\Models\Category> $categories
 */
component('toast');

$levels = [
    'all' => __('admin.inventory.title'),
    'low' => __('admin.inventory.low'),
    'out' => __('admin.inventory.out'),
];
?>
<div class="admin-page">

    <header class="admin-page__head">
        <h1 class="admin-page__title"><?= e(__('admin.inventory.title')) ?></h1>
        <div class="admin-page__tools">
            <span class="pill"><?= e(__('admin.kpi.low_stock')) ?> : <?= e((string) $lowCount) ?></span>
            <span class="pill"><?= e(__('admin.kpi.out_of_stock')) ?> : <?= e((string) $outCount) ?></span>
        </div>
    </header>

    <nav class="admin-page__tools" style="margin:0 0 1.2rem" aria-label="<?= e(__('common.filter')) ?>">
        <?php foreach ($levels as $value => $text): ?>
            <a class="btn btn-sm <?= $level === $value ? 'btn-light' : 'btn-outline-light' ?>"
               href="<?= e(url('/admin/inventory?level=' . $value)) ?>">
                <?= e($text) ?>
                <?php if ($value === 'low') { ?> (<?= e((string) $lowCount) ?>)<?php } ?>
                <?php if ($value === 'out') { ?> (<?= e((string) $outCount) ?>)<?php } ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (has_error('delta')): ?>
        <p class="order-warning"><?= e((string) error_for('delta')) ?></p>
    <?php endif; ?>

    <section class="admin-panel">
        <div class="table-scroll">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th><?= e(__('common.product')) ?></th>
                        <th><?= e(__('admin.product.category')) ?></th>
                        <th><?= e(__('admin.variants.size')) ?></th>
                        <th><?= e(__('admin.variants.sku')) ?></th>
                        <th class="is-end"><?= e(__('admin.inventory.stock')) ?></th>
                        <th class="is-end"><?= e(__('admin.inventory.adjust')) ?></th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $levelRow = stock_status((int) $row['stock'])['level']; ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('/admin/products/' . $row['product_id'] . '/edit')) ?>">
                                    <?= e((string) $row['name']) ?>
                                </a>
                            </td>
                            <td><?= e((string) ($row['category_name'] ?? '—')) ?></td>
                            <td><?= e((string) $row['size']) ?></td>
                            <td><?= e((string) ($row['sku'] ?? '—')) ?></td>
                            <td class="is-end">
                                <?php component('badge', [
                                    'label'  => (string) $row['stock'],
                                    'status' => $levelRow,
                                    'map'    => [
                                        'in'  => 'admin.inventory.ok',
                                        'low' => 'admin.inventory.low',
                                        'out' => 'admin.inventory.out',
                                    ],
                                ]); ?>
                            </td>
                            <td class="is-end">
                                <div class="admin-table__actions" style="justify-content:flex-end">
                                    <?php foreach ($deltas as $delta): ?>
                                        <form method="post"
                                              action="<?= e(url('/admin/inventory/' . $row['id'] . '/adjust')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="delta" value="<?= e((string) $delta) ?>">
                                            <button type="submit" class="btn btn-sm <?= $delta > 0 ? 'btn-light' : 'btn-outline-light' ?>"
                                                    title="<?= e($delta > 0 ? '+' . $delta : (string) $delta) ?>">
                                                <?= e($delta > 0 ? '+' . $delta : (string) $delta) ?>
                                            </button>
                                        </form>
                                    <?php endforeach; ?>

                                    <form method="post"
                                          action="<?= e(url('/admin/inventory/' . $row['id'] . '/adjust')) ?>"
                                          data-delta-form>
                                        <?= csrf_field() ?>
                                        <input type="number" name="delta" step="1" style="width:4.5rem"
                                               placeholder="±" aria-label="<?= e(__('admin.inventory.adjust')) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-light">
                                            <?= e(__('common.update')) ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($rows === []): ?>
            <?php component('empty-state', ['title' => __('admin.inventory.ok')]); ?>
        <?php endif; ?>
    </section>

    <p class="admin-field__hint"><?= e(__('admin.inventory.threshold')) ?> : <?= e((string) $threshold) ?></p>
</div>