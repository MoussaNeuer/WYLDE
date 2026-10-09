<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Produit de la boutique.
 *
 * Le stock n'existe pas au niveau produit : chaque produit possède au
 * moins une variante (contrainte applicative, cf. CDC §5) et le stock est
 * géré par variante. « Sans tailles » se traduit par une unique variante
 * dont size = 'UNIQUE'.
 */
class Product extends BaseModel
{
    protected string $table = 'products';

    /** @var array<int, string> */
    protected array $fillable = [
        'category_id', 'name', 'slug', 'description',
        'price', 'sale_price', 'label', 'status',
        'is_featured', 'published_at',
    ];

    protected array $intColumns = [
        'id', 'category_id', 'sold_count', 'view_count',
    ];

    /** DECIMAL(12,0) : FCFA, jamais de centimes. */
    protected array $moneyColumns = ['price', 'sale_price'];

    protected array $boolColumns = ['is_featured'];

    /** Statuts_alignés sur l'énumération SQL. */
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED  = 'archived';

    /** Libellés marketing, stockés en base et traduits à l'affichage. */
    public const LABELS = ['new', 'bestseller', 'limited', 'sale'];

    // ── Lectures ─────────────────────────────────────────────

    /**
     * Produits publiés avec leur image principale résolue en une requête.
     *
     * @param array<string, mixed> $filters
     * @return array<int, static>
     */
    public static function published(int $limit = 12, int $offset = 0, array $filters = []): array
    {
        [$where, $bindings] = self::buildFilters($filters, 'p');

        $sql = 'SELECT p.*, (
                    SELECT path FROM product_images
                    WHERE product_id = p.id
                    ORDER BY is_primary DESC, sort_order ASC LIMIT 1
                ) AS image_path
                FROM products p
                ' . $where . '
                ORDER BY p.published_at DESC, p.id DESC
                LIMIT ' . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset);

        return array_map(
            static fn (array $row): static => (new static())->hydrate($row),
            Database::select($sql, $bindings)
        );
    }

    /**
     * Produits par label marketing (bandeau « best-sellers »).
     *
     * @return array<int, static>
     */
    public static function byLabel(string $label, int $limit = 4): array
    {
        $sql = 'SELECT p.*, (
                    SELECT path FROM product_images
                    WHERE product_id = p.id
                    ORDER BY is_primary DESC, sort_order ASC LIMIT 1
                ) AS image_path
                FROM products p
                WHERE p.status = :status AND p.label = :label
                ORDER BY p.sold_count DESC
                LIMIT ' . max(1, min(24, $limit));

        return array_map(
            static fn (array $row): static => (new static())->hydrate($row),
            Database::select($sql, ['status' => self::STATUS_PUBLISHED, 'label' => $label])
        );
    }

    /** Charge un produit publié par son slug. */
    public static function publishedBySlug(string $slug): ?static
    {
        $model = static::query(
            'SELECT * FROM `products` WHERE `slug` = :slug AND `status` = :status LIMIT 1',
            ['slug' => $slug, 'status' => self::STATUS_PUBLISHED]
        );

        return $model->exists() ? $model : null;
    }

    /** @return array<int, static> */
    public static function publishedByCategory(int $categoryId, int $limit = 12, int $offset = 0): array
    {
        $sql = 'SELECT p.*, (
                    SELECT path FROM product_images
                    WHERE product_id = p.id
                    ORDER BY is_primary DESC, sort_order ASC LIMIT 1
                ) AS image_path
                FROM products p
                WHERE p.status = :status AND p.category_id = :category
                ORDER BY p.published_at DESC, p.id DESC
                LIMIT ' . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset);

        return array_map(
            static fn (array $row): static => (new static())->hydrate($row),
            Database::select($sql, ['status' => self::STATUS_PUBLISHED, 'category' => $categoryId])
        );
    }

    /**
     * Produits publiés (slug + dates) pour le sitemap.
     *
     * @return array<int, static>
     */
    public static function publishedForSitemap(int $limit = 1000): array
    {
        $sql = 'SELECT `slug`, `created_at`, `updated_at`
                FROM `products`
                WHERE `status` = :status
                ORDER BY `updated_at` DESC
                LIMIT ' . max(1, min(5000, $limit));

        return array_map(
            static fn (array $row): static => (new static())->hydrate($row),
            Database::select($sql, ['status' => self::STATUS_PUBLISHED])
        );
    }

    public static function countPublished(?int $categoryId = null): int
    {
        if ($categoryId === null) {
            return self::count("`status` = :status", ['status' => self::STATUS_PUBLISHED]);
        }

        return self::count(
            '`status` = :status AND `category_id` = :category',
            ['status' => self::STATUS_PUBLISHED, 'category' => $categoryId]
        );
    }

    /** Produits dans un état donné (brouillons, archivés) : menu et pastilles. */
    public static function countByStatus(string $status): int
    {
        if (!in_array($status, [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED], true)) {
            return 0;
        }

        return self::count('`status` = :status', ['status' => $status]);
    }

    /**
     * Recherche + filtres + tri pour la page boutique.
     *
     * @param  array<string, mixed> $params  q, sort, page, per_page, label,
     *                                       category_id, min_price, max_price,
     *                                       size, in_stock
     * @return array{products: array<int, static>, total: int}
     */
    public static function search(?string $query = null, array $params = []): array
    {
        $sort     = (string) ($params['sort'] ?? 'recent');
        $page     = max(1, (int) ($params['page'] ?? 1));
        $perPage  = max(1, min(100, (int) ($params['per_page'] ?? 12)));

        $clauses  = ['p.status = :status'];
        $bindings = ['status' => self::STATUS_PUBLISHED];

        if ($query !== null && trim($query) !== '') {
            // Placeholders distincts : avec EMULATE_PREPARES=false, PDO
            // refuse qu'un même nom apparaisse deux fois dans un statement.
            $like                  = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($query)) . '%';
            $clauses[]             = '(p.name LIKE :search OR p.name_en LIKE :search_en)';
            $bindings['search']    = $like;
            $bindings['search_en'] = $like;
        }

        if (!empty($params['label']) && in_array($params['label'], ['new', 'bestseller', 'limited'], true)) {
            $clauses[] = 'p.label = :label';
            $bindings['label'] = (string) $params['label'];
        }

        if (!empty($params['category_id'])) {
            $clauses[] = 'p.category_id = :category_id';
            $bindings['category_id'] = (int) $params['category_id'];
        }

        if (isset($params['min_price'])) {
            $clauses[] = 'COALESCE(NULLIF(p.sale_price, 0), p.price) >= :min_price';
            $bindings['min_price'] = (int) $params['min_price'];
        }

        if (isset($params['max_price'])) {
            $clauses[] = 'COALESCE(NULLIF(p.sale_price, 0), p.price) <= :max_price';
            $bindings['max_price'] = (int) $params['max_price'];
        }

        // Un produit « taille » est un produit qui possède une variante
        // de cette taille et pas seulement une variante UNIQUE.
        $size = trim((string) ($params['size'] ?? ''));
        $sized = $size !== '' && $size !== 'UNIQUE';

        if ($sized) {
            $clauses[] = 'EXISTS (SELECT 1 FROM `product_variants` v
                          WHERE v.product_id = p.id AND v.size = :size)';
            $bindings['size'] = $size;
        }

        // « En stock seulement » : au moins une variante vendable. Si une
        // taille est choisie, le stock demandé porte sur cette taille-là :
        // « taille L + en stock » doit sortir les produits dont le L est
        // vendable, pas ceux qui ont une M encore disponible.
        if (!empty($params['in_stock'])) {
            if ($sized) {
                $clauses[] = 'EXISTS (SELECT 1 FROM `product_variants` v2
                              WHERE v2.product_id = p.id AND v2.size = :size_stock AND v2.stock > 0)';
                $bindings['size_stock'] = $size;
            } else {
                $clauses[] = 'EXISTS (SELECT 1 FROM `product_variants` v2
                              WHERE v2.product_id = p.id AND v2.stock > 0)';
            }
        }

        $where = 'WHERE ' . implode(' AND ', $clauses);

        $order = match ($sort) {
            'price_asc'  => 'COALESCE(NULLIF(p.sale_price, 0), p.price) ASC, p.id ASC',
            'price_desc' => 'COALESCE(NULLIF(p.sale_price, 0), p.price) DESC, p.id DESC',
            'popular'    => 'p.sold_count DESC, p.id DESC',
            'relevance'  => 'p.name ASC, p.id DESC',
            default      => 'p.published_at DESC, p.id DESC',
        };

        $total = (int) Database::selectValue(
            'SELECT COUNT(*) FROM `products` p ' . $where,
            $bindings
        );

        $rows = self::listingQuery(
            $where . '
             ORDER BY ' . $order . '
             LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $bindings
        );

        return [
            'products' => array_map(static fn (array $row): static => (new static())->hydrate($row), $rows),
            'total'    => $total,
        ];
    }

    /**
     * Bornes de prix des produits publiés, pour les curseurs de filtre.
     *
     * Interroge le prix effectif (remise comprise) et non `price` : sinon
     * le curseur proposerait des bornes qu'aucun produit n'a jamais.
     *
     * @return array{min: int, max: int}
     */
    public static function priceBounds(): array
    {
        $row = Database::selectOne(
            "SELECT COALESCE(MIN(COALESCE(NULLIF(p.sale_price, 0), p.price)), 0) AS min_price,
                    COALESCE(MAX(COALESCE(NULLIF(p.sale_price, 0), p.price)), 0) AS max_price
             FROM `products` p
             WHERE p.status = :status",
            ['status' => self::STATUS_PUBLISHED]
        );

        return [
            'min' => (int) ($row['min_price'] ?? 0),
            'max' => (int) ($row['max_price'] ?? 0),
        ];
    }

    /**
     * Produits publiés par identifiants, dans l'ordre demandé.
     *
     * Utilisé par la page « Mes favoris » : l'ordre vient du navigateur,
     * qui classe les ajouts du plus récent au plus ancien.
     *
     * @param  array<int, int|string> $ids
     * @return array<int, static>
     */
    public static function findManyByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // La clause filtre sur le statut : un produit archivé ne doit pas
        // réapparaître dans les favoris du visiteur.
        $rows = self::listingQuery(
            'WHERE p.status = ? AND p.id IN (' . $placeholders . ')',
            array_merge([self::STATUS_PUBLISHED], $ids)
        );

        $byId = [];

        foreach ($rows as $row) {
            $byId[(int) $row['id']] = (new static())->hydrate($row);
        }

        // La requête ne garantit pas l'ordre du navigateur : on le rétablit.
        return array_values(array_filter(
            array_map(static fn (int $id) => $byId[$id] ?? null, $ids)
        ));
    }

    /**
     * Colonnes communes aux listes de produits.
     *
     * Le stock total et la première image sont résolus ici plutôt que par
     * une requête par produit : une grille de 12 cartes ne coûterait
     * sinon 24 allers-retours.
     */
    private static function listingQuery(string $tail, array $bindings = []): array
    {
        return Database::select(
            'SELECT p.*,
                    COALESCE((SELECT SUM(v.stock) FROM `product_variants` v
                              WHERE v.product_id = p.id), 0) AS total_stock,
                    (SELECT path FROM product_images
                     WHERE product_id = p.id
                     ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS image_path
             FROM products p
             ' . $tail,
            $bindings
        );
    }

    /**
     * Liste paginée pour le back-office, filtres et tri inclus.
     *
     * @param  array<string, mixed> $params  q, status, category_id, label, sort
     * @return array{products: array<int, static>, total: int, page: int, per_page: int, pages: int}
     */
    public static function adminList(array $params = [], int $perPage = 20): array
    {
        $page    = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(100, $perPage));

        [$clauses, $bindings] = self::buildAdminFilters($params);

        $where = $clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses);

        $total = (int) Database::selectValue('SELECT COUNT(*) FROM `products` p ' . $where, $bindings);

        $order = match ((string) ($params['sort'] ?? 'recent')) {
            'name'       => 'p.name ASC, p.id ASC',
            'oldest'     => 'p.created_at ASC, p.id ASC',
            'price_asc'  => 'p.price ASC, p.id ASC',
            'price_desc' => 'p.price DESC, p.id DESC',
            'stock'      => 'total_stock ASC, p.name ASC',
            'featured'   => 'p.is_featured DESC, p.id DESC',
            default      => 'p.created_at DESC, p.id DESC',
        };

        $rows = Database::select(
            'SELECT p.*,
                    COALESCE(SUM(v.stock), 0) AS total_stock,
                    (SELECT path FROM product_images
                     WHERE product_id = p.id
                     ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS image_path
             FROM `products` p
             LEFT JOIN `product_variants` v ON v.product_id = p.id
             ' . $where . '
             GROUP BY p.id
             ORDER BY ' . $order . '
             LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $bindings
        );

        return [
            'products' => array_map(static fn (array $row): static => (new static())->hydrate($row), $rows),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int) max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * @param  array<string, mixed> $params
     * @return array{0: array<int, string>, 1: array<string, mixed>}
     */
    private static function buildAdminFilters(array $params): array
    {
        $clauses  = [];
        $bindings = [];

        $query = trim((string) ($params['q'] ?? ''));
        if ($query !== '') {
            $like                  = '%' . str_replace(['%', '_'], ['\%', '\_'], $query) . '%';
            $clauses[]             = '(p.name LIKE :search OR p.name_en LIKE :search_en OR p.sku LIKE :search_sku)';
            $bindings['search']    = $like;
            $bindings['search_en'] = $like;
            $bindings['search_sku'] = $like;
        }

        $status = (string) ($params['status'] ?? '');
        if (in_array($status, ['draft', 'published', 'hidden', 'archived'], true)) {
            $clauses[]        = 'p.status = :status';
            $bindings['status'] = $status;
        }

        $category = (int) ($params['category_id'] ?? 0);
        if ($category > 0) {
            $clauses[]           = 'p.category_id = :category_id';
            $bindings['category_id'] = $category;
        }

        $label = (string) ($params['label'] ?? '');
        if (in_array($label, self::LABELS, true)) {
            $clauses[]       = 'p.label = :label';
            $bindings['label'] = $label;
        }

        $lowStock = !empty($params['low_stock']);
        if ($lowStock) {
            $clauses[] = 'p.id IN (
                SELECT v2.product_id FROM product_variants v2
                WHERE v2.stock <= :threshold
            )';
            $bindings['threshold'] = (int) config('app.stock.low_threshold', 5);
        }

        return [$clauses, $bindings];
    }

    // ── Relations ────────────────────────────────────────────

    /** @return array<int, Variant> */
    public function variants(): array
    {
        /** @var array<int, Variant> $variants */
        $variants = $this->hasMany(Variant::class, 'product_id');

        return $variants;
    }

    public function category(): ?Category
    {
        $id = $this->attributes['category_id'] ?? null;

        return $id === null ? null : $this->belongsTo(Category::class, 'category_id', $id);
    }

    /**
     * Galerie produit, dans l'ordre d'affichage : l'image principale en
     * premier, puis l'ordre du drag & drop (sort_order), puis id.
     *
     * @return array<int, ProductImage>
     */
    public function images(): array
    {
        $rows = Database::select(
            'SELECT * FROM `product_images`
             WHERE `product_id` = :id
             ORDER BY `is_primary` DESC, `sort_order` ASC, `id` ASC',
            ['id' => $this->id()]
        );

        return array_map(
            static fn (array $row): ProductImage => (new ProductImage())->hydrate($row),
            $rows
        );
    }

    /** Image principale résolue par le contrôleur, sinon la première. */
    public function primaryImage(): ?string
    {
        $path = $this->attributes['image_path'] ?? null;

        if (is_string($path) && $path !== '') {
            return $path;
        }

        foreach ($this->images() as $image) {
            $imagePath = $image->path;

            if (is_string($imagePath) && $imagePath !== '') {
                return $imagePath;
            }
        }

        return null;
    }

    // ── Stock ────────────────────────────────────────────────

    /** Stock cumulé sur toutes les variantes. */
    public function totalStock(): int
    {
        // Les listes de produits rapportent déjà le cumul dans la requête :
        // sans cette court-circuit, une grille de 12 cartes coûterait
        // 12 requêtes supplémentaires.
        if (isset($this->attributes['total_stock'])) {
            return (int) $this->attributes['total_stock'];
        }

        $row = Database::selectOne(
            'SELECT COALESCE(SUM(stock), 0) AS total FROM `product_variants` WHERE `product_id` = :id',
            ['id' => $this->id()]
        );

        return (int) ($row['total'] ?? 0);
    }

    public function isInStock(): bool
    {
        return $this->totalStock() > 0;
    }

    // ── Présentation ─────────────────────────────────────────

    /** Nom traduit avec repli français (§11). */
    public function localizedName(?string $locale = null): string
    {
        $locale ??= \App\Core\Lang::locale();

        $localized = $locale === 'en'
            ? ($this->attributes['name_en'] ?? null)
            : null;

        return (string) ($localized !== null && $localized !== '' ? $localized : ($this->attributes['name'] ?? ''));
    }

    /**
     * Prix effectif : le prix promotionnel (sale_price) s'il est renseigné,
     * sinon le prix de vente. C'est ce montant qui est facturé.
     */
    public function effectivePrice(): int
    {
        $salePrice = $this->attributes['sale_price'] ?? null;

        return $salePrice !== null && (int) $salePrice > 0
            ? (int) $salePrice
            : (int) ($this->attributes['price'] ?? 0);
    }

    /** true si un prix barré doit être affiché. */
    public function hasDiscount(): bool
    {
        $salePrice = $this->attributes['sale_price'] ?? null;

        if ($salePrice === null || (int) $salePrice <= 0) {
            return false;
        }

        return (int) $this->attributes['price'] > (int) $salePrice;
    }

    public function discountPercent(): int
    {
        if (!$this->hasDiscount()) {
            return 0;
        }

        $price = (int) $this->attributes['price'];
        $sale  = (int) $this->attributes['sale_price'];

        if ($sale <= 0 || $price <= 0) {
            return 0;
        }

        return (int) round((($price - $sale) / $price) * 100);
    }

    // ── Écriture ─────────────────────────────────────────────

    protected function afterSave(): void
    {
        $name = (string) ($this->attributes['name'] ?? '');

        // Slug conservé s'il a déjà été choisi ou généré.
        if (($this->attributes['slug'] ?? '') === '' && $name !== '') {
            $this->attributes['slug'] = self::uniqueSlug($name, $this->id());
        }

        // published_at positionne la date de publication pour le tri.
        if (
            ($this->attributes['status'] ?? null) === self::STATUS_PUBLISHED
            && ($this->attributes['published_at'] ?? null) === null
        ) {
            $this->attributes['published_at'] = date('Y-m-d H:i:s');
        }
    }

    /**
     * Slug ASCII unique, suffixe numérique en cas de collision.
     */
    public static function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = self::slugify($source);

        if ($base === '') {
            $base = 'produit';
        }

        $slug  = $base;
        $index = 1;

        while (self::slugExists($slug, $ignoreId)) {
            $slug = $base . '-' . (++$index);
        }

        return $slug;
    }

    public static function slugify(string $value): string
    {
        $value = \App\Core\Lang::transliterate($value);

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    private static function slugExists(string $slug, ?int $ignoreId): bool
    {
        $sql    = 'SELECT COUNT(*) FROM `products` WHERE `slug` = :slug';
        $params = ['slug' => $slug];

        if ($ignoreId !== null) {
            $sql .= ' AND `id` <> :id';
            $params['id'] = $ignoreId;
        }

        return (int) Database::selectValue($sql, $params) > 0;
    }

    /**
     * Construit la clause WHERE et ses bindings depuis des filtres utilisateur.
     *
     * @param  array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function buildFilters(array $filters, string $alias): array
    {
        $clauses  = [];
        $bindings = [];

        $clauses[]           = $alias . '.status = :status';
        $bindings['status']  = self::STATUS_PUBLISHED;

        if (!empty($filters['category_id'])) {
            $clauses[]           = $alias . '.category_id = :category_id';
            $bindings['category_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['label']) && in_array($filters['label'], self::LABELS, true)) {
            $clauses[]        = $alias . '.label = :label';
            $bindings['label'] = (string) $filters['label'];
        }

        if (!empty($filters['search'])) {
            $like                     = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']) . '%';
            $clauses[]                = '(' . $alias . '.name LIKE :search OR ' . $alias . '.name_en LIKE :search_en)';
            $bindings['search']       = $like;
            $bindings['search_en']    = $like;
        }

        if (isset($filters['min_price'])) {
            $clauses[]           = $alias . '.price >= :min_price';
            $bindings['min_price'] = (int) $filters['min_price'];
        }

        if (isset($filters['max_price'])) {
            $clauses[]              = $alias . '.price <= :max_price';
            $bindings['max_price']  = (int) $filters['max_price'];
        }

        return ['WHERE ' . implode(' AND ', $clauses), $bindings];
    }

    /**
     * Le produit figure-t-il dans un historique de commandes ?
     *
     * Ordres non livrés comme livrés : suppression bloquée pour préserver
     * la traçabilité (order_items.product_id est en SET NULL sinon).
     */
    public function orderedCount(): int
    {
        return (int) Database::selectValue(
            'SELECT COUNT(*) FROM `order_items` WHERE `product_id` = :id',
            ['id' => $this->id()]
        );
    }
}
