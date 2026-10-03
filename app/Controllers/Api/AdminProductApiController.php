<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Product;
use App\Services\AuditService;
use App\Services\UploadService;

/**
 * API produits du back-office : liste filtrable asynchrone et suppression
 * rapide. La suppression passe par ici et non par un lien GET : c'est une
 * action irréversible (§16).
 */
final class AdminProductApiController extends Controller
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

        $result = Product::adminList($params, (int) config('app.pagination.admin', 20));

        $products = array_map(static function (Product $product): array {
            $stock = (int) ($product->total_stock ?? $product->totalStock());
            $image = $product->primaryImage();

            return [
                'id'          => (int) $product->id(),
                'name'        => $product->name,
                'name_en'     => (string) $product->name_en,
                'slug'        => (string) $product->slug,
                'sku'         => (string) $product->sku,
                'status'      => (string) $product->status,
                'label'       => (string) $product->label,
                'is_featured' => (int) $product->is_featured === 1,
                'price'       => (int) $product->price,
                'sale_price'  => (int) $product->sale_price,
                'image'       => $image === null ? null : upload_url($image),
                'stock'       => $stock,
                'stock_level' => stock_status($stock)['level'],
                'edit_url'    => url('/admin/products/' . $product->id() . '/edit'),
                'media_url'   => url('/admin/products/' . $product->id() . '/media'),
            ];
        }, $result['products']);

        return $this->json([
            'products' => $products,
            'total'    => (int) $result['total'],
            'page'     => (int) $result['page'],
            'pages'    => (int) $result['pages'],
            'per_page' => (int) $result['per_page'],
        ]);
    }

    public function destroy(Request $request): Response
    {
        $product = Product::findOrFail($this->id($request));

        $name = (string) $product->name;

        Database::transaction(function () use ($product): void {
            // Les fichiers sont retirés avant la ligne : si l'un d'eux
            // échoue, la transaction est annulée et rien n'est perdu.
            foreach ($product->images() as $image) {
                if (is_string($image->path) && $image->path !== '') {
                    UploadService::delete($image->path);
                }
            }

            $product->delete();
        });

        AuditService::log(AuditService::ACTION_PRODUCT_DELETE, 'products', (int) $product->id(), [
            'name' => $name,
        ]);

        return $this->json(['deleted' => (int) $product->id()]);
    }
}
