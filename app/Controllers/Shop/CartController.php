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

        if ($request->wantsJson()) {
            return $this->json([
                'count' => $cart->count(),
                'total' => $cart->total(),
            ]);
        }

        return $this->redirect('/cart');
    }

    public function update(Request $request): Response
    {
        $variantId = $request->int('variant_id');
        $quantity  = $request->int('quantity', 0);

        $cart = new \App\Models\Cart();
        $cart->load();

        if ($quantity < 1) {
            $cart->remove($variantId);
        } else {
            $cart->setQuantity($variantId, $quantity);
        }

        if ($request->wantsJson()) {
            return $this->json([
                'count' => $cart->count(),
                'total' => $cart->total(),
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