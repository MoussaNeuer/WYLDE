<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Image d'une galerie produit.
 * path est relatif à storage/uploads et servi via /media/{path}.
 */
class ProductImage extends BaseModel
{
    protected string $table = 'product_images';

    protected array $intColumns = ['id', 'product_id', 'sort_order'];

    protected array $boolColumns = ['is_primary'];
}