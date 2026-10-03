<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;

/**
 * Panier d'achat (invité ou connecté).
 *
 * Les montants ne sont jamais stockés dans cart_items : ils sont recalcu­lés
 * à chaque lecture à partir des prix de la base (products.price), conformément
 * au principe « le serveur fait foi » (§13.3).
 */
class Cart
{
    private const SESSION_KEY = 'cart_session_id';

    /** @var array<int, array{id:int, product_id:int, variant_id:int, quantity:int, name:string, price:int, compare_at_price:?int, image_path:?string, size:?string, stock:int}> */
    private array $items = [];

    private string $ownerSessionId = '';

    private ?int $ownerUserId = null;

    public function load(): void
    {
        $this->ownerSessionId = $this->currentSessionId();
        $this->ownerUserId    = Auth::id();

        $rows = Database::select(
            'SELECT ci.id, ci.product_id, ci.variant_id, ci.quantity,
                    p.slug, p.name, p.name_en,
                    COALESCE(NULLIF(p.sale_price, 0), p.price) AS price,
                    p.price AS compare_at_price,
                    (SELECT path FROM product_images
                     WHERE product_id = p.id
                     ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS image_path,
                    v.size, v.sku, v.stock
             FROM `cart_items` ci
             INNER JOIN `product_variants` v ON v.id = ci.variant_id
             INNER JOIN `products` p        ON p.id = ci.product_id
             WHERE (ci.session_id = :session_key AND ci.user_id IS NULL)
                OR ci.user_id = :user_key
             ORDER BY ci.created_at ASC, ci.id ASC',
            ['session_key' => $this->ownerSessionId, 'user_key' => $this->ownerUserId ?? -1]
        );

        $this->items = [];

        foreach ($rows as $row) {
            $this->items[(int) $row['id']] = [
                'id'               => (int) $row['id'],
                'product_id'       => (int) $row['product_id'],
                'product_slug'     => (string) $row['slug'],
                'variant_id'       => (int) $row['variant_id'],
                'quantity'         => (int) $row['quantity'],
                'name'             => localized($row, 'name'),
                'price'            => money_int($row['price']),
                'compare_at_price' => $row['compare_at_price'] !== null ? money_int($row['compare_at_price']) : null,
                'image_path'       => $row['image_path'] !== null ? (string) $row['image_path'] : null,
                'size'             => $row['size'] !== null ? (string) $row['size'] : null,
                'stock'            => (int) $row['stock'],
            ];
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function items(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return array_sum(array_column($this->items, 'quantity'));
    }

    public function total(): int
    {
        $total = 0;

        foreach ($this->items as $item) {
            $total += $item['quantity'] * $item['price'];
        }

        return $total;
    }

    public function add(int $variantId, int $quantity): bool
    {
        // Aucun ajout si le produit est indisponible ou le stock insuffisant.
        $variant = Database::selectOne(
            'SELECT v.id, v.stock, p.id AS product_id, p.status
             FROM `product_variants` v
             INNER JOIN `products` p ON p.id = v.product_id
             WHERE v.id = :id LIMIT 1',
            ['id' => $variantId]
        );

        if ($variant === null || (string) $variant['status'] !== 'published') {
            return false;
        }

        $existing = Database::selectOne(
            'SELECT id, quantity FROM `cart_items`
             WHERE variant_id = :variant AND session_id = :session
             ORDER BY id DESC LIMIT 1',
            ['variant' => $variantId, 'session' => $this->currentSessionId()]
        );

        if ($existing !== null) {
            return $this->setQuantity((int) $existing['variant_id'], (int) $existing['quantity'] + $quantity);
        }

        Database::insert('cart_items', [
            'session_id' => $this->currentSessionId(),
            'user_id'    => $this->ownerUserId,
            'product_id' => (int) $variant['product_id'],
            'variant_id' => $variantId,
            'quantity'   => $quantity,
        ]);

        return true;
    }

    public function setQuantity(int $variantId, int $quantity): bool
    {
        $existing = Database::selectOne(
            'SELECT ci.id, ci.session_id, v.stock
             FROM `cart_items` ci
             INNER JOIN `product_variants` v ON v.id = ci.variant_id
             WHERE ci.variant_id = :variant AND ci.session_id = :session
             ORDER BY ci.id DESC LIMIT 1',
            ['variant' => $variantId, 'session' => $this->currentSessionId()]
        );

        if ($existing === null) {
            return false;
        }

        $max = max(1, (int) $existing['stock']);
        $quantity = min(max(1, $quantity), $max);

        Database::update('cart_items', ['quantity' => $quantity], ['id' => (int) $existing['id']]);

        return true;
    }

    public function remove(int $variantId): void
    {
        Database::delete('cart_items', [
            'variant_id' => $variantId,
            'session_id' => $this->currentSessionId(),
        ]);
    }

    public function clear(): void
    {
        Database::delete('cart_items', ['session_id' => $this->currentSessionId()]);
    }

    private function currentSessionId(): string
    {
        // La session PHP persiste sur la durée du navigateur ; le panier
        // invité est rattaché à l'ID de session actif.
        return (string) Session::id();
    }
}