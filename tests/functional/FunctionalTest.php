<?php

declare(strict_types=1);

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use Tests\T;

/**
 * Exécute une requête simulée à travers le routeur.
 */
function dispatchTo(Router $router, string $method, string $uri): Response
{
    $_GET    = [];
    $_POST   = [];
    $_FILES  = [];
    $_SERVER = [
        'REQUEST_METHOD' => $method,
        'REQUEST_URI'    => $uri,
        'SCRIPT_NAME'    => '/index.php',
        'HTTP_HOST'      => 'localhost',
        'REMOTE_ADDR'    => '127.0.0.1',
    ];

    return $router->dispatch(Request::capture());
}

T::group('Fonctionnel — dispatch');

T::it('transmet les paramètres de route au handler', static function (): void {
    $router = new Router();
    $router->get('/probe/{id}', static fn (Request $request): string => 'id:' . $request->routeParam('id'));

    $response = dispatchTo($router, 'GET', '/probe/42');

    T::same(200, $response->status());
    T::same('id:42', $response->content());
});

T::it('sérialise un tableau de handler en JSON', static function (): void {
    $router = new Router();
    $router->get('/api/probe', static fn (): array => ['ok' => true, 'count' => 3]);

    $response = dispatchTo($router, 'GET', '/api/probe');

    T::same(200, $response->status());
    T::contains('"ok":true', $response->content());
    T::contains('"count":3', $response->content());
});

T::it('lève 404 sur un identifiant non numérique', static function (): void {
    $router = new Router();
    $router->get('/probe/{id}', static fn (): string => 'ok');

    T::throws(static fn (): Response => dispatchTo($router, 'GET', '/probe/abc'), HttpException::class);
});

T::it('lève 404 sur un chemin inconnu', static function (): void {
    $router = new Router();
    $router->get('/only', static fn (): string => 'ok');

    $exception = T::throws(static fn (): Response => dispatchTo($router, 'GET', '/inconnu'), HttpException::class);

    T::same(404, $exception->statusCode());
});

T::it('lève 405 quand le chemin existe avec une autre méthode', static function (): void {
    $router = new Router();
    $router->get('/only', static fn (): string => 'ok');

    $exception = T::throws(static fn (): Response => dispatchTo($router, 'POST', '/only'), HttpException::class);

    T::same(405, $exception->statusCode());
});
