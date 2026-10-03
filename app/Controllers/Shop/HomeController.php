<?php

declare(strict_types=1);

namespace App\Controllers\Shop;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * Page d'accueil.
 *
 * Version Phase 1 : vérifie la chaîne complète (route -> contrôleur ->
 * modèle -> vue -> rendu) et affiche l'état du système. Le contenu
 * éditorial et les sections réelles arrivent en Phase 3.
 */
final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $productsCount = 0;
        $newProducts   = [];
        $bestSellers   = [];
        $dbOk          = true;

        try {
            $productsCount = (int) Database::selectValue(
                "SELECT COUNT(*) FROM `products` WHERE `status` = 'published'"
            );

            // Dernières nouveautés publiées, pour prouver la lecture de données.
            $newProducts = Database::select(
                "SELECT p.*, (
                     SELECT path FROM product_images
                     WHERE product_id = p.id
                     ORDER BY is_primary DESC, sort_order ASC LIMIT 1
                 ) AS image_path
                 FROM products p
                 WHERE p.status = 'published'
                 ORDER BY p.published_at DESC, p.id DESC
                 LIMIT 8"
            );

            $bestSellers = Database::select(
                "SELECT p.*, (
                     SELECT path FROM product_images
                     WHERE product_id = p.id
                     ORDER BY is_primary DESC, sort_order ASC LIMIT 1
                 ) AS image_path
                 FROM products p
                 WHERE p.status = 'published' AND p.label = 'bestseller'
                 ORDER BY p.sold_count DESC
                 LIMIT 4"
            );
        } catch (\Throwable) {
            // La base est inaccessible : la page reste affichable.
            $dbOk = false;
        }

        return $this->view('shop/home', [
            'title'         => config('app.name', 'WYLDE') . ' — ' . __('app.tagline'),
            'productsCount' => $productsCount,
            'newProducts'   => $newProducts,
            'bestSellers'   => $bestSellers,
            'dbOk'          => $dbOk,
            'bodyClass'     => 'page-home',
        ], 'layouts/shop');
    }
}
