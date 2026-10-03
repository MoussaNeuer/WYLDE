<?php
/**
 * Toasts : messages de session rendus côté serveur.
 *
 * Le conteneur #toast-stack est déjà présent dans les layouts ; ce
 * composant injecte les messages flash au chargement et admin.js se
 * charge de les retirer. Les réponses AJAX utilisent la même API
 * (window.WyldeToast).
 */
$messages = $messages ?? flash();

if ($messages === []) {
    return;
}
?>
<script data-toast-flush>
    (function () {
        var messages = <?= json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

        window.addEventListener('DOMContentLoaded', function () {
            messages.forEach(function (message) {
                if (window.WyldeToast) {
                    window.WyldeToast.push(message.type, message.message);
                }
            });
        });
    })();
</script>