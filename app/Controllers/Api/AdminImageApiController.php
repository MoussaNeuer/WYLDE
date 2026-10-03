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
 * API médias du back-office : dépôt par glisser-déposer, réorganisation
 * et suppression. Les réponses sont JSON pour que la galerie reste en
 * place ; le rechargement complet n'est nécessaire qu'après le dépôt.
 *
 * Le middleware 'admin' protège déjà la route : le rôle est vérifié côté
 * serveur avant d'arriver ici.
 */
final class AdminImageApiController extends Controller
{
    /** Dépôt d'une ou plusieurs images pour un produit. */
    public function upload(Request $request): Response
    {
        $product = Product::findOrFail($this->id($request));

        $bucket = $request->file('images') ?? $request->file('image');

        if ($bucket === null || (!$request->hasFile('images') && !$request->hasFile('image'))) {
            return $this->json(['error' => __('validation.required', ['field' => 'Image'])], 422);
        }

        // Un fichier unique arrive sous 'name', un lot sous un tableau.
        $files = isset($bucket['name']) ? [$bucket] : array_values($bucket);

        $hasPrimary = (int) Database::selectValue(
            'SELECT COUNT(*) FROM `product_images` WHERE `product_id` = :id AND `is_primary` = 1',
            ['id' => (int) $product->id()]
        ) > 0;

        $stored = [];
        $errors = [];

        Database::transaction(function () use ($files, $product, $hasPrimary, &$stored, &$errors): void {
            foreach ($files as $file) {
                $result = UploadService::store(
                    $file,
                    (string) config('app.uploads.folder', 'products'),
                    (string) $product->name
                );

                if (!$result['ok']) {
                    $errors[] = $result['error'];
                    continue;
                }

                $imageId = (int) Database::insert('product_images', [
                    'product_id' => (int) $product->id(),
                    'path'       => $result['path'],
                    'alt_text'   => null,
                    'sort_order' => 0,
                    'is_primary' => $hasPrimary ? 0 : 1,
                ]);

                $stored[] = [
                    'id'         => $imageId,
                    'url'        => upload_url((string) $result['path']),
                    'is_primary' => $hasPrimary ? 0 : 1,
                ];

                // La toute première image devient principale.
                $hasPrimary = true;
            }
        });

        if ($stored === []) {
            return $this->json([
                'error'  => $errors[0] ?? __('validation.required', ['field' => 'Image']),
                'errors' => $errors,
            ], 422);
        }

        AuditService::log(AuditService::ACTION_MEDIA_UPLOAD, 'products', (int) $product->id(), [
            'count' => count($stored),
        ]);

        return $this->json([
            'images'    => $stored,
            'count'     => count($stored),
            'rejected'  => $errors,
        ], count($errors) > 0 ? 207 : 201);
    }

    /** Nouvel ordre de la galerie : tableau d'identifiants d'images. */
    public function reorder(Request $request): Response
    {
        $product = Product::findOrFail($this->id($request));

        $order = array_values(array_filter(
            array_map('intval', $request->array('order')),
            static fn (int $imageId): bool => $imageId > 0
        ));

        if ($order === []) {
            return $this->json(['error' => __('validation.required', ['field' => 'order'])], 422);
        }

        $placeholders = implode(',', array_fill(0, count($order), '?'));

        // Le produit dans la clause WHERE empêche de réordonner une image
        // appartenant à un autre produit.
        $owned = array_map(
            static fn (array $row): int => (int) $row['id'],
            Database::select(
                'SELECT `id` FROM `product_images`
                 WHERE `product_id` = ? AND `id` IN (' . $placeholders . ')',
                array_merge([(int) $product->id()], $order)
            )
        );

        if (count($owned) !== count($order)) {
            return $this->json(['error' => __('admin.media.error_order')], 422);
        }

        Database::transaction(function () use ($order): void {
            $position = 0;

            foreach ($order as $imageId) {
                Database::update('product_images', ['sort_order' => $position], ['id' => $imageId]);
                $position++;
            }
        });

        AuditService::log(AuditService::ACTION_MEDIA_REORDER, 'products', (int) $product->id(), [
            'count' => count($order),
        ]);

        return $this->json(['count' => count($order), 'order' => $order]);
    }

    /** Suppression d'une image, quel que soit son produit. */
    public function destroy(Request $request): Response
    {
        $imageId = $this->id($request, 'imageId');

        $row = Database::selectOne(
            'SELECT `id`, `product_id`, `path`, `is_primary` FROM `product_images` WHERE `id` = :id LIMIT 1',
            ['id' => $imageId]
        );

        if ($row === null) {
            abort(404);
        }

        $productId = (int) $row['product_id'];

        Database::transaction(function () use ($row, $productId): void {
            UploadService::delete((string) $row['path']);
            Database::delete('product_images', ['id' => (int) $row['id']]);

            // L'image principale disparue : on promeut la suivante.
            if ((int) $row['is_primary'] === 1) {
                Database::statement(
                    'UPDATE `product_images` SET `is_primary` = 1
                     WHERE `product_id` = :pid
                     ORDER BY sort_order ASC, id ASC
                     LIMIT 1',
                    ['pid' => $productId]
                );
            }

            AuditService::log(AuditService::ACTION_MEDIA_DELETE, 'products', $productId, [
                'image_id' => (int) $row['id'],
            ]);
        });

        return $this->json([
            'deleted'    => (int) $row['id'],
            'product_id' => $productId,
        ]);
    }
}
