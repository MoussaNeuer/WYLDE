<?php

declare(strict_types=1);

namespace App\Controllers\Shop;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
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

// La variante affichée par défaut : is_default l'emporte, car c'est un
// choix explicite de l'admin. « UNIQUE » n'est qu'un repli pour les
// produits à une seule variante dont la taille porte ce libellé —
// sinon une variante UNIQUE parasite masquerait la vraie taille par
// défaut d'un produit qui, lui, a des tailles.
$defaultVariant = null;

foreach ($variants as $variant) {
    if (($variant->is_default ?? false) === true) {
        $defaultVariant = $variant;
        break;
    }
}

if ($defaultVariant === null) {
    foreach ($variants as $variant) {
        if ($variant->size === 'UNIQUE') {
            $defaultVariant = $variant;
            break;
        }
    }
}

$defaultVariant ??= $variants[0] ?? null;

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

        View::share('quick_variants', quick_variants_map($related));

        return $this->view('shop/product', [
            'title'    => $product->localizedName($locale),
            'product'  => $product,
            'variants' => $variants,
            'defaultVariant' => $defaultVariant,
            'related'  => $related,
        ], 'layouts/shop');
    }
}