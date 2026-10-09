<?php

declare(strict_types=1);

namespace App\Controllers\Shop;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Panier visiteur.
 *
 * La logique d'agrégat (lecture, ajout, mise à jour) vit dans le modèle
 * Cart ; ce contrôleur ne manipule que la présentation. L'API panier
 * (Api\CartApiController) répond en JSON pour le compteur du header.
 */
final class CartController extends Controller
{
    public function index(Request $request): Response
    {
        $cart = new \App\Models\Cart();
        $cart->load();

        return $this->view('shop/cart', [
            'title' => __('cart.title'),
            'cart'  => $cart,
        ], 'layouts/shop');
    }

    public function add(Request $request): Response
    {
        $variantId = $request->int('variant_id');
        $quantity  = $request->int('quantity', 1);

        if ($variantId < 1 || $quantity < 1) {
            return $request->wantsJson()
                ? $this->json(['error' => __('cart.invalid')], 422)
                : $this->redirect('/');
        }

        $cart = new \App\Models\Cart();
        $cart->load();
        $added = $cart->add($variantId, $quantity);

        if (!$added) {
            return $request->wantsJson()
                ? $this->json(['error' => __('cart.unavailable')], 422)
                : $this->redirectWithErrors('/cart', ['quantity' => __('cart.unavailable')]);
        }

        // Demande supérieure au stock : l'ajout est plafonné. On suit la
        // même règle que la mise à jour du panier (§12.4).
        $capped = $cart->cappedQuantity();

        if ($request->wantsJson()) {
            $payload = [
                'count' => $cart->count(),
                'total' => $cart->total(),
            ];

            if ($capped !== null) {
                $payload['message'] = trans_choice('cart.capped', $capped);
            }

            return $this->json($payload);
        }

        if ($capped !== null) {
            return $this->redirectWithErrors('/cart', [
                'quantity' => trans_choice('cart.capped', $capped),
            ]);
        }

        return $this->redirect('/cart');
    }

    public function update(Request $request): Response
    {
        $cart = new \App\Models\Cart();
        $cart->load();

        // Stock effectif par variante : il borne ce que le visiteur peut
        // demander, et sert aussi de garde-fou contre un champ quantity[]
        // forgé pour toucher une ligne absente du panier.
        $limits = [];

        foreach ($cart->items() as $item) {
            $limits[(int) $item['variant_id']] = max(1, (int) $item['stock']);
        }

        $submitted = $request->post('quantity', []);
        $plan      = cart_update_plan(is_array($submitted) ? $submitted : [], $limits);

        foreach ($plan['remove'] as $variantId) {
            $cart->remove($variantId);
        }

        foreach ($plan['set'] as $variantId => $quantity) {
            $cart->setQuantity($variantId, $quantity);
        }

        if ($request->wantsJson()) {
            return $this->json([
                'count'  => $cart->count(),
                'total'  => $cart->total(),
                'capped' => count($plan['capped']),
            ]);
        }

        if ($plan['capped'] !== []) {
            return $this->redirectWithErrors('/cart', [
                'quantity' => trans_choice('cart.capped', max($plan['capped'])),
            ]);
        }

        return $this->redirect('/cart');
    }

    public function remove(Request $request): Response
    {
        $variantId = $request->int('variant_id');

        $cart = new \App\Models\Cart();
        $cart->load();
        $cart->remove($variantId);

        if ($request->wantsJson()) {
            return $this->json([
                'count' => $cart->count(),
                'total' => $cart->total(),
            ]);
        }

        return $this->redirect('/cart');
    }
}