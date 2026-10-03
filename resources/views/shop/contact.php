<?php
/** @var string $title */
?>
<section class="section">
    <div class="container">
        <header class="page-head">
            <h1 class="page-head__title"><?= e($title) ?></h1>
            <p class="page-head__text"><?= e(__('contact.subtitle')) ?></p>
        </header>

        <div class="grid-2">
            <div>
                <p><?= e(__('contact.response_delay')) ?></p>

                <dl class="contact-details">
                    <dt><?= e(__('contact.email')) ?></dt>
                    <dd><?= e(__('settings.email')) ?></dd>
                </dl>
            </div>

            <form method="post"
                  action="<?= e(url('/contact')) ?>"
                  class="card-surface__body"
                  novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="c-name"><?= e(__('contact.name')) ?></label>
                    <input class="form-control"
                           type="text"
                           id="c-name"
                           name="name"
                           value="<?= e(old('name')) ?>"
                           required
                           <?= error_for('name') !== null ? 'aria-invalid="true"' : '' ?>>
                    <?php if ($error = error_for('name')): ?>
                        <p class="form-error"><?= e($error) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="c-email"><?= e(__('contact.email')) ?></label>
                    <input class="form-control"
                           type="email"
                           id="c-email"
                           name="email"
                           value="<?= e(old('email')) ?>"
                           required
                           <?= error_for('email') !== null ? 'aria-invalid="true"' : '' ?>>
                    <?php if ($error = error_for('email')): ?>
                        <p class="form-error"><?= e($error) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="c-subject"><?= e(__('contact.subject')) ?></label>
                    <input class="form-control"
                           type="text"
                           id="c-subject"
                           name="subject"
                           value="<?= e(old('subject')) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="c-message"><?= e(__('contact.message')) ?></label>
                    <textarea class="form-control"
                              id="c-message"
                              name="message"
                              rows="6"
                              required
                              <?= error_for('message') !== null ? 'aria-invalid="true"' : '' ?>
                    ><?= e(old('message')) ?></textarea>
                    <?php if ($error = error_for('message')): ?>
                        <p class="form-error"><?= e($error) ?></p>
                    <?php endif; ?>
                </div>

                <button class="btn btn-light btn-block" type="submit">
                    <?= e(__('contact.send')) ?>
                </button>
            </form>
        </div>
    </div>
</section>
