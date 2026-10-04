<?php

declare(strict_types=1);

namespace App\Validators;

use App\Models\Product;
use App\Models\ProductSize;

/**
 * Validation du formulaire produit (création et édition).
 */
class ProductValidator extends Validator
{
    /**
     * @param array<string, mixed> $data  Données brutes du formulaire
     * @param int|null             $id    Produit en cours d'édition
     */
    public function __construct(array $data = [], private ?int $id = null)
    {
        parent::__construct($data);
    }

    public function validate(): void
    {
        $name  = $this->string('name', __('admin.product.name'));
        $label = __($this->id !== null ? 'admin.product.edit' : 'admin.product.new');

        $this->required('name', $name);
        $this->maxLength('name', $name, 180);
        $this->maxLength('name_en', 'Nom EN', 180);

        // price est NOT NULL et strictement positif : sans ce contrôle,
        // un champ absent produirait silencieusement un produit à 0 FCFA.
        $this->required('price', __('admin.product.price'));

        $this->maxLength('short_description', 'Description courte', 320);
        $this->maxLength('short_description_en', 'Description courte EN', 320);

        $this->integer('category_id', __('admin.product.category'));
        $this->positive('price', __('admin.product.price'));
        $this->positive('sale_price', __('admin.product.sale_price'));

        $this->inList('status', __('common.status'), [
            Product::STATUS_DRAFT,
            Product::STATUS_PUBLISHED,
            'hidden',
            Product::STATUS_ARCHIVED,
        ]);

        $this->inList('label', 'Label', ['none', 'new', 'bestseller', 'limited']);

        $this->maxLength('seo_title', 'SEO titre', 190);
        $this->maxLength('seo_description', 'SEO description', 320);

        $this->maxLength('sku', 'SKU', 64);

        $slug = $this->string('slug', 'Slug');

        if ($slug !== '') {
            $this->maxLength('slug', 'Slug', 200);
            $this->slugUnique($slug, 'products', $this->id);
        }

        // Variantes : uniquement si le produit a des tailles. Le panneau
        // des variantes reste soumis même quand il est masqué, donc sans
        // cette condition des lignes oubliées seraient contrôlées.
        if (!empty($this->data['has_sizes'])) {
            $this->validateSizes();
        }
    }

    /**
     * Contrôle les tailles du produit contre le catalogue admin.
     *
     * Le formulaire n'offre que des listes déroulantes, mais la requête
     * peut être forgée : une taille hors catalogue est refusée ici,
     * sinon un produit porterait une taille que l'admin ne peut plus
     * choisir — et dont la suppression resterait bloquée par cette
     * variante. Les valeurs sont normalisées comme à l'enregistrement.
     */
    private function validateSizes(): void
    {
        $sizes      = $this->data['sizes'] ?? [];
        $catalogue  = ProductSize::labelMap();
        $normalized = [];

        if (!is_array($sizes)) {
            return;
        }

        foreach ($sizes as $size) {
            if (!is_string($size) || trim($size) === '') {
                continue;
            }

            $label = ProductSize::normalize($size);

            if (!isset($catalogue[$label])) {
                $this->addError('sizes', __('admin.sizes.error_unknown', ['label' => $label]));

                return;
            }

            $normalized[] = $label;
        }

        if (array_diff_assoc($normalized, array_unique($normalized)) !== []) {
            $this->addError('sizes', __('admin.sizes.error_duplicate'));
        }
    }

    /** @return array<string, string|int|null> */
    public function productData(): array
    {
        return [
            'name'                  => $this->string('name', 'name'),
            'name_en'               => $this->nullable('name_en'),
            'slug'                  => '',
            'category_id'           => ($this->value('category_id') !== null && (string) $this->value('category_id') !== '')
                ? (int) $this->value('category_id')
                : null,
            'short_description'     => $this->nullable('short_description'),
            'short_description_en'  => $this->nullable('short_description_en'),
            'description'           => $this->nullable('description'),
            'description_en'        => $this->nullable('description_en'),
            'price'                 => (int) $this->value('price'),
            'sale_price'            => $this->nullable('sale_price'),
            'cost_price'            => $this->nullable('cost_price'),
            'status'                => $this->stringOr('status', 'draft'),
            'label'                 => $this->stringOr('label', 'none'),
            'sku'                   => $this->nullable('sku'),
            'has_sizes'             => !empty($this->data['has_sizes']) ? 1 : 0,
            'is_featured'           => !empty($this->data['is_featured']) ? 1 : 0,
            'seo_title'             => $this->nullable('seo_title'),
            'seo_title_en'          => $this->nullable('seo_title_en'),
            'seo_description'       => $this->nullable('seo_description'),
            'seo_description_en'    => $this->nullable('seo_description_en'),
        ];
    }

    /**
     * Variantes à écrire (sizes + stocks + skus associés).
     *
     * @return array<int, array{size: string, sku: string, stock: int, price_override: ?int, is_default?: bool}>
     */
    public function variantsData(bool $singleVariantOnly = false): array
    {
        if ($singleVariantOnly) {
            return [[
                'size'            => 'UNIQUE',
                'sku'             => (string) $this->value('sku'),
                'stock'           => (int) $this->value('stock'),
                'price_override'  => null,
            ]];
        }

        $sizes  = is_array($this->data['sizes'] ?? null) ? $this->data['sizes'] : [];
        $stocks = is_array($this->data['stock_per_size'] ?? null) ? $this->data['stock_per_size'] : [];
        $skus   = is_array($this->data['sku_per_size'] ?? null) ? $this->data['sku_per_size'] : [];
        $prices = is_array($this->data['price_per_size'] ?? null) ? $this->data['price_per_size'] : [];

        $variants = [];

        foreach ($sizes as $index => $size) {
            if (!is_string($size) || trim($size) === '') {
                continue;
            }

            $price = isset($prices[$index]) && is_numeric($prices[$index])
                ? (int) $prices[$index]
                : null;

            $variants[] = [
                // Libellé du catalogue, pas la saisie brute : « s »
                // devient « S », comme dans /admin/sizes.
                'size'           => ProductSize::normalize($size),
                'sku'            => isset($skus[$index]) && is_string($skus[$index]) ? trim($skus[$index]) : '',
                'stock'          => isset($stocks[$index]) && is_numeric($stocks[$index]) ? max(0, (int) $stocks[$index]) : 0,
                'price_override' => $price,
            ];
        }

        if ($variants === []) {
            $variants[] = [
                'size'           => 'UNIQUE',
                'sku'            => (string) $this->value('sku'),
                'stock'          => max(0, (int) $this->value('stock')),
                'price_override' => null,
            ];
        }

        // La première variante devient la variante par défaut.
        $variants[0]['is_default'] = true;

        return $variants;
    }
}