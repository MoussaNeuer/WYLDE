<?php

declare(strict_types=1);

use App\Core\Router;
use Tests\T;

/**
 * Applique l'expression d'une route à un chemin et renvoie les paramètres.
 *
 * @param array<string, mixed> $route
 * @return array<string, string>|null
 */
function matchRoute(array $route, string $path): ?array
{
    if (preg_match($route['regex'], $path, $matches) !== 1) {
        return null;
    }

    $params = [];

    foreach ($route['params'] as $index => $name) {
        $params[$name] = $matches[$index + 1] ?? '';
    }

    return $params;
}

T::group('Routeur — correspondance');

T::it('un paramètre simple capture un segment', static function (): void {
    $router = new Router();
    $router->get('/product/{slug}', static fn (): string => 'ok');
    $route = $router->routes()[0];

    T::same(['slug' => 't-shirt'], matchRoute($route, '/product/t-shirt'));
    T::null(matchRoute($route, '/product'));
    T::null(matchRoute($route, '/product/a/b'));
});

T::it('where() contraint un paramètre (régression groupe capturant)', static function (): void {
    $router = new Router();
    $router->get('/locale/{code}', static fn (): string => 'ok')->where('code', 'fr|en');
    $route = $router->routes()[0];

    T::same(['code' => 'fr'], matchRoute($route, '/locale/fr'));
    T::same(['code' => 'en'], matchRoute($route, '/locale/en'));
    T::null(matchRoute($route, '/locale/xx'));
});

T::it('un paramètre *id n’accepte que des chiffres', static function (): void {
    $router = new Router();
    $router->get('/admin/orders/{id}', static fn (): string => 'ok');
    $route = $router->routes()[0];

    T::same(['id' => '42'], matchRoute($route, '/admin/orders/42'));
    T::null(matchRoute($route, '/admin/orders/abc'));
});

T::it('where() refuse un paramètre inexistant', static function (): void {
    $router = new Router();

    T::throws(static function () use ($router): void {
        $router->get('/only/{slug}', static fn (): string => 'ok')->where('id', '\\d+');
    }, RuntimeException::class);
});
