<?php
/**
 * Modale Bootstrap réutilisée par le back-office.
 *
 * @var string      $id
 * @var string      $title
 * @var string|null $footer
 * @var bool        $static Empêche la fermeture au clic sur le fond
 * @var string      $size   sm | lg | xl
 */
$id     = $id ?? 'adminModal';
$title  = $title ?? '';
$footer = $footer ?? null;
$static = $static ?? false;
$size   = $size ?? '';
?>
<div class="modal fade" id="<?= e($id) ?>" tabindex="-1"
     aria-labelledby="<?= e($id) ?>-label" aria-hidden="true"
     <?= $static ? 'data-bs-backdrop="static"' : '' ?>>
    <div class="modal-dialog<?= $size !== '' ? ' modal-' . e($size) : '' ?>">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="<?= e($id) ?>-label"><?= e($title) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="<?= e(__('common.close_modal')) ?>"></button>
            </div>

            <div class="modal-body">
                <?= $content ?? '' ?>
            </div>

            <?php if ($footer !== null): ?>
                <div class="modal-footer"><?= $footer ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>