<?php
/**
 * Tableau d'administration : en-tête, actions, corps, pied (pagination).
 *
 * Le composant ne connaît pas le SQL : il reçoit les lignes déjà
 * transformées par le contrôleur et se contente de les habiller.
 *
 * @var string   $title
 * @var string   $head       HTML des <th> (une ligne)
 * @var callable $row        fn(array $row, int $index): string
 * @var array    $rows
 * @var string|null $actions HTML du bandeau d'actions
 * @var string|null $footer  HTML du pied (pagination)
 * @var bool        $selectable Ajoute une colonne de cases à cocher
 * @var string      $emptyTitle
 * @var string|null $emptyText
 * @var string|null $emptyAction
 * @var string|null $emptyActionLabel
 */
$title    = $title ?? __('admin.dashboard');
$head     = $head ?? '';
$row      = $row ?? null;
$rows     = $rows ?? [];
$actions  = $actions ?? null;
$footer   = $footer ?? null;
$selectable = $selectable ?? false;
$bulkForm  = $bulkForm ?? null;
?>
<section class="admin-panel">
    <?php if ($actions !== null || $title !== ''): ?>
        <header class="admin-panel__head">
            <h2 class="admin-panel__title"><?= e($title) ?></h2>
            <div class="admin-panel__actions"><?= $actions ?></div>
        </header>
    <?php endif; ?>

    <?php if ($rows === []): ?>
        <?php component('empty-state', [
            'title'       => $emptyTitle ?? __('admin.empty.search'),
            'text'        => $emptyText ?? null,
            'action'      => $emptyAction ?? null,
            'actionLabel' => $emptyActionLabel ?? null,
        ]); ?>
    <?php else: ?>
        <form method="post"<?= $bulkForm !== null ? ' action="' . e($bulkForm) . '"' : '' ?> data-bulk-form>
            <?php if ($bulkForm !== null): ?>
                <?= csrf_field() ?>
            <?php endif; ?>

            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <?php if ($selectable): ?>
                                <th class="admin-table__check">
                                    <input type="checkbox" data-check-all aria-label="<?= e(__('common.all')) ?>">
                                </th>
                            <?php endif; ?>
                            <?= $head ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $index => $item): ?>
                            <tr>
                                <?php if ($selectable): ?>
                                    <td class="admin-table__check">
                                        <input type="checkbox" name="ids[]" value="<?= e((string) $item->id) ?>"
                                               data-check-row aria-label="<?= e(__('common.all')) ?>">
                                    </td>
                                <?php endif; ?>
                                <?= $row($item, $index) ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($selectable && $bulkForm !== null): ?>
                <div class="admin-bulkbar" data-bulkbar hidden>
                    <span data-bulk-count>0</span>
                    <button type="submit" name="action" value="publish" class="btn btn-sm btn-outline-light"><?= e(__('admin.quick_actions.publish')) ?></button>
                    <button type="submit" name="action" value="archive" class="btn btn-sm btn-outline-light"><?= e(__('admin.quick_actions.archive')) ?></button>
                    <button type="submit" name="action" value="feature" class="btn btn-sm btn-outline-light"><?= e(__('admin.quick_actions.feature')) ?></button>
                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger"
                            data-confirm="<?= e(__('common.confirm')) ?>"><?= e(__('common.delete')) ?></button>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>

    <?php if ($footer !== null): ?>
        <footer class="admin-panel__foot"><?= $footer ?></footer>
    <?php endif; ?>
</section>