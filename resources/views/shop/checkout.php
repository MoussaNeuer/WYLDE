<?php
/** @var string $title
 *  @var \App\Models\Cart $cart
 *  @var array{zone:?\App\Models\ShippingZone, available:bool, price:int, label:string} $quote
 *  @var array<string,string> $countries
 *  @var bool $whatsappEnabled
 */
$items  = $cart->items();
$subtotal = $cart->total();
$total    = $subtotal + ($quote['available'] ? $quote['price'] : 0);
$shippingProgress = free_shipping_progress($subtotal);

// Wave n'est proposé que si un lien Wave Business est renseigné.
// L'option reste masquée plutôt que cochée par défaut : proposer un
// moyen de paiement qui ne fonctionne pas ferait perdre la commande.
$waveAvailable = \App\Services\SettingsService::hasWaveLink();
?>
<section class="section">
    <div class="container">
        <header class="page-head">
            <h1 class="page-head__title"><?= e($title) ?></h1>
            <p class="page-head__text"><?= e(__('checkout.subtitle')) ?></p>
        </header>

        <form method="post" action="<?= e(url('/checkout')) ?>" class="checkout-form" novalidate
              data-checkout-step="1">
            <?= csrf_field() ?>

            <?php /* Trois étapes : Contact → Livraison → Paiement. Les
                     champs restent dans le même formulaire, donc le
                     navigateur valide tout avant l'envoi ; JavaScript ne
                     fait que guider l'affichage. Sans lui, les trois
                     groupes sont simplement visibles d'affilée. */ ?>
            <ol class="steps" data-steps aria-label="<?= e(__('checkout.steps_label')) ?>">
                <li class="steps__item is-current" data-step-indicator="1">
                    <span class="steps__num">1</span>
                    <span class="steps__label"><?= e(__('checkout.step_contact')) ?></span>
                </li>
                <li class="steps__item" data-step-indicator="2">
                    <span class="steps__num">2</span>
                    <span class="steps__label"><?= e(__('checkout.step_delivery')) ?></span>
                </li>
                <li class="steps__item" data-step-indicator="3">
                    <span class="steps__num">3</span>
                    <span class="steps__label"><?= e(__('checkout.step_payment')) ?></span>
                </li>
            </ol>

            <div class="grid-2">
                <div class="checkout-fields">
                    <fieldset class="fieldset" data-step-panel="1">
                        <legend><?= e(__('checkout.contact_legend')) ?></legend>

                        <div class="form-group">
                            <label class="form-label" for="co-name"><?= e(__('checkout.full_name')) ?></label>
                            <input class="form-control"
                                   type="text"
                                   id="co-name"
                                   name="name"
                                   value="<?= e(old('name')) ?>"
                                   autocomplete="name"
                                   required
                                   <?= error_for('name') !== null ? 'aria-invalid="true"' : '' ?>>
                            <?php if ($error = error_for('name')): ?>
                                <p class="form-error"><?= e($error) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="co-email"><?= e(__('checkout.email')) ?></label>
                            <input class="form-control"
                                   type="email"
                                   id="co-email"
                                   name="email"
                                   value="<?= e(old('email')) ?>"
                                   autocomplete="email"
                                   required
                                   <?= error_for('email') !== null ? 'aria-invalid="true"' : '' ?>>
                            <?php if ($error = error_for('email')): ?>
                                <p class="form-error"><?= e($error) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="co-phone"><?= e(__('checkout.phone')) ?></label>
                            <input class="form-control"
                                   type="tel"
                                   id="co-phone"
                                   name="phone"
                                   value="<?= e(old('phone')) ?>"
                                   autocomplete="tel"
                                   placeholder="+221 77 000 00 00"
                                   required
                                   <?= error_for('phone') !== null ? 'aria-invalid="true"' : '' ?>>
                            <?php if ($error = error_for('phone')): ?>
                                <p class="form-error"><?= e($error) ?></p>
                            <?php endif; ?>
                        </div>
                    </fieldset>

                    <fieldset class="fieldset" data-step-panel="2">
                        <legend><?= e(__('checkout.shipping_legend')) ?></legend>

                        <div class="form-group">
                            <label class="form-label" for="co-address"><?= e(__('checkout.address')) ?></label>
                            <input class="form-control"
                                   type="text"
                                   id="co-address"
                                   name="address"
                                   value="<?= e(old('address')) ?>"
                                   autocomplete="street-address"
                                   required
                                   <?= error_for('address') !== null ? 'aria-invalid="true"' : '' ?>>
                            <?php if ($error = error_for('address')): ?>
                                <p class="form-error"><?= e($error) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="co-city"><?= e(__('checkout.city')) ?></label>
                            <input class="form-control"
                                   type="text"
                                   id="co-city"
                                   name="city"
                                   value="<?= e(old('city')) ?>"
                                   autocomplete="address-level2"
                                   <?= error_for('city') !== null ? 'aria-invalid="true"' : '' ?>>
                            <?php if ($error = error_for('city')): ?>
                                <p class="form-error"><?= e($error) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="co-country"><?= e(__('checkout.country')) ?></label>
                            <select class="form-select"
                                    id="co-country"
                                    name="country_code"
                                    data-shipping-country
                                    required
                                    <?= error_for('country_code') !== null ? 'aria-invalid="true"' : '' ?>>
                                <option value=""><?= e(__('checkout.select_country')) ?></option>
                                <?php foreach ($countries as $code => $name): ?>
                                    <option value="<?= e($code) ?>"
                                        <?= old('country_code') === $code ? 'selected' : '' ?>>
                                        <?= e($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($error = error_for('country_code')): ?>
                                <p class="form-error"><?= e($error) ?></p>
                            <?php endif; ?>
                            <?php if ($error = error_for('zone')): ?>
                                <p class="form-error"><?= e($error) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="co-notes"><?= e(__('checkout.notes')) ?></label>
                            <textarea class="form-control" id="co-notes" name="notes" rows="3"><?= e(old('notes')) ?></textarea>
                        </div>
                    </fieldset>

                    <fieldset class="fieldset" data-step-panel="3">
                        <legend><?= e(__('checkout.payment_legend')) ?></legend>

<div class="form-group">
                                <label class="form-check payment-option">
                                    <input class="form-check-input" type="radio" name="payment" value="cod" checked
                                           data-payment-option>
                                    <span>
                                        <strong><?= e(__('checkout.payment_cod')) ?></strong>
                                        <small><?= e(__('checkout.payment_cod_desc')) ?></small>
                                    </span>
                                </label>
                            </div>

<?php if ($waveAvailable): ?>
                            <div class="form-group">
                                <label class="form-check payment-option">
                                    <input class="form-check-input" type="radio" name="payment" value="wave"
                                           data-payment-option>
                                    <span>
                                        <strong><?= e(__('checkout.payment_wave')) ?></strong>
                                        <small><?= e(__('checkout.payment_wave_desc')) ?></small>
                                    </span>
                                </label>
                            </div>
                        <?php endif; ?>

                        <?php if ($error = error_for('payment')): ?>
                            <p class="form-error"><?= e($error) ?></p>
                        <?php endif; ?>
                    </fieldset>

                    <?php if ($whatsappEnabled): ?>
                        <?php /* Le canal de confirmation partage l'étape 3 avec le
                                 paiement : ce sont les deux derniers choix avant
                                 de valider. */ ?>
                        <fieldset class="fieldset" data-step-panel="3" data-channel-fieldset>
                            <legend><?= e(__('checkout.channel_legend')) ?></legend>

                            <div class="form-group">
                                <label class="form-check payment-option">
                                    <input class="form-check-input" type="radio" name="channel" value="site" checked
                                           data-channel-option>
                                    <span>
                                        <strong><?= e(__('checkout.channel_site')) ?></strong>
                                        <small><?= e(__('checkout.channel_site_desc')) ?></small>
                                    </span>
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="form-check payment-option payment-option--wa">
                                    <input class="form-check-input" type="radio" name="channel" value="whatsapp"
                                           data-channel-option
                                           <?= old('channel') === 'whatsapp' ? 'checked' : '' ?>>
                                    <span>
                                        <strong>
                                            <svg class="payment-option__wa" width="16" height="16" viewBox="0 0 24 24"
                                                 fill="currentColor" aria-hidden="true">
                                                <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.74 1.21h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.05 2m0 18.15h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.23 8.25-8.23 2.2 0 4.27.86 5.83 2.42a8.18 8.18 0 0 1 2.41 5.82c0 4.54-3.7 8.23-8.24 8.23m4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.78.97-.15.16-.29.18-.53.06-.25-.12-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.01-.38.11-.5.11-.11.25-.29.37-.44.13-.15.17-.25.25-.42.09-.16.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.43h-.47c-.17 0-.43.06-.66.31-.22.24-.86.85-.86 2.07s.89 2.4 1.01 2.56c.12.17 1.74 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.47-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.15-1.18-.06-.11-.22-.17-.47-.29"/>
                                            </svg>
                                            <?= e(__('checkout.channel_whatsapp')) ?>
                                        </strong>
                                        <small><?= e(__('checkout.channel_whatsapp_desc')) ?></small>
                                    </span>
                                </label>
                            </div>

                            <?php if ($error = error_for('channel')): ?>
                                <p class="form-error"><?= e($error) ?></p>
                            <?php endif; ?>
                        </fieldset>
                    <?php endif; ?>

                    <?php /* Navigation des étapes. Les boutons n'ont pas
                             type="submit" : changer d'étape ne doit jamais
                             envoyer le formulaire. */ ?>
                    <div class="checkout-nav">
                        <button class="btn btn-ghost checkout-nav__back"
                                type="button" data-step-prev hidden>
                            <?= e(__('checkout.step_back')) ?>
                        </button>
                        <button class="btn btn-light checkout-nav__next"
                                type="button" data-step-next hidden>
                            <?= e(__('checkout.step_next')) ?>
                        </button>
                    </div>
                </div>

                <aside class="checkout-summary" data-checkout-summary>
                    <?php /* Sur téléphone, ce bandeau est replié : le client
                             voit le total sans que le détail occupe tout
                             l'écran. Sur grand écran, il reste masqué. */ ?>
                    <button class="checkout-summary__toggle" type="button"
                            aria-expanded="false" data-summary-toggle>
                        <span><?= e(__('checkout.summary')) ?></span>
                        <span class="amount" data-summary-total><?= money($total) ?></span>
                    </button>

                    <div class="summary">
                        <ul class="summary__items">
                            <?php foreach ($items as $item): ?>
                                <li class="summary-item">
                                    <?php if ($item['image_path'] !== null): ?>
                                        <a class="summary-item__thumb"
                                           href="<?= e(url('/product/' . $item['product_slug'])) ?>"
                                           aria-hidden="true" tabindex="-1">
                                            <img src="<?= e(upload_url((string) $item['image_path'])) ?>"
                                                 alt="" width="56" height="70"
                                                 loading="lazy" decoding="async">
                                        </a>
                                    <?php else: ?>
                                        <span class="summary-item__thumb summary-item__thumb--blank"></span>
                                    <?php endif; ?>

                                    <div class="summary-item__main">
                                        <a class="summary-item__name"
                                           href="<?= e(url('/product/' . $item['product_slug'])) ?>">
                                            <?= e($item['name']) ?>
                                        </a>
                                        <p class="summary-item__meta">
                                            <?php if (!empty($item['size'])): ?>
                                                <span class="summary-item__size"><?= e(size_label((string) $item['size'])) ?></span>
                                            <?php endif; ?>
                                            <span>× <?= e((string) $item['quantity']) ?></span>
                                        </p>
                                    </div>

                                    <span class="amount summary-item__total"><?= money($item['quantity'] * $item['price']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="summary__row">
                            <span><?= e(__('checkout.subtotal')) ?></span>
                            <span class="amount"><?= money($subtotal) ?></span>
                        </div>

                        <?php if ($shippingProgress['enabled']): ?>
                            <div class="freeship<?= $shippingProgress['reached'] ? ' freeship--reached' : '' ?>"
                                 data-checkout-freeship>
                                <p class="freeship__text" data-checkout-freeship-text>
                                    <?= e($shippingProgress['reached']
                                        ? __('cart.free_shipping.reached')
                                        : __('cart.free_shipping.remaining', [
                                            'amount' => $shippingProgress['remaining_text'],
                                        ])) ?>
                                </p>
                                <div class="freeship__track" role="progressbar"
                                     aria-valuemin="0" aria-valuemax="100"
                                     aria-valuenow="<?= e((string) $shippingProgress['percent']) ?>"
                                     data-checkout-freeship-bar>
                                    <span class="freeship__fill"
                                          style="width: <?= e((string) $shippingProgress['percent']) ?>%"></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="summary__row">
                            <span><?= e(__('checkout.shipping')) ?></span>
                            <span class="amount" data-shipping-label>
                                <?php if (!$quote['available']): ?>
                                    <?= e(__('checkout.shipping_check')) ?>
                                <?php elseif ($shippingProgress['reached']): ?>
                                    <?= e(__('cart.shipping_free')) ?>
                                <?php else: ?>
                                    <?= money($quote['price']) ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="summary__row summary__row--total">
                            <span><?= e(__('checkout.total')) ?></span>
                            <span class="amount" data-total><?= money($total) ?></span>
                        </div>

                        <?php if (!$quote['available']): ?>
                            <p class="checkout-summary__hint"><?= e(__('checkout.zone_unavailable')) ?></p>
                        <?php endif; ?>
                    </div>

                    <button class="btn btn-light btn-block btn-lg"
                            type="submit"
                            data-checkout-submit
                            data-label-site="<?= e(__('checkout.place_order')) ?>"
                            data-label-whatsapp="<?= e(__('checkout.whatsapp_cta')) ?>"
                            data-label-cod="<?= e(__('checkout.submit_cod')) ?>"
                            data-label-wave="<?= e(__('checkout.submit_wave')) ?>">
                        <?php /* Le bouton nomme le moyen de paiement choisi :
                                 « Valider la commande » seul ne dit pas au
                                 client s'il va payer Wave ou à la livraison. */ ?>
                        <span data-checkout-submit-label><?= e(__('checkout.submit_cod')) ?></span>
                    </button>

                    <p class="checkout-summary__foot"><?= e(__('checkout.confirm_terms')) ?></p>
                </aside>
            </div>
        </form>
    </div>
</section>

<?php App\Core\View::start('scripts'); ?>
<script src="<?= e(asset('assets/js/checkout.js')) ?>" defer></script>
<?php App\Core\View::stop(); ?>
