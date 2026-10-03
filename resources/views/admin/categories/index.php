<?php
/**
 * Catégories : liste à plat, édition en ligne et création.
 *
 * Chaque ligne est un formulaire : l'édition ne demande pas de quitter
 * la page, ce qui reste confortable sur une liste courte.
 *
 * @var array<int, array<string, mixed>> $categories
 * @var array<int, string> $statuses
 */
component('toast');

$statusMap = [
    'active'   => __('admin.category.active'),
    'inactive' => __('admin.category.inactive'),
];
?>
<div class="admin-page">

    <header class="admin-page__head">
        <h1 class="admin-page__title"><?= e(__('admin.categories')) ?></h1>
        <div class="admin-page__tools">
            <a class="btn btn-light btn-sm" href="#new-category"><?= e(__('admin.category.new')) ?></a>
        </div>
    </header>

    <div class="admin-cols">
        <section class="admin-panel">
            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th><?= e(__('admin.category.name')) ?></th>
                            <th><?= e(__('admin.category.parent')) ?></th>
                            <th class="is-end"><?= e(__('admin.category.products')) ?></th>
                            <th><?= e(__('common.status')) ?></th>
                            <th class="is-end"><?= e(__('admin.category.sort_order')) ?></th>
                            <th class="is-end"><?= e(__('common.actions')) ?></th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <?php $formId = 'cat-' . (int) $category['id']; ?>
                            <tr>
                                <td>
                                    <label class="visually-hidden" for="name-<?= e((string) $category['id']) ?>">
                                        <?= e(__('admin.category.name')) ?>
                                    </label>
                                    <input type="text" id="name-<?= e((string) $category['id']) ?>" name="name"
                                           form="<?= e($formId) ?>" maxlength="180"
                                           value="<?= e((string) $category['name']) ?>" required>
                                    <input type="hidden" name="slug" form="<?= e($formId) ?>"
                                           value="<?= e((string) $category['slug']) ?>">
                                </td>

                                <td>
                                    <label class="visually-hidden" for="parent-<?= e((string) $category['id']) ?>">
                                        <?= e(__('admin.category.parent')) ?>
                                    </label>
                                    <select id="parent-<?= e((string) $category['id']) ?>" name="parent_id" form="<?= e($formId) ?>">
                                        <option value=""><?= e(__('admin.category.none_parent')) ?></option>
                                        <?php foreach ($categories as $candidate): ?>
                                            <?php if ((int) $candidate['id'] === (int) $category['id']) {
                                                continue;
                                            } ?>
                                            <option value="<?= e((string) $candidate['id']) ?>"
                                                <?= (int) ($category['parent_id'] ?? 0) === (int) $candidate['id'] ? ' selected' : '' ?>>
                                                <?= e((string) $candidate['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>

                                <td class="is-end"><?= e((string) $category['product_count']) ?></td>

                                <td>
                                    <select name="status" form="<?= e($formId) ?>" aria-label="<?= e(__('common.status')) ?>">
                                        <?php foreach ($statuses as $status): ?>
                                            <option value="<?= e($status) ?>"<?= (string) $category['status'] === $status ? ' selected' : '' ?>>
                                                <?= e($statusMap[$status] ?? $status) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>

                                <td class="is-end">
                                    <label class="visually-hidden" for="sort-<?= e((string) $category['id']) ?>">
                                        <?= e(__('admin.category.sort_order')) ?>
                                    </label>
                                    <input type="number" id="sort-<?= e((string) $category['id']) ?>" name="sort_order"
                                           form="<?= e($formId) ?>" min="0"
                                           value="<?= e((string) $category['sort_order']) ?>" style="width:5rem">
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

            <?php if ($categories === []): ?>
                <?php component('empty-state', ['title' => __('admin.empty.search')]); ?>
            <?php endif; ?>

            <?php foreach ($categories as $category): ?>
                <form method="post" id="cat-<?= e((string) $category['id']) ?>" hidden
                      action="<?= e(url('/admin/categories/' . $category['id'])) ?>">
                    <?= csrf_field() ?>
                </form>
            <?php endforeach; ?>
        </section>

        <section class="admin-panel" id="new-category">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.category.new')) ?></h2>
            </header>

            <form class="admin-filter__form" method="post" action="<?= e(url('/admin/categories')) ?>">
                <?= csrf_field() ?>

                <div class="admin-field">
                    <label for="new-name"><?= e(__('admin.category.name')) ?> *</label>
                    <input type="text" id="new-name" name="name" maxlength="180" required
                           value="<?= e((string) old('name')) ?>">
                    <?php if (has_error('name')): ?>
                        <p class="admin-field__error"><?= e((string) error_for('name')) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-field">
                    <label for="new-name-en"><?= e(__('admin.category.name_en')) ?></label>
                    <input type="text" id="new-name-en" name="name_en" maxlength="180"
                           value="<?= e((string) old('name_en')) ?>">
                </div>

                <div class="admin-field">
                    <label for="new-desc"><?= e(__('admin.category.description')) ?></label>
                    <textarea id="new-desc" name="description" rows="3"><?= e((string) old('description')) ?></textarea>
                </div>

                <div class="admin-form__grid">
                    <div class="admin-field">
                        <label for="new-parent"><?= e(__('admin.category.parent')) ?></label>
                        <select id="new-parent" name="parent_id">
                            <option value=""><?= e(__('admin.category.none_parent')) ?></option>
                            <?php foreach ($categories as $candidate): ?>
                                <option value="<?= e((string) $candidate['id']) ?>"><?= e((string) $candidate['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="admin-field">
                        <label for="new-status"><?= e(__('common.status')) ?></label>
                        <select id="new-status" name="status">
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= e($status) ?>"<?= old('status', 'active') === $status ? ' selected' : '' ?>>
                                    <?= e($statusMap[$status] ?? $status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="admin-field">
                    <label for="new-sort"><?= e(__('admin.category.sort_order')) ?></label>
                    <input type="number" id="new-sort" name="sort_order" min="0" value="<?= e((string) old('sort_order', '0')) ?>">
                </div>

                <div class="admin-filter__actions">
                    <button type="submit" class="btn btn-sm btn-light"><?= e(__('common.create')) ?></button>
                </div>
            </form>
        </section>
    </div>

    <?php if ($categories !== []): ?>
        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('common.delete')) ?></h2>
            </header>

            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th><?= e(__('admin.category.name')) ?></th>
                            <th class="is-end"><?= e(__('common.actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?= e((string) $category['name']) ?></td>
                                <td class="is-end">
                                    <form method="post" action="<?= e(url('/admin/categories/' . $category['id'] . '/delete')) ?>"
                                          data-confirm="<?= e(str_replace(':name', (string) $category['name'], __('admin.category.confirm_delete'))) ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-danger"><?= e(__('common.delete')) ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>