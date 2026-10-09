<?php
/**
 * Paramètres de la boutique.
 *
 * @var array<string, string> $keys
 * @var array<string, string> $values
 */
component('toast');

$emails = ['shop_email'];
$urls   = ['wave_payment_link', 'credit_url'];
$phones = ['shop_phone', 'whatsapp_number'];
$bools  = ['whatsapp_enabled'];
$texts  = ['whatsapp_message'];
$ints   = ['free_shipping_threshold'];
$hints  = [
    'credit_name'       => 'Affiché dans le pied de page et sur les pages de connexion',
    'credit_url'        => 'Laisser vide pour afficher le nom sans lien',
    'whatsapp_number'   => 'Format international, c\'est le numéro qui reçoit les commandes. Les espaces et tirets sont ignorés. Exemple : +221 77 785 12 23',
    'whatsapp_enabled'  => 'Le client valide sa commande sur le site, puis est redirigé vers une conversation wa.me avec le récapitulatif pré-rempli. C\'est lui qui appuie sur « Envoyer ».',
    'whatsapp_message'  => 'Facultatif : une ou deux lignes ajoutées au début du message WhatsApp.',
    'free_shipping_threshold' => 'Montant du panier (en XOF, hors frais de port) à partir duquel les frais de livraison tombent à zéro. Laisser vide ou 0 pour proposer toujours les frais de port.',
];
?>
<div class="admin-page">

    <header class="admin-page__head">
        <h1 class="admin-page__title"><?= e(__('admin.settings')) ?></h1>
        <div class="admin-page__tools">
            <a class="btn btn-outline-light btn-sm"
               href="<?= e(url('/admin/shipping-zones')) ?>"><?= e(__('admin.shipping.title')) ?></a>
            <a class="btn btn-outline-light btn-sm" href="<?= e(url('/admin/sizes')) ?>">
                <?= e(__('admin.sizes.title')) ?>
                <span class="badge badge--default"><?= e((string) ($sizes_total ?? 0)) ?></span>
            </a>
        </div>
    </header>

    <p class="admin-field__hint"><?= e(__('admin.sizes.settings_hint')) ?></p>

    <form class="admin-form" method="post" action="<?= e(url('/admin/settings')) ?>">
        <?= csrf_field() ?>

        <section class="admin-panel">
            <div class="admin-panel__head">
                <h2 class="admin-panel__title"><?= e(__('admin.settings')) ?></h2>
            </div>

            <div class="admin-panel__head" style="display:block;padding:1.2rem">
                <div class="admin-form__grid">
                    <?php foreach ($keys as $key => $label): ?>
                        <div class="admin-field<?= in_array($key, $bools, true) ? ' admin-field--check' : '' ?>">
                            <?php if (in_array($key, $bools, true)): ?>
                                <label class="admin-check" for="s-<?= e($key) ?>">
                                    <input type="checkbox" id="s-<?= e($key) ?>" name="<?= e($key) ?>" value="1"
                                           <?= ($values[$key] ?? '') === '1' ? 'checked' : '' ?>>
                                    <span><?= e($label) ?></span>
                                </label>
                            <?php else: ?>
                                <label for="s-<?= e($key) ?>"><?= e($label) ?></label>
                            <?php endif; ?>

                            <?php if (in_array($key, $bools, true)): ?>
                                <?php if (isset($hints[$key])): ?>
                                    <p class="admin-field__hint"><?= e($hints[$key]) ?></p>
                                <?php endif; ?>
                            <?php elseif (in_array($key, $emails, true)): ?>
                                <input type="email" id="s-<?= e($key) ?>" name="<?= e($key) ?>"
                                       value="<?= e($values[$key] ?? '') ?>">
                            <?php elseif (in_array($key, $phones, true)): ?>
                                <input type="tel" id="s-<?= e($key) ?>" name="<?= e($key) ?>"
                                       value="<?= e($values[$key] ?? '') ?>">
                            <?php elseif (in_array($key, $urls, true)): ?>
                                <input type="url" id="s-<?= e($key) ?>" name="<?= e($key) ?>"
                                       placeholder="https://"
                                       value="<?= e($values[$key] ?? '') ?>">
                                <p class="admin-field__hint"><?= e($hints[$key] ?? 'https:// obligatoire') ?></p>
                            <?php elseif (in_array($key, $texts, true)): ?>
                                <textarea id="s-<?= e($key) ?>" name="<?= e($key) ?>"
                                          rows="2"><?= e($values[$key] ?? '') ?></textarea>
                                <?php if (isset($hints[$key])): ?>
                                    <p class="admin-field__hint"><?= e($hints[$key]) ?></p>
                                <?php endif; ?>
                            <?php elseif (in_array($key, $ints, true)): ?>
                                <input type="number" id="s-<?= e($key) ?>" name="<?= e($key) ?>"
                                       inputmode="numeric" min="0" step="1"
                                       value="<?= (int) ($values[$key] ?? 0) > 0 ? e((string) (int) $values[$key]) : '' ?>">
                                <?php if (isset($hints[$key])): ?>
                                    <p class="admin-field__hint"><?= e($hints[$key]) ?></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <input type="text" id="s-<?= e($key) ?>" name="<?= e($key) ?>"
                                       value="<?= e($values[$key] ?? '') ?>">
                                <?php if (isset($hints[$key])): ?>
                                    <p class="admin-field__hint"><?= e($hints[$key]) ?></p>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($key === 'wave_payment_link' && ($values[$key] ?? '') === ''): ?>
                                    <?php /* Rappel visible : sans lien, le choix Wave est
                                             masqué à la caisse et aucun client ne peut
                                             payer par Wave. */ ?>
                                    <p class="admin-alert admin-alert--warning">
                                        <?= e(__('admin.settings.wave_missing')) ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (has_error($key)): ?>
                                <p class="admin-field__error"><?= e((string) error_for($key)) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <div class="admin-form__actions">
            <button type="submit" class="btn btn-light"><?= e(__('common.save')) ?></button>
        </div>
    </form>
</div>