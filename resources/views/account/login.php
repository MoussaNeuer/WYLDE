<?php
/** @var string $title */
?>
<div class="auth-head">
    <h1 class="auth-head__title"><?= e($title) ?></h1>
</div>

<?php foreach (\App\Core\Session::pullFlash() as $flash): ?>
    <div class="alert alert--<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
<?php endforeach; ?>

<form method="post" action="<?= e(url('/login')) ?>" novalidate>
    <?= csrf_field() ?>

    <div class="form-group">
        <label class="form-label" for="login-email"><?= e(__('auth.email')) ?></label>
        <input class="form-control"
               type="email"
               id="login-email"
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
        <label class="form-label" for="login-password"><?= e(__('auth.password')) ?></label>
        <input class="form-control"
               type="password"
               id="login-password"
               name="password"
               autocomplete="current-password"
               required
               <?= error_for('password') !== null ? 'aria-invalid="true"' : '' ?>>
        <?php if ($error = error_for('password')): ?>
            <p class="form-error"><?= e($error) ?></p>
        <?php endif; ?>
    </div>

    <div class="form-check">
        <input class="form-check-input"
               type="checkbox"
               id="login-remember"
               name="remember"
               value="1"
               <?= old('remember', '0') === '1' ? 'checked' : '' ?>>
        <label class="form-check-label" for="login-remember"><?= e(__('auth.remember')) ?></label>
    </div>

    <button class="btn btn-light btn-block" type="submit">
        <?= e(__('auth.submit_login')) ?>
    </button>
</form>

<p class="auth-alt">
    <?= e(__('auth.no_account')) ?>
    <a href="<?= e(url('/register')) ?>"><?= e(__('auth.register_title')) ?></a>
</p>