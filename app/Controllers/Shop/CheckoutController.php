<?php

declare(strict_types=1);

namespace App\Controllers\Shop;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Cart;
use App\Models\Order;
use App\Models\ShippingZone;
use App\Services\OrderService;
use App\Services\SettingsService;
use App\Services\UploadService;
use App\Services\WhatsAppService;

/**
 * Tunnel de commande (invité autorisé).
 *
 * Les frais de livraison et le total ne sont jamais acceptés du client :
 * ils sont recalculés côté serveur au moment du passage de commande.
 *
 * Le canal de confirmation est indépendant du règlement : une commande peut
 * être réglée à la livraison ET confirmée via WhatsApp (§ orders.channel).
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
            'title'           => __('checkout.title'),
            'cart'            => $cart,
            'quote'           => $quote,
            'countries'       => ShippingZone::deliverableCountries(),
            'whatsappEnabled' => WhatsAppService::isEnabled(),
            // La page réserve une place en bas de l'écran pour le bandeau
            // de total fixe, ce padding n'a lieu d'être que sur le checkout.
            'bodyClass'       => 'page-checkout',
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

        // Le devis applique le seuil de livraison offerte : à partir du
        // seuil fixé dans l'admin, les frais tombent à zéro. Le montant
        // final est de toute façon recalculé ici, jamais repris du client.
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

        // La commande est déjà enregistrée : on peut envoyer le client sur
        // WhatsApp. Un lien null signifie que le numéro a été vidé entre-temps ;
        // on retombe alors sur la page de confirmation habituelle.
        $waLink = $this->whatsappLinkFor($reference);

        if ($data['channel'] === 'whatsapp' && $waLink !== null) {
            if ($request->wantsJson()) {
                return $this->json(['redirect' => $waLink]);
            }

            return $this->redirect($waLink);
        }

        if ($request->wantsJson()) {
            return $this->json(['redirect' => url('/order/success/' . $reference)]);
        }

        return $this->redirect('/order/success/' . $reference);
    }

    public function success(Request $request): Response
    {
        $reference = (string) $request->routeParam('reference', '');
        $order     = $reference !== '' ? Order::findByReference($reference) : null;

        // Bouton de secours : si le client a quitté WhatsApp sans envoyer,
        // il peut renvoyer le récapitulatif depuis cette page.
        $whatsappLink = $order !== null ? $this->whatsappLinkFor($reference, $order) : null;

        return $this->view('shop/checkout-success', [
            'title'     => __('checkout.success_title'),
            'reference' => $reference,
            'order'     => $order,
            'whatsappLink' => $whatsappLink,
            // Le client ne doit pas repartir chercher un lien Wave : on le
            // rappelle ici tant que la commande n'est pas encaissée. Le
            // lien reste celui configuré par l'équipe dans les réglages.
            'waveLink' => $order !== null
                && (string) ($order->getAttribute('payment_method') ?? '') === 'wave'
                && (new SettingsService())->hasWaveLink()
                    ? (new SettingsService())->waveLink()
                    : null,
        ], 'layouts/shop');
    }

    /**
     * Suivi de commande : récapitulatif public + suivi en direct.
     *
     * La page est identifiée par la référence (comme la page de
     * confirmation, qui la montre déjà au client). Le même point sert de
     * source JSON au rafraîchissement automatique : quand l'équipe fait
     * évoluer la commande dans le back-office, la page client se met à
     * jour sans rechargement.
     */
    public function tracking(Request $request): Response
    {
        $reference = (string) $request->routeParam('reference', '');
        $order     = $reference !== '' ? Order::findByReference($reference) : null;

        if ($order === null) {
            abort(404);
        }

        $payload = $this->trackingPayload($order);

        if ($request->wantsJson()) {
            $response = $this->json($payload);
            $response->setHeader('Cache-Control', 'no-store');

            return $response;
        }

        $response = $this->view('shop/order-tracking', [
            'title' => __('tracking.title'),
            'order' => $order,
            'items' => $order->items(),
            // État de suivi identique pour le rendu HTML et le JSON de
            // polling : aucun décalage entre ce que voit le client et ce
            // que renvoie la mise à jour automatique.
            'payload' => $payload,
        ], 'layouts/tracking');

        $response->setHeader('Cache-Control', 'no-store');

        return $response;
    }

    /**
     * État public d'une commande pour la page de suivi.
     *
     * @return array<string, mixed>
     */
    private function trackingPayload(Order $order): array
    {
        $status = (string) $order->status;

        $phase = match ($status) {
            Order::STATUS_CONFIRMED => 2,
            Order::STATUS_PREPARING => 3,
            Order::STATUS_SHIPPED   => 4,
            Order::STATUS_DELIVERED => 5,
            Order::STATUS_CANCELLED => null,
            default                 => 1,
        };

        $steps = [];

        foreach ([
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PREPARING,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
        ] as $index => $key) {
            $steps[] = [
                'key'   => $key,
                'label' => __('order.status.' . $key),
                // Livrée : les 5 étapes sont vertes. Annulée : aucune.
                // Sinon, tout ce qui précède est « fait », l'étape courante
                // est « active », la suite reste « à venir ».
                'state' => $status === Order::STATUS_CANCELLED
                    ? 'todo'
                    : ($status === Order::STATUS_DELIVERED
                        ? 'done'
                        : ($index + 1 < (int) max(1, $phase ?? 0)
                            ? 'done'
                            : ($index + 1 === (int) max(1, $phase) ? 'active' : 'todo'))),
            ];
        }

$proofPath = trim((string) ($order->getAttribute('payment_proof_path') ?? ''));
$cancelNote = trim((string) ($order->getAttribute('cancelled_reason') ?? ''));

return [
    'status'      => $status,
    'statusLabel' => __('order.status.' . $status),
    'cancelNote'  => $cancelNote !== '' ? $cancelNote : null,
    'phase'       => $phase,
            'cancelled'   => $status === Order::STATUS_CANCELLED,
            'delivered'   => $status === Order::STATUS_DELIVERED,
            'ended'       => in_array($status, [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED], true),
            'steps'       => $steps,
            'payment'     => [
                'method'      => (string) ($order->payment_method ?? ''),
                'methodLabel' => __('order.payment_method.' . $order->payment_method),
                'status'      => (string) $order->payment_status,
                'statusLabel' => __('order.payment_status.' . $order->payment_status),
                'proof'       => $proofPath !== '' ? $proofPath : null,
            ],
            'tracking'  => ['number' => trim((string) ($order->getAttribute('tracking_number') ?? ''))],
            'reference' => (string) ($order->getAttribute('reference') ?? ''),
            'placedAt'  => format_date((string) $order->created_at),
        ];
    }

    /**
     * Preuve de paiement Wave.
     *
     * Le client joint une capture de son reçu depuis la page de confirmation ;
     * la page est publique et n'est identifiée que par la référence, seule
     * information déjà affichée sur place. La route est protégée par CSRF et
     * le rate limiter, et n'accepte une preuve que pour une commande Wave
     * encore en attente d'encaissement.
     */
    public function uploadProof(Request $request): Response
    {
        $reference = (string) $request->routeParam('reference', '');
        $order     = $reference !== '' ? Order::findByReference($reference) : null;

        if ($order === null) {
            abort(404);
        }

        // Une preuve n'a de sens que tant que la commande attend son
        // encaissement : une fois payée (ou pour le paiement à la livraison),
        // le back-office n'a plus rien à vérifier ici.
        if ((string) ($order->getAttribute('payment_method') ?? '') !== Order::METHOD_WAVE
            || (string) ($order->getAttribute('payment_status') ?? Order::PAYMENT_UNPAID) !== Order::PAYMENT_UNPAID
        ) {
            return $this->redirect('/order/success/' . rawurlencode($reference));
        }

        $file = $request->file('proof');

        if ($file === null) {
            return $this->redirectWithErrors(
                '/order/success/' . rawurlencode($reference),
                ['proof' => __('checkout.wave_proof_required')]
            );
        }

        $result = UploadService::store($file, 'payments', 'preuve-' . $reference);

        if (!$result['ok']) {
            return $this->redirectWithErrors(
                '/order/success/' . rawurlencode($reference),
                ['proof' => $result['error']]
            );
        }

        // Une nouvelle capture remplace la précédente : on efface l'ancien
        // fichier pour ne pas accumuler d'orphelins dans storage/uploads.
        UploadService::delete((string) ($order->getAttribute('payment_proof_path') ?? ''));

        Database::update('orders', ['payment_proof_path' => $result['path']], ['id' => $order->id()]);

        OrderService::addHistory(
            $order->id(),
            (string) $order->getAttribute('status'),
            'Preuve de paiement Wave reçue.'
        );

        if ($request->wantsJson()) {
            return $this->json(['path' => $result['path'], 'redirect' => '/order/tracking/' . rawurlencode($reference)]);
        }

        return $this->redirectWithSuccess(
            '/order/tracking/' . rawurlencode($reference),
            __('flash.proof_uploaded')
        );
    }

    /** Lien WhatsApp du récapitulatif, ou null si le canal n'est pas configuré. */
    private function whatsappLinkFor(string $reference, ?Order $order = null): ?string
    {
        if (!WhatsAppService::isEnabled() || $reference === '') {
            return null;
        }

        $order ??= Order::findByReference($reference);

        if ($order === null || (string) ($order->getAttribute('channel') ?? 'site') !== 'whatsapp') {
            return null;
        }

        return WhatsAppService::link(WhatsAppService::orderMessage($order));
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
            'country_code' => strtoupper(trim($request->str('country_code'))),
            'notes'        => mb_substr($request->str('notes'), 0, 1000),
            'payment'      => in_array($request->str('payment'), ['cod', 'wave'], true)
                ? $request->str('payment')
                : 'cod',
            'channel'      => $request->str('channel') === 'whatsapp'
                ? 'whatsapp'
                : 'site',
        ];

        $errors = [];

        if ($data['name'] === '') {
            $errors['name'] = __('validation.required', ['field' => __('checkout.full_name')]);
        } elseif (mb_strlen($data['name'], 'UTF-8') > 80) {
            // name est scindé en first_name / last_name (VARCHAR(80)) :
            // une valeur trop longue ferait échouer MySQL en mode strict.
            $errors['name'] = __('validation.max', ['field' => __('checkout.full_name'), 'max' => 80]);
        }

        if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = __('validation.email', ['field' => __('checkout.email')]);
        } elseif (mb_strlen($data['email'], 'UTF-8') > 190) {
            $errors['email'] = __('validation.max', ['field' => __('checkout.email'), 'max' => 190]);
        }

        if ($data['phone'] === '') {
            $errors['phone'] = __('validation.required', ['field' => __('checkout.phone')]);
        } elseif (!preg_match('/^[+0-9][0-9 .\-]{6,17}$/', $data['phone'])) {
            $errors['phone'] = __('checkout.phone_invalid');
        }

        if ($data['address'] === '') {
            $errors['address'] = __('validation.required', ['field' => __('checkout.address')]);
        } elseif (mb_strlen($data['address'], 'UTF-8') > 255) {
            $errors['address'] = __('validation.max', ['field' => __('checkout.address'), 'max' => 255]);
        }

        if (mb_strlen($data['city'], 'UTF-8') > 120) {
            $errors['city'] = __('validation.max', ['field' => __('checkout.city'), 'max' => 120]);
        }

        if ($data['country_code'] === '') {
            $errors['country_code'] = __('validation.required', ['field' => __('checkout.country')]);
        } elseif (!preg_match('/^[A-Z]{2}$/', $data['country_code'])) {
            $errors['country_code'] = __('checkout.country_invalid');
        } elseif (!array_key_exists($data['country_code'], ShippingZone::deliverableCountries())) {
            $errors['country_code'] = __('checkout.country_unavailable');
        }

        if ($data['payment'] === 'wave') {
            // Le lien Wave est validé par l'équipe : la commande reste
            // « en attente de paiement » tant que rien n'est confirmé.
            if ((new SettingsService())->waveLink() === '') {
                $errors['payment'] = __('checkout.wave_unavailable');
            }
        }

        // On ne promet pas WhatsApp si le numéro a été retiré des réglages.
        if ($data['channel'] === 'whatsapp' && !WhatsAppService::isEnabled()) {
            $errors['channel'] = __('checkout.whatsapp_unavailable');
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
        // Scindé sur la première espace, en positions de caractères (le mélange
        // strlen/mb_substr d'origine découpait mal les noms accentués).
        $name      = $data['name'];
        $spaceAt   = mb_strpos($name . ' ', ' ', 0, 'UTF-8');
        $firstName = mb_substr($name, 0, $spaceAt, 'UTF-8');
        $lastName  = trim(mb_substr($name, $spaceAt, null, 'UTF-8'));

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
            'channel'              => $data['channel'],
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