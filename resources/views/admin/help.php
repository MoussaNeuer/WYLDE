<?php

/**
 * Aide du back-office : démarrage rapide, raccourcis clavier, dépannage.
 *
 * @var string $title
 * @var array<int, array{title: string, text: string}> $steps
 * @var array<int, array{keys: string, action: string}> $shortcuts
 * @var array<int, array{q: string, a: string}> $topics
 */
?>

<header class="admin-page__head">
    <div>
        <h1 class="admin-page__title"><?= e($title) ?></h1>
        <p class="admin-page__lead"><?= e(__('admin.help.subtitle')) ?></p>
    </div>
    <div class="admin-page__tools">
        <a class="btn btn-sm btn-light" href="<?= e(url('/admin')) ?>"><?= e(__('admin.dashboard')) ?></a>
    </div>
</header>

<div class="admin-cols">

    <section class="admin-panel" id="shortcuts">
        <header class="admin-panel__head">
            <h2 class="admin-panel__title"><?= e(__('admin.help.shortcuts_title')) ?></h2>
        </header>

        <ul class="admin-list kbd-list">
            <?php foreach ($shortcuts as $shortcut): ?>
                <li>
                    <span class="kbd-list__action"><?= e($shortcut['action']) ?></span>
                    <span class="kbd-list__keys">
                        <?php foreach (preg_split('/\s*\+\s*/', $shortcut['keys']) as $key): ?>
                            <kbd><?= e($key) ?></kbd>
                        <?php endforeach; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>

        <footer class="admin-panel__foot">
            <p class="admin-field__hint"><?= e(__('admin.help.shortcuts_hint')) ?></p>
        </footer>
    </section>

    <section class="admin-panel">
        <header class="admin-panel__head">
            <h2 class="admin-panel__title"><?= e(__('admin.help.steps_title')) ?></h2>
        </header>

        <ol class="steps">
            <?php foreach ($steps as $index => $step): ?>
                <li class="steps__item">
                    <span class="steps__num" aria-hidden="true"><?= $index + 1 ?></span>
                    <div>
                        <p class="steps__title"><?= e($step['title']) ?></p>
                        <p class="steps__text"><?= e($step['text']) ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
</div>

<section class="admin-panel">
    <header class="admin-panel__head">
        <h2 class="admin-panel__title"><?= e(__('admin.help.topics_title')) ?></h2>
    </header>

    <div class="faq" data-accordion>
        <?php foreach ($topics as $topic): ?>
            <details class="faq__item">
                <summary class="faq__q"><?= e($topic['q']) ?></summary>
                <div class="faq__a"><?= e($topic['a']) ?></div>
            </details>
        <?php endforeach; ?>
    </div>

    <footer class="admin-panel__foot">
        <a class="btn btn-sm btn-light" href="<?= e(url('/admin/settings')) ?>"><?= e(__('admin.settings')) ?></a>
        <a class="btn btn-sm btn-outline-light" href="<?= e(url('/admin/security')) ?>"><?= e(__('admin.security_title')) ?></a>
    </footer>
</section>
