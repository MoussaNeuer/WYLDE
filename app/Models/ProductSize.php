<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Catalogue des tailles disponibles.
 *
 * C'est l'admin qui décide des tailles proposées : le formulaire produit
 * n'affiche que celles-ci, et le sélecteur public suit l'ordre défini
 * ici. Les variantes stockent le libellé en texte (`product_variants`
 * .size), ce qui permet de compter l'usage d'une taille avant de la
 * supprimer et de la retirer d'abord des produits concernés.
 *
 * @property int    $id
 * @property string $label
 * @property int    $sort_order
 */class ProductSize extends BaseModel
{
    /** Longueur alignée sur product_variants.size. */
    public const MAX_LENGTH = 20;

    protected string $table = 'product_sizes';

    protected array $intColumns = ['id', 'sort_order'];

    /**
     * Tout le catalogue, dans l'ordre d'affichage.
     *
     * @return array<int, self>
     */
    public static function ordered(): array
    {
        return self::all('`sort_order` ASC, `label` ASC');
    }

    /**
     * Libellés du catalogue, prêts pour une liste déroulante.
     *
     * @return array<int, string>
     */
    public static function labels(): array
    {
        return array_map(static fn (self $size): string => $size->label, self::ordered());
    }

    /** Libellés indexés par valeur, pour les tests d'appartenance. */
    public static function labelMap(): array
    {
        $map = [];

        foreach (self::ordered() as $size) {
            $map[$size->label] = true;
        }

        return $map;
    }

    /** Le libellé existe-t-il déjà dans le catalogue ? */
    public static function labelExists(string $label): bool
    {
        return Database::selectValue(
            'SELECT COUNT(*) FROM `product_sizes` WHERE `label` = :label',
            ['label' => self::normalize($label)]
        ) > 0;
    }

    /**
     * Variantes qui portent cette taille, tous produits confondus.
     *
     * Le catalogue ne peut pas être purgé pendant qu'un produit l'utilise :
     * le formulaire produit n'accepterait plus cette valeur.
     */
    public function usageCount(): int
    {
        return (int) Database::selectValue(
            'SELECT COUNT(*) FROM `product_variants` WHERE `size` = :size',
            ['size' => $this->label]
        );
    }

    /** Produits distincts qui utilisent cette taille. */
    public function productCount(): int
    {
        return (int) Database::selectValue(
            'SELECT COUNT(DISTINCT `product_id`) FROM `product_variants` WHERE `size` = :size',
            ['size' => $this->label]
        );
    }

    /** Taille du catalogue, pour le résumé affiché dans le menu. */
    public static function total(): int
    {
        return self::count();
    }

    /** Prochaine position d'affichage, en fin de liste. */
    public static function nextSortOrder(): int
    {
        $max = Database::selectValue('SELECT MAX(`sort_order`) FROM `product_sizes`');

        return $max === null ? 10 : ((int) $max) + 10;
    }

    /**
     * Normalise un libellé saisi : espaces internes réduits, majuscules,
     * longueur bornée. Les tailles sont des codes (« S », « 38 », « XL »),
     * la casse ne doit donc pas créer deux entrées.
     */
    public static function normalize(string $label): string
    {
        $label = preg_replace('/\s+/u', ' ', trim($label)) ?? '';

        return mb_substr(mb_strtoupper($label, 'UTF-8'), 0, self::MAX_LENGTH);
    }
}
