<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\ProductSize;
use App\Models\ShippingZone;
use App\Services\AuditService;
use App\Services\SettingsService;

/**
 * Paramètres boutique et zones de livraison.
 *
 * Les paramètres sont stockés dans `settings` (clé/valeur) et lus par
 * SettingsService avec une cache par requête.
 */
final class SettingsController extends AdminController
{
    /**
     * Clés modifiables depuis l'interface, avec leur libellé.
     *
     * @return array<string, string>
     */
    private const KEYS = [
        'shop_name'         => 'Nom de la boutique',
        'shop_tagline'      => 'Slogan',
        'shop_email'        => 'E-mail de contact',
        'shop_phone'        => 'Téléphone',
        'shop_address'      => 'Adresse',
        'wave_payment_link' => 'Lien Wave Business',
        'credit_name'       => 'Crédit « site créé par »',
        'credit_url'        => 'Lien du site du créateur',
    ];

    /**
     * Clés attendues sous forme d'URL (https obligatoire).
     */
    private const URL_KEYS = ['wave_payment_link', 'credit_url'];

    public function index(Request $request): Response
    {
        $values = [];

        foreach (self::KEYS as $key => $label) {
            $values[$key] = (string) SettingsService::get($key, '');
        }

        return $this->view('admin/settings/index', [
            'title'  => __('admin.settings'),
            'keys'   => self::KEYS,
            'values' => $values,
            // Rappel du catalogue : le formulaire produit ne propose que
            // ces tailles, la page dédiée reste le seul endroit où les
            // ajouter ou en retirer.
            'sizes_total' => ProductSize::total(),
        ]);
    }

    public function update(Request $request): Response
    {
        $pairs = [];

        foreach (self::KEYS as $key => $label) {
            $value = trim($request->str($key));

            if (in_array($key, self::URL_KEYS, true) && $value !== '' && !str_starts_with($value, 'https://')) {
                return $this->redirectWithErrors('/admin/settings', [
                    $key => 'Le lien doit commencer par https://',
                ], $request->all());
            }

            $pairs[$key] = $value;
        }

        SettingsService::putMany($pairs);

        AuditService::log(AuditService::ACTION_SETTINGS_UPDATE, 'settings', null, [
            'keys' => array_keys(self::KEYS),
        ]);

        return $this->redirectWithSuccess('/admin/settings', __('flash.settings_saved'));
    }

    // ── Zones de livraison ───────────────────────────────────

    public function shippingZones(Request $request): Response
    {
        $zones = Database::select(
            'SELECT z.*, COUNT(DISTINCT o.id) AS order_count
             FROM `shipping_zones` z
             LEFT JOIN `orders` o ON o.shipping_zone_id = z.id
             GROUP BY z.id
             ORDER BY z.is_default DESC, z.sort_order ASC, z.label ASC'
        );

        return $this->view('admin/settings/zones', [
            'title'  => __('admin.shipping.title'),
            'zones'  => $zones,
            'countries' => ShippingZone::availableCountries(),
        ]);
    }

    public function storeShippingZone(Request $request): Response
    {
        $data = $this->zoneData($request);

        if ($data === null) {
            return $this->redirectWithErrors('/admin/shipping-zones', ['label' => __('validation.required', ['field' => __('admin.shipping.label')])], $request->all());
        }

        self::enforceSingleDefault($data);

        ShippingZone::create($data);

        AuditService::log(AuditService::ACTION_ZONE_CREATE, 'shipping_zones', null, ['label' => $data['label']]);

        return $this->redirectWithSuccess('/admin/shipping-zones', __('flash.zone_saved'));
    }

    public function updateShippingZone(Request $request): Response
    {
        $zone = ShippingZone::findOrFail($this->id($request));

        $data = $this->zoneData($request);

        if ($data === null) {
            return $this->redirectWithErrors('/admin/shipping-zones', ['label' => __('validation.required', ['field' => __('admin.shipping.label')])], $request->all());
        }

        self::enforceSingleDefault($data);

        $zone->fill($data);
        $zone->save();

        AuditService::log(AuditService::ACTION_ZONE_UPDATE, 'shipping_zones', (int) $zone->id(), ['label' => $data['label']]);

        return $this->redirectWithSuccess('/admin/shipping-zones', __('flash.zone_saved'));
    }

    public function destroyShippingZone(Request $request): Response
    {
        $zone = ShippingZone::findOrFail($this->id($request));

        $label = $zone->label;

        $zone->delete();

        AuditService::log(AuditService::ACTION_ZONE_DELETE, 'shipping_zones', (int) $zone->id(), ['label' => $label]);

        return $this->redirectWithSuccess('/admin/shipping-zones', __('flash.zone_deleted'));
    }

    /**
     * Une seule zone peut être « par défaut » : on la retire des autres
     * avant d'enregistrer.
     *
     * @param array<string, mixed> $data
     */
    private static function enforceSingleDefault(array &$data): void
    {
        if (empty($data['is_default'])) {
            return;
        }

        // Retire le drapeau à toutes les autres zones, puis marque celle-ci.
        Database::update('shipping_zones', ['is_default' => 0], ['is_default' => 1]);

        $data['is_default'] = 1;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function zoneData(Request $request): ?array
    {
        $label = trim($request->str('label'));

        if ($label === '') {
            return null;
        }

        $country = strtoupper(trim($request->str('country_code')));
        $city    = trim($request->str('city'));
        $price   = max(0, $request->int('price'));

        return [
            'label'        => mb_substr($label, 0, 120),
            'country_code' => $country !== '' ? $country : null,
            'city'         => $city !== '' ? mb_substr($city, 0, 120) : null,
            'country_name' => $country !== '' ? (ShippingZone::availableCountries()[$country] ?? $country) : null,
            'price'        => $price,
            'is_default'   => $request->bool('is_default'),
            'status'       => $request->bool('active') ? 'active' : 'inactive',
            'sort_order'   => $request->int('sort_order'),
        ];
    }
}