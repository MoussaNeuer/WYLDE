<?php
/** @var string $title
 *  @var \App\Models\Cart $cart
 *  @var array{zone:?\App\Models\ShippingZone, available:bool, price:int, label:string} $quote
 *  @var array<string,string> $countries
 */
$items  = $cart->items();
$subtotal = $cart->total();
$total    = $subtotal + ($quote['available'] ? $quote['price'] : 0);
?>
<section class="section">
    <div class="container">
        <header class="page-head">
            <h1 class="page-head__title"><?= e($title) ?></h1>
            <p class="page-head__text"><?= e(__('checkout.subtitle')) ?></p>
        </header>

        <form method="post" action="<?= e(url('/checkout')) ?>" class="checkout-form" novalidate>
            <?= csrf_field() ?>

            <div class="grid-2">
                <div class="checkout-fields">
                    <fieldset class="fieldset">
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

                    <fieldset class="fieldset">
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

                    <fieldset class="fieldset">
                        <legend><?= e(__('checkout.payment_legend')) ?></legend>

                        <div class="form-group">
                            <label class="form-check payment-option">
                                <input class="form-check-input" type="radio" name="payment" value="cod" checked>
                                <span>
                                    <strong><?= e(__('checkout.payment_cod')) ?></strong>
                                    <small><?= e(__('checkout.payment_cod_desc')) ?></small>
                                </span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="form-check payment-option">
                                <input class="form-check-input" type="radio" name="payment" value="wave">
                                <span>
                                    <strong><?= e(__('checkout.payment_wave')) ?></strong>
                                    <small><?= e(__('checkout.payment_wave_desc')) ?></small>
                                </span>
                            </label>
                            <?php if ($error = error_for('payment')): ?>
                                <p class="form-error"><?= e($error) ?></p>
                            <?php endif; ?>
                        </div>
                    </fieldset>
                </div>

                <aside class="checkout-summary">
                    <div class="summary">
                        <?php foreach ($items as $item): ?>
                            <div class="summary__row">
                                <span><?= e($item['name']) ?> × <?= e((string) $item['quantity']) ?></span>
                                <span class="amount"><?= money($item['quantity'] * $item['price']) ?></span>
                            </div>
                        <?php endforeach; ?>

                        <div class="summary__row">
                            <span><?= e(__('checkout.subtotal')) ?></span>
                            <span class="amount"><?= money($subtotal) ?></span>
                        </div>

                        <div class="summary__row">
                            <span><?= e(__('checkout.shipping')) ?></span>
                            <span class="amount" data-shipping-label>
                                <?= $quote['available'] ? money($quote['price']) : e(__('checkout.shipping_check')) ?>
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

                    <button class="btn btn-light btn-block btn-lg" type="submit">
                        <?= e(__('checkout.place_order')) ?>
                    </button>

                    <p class="checkout-summary__foot"><?= e(__('checkout.confirm_terms')) ?></p>
                </aside>
            </div>
        </form>
    </div>
</section>