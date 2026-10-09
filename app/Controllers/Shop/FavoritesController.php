<?php

declare(strict_types=1);

namespace App\Controllers\Shop;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * « Mes favoris ».
 *
 * La page ne rend aucun produit : les favoris vivent dans le navigateur
 * du visiteur (aucun compte requis, rien à 동기iser). Le JavaScript
 * demande les cartes correspondantes et les dessine ici.
 */
final class FavoritesController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('shop/favorites', [
            'title'     => __('favorites.title'),
            'bodyClass' => 'page-favorites',
        ], 'layouts/shop');
    }
}