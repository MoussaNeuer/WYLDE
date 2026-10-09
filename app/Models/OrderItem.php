<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Ligne de commande.
 *
 * product_name, size et unit_price sont figés à l'achat : la commande
 * reste exacte même si le produit, son nom ou son prix changent (§7).
 */
class OrderItem extends BaseModel
{
    protected string $table = 'order_items';

    /** @var array<int, string> */
    protected array $fillable = [
        'order_id', 'product_id', 'variant_id', 'product_name', 'product_name_en',
        'sku', 'size', 'quantity', 'unit_price', 'line_total', 'image_path',
    ];

    protected array $intColumns = [
        'id', 'order_id', 'product_id', 'variant_id', 'quantity',
    ];

    /** DECIMAL(12,0) : FCFA, entiers. */
    protected array $moneyColumns = ['unit_price', 'line_total'];

    /** Nom traduit avec repli français (§11). */
    public function localizedName(?string $locale = null): string
    {
        $locale ??= \App\Core\Lang::locale();

        $localized = $locale === 'en'
            ? ($this->attributes['product_name_en'] ?? null)
            : null;

        return (string) ($localized !== null && $localized !== ''
            ? $localized
            : ($this->attributes['product_name'] ?? ''));
    }

    public function sizeLabel(): string
    {
        return size_label((string) ($this->attributes['size'] ?? ''));
    }

    public function product(): ?Product
    {
        $id = $this->attributes['product_id'] ?? null;

        return $id === null ? null : $this->belongsTo(Product::class, 'product_id', $id);
    }

    public function variant(): ?Variant
    {
        $id = $this->attributes['variant_id'] ?? null;

        return $id === null ? null : $this->belongsTo(Variant::class, 'variant_id', $id);
    }

    /** Le produit existe-t-il encore ? (produits supprimés en cascade SET NULL) */
    public function isProductAvailable(): bool
    {
        return $this->product() !== null;
    }
}
