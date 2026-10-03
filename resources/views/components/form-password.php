<?php
/**
 * Champ mot de passe avec affichage/masquage.
 *
 * @var string      $name
 * @var string|null $label
 * @var string|null $value
 * @var bool        $required
 * @var bool        $autofocus
 * @var bool        $reveal
 */
$name      = $name      ?? 'password';
$label     = $label     ?? __('auth.password');
$value     = $value     ?? '';
$required  = $required  ?? true;
$autofocus = $autofocus ?? false;
$reveal    = $reveal    ?? true;
$inputId   = $inputId   ?? 'f-' . $name;
$error     = $error     ?? error_for($name);
?>
<div class="form-group">
    <label class="form-label" for="<?= e($inputId) ?>"><?= e($label) ?></label>

    <div class="password-field">
        <input class="form-control"
               type="password"
               id="<?= e($inputId) ?>"
               name="<?= e($name) ?>"
               value="<?= e($value) ?>"
               autocomplete="<?= $name === 'password' ? 'current-password' : 'new-password' ?>"
               <?= $required ? 'required' : '' ?>
               <?= $autofocus ? 'autofocus' : '' ?>
               <?= $error !== null ? 'aria-invalid="true"' : '' ?>>

        <?php if ($reveal): ?>
            <button class="password-field__toggle"
                    type="button"
                    data-password-toggle="<?= e($inputId) ?>"
                    aria-label="<?= e(__('auth.show_password')) ?>">
                <span data-password-label-show><?= e(__('auth.show')) ?></span>
            </button>
        <?php endif; ?>
    </div>

    <?php if ($error !== null): ?>
        <p class="form-error"><?= e($error) ?></p>
    <?php endif; ?>
</div>
