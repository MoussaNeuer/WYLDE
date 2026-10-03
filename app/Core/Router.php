<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Routeur HTTP : enregistrement des routes, groupes avec préfixe et
 * middleware, dispatch avec extraction des paramètres de route.
 *
 * Syntaxe des paramètres : /product/{slug}, /admin/orders/{id}/edit
 *
 * Ordre de résolution : la première route qui matche gagne. Il faut donc
 * déclarer les routes littérales AVANT les routes paramétrées
 * (/admin/products/create avant /admin/products/{id}).
 */
final class Router
{
    /**
     * @var array<int, array{
     *   method: string, pattern: string, regex: string,
     *   params: array<int, string>, handler: mixed,
     *   middleware: array<int, string>, name: string
     * }>
     */
    private array $routes = [];

    /** @var array<int, array{prefix: string, middleware: array<int, string>}> */
    private array $groupStack = [];

    private string $groupPrefix = '';

    /** @var array<int, string> */
    private array $groupMiddleware = [];

    /** @var (callable(Request): Response)|null */
    private $fallback = null;

    private ?string $currentRouteName = null;

    public function get(string $pattern, mixed $handler): self
    {
        return $this->addRoute('GET', $pattern, $handler);
    }

    public function post(string $pattern, mixed $handler): self
    {
        return $this->addRoute('POST', $pattern, $handler);
    }

    public function put(string $pattern, mixed $handler): self
    {
        return $this->addRoute('PUT', $pattern, $handler);
    }

    public function patch(string $pattern, mixed $handler): self
    {
        return $this->addRoute('PATCH', $pattern, $handler);
    }

    public function delete(string $pattern, mixed $handler): self
    {
        return $this->addRoute('DELETE', $pattern, $handler);
    }

    /**
     * Ajoute un middleware à la dernière route enregistrée.
     *
     * @param array<int, string> $middleware
     *
     * Routes littérales : $router->post('/contact', …)->middleware(['csrf']);
     */
    public function middleware(array $middleware): self
    {
        $index = array_key_last($this->routes);

        if ($index === null) {
            throw new \RuntimeException('middleware() appelé avant tout enregistrement de route.');
        }

        // Les middlewares du groupe passent en premier, puis ceux de la route.
        $this->routes[$index]['middleware'] = array_values(array_unique(
            array_merge($this->routes[$index]['middleware'], $middleware)
        ));

        return $this;
    }

    /**
     * Contrainte de validation sur un paramètre de route.
     *
     * S'applique à la dernière route enregistrée :
     *   $router->get('/locale/{code}', …)->where('code', 'fr|en');
     *
     * @param string $pattern Motif regex, sans ancres ni groupe : il est
     *                        enveloppé automatiquement dans un groupe capturant.
     */
    public function where(string $param, string $pattern): self
    {
        $index = array_key_last($this->routes);

        if ($index === null) {
            throw new \RuntimeException('where() appelé avant tout enregistrement de route.');
        }

        if (!in_array($param, $this->routes[$index]['params'], true)) {
            throw new \RuntimeException(
                "Le paramètre {{$param}} n'existe pas dans la route {$this->routes[$index]['pattern']}."
            );
        }

        $this->routes[$index]['constraints'][$param] = $pattern;
        $this->routes[$index]['regex'] = $this->compileRegex(
            $this->routes[$index]['pattern'],
            $this->routes[$index]['constraints']
        );

        return $this;
    }

    /**
     * Ouvre un groupe de routes.
     *
     * @param array<int, string> $middleware
     */
    public function group(array $middleware = [], string $prefix = ''): self
    {
        $this->groupStack[] = ['prefix' => $prefix, 'middleware' => $middleware];

        $this->groupPrefix     = $this->currentPrefix();
        $this->groupMiddleware = $middleware;

        return $this;
    }

    /** Referme le dernier groupe ouvert. */
    public function endGroup(): self
    {
        array_pop($this->groupStack);

        $this->groupPrefix     = $this->currentPrefix();
        $this->groupMiddleware = [];

        return $this;
    }

    /** @param callable(Request): Response $handler */
    public function fallback(callable $handler): self
    {
        $this->fallback = $handler;

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path   = $request->path();
        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $allowed[$route['method']] = true;

            if ($route['method'] !== $method) {
                continue;
            }

            $params = [];
            foreach ($route['params'] as $i => $name) {
                $params[$name] = $matches[$i + 1] ?? '';
            }

            $params['_route']  = $route['name'];
            $params['_method'] = $method;

            $request->setRouteParams($params);
            $this->currentRouteName = $route['name'];

            return $this->runPipeline($route, $request);
        }

        if ($this->fallback !== null) {
            return ($this->fallback)($request);
        }

        // Le chemin existe, mais pas avec cette méthode.
        if ($allowed !== []) {
            throw new HttpException(405);
        }

        throw HttpException::notFound();
    }

    private function addRoute(string $method, string $pattern, mixed $handler): self
    {
        $full = $this->groupPrefix . '/' . trim($pattern, '/');
        $full = '/' . trim($full, '/');

        $params = [];

        if (preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $full, $found) > 0) {
            $params = $found[1];
        }

        $this->routes[] = [
            'method'      => $method,
            'pattern'     => $full,
            'regex'       => $this->compileRegex($full, []),
            'params'      => $params,
            'constraints' => [],
            'handler'     => $handler,
            'middleware'  => $this->groupMiddleware,
            'name'        => trim(str_replace(['{', '}'], '', $full), '/') ?: 'home',
        ];

        return $this;
    }

    /**
     * Construit l'expression régulière d'une route en injectant les
     * contraintes déclarées via where().
     *
     * Par convention, tout paramètre nommé id, <nom>Id ou <nom>_id
     * n'accepte que des chiffres : un identifiant ne transite jamais
     * dans une requête sous une forme non numérique.
     *
     * @param array<string, string> $constraints
     */
    private function compileRegex(string $pattern, array $constraints): string
    {
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $m) use ($constraints): string {
                $name = $m[1];

                if (isset($constraints[$name])) {
                    // Le motif fourni est enveloppé dans un groupe capturant :
                    // dispatch() lit les paramètres par position ($matches[1..n]).
                    return '(' . $constraints[$name] . ')';
                }

                return self::isIdentifier($name) ? '(\d+)' : '([^/]+)';
            },
            $pattern
        );

        if (!is_string($regex)) {
            throw new \RuntimeException("Pattern de route invalide : {$pattern}");
        }

        return '#^' . $regex . '$#u';
    }

    /** Le nom du paramètre désigne-t-il un identifiant numérique ? */
    private static function isIdentifier(string $name): bool
    {
        return $name === 'id'
            || preg_match('/Id$/', $name) === 1
            || preg_match('/_id$/', $name) === 1;
    }

    private function runPipeline(array $route, Request $request): Response
    {
        foreach ($route['middleware'] as $middleware) {
            $result = MiddlewareRegistry::resolve($middleware)->handle($request);

            if ($result instanceof Response) {
                return $result;
            }
        }

        $handler = $route['handler'];
        $result  = $this->callHandler($handler, $route['pattern'], $request);

        return $this->normalizeResult($result, $route['pattern']);
    }

    private function callHandler(mixed $handler, string $pattern, Request $request): mixed
    {
        if ($handler instanceof \Closure || is_callable($handler)) {
            return $handler($request);
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);

            return $this->callClassMethod('App\\Controllers\\' . $class, $method, $pattern, $request);
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $isObject         = is_object($class);
            $className        = $isObject ? $class::class : 'App\\Controllers\\' . $class;

            return $this->callClassMethod(
                $className,
                $method,
                $pattern,
                $request,
                $isObject ? $class : null
            );
        }

        throw new \RuntimeException("Handler invalide pour la route {$pattern}");
    }

    private function callClassMethod(
        string $class,
        string $method,
        string $pattern,
        Request $request,
        ?object $instance = null
    ): mixed {
        if (!class_exists($class)) {
            throw new \RuntimeException("Contrôleur introuvable : {$class} (route {$pattern})");
        }

        $target   = $instance ?? new $class();
        $callable = [$target, $method];

        if (!is_callable($callable)) {
            throw new \RuntimeException("Action {$class}::{$method}() introuvable (route {$pattern})");
        }

        return $callable($request);
    }

    private function normalizeResult(mixed $result, string $pattern): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        if (is_string($result)) {
            return Response::html($result);
        }

        throw new \RuntimeException("Le contrôleur de {$pattern} n'a renvoyé ni Response, ni tableau, ni chaîne.");
    }

    private function currentPrefix(): string
    {
        $prefix = '';

        foreach ($this->groupStack as $group) {
            $prefix .= '/' . trim($group['prefix'], '/');
        }

        return rtrim($prefix, '/');
    }

    /** @return array<int, array<string, mixed>> */
    public function routes(): array
    {
        return $this->routes;
    }

    public function currentRouteName(): ?string
    {
        return $this->currentRouteName;
    }
}
