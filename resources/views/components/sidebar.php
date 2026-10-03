<?php
/**
 * Barre de filtres latérale du back-office.
 *
 * @var string $title
 * @var string $action  URL de soumission du filtre
 * @var bool   $compact Empile les champs en une colonne
 */
$title   = $title ?? __('common.filter');
$action  = $action ?? '';
$compact = $compact ?? false;
?>
<aside class="admin-filter<?= $compact ? ' is-compact' : '' ?>">
    <form class="admin-filter__form" method="get" action="<?= e($action) ?>" role="search">
        <?= $content ?? '' ?>

        <div class="admin-filter__actions">
            <button type="submit" class="btn btn-sm btn-light"><?= e(__('common.apply')) ?></button>
            <a href="<?= e($action) ?>" class="btn btn-sm btn-outline-light"><?= e(__('common.reset')) ?></a>
        </div>
    </form>
</aside>