<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * Classe de base des contrôleurs.
 *
 * Centralise ce qui est commun à la vitrine et au back-office :
 * données communes aux vues, redirections, réponses JSON.
 */
abstract class Controller
{
    /**
     * Données injectées dans toutes les vues rendues par ce contrôleur.
     *
     * @var array<string, mixed>
     */
    protected array $shared = [];

    /** Applique des données partagées aux prochaines vues. */
    protected function share(array $data): void
    {
        $this->shared = array_merge($this->shared, $data);
    }

    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = [], ?string $layout = 'layouts/shop', int $status = 200): Response
    {
        return Response::html(
            View::render($template, array_merge($this->shared, $data), $layout),
            $status
        );
    }

    protected function redirect(string $path, int $status = 302): Response
    {
        return Response::redirect(str_starts_with($path, 'http') ? $path : url($path), $status);
    }

    protected function back(): Response
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');

        if ($referer === '' || !str_starts_with($referer, (string) config('app.url', ''))) {
            return $this->redirect('/');
        }

        return Response::redirect($referer);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string>    $errors
     */
    protected function redirectWithErrors(string $path, array $errors, array $oldInput = []): Response
    {
        \App\Core\Session::flashErrors($errors, $oldInput);

        return $this->redirect($path);
    }

    protected function redirectWithSuccess(string $path, string $message): Response
    {
        \App\Core\Session::flashSuccess($message);

        return $this->redirect($path);
    }

    /** @param array<string, mixed> $data */
    protected function json(array $data, int $status = 200): Response
    {
        return Response::json(array_merge(['ok' => $status < 400], $data), $status);
    }

    /**
     * Paramètre d'identifiant validé numériquement.
     * Le routeur garantit déjà le format ; on revalide par prudence.
     */
    protected function id(Request $request, string $param = 'id'): int
    {
        $value = $request->routeParam($param);

        if ($value === null || !ctype_digit($value)) {
            abort(404);
        }

        return (int) $value;
    }

    protected function page(Request $request, int $default = 1, int $perPage = 20): int
    {
        $page = $request->int('page', $default);

        return $page > 0 ? $page : $default;
    }
}
