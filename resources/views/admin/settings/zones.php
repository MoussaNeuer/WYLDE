<?php
/**
 * Zones de livraison : tarifs par ville, pays ou zone de repli.
 *
 * Rappel du schéma : country_code et city sont NULLABLES, status est un
 * ENUM('active','inactive') et une seule ligne peut porter is_default = 1.
 *
 * @var array<int, array<string, mixed>> $zones
 * @var array<string, string> $countries
 */
component('toast');
?>
<div class="admin-page">

    <a class="admin-page__back" href="<?= e(url('/admin/settings')) ?>">← <?= e(__('admin.settings')) ?></a>

    <header class="admin-page__head">
        <h1 class="admin-page__title"><?= e(__('admin.shipping.title')) ?></h1>
    </header>

    <p class="admin-field__hint"><?= e(__('admin.shipping.resolution_hint')) ?></p>

    <div class="admin-cols">
        <section class="admin-panel">
            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th><?= e(__('admin.shipping.label')) ?></th>
                            <th><?= e(__('admin.shipping.country')) ?></th>
                            <th><?= e(__('admin.shipping.city')) ?></th>
                            <th class="is-end"><?= e(__('admin.shipping.price')) ?></th>
                            <th><?= e(__('common.status')) ?></th>
                            <th><?= e(__('admin.shipping.is_default')) ?></th>
                            <th class="is-end"><?= e(__('admin.kpi.orders')) ?></th>
                            <th class="is-end"><?= e(__('common.actions')) ?></th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($zones as $zone): ?>
                            <?php $formId = 'zone-' . (int) $zone['id']; ?>
                            <tr>
                                <td>
                                    <label class="visually-hidden" for="z-label-<?= e((string) $zone['id']) ?>">
                                        <?= e(__('admin.shipping.label')) ?>
                                    </label>
                                    <input type="text" id="z-label-<?= e((string) $zone['id']) ?>" name="label"
                                           form="<?= e($formId) ?>" maxlength="120"
                                           value="<?= e((string) $zone['label']) ?>" required>
                                </td>

                                <td>
                                    <label class="visually-hidden" for="z-country-<?= e((string) $zone['id']) ?>">
                                        <?= e(__('admin.shipping.country')) ?>
                                    </label>
                                    <select id="z-country-<?= e((string) $zone['id']) ?>" name="country_code"
                                            form="<?= e($formId) ?>">
                                        <option value=""><?= e(__('common.none')) ?></option>
                                        <?php foreach ($countries as $code => $name): ?>
                                            <option value="<?= e($code) ?>"<?= (string) $zone['country_code'] === $code ? ' selected' : '' ?>>
                                                <?= e($name) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>

                                <td>
                                    <label class="visually-hidden" for="z-city-<?= e((string) $zone['id']) ?>">
                                        <?= e(__('admin.shipping.city')) ?>
                                    </label>
                                    <input type="text" id="z-city-<?= e((string) $zone['id']) ?>" name="city"
                                           form="<?= e($formId) ?>" maxlength="120"
                                           placeholder="<?= e(__('admin.shipping.city_hint')) ?>"
                                           value="<?= e((string) ($zone['city'] ?? '')) ?>">
                                </td>

                                <td class="is-end">
                                    <label class="visually-hidden" for="z-price-<?= e((string) $zone['id']) ?>">
                                        <?= e(__('admin.shipping.price')) ?>
                                    </label>
                                    <input type="number" id="z-price-<?= e((string) $zone['id']) ?>" name="price"
                                           form="<?= e($formId) ?>" min="0" step="100"
                                           value="<?= e((string) $zone['price']) ?>" style="width:6rem">
                                </td>

                                <td>
                                    <select name="active" form="<?= e($formId) ?>" aria-label="<?= e(__('common.status')) ?>">
                                        <option value="1"<?= (string) $zone['status'] === 'active' ? ' selected' : '' ?>>
                                            <?= e(__('admin.category.active')) ?>
                                        </option>
                                        <option value="0"<?= (string) $zone['status'] !== 'active' ? ' selected' : '' ?>>
                                            <?= e(__('admin.category.inactive')) ?>
                                        </option>
                                    </select>
                                </td>

                                <td>
                                    <input type="hidden" name="is_default" value="0" form="<?= e($formId) ?>">
                                    <input type="checkbox" name="is_default" value="1" form="<?= e($formId) ?>"
                                           aria-label="<?= e(__('admin.shipping.is_default')) ?>"
                                           <?= (int) $zone['is_default'] === 1 ? ' checked' : '' ?>>
                                </td>

                                <td class="is-end"><?= e((string) $zone['order_count']) ?></td>

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

            <?php if ($zones === []): ?>
                <?php component('empty-state', ['title' => __('admin.empty.search')]); ?>
            <?php endif; ?>

            <?php foreach ($zones as $zone): ?>
                <form method="post" id="zone-<?= e((string) $zone['id']) ?>" hidden
                      action="<?= e(url('/admin/shipping-zones/' . $zone['id'])) ?>">
                    <?= csrf_field() ?>
                </form>
            <?php endforeach; ?>
        </section>

        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.shipping.zone')) ?></h2>
            </header>

            <form class="admin-filter__form" method="post" action="<?= e(url('/admin/shipping-zones')) ?>">
                <?= csrf_field() ?>

                <?php if (has_error('label')): ?>
                    <p class="admin-field__error"><?= e((string) error_for('label')) ?></p>
                <?php endif; ?>

                <div class="admin-field">
                    <label for="nz-label"><?= e(__('admin.shipping.label')) ?> *</label>
                    <input type="text" id="nz-label" name="label" maxlength="120" required
                           value="<?= e((string) old('label')) ?>">
                </div>

                <div class="admin-field">
                    <label for="nz-country"><?= e(__('admin.shipping.country')) ?></label>
                    <select id="nz-country" name="country_code">
                        <option value=""><?= e(__('common.none')) ?></option>
                        <?php foreach ($countries as $code => $name): ?>
                            <option value="<?= e($code) ?>"><?= e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (has_error('country_code')): ?>
                        <p class="admin-field__error"><?= e((string) error_for('country_code')) ?></p>
                    <?php endif; ?>
                    <p class="admin-field__hint"><?= e(__('admin.shipping.city_hint')) ?></p>
                </div>

                <div class="admin-field">
                    <label for="nz-city"><?= e(__('admin.shipping.city')) ?></label>
                    <input type="text" id="nz-city" name="city" maxlength="120" value="<?= e((string) old('city')) ?>">
                </div>

                <div class="admin-field">
                    <label for="nz-price"><?= e(__('admin.shipping.price')) ?></label>
                    <input type="number" id="nz-price" name="price" min="0" step="100" value="<?= e((string) old('price', '0')) ?>">
                    <?php if (has_error('price')): ?>
                        <p class="admin-field__error"><?= e((string) error_for('price')) ?></p>
                    <?php endif; ?>
                </div>

                <label class="admin-check">
                    <input type="checkbox" name="is_default" value="1">
                    <?= e(__('admin.shipping.is_default')) ?>
                </label>
                <p class="admin-field__hint"><?= e(__('admin.shipping.is_default_hint')) ?></p>

                <div class="admin-filter__actions">
                    <button type="submit" class="btn btn-sm btn-light"><?= e(__('common.create')) ?></button>
                </div>
            </form>
        </section>
    </div>

    <?php if ($zones !== []): ?>
        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('common.delete')) ?></h2>
            </header>

            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th><?= e(__('admin.shipping.label')) ?></th>
                            <th class="is-end"><?= e(__('admin.kpi.orders')) ?></th>
                            <th class="is-end"><?= e(__('common.actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($zones as $zone): ?>
                            <tr>
                                <td><?= e((string) $zone['label']) ?></td>
                                <td class="is-end"><?= e((string) $zone['order_count']) ?></td>
                                <td class="is-end">
                                    <form method="post"
                                          action="<?= e(url('/admin/shipping-zones/' . $zone['id'] . '/delete')) ?>"
                                          data-confirm="<?= e(__('common.confirm')) ?>">
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