<?php

declare(strict_types=1);

namespace App\Controllers\Shop;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Product;

/**
 * Fiche produit.
 *
 * La vue affiche le prix barré depuis sale_price, jamais depuis des
 * valeurs transmises par le navigateur.
 */
final class ProductController extends Controller
{
    public function show(Request $request): Response
    {
        $slug = $request->routeParam('slug', '');

        $product = Product::publishedBySlug($slug);

        if ($product === null) {
            abort(404);
        }

        Database::statement(
            'UPDATE `products` SET `view_count` = `view_count` + 1 WHERE `id` = :id',
            ['id' => $product->id()]
        );

        $locale = Lang::locale();

        $variants = $product->variants();

        $defaultVariant = null;
        foreach ($variants as $variant) {
            if (($variant->is_default ?? false) === true || $variant->size === 'UNIQUE') {
                $defaultVariant = $variant;
                break;
            }
        }

        $relatedIds = [];
        $related    = [];

        foreach (Product::search(null, [
            'category_id' => $product->attributes['category_id'] ?? null,
            'per_page'    => 6,
            'sort'        => 'popular',
        ])['products'] as $p) {
            if ($p->id() === $product->id()) {
                continue;
            }
            $related[]              = $p;
            $relatedIds[]           = $p->id();
        }

        if (count($related) < 4) {
            foreach (Product::search(null, ['per_page' => 8, 'sort' => 'recent'])['products'] as $p) {
                if (count($related) >= 4) {
                    break;
                }
                if ($p->id() === $product->id() || in_array($p->id(), $relatedIds, true)) {
                    continue;
                }
                $related[]    = $p;
                $relatedIds[] = $p->id();
            }
        }

        return $this->view('shop/product', [
            'title'    => $product->localizedName($locale),
            'product'  => $product,
            'variants' => $variants,
            'defaultVariant' => $defaultVariant,
            'related'  => $related,
        ], 'layouts/shop');
    }
}