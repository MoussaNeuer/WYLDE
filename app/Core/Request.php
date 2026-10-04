<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Représentation immuable de la requête HTTP courante.
 */
final class Request
{
    /** @var array<string, mixed> */
    private array $query;

    /** @var array<string, mixed> */
    private array $body;

    /** @var array<string, mixed> */
    private array $files;

    /** @var array<string, string> */
    private array $headers;

    /** @var array<string, string> */
    private array $routeParams = [];

    /**
     * Derniers paramètres de route définis par le routeur.
     *
     * Request::capture() crée une instance neuve : les helpers comme
     * current_route(), appelés depuis une vue, ne verraient donc jamais
     * la route courante. On conserve la dernière définition ici.
     *
     * @var array<string, string>
     */
    private static array $lastRouteParams = [];

    private function __construct()
    {
    }

    public static function capture(): self
    {
        $request = new self();

        $request->query   = $_GET;
        $request->body    = self::captureBody();
        $request->files   = self::normalizeFiles($_FILES);
        $request->headers = self::captureHeaders();

        return $request;
    }

    /**
     * Corps de la requête. Les payloads JSON (Fetch/AJAX) sont décodés
     * dans le même espace que $_POST afin d'unifier la lecture.
     *
     * @return array<string, mixed>
     */
    private static function captureBody(): array
    {
        if ($_POST !== []) {
            return $_POST;
        }

        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        if (!str_contains($contentType, 'application/json')) {
            return [];
        }

        $raw = file_get_contents('php://input');

        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function method(): string
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        // Support des formulaires HTML : _method=PUT|DELETE|PATCH
        if ($method === 'POST') {
            $override = strtoupper((string) ($this->body['_method'] ?? ''));

            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }

        return $method;
    }

    /**
     * Chemin de la requête, relatif à la racine publique.
     *
     * Le préfixe du dossier public est retiré afin que les routes restent
     * identiques dans un sous-dossier comme /WYLDE/public/ (cf. .htaccess).
     * La chaîne always commence par « / » et ne se termine pas par « / »,
     * sauf pour la racine.
     */
    public function path(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $uri = explode('?', $uri, 2)[0];

        $base = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));

        if ($base !== '' && $base !== '/' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $uri = '/' . ltrim(rawurldecode($uri), '/');
        $uri = rtrim($uri, '/');

        return $uri === '' ? '/' : $uri;
    }

    /** URL absolue de la requête courante. */
    public function fullUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host   = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return $scheme . '://' . $host . ($_SERVER['REQUEST_URI'] ?? '/');
    }

    // ── Entrées ─────────────────────────────────────────────────────────

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->query);
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);

        return $value !== null && $value !== '' && $value !== [];
    }

    public function str(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'on', 'yes', 'true'], true);
    }

    /** @return array<int|string, mixed> */
    public function array(string $key): array
    {
        $value = $this->input($key, []);

        return is_array($value) ? $value : [];
    }

    /** Le corps prime sur la query string. */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    // ── Fichiers ────────────────────────────────────────────────────────

    /**
     * Fichier téléversé pour un champ donné.
     *
     * Un champ simple renvoie l'entrée unique ; un champ multiple
     * (images[]) renvoie la liste des entrées, déjà transposée.
     *
     * @return array<string, mixed>|array<int, array<string, mixed>>|null
     */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) && $file !== [] ? $file : null;
    }

    /** @return array<string, mixed> */
    public function files(): array
    {
        return $this->files;
    }

    public function hasFile(string $key): bool
    {
        $file = $this->file($key);

        if ($file === null) {
            return false;
        }

        // Liste d'entrées (champ multiple) : au moins un envoi valide.
        if (!isset($file['name'])) {
            foreach ($file as $single) {
                if (is_array($single) && (int) ($single['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    return true;
                }
            }

            return false;
        }

        return (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    }

    /**
     * $_FILES arrive sous forme de tableaux parallèles pour les champs
     * multiples : on transpose chaque entrée en tableau associatif et on
     * écarte les champs laissés vides par le navigateur.
     *
     * @param array<string, mixed> $files
     * @return array<string, mixed>
     */
    private static function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $field => $entry) {
            if (!is_array($entry) || !isset($entry['name'])) {
                continue;
            }

            if (!is_array($entry['name'])) {
                if ((int) ($entry['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $normalized[(string) $field] = $entry;
                }

                continue;
            }

            $list = [];

            foreach (array_keys($entry['name']) as $index) {
                $single = [];

                foreach ($entry as $property => $values) {
                    $single[(string) $property] = is_array($values) ? ($values[$index] ?? null) : $values;
                }

                if ((int) ($single['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $list[] = $single;
            }

            if ($list !== []) {
                $normalized[(string) $field] = $list;
            }
        }

        return $normalized;
    }

    // ── En-têtes et réseau ─────────────────────────────────────────────

    public function header(string $key, ?string $default = null): ?string
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    /** @return array<string, string> */
    private static function captureHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $server => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (str_starts_with($server, 'HTTP_')) {
                $name = str_replace('_', '-', substr($server, 5));
            } elseif (in_array($server, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = str_replace('_', '-', $server);
            } else {
                continue;
            }

            $headers[strtolower($name)] = $value;
        }

        return $headers;
    }

    public function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    /**
     * Empreinte de l'IP (HMAC SHA-256 avec la clé applicative) : permet
     * de corréler deux actions sans conserver d'adresse en clair.
     */
    public function ipHash(): ?string
    {
        $ip = $this->ip();

        if ($ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) Config::get('app.key', ''));
    }

    public function userAgent(): string
    {
        return (string) $this->header('User-Agent', '');
    }

    public function referer(): ?string
    {
        return $this->header('Referer');
    }

    public function isSecure(): bool
    {
        if (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        return strtolower((string) $this->header('X-Forwarded-Proto', '')) === 'https';
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    /** La réponse attendue est-elle du JSON ? */
    public function wantsJson(): bool
    {
        if ($this->isAjax()) {
            return true;
        }

        $accept = strtolower((string) $this->header('Accept', ''));

        return str_contains($accept, 'application/json')
            || str_contains($accept, '+json')
            || $this->routeParam('_json') !== null;
    }

    // ── Paramètres de route ─────────────────────────────────────────────

    public function routeParam(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $this->routeParams)) {
            return $this->routeParams[$key];
        }

        return self::$lastRouteParams[$key] ?? $default;
    }

    /** @param array<string, string> $params */
    public function setRouteParams(array $params): void
    {
        $this->routeParams       = $params;
        self::$lastRouteParams   = $params;
    }
}