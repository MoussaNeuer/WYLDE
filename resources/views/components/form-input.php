<?php
/**
 * Champ de formulaire réutilisable.
 *
 * @var string      $name
 * @var string|null $label
 * @var string|null $type
 * @var string|null $value
 * @var array<string,string> $attributes
 * @var string|null $help
 * @var string|null $error
 * @var bool        $required
 * @var bool        $autofocus
 */
$name         = $name         ?? '';
$type         = $type         ?? 'text';
$label        = $label        ?? null;
$value        = $value        ?? old($name);
$attributes   = $attributes   ?? [];
$help         = $help         ?? null;
$error        = $error        ?? error_for($name);
$required     = $required     ?? false;
$autofocus    = $autofocus    ?? false;
$inputId      = $inputId      ?? 'f-' . $name;
$isTextarea   = $type === 'textarea';
$isSelect     = $type === 'select';
$options      = $options      ?? [];
?>
<div class="form-group">
    <?php if ($label !== null): ?>
        <label class="form-label" for="<?= e($inputId) ?>"><?= e($label) ?></label>
    <?php endif; ?>

    <?php if ($isTextarea): ?>
        <textarea class="form-control"
                  id="<?= e($inputId) ?>"
                  name="<?= e($name) ?>"
                  <?= $required ? 'required' : '' ?>
                  <?= $autofocus ? 'autofocus' : '' ?>
                  <?= $error !== null ? 'aria-invalid="true"' : '' ?>
                  <?= $error !== null ? 'aria-describedby="' . e($inputId) . '-error"' : '' ?>
                  <?php foreach ($attributes as $attr => $attrValue): ?>
                  <?= e($attr) ?>="<?= e($attrValue) ?>"
                  <?php endforeach; ?>
        ><?= e($value) ?></textarea>

    <?php elseif ($isSelect): ?>
        <select class="form-select"
                id="<?= e($inputId) ?>"
                name="<?= e($name) ?>"
                <?= $required ? 'required' : '' ?>
                <?= $autofocus ? 'autofocus' : '' ?>
                <?= $error !== null ? 'aria-invalid="true"' : '' ?>
                <?php foreach ($attributes as $attr => $attrValue): ?>
                <?= e($attr) ?>="<?= e($attrValue) ?>"
                <?php endforeach; ?>
        >
            <?php foreach ($options as $optionValue => $optionLabel): ?>
                <option value="<?= e($optionValue) ?>"
                    <?= (string) $optionValue === (string) $value ? 'selected' : '' ?>>
                    <?= e($optionLabel) ?>
                </option>
            <?php endforeach; ?>
        </select>

    <?php else: ?>
        <input class="form-control"
               type="<?= e($type) ?>"
               id="<?= e($inputId) ?>"
               name="<?= e($name) ?>"
               value="<?= e($value) ?>"
               <?= $required ? 'required' : '' ?>
               <?= $autofocus ? 'autofocus' : '' ?>
               <?= $error !== null ? 'aria-invalid="true"' : '' ?>
               <?= $error !== null ? 'aria-describedby="' . e($inputId) . '-error"' : '' ?>
               <?php foreach ($attributes as $attr => $attrValue): ?>
               <?= e($attr) ?>="<?= e($attrValue) ?>"
               <?php endforeach; ?>
        >
    <?php endif; ?>

    <?php if ($help !== null): ?>
        <p class="form-help" id="<?= e($inputId) ?>-help"><?= e($help) ?></p>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <p class="form-error" id="<?= e($inputId) ?>-error"><?= e($error) ?></p>
    <?php endif; ?>
</div>
