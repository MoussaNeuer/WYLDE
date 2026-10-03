<?php
/**
 * Pagination.
 *
 * @var int         $current Page courante (1-based)
 * @var int         $last    Dernière page
 * @var string|null $prevUrl URL page précédente
 * @var string|null $nextUrl URL page suivante
 * @var int         $window  Nombre de pages à montrer de chaque côté
 */
$current = $current ?? 1;
$last    = $last    ?? 1;
$prevUrl = $prevUrl ?? null;
$nextUrl = $nextUrl ?? null;
$window  = $window  ?? 2;

if ($last < 2) {
    return;
}

$start = max(1, $current - $window);
$end   = min($last, $current + $window);
?>
<nav aria-label="<?= e(__('common.pagination')) ?>">
    <ul class="pagination">
        <li class="<?= $current > 1 ? '' : 'is-disabled' ?>">
            <?php if ($current > 1 && $prevUrl !== null): ?>
                <a href="<?= e($prevUrl) ?>" rel="prev" aria-label="<?= e(__('common.previous')) ?>"><?= e(__('common.previous')) ?></a>
            <?php else: ?>
                <span aria-hidden="true"><?= e(__('common.previous')) ?></span>
            <?php endif; ?>
        </li>

        <?php if ($start > 1): ?>
            <li><a href="<?= e(pagination_url(1)) ?>">1</a></li>
            <?php if ($start > 2): ?>
                <li class="is-disabled"><span aria-hidden="true">…</span></li>
            <?php endif; ?>
        <?php endif; ?>

        <?php for ($p = $start; $p <= $end; $p++): ?>
            <li class="<?= $p === $current ? 'is-current' : '' ?>">
                <?php if ($p === $current): ?>
                    <span aria-current="page"><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= e(pagination_url($p)) ?>"
                       aria-label="<?= e(sprintf(__('common.page_number'), $p)) ?>"><?= $p ?></a>
                <?php endif; ?>
            </li>
        <?php endfor; ?>

        <?php if ($end < $last): ?>
            <?php if ($end < $last - 1): ?>
                <li class="is-disabled"><span aria-hidden="true">…</span></li>
            <?php endif; ?>
            <li><a href="<?= e(pagination_url($last)) ?>"><?= $last ?></a></li>
        <?php endif; ?>

        <li class="<?= $current < $last ? '' : 'is-disabled' ?>">
            <?php if ($current < $last && $nextUrl !== null): ?>
                <a href="<?= e($nextUrl) ?>" rel="next" aria-label="<?= e(__('common.next')) ?>"><?= e(__('common.next')) ?></a>
            <?php else: ?>
                <span aria-hidden="true"><?= e(__('common.next')) ?></span>
            <?php endif; ?>
        </li>
    </ul>
</nav>
