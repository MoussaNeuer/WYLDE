<?php

declare(strict_types=1);

namespace App\Controllers\Shop;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\ShippingZone;

/**
 * Boutique : liste produits avec recherche, filtres et « charger plus ».
 */
final class ShopController extends Controller
{
    /** Produits par vague : 12 correspond à la grille de quatre sur trois. */
    private const PER_PAGE = 12;

    public function index(Request $request): Response
    {
        $filters = $this->filtersFrom($request);
        $result  = Product::search($request->str('q'), $filters + [
            'per_page' => self::PER_PAGE,
        ]);

        return $this->render($request, null, $result, $filters);
    }

    public function category(Request $request): Response
    {
        $category = Category::findBySlug($request->routeParam('slug', ''));

        if ($category === null) {
            abort(404);
        }

        // La catégorie de l'URL gagne : un lien de collection reste
        // cohérent même si la barre d'URL est éditée à la main.
        $filters = $this->filtersFrom($request) + ['category_id' => $category->id()];
        $result  = Product::search($request->str('q'), $filters + [
            'per_page' => self::PER_PAGE,
        ]);

        return $this->render($request, $category, $result, $filters);
    }

    /**
     * Filtres lus dans la requête, normalisés une seule fois.
     *
     * La même lecture sert la page et l'API « charger plus » : le
     * compteur « 12 sur 48 » et la grille affichée portent donc sur la
     * même sélection.
     *
     * @return array<string, mixed>
     */
    public static function filtersFrom(Request $request): array
    {
        $filters = [
            'sort'    => $request->str('sort', 'recent'),
            'page'    => max(1, $request->int('page', 1)),
            'label'   => $request->str('label'),
            'size'    => $request->str('size'),
            'q'       => $request->str('q'),
        ];

        if ($request->filled('category_id')) {
            $filters['category_id'] = $request->int('category_id');
        }

        // Un prix négatif n'aurait aucun sens et inverserait le filtre.
        if ($request->filled('min_price')) {
            $filters['min_price'] = max(0, $request->int('min_price'));
        }

        if ($request->filled('max_price')) {
            $filters['max_price'] = max(0, $request->int('max_price'));
        }

        if ($request->str('in_stock') === '1') {
            $filters['in_stock'] = true;
        }

        return $filters;
    }

    /**
     * @param  array{products: array<int, Product>, total: int} $result
     * @param  array<string, mixed>                             $filters
     */
    private function render(Request $request, ?Category $category, array $result, array $filters): Response
    {
        $products = $result['products'];
        $total    = (int) $result['total'];
        $page     = max(1, (int) ($filters['page'] ?? 1));

        // Le compteur est cumulatif : sur la deuxième page, il doit
        // annoncer « 15 sur 15 », pas « 3 sur 15 » — les douze produits
        // de la première page sont toujours visibles au-dessus.
        $shown = min($page * self::PER_PAGE, $total);

        // Variantes et images secondaires des cartes en une requête chacune :
        // sans cela, chaque carte coûterait deux allers-retours de plus.
        View::share('quick_variants', quick_variants_map($products));
        View::share('card_images', card_images_map($products));

        return $this->view('shop/shop', [
            'title'      => $category?->localizedName() ?? __('nav.shop'),
            'category'   => $category,
            'products'   => $products,
            'total'      => $total,
            'shown'      => $shown,
            'perPage'    => self::PER_PAGE,
            'categories' => Category::allActive(),
            'sizes'      => ProductSize::inUse(),
            'bounds'     => Product::priceBounds(),
            'filters'    => $filters,
            'chips'      => $this->chips($request, $filters),
            'shipping'   => ShippingZone::allActive(),
        ], 'layouts/shop');
    }

    /**
     * Puces décrivant la sélection courante.
     *
     * Le client doit pouvoir lire d'un coup d'œil pourquoi il ne voit
     * que 3 produits : chaque critère actif devient une puce supprimable.
     *
     * @param  array<string, mixed> $filters
     * @return array<int, array{key: string, label: string, url: string}>
     */
    private function chips(Request $request, array $filters): array
    {
        $chips = [];

        $remove = static function (array $overrides) use ($request, $filters): string {
            $query = $filters;
            unset($query['page']);

            foreach ($overrides as $key => $value) {
                if ($value === null) {
                    unset($query[$key]);
                } else {
                    $query[$key] = (string) $value;
                }
            }

            // Les paramètres sans valeur ne doivent pas survivre au lien :
            // « ?min_price= » ferait échouer `filled()` et laisserait la
            // puce fantôme.
            $query = array_filter(
                $query,
                static fn ($value): bool => $value !== '' && $value !== null && $value !== false
            );

            // Un lien de collection doit rester sur /collection/{slug} :
            // renvoyer le visiteur vers /shop l'enverrait sur la boutique
            // entière en perdant le contexte de la catégorie.
            $slug = (string) $request->routeParam('slug', '');

            $path = $slug === ''
                ? url('/shop')
                : url('/collection/' . $slug);

            return $query === [] ? $path : $path . '?' . http_build_query($query);
        };

        if (($filters['q'] ?? '') !== '') {
            $chips[] = [
                'key'   => 'q',
                'label' => __('shop.chip_query', ['query' => (string) $filters['q']]),
                'url'   => $remove(['q' => null]),
            ];
        }

        if (!empty($filters['category_id'])) {
            $category = Category::find((int) $filters['category_id']);
            $chips[]  = [
                'key'   => 'category_id',
                'label' => $category?->localizedName() ?? __('shop.filter.category'),
                'url'   => $remove(['category_id' => null]),
            ];
        }

        if (($filters['size'] ?? '') !== '') {
            $chips[] = [
                'key'   => 'size',
                'label' => __('shop.chip_size', ['size' => (string) $filters['size']]),
                'url'   => $remove(['size' => null]),
            ];
        }

        if (isset($filters['min_price']) && (int) $filters['min_price'] > 0) {
            $chips[] = [
                'key'   => 'min_price',
                'label' => __('shop.chip_min_price', ['amount' => money((int) $filters['min_price'])]),
                'url'   => $remove(['min_price' => null]),
            ];
        }

        if (isset($filters['max_price']) && (int) $filters['max_price'] > 0) {
            $chips[] = [
                'key'   => 'max_price',
                'label' => __('shop.chip_max_price', ['amount' => money((int) $filters['max_price'])]),
                'url'   => $remove(['max_price' => null]),
            ];
        }

        if (!empty($filters['in_stock'])) {
            $chips[] = [
                'key'   => 'in_stock',
                'label' => __('shop.filter.in_stock'),
                'url'   => $remove(['in_stock' => null]),
            ];
        }

        return $chips;
    }
}