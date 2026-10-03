<?php
/** @var string $title */
?>
<div class="auth-head">
    <h1 class="auth-head__title"><?= e($title) ?></h1>
</div>

<?php foreach (\App\Core\Session::pullFlash() as $flash): ?>
    <div class="alert alert--<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
<?php endforeach; ?>

<form method="post" action="<?= e(url('/register')) ?>" novalidate>
    <?= csrf_field() ?>

    <div class="form-group">
        <label class="form-label" for="reg-name"><?= e(__('auth.name')) ?></label>
        <input class="form-control"
               type="text"
               id="reg-name"
               name="name"
               value="<?= e(old('name')) ?>"
               autocomplete="name"
               autofocus
               required
               <?= error_for('name') !== null ? 'aria-invalid="true"' : '' ?>>
        <?php if ($error = error_for('name')): ?>
            <p class="form-error"><?= e($error) ?></p>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label class="form-label" for="reg-email"><?= e(__('auth.email')) ?></label>
        <input class="form-control"
               type="email"
               id="reg-email"
               name="email"
               value="<?= e(old('email')) ?>"
               autocomplete="email"
               required
               <?= error_for('email') !== null ? 'aria-invalid="true"' : '' ?>>
        <?php if ($error = error_for('email')): ?>
            <p class="form-error"><?= e($error) ?></p>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label class="form-label" for="reg-password"><?= e(__('auth.password')) ?></label>
        <input class="form-control"
               type="password"
               id="reg-password"
               name="password"
               autocomplete="new-password"
               minlength="8"
               required
               <?= error_for('password') !== null ? 'aria-invalid="true"' : '' ?>>
        <p class="form-help"><?= e(__('auth.password_help')) ?></p>
        <?php if ($error = error_for('password')): ?>
            <p class="form-error"><?= e($error) ?></p>
        <?php endif; ?>
    </div>

    <button class="btn btn-light btn-block" type="submit">
        <?= e(__('auth.submit_register')) ?>
    </button>
</form>

<p class="auth-alt">
    <?= e(__('auth.has_account')) ?>
    <a href="<?= e(url('/login')) ?>"><?= e(__('auth.login_title')) ?></a>
</p>