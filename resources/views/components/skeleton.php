<?php
/**
 * Squelette de chargement.
 *
 * Masqué par défaut et révélé par admin.js via [data-skeleton] dès que
 * la requête qui l'a déclenché est terminée : l'utilisateur voit la
 * structure de la page au lieu d'un écran vide.
 *
 * @var string|null $label
 * @var int         $lines
 * @var string      $class
 */
$label = $label ?? null;
$lines = max(1, min(8, (int) ($lines ?? 3)));
$class = $class ?? '';
?>
<div class="skeleton<?= $class !== '' ? ' ' . e($class) : '' ?>" data-skeleton <?= $label !== null ? 'aria-label="' . e($label) . '"' : '' ?>>
    <?php if ($label !== null): ?>
        <p class="skeleton__label"><?= e($label) ?></p>
    <?php endif; ?>

    <?php for ($i = 0; $i < $lines; $i++): ?>
        <span class="skeleton__line" style="--skeleton-delay: <?= (int) ($i * 90) ?>ms"></span>
    <?php endfor; ?>
</div>