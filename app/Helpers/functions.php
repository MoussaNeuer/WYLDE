<?php

declare(strict_types=1);

/**
 * Fonctions globales utilitaires.
 *
 * Chargé par App\Core\Autoloader::loadHelpers() ou par Composer
 * (section autoload.files).
 */

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Lang;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return App\Core\Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('e')) {
    /**
     * Échappement HTML. À utiliser pour TOUTE donnée affichée
     * (cf. §13.3 — XSS).
     */
    function e(mixed $value): string
    {
        if ($value === null || is_bool($value) || is_array($value)) {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('__')) {
    /**
     * Traduit un libellé d'interface.
     *
     * @param array<string, string|int|float> $replace
     */
    function __(string $key, array $replace = []): string
    {
        return Lang::get($key, $replace);
    }
}

if (!function_exists('trans_choice')) {
    /**
     * Formulation au pluriel simple, sans dépendre de Symfony.
     * Clé attendue : <clé> avec .one et .other.
     *
     * @param array<string, string|int|float> $replace
     */
    function trans_choice(string $key, int $count, array $replace = []): string
    {
        $replace['count'] = $count;

        $variants = Lang::get($key . '.' . ($count > 1 ? 'other' : 'one'), $replace);

        if ($variants === $key . '.' . ($count > 1 ? 'other' : 'one')) {
            return $count . ' ' . Lang::get($key, $replace);
        }

        return $variants;
    }
}

if (!function_exists('locale')) {
    function locale(): string
    {
        return Lang::locale();
    }
}

if (!function_exists('alt_locale')) {
    function alt_locale(): string
    {
        return Lang::alternate();
    }
}

if (!function_exists('localized')) {
    /**
     * Champ localisé d'une entité : name_en si la locale est EN et la
     * colonne est renseignée, sinon le français.
     *
     * @param array<string, mixed> $row
     */
    function localized(array $row, string $field): string
    {
        return Lang::field($row, $field);
    }
}

if (!function_exists('money')) {
    /**
     * Formate un montant en FCFA.
     *
     * Les montants sont des entiers en DECIMAL(12,0) : pas de centimes,
     * donc aucune arrondi nécessaire. L'espace fine insécable évite qu'un
     * retour à la ligne coupe le montant.
     */
    function money(mixed $amount, bool $withSymbol = true): string
    {
        $normalized = money_raw($amount);

        if ($withSymbol === false) {
            return $normalized;
        }

        $symbol   = (string) Config::get('app.currency.symbol', 'FCFA');
        $position = (string) Config::get('app.currency.position', 'after');

        return $position === 'before' ? $symbol . "\u{202F}" . $normalized : $normalized . "\u{202F}" . $symbol;
    }
}

if (!function_exists('money_raw')) {
    /** Montant sans symbole, séparateurs de milliers. */
    function money_raw(mixed $amount): string
    {
        $value = is_numeric($amount) ? (float) $amount : 0.0;
        $decimals = (int) Config::get('app.currency.decimals', 0);
        $separator = (string) Config::get('app.currency.separator', "\u{202F}");

        $formatted = number_format($value, $decimals, '.', ' ');

        return str_replace(' ', $separator, $formatted);
    }
}

if (!function_exists('money_int')) {
    /**
     * Convertit un montant en entier pour le calcul.
     *
     * DECIMAL(12,0) => aucune partie décimale, donc (int) est exact et
     * évite toute passage par un float.
     */
    function money_int(mixed $amount): int
    {
        return is_numeric($amount) ? (int) round((float) $amount) : 0;
    }
}

if (!function_exists('url')) {
    /**
     * Construit une URL absolue à partir de l'URL de base configurée,
     * en conservant le préfixe du dossier public.
     */
    function url(string $path = ''): string
    {
        $base = rtrim((string) Config::get('app.url', ''), '/');
        $path = '/' . ltrim($path, '/');

        if ($path === '/' && $base !== '') {
            return $base . '/';
        }

        return $base . ($path === '/' ? '/' : rtrim($path, '/'));
    }
}

if (!function_exists('pagination_url')) {
    /**
     * Construit l'URL de la page $page en préservant la query string
     * courante (filtres, tri, recherche) de la requête en cours.
     *
     * Le composant resources/views/components/pagination.php l'utilise
     * pour ses liens sans dépendre de la couche contrôleur.
     */
    function pagination_url(int $page): string
    {
        $request = Request::capture();
        $query   = $request->all();

        // Les paramètres de pagination pilotés en URL restent pris en compte.
        foreach (['page', 'p'] as $param) {
            unset($query[$param]);
        }

        $query['page'] = max(1, $page);
        ksort($query);

        $path = '/' . ltrim($request->path(), '/');

        return url($path === '/' ? '/' : rtrim($path, '/')) . '?' . http_build_query($query);
    }
}

if (!function_exists('asset')) {
    /** URL d'un asset public, avec cache-busting en développement. */
    function asset(string $path): string
    {
        $path = ltrim($path, '/');

        if (!Config::isDebug()) {
            return url($path);
        }

        $file = rtrim((string) Config::get('app.paths.public', ''), '/\\') . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $path);

        $version = is_file($file) ? substr((string) filemtime($file), -6) : 'dev';

        return url($path) . '?v=' . $version;
    }
}

if (!function_exists('upload_url')) {
    /**
     * URL publique d'un média stocké hors document root.
     *
     * Les fichiers vivent dans storage/uploads et ne sont pas servis
     * directement par Apache : ils transitent par /media/{chemin},
     * qui vérifie le chemin et renvoie le fichier (cf. §13.4).
     */
    function upload_url(?string $path): string
    {
        if ($path === null || trim($path) === '') {
            return asset('assets/images/placeholder.svg');
        }

        return url('media/' . ltrim(str_replace('\\', '/', $path), '/'));
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    /** Champ caché à insérer dans chaque formulaire POST. */
    function csrf_field(): string
    {
        $name = (string) Config::get('security.csrf.token_name', '_token');

        return '<input type="hidden" name="' . e($name) . '" value="' . e(Csrf::token()) . '">';
    }
}

if (!function_exists('method_field')) {
    /** Champ caché pour _method=PUT|DELETE depuis un formulaire HTML. */
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('old')) {
    /**
     * Ancienne saisie après un échec de validation.
     * Évite de renvoyer la valeur duSubmitted mal échappée par erreur.
     */
    function old(string $key, mixed $default = ''): string
    {
        $old = Session::pullOldInput();

        if ($old === []) {
            return e($default);
        }

        View::share('__old', $old);

        return e($old[$key] ?? $default);
    }
}

if (!function_exists('old_raw')) {
    /** @return array<string, mixed> */
    function old_raw(): array
    {
        return Session::pullOldInput();
    }
}

if (!function_exists('error_for')) {
    function error_for(string $field): ?string
    {
        $errors = Session::pullErrors();

        View::share('__errors', $errors);

        $message = $errors[$field] ?? null;

        return is_string($message) ? $message : null;
    }
}

if (!function_exists('has_error')) {
    function has_error(string $field): bool
    {
        $errors = Session::pullErrors();

        View::share('__errors', $errors);

        return isset($errors[$field]);
    }
}

if (!function_exists('flash')) {
    /** @return array<int, array{type: string, message: string}> */
    function flash(): array
    {
        return Session::pullFlash();
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $status = 302): App\Core\Response
    {
        $target = str_starts_with($path, 'http') ? $path : url($path);

        return App\Core\Response::redirect($target, $status);
    }
}

if (!function_exists('back')) {
    function back(): App\Core\Response
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');

        if ($referer === '' || !str_starts_with($referer, (string) Config::get('app.url', ''))) {
            return redirect('/');
        }

        return App\Core\Response::redirect($referer);
    }
}

if (!function_exists('route')) {
    function route(string $name, array $params = []): string
    {
        $url = '/' . trim($name, '/');

        foreach ($params as $key => $value) {
            $url = str_replace('{' . $key . '}', rawurlencode((string) $value), $url);
        }

        return url($url);
    }
}

if (!function_exists('current_route')) {
    function current_route(): ?string
    {
        return Request::capture()->routeParam('_route');
    }
}

if (!function_exists('is_active')) {
    /** Classe CSS pour lier l'élément de navigation courant. */
    function is_active(string $pattern, string $class = 'active'): string
    {
        $current = current_route() ?? '';

        if ($pattern === $current) {
            return $class;
        }

        return str_starts_with($current, rtrim($pattern, '/')) && $pattern !== '/' ? $class : '';
    }
}

if (!function_exists('query_string')) {
    /**
     * Reconstruit la query string en modifiant certains paramètres.
     * Utilisé pour la pagination et les tris (cf. §17).
     *
     * @param array<string, string|int|null> $overrides
     */
    function query_string(array $overrides = []): string
    {
        $params = array_merge($_GET, $overrides);

        $params = array_filter(
            $params,
            static fn (mixed $v): bool => $v !== null && $v !== '' && $v !== false
        );

        return $params === [] ? '' : '?' . http_build_query($params);
    }
}

if (!function_exists('slugify')) {
    /** Slug ASCII stable pour les URLs. */
    function slugify(string $value): string
    {
        if (function_exists('transliterator_transliterate')) {
            $converted = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value);

            if (is_string($converted) && $converted !== '') {
                $value = $converted;
            }
        } else {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

            if (is_string($ascii) && $ascii !== '') {
                $value = $ascii;
            }
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }
}

if (!function_exists('unique_slug')) {
    /**
     * Slug unique en base pour la table et la colonne fournies.
     * Ajoute un suffixe -2, -3... en cas de collision.
     */
    function unique_slug(string $table, string $slug, ?int $ignoreId = null): string
    {
        $base   = $slug === '' ? 'item' : $slug;
        $suffix = 1;

        do {
            $candidate = $suffix === 1 ? $base : $base . '-' . $suffix;
            $suffix++;

            $sql    = "SELECT id FROM `{$table}` WHERE slug = :slug";
            $params = ['slug' => $candidate];

            if ($ignoreId !== null) {
                $sql            .= ' AND id <> :ignore_id';
                $params['ignore_id'] = $ignoreId;
            }

            $exists = App\Core\Database::selectValue($sql . ' LIMIT 1', $params);
        } while ($exists !== null && $suffix < 1000);

        return $candidate;
    }
}

if (!function_exists('view')) {
    /**
     * Rend une vue et renvoie une Response HTML.
     *
     * @param array<string, mixed> $data
     */
    function view(string $template, array $data = [], ?string $layout = null, int $status = 200): App\Core\Response
    {
        return App\Core\Response::html(
            View::render($template, $data, $layout),
            $status
        );
    }
}

if (!function_exists('view_partial')) {
    /**
     * Inclut un autre template à cet endroit (composant, partiel, layout).
     * À utiliser depuis les vues plutôt que View::include(), afin de ne
     * pas dépendre d'un namespace dans un fichier inclus.
     *
     * @param array<string, mixed> $data
     */
    function view_partial(string $template, array $data = []): void
    {
        echo View::render($template, $data);
    }
}

if (!function_exists('component')) {
    /**
     * Rend un composant de resources/views/components.
     *
     * @param array<string, mixed> $data
     */
    function component(string $name, array $data = []): void
    {
        echo View::render('components/' . $name, $data);
    }
}

if (!function_exists('section_if')) {
    /**
     * Affiche une section du layout si elle a été alimentée.
     * Évite les lignes vides dans le HTML.
     */
    function section_if(string $name): void
    {
        if (!View::hasSection($name)) {
            return;
        }

        echo View::section($name);
    }
}

if (!function_exists('json_response')) {
    /**
     * @param array<string, mixed> $data
     */
    function json_response(array $data, int $status = 200): App\Core\Response
    {
        return App\Core\Response::json(
            array_merge(['ok' => $status < 400], $data),
            $status
        );
    }
}

if (!function_exists('abort')) {
    function abort(int $status, string $message = ''): never
    {
        throw new App\Core\HttpException($status, $message);
    }
}

if (!function_exists('abort_if')) {
    function abort_if(bool $condition, int $status, string $message = ''): void
    {
        if ($condition) {
            abort($status, $message);
        }
    }
}

if (!function_exists('auth')) {
    function auth(): ?App\Models\User
    {
        return App\Core\Auth::user();
    }
}

if (!function_exists('auth_check')) {
    function auth_check(): bool
    {
        return App\Core\Auth::check();
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return App\Core\Auth::isAdmin();
    }
}

if (!function_exists('old_input')) {
    function old_input(string $key, mixed $default = null): mixed
    {
        return Session::get('__old.' . $key, $default);
    }
}

if (!function_exists('setting')) {
    /**
     * Lit un paramètre de boutique depuis la table settings,
     * avec repli sur la configuration.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return App\Services\SettingsService::get($key, $default);
    }
}

if (!function_exists('stock_status')) {
    /**
     * Statut de stock d'un produit.
     *
     * @return array{level: string, label: string}
     */
    function stock_status(int $stock): array
    {
        $threshold = (int) Config::get('app.stock.low_threshold', 5);

        if ($stock <= 0) {
            return ['level' => 'out', 'label' => __('product.stock_out')];
        }

        if ($stock <= $threshold) {
            return ['level' => 'low', 'label' => __('product.stock_low', ['count' => $stock])];
        }

        return ['level' => 'in', 'label' => __('product.stock_in')];
    }
}

if (!function_exists('product_payload')) {
    /**
     * Représentation JSON d'un produit, partagée par l'API recherche et
     * l'API produits du back-office. Les montants sont des entiers : la
     * mise en forme reste à la charge du client (§4.2).
     */
    function product_payload(\App\Models\Product $product): array
    {
        $stock = $product->totalStock();
        $image = $product->primaryImage();

        return [
            'id'          => (int) $product->id(),
            'name'        => $product->localizedName(),
            'slug'        => (string) $product->slug,
            'sku'         => (string) $product->sku,
            'price'       => (int) $product->effectivePrice(),
            'has_discount' => $product->hasDiscount(),
            'image'       => $image === null ? null : upload_url($image),
            'stock'       => $stock,
            'stock_level' => stock_status($stock)['level'],
            'url'         => url('/product/' . $product->slug),
        ];
    }
}

if (!function_exists('active_class')) {
    function active_class(string $pattern, string $class = 'is-active'): string
    {
        return is_active($pattern, $class);
    }
}

if (!function_exists('str_limit')) {
    function str_limit(?string $value, int $limit = 120, string $end = '…'): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        return mb_strlen($value, 'UTF-8') <= $limit
            ? $value
            : rtrim(mb_substr($value, 0, $limit, 'UTF-8')) . $end;
    }
}

if (!function_exists('now')) {
    function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $date, string $format = 'd/m/Y'): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        $timestamp = strtotime($date);

        return $timestamp === false ? '' : date($format, $timestamp);
    }
}
