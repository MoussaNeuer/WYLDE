<?php
/** @var string $title */
?>
<div class="auth-head">
    <h1 class="auth-head__title"><?= e($title) ?></h1>
    <p class="auth-head__text"><?= e(__('admin.login_subtitle')) ?></p>
</div>

<?php foreach (\App\Core\Session::pullFlash() as $flash): ?>
    <div class="alert alert--<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
<?php endforeach; ?>

<form method="post" action="<?= e(url('/admin/login')) ?>" novalidate>
    <?= csrf_field() ?>

    <div class="form-group">
        <label class="form-label" for="admin-email"><?= e(__('auth.email')) ?></label>
        <input class="form-control"
               type="email"
               id="admin-email"
               name="email"
               value="<?= e(old('email')) ?>"
               autocomplete="email"
               autofocus
               required
               <?= error_for('email') !== null ? 'aria-invalid="true"' : '' ?>>
        <?php if ($error = error_for('email')): ?>
            <p class="form-error"><?= e($error) ?></p>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label class="form-label" for="admin-password"><?= e(__('auth.password')) ?></label>
        <input class="form-control"
               type="password"
               id="admin-password"
               name="password"
               autocomplete="current-password"
               required
               <?= error_for('password') !== null ? 'aria-invalid="true"' : '' ?>>
        <?php if ($error = error_for('password')): ?>
            <p class="form-error"><?= e($error) ?></p>
        <?php endif; ?>
    </div>

    <button class="btn btn-light btn-block" type="submit">
        <?= e(__('admin.login_submit')) ?>
    </button>
</form>