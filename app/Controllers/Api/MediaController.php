<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Config;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;

/**
 * Sert les médias produits stockés hors document root.
 *
 * Les fichiers vivent dans storage/uploads et ne sont jamais exposés
 * directement : chaque chemin est validé puis-existence vérifié, ce qui
 * empêche la traversée de répertoire via « .. » ou un chemin absolu.
 */
final class MediaController extends Controller
{
    /** Types réellement servis, avec leur Content-Type. */
    private const TYPES = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
    ];

    public function show(Request $request): Response
    {
        $path = $this->sanitizePath((string) $request->routeParam('path', ''));

        if ($path === null) {
            return $this->notFound();
        }

        $base = rtrim((string) Config::get('app.paths.uploads', ''), '/\\');
        $file = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

        // Garde-fou : le fichier réel doit rester sous storage/uploads.
        $realBase = realpath($base);
        $realFile = realpath($file);

        if ($realBase === false || $realFile === false || !is_file($realFile)) {
            return $this->notFound();
        }

        if (!str_starts_with($realFile, $realBase . DIRECTORY_SEPARATOR)) {
            Logger::warning('Media traversal tentée', ['path' => $path]);

            return $this->notFound();
        }

        $extension = strtolower((string) pathinfo($realFile, PATHINFO_EXTENSION));

        if (!isset(self::TYPES[$extension])) {
            return $this->notFound();
        }

        $contents = file_get_contents($realFile);

        if ($contents === false) {
            return $this->notFound();
        }

        return Response::make($contents)
            ->setHeader('Content-Type', self::TYPES[$extension])
            // Les médias sont immuables : nom horodaté à l'upload.
            ->setHeader('Cache-Control', 'public, max-age=31536000, immutable')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
    }

    /**
     * Normalise et valide un chemin relatif de média.
     * Retourne null si le chemin contient des segments interdits.
     */
    private function sanitizePath(string $path): ?string
    {
        $path = trim($path);

        if ($path === '') {
            return null;
        }

        // Refus des chemins absolus Windows/Unix et des traversées.
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            return null;
        }

        if (preg_match('#^[a-zA-Z]:[\\\\/]#', $path) === 1 || str_starts_with($path, '/')) {
            return null;
        }

        // Normalise les séparateurs et retire les segments vides.
        $segments = array_values(array_filter(
            preg_split('#[\\\\/]+#', $path) ?: [],
            static fn (string $segment): bool => $segment !== '' && $segment !== '.'
        ));

        if ($segments === []) {
            return null;
        }

        return implode('/', $segments);
    }

    private function notFound(): Response
    {
        return Response::make('', 404)
            ->setHeader('Content-Type', 'text/plain; charset=UTF-8');
    }
}
