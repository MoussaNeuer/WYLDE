<?php
/**
 * État vide : aucun produit, aucune commande, aucun client (§16).
 *
 * @var string      $title
 * @var string|null $text
 * @var string|null $action
 * @var string|null $actionLabel
 */
$title       = $title ?? __('admin.empty.search');
$text        = $text ?? null;
$action      = $action ?? null;
$actionLabel = $actionLabel ?? null;
?>
<div class="empty-state">
    <p class="empty-state__title"><?= e($title) ?></p>

    <?php if ($text !== null): ?>
        <p class="empty-state__text"><?= e($text) ?></p>
    <?php endif; ?>

    <?php if ($action !== null && $actionLabel !== null): ?>
        <a href="<?= e($action) ?>" class="btn btn-outline-light"><?= e($actionLabel) ?></a>
    <?php endif; ?>
</div>
