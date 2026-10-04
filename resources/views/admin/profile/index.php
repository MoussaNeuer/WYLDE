<?php
/**
 * Profil de l'administrateur connecté.
 *
 * @var \App\Models\User|null $user
 */
component('toast');

$user = $user ?? auth();
?>
<div class="admin-page">

    <header class="admin-page__head">
        <h1 class="admin-page__title"><?= e(__('admin.profile')) ?></h1>
        <div class="admin-page__tools">
            <a class="btn btn-outline-light btn-sm"
               href="<?= e(url('/admin/security')) ?>"><?= e(__('admin.security_title')) ?></a>
        </div>
    </header>

    <form class="admin-form" method="post" action="<?= e(url('/admin/profile')) ?>">
        <?= csrf_field() ?>

        <section class="admin-panel">
            <div class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.profile')) ?></h2>
            </div>

            <div class="admin-panel__head" style="display:block;padding:1.2rem">
                <div class="admin-form__grid">
                    <div class="admin-field">
                        <label for="p-name"><?= e(__('auth.name')) ?> *</label>
                        <input type="text" id="p-name" name="name" maxlength="120" required
                               value="<?= e((string) old('name', (string) ($user?->name ?? ''))) ?>">
                        <?php if (has_error('name')): ?>
                            <p class="admin-field__error"><?= e((string) error_for('name')) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="admin-field">
                        <label for="p-email"><?= e(__('auth.email')) ?> *</label>
                        <input type="email" id="p-email" name="email" maxlength="190" required
                               value="<?= e((string) old('email', (string) ($user?->email ?? ''))) ?>">
                        <?php if (has_error('email')): ?>
                            <p class="admin-field__error"><?= e((string) error_for('email')) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="admin-field">
                        <label for="p-phone"><?= e(__('common.phone')) ?></label>
                        <input type="tel" id="p-phone" name="phone" maxlength="30"
                               value="<?= e((string) old('phone', (string) ($user?->phone ?? ''))) ?>">
                        <?php if (has_error('phone')): ?>
                            <p class="admin-field__error"><?= e((string) error_for('phone')) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <div class="admin-form__actions">
            <button type="submit" class="btn btn-light"><?= e(__('common.save')) ?></button>
        </div>
    </form>
</div>