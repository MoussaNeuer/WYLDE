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

if (!function_exists('cart_update_plan')) {
    /**
     * Traduit le champ quantity[variant_id] du panier en plan de mise à jour.
     *
     * Le formulaire du panier envoie une quantité par ligne, et non un
     * couple (variant_id, quantité) : sans cette traduction, chaque ligne
     * était lue comme absente et la mise à jour ne touchait à rien.
     *
     * Règles :
     *  - une quantité nulle ou négative retire la ligne concerned ;
     *  - une quantité supérieure au stock est plafonnée, et signalée dans
     *    « capped » pour que l'appelant puisse prévenir le visiteur ;
     *  - seule variante présente dans $limits est retenue, pour qu'un champ
     *    bricolé ne crée ni ne modifie une ligne qui n'est pas au panier.
     *
     * @param array<array-key, mixed> $submitted  champ quantity[id] reçu
     * @param array<int, int>          $limits     variant_id => stock effectif
     *
     * @return array{remove: array<int, int>, set: array<int, int>, capped: array<int, int>}
     */
    function cart_update_plan(array $submitted, array $limits): array
    {
        $plan = ['remove' => [], 'set' => [], 'capped' => []];

        foreach ($submitted as $variantId => $rawQuantity) {
            $variantId = (int) $variantId;

            if (!array_key_exists($variantId, $limits)) {
                continue;
            }

            $quantity = is_numeric($rawQuantity) ? (int) $rawQuantity : 0;

            if ($quantity < 1) {
                $plan['remove'][] = $variantId;

                continue;
            }

            // Le stock nul reste commandable d'une unité : la même règle que
            // Cart::setQuantity(), qui plafonne à max(1, stock).
            $limit = max(1, (int) $limits[$variantId]);

            if ($quantity > $limit) {
                $plan['capped'][$variantId] = $limit;
            }

            $plan['set'][$variantId] = min($quantity, $limit);
        }

        return $plan;
    }
}

if (!function_exists('app_base_url')) {
    /**
     * URL de base absolue de l'application, sans barre oblique finale.
     *
     * Valeur de app.url (APP_URL) par défaut. Quand app.url_from_request
     * est actif, l'hôte et le préfixe public sont déduits de la requête
     * courante, ce qui rend le même code déployable à la racine d'un
     * DocumentRoot ou dans un sous-dossier. L'en-tête Host n'est retenu que
     * s'il figure dans app.trusted_hosts, sauf si cette liste est vide.
     */
    function app_base_url(): string
    {
        $base = (string) Config::get('app.url', '');

        if (Config::get('app.url_from_request', false)) {
            $request = Request::capture();
            $host    = $request->host();
            $trusted = (array) Config::get('app.trusted_hosts', []);

            // Hôte vide (CLI, tests) : impossible de deriv quoi que ce soit,
            // on garde la valeur de app.url.
            if ($host !== '' && ($trusted === [] || in_array($host, $trusted, true))) {
                $base = $request->baseUrl();
            }
        }

        return rtrim($base, '/');
    }
}

if (!function_exists('url')) {
    /**
     * Construit une URL absolue à partir de l'URL de base configurée,
     * en conservant le préfixe du dossier public.
     */
    function url(string $path = ''): string
    {
        $base = app_base_url();
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

        // En production, on sert la version minifiée si elle existe.
        // Elle est produite par tools/minify.php avant le déploiement.
        if (!Config::isDebug()) {
            if (preg_match('#\.(css|js)$#', $path) === 1) {
                $minPath = preg_replace('#\.(css|js)$#', '.min.$1', $path) ?? $path;
                $minFile = rtrim((string) Config::get('app.paths.public', ''), '/\\')
                    . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $minPath);

                if (is_file($minFile)) {
                    return url($minPath);
                }
            }

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

if (!function_exists('free_shipping_threshold')) {
    /**
     * Montant à partir duquel la livraison est offerte, en XOF.
     *
     * Le réglage vit dans l'admin (free_shipping_threshold). À 0, ou
     * négatif, la livraison offerte est désactivée : c'est le cas par
     * défaut, et ShippingZone continue alors de facturer la zone.
     */
    function free_shipping_threshold(): int
    {
        return max(0, (int) setting('free_shipping_threshold', 0));
    }
}

if (!function_exists('free_shipping_progress')) {
    /**
     * État de la livraison offerte pour un sous-total donné.
     *
     * Alimente la barre de progression du panier et du mini-panier.
     * Quand le seuil est désactivé, enabled vaut false et le reste n'a pas
     * de sens : les appelants doivent alors masquer la barre.
     *
     * @return array{enabled: bool, threshold: int, threshold_text: string, remaining: int, remaining_text: string, reached: bool, percent: int}
     */
    function free_shipping_progress(int $subtotal, ?int $threshold = null): array
    {
        $threshold ??= free_shipping_threshold();

        if ($threshold < 1) {
            return [
                'enabled'        => false,
                'threshold'      => 0,
                'threshold_text' => '',
                'remaining'      => 0,
                'remaining_text' => '',
                'reached'        => false,
                'percent'        => 0,
            ];
        }

        $remaining = max(0, $threshold - $subtotal);

        return [
            'enabled'        => true,
            'threshold'      => $threshold,
            'threshold_text' => money($threshold),
            'remaining'      => $remaining,
            'remaining_text' => money($remaining),
            'reached'        => $remaining === 0,
            // Plafonné à 100 : au-delà du seuil la barre est pleine.
            'percent'        => (int) min(100, (int) round($subtotal / $threshold * 100)),
        ];
    }
}

if (!function_exists('low_stock_threshold')) {
    /**
     * Seuil « stock bas » : en dessous, l'admin alerte et la fiche produit
     * affiche un message de rareté. Repli sur le seuil configuré.
     */
    function low_stock_threshold(): int
    {
        return max(1, (int) config('stock.low_threshold', 5));
    }
}

if (!function_exists('scarcity_message')) {
    /**
     * Message de rareté pour un stock faible : « Plus que 2 en M ».
     *
     * @param  string|null $size  taille de la variante, ou null si unique
     * @return array{level: string, text: string}|null
     */
    function scarcity_message(int $stock, ?string $size = null): ?array
    {
        if ($stock < 1) {
            return null;
        }

        $threshold = low_stock_threshold();

        if ($stock > $threshold) {
            return null;
        }

        // « UNIQUE » et les tailles vides ne devraient jamais apparaître dans
        // une phrase client : on retombe sur le message sans le nom de taille.
        $withSize = ($size !== null && $size !== '' && $size !== 'UNIQUE')
            ? (string) $size
            : null;

        return [
            'level' => $stock <= 2 ? 'critical' : 'low',
            'text'  => $withSize !== null
                ? trans_choice('product.scarcity_in_size', $stock, ['size' => $withSize])
                : trans_choice('product.scarcity_count', $stock),
        ];
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

if (!function_exists('site_credit')) {
    /**
     * Crédit du créateur du site, affiché aux visiteurs.
     *
     * Le nom et le lien se modifient dans l'admin (Paramètres), ce qui évite
     * de toucher au code pour changer de mention. Sans lien renseigné, seul
     * le nom est affiché.
     *
     * @return array{name: string, url: ?string}
     */
    function site_credit(): array
    {
        $name = trim((string) setting('credit_name', 'Jef Tech'));
        $url  = trim((string) setting('credit_url', ''));

        // On n'accepte qu'une URL http(s) : pas de javascript: ni de data:.
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = '';
        }

        return [
            'name' => $name,
            'url'  => $url !== '' ? $url : null,
        ];
    }
}

if (!function_exists('quick_variants_map')) {
    /**
     * Variantes d'une liste de produits, indexées par produit.
     *
     * Les vignettes ont besoin du stock de chaque variante pour proposer
     * l'ajout rapide. Interroger la base depuis la vignette coûterait une
     * requête par produit : sur une page de 12 produits, le navigateur
     * attendrait 12 allers-retours avant d'afficher la page. Les
     * contrôleurs appellent donc ce helper une fois et partagent le
     * résultat avec les vignettes via View::share('quick_variants', …).
     *
     * @param  array<int, \App\Models\Product|array<string, mixed>> $products
     * @return array<int, array<int, array{id:int, size:string, stock:int, available:bool}>>
     */
    function quick_variants_map(array $products): array
    {
        $ids = [];

        foreach ($products as $product) {
            $id = $product instanceof \App\Models\Product
                ? $product->id()
                : (int) ($product['id'] ?? 0);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids === [] ? [] : \App\Models\Variant::forProducts($ids);
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

if (!function_exists('size_label')) {
    /**
     * Libellé d'une taille pour l'affichage client.
     *
     * « UNIQUE » n'est qu'un marqueur interne : le client doit lire
     * « Taille unique », pas un code technique.
     */
    function size_label(?string $size): string
    {
        if ($size === null || $size === '' || $size === 'UNIQUE') {
            return __('product.size_unique');
        }

        return $size;
    }
}

if (!function_exists('product_payload')) {
    /**
     * Représentation JSON d'un produit, partagée par l'API recherche et
     * l'API produits du back-office.
     *
     * Les montants sont des entiers, mais la texte est fourni également :
     * les former en JavaScript reviendrait à réimplémenter le séparateur
     * de milliers et le suffixe de devise dans deux langues.
     */
    function product_payload(\App\Models\Product $product): array
    {
        $stock = $product->totalStock();
        $image = $product->primaryImage();
        $price = (int) $product->effectivePrice();
        $list  = (int) $product->price;

        return [
            'id'          => (int) $product->id(),
            'name'        => $product->localizedName(),
            'slug'        => (string) $product->slug,
            'sku'         => (string) $product->sku,
            'price'       => $price,
            'price_text'  => money($price),
            'list_price'  => $product->hasDiscount() ? $list : null,
            'list_price_text' => $product->hasDiscount() ? money($list) : null,
            'has_discount' => $product->hasDiscount(),
            'image'       => $image === null ? null : upload_url($image),
            'stock'       => $stock,
            'stock_level' => stock_status($stock)['level'],
            'stock_text'  => stock_status($stock)['label'],
            'url'         => url('/product/' . $product->slug),
        ];
    }
}

if (!function_exists('card_images_map')) {
    /**
     * Deuxième image de chaque produit, indexée par identifiant.
     *
     * La carte affiche cette image au survol (bureau uniquement). La
     * lecture se fait en une requête pour toute la page : l'appeler pour
     * chaque carte coûterait un aller-retour par produit, juste pour
     * savoir s'il existe une photo secondaire.
     *
     * @param  array<int, \App\Models\Product|array<string, mixed>> $products
     * @return array<int, string|null>
     */
    function card_images_map(array $products): array
    {
        $ids = [];

        foreach ($products as $product) {
            $id = $product instanceof \App\Models\Product
                ? $product->id()
                : (int) ($product['id'] ?? 0);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $rows = \App\Core\Database::select(
            "SELECT product_id, path
             FROM `product_images`
             WHERE product_id IN ($placeholders)
             ORDER BY is_primary DESC, sort_order ASC, id ASC",
            $ids
        );

        // On garde la première image de chaque produit, en ignorant celle
        // déjà affichée : celle-ci est généralement la principale.
        $seenPrimary = [];
        $map = [];

        foreach ($rows as $row) {
            $productId = (int) $row['product_id'];

            if (!isset($seenPrimary[$productId])) {
                $seenPrimary[$productId] = true;

                continue;
            }

            if (!isset($map[$productId])) {
                $map[$productId] = (string) $row['path'];
            }
        }

        // La photo principale connue de la carte permet d'ignorer aussi
        // celle-ci : sans cela, on afficherait deux fois la même image.
        foreach ($products as $product) {
            $id = $product instanceof \App\Models\Product
                ? $product->id()
                : (int) ($product['id'] ?? 0);

            if ($id < 1 || !isset($map[$id])) {
                continue;
            }

            $primary = $product instanceof \App\Models\Product
                ? $product->primaryImage()
                : ($product['image_path'] ?? null);

            if (is_string($primary) && $primary === $map[$id]) {
                unset($map[$id]);
            }
        }

        return $map;
    }
}

if (!function_exists('render_product_cards')) {
    /**
     * Rend une liste de cartes produit dans une chaîne.
     *
     * Utilisé par « Charger plus » et par la page des favoris : le HTML
     * vient du même composant que la grille rendue par le serveur, donc
     * le JavaScript ne duplique pas la mise en page de la carte.
     *
     * @param array<int, \App\Models\Product> $products
     */
    function render_product_cards(array $products): string
    {
        if ($products === []) {
            return '';
        }

        // Les variantes et les images secondaires des nouveaux produits
        // sont préchargées avant le rendu, sinon chaque carte déclencherait
        // une requête pour son ajout rapide et son survol.
        View::share('quick_variants', quick_variants_map($products));
        View::share('card_images', card_images_map($products));

        ob_start();

        foreach ($products as $product) {
            view_partial('components/product-card', ['product' => $product]);
        }

        return (string) ob_get_clean();
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
