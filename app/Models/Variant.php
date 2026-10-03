<?php

declare(strict_types=1);

namespace App\Models;

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
}