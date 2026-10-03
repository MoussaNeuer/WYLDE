<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Order;
use App\Models\Variant;

/**
 * Gestion du stock, toujours au niveau de la variante.
 *
 * Le stock n'existe pas sur le produit : chaque produit possède au moins
 * une variante, et « sans tailles » se traduit par une variante UNIQUE
 * (cf. CDC §5). Toutes les écritures passent par ici pour rester
 * auditables et transactionnelles.
 */
final class StockService
{
    public static function threshold(): int
    {
        return max(0, (int) config('app.stock.low_threshold', 5));
    }

    /**
     * Ajustement manuel depuis le back-office.
     *
     * @return array{ok: bool, error: string, stock: int}
     */
    public static function adjust(int $variantId, int $delta, string $reason = ''): array
    {
        if ($variantId <= 0) {
            return ['ok' => false, 'error' => 'variante inconnue', 'stock' => 0];
        }

        return Database::transaction(static function () use ($variantId, $delta, $reason): array {
            $row = Database::selectOne(
                'SELECT `id`, `product_id`, `size`, `stock` FROM `product_variants` WHERE `id` = :id FOR UPDATE',
                ['id' => $variantId]
            );

            if ($row === null) {
                return ['ok' => false, 'error' => 'variante inconnue', 'stock' => 0];
            }

            $current = (int) $row['stock'];
            $next    = max(0, $current + $delta);

            Database::update('product_variants', ['stock' => $next], ['id' => $variantId]);

            AuditService::log(AuditService::ACTION_INVENTORY_ADJUST, 'product_variants', (int) $row['id'], [
                'product_id' => (int) $row['product_id'],
                'size'       => (string) $row['size'],
                'from'       => $current,
                'delta'      => $delta,
                'to'         => $next,
                'reason'     => mb_substr(trim($reason), 0, 120),
            ]);

            return ['ok' => true, 'error' => '', 'stock' => $next];
        });
    }

    /**
     * Variantes à surveiller, triées par criticité.
     *
     * @param  string $level  all | low | out
     * @return array<int, array<string, mixed>>
     */
    public static function alertList(string $level = 'all', ?int $categoryId = null, int $limit = 100): array
    {
        $clauses  = [];
        $bindings = ['threshold' => self::threshold()];

        if ($level === 'low') {
            $clauses[] = 'v.stock > 0 AND v.stock <= :threshold';
        } elseif ($level === 'out') {
            $clauses[] = 'v.stock <= 0';
        } else {
            $clauses[] = 'v.stock <= :threshold';
        }

        if ($categoryId !== null && $categoryId > 0) {
            $clauses[]           = 'p.category_id = :category_id';
            $bindings['category_id'] = $categoryId;
        }

        $where = $clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses);

        return Database::select(
            'SELECT v.id, v.product_id, v.size, v.sku, v.stock,
                    p.name, p.slug, p.status,
                    c.name AS category_name
             FROM `product_variants` v
             INNER JOIN `products` p ON p.id = v.product_id
             LEFT JOIN `categories` c ON c.id = p.category_id
             ' . $where . '
             ORDER BY v.stock ASC, p.name ASC
             LIMIT ' . max(1, min(500, $limit)),
            $bindings
        );
    }

    public static function countLow(): int
    {
        return (int) Database::selectValue(
            'SELECT COUNT(*) FROM `product_variants` WHERE `stock` > 0 AND `stock` <= :threshold',
            ['threshold' => self::threshold()]
        );
    }

    public static function countOut(): int
    {
        return (int) Database::selectValue('SELECT COUNT(*) FROM `product_variants` WHERE `stock` <= 0');
    }

    /** Valeurs de stock distinctes proposées à la saisie (sélecteur). */
    public static function presetDeltas(): array
    {
        return [-10, -5, -3, -1, 1, 3, 5, 10];
    }

    /**
     * Décrémente le stock pour les lignes d'une commande et incrémente
     * le compteur de ventes du produit. Appelé par le checkout et par
     * une annulation (avec le signe inversé via $direction).
     */
    public static function applyOrderItems(Order $order, int $direction = -1): int
    {
        $touched = 0;

        foreach ($order->items() as $item) {
            $variantId = (int) ($item->variant_id ?? 0);
            $productId = (int) ($item->product_id ?? 0);
            $quantity  = max(0, (int) $item->quantity);

            if ($variantId <= 0 || $quantity === 0) {
                continue;
            }

            Database::statement(
                'UPDATE `product_variants` SET `stock` = GREATEST(0, `stock` + (:delta))
                 WHERE `id` = :id',
                ['delta' => $direction * $quantity, 'id' => $variantId]
            );

            if ($productId > 0) {
                Database::statement(
                    'UPDATE `products` SET `sold_count` = GREATEST(0, `sold_count` + (:delta))
                     WHERE `id` = :id',
                    ['delta' => $direction * $quantity, 'id' => $productId]
                );
            }

            $touched++;
        }

        return $touched;
    }

    /** Stock total d'un produit, toutes variantes confondues. */
    public static function productStock(int $productId): int
    {
        return (int) Database::selectValue(
            'SELECT COALESCE(SUM(`stock`), 0) FROM `product_variants` WHERE `product_id` = :id',
            ['id' => $productId]
        );
    }

    /** Stock disponible d'une variante, pour les vérifications de vente. */
    public static function variantStock(int $variantId): int
    {
        $variant = Variant::find($variantId);

        return $variant === null ? 0 : (int) $variant->stock;
    }
}
