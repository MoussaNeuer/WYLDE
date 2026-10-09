<?php
/**
 * Toasts : messages de session rendus côté serveur.
 *
 * Aucun script inline n'est émis : la fermeture et la disparition
 * automatique sont gérées par app.js (délégation sur #toast-stack).
 * Le rendu reste donc compatible avec une CSP stricte (script-src 'self').
 *
 * Les erreurs de session posées par redirectWithErrors() ou la validation
 * (Session::pullErrors) sont affichées ici sous forme de toast d'erreur :
 * c'est leur unique point de rendu dans les pages d'administration.
 */
use App\Core\Session;

$messages = $messages ?? flash();

$errors = Session::pullErrors();

foreach ($errors as $message) {
    $messages[] = ['type' => 'error', 'message' => (string) $message];
}

if ($messages === []) {
    return;
}
?>
<?php foreach ($messages as $message): ?>
    <?php $type = $message['type'] ?? 'info'; ?>
    <div class="toast toast--<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
        <span class="toast__icon" aria-hidden="true"><?= e(['success' => '✓', 'error' => '×', 'warning' => '!', 'info' => 'i'][$type] ?? 'i') ?></span>
        <span class="toast__text"><?= e($message['message']) ?></span>
        <button class="toast__close" type="button" aria-label="<?= e(__('common.close')) ?>">×</button>
    </div>
<?php endforeach; ?>