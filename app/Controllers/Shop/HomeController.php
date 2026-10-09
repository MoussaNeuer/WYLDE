<?php

declare(strict_types=1);

namespace App\Controllers\Shop;

use App\Controllers\Controller;
use App\Core\Cache;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Category;
use App\Models\Product;

/**
 * Page d'accueil.
 *
 * L'accueil est organisé par catégorie : chaque section affiche les
 * produits publiés d'une collection, sans quinquets « Nouveautés » ou
 * « Best-sellers » — la catégorie EST le fil.
 *
 * Les données sont mises en cache 60 secondes : chaque visite ne coûte
 * qu'une lecture de fichier au lieu d'une requête SQL par catégorie.
 */
final class HomeController extends Controller
{
    /** Durée du cache de la page d'accueil, en secondes. */
    private const CACHE_TTL = 60;

    /** Produits maximum par catégorie sur l'accueil. */
    private const PRODUCTS_PER_CATEGORY = 8;

    public function index(Request $request): Response
    {
        $sections = [];
        $dbOk     = true;

        try {
            $sections = Cache::remember('home.sections', self::CACHE_TTL, function (): array {
                $categories = array_filter(
                    Category::allActive(),
                    static fn (Category $category): bool => Product::countPublished((int) $category->id) > 0
                );

                $sections = [];

                foreach ($categories as $category) {
                    $products = Product::publishedByCategory(
                        (int) $category->id,
                        self::PRODUCTS_PER_CATEGORY
                    );

                    if ($products === []) {
                        continue;
                    }

                    $sections[] = [
                        'id'       => (int) $category->id,
                        'slug'     => (string) $category->slug,
                        'name'     => $category->localizedName(),
                        'products' => $products,
                    ];
                }

                return $sections;
            });
        } catch (\Throwable) {
            // La base est inaccessible : la page reste affichable.
            $dbOk = false;
            $sections = [];
        }

        // Variantes +/- images de survol pour toutes les cartes, en une
        // seule requête au lieu d'une par carte.
        $allProducts = [];
        foreach ($sections as $section) {
            foreach ($section['products'] as $product) {
                $allProducts[] = $product;
            }
        }

        View::share('quick_variants', quick_variants_map($allProducts));
        View::share('card_images', card_images_map($allProducts));

        return $this->view('shop/home', [
            'title'    => config('app.name', 'WYLDE') . ' — ' . __('app.tagline'),
            'sections' => $sections,
            'dbOk'     => $dbOk,
            'bodyClass'=> 'page-home',
        ], 'layouts/shop');
    }
}