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
     * Erreurs de validation rencontrées par zoneData().
     *
     * zoneData() rend null pour « label manquant » comme pour les autres
     * erreurs : ce tableau lève l'ambiguïté aux appelants.
     *
     * @var array<string, string>
     */
    private array $zoneErrors = [];
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
        'whatsapp_number'   => 'Numéro WhatsApp (commandes)',
        'whatsapp_enabled'  => 'Activer la commande via WhatsApp',
        'whatsapp_message'  => 'Message d’introduction WhatsApp',
        'free_shipping_threshold' => 'Livraison offerte à partir de (XOF)',
    ];

    /**
     * Clés numériques : un seuil négatif ou non entier casserait le
     * calcul de la barre de progression, on le refuse donc au lieu de
     * le laisser passer en base.
     *
     * @var array<string, int>
     */
    private const INT_KEYS = [
        'free_shipping_threshold' => 0,
    ];

    /**
     * Clés attendues sous forme d'URL (https obligatoire).
     */
    private const URL_KEYS = ['wave_payment_link', 'credit_url'];

    /**
     * Clés gérées par une case à cocher : absentes du POST = désactivées.
     *
     * @var array<int, string>
     */
    private const BOOL_KEYS = ['whatsapp_enabled'];

    public function index(Request $request): Response
    {
        $values = [];

        foreach (self::KEYS as $key => $label) {
            $values[$key] = in_array($key, self::BOOL_KEYS, true)
                // La case est cochée quand la valeur est vraie : on stocke
                // « 1 » et la vue compare à « 1 ».
                ? (SettingsService::getBool($key, false) ? '1' : '')
                : (string) SettingsService::get($key, '');
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
            // Une case à cocher absente du POST doit restrictive à « 0 »,
            // sinon impossible de désactiver une option.
            if (in_array($key, self::BOOL_KEYS, true)) {
                $pairs[$key] = $request->str($key) === '1' ? '1' : '0';

                continue;
            }

            $value = trim($request->str($key));

            if (in_array($key, self::URL_KEYS, true) && $value !== '' && !str_starts_with($value, 'https://')) {
                return $this->redirectWithErrors('/admin/settings', [
                    $key => 'Le lien doit commencer par https://',
                ], $request->all());
            }

            if (array_key_exists($key, self::INT_KEYS)) {
                $min = self::INT_KEYS[$key];

                // Champ vide = seuil désactivé : c'est ce que l'équipe veut
                // quand elle ne fait pas de livraison offerte.
                if ($value === '') {
                    $pairs[$key] = '0';

                    continue;
                }

                if (!ctype_digit($value) || (int) $value < $min) {
                    return $this->redirectWithErrors('/admin/settings', [
                        $key => 'Ce montant doit être un nombre entier'
                            . ($min > 0 ? ' supérieur ou égal à ' . $min . '.' : '.'),
                    ], $request->all());
                }

                $pairs[$key] = (string) (int) $value;

                continue;
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
            $errors = $this->zoneErrors !== []
                ? $this->zoneErrors
                : ['label' => __('validation.required', ['field' => __('admin.shipping.label')])];

            return $this->redirectWithErrors('/admin/shipping-zones', $errors, $request->all());
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
            $errors = $this->zoneErrors !== []
                ? $this->zoneErrors
                : ['label' => __('validation.required', ['field' => __('admin.shipping.label')])];

            return $this->redirectWithErrors('/admin/shipping-zones', $errors, $request->all());
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
     *
     * Retourne null dès qu'une erreur de validation survient ; les messages
     * sont alors dans $this->zoneErrors (le label vide rend null SANS
     * remplir zoneErrors, pour conserver le comportement historique).
     */
    private function zoneData(Request $request): ?array
    {
        $this->zoneErrors = [];

        $label = trim($request->str('label'));

        if ($label === '') {
            return null;
        }

        $country = strtoupper(trim($request->str('country_code')));

        // country_code est VARCHAR(2) + ENUM-like par la liste : un code qui
        // n'est pas exactement 2 lettres (ex. « FRA ») ferait tronquer ou
        // échouer l'écriture en mode strict → erreur claire à la place.
        if ($country !== '' && preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            $this->zoneErrors['country_code'] = __('admin.shipping.country_code_invalid');
        }

        $priceRaw = trim($request->str('price'));

        // price est DECIMAL(12,0) : une valeur non numérique ou dépassant la
        // capacité déclencherait un 500 (Data truncated / Out of range).
        if ($priceRaw === '' || preg_match('/^\d+$/', $priceRaw) !== 1) {
            $this->zoneErrors['price'] = __('validation.numeric', ['field' => __('admin.shipping.price')]);
        }

        if ($this->zoneErrors !== []) {
            return null;
        }

        $price = min((int) $priceRaw, 999999999999);

        $city = trim($request->str('city'));

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