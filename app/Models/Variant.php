<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Variante produit : taille + stock + éventuel tarif spécifique.
 * Sans taille, size = 'UNIQUE' (unique variante par produit).
 */
class Variant extends BaseModel
{
    protected string $table = 'product_variants';

    protected array $intColumns = [
        'id', 'product_id', 'stock', 'sort_order',
    ];

    protected array $boolColumns = ['is_default'];

    /** DECIMAL(12,0), facultatif : tarif propre à cette taille. */
    protected array $moneyColumns = ['price_override'];

    /** true si la variante est achetable (stock disponible). */
    public function isAvailable(): bool
    {
        return (int) ($this->attributes['stock'] ?? 0) > 0;
    }

    /**
     * Prix effectif : tarif de la taille s'il existe, sinon prix produit.
     */
    public function effectivePrice(?Product $product = null): int
    {
        $override = $this->attributes['price_override'] ?? null;

        if ($override !== null && (int) $override > 0) {
            return (int) $override;
        }

        return $product !== null ? $product->effectivePrice() : 0;
    }

    /**
     * Variantes de plusieurs produits en une seule requête.
     *
     * Les cartes produit affichent leurs tailles : sans ce regroupement,
     * une page de 12 produits lancerait 12 requêtes. Le résultat est
     * indexé par product_id, prêt pour l'affichage.
     *
     * @param  array<int, int|string> $productIds
     * @return array<int, array<int, array{id: int, size: string, stock: int, available: bool}>>
     */
    public static function forProducts(array $productIds): array
    {
        $ids = array_values(array_unique(array_map(
            static fn ($id): int => (int) $id,
            $productIds
        )));

        $ids = array_filter($ids, static fn (int $id): bool => $id > 0);

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $rows = Database::select(
            "SELECT id, product_id, size, stock
             FROM `product_variants`
             WHERE `product_id` IN ($placeholders)
             ORDER BY is_default DESC, sort_order ASC, id ASC",
            $ids
        );

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row['product_id']][] = [
                'id'        => (int) $row['id'],
                'size'      => (string) $row['size'],
                'stock'     => (int) $row['stock'],
                'available' => (int) $row['stock'] > 0,
            ];
        }

        return $grouped;
    }
}