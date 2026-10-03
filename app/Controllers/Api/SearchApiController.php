<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Product;

/**
 * Recherche instantanée (§4.4) : les suggestions sont rendues par le client
 * à partir de cette réponse. Seuls les produits publiés sont exposés.
 */
final class SearchApiController extends Controller
{
    public function index(Request $request): Response
    {
        $query = trim($request->str('q'));

        if ($query === '') {
            return $this->json(['query' => '', 'results' => [], 'count' => 0]);
        }

        $limit = max(1, min(20, $request->int('limit', 8)));

        $result = Product::search($query, [
            'sort'     => 'relevance',
            'page'     => 1,
            'per_page' => $limit,
        ]);

        $results = array_map(
            static fn (Product $product): array => product_payload($product),
            $result['products']
        );

        return $this->json([
            'query'   => $query,
            'results' => $results,
            'count'   => count($results),
            'total'   => (int) $result['total'],
        ]);
    }
}
