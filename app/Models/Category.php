<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Catégorie / collection de la boutique.
 * parent_id permet d'imbriquer (§5) : les enfants restent ceints
 * à la collection parente pour calculer les effectifs produits.
 */
class Category extends BaseModel
{
    protected string $table = 'categories';

    protected array $intColumns = ['id', 'parent_id', 'sort_order'];

    protected array $boolColumns = [];

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public static function findBySlug(string $slug): ?static
    {
        $model = static::query(
            'SELECT * FROM `categories` WHERE `slug` = :slug AND `status` = :status LIMIT 1',
            ['slug' => $slug, 'status' => self::STATUS_ACTIVE]
        );

        return $model->exists() ? $model : null;
    }

    /** Nom traduit avec repli français. */
    public function localizedName(?string $locale = null): string
    {
        $locale ??= \App\Core\Lang::locale();

        $localized = $locale === 'en'
            ? ($this->attributes['name_en'] ?? null)
            : null;

        return (string) ($localized !== null && $localized !== '' ? $localized : ($this->attributes['name'] ?? ''));
    }

    /** @return array<int, static> */
    public static function allActive(): array
    {
        return array_map(
            static fn (array $row): static => (new static())->hydrate($row),
            Database::select(
                'SELECT * FROM `categories`
                 WHERE `status` = :status
                 ORDER BY `sort_order` ASC, `name` ASC',
                ['status' => self::STATUS_ACTIVE]
            )
        );
    }

    /**
     * Produits rattachés (directement ou via un enfant).
     *
     * Une catégorie non vide ne se supprime pas : on refuse plutôt que de
     * laisser des produits orphelins sans catégorie.
     */
    public function productCount(): int
    {
        return (int) Database::selectValue(
            'SELECT COUNT(*) FROM `products` WHERE `category_id` = :id',
            ['id' => $this->id()]
        );
    }
}