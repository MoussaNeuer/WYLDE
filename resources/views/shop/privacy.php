<?php
/** @var string $title */
?>
<section class="section">
    <div class="container">
        <header class="page-head">
            <h1 class="page-head__title"><?= e($title) ?></h1>
        </header>

        <div class="prose">
            <p><?= e(__('page.privacy_intro')) ?></p>

            <h2><?= e(__('page.privacy_data_title')) ?></h2>
            <p><?= e(__('page.privacy_data_text')) ?></p>
            <ul>
                <li><?= e(__('page.privacy_data_identities')) ?></li>
                <li><?= e(__('page.privacy_data_contact')) ?></li>
                <li><?= e(__('page.privacy_data_orders')) ?></li>
            </ul>

            <h2><?= e(__('page.privacy_payments_title')) ?></h2>
            <p><?= e(__('page.privacy_payments_text')) ?></p>

            <h2><?= e(__('page.privacy_cookies_title')) ?></h2>
            <p><?= e(__('page.privacy_cookies_text')) ?></p>

            <h2><?= e(__('page.privacy_rights_title')) ?></h2>
            <p><?= e(__('page.privacy_rights_text')) ?></p>
        </div>
    </div>
</section>
