<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Cart;
use App\Models\ShippingZone;

/**
 * API panier : endpoints JSON consommés par app.js.
 *
 * Toutes les mutations renvoient le même jeu de données (payload()) :
 * compteur du header, total, lignes détaillées et barre de livraison
 * offerte. Le mini-panier et la page panier se contentent donc de
 * redessiner la réponse, sans recalculer quoi que ce soit en JavaScript.
 */
final class CartApiController extends Controller
{
    public function summary(Request $request): Response
    {
        $cart = new Cart();
        $cart->load();

        return $this->json($this->payload($cart));
    }

    public function add(Request $request): Response
    {
        $cart = new Cart();
        $cart->load();

        $variantId = $request->int('variant_id');
        $quantity  = max(1, $request->int('quantity', 1));

        if (!$cart->add($variantId, $quantity)) {
            return $this->json(['error' => __('product.out_of_stock')], 422);
        }

        return $this->json($this->payload($cart, [
            'just_added' => __('cart.added'),
            'variant_id' => $variantId,
            // Signale un ajout volontaire : le mini-panier s'ouvre alors,
            // que l'ajout vienne d'une carte ou de la fiche produit.
            'open_drawer' => true,
        ]));
    }

    /**
     * Ajuste la quantité d'une ligne sans rechargement.
     *
     * Une quantité nulle ou négative retire la ligne : c'est ce que fait le
     * bouton « − » du mini-panier quand il arrive à 1. On renvoie la réponse
     * standard pour que le tiroir se mette à jour dans tous les cas.
     */
    public function update(Request $request): Response
    {
        $cart = new Cart();
        $cart->load();

        $variantId = $request->int('variant_id');
        $quantity  = $request->int('quantity', 1);

        if ($variantId <= 0) {
            return $this->json(['error' => __('cart.invalid')], 422);
        }

        if ($quantity < 1) {
            $cart->remove($variantId);

            return $this->json($this->payload($cart, ['just_removed' => __('cart.removed')]));
        }

        if (!$cart->setQuantity($variantId, $quantity)) {
            return $this->json(['error' => __('cart.invalid')], 422);
        }

        return $this->json($this->payload($cart, ['variant_id' => $variantId]));
    }

    public function remove(Request $request): Response
    {
        $cart = new Cart();
        $cart->load();

        $cart->remove($request->int('variant_id'));

        return $this->json($this->payload($cart, ['just_removed' => __('cart.removed')]));
    }

    /**
     * Devis de livraison recalculé côté serveur. Le montant retourné
     * n'est jamais utilisé comme paiement : le total est recalculé
     * à nouveau au passage de commande (§13.3).
     */
    public function shippingQuote(Request $request): Response
    {
        $cart   = new Cart();
        $cart->load();

        $quote = ShippingZone::quote(
            $request->str('country_code'),
            $request->str('city', ''),
            $cart->total()
        );

        $quote = ShippingZone::applyFreeShipping($quote, $cart->total());

        return $this->json([
            'available' => $quote['available'],
            'price'     => $quote['price'],
            'label'     => $quote['label'],
            'free'      => $quote['price'] === 0,
        ]);
    }

    /**
     * État complet du panier, partagé par tous les endpoints.
     *
     * @param  array<string, mixed> $extra  champs additionnels (messages)
     * @return array<string, mixed>
     */
    private function payload(Cart $cart, array $extra = []): array
    {
        $items = [];

        foreach ($cart->items() as $item) {
            $lineTotal = $item['quantity'] * $item['price'];

            $items[] = [
                'variant_id'       => $item['variant_id'],
                'product_slug'     => $item['product_slug'],
                'name'             => $item['name'],
                'size'             => $item['size'],
                'size_label'       => size_label($item['size']),
                'quantity'         => $item['quantity'],
                'stock'            => $item['stock'],
                'price'            => $item['price'],
                'price_text'       => money($item['price']),
                'line_total'       => $lineTotal,
                'line_total_text'  => money($lineTotal),
                'image'            => $item['image_path'] !== null
                    ? upload_url((string) $item['image_path'])
                    : null,
                'url'              => url('/product/' . $item['product_slug']),
                'checkout_url'     => url('/checkout'),
            ];
        }

        $total = $cart->total();

        $shipping = free_shipping_progress($total);

        // Phrase déjà traduite : le JavaScript ne connaît pas les langues.
        $shipping['text'] = $shipping['enabled']
            ? ($shipping['reached']
                ? __('cart.free_shipping.reached')
                : __('cart.free_shipping.remaining', ['amount' => $shipping['remaining_text']]))
            : '';

        return array_merge([
            'ok'         => true,
            'count'      => $cart->count(),
            'total'      => $total,
            'total_text' => money($total),
            'empty'      => $cart->isEmpty(),
            'items'      => $items,
            'shipping'   => $shipping,
            'checkout_url' => url('/checkout'),
            'cart_url'   => url('/cart'),
        ], $extra);
    }
}