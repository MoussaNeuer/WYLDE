<?php

declare(strict_types=1);

/**
 * WYLDE — Table de routage.
 *
 * Règle de résolution : la première route qui matche gagne. Les routes
 * littérales sont donc déclarées AVANT les routes paramétrées.
 *
 * $router, $request et les fonctions globales (view(), __(), …) sont
 * disponibles : ce fichier est inclus depuis public/index.php.
 *
 * Middlewares disponibles : auth, admin, guest, csrf, rate_limit, security
 */

use App\Core\Router;

/** @var Router $router */

// ═══════════════════════════════════════════════════════════════════════
//  VITRINE PUBLIQUE
//  Aucune authentification requise : un visiteur peut commander (§4).
// ═══════════════════════════════════════════════════════════════════════

// Bascule de langue. Les URLs restent identiques : la langue est
// mémorisée en session puis en cookie (voir Lang::boot).
$router->get('/locale/{code}', 'Shop\PageController@switchLocale')
    ->where('code', 'fr|en');

$router->get('/', 'Shop\HomeController@index');

$router->get('/shop', 'Shop\ShopController@index');
$router->get('/collection/{slug}', 'Shop\ShopController@category');

$router->get('/product/{slug}', 'Shop\ProductController@show');

// Pages éditoriales
$router->get('/about', 'Shop\PageController@about');
$router->get('/contact', 'Shop\PageController@contact');
$router->post('/contact', 'Shop\PageController@submitContact')
    ->middleware(['csrf', 'rate_limit']);
$router->get('/privacy', 'Shop\PageController@privacy');
$router->get('/terms', 'Shop\PageController@terms');

// ── Recherche (JSON, sans rechargement) ───────────────────────────────
$router->get('/api/search', 'Api\SearchApiController@index')
    ->middleware(['rate_limit']);

// ── Panier ────────────────────────────────────────────────────────────
$router->get('/cart', 'Shop\CartController@index');
$router->post('/cart/add', 'Shop\CartController@add')
    ->middleware(['csrf', 'rate_limit']);
$router->post('/cart/update', 'Shop\CartController@update')
    ->middleware(['csrf']);
$router->post('/cart/remove', 'Shop\CartController@remove')
    ->middleware(['csrf']);

// API panier : utilisée par le compteur du header (Fetch).
$router->get('/api/cart', 'Api\CartApiController@summary');
$router->post('/api/cart/add', 'Api\CartApiController@add')
    ->middleware(['csrf', 'rate_limit']);
$router->post('/api/cart/update', 'Api\CartApiController@update')
    ->middleware(['csrf']);
$router->post('/api/cart/remove', 'Api\CartApiController@remove')
    ->middleware(['csrf']);

// ── Checkout (invité autorisé) ────────────────────────────────────────
$router->get('/checkout', 'Shop\CheckoutController@index');
$router->post('/checkout', 'Shop\CheckoutController@place')
    ->middleware(['csrf', 'rate_limit']);

// Frais de livraison recalculés côté serveur (§13.3 : le client ne
// propose jamais le montant des frais).
$router->get('/api/shipping/quote', 'Api\CartApiController@shippingQuote')
    ->middleware(['rate_limit']);

$router->get('/order/success/{reference}', 'Shop\CheckoutController@success');

// ── Médias produits ───────────────────────────────────────────────────
// Les fichiers sont stockés hors document root : ils ne sont servis que
// par ce contrôleur, qui valide le chemin (§13.4).
$router->get('/media/{path}', 'Api\MediaController@show')
    ->where('path', '.+');

// ── SEO ───────────────────────────────────────────────────────────────
$router->get('/sitemap.xml', 'Shop\PageController@sitemap');
$router->get('/site.webmanifest', 'Shop\PageController@manifest');

// ═══════════════════════════════════════════════════════════════════════
//  COMPTE CLIENT
// ═══════════════════════════════════════════════════════════════════════

$router->get('/login', 'Account\AuthController@showLogin')
    ->middleware(['guest']);
$router->post('/login', 'Account\AuthController@login')
    ->middleware(['guest', 'csrf', 'rate_limit']);

$router->get('/register', 'Account\AuthController@showRegister')
    ->middleware(['guest']);
$router->post('/register', 'Account\AuthController@register')
    ->middleware(['guest', 'csrf', 'rate_limit']);

// Pas d'« mot de passe oublié » en V1 : aucun envoi d'email (§22).
// Ces deux routes seront ajoutées avec la messagerie.

$router->post('/logout', 'Account\AuthController@logout')
    ->middleware(['auth', 'csrf']);

$router->group(['auth'], '');
{
    $router->get('/account', 'Account\AccountController@index');
    $router->post('/account', 'Account\AccountController@update')
        ->middleware(['csrf']);

    $router->get('/account/orders', 'Account\OrdersController@index');
    // L'ID est vérifié côté serveur : un client ne voit que ses commandes.
    $router->get('/account/orders/{id}', 'Account\OrdersController@show');
}
$router->endGroup();

// ═══════════════════════════════════════════════════════════════════════
//  BACK-OFFICE ADMINISTRATEUR
//  Le middleware 'admin' vérifie le rôle côté serveur sur chaque route
//  (§13.2) : masquer un bouton ne protège rien.
// ═══════════════════════════════════════════════════════════════════════

$router->get('/admin/login', 'Admin\AuthController@showLogin')
    ->middleware(['guest']);
$router->post('/admin/login', 'Admin\AuthController@login')
    ->middleware(['guest', 'csrf', 'rate_limit']);

$router->group(['admin'], 'admin');
{
    $router->get('', 'Admin\DashboardController@index');

    // ── Produits ───────────────────────────────────────────────────────
    // Routes littérales avant {id}.
    $router->get('/products',          'Admin\ProductController@index');
    $router->get('/products/create',    'Admin\ProductController@create');
    $router->post('/products',          'Admin\ProductController@store')
        ->middleware(['csrf']);

    $router->get('/products/{id}/edit',   'Admin\ProductController@edit');
    $router->post('/products/{id}',        'Admin\ProductController@update')
        ->middleware(['csrf']);
    $router->post('/products/{id}/delete', 'Admin\ProductController@destroy')
        ->middleware(['csrf']);

    // Actions groupées sur la liste (§19 : bulk actions).
    $router->post('/products/bulk', 'Admin\ProductController@bulk')
        ->middleware(['csrf']);

    // ── Médias : upload et Drag & Drop (§6) ───────────────────────────
    $router->get('/products/{id}/media', 'Admin\ProductController@media')
        ->middleware(['csrf']);
    $router->post('/products/{id}/media', 'Admin\ProductController@uploadMedia')
        ->middleware(['csrf']);
    $router->post('/products/{id}/media/reorder', 'Admin\ProductController@reorderMedia')
        ->middleware(['csrf']);
    $router->post('/products/{id}/media/{imageId}/primary', 'Admin\ProductController@setPrimaryMedia')
        ->middleware(['csrf']);
    $router->post('/products/{id}/media/{imageId}/delete', 'Admin\ProductController@deleteMedia')
        ->middleware(['csrf']);

    // ── Catégories ─────────────────────────────────────────────────────
    $router->get('/categories',            'Admin\CategoryController@index');
    $router->post('/categories',           'Admin\CategoryController@store')
        ->middleware(['csrf']);
    $router->post('/categories/{id}',      'Admin\CategoryController@update')
        ->middleware(['csrf']);
    $router->post('/categories/{id}/delete', 'Admin\CategoryController@destroy')
        ->middleware(['csrf']);

    // ── Stock ──────────────────────────────────────────────────────────
    $router->get('/inventory',          'Admin\InventoryController@index');
    $router->post('/inventory/{variantId}/adjust', 'Admin\InventoryController@adjust')
        ->middleware(['csrf']);

    // ── Commandes ──────────────────────────────────────────────────────
    $router->get('/orders',           'Admin\OrderController@index');
    $router->get('/orders/{id}',      'Admin\OrderController@show');
    $router->post('/orders/{id}/status', 'Admin\OrderController@updateStatus')
        ->middleware(['csrf']);
    $router->post('/orders/{id}/payment', 'Admin\OrderController@updatePayment')
        ->middleware(['csrf']);
    $router->post('/orders/{id}/notes', 'Admin\OrderController@updateNotes')
        ->middleware(['csrf']);

    // ── Clients ────────────────────────────────────────────────────────
    $router->get('/customers',       'Admin\CustomerController@index');
    $router->get('/customers/{id}',  'Admin\CustomerController@show');

    // ── Analytics ──────────────────────────────────────────────────────
    $router->get('/analytics', 'Admin\AnalyticsController@index');

    // ── Notifications et aide (menu du back-office) ────────────────────
    $router->get('/notifications', 'Admin\NotificationController@index');
    $router->get('/help',          'Admin\HelpController@index');

    // ── Paramètres, zones de livraison, profil, sécurité ───────────────
    $router->get('/settings',         'Admin\SettingsController@index');
    $router->post('/settings',        'Admin\SettingsController@update')
        ->middleware(['csrf']);

    $router->get('/shipping-zones',            'Admin\SettingsController@shippingZones');
    $router->post('/shipping-zones',           'Admin\SettingsController@storeShippingZone')
        ->middleware(['csrf']);
    $router->post('/shipping-zones/{id}',      'Admin\SettingsController@updateShippingZone')
        ->middleware(['csrf']);
    $router->post('/shipping-zones/{id}/delete', 'Admin\SettingsController@destroyShippingZone')
        ->middleware(['csrf']);

    $router->get('/profile',  'Admin\ProfileController@index');
    $router->post('/profile', 'Admin\ProfileController@update')
        ->middleware(['csrf']);

    $router->get('/security',  'Admin\ProfileController@security');
    $router->post('/security/password', 'Admin\ProfileController@updatePassword')
        ->middleware(['csrf']);
    $router->post('/security/sessions/revoke', 'Admin\ProfileController@revokeSessions')
        ->middleware(['csrf']);

    // ── Catalogue des tailles ───────────────────────────────────────────
    // Ces tailles sont la seule source du formulaire produit : voir
    // ProductValidator, qui refuse toute valeur absente du catalogue.
    // « preset » est déclaré avant « {id} » : le routeur teste dans
    // l'ordre d'enregistrement.
    $router->get('/sizes',        'Admin\SizeController@index');
    $router->post('/sizes',       'Admin\SizeController@store')
        ->middleware(['csrf']);
    $router->post('/sizes/preset', 'Admin\SizeController@storePreset')
        ->middleware(['csrf']);
    $router->post('/sizes/{id}', 'Admin\SizeController@update')
        ->middleware(['csrf']);
    $router->post('/sizes/{id}/delete', 'Admin\SizeController@destroy')
        ->middleware(['csrf']);
}
$router->endGroup();

// ═══════════════════════════════════════════════════════════════════════
//  API BACK-OFFICE (JSON)
//  Routes à la racine : le préfixe « admin » du groupe précédent ne
//  s'applique pas. Le middleware « admin » vérifie session et rôle.
// ═══════════════════════════════════════════════════════════════════════

// Médias produits : dépôt, réordonnancement, image principale, suppression.
$router->post('/api/admin/products/{id}/media', 'Api\AdminImageApiController@upload')
    ->middleware(['admin', 'csrf']);
$router->post('/api/admin/products/{id}/media/reorder', 'Api\AdminImageApiController@reorder')
    ->middleware(['admin', 'csrf']);
$router->post('/api/admin/media/{imageId}/delete', 'Api\AdminImageApiController@destroy')
    ->middleware(['admin', 'csrf']);
$router->post('/api/admin/media/{imageId}/primary', 'Api\AdminImageApiController@primary')
    ->middleware(['admin', 'csrf']);

// Produits : filtres asynchrones et actions rapides.
$router->get('/api/admin/products', 'Api\AdminProductApiController@index')
    ->middleware(['admin', 'rate_limit']);
$router->post('/api/admin/products/{id}/delete', 'Api\AdminProductApiController@destroy')
    ->middleware(['admin', 'csrf']);

// Commandes et statistiques.
$router->get('/api/admin/orders', 'Api\AdminOrderApiController@index')
    ->middleware(['admin', 'rate_limit']);
$router->post('/api/admin/orders/{id}/status', 'Api\AdminOrderApiController@updateStatus')
    ->middleware(['admin', 'csrf']);
$router->get('/api/admin/analytics', 'Api\AdminOrderApiController@stats')
    ->middleware(['admin', 'rate_limit']);

// ═══════════════════════════════════════════════════════════════════════
//  404 — page personnalisée (§3.1)
// ═══════════════════════════════════════════════════════════════════════
$router->fallback(static function () {
    return view('errors/error', [
        'status'  => 404,
        'message' => __('errors.not_found_text'),
    ], 'layouts/shop', 404);
});
