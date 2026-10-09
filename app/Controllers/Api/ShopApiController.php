<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Controllers\Shop\ShopController;
use App\Core\Request;
use App\Core\Response;
use App\Models\Product;

/**
 * Fragments de la grille boutique pour « Charger plus ».
 *
 * Le HTML est rendu par le serveur avec le même composant que la grille
 * initiale : le JavaScript ne réécrit donc jamais la carte. Les filtres
 * lus dans la requête sont ceux de ShopController, ce qui garantit que
 * la vague affichée appartient bien à la sélection en cours.
 */
final class ShopApiController extends Controller
{
    public function products(Request $request): Response
    {
        $filters = ShopController::filtersFrom($request);

        // Une URL d'API n'a pas de route de catégorie : on ne peut donc
        // pas reconstruire le lien de collection. On s'en tient à la
        // catégorie explicite transmise par le client.
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $perPage = 12;
        $query   = (string) ($filters['q'] ?? '');

        unset($filters['page']);

        $result = Product::search($query === '' ? null : $query, $filters + [
            'per_page' => $perPage,
        ]);

        $products = $result['products'];
        $total    = (int) $result['total'];
        $shown    = ($page * $perPage);

        return $this->json([
            'html'      => render_product_cards($products),
            'products'  => array_map(
                static fn (Product $product): array => product_payload($product),
                $products
            ),
            'page'      => $page,
            'per_page'  => $perPage,
            'total'     => $total,
            // Le compteur annonce le nombre réellement visible, pas la
            // page courante x le lot : avec une dernière page partielle,
            // « 24 sur 48 » deviendrait « 36 sur 48 ».
            'shown'     => min($shown, $total),
            'has_more'  => $page * $perPage < $total,
        ]);
    }
}