<?php
/**
 * Connexion au back-office.
 *
 * Formulaire piloté par CSS (libellés flottants, halo de focus) et
 * enrichi par assets/js/admin-login.js : affichage du mot de passe,
 * detection de la touche Verr. Maj, retour visuel d'erreur.
 *
 * @var string $title
 */
$emailError    = error_for('email');
$passwordError = error_for('password');
$hasError      = $emailError !== null || $passwordError !== null;

$icon = static function (string $name): string {
    $paths = [
        'mail'  => '<rect x="2.5" y="4.5" width="19" height="15" rx="3"/><path d="m3 7 8.2 5.6a1.4 1.4 0 0 0 1.6 0L21 7"/>',
        'lock'  => '<rect x="4" y="10" width="16" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 14.5v2.5"/>',
        'eye'   => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3.2"/>',
        'eyeoff'=> '<path d="M4 4l16 16"/><path d="M9.6 5.9A9.6 9.6 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-3 3.8"/><path d="M6.6 7.8A17.6 17.6 0 0 0 2.5 12S6 18.5 12 18.5c1.2 0 2.3-.2 3.3-.6"/><path d="M10.2 10.3a2.5 2.5 0 0 0 3.4 3.5"/>',
        'arrow' => '<path d="M4 12h15"/><path d="m13 6 6 6-6 6"/>',
        'shield'=> '<path d="M12 2.8 4.5 6v6c0 4.6 3.1 8.3 7.5 9.4 4.4-1.1 7.5-4.8 7.5-9.4V6L12 2.8Z"/><path d="m9 12 2.2 2.2L15.4 10"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 2"/>',
        'key'   => '<circle cx="8" cy="12" r="4"/><path d="M12 12h9"/><path d="M18 12v3"/><path d="M15.5 12v2.5"/>',
    ];

    return '<svg class="alx-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . ($paths[$name] ?? '') . '</svg>';
};
?>

<div class="alx__brand">
    <span class="alx__logo">
        <img src="<?= e(asset('assets/images/logo/logo-white.svg')) ?>"
             alt="<?= e(config('app.name', 'WYLDE')) ?>" width="96" height="38">
    </span>
    <span class="alx__badge">
        <span class="alx__badge-dot" aria-hidden="true"></span>
        <?= e(__('admin.login_badge')) ?>
    </span>
</div>

<h1 class="alx__title"><?= e($title) ?></h1>
<p class="alx__subtitle"><?= e(__('admin.login_subtitle')) ?></p>

<?php foreach (\App\Core\Session::pullFlash() as $flash): ?>
    <div class="alert alert--<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
<?php endforeach; ?>

<form class="alx-form" method="post" action="<?= e(url('/admin/login')) ?>" novalidate>
    <?= csrf_field() ?>

    <div class="alx-field<?= $emailError !== null ? ' is-error' : '' ?>" data-alx-field>
        <?= $icon('mail') ?>
        <input class="alx-input"
               type="email"
               id="admin-email"
               name="email"
               placeholder=" "
               value="<?= e(old('email')) ?>"
               autocomplete="username"
               inputmode="email"
               autocapitalize="off"
               autocorrect="off"
               spellcheck="false"
               required
               autofocus
               <?= $emailError !== null ? 'aria-invalid="true" aria-describedby="admin-email-error"' : '' ?>>
        <label class="alx-label" for="admin-email"><?= e(__('auth.email')) ?></label>
        <?php if ($emailError !== null): ?>
            <p class="alx-error" id="admin-email-error"><?= e($emailError) ?></p>
        <?php endif; ?>
    </div>

    <div class="alx-field<?= $passwordError !== null ? ' is-error' : '' ?>" data-alx-field>
        <?= $icon('lock') ?>
        <input class="alx-input"
               type="password"
               id="admin-password"
               name="password"
               placeholder=" "
               autocomplete="current-password"
               required
               <?= $passwordError !== null ? 'aria-invalid="true" aria-describedby="admin-password-error"' : '' ?>>
        <label class="alx-label" for="admin-password"><?= e(__('auth.password')) ?></label>
        <button class="alx-eye" type="button" data-alx-toggle-password
                aria-label="<?= e(__('admin.login_show_password')) ?>"
                aria-pressed="false">
            <?= $icon('eye') ?>
        </button>
        <p class="alx-caps" data-alx-caps hidden><?= e(__('admin.login_caps')) ?></p>
        <?php if ($passwordError !== null): ?>
            <p class="alx-error" id="admin-password-error"><?= e($passwordError) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($hasError): ?>
        <p class="alx-alert" role="alert"><?= e(__('auth.failed')) ?></p>
    <?php endif; ?>

    <button class="alx-submit" type="submit" data-alx-submit>
        <span class="alx-submit__label"><?= e(__('admin.login_submit')) ?></span>
        <?= $icon('arrow') ?>
        <span class="alx-submit__wave" aria-hidden="true"></span>
    </button>

    <ul class="alx-trust">
        <li><?= $icon('shield') ?><span><?= e(__('admin.login_trust_staff')) ?></span></li>
        <li><?= $icon('clock') ?><span><?= e(__('admin.login_trust_log')) ?></span></li>
        <li><?= $icon('key') ?><span><?= e(__('admin.login_trust_password')) ?></span></li>
    </ul>
</form>
