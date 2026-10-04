<?php

/**
 * Catalogue des tailles.
 *
 * Ces tailles sont les seules proposées dans le formulaire produit, et
 * sort_order est l'ordre du sélecteur public. Le compteur indique quand
 * une taille est encore portée par des variantes : la suppression et le
 * renommage sont alors refusés, faute de quoi un produit deviendrait
 * impossible à enregistrer.
 *
 * @var string $title
 * @var array<int, array{size: \App\Models\ProductSize, usage: int, products: int}> $sizes
 * @var array<int, string> $presets
 * @var int $total
 */
component('toast');

$existing = array_map(static fn (array $row): string => (string) $row['size']->label, $sizes);
$missing  = array_values(array_filter($presets, static fn (string $label): bool => !in_array($label, $existing, true)));
?>

<div class="admin-page">

    <a class="admin-page__back" href="<?= e(url('/admin/settings')) ?>">← <?= e(__('admin.settings')) ?></a>

    <header class="admin-page__head">
        <div>
            <h1 class="admin-page__title"><?= e($title) ?></h1>
            <p class="admin-page__lead"><?= e(__('admin.sizes.subtitle')) ?></p>
        </div>
        <div class="admin-page__tools">
            <span class="badge badge--default">
                <?= e((string) $total) ?> <?= e($total > 1 ? __('admin.sizes.count') : __('admin.sizes.count_one')) ?>
            </span>
        </div>
    </header>

    <div class="admin-cols">
        <section class="admin-panel">
            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th><?= e(__('admin.sizes.label')) ?></th>
                            <th><?= e(__('admin.sizes.sort_order')) ?></th>
                            <th class="is-end"><?= e(__('admin.sizes.used_by')) ?></th>
                            <th class="is-end"><?= e(__('common.actions')) ?></th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($sizes as $row): ?>
                            <?php
                            $size   = $row['size'];
                            $sizeId = (int) $size->id();
                            $formId = 'size-' . $sizeId;
                            ?>
                            <tr>
                                <td>
                                    <label class="visually-hidden" for="s-label-<?= e((string) $sizeId) ?>">
                                        <?= e(__('admin.sizes.label')) ?>
                                    </label>
                                    <input type="text" id="s-label-<?= e((string) $sizeId) ?>" name="label"
                                           form="<?= e($formId) ?>"
                                           maxlength="<?= e((string) \App\Models\ProductSize::MAX_LENGTH) ?>"
                                           value="<?= e($size->label) ?>" required>
                                </td>

                                <td>
                                    <label class="visually-hidden" for="s-sort-<?= e((string) $sizeId) ?>">
                                        <?= e(__('admin.sizes.sort_order')) ?>
                                    </label>
                                    <input type="number" id="s-sort-<?= e((string) $sizeId) ?>" name="sort_order"
                                           form="<?= e($formId) ?>" min="0" step="10"
                                           value="<?= e((string) $size->sort_order) ?>" style="width:5rem">
                                </td>

                                <td class="is-end">
                                    <?php if ($row['usage'] > 0): ?>
                                        <span class="badge badge--default" title="<?= e(__('admin.sizes.used_by_hint')) ?>">
                                            <?= e((string) $row['products']) ?>
                                            <?= e($row['products'] > 1 ? __('admin.sizes.products') : __('admin.sizes.product_one')) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>

                                <td class="is-end">
                                    <div class="admin-table__actions">
                                        <button type="submit" class="btn btn-sm btn-light" form="<?= e($formId) ?>">
                                            <?= e(__('common.save')) ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($sizes === []): ?>
                <?php component('empty-state', [
                    'title' => __('admin.sizes.empty'),
                    'text'  => __('admin.sizes.empty_hint'),
                ]); ?>
            <?php endif; ?>

            <p class="admin-field__hint" style="padding:0.8rem 1.2rem"><?= e(__('admin.sizes.order_hint')) ?></p>

            <?php foreach ($sizes as $row): ?>
                <form method="post" id="size-<?= e((string) (int) $row['size']->id()) ?>" hidden
                      action="<?= e(url('/admin/sizes/' . (int) $row['size']->id())) ?>">
                    <?= csrf_field() ?>
                </form>
            <?php endforeach; ?>
        </section>

        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.sizes.add')) ?></h2>
            </header>

            <form class="admin-filter__form" method="post" action="<?= e(url('/admin/sizes')) ?>">
                <?= csrf_field() ?>

                <?php if (has_error('label')): ?>
                    <p class="admin-field__error"><?= e((string) error_for('label')) ?></p>
                <?php endif; ?>

                <div class="admin-field">
                    <label for="nz-size"><?= e(__('admin.sizes.label')) ?> *</label>
                    <input type="text" id="nz-size" name="label" required
                           maxlength="<?= e((string) \App\Models\ProductSize::MAX_LENGTH) ?>"
                           placeholder="<?= e(__('admin.sizes.placeholder')) ?>"
                           value="<?= e((string) old('label')) ?>">
                    <p class="admin-field__hint"><?= e(__('admin.sizes.add_hint')) ?></p>
                </div>

                <div class="admin-filter__actions">
                    <button type="submit" class="btn btn-sm btn-light"><?= e(__('common.create')) ?></button>
                </div>
            </form>

            <?php if ($missing !== []): ?>
                <footer class="admin-panel__foot">
                    <form method="post" action="<?= e(url('/admin/sizes/preset')) ?>"
                          data-confirm="<?= e(__('admin.sizes.preset_confirm')) ?>">
                        <?= csrf_field() ?>
                        <p class="admin-field__hint"><?= e(__('admin.sizes.preset_hint')) ?></p>
                        <button type="submit" class="btn btn-sm btn-outline-light">
                            <?= e(__('admin.sizes.add_preset')) ?>
                        </button>
                    </form>
                </footer>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($sizes !== []): ?>
        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('common.delete')) ?></h2>
            </header>

            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th><?= e(__('admin.sizes.label')) ?></th>
                            <th class="is-end"><?= e(__('admin.sizes.used_by')) ?></th>
                            <th class="is-end"><?= e(__('common.actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sizes as $row): ?>
                            <tr>
                                <td><?= e($row['size']->label) ?></td>
                                <td class="is-end"><?= e((string) $row['usage']) ?></td>
                                <td class="is-end">
                                    <?php if ($row['usage'] > 0): ?>
                                        <span class="text-muted"><?= e(__('admin.sizes.locked')) ?></span>
                                    <?php else: ?>
                                        <form method="post"
                                              action="<?= e(url('/admin/sizes/' . (int) $row['size']->id() . '/delete')) ?>"
                                              data-confirm="<?= e(__('admin.sizes.delete_confirm')) ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <?= e(__('common.delete')) ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>
