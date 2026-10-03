<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Gestion centralisée des erreurs et exceptions.
 *
 * Principe (cf. §13.5) : le détail technique va dans les logs, le
 * visiteur ne reçoit qu'un message neutre. En production, aucun stack
 * trace n'est exposé.
 */
final class ErrorHandler
{
    public static function register(): void
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);

        // Les erreurs non interceptées doivent aussi finir dans le log.
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }

        $level = match ($severity) {
            E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => 'critical',
            E_RECOVERABLE_ERROR, E_USER_DEPRECATED, E_DEPRECATED                => 'warning',
            default                                                            => 'error',
        };

        Logger::log($level, "{$message} | {$file}:{$line}");

        return true;
    }

    public static function handleException(Throwable $e): void
    {
        if ($e instanceof HttpException) {
            self::renderHttpException($e);

            return;
        }

        Logger::exception($e, 'Unhandled exception');

        self::renderServerError($e);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }

        Logger::critical("Fatal au shutdown : {$error['message']} | {$error['file']}:{$error['line']}");
    }

    private static function renderHttpException(HttpException $e): void
    {
        $request = Request::capture();

        if ($request->wantsJson()) {
            Response::json(
                ['ok' => false, 'error' => $e->publicMessage()],
                $e->statusCode()
            )->send();

            return;
        }

        $layout = $e->statusCode() === 404 ? 'layouts/shop' : null;

        try {
            Response::html(
                View::render('errors/error', [
                    'status'  => $e->statusCode(),
                    'message' => $e->publicMessage(),
                ], $layout),
                $e->statusCode()
            )->send();
        } catch (Throwable) {
            Response::html(self::plainError($e->statusCode(), $e->publicMessage()), $e->statusCode())->send();
        }
    }

    private static function renderServerError(Throwable $e): void
    {
        $request = Request::capture();

        if ($request->wantsJson()) {
            Response::json(['ok' => false, 'error' => __('errors.server')], 500)->send();

            return;
        }

        // Trace complète uniquement en développement.
        $details = Config::isDebug()
            ? sprintf(
                "%s: %s\n%s:%d\n\n%s",
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            )
            : null;

        try {
            Response::html(
                View::render('errors/error', [
                    'status'  => 500,
                    'message' => __('errors.server'),
                    'details' => $details,
                ], 'layouts/shop'),
                500
            )->send();
        } catch (Throwable) {
            Response::html(self::plainError(500, __('errors.server')), 500)->send();
        }
    }

    private static function plainError(int $status, string $message): string
    {
        return sprintf(
            '<!doctype html><html lang="fr"><meta charset="utf-8">'
            . '<title>%1$d</title>'
            . '<body style="background:#000;color:#fff;font-family:system-ui,sans-serif;'
            . 'display:flex;align-items:center;justify-content:center;height:100vh;margin:0">'
            . '<div style="text-align:center">'
            . '<h1 style="font-size:4rem;margin:0">%1$d</h1>'
            . '<p style="color:#A7A7A7">%2$s</p>'
            . '<a href="/" style="color:#fff">Retour à l\'accueil</a>'
            . '</div></body></html>',
            $status,
            htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        );
    }
}
