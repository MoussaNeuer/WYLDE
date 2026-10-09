<?php
/**
 * Détail d'un message reçu depuis la page contact.
 *
 * L'ouverture de cette page marque le message comme lu.
 *
 * @var \App\Models\ContactMessage $message
 */
component('toast');

$subject = (string) ($message->subject !== '' && $message->subject !== null
    ? $message->subject
    : __('admin.messages.no_subject'));
?>
<div class="admin-page">

    <a class="admin-page__back" href="<?= e(url('/admin/messages')) ?>">← <?= e(__('admin.messages.title')) ?></a>

    <header class="admin-page__head">
        <h1 class="admin-page__title"><?= e($subject) ?></h1>
        <div class="admin-page__tools">
            <a class="btn btn-outline-light btn-sm"
               href="mailto:<?= e((string) $message->email) ?>?subject=Re: <?= e(rawurlencode($subject)) ?>">
                <?= e(__('admin.messages.reply')) ?>
            </a>
        </div>
    </header>

    <div class="admin-cols">
        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.messages.details')) ?></h2>
            </header>

            <dl class="admin-panel__head" style="display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;padding:1.2rem">
                <div>
                    <dt><?= e(__('admin.messages.from')) ?></dt>
                    <dd><?= e((string) $message->name) ?></dd>
                </div>
                <div>
                    <dt><?= e(__('common.email')) ?></dt>
                    <dd><a href="mailto:<?= e((string) $message->email) ?>"><?= e((string) $message->email) ?></a></dd>
                </div>
                <div>
                    <dt><?= e(__('common.date')) ?></dt>
                    <dd><?= e(format_date($message->created_at)) ?></dd>
                </div>
                <div>
                    <dt><?= e(__('common.status')) ?></dt>
                    <dd><?php component('badge', [
                        'status' => (string) $message->status,
                        'map'    => [
                            'new'  => 'admin.messages.new',
                            'read' => 'admin.messages.read',
                        ],
                    ]); ?></dd>
                </div>
            </dl>
        </section>

        <section class="admin-panel">
            <header class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('contact.message')) ?></h2>
            </header>

            <div style="padding:1.2rem;white-space:pre-wrap;word-break:break-word">
                <?= e((string) $message->message) ?>
            </div>

            <footer class="admin-panel__foot">
                <form method="post"
                      action="<?= e(url('/admin/messages/' . $message->id() . '/delete')) ?>"
                      data-confirm="<?= e(__('admin.messages.confirm_delete')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-danger"><?= e(__('common.delete')) ?></button>
                </form>
            </footer>
        </section>
    </div>
</div>