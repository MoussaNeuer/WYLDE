<?php

/**
 * Notifications du back-office.
 *
 * Chaque alerte est déduite de l'état réel de la boutique : une pastille
 * n'apparaît que s'il y a vraiment quelque chose à traiter, et le détail
 * des lignes est affiché sous le compteur.
 *
 * @var string $title
 * @var array<int, array<string, mixed>> $alerts
 * @var array<int, array<string, mixed>> $sections
 */
$icon = static function (string $name): string {
    $paths = [
        'alert'   => '<path d="M12 3.5 2.8 19a1 1 0 0 0 .9 1.5h16.6a1 1 0 0 0 .9-1.5L12 3.5Z"/><path d="M12 10v4"/><path d="M12 17.4h.01"/>',
        'box'     => '<path d="M3 7.5 12 3l9 4.5-9 4.5-9-4.5Z"/><path d="M3 7.5V16l9 4.5 9-4.5V7.5"/>',
        'cart'    => '<path d="M3 4h2.2l2.3 11.2A2 2 0 0 0 9.5 17h8a2 2 0 0 0 2-1.6L21 8H6"/><circle cx="10" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/>',
        'coin'    => '<circle cx="12" cy="12" r="8"/><path d="M12 8v8M9.5 10.2h4a1.8 1.8 0 0 1 0 3.6h-3a1.8 1.8 0 0 0 0 3.6h4"/>',
        'tag'     => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9-9-9Z"/><path d="M7.5 7.5h.01"/>',
        'check'   => '<circle cx="12" cy="12" r="8.5"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
    ];

    return '<svg class="admin-panel__glyph" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . ($paths[$name] ?? '') . '</svg>';
};

$glyphs = ['out_of_stock' => 'box', 'orders' => 'cart', 'unpaid' => 'coin', 'low_stock' => 'box', 'drafts' => 'tag'];
?>

<header class="admin-page__head">
    <div>
        <h1 class="admin-page__title"><?= e($title) ?></h1>
        <p class="admin-page__lead"><?= e(__('admin.notifications.subtitle')) ?></p>
    </div>
    <div class="admin-page__tools">
        <a class="btn btn-sm btn-light" href="<?= e(url('/admin/help')) ?>"><?= e(__('admin.help.title')) ?></a>
    </div>
</header>

<?php if ($alerts === []): ?>

    <section class="admin-panel">
        <div class="empty-state">
            <?= $icon('check') ?>
            <p class="empty-state__title"><?= e(__('admin.notifications.all_clear')) ?></p>
            <p class="empty-state__text"><?= e(__('admin.notifications.all_clear_hint')) ?></p>
        </div>
    </section>

<?php else: ?>

    <div class="note-stack">
        <?php foreach ($sections as $section): ?>
            <?php $alert = $section['alert']; ?>
            <section class="admin-panel note note--<?= e((string) $alert['level']) ?>" id="note-<?= e((string) $alert['type']) ?>">
                <header class="admin-panel__head">
                    <?= $icon($glyphs[(string) $alert['type']] ?? 'alert') ?>
                    <div class="note__head">
                        <h2 class="admin-panel__title"><?= e((string) $alert['label']) ?></h2>
                        <p class="note__hint"><?= e((string) $alert['hint']) ?></p>
                    </div>
                    <span class="note__count"><?= (int) $alert['count'] ?></span>
                </header>

                <?php if ($section['items'] === []): ?>
                    <p class="admin-list__empty"><?= e(__('admin.notifications.nothing_here')) ?></p>
                <?php else: ?>
                    <ul class="admin-list note__items">
                        <?php foreach ($section['items'] as $item): ?>
                            <li>
                                <?php if (is_array($item)): ?>
                                    <?php /* Variante de stock : retour SQL direct. */ ?>
                                    <a href="<?= e(url('/admin/products/' . (int) $item['product_id'] . '/edit')) ?>">
                                        <span class="note__item-main">
                                            <strong><?= e((string) $item['name']) ?></strong>
                                            <small><?= e((string) $item['size']) ?> · <?= e((string) $item['sku']) ?></small>
                                        </span>
                                        <span class="note__item-meta<?= (int) $item['stock'] <= 0 ? ' is-danger' : ' is-warning' ?>">
                                            <?= (int) $item['stock'] ?> · <?= e(__('admin.product.stock')) ?>
                                        </span>
                                    </a>
                                <?php elseif (isset($item->reference)): ?>
                                    <?php /* Commande : modèle. */ ?>
                                    <a href="<?= e(url('/admin/orders/' . $item->id())) ?>">
                                        <span class="note__item-main">
                                            <strong>#<?= e($item->reference) ?></strong>
                                            <small>
                                                <?= e((string) ($item->shipping_first_name ?? '')) ?>
                                                <?= e(format_date((string) $item->created_at, 'd/m/Y')) ?>
                                            </small>
                                        </span>
                                        <span class="note__item-meta">
                                            <?= e(money($item->total)) ?>
                                        </span>
                                    </a>
                                <?php else: ?>
                                    <?php /* Produit : modèle. */ ?>
                                    <a href="<?= e(url('/admin/products/' . $item->id() . '/edit')) ?>">
                                        <span class="note__item-main">
                                            <strong><?= e($item->name) ?></strong>
                                            <small><?= e(__('admin.product_status.' . (string) $item->status)) ?></small>
                                        </span>
                                        <span class="note__item-meta is-muted"><?= e(__('common.edit')) ?></span>
                                    </a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <footer class="admin-panel__foot">
                    <a class="btn btn-sm btn-light" href="<?= e(url((string) $alert['url'])) ?>">
                        <?= e(__('admin.notifications.handle')) ?>
                    </a>
                </footer>
            </section>
        <?php endforeach; ?>
    </div>

<?php endif; ?>
