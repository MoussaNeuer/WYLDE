<?php
/**
 * Page d'erreur (404, 403, 429, 500).
 *
 * @var int         $status
 * @var string      $message
 * @var string|null $details  Trace complète, uniquement en APP_DEBUG
 */
$status   = $status ?? 500;
$message  = $message ?? __('errors.server');
$details  = $details ?? null;
$headings = [
    403 => __('errors.forbidden'),
    404 => __('errors.not_found_heading'),
    419 => __('errors.csrf'),
    429 => __('errors.too_many_requests'),
];
$heading = $headings[$status] ?? __('errors.title');
?>
<section class="error-page">
    <div class="container">
        <p class="error-page__code"><?= e((string) $status) ?></p>
        <h1 class="error-page__title"><?= e($heading) ?></h1>
        <p class="error-page__text"><?= e($message) ?></p>

        <div class="error-page__actions">
            <a href="<?= e(url('/')) ?>" class="btn btn-light"><?= e(__('errors.go_home')) ?></a>
            <a href="<?= e(url('/shop')) ?>" class="btn btn-outline-light"><?= e(__('nav.shop')) ?></a>
        </div>

        <?php if ($details !== null): ?>
            <details class="error-page__details">
                <summary>Détail technique (développement uniquement)</summary>
                <pre><?= e($details) ?></pre>
            </details>
        <?php endif; ?>
    </div>
</section>
