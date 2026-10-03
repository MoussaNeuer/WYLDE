<?php
/**
 * Carte d'indicateur pour le tableau de bord et l'analytics.
 *
 * @var string      $label
 * @var string      $value
 * @var string|null $hint
 * @var string|null $trend   'up' | 'down' | null
 * @var string      $tone    up | down | flat (couleur de la valeur)
 * @var string|null $url     Rend la carte cliquable
 */
$label  = $label ?? '';
$value  = $value ?? '';
$hint   = $hint ?? null;
$trend  = $trend ?? null;
$url    = $url ?? null;
$tag    = $tag ?? 'div';
?>
<<?= e($tag) ?> class="stat-card stat-card--<?= e($tone ?? 'flat') ?><?= $url !== null ? ' stat-card--link' : '' ?>">
    <?php if ($url !== null): ?>
        <a href="<?= e($url) ?>">
    <?php endif; ?>

    <p class="stat-card__label"><?= e($label) ?></p>
    <p class="stat-card__value"><?= e($value) ?></p>

    <?php if ($hint !== null): ?>
        <p class="stat-card__hint<?= $trend !== null ? ' is-' . e($trend) : '' ?>">
            <?php if ($trend === 'up'): ?><span aria-hidden="true">↑</span>
            <?php elseif ($trend === 'down'): ?><span aria-hidden="true">↓</span><?php endif; ?>
            <?= e($hint) ?>
        </p>
    <?php endif; ?>

    <?php if ($url !== null): ?>
        </a>
    <?php endif; ?>
</<?= e($tag) ?>>