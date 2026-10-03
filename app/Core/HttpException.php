<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Erreur HTTP transportable jusqu'au ErrorHandler.
 * Le message destiné au visiteur est distinct du détail technique loggé.
 */
class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $publicMessage = '',
        private readonly ?string $logContext = null,
    ) {
        parent::__construct($publicMessage !== '' ? $publicMessage : self::defaultMessage($statusCode), $statusCode);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function publicMessage(): string
    {
        return $this->getMessage();
    }

    public function logContext(): ?string
    {
        return $this->logContext;
    }

    public static function notFound(string $message = ''): self
    {
        return new self(404, $message !== '' ? $message : __('errors.not_found'), 'Route introuvable');
    }

    public static function forbidden(string $message = ''): self
    {
        return new self(403, $message !== '' ? $message : __('errors.forbidden'), 'Accès refusé');
    }

    public static function unauthorized(string $message = ''): self
    {
        return new self(401, $message !== '' ? $message : __('errors.unauthorized'), 'Non authentifié');
    }

    public static function tooManyRequests(string $message = ''): self
    {
        return new self(429, $message !== '' ? $message : __('errors.too_many_requests'), 'Quota dépassé');
    }

    public static function badRequest(string $message = ''): self
    {
        return new self(400, $message !== '' ? $message : __('errors.bad_request'), 'Requête invalide');
    }

    public static function defaultMessage(int $status): string
    {
        return match ($status) {
            400     => 'Requête invalide.',
            401     => 'Authentification requise.',
            403     => 'Accès refusé.',
            404     => 'Page introuvable.',
            405     => 'Méthode non autorisée.',
            429     => 'Trop de requêtes, réessayez plus tard.',
            default => 'Une erreur est survenue.',
        };
    }
}
