<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;

/**
 * Commande transmise à l'admin par WhatsApp.
 *
 * Le client valide son checkout sur le site : la commande est d'abord
 * enregistrée en base (transaction, stock décrémenté), puis il est redirigé
 * vers WhatsApp avec un message déjà rédigé. C'est le client qui appuie sur
 * « Envoyer », donc aucune clé d'API Meta n'est nécessaire et le numéro
 * peut être un simple mobile ou une ligne professionnelle.
 *
 * Le lien wa.me est construit à partir du réglage `whatsapp_number` : le
 * numéro est modifiable depuis l'admin sans toucher au code.
 */
final class WhatsAppService
{
    /**
     * Numéro au format international, ou null si le réglage est absent/invalide.
     *
     * Seuls le « + » et les chiffres sont conservés : « +221 77 785 12 23 »
     * devient « 221777851223 », ce que wa.me attend.
     */
    public static function number(): ?string
    {
        $raw = SettingsService::get('whatsapp_number', '');

        if (! is_string($raw)) {
            return null;
        }

        // On ne garde que les chiffres, en mémorisant si un « + » a été saisi.
        $hasPlus = str_contains($raw, '+');
        $digits  = preg_replace('/\D+/', '', $raw) ?? '';

        // 8 à 15 chiffres : borne de l'E.164, assez large pour tous les pays.
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return ($hasPlus ? '+' : '') . $digits;
    }

    /** Le canal WhatsApp est-il utilisable ? (réglé + numéro exploitable) */
    public static function isEnabled(): bool
    {
        return SettingsService::getBool('whatsapp_enabled', true)
            && self::number() !== null;
    }

    /**
     * Lien wa.me vers l'admin, message pré-rempli.
     *
     * Le message est passé via `text` : c'est la seule méthode qui fonctionne
     * sur WhatsApp Web comme sur mobile, sans JavaScript ni SDK.
     */
    public static function link(string $message): ?string
    {
        $number = self::number();

        if ($number === null || trim($message) === '') {
            return null;
        }

        return 'https://wa.me/' . ltrim($number, '+') . '?text=' . rawurlencode($message);
    }

    /**
     * Message décrivant la commande, prêt à être relu par l'admin.
     *
     * Tout provient de la base : ni le client ni le navigateur ne dicte les
     * prix, les quantités ou le total (§13.3).
     */
    public static function orderMessage(Order $order): string
    {
        $lines = [];

        // Préfixe libre défini par l'admin, pour personnaliser l'accusé de réception.
        $prefix = trim((string) SettingsService::get('whatsapp_message', ''));

        if ($prefix !== '') {
            $lines[] = $prefix;
            $lines[] = '';
        }

        $lines[] = '🛍️ *NOUVELLE COMMANDE ' . config('app.name', 'WYLDE') . ' — ' . (string) $order->reference;
        $lines[] = '';

        // ── Client ──
        $lines[] = '*Client*';
        $lines[] = $order->customerName();
        $lines[] = (string) ($order->getAttribute('customer_phone') ?? '');

        $email = trim((string) ($order->getAttribute('customer_email') ?? ''));

        if ($email !== '') {
            $lines[] = $email;
        }

        $lines[] = '';
        $lines[] = '*Livraison*';
        $lines[] = $order->shippingAddress();

        // ── Lignes ──
        $lines[] = '';
        $lines[] = '*Articles (' . (int) ($order->getAttribute('item_count') ?? 0) . ')*';

        foreach ($order->items() as $item) {
            $lines[] = sprintf(
                '• %s — %s × %d = %s',
                $item->localizedName(),
                $item->sizeLabel(),
                (int) $item->quantity,
                money((int) $item->line_total)
            );
        }

        // ── Totaux ──
        $lines[] = '';
        $lines[] = 'Sous-total : ' . money((int) ($order->getAttribute('subtotal') ?? 0));

        $shipping = (int) ($order->getAttribute('shipping_cost') ?? 0);

        $lines[] = 'Livraison : ' . ($shipping > 0 ? money($shipping) : __('checkout.shipping_free'));

        $lines[] = '*TOTAL : ' . money((int) ($order->getAttribute('total') ?? 0)) . '*';

        $payment = (string) ($order->getAttribute('payment_method') ?? 'cod');

        $lines[] = 'Paiement : ' . ($payment === 'wave'
            ? __('checkout.payment_wave')
            : __('checkout.payment_cod'));

        $notes = trim((string) ($order->getAttribute('notes') ?? ''));

        if ($notes !== '') {
            $lines[] = '';
            $lines[] = '*Remarque* : ' . $notes;
        }

        return implode("\n", $lines);
    }
}
