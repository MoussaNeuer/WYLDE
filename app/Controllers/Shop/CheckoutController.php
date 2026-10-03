<?php

declare(strict_types=1);

namespace App\Controllers\Shop;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Cart;
use App\Models\ShippingZone;

/**
 * Tunnel de commande (invité autorisé).
 *
 * Les frais de livraison et le total ne sont jamais acceptés du client :
 * ils sont recalculés côté serveur au moment du passage de commande.
 */
final class CheckoutController extends Controller
{
    public function index(Request $request): Response
    {
        $cart = $this->requiredCart();

        if ($cart === null) {
            return $this->redirect('/cart');
        }

        $quote = ShippingZone::quote(
            $request->str('country_code'),
            $request->str('city', ''),
            $cart->total()
        );

        return $this->view('shop/checkout', [
            'title'      => __('checkout.title'),
            'cart'       => $cart,
            'quote'      => $quote,
            'countries'  => ShippingZone::availableCountries(),
        ], 'layouts/shop');
    }

    public function place(Request $request): Response
    {
        $cart = $this->requiredCart();

        if ($cart === null) {
            return $this->redirect('/cart');
        }

        $data = $this->validate($request);

        if ($data === null) {
            // Les erreurs et l'ancien input ont déjà été mis en session
            // dans validate().
            return $this->redirect('/checkout');
        }

        $quote = ShippingZone::quote($data['country_code'], $data['city'], $cart->total());

        if (!$quote['available']) {
            Session::flashErrors(['zone' => __('checkout.zone_unavailable')], $data);

            return $this->redirect('/checkout');
        }

        try {
            $reference = Database::transaction(function () use ($cart, $data, $quote) {
                return $this->persistOrder($cart, $data, (int) $quote['price']);
            });
        } catch (\Throwable $e) {
            if (!$cart->isEmpty()) {
                $cart->clear();
            }

            throw $e;
        }

        $cart->clear();

        if ($request->wantsJson()) {
            return $this->json(['redirect' => url('/order/success/' . $reference)]);
        }

        return $this->redirect('/order/success/' . $reference);
    }

    public function success(Request $request): Response
    {
        $reference = $request->routeParam('reference', '');

        return $this->view('shop/checkout-success', [
            'title'     => __('checkout.success_title'),
            'reference' => (string) $reference,
        ], 'layouts/shop');
    }

    private function requiredCart(): ?Cart
    {
        $cart = new Cart();
        $cart->load();

        return $cart->isEmpty() ? null : $cart;
    }

    /**
     * Valide les champs du formulaire. Retourne null en cas d'erreur
     * (les messages sont déjà placés en session).
     *
     * @return array<string, string>|null
     */
    private function validate(Request $request): ?array
    {
        $data = [
            'name'         => trim($request->str('name')),
            'email'        => $request->str('email'),
            'phone'        => $request->str('phone'),
            'address'      => trim($request->str('address')),
            'city'         => trim($request->str('city', '')),
            'country_code' => strtoupper(mb_substr(trim($request->str('country_code')), 0, 2)),
            'notes'        => mb_substr($request->str('notes'), 0, 1000),
            'payment'      => in_array($request->str('payment'), ['cod', 'wave'], true)
                ? $request->str('payment')
                : 'cod',
        ];

        $errors = [];

        if ($data['name'] === '') {
            $errors['name'] = __('validation.required', ['field' => __('checkout.full_name')]);
        }

        if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = __('validation.email', ['field' => __('checkout.email')]);
        }

        if ($data['phone'] === '') {
            $errors['phone'] = __('validation.required', ['field' => __('checkout.phone')]);
        } elseif (!preg_match('/^[+0-9][0-9 .\-]{6,17}$/', $data['phone'])) {
            $errors['phone'] = __('checkout.phone_invalid');
        }

        if ($data['address'] === '') {
            $errors['address'] = __('validation.required', ['field' => __('checkout.address')]);
        }

        if ($data['country_code'] === '') {
            $errors['country_code'] = __('validation.required', ['field' => __('checkout.country')]);
        }

        if ($data['payment'] === 'wave') {
            // Le lien Wave est validé par l'équipe : la commande reste
            // « en attente de paiement » tant que rien n'est confirmé.
            if ((new \App\Services\SettingsService())->waveLink() === '') {
                $errors['payment'] = __('checkout.wave_unavailable');
            }
        }

        if ($errors !== []) {
            Session::flashErrors($errors, $data);

            return null;
        }

        return $data;
    }

    /**
     * @param  array<string, string> $data
     * @return string Référence de commande.
     */
    private function persistOrder(Cart $cart, array $data, int $shippingPrice): string
    {
        $firstName = trim(mb_substr($data['name'], 0, (int) strpos($data['name'] . ' ', ' ')));
        $lastName  = trim(mb_substr($data['name'], strlen($firstName)));

        $reference = 'WY-' . strtoupper(bin2hex(random_bytes(4)));

        $userId     = \App\Core\Auth::id();
        $customerId = null;

        if ($userId === null) {
            $customerId = (int) Database::insert('customers', [
                'user_id'      => null,
                'first_name'   => $firstName,
                'last_name'    => $lastName,
                'email'        => $data['email'],
                'phone'        => $data['phone'],
                'address'      => $data['address'],
                'city'         => $data['city'],
                'country_code' => $data['country_code'],
            ]);
        } else {
            $customer = (new \App\Models\Customer())->findByUserId($userId);
            $customerId = $customer !== null ? (int) $customer->id() : null;
        }

        $total = $cart->total() + $shippingPrice;

        $orderId = (int) Database::insert('orders', [
            'reference'            => $reference,
            'customer_id'          => $customerId,
            'user_id'              => $userId,
            'status'               => 'pending',
            'payment_method'       => $data['payment'],
            'payment_status'       => 'unpaid',
            'subtotal'             => $cart->total(),
            'shipping_cost'        => $shippingPrice,
            'discount'             => 0,
            'total'                => $total,
            'currency'             => 'XOF',
            'item_count'           => $cart->count(),
            'customer_email'       => $data['email'],
            'customer_phone'       => $data['phone'],
            'shipping_first_name'  => $firstName,
            'shipping_last_name'   => $lastName,
            'shipping_address'     => $data['address'],
            'shipping_city'        => $data['city'],
            'shipping_country_code'=> $data['country_code'],
            'shipping_zone_id'     => $this->zoneIdFor($data['country_code'], $data['city']),
            'shipping_method'      => $this->zoneLabelFor($data['country_code'], $data['city']),
            'notes'                => $data['notes'] !== '' ? $data['notes'] : null,
        ]);

        foreach ($cart->items() as $item) {
            Database::insert('order_items', [
                'order_id'       => $orderId,
                'product_id'     => $item['product_id'],
                'variant_id'     => $item['variant_id'],
                'product_name'   => $item['name'],
                'product_name_en'=> null,
                'sku'            => null,
                'size'           => $item['size'] ?? 'UNIQUE',
                'quantity'       => $item['quantity'],
                'unit_price'     => $item['price'],
                'line_total'     => $item['quantity'] * $item['price'],
                'image_path'     => $item['image_path'],
            ]);

            Database::statement(
                'UPDATE `product_variants` SET `stock` = GREATEST(`stock` - :qty, 0) WHERE `id` = :id',
                ['qty' => $item['quantity'], 'id' => $item['variant_id']]
            );
        }

        Database::insert('order_status_history', [
            'order_id'    => $orderId,
            'status'      => 'pending',
            'note'        => 'Commande créée côté boutique.',
            'changed_by'  => $userId,
        ]);

        return $reference;
    }

    private function zoneIdFor(string $countryCode, string $city): ?int
    {
        $zone = ShippingZone::resolve($countryCode, $city);

        return $zone['zone']?->id();
    }

    private function zoneLabelFor(string $countryCode, string $city): ?string
    {
        $zone = ShippingZone::resolve($countryCode, $city);

        return $zone['zone']?->label;
    }
}