<?php
/**
 * Sécurité : changement de mot de passe, révocation des sessions et
 * journal d'activité.
 *
 * @var \App\Models\User|null $user
 * @var array<int, string>   $actions
 * @var array<int, \App\Models\AuditLog> $logs
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 */
use App\Models\AuditLog;

component('toast');

$user = $user ?? auth();

/** Traduit une action brute, ou la rend lisible si inconnue. */
$label = static function (string $action): string {
    $known = __('admin.audit.' . $action);
    $key   = 'admin.audit.' . $action;

    return $known === $key ? $action : $known;
};
?>
<div class="admin-page">

    <header class="admin-page__head">
        <h1 class="admin-page__title"><?= e(__('admin.security_title')) ?></h1>
        <div class="admin-page__tools">
            <a class="btn btn-outline-light btn-sm"
               href="<?= e(url('/admin/profile')) ?>"><?= e(__('admin.profile')) ?></a>
        </div>
    </header>

    <div class="admin-cols">
        <section class="admin-panel" id="password">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.security_title')) ?></h2>
            </header>

            <form class="admin-filter__form" method="post" action="<?= e(url('/admin/security/password')) ?>">
                <?= csrf_field() ?>

                <?php if (has_error('current_password')): ?>
                    <p class="admin-field__error"><?= e((string) error_for('current_password')) ?></p>
                <?php endif; ?>

                <div class="admin-field">
                    <label for="s-current"><?= e(__('admin.security.current_password')) ?> *</label>
                    <input type="password" id="s-current" name="current_password" required autocomplete="current-password">
                </div>

                <div class="admin-field">
                    <label for="s-new"><?= e(__('auth.password')) ?> *</label>
                    <input type="password" id="s-new" name="password" required autocomplete="new-password">
                    <p class="admin-field__hint">
                        <?= e(__('auth.password_help')) ?>
                    </p>
                    <?php if (has_error('password')): ?>
                        <p class="admin-field__error"><?= e((string) error_for('password')) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-field">
                    <label for="s-confirm"><?= e(__('auth.password_confirm')) ?> *</label>
                    <input type="password" id="s-confirm" name="password_confirmation" required autocomplete="new-password">
                    <?php if (has_error('password_confirmation')): ?>
                        <p class="admin-field__error"><?= e((string) error_for('password_confirmation')) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-filter__actions">
                    <button type="submit" class="btn btn-sm btn-light"><?= e(__('common.update')) ?></button>
                </div>
            </form>
        </section>

        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.security.sessions')) ?></h2>
            </header>

            <div class="admin-panel__head" style="display:block;padding:1.2rem">
                <p class="admin-field__hint"><?= e(__('admin.security.sessions_hint')) ?></p>

                <form method="post" action="<?= e(url('/admin/security/sessions/revoke')) ?>"
                      data-confirm="<?= e(__('common.confirm')) ?>" style="margin-top:1rem">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-light">
                        <?= e(__('admin.security.revoke')) ?>
                    </button>
                </form>
            </div>
        </section>
    </div>

    <section class="admin-panel">
        <header class="admin-panel__head">
            <h2 class="admin-panel__title"><?= e(__('admin.audit.title')) ?></h2>
            <div class="admin-panel__actions">
                <span class="pill"><?= e((string) $total) ?></span>
            </div>
        </header>

        <p class="admin-field__hint" style="padding:0.8rem 1.2rem;margin:0">
            <?= e(__('admin.audit.subtitle')) ?>
        </p>

        <div class="table-scroll">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th><?= e(__('admin.audit.action')) ?></th>
                        <th><?= e(__('admin.audit.entity')) ?></th>
                        <th><?= e(__('admin.audit.author')) ?></th>
                        <th><?= e(__('admin.audit.ip')) ?></th>
                        <th><?= e(__('admin.audit.when')) ?></th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <span class="audit-action is-<?= e((string) $log->action) ?>">
                                    <?= e($label((string) $log->action)) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($log->entity === null): ?>
                                    —
                                <?php else: ?>
                                    <?= e((string) $log->entity) ?><?= $log->entity_id !== null ? ' #' . e((string) $log->entity_id) : '' ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $author = $log->user(); ?>
                                <?= e($author !== null ? (string) $author->name : __('admin.audit.guest')) ?>
                            </td>
                            <td>
                                <?php if ($log->ip_hash === null): ?>
                                    —
                                <?php else: ?>
                                    <span class="audit-meta"><?= e(substr((string) $log->ip_hash, 0, 12)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= e(format_date($log->created_at, 'd/m/Y H:i')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($logs === []): ?>
            <?php component('empty-state', ['title' => __('admin.audit.empty')]); ?>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
            <footer class="admin-panel__foot">
                <?php component('pagination', [
                    'current' => $page,
                    'last'    => $pages,
                    'prevUrl' => $page > 1 ? pagination_url($page - 1) : null,
                    'nextUrl' => $page < $pages ? pagination_url($page + 1) : null,
                ]); ?>
            </footer>
        <?php endif; ?>
    </section>
</div>