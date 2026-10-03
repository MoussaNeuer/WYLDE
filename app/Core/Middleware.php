<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Classe de base des middlewares : l'implémentation par défaut laisse
 * passer la requête.
 */
abstract class Middleware implements MiddlewareInterface
{
    public function handle(Request $request): ?Response
    {
        return $this->process($request);
    }

    /** @return Response|null */
    abstract protected function process(Request $request): ?Response;

    /** URL de connexion admin. */
    protected function adminLoginUrl(): string
    {
        return url('/admin/login');
    }

    /** URL de connexion client. */
    protected function clientLoginUrl(): string
    {
        return url('/login');
    }

    /** Conserve la destination pour y revenir après connexion. */
    protected function storeIntendedUrl(Request $request): void
    {
        if ($request->method() === 'GET' && $request->path() !== '/login') {
            Session::set('_intended_url', $request->path() . self::queryOf($request));
        }
    }

    private static function queryOf(Request $request): string
    {
        $query = $_SERVER['QUERY_STRING'] ?? '';

        return is_string($query) && $query !== '' ? '?' . $query : '';
    }
}
