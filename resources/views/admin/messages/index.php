<?php
/**
 * Liste des messages reçus depuis la page contact.
 *
 * @var array<int, array<string, mixed>> $rows
 * @var int    $total
 * @var int    $unread
 * @var string $status
 * @var string $query
 * @var int    $page
 * @var int    $pages
 * @var string|null $prevUrl
 * @var string|null $nextUrl
 */
component('toast');
?>
<div class="admin-page">

    <header class="admin-page__head">
        <h1 class="admin-page__title"><?= e(__('admin.messages.title')) ?></h1>
        <div class="admin-page__tools">
            <span class="pill"><?= e((string) $total) ?></span>
            <?php if ($unread > 0): ?>
                <span class="badge badge--warning"><?= e(__('admin.messages.unread_count', ['count' => $unread])) ?></span>
            <?php endif; ?>
        </div>
    </header>

    <form class="admin-filter" method="get" action="<?= e(url('/admin/messages')) ?>" role="search">
        <div class="admin-filter__form" style="grid-template-columns:1fr auto auto">
            <label class="admin-field">
                <span class="visually-hidden"><?= e(__('common.search')) ?></span>
                <input type="search" name="q" value="<?= e($query) ?>"
                       placeholder="<?= e(__('common.search')) ?>">
            </label>

            <label class="admin-field">
                <span class="visually-hidden"><?= e(__('common.status')) ?></span>
                <select name="status">
                    <option value=""><?= e(__('admin.messages.all')) ?></option>
                    <option value="new"<?= $status === 'new' ? ' selected' : '' ?>><?= e(__('admin.messages.new')) ?></option>
                    <option value="read"<?= $status === 'read' ? ' selected' : '' ?>><?= e(__('admin.messages.read')) ?></option>
                </select>
            </label>

            <div class="admin-filter__actions">
                <button type="submit" class="btn btn-sm btn-light"><?= e(__('common.search')) ?></button>
                <?php if ($query !== '' || $status !== ''): ?>
                    <a class="btn btn-sm btn-outline-light" href="<?= e(url('/admin/messages')) ?>"><?= e(__('common.reset')) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <section class="admin-panel">
        <div class="table-scroll">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th><?= e(__('admin.messages.from')) ?></th>
                        <th><?= e(__('contact.subject')) ?></th>
                        <th><?= e(__('common.email')) ?></th>
                        <th><?= e(__('common.status')) ?></th>
                        <th><?= e(__('common.date')) ?></th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $subject = (string) ($row['subject'] !== '' ? $row['subject'] : __('admin.messages.no_subject')); ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('/admin/messages/' . $row['id'])) ?>">
                                    <strong><?= e((string) $row['name']) ?></strong>
                                </a>
                            </td>
                            <td><a href="<?= e(url('/admin/messages/' . $row['id'])) ?>"><?= e($subject) ?></a></td>
                            <td><a href="mailto:<?= e((string) $row['email']) ?>"><?= e((string) $row['email']) ?></a></td>
                            <td><?php component('badge', [
                                'status' => (string) $row['status'],
                                'map'    => [
                                    'new'  => 'admin.messages.new',
                                    'read' => 'admin.messages.read',
                                ],
                            ]); ?></td>
                            <td><?= e(format_date($row['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($rows === []): ?>
            <?php component('empty-state', ['title' => __('admin.messages.empty')]); ?>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
            <footer class="admin-panel__foot">
                <?php component('pagination', [
                    'current' => $page,
                    'last'    => $pages,
                    'prevUrl' => $prevUrl,
                    'nextUrl' => $nextUrl,
                ]); ?>
            </footer>
        <?php endif; ?>
    </section>
</div>