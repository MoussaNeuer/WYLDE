<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSize;
use App\Services\AuditService;
use App\Services\UploadService;
use App\Validators\ProductValidator;

/**
 * Gestion des produits : CRUD, variantes, galerie.
 */
final class ProductController extends AdminController
{
    public function index(Request $request): Response
    {
        $params = array_filter([
            'q'           => $request->str('q'),
            'status'      => $request->str('status'),
            'category_id' => $request->int('category_id'),
            'label'       => $request->str('label'),
            'sort'        => $request->str('sort'),
            'low_stock'   => $request->filled('low_stock') ? '1' : '',
            'page'        => $request->int('page', 1),
        ], static fn (mixed $value): bool => $value !== '');

        $result    = Product::adminList($params, (int) config('app.pagination.admin', 20));
        $page      = $result['page'];
        $last      = $result['pages'];

        return $this->view('admin/products/index', [
            'title'    => __('admin.products'),
            'products' => $result['products'],
            'total'    => $result['total'],
            'page'     => $page,
            'pages'    => $last,
            'categories' => Category::all('`name` ASC'),
            'filters'  => $params,
            'prevUrl'  => $page > 1 ? pagination_url($page - 1) : null,
            'nextUrl'  => $page < $last ? pagination_url($page + 1) : null,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/products/form', [
            'title'      => __('admin.product.new'),
            'product'    => null,
            'categories' => Category::all('`name` ASC'),
            'statuses'   => ['draft', 'published', 'hidden', 'archived'],
            'labels'     => ['none', 'new', 'bestseller', 'limited'],
            'sizes'      => ProductSize::labels(),
        ]);
    }

    public function store(Request $request): Response
    {
        $validator = new ProductValidator($request->all());

        $validator->validate();

        if ($validator->fails()) {
            $this->auditFailed('product.create', $validator->errors());

            return $this->redirectWithErrors('/admin/products/create', $validator->errors(), $request->all());
        }

        $data = $validator->productData();

        Database::transaction(function () use ($data, $validator, $request): void {
            $product = Product::create($data);

            $variants = $validator->variantsData(empty($request->array('sizes')) && !$request->bool('has_sizes'));

            foreach ($variants as $variant) {
                Database::insert('product_variants', array_merge(
                    ['product_id' => (int) $product->id()],
                    $variant
                ));
            }

            // Product::afterSave() a calculé un slug en mémoire : la
            // seconde sauvegarde le persiste réellement.
            if (trim((string) $product->slug) !== '') {
                $product->save();
            }

            AuditService::log(AuditService::ACTION_PRODUCT_CREATE, 'products', (int) $product->id(), [
                'name'   => $data['name'],
                'status' => $data['status'],
            ]);

            self::saveUploadedImages($product, $request);
        });

        return $this->redirectWithSuccess('/admin/products', __('flash.product_created'));
    }

    public function edit(Request $request): Response
    {
        $product = Product::findOrFail($this->id($request));

        return $this->view('admin/products/form', [
            'title'      => __('admin.product.edit') . ' — ' . $product->name,
            'product'    => $product,
            'variants'   => $product->variants(),
            'images'     => $product->images(),
            'categories' => Category::all('`name` ASC'),
            'statuses'   => ['draft', 'published', 'hidden', 'archived'],
            'labels'     => ['none', 'new', 'bestseller', 'limited'],
            'sizes'      => ProductSize::labels(),
        ]);
    }

    public function update(Request $request): Response
    {
        $product = Product::findOrFail($this->id($request));

        $validator = new ProductValidator($request->all(), (int) $product->id());

        $validator->validate();

        if ($validator->fails()) {
            return $this->redirectWithErrors(
                '/admin/products/' . $product->id() . '/edit',
                $validator->errors(),
                $request->all()
            );
        }

        $data = $validator->productData();

        if ($data['slug'] === '' && !empty($product->slug)) {
            $data['slug'] = (string) $product->slug;
        }

        Database::transaction(function () use ($product, $data, $validator, $request): void {
            $product->fill($data);
            $product->save();

            self::syncVariants($product, $validator, $request);

            AuditService::log(AuditService::ACTION_PRODUCT_UPDATE, 'products', (int) $product->id(), [
                'name'   => $data['name'],
                'status' => $data['status'],
            ]);

            self::saveUploadedImages($product, $request);
        });

        return $this->redirectWithSuccess('/admin/products', __('flash.product_updated'));
    }

    public function destroy(Request $request): Response
    {
        $product = Product::findOrFail($this->id($request));

        $name = $product->name;

        Database::transaction(function () use ($request, $product): void {
            AuditService::log(AuditService::ACTION_PRODUCT_DELETE, 'products', (int) $product->id(), [
                'name' => $product->name,
            ]);

            self::deleteImages($product);
            $product->delete();
        });

        return $this->redirectWithSuccess('/admin/products', __('flash.product_deleted'));
    }

    /** Actions groupées : publication, archivage, suppression. */
    public function bulk(Request $request): Response
    {
        $ids = array_values(array_filter(
            array_map('intval', $request->array('ids')),
            static fn (int $id): bool => $id > 0
        ));

        $action = $request->str('action');

        if ($ids === [] || !in_array($action, ['publish', 'archive', 'delete', 'feature'], true)) {
            return $this->back();
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        match ($action) {
            'publish' => Database::statement(
                "UPDATE `products` SET `status` = 'published', published_at = COALESCE(published_at, NOW()) WHERE `id` IN ({$placeholders})",
                $ids
            ),
            'archive' => Database::statement(
                "UPDATE `products` SET `status` = 'archived' WHERE `id` IN ({$placeholders})",
                $ids
            ),
            'feature' => Database::statement(
                "UPDATE `products` SET `is_featured` = 1 - `is_featured` WHERE `id` IN ({$placeholders})",
                $ids
            ),
            'delete'  => Database::transaction(function () use ($ids, $placeholders): void {
                $rows = Database::select(
                    'SELECT `id`, `name` FROM `products` WHERE `id` IN (' . $placeholders . ')',
                    $ids
                );

                foreach ($rows as $row) {
                    $product = (new Product())->hydrate($row);
                    self::deleteImages($product);
                }

                Database::statement(
                    'DELETE FROM `products` WHERE `id` IN (' . $placeholders . ')',
                    $ids
                );
            }),
        };

        AuditService::log(AuditService::ACTION_PRODUCT_BULK, 'products', null, [
            'action' => $action,
            'ids'    => $ids,
        ]);

        return $this->redirectWithSuccess('/admin/products', __('flash.product_updated'));
    }

    // ── Médias ───────────────────────────────────────────────

    public function media(Request $request): Response
    {
        $product = Product::findOrFail($this->id($request));

        return $this->view('admin/products/media', [
            'title'   => __('admin.media_title') . ' — ' . $product->name,
            'product' => $product,
            'images'  => $product->images(),
        ]);
    }

    public function uploadMedia(Request $request): Response
    {
        $product = Product::findOrFail($this->id($request));

        $bucket = $request->file('images') ?? $request->file('image');

        if ($bucket === null || (!$request->hasFile('images') && !$request->hasFile('image'))) {
            return $this->redirectWithErrors('/admin/products/' . $product->id() . '/media', [
                'files' => __('validation.required', ['field' => 'Image']),
            ]);
        }

        $count = 0;

        // Un seul fichier (clé name), ou un tableau d'inputs images[].
        $files = isset($bucket['name']) ? [$bucket] : array_values($bucket);

        foreach ($files as $single) {
            $result = UploadService::store($single, (string) config('app.uploads.folder', 'products'), $product->name);

            if (!$result['ok']) {
                continue;
            }

            $hasPrimary = (int) Database::selectValue(
                'SELECT COUNT(*) FROM `product_images` WHERE `product_id` = :id AND `is_primary` = 1',
                ['id' => $product->id()]
            ) > 0;

            Database::insert('product_images', [
                'product_id' => (int) $product->id(),
                'path'       => $result['path'],
                'alt_text'   => null,
                'sort_order' => 0,
                'is_primary' => $hasPrimary ? 0 : 1,
            ]);

            $count++;
        }

        if ($count === 0) {
            return $this->redirectWithErrors('/admin/products/' . $product->id() . '/media', [
                'files' => 'Aucune image valide n\'a été reçue.',
            ]);
        }

        AuditService::log(AuditService::ACTION_MEDIA_UPLOAD, 'products', (int) $product->id(), ['count' => $count]);

        return $this->redirectWithSuccess('/admin/products/' . $product->id() . '/media', $count . ' image(s) ajoutée(s).');
    }

    public function reorderMedia(Request $request): Response
    {
        $product = Product::findOrFail($this->id($request));

        $order = array_map('intval', $request->array('order'));

        Database::transaction(function () use ($product, $order): void {
            $position = 0;

            foreach ($order as $imageId) {
                if ($imageId <= 0) {
                    continue;
                }

                Database::update('product_images', ['sort_order' => $position], [
                    'id'         => $imageId,
                    'product_id' => $product->id(),
                ]);

                $position++;
            }
        });

        AuditService::log(AuditService::ACTION_MEDIA_REORDER, 'products', (int) $product->id());

        return $this->json(['count' => count($order)]);
    }

    public function setPrimaryMedia(Request $request): Response
    {
        $product  = Product::findOrFail($this->id($request));
        $imageId  = $this->id($request, 'imageId');

        Database::transaction(function () use ($product, $imageId): void {
            Database::update('product_images', ['is_primary' => 0], ['product_id' => $product->id()]);

            Database::update('product_images', ['is_primary' => 1], [
                'id'         => $imageId,
                'product_id' => $product->id(),
            ]);
        });

        AuditService::log(AuditService::ACTION_MEDIA_PRIMARY, 'products', (int) $product->id(), ['image_id' => $imageId]);

        return $this->redirectWithSuccess('/admin/products/' . $product->id() . '/media', 'Image principale modifiée.');
    }

    public function deleteMedia(Request $request): Response
    {
        $product = Product::findOrFail($this->id($request));
        $imageId = $this->id($request, 'imageId');

        $imageRow = Database::selectOne(
            'SELECT `id`, `path`, `is_primary` FROM `product_images` WHERE `id` = :id AND `product_id` = :pid LIMIT 1',
            ['id' => $imageId, 'pid' => $product->id()]
        );

        if ($imageRow === null) {
            abort(404);
        }

        Database::transaction(function () use ($product, $imageRow): void {
            UploadService::delete((string) $imageRow['path']);
            Database::delete('product_images', ['id' => (int) $imageRow['id']]);

            // Si l'image principale disparaît, bascule sur la première restante.
            if ((int) $imageRow['is_primary'] === 1) {
                Database::statement(
                    'UPDATE `product_images` SET `is_primary` = 1
                     WHERE `product_id` = :pid
                     ORDER BY sort_order ASC, id ASC
                     LIMIT 1',
                    ['pid' => $product->id()]
                );
            }

            AuditService::log(AuditService::ACTION_MEDIA_DELETE, 'products', (int) $product->id(), [
                'image_id' => (int) $imageRow['id'],
            ]);
        });

        return $this->redirectWithSuccess('/admin/products/' . $product->id() . '/media', 'Image supprimée.');
    }

    /**
     * Écrit les variantes du formulaire : mise à jour des existantes,
     * ajout des nouvelles, suppression des retirées.
     */
    private static function syncVariants(Product $product, ProductValidator $validator, Request $request): void
    {
        $submitted = $validator->variantsData(empty($request->array('sizes')) && !$request->bool('has_sizes'));

        $existing = $product->variants();
        $existingIds = [];

        foreach ($existing as $variant) {
            $existingIds[] = (int) $variant->id;
        }

        $newIds = [];

        foreach ($submitted as $index => $line) {
            $overrideId = isset($request->array('variant_ids')[$index])
                ? (int) $request->array('variant_ids')[$index]
                : 0;

            $payload = [
                'size'           => $line['size'],
                'sku'            => $line['sku'] !== '' ? $line['sku'] : null,
                'stock'          => $line['stock'],
                'price_override' => $line['price_override'],
                'is_default'     => $index === 0 ? 1 : 0,
                'sort_order'     => $index,
            ];

            if ($overrideId > 0 && in_array($overrideId, $existingIds, true)) {
                Database::update('product_variants', $payload, ['id' => $overrideId]);
                $newIds[] = $overrideId;
            } else {
                $newIds[] = (int) Database::insert('product_variants', array_merge(
                    ['product_id' => (int) $product->id()],
                    $payload
                ));
            }
        }

        // Variantes présentes en base mais absentes du formulaire : retirées.
        $removed = array_values(array_diff($existingIds, $newIds));

        if ($removed !== []) {
            $placeholders = implode(',', array_fill(0, count($removed), '?'));

            Database::statement(
                'DELETE FROM `product_variants`
                 WHERE `product_id` = ? AND `id` IN (' . $placeholders . ')',
                array_merge([(int) $product->id()], $removed)
            );
        }
    }

    private static function deleteImages(Product $product): void
    {
        foreach ($product->images() as $image) {
            $path = $image->path;

            if (is_string($path) && $path !== '') {
                UploadService::delete($path);
            }
        }
    }

    /**
     * Enregistre les images téléversées avec le formulaire produit
     * (glisser-déposer sur la fiche, création ou édition).
     */
    private static function saveUploadedImages(Product $product, Request $request): int
    {
        $bucket = $request->file('images') ?? $request->file('image');

        if ($bucket === null) {
            return 0;
        }

        $files = isset($bucket['name']) ? [$bucket] : array_values($bucket);

        $count = 0;

        foreach ($files as $single) {
            if (!is_array($single) || (int) ($single['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $result = UploadService::store(
                $single,
                (string) config('app.uploads.folder', 'products'),
                (string) $product->name
            );

            if (!$result['ok']) {
                continue;
            }

            $hasPrimary = (int) Database::selectValue(
                'SELECT COUNT(*) FROM `product_images` WHERE `product_id` = :id AND `is_primary` = 1',
                ['id' => $product->id()]
            ) > 0;

            Database::insert('product_images', [
                'product_id' => (int) $product->id(),
                'path'       => $result['path'],
                'alt_text'   => null,
                'sort_order' => 0,
                'is_primary' => $hasPrimary ? 0 : 1,
            ]);

            $count++;
        }

        if ($count > 0) {
            AuditService::log(AuditService::ACTION_MEDIA_UPLOAD, 'products', (int) $product->id(), ['count' => $count]);
        }

        return $count;
    }

    private static function auditFailed(string $action, array $errors): void
    {
        AuditService::log($action . '_failed', null, null, ['errors' => $errors]);
    }
}