<?php
/** @var string $title */
?>
<section class="section">
    <div class="container">
        <header class="page-head">
            <h1 class="page-head__title"><?= e($title) ?></h1>
        </header>

        <div class="prose">
            <p><?= e(__('page.about_intro')) ?></p>

            <h2><?= e(__('page.about_identity_title')) ?></h2>
            <p><?= e(__('page.about_identity_text')) ?></p>

            <h2><?= e(__('page.about_values_title')) ?></h2>
            <ul>
                <li><?= e(__('page.about_value_quality')) ?></li>
                <li><?= e(__('page.about_value_quantities')) ?></li>
                <li><?= e(__('page.about_value_transparency')) ?></li>
            </ul>

            <h2><?= e(__('page.about_contact_title')) ?></h2>
            <p><?= e(__('page.about_contact_text')) ?></p>
            <p><a class="link-more" href="<?= e(url('/contact')) ?>"><?= e(__('nav.contact')) ?></a></p>
        </div>
    </div>
</section>
