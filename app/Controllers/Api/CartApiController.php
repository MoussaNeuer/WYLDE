<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Cart;
use App\Models\ShippingZone;

/**
 * API panier : endpoints JSON consommés par app.js
 * (compteur du header, ajout/retrait sans rechargement).
 */
final class CartApiController extends Controller
{
    public function summary(Request $request): Response
    {
        $cart = new Cart();
        $cart->load();

        return $this->json([
            'count' => $cart->count(),
            'total' => $cart->total(),
            'empty' => $cart->isEmpty(),
        ]);
    }

    public function add(Request $request): Response
    {
        $cart = new Cart();
        $cart->load();

        $variantId = $request->int('variant_id');
        $quantity  = max(1, $request->int('quantity', 1));

        if ($variantId <= 0 || !$cart->add($variantId, $quantity)) {
            return $this->json(['error' => __('product.out_of_stock')], 422);
        }

        $cart->load();

        return $this->json([
            'ok'    => true,
            'count' => $cart->count(),
            'total' => $cart->total(),
            'just_added' => __('cart.added'),
        ]);
    }

    public function update(Request $request): Response
    {
        $cart = new Cart();
        $cart->load();

        $variantId = $request->int('variant_id');
        $quantity  = max(1, $request->int('quantity', 1));

        if ($variantId <= 0 || !$cart->setQuantity($variantId, $quantity)) {
            return $this->json(['error' => __('cart.invalid')], 422);
        }

        $cart->load();

        return $this->json([
            'ok'    => true,
            'count' => $cart->count(),
            'total' => $cart->total(),
        ]);
    }

    public function remove(Request $request): Response
    {
        $cart = new Cart();
        $cart->load();

        $cart->remove($request->int('variant_id'));
        $cart->load();

        return $this->json([
            'ok'    => true,
            'count' => $cart->count(),
            'total' => $cart->total(),
            'just_removed' => __('cart.removed'),
        ]);
    }

    /**
     * Devis de livraison recalculé côté serveur. Le montant retourné
     * n'est jamais utilisé comme paiement : le total est recalculé
     * à nouveau au passage de commande (§13.3).
     */
    public function shippingQuote(Request $request): Response
    {
        $quote = ShippingZone::quote(
            $request->str('country_code'),
            $request->str('city', ''),
            0
        );

        return $this->json([
            'available' => $quote['available'],
            'price'     => $quote['price'],
            'label'     => $quote['label'],
        ]);
    }
}