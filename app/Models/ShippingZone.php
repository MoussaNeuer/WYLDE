<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Zone de livraison.
 *
 * Résolution : ville exacte → pays (ville NULL) → zone par défaut.
 * Dans tous les cas, seules les zones actives sont considérées. Si rien
 * ne correspond, la livraison est indisponible pour cette destination.
 */
class ShippingZone extends BaseModel
{
    protected string $table = 'shipping_zones';

    protected array $intColumns = ['id', 'sort_order'];

    /** DECIMAL(12,0) : frais en FCFA, jamais de centimes. */
    protected array $moneyColumns = ['price'];

    protected array $boolColumns = ['is_default'];

    /**
     * Résout la zone applicable à une destination.
     *
     * @return array{zone: ?self, available: bool, price: int, label: string}
     */
    public static function resolve(string $countryCode, string $city = ''): array
    {
        $countryCode = strtoupper(substr(trim($countryCode), 0, 2));
        $city        = mb_strtolower(trim($city), 'UTF-8');

        $fallback = ['zone' => null, 'available' => false, 'price' => 0, 'label' => ''];

        // 1. Ville exacte dans le pays.
        if ($city !== '') {
            $row = Database::selectOne(
                "SELECT * FROM `shipping_zones`
                 WHERE `status` = 'active' AND `country_code` = :country AND `city` = :city
                 LIMIT 1",
                ['country' => $countryCode, 'city' => $city]
            );

            if ($row !== null) {
                return static::result($row);
            }
        }

        // 2. Tout le pays (ville NULL).
        $row = Database::selectOne(
            "SELECT * FROM `shipping_zones`
             WHERE `status` = 'active'
               AND `country_code` = :country AND `city` IS NULL
             ORDER BY `is_default` DESC, `sort_order` ASC
             LIMIT 1",
            ['country' => $countryCode]
        );

        if ($row !== null) {
            return static::result($row);
        }

        // 3. Zone par défaut (reste du monde administré).
        $row = Database::selectOne(
            "SELECT * FROM `shipping_zones`
             WHERE `status` = 'active' AND `country_code` IS NULL
             ORDER BY `is_default` DESC, `sort_order` ASC
             LIMIT 1"
        );

        if ($row !== null) {
            return static::result($row);
        }

        return $fallback;
    }

    /**
     * Devis de livraison pour un panier donné, avec indisponibilité
     * explicite si aucune zone ne couvre la destination.
     *
     * @return array{zone: ?self, available: bool, price: int, label: string}
     */
    public static function quote(string $countryCode, string $city = '', int $subtotal = 0): array
    {
        $quote = static::resolve($countryCode, $city);

        return static::applyFreeShipping($quote, $subtotal);
    }

    /**
     * Applique le seuil de livraison offerte au devis.
     *
     * Le prix de la zone est conservé tel quel dans `zone` : seul le prix
     * facturé tombe à zéro. Un panier vide n'est jamais « offert », sinon
     * la barre du mini-panier afficherait une remise sur un panier vide.
     *
     * @param  array{zone: ?self, available: bool, price: int, label: string} $quote
     * @return array{zone: ?self, available: bool, price: int, label: string}
     */
    public static function applyFreeShipping(array $quote, int $subtotal): array
    {
        if ($subtotal < 1 || free_shipping_threshold() < 1) {
            return $quote;
        }

        if ($subtotal >= free_shipping_threshold()) {
            $quote['price'] = 0;
        }

        return $quote;
    }

    /** @return array<string, string> Pays desservis (code ISO => nom). */
    public static function availableCountries(): array
    {
        $rows = Database::select(
            "SELECT DISTINCT `country_code`, `country_name`
             FROM `shipping_zones`
             WHERE `status` = 'active' AND `country_code` IS NOT NULL
             ORDER BY `country_name` ASC"
        );

        $countries = [];

        foreach ($rows as $row) {
            $code = (string) $row['country_code'];
            $name = (string) ($row['country_name'] ?: $code);

            $countries[$code] = $name;
        }

        // La zone par défaut couvre le reste du monde.
        $defaultExists = Database::selectValue(
            "SELECT COUNT(*) FROM `shipping_zones`
             WHERE `status` = 'active' AND `country_code` IS NULL"
        );

        if ((int) $defaultExists > 0) {
            $countries['ZZ'] = __('checkout.rest_of_world');
        }

        return $countries;
    }

    /**
     * Pays réellement desservis au checkout, sans la zone de repli « ZZ ».
     *
     * Le code pseudo-pays « ZZ » représente la zone par défaut et reste un
     * choix interne de la boutique : au tunnel de commande on n'accepte
     * que les pays ayant une zone explicite (pays ou ville), afin qu'un
     * code forgé ou erroné ne se retrouve jamais facturé au tarif de repli.
     *
     * @return array<string, string> Code ISO => nom.
     */
    public static function deliverableCountries(): array
    {
        $countries = static::availableCountries();

        unset($countries['ZZ']);

        return $countries;
    }

    /** @return array<int, self> */
    public static function allActive(): array
    {
        return array_map(
            static fn (array $row): self => (new self())->hydrate($row),
            Database::select(
                "SELECT * FROM `shipping_zones`
                 WHERE `status` = 'active'
                 ORDER BY `is_default` DESC, `country_code` ASC, `sort_order` ASC, `city` ASC"
            )
        );
    }

    /**
     * @param  array<string, mixed> $row
     * @return array{zone: self, available: bool, price: int, label: string}
     */
    private static function result(array $row): array
    {
        $zone = (new self())->hydrate($row);

        return [
            'zone'      => $zone,
            'available' => true,
            'price'     => money_int($zone->price),
            'label'     => (string) ($zone->label ?? $zone->country_name ?? ''),
        ];
    }
}