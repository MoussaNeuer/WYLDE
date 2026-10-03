<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Envoi de la réponse HTTP. Aucun contenu n'est produit ici :
 * tout passe par send() pour garantir un point de sortie unique.
 */
final class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    private int $status = 200;

    private string $content = '';

    public static function make(string $content = '', int $status = 200): self
    {
        $response = new self();
        $response->content = $content;
        $response->status  = $status;

        return $response;
    }

    public static function html(string $html, int $status = 200): self
    {
        $response = self::make($html, $status);
        $response->setHeader('Content-Type', 'text/html; charset=UTF-8');

        return $response;
    }

    /** @param array<string, mixed>|list<mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        $response = self::make(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            $status
        );
        $response->setHeader('Content-Type', 'application/json; charset=UTF-8');

        return $response;
    }

    public static function redirect(string $url, int $status = 302): self
    {
        $response = self::make('', $status);
        $response->setHeader('Location', $url);

        return $response;
    }

    public static function text(string $text, int $status = 200): self
    {
        $response = self::make($text, $status);
        $response->setHeader('Content-Type', 'text/plain; charset=UTF-8');

        return $response;
    }

    public static function noContent(int $status = 204): self
    {
        return self::make('', $status);
    }

    public static function download(string $content, string $filename, string $mime = 'application/octet-stream'): self
    {
        $response = self::make($content, 200);
        $response->setHeader('Content-Type', $mime);
        $response->setHeader('Content-Disposition', 'attachment; filename="' . basename($filename) . '"');

        return $response;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }

        return $this;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        echo $this->content;
    }
}
