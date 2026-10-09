<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Product;

/**
 * Cartes produit par identifiants.
 *
 * La page « Mes favoris » ne connaît que des identifiants : ils sont
 * rangés dans le navigateur du visiteur, jamais en base. Le serveur les
 * rend donc à la demande, en écartant les produits archivés ou retirés
 * de la vente entre-temps.
 */
final class ProductApiController extends Controller
{
    /** Garde-fou : au-delà, la requête porterait trop de placeholders. */
    private const MAX_IDS = 60;

    public function cards(Request $request): Response
    {
        $ids = $request->str('ids');

        $list = array_values(array_filter(array_map(
            static fn (string $id): int => (int) trim($id),
            $ids === '' ? [] : explode(',', $ids)
        )));

        $list = array_slice(array_values(array_unique($list)), 0, self::MAX_IDS);

        if ($list === []) {
            return $this->json(['html' => '', 'count' => 0, 'missing' => []]);
        }

        $products = Product::findManyByIds($list);

        $found = array_map(
            static fn (Product $product): int => (int) $product->id(),
            $products
        );

        return $this->json([
            'html'  => render_product_cards($products),
            'count'  => count($products),
            // Les produits introuvables sont signalés : la page peut
            // proposer de nettoyer les favoris périmés.
            'missing' => array_values(array_diff($list, $found)),
        ]);
    }
}