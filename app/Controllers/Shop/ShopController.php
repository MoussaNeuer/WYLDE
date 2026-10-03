<?php

declare(strict_types=1);

namespace App\Controllers\Shop;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Product;
use App\Models\ShippingZone;

/**
 * Boutique : liste produits avec recherche, filtres et pagination.
 */
final class ShopController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = 12;

        $params = [
            'sort' => $request->str('sort', 'recent'),
            'page' => $this->page($request),
            'per_page' => $perPage,
            'label' => $request->str('label'),
        ];

        if ($request->filled('category_id')) {
            $params['category_id'] = $request->int('category_id');
        }

        $result = Product::search($request->str('q'), $params);

        return $this->view('shop/shop', [
            'title'    => __('nav.shop'),
            'category' => null,
            'products' => $result['products'],
            'total'    => $result['total'],
            'page'     => $params['page'],
            'pages'    => (int) ceil($result['total'] / $perPage),
            'perPage'  => $perPage,
            'categories' => Category::allActive(),
            'filters'    => $params,
            'shipping'   => ShippingZone::allActive(),
        ], 'layouts/shop');
    }

    public function category(Request $request): Response
    {
        $category = Category::findBySlug($request->routeParam('slug', ''));

        if ($category === null) {
            abort(404);
        }

        $perPage = 12;

        $result = Product::search(null, [
            'page'        => $this->page($request),
            'per_page'    => $perPage,
            'sort'        => $request->str('sort', 'recent'),
            'category_id' => $category->id(),
        ]);

        return $this->view('shop/shop', [
            'title'      => $category->localizedName(),
            'category'   => $category,
            'products'   => $result['products'],
            'total'      => $result['total'],
            'page'       => $result['page'] ?? $this->page($request),
            'pages'      => (int) ceil($result['total'] / $perPage),
            'perPage'    => $perPage,
            'categories' => Category::allActive(),
            'filters'    => ['sort' => $request->str('sort', 'recent')],
            'shipping'   => ShippingZone::allActive(),
        ], 'layouts/shop');
    }
}