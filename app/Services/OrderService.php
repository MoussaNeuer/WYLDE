<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Order;

/**
 * Cycle de vie d'une commande côté back-office (§7).
 *
 * Toute évolution de statut passe par ici : elle est transactionnelle,
 * elle écrit une ligne dans order_status_history, elle journalise
 * l'action et elle restitue le stock si la commande est annulée.
 */
final class OrderService
{
    /**
     * Change le statut d'une commande.
     *
     * @return array{ok: bool, error: string, order: Order, auto_paid: bool}
     */
    public static function changeStatus(Order $order, string $status, string $note = ''): array
    {
        if (!in_array($status, Order::statuses(), true)) {
            return ['ok' => false, 'error' => 'statut inconnu', 'order' => $order, 'auto_paid' => false];
        }

        $current = (string) $order->status;

        if ($current === $status) {
            return ['ok' => true, 'error' => '', 'order' => $order, 'auto_paid' => false];
        }

        if (!$order->canTransitionTo($status)) {
            return [
                'ok'        => false,
                'error'     => 'transition ' . $current . ' -> ' . $status . ' interdite',
                'order'     => $order,
                'auto_paid' => false,
            ];
        }

        $note = mb_substr(trim($note), 0, 255);
        $autoPaid = false;

        Database::transaction(static function () use ($order, $status, $note, $current, &$autoPaid): void {
            $data = ['status' => $status];

            // Horodatage métier : seules les colonnes réellement
            // prévues par le schéma sont écrites.
            $now = date('Y-m-d H:i:s');

            $stampColumn = match ($status) {
                Order::STATUS_SHIPPED   => 'shipped_at',
                Order::STATUS_DELIVERED => 'delivered_at',
                Order::STATUS_CANCELLED => 'cancelled_at',
                default                 => null,
            };

            if ($stampColumn !== null) {
                $data[$stampColumn] = $now;
            }

            // Annulation : le stock part avec la commande.
            if ($status === Order::STATUS_CANCELLED) {
                StockService::applyOrderItems($order, 1);

                $data['cancelled_reason'] = $note !== '' ? $note : null;
            }

            // Livraison en paiement à la livraison : l'admin encaisse au
            // passage du livreur, on bascule donc la commande en « payée ».
            if (
                $status === Order::STATUS_DELIVERED
                && $order->payment_method === Order::METHOD_COD
                && $order->payment_status === Order::PAYMENT_UNPAID
            ) {
                $data['payment_status'] = Order::PAYMENT_PAID;
                $data['paid_at']        = $now;
                $autoPaid              = true;
            }

            Database::update('orders', $data, ['id' => $order->id()]);

            self::addHistory((int) $order->id(), $status, $note);

            AuditService::log(AuditService::ACTION_ORDER_STATUS, 'orders', (int) $order->id(), [
                'from'      => $current,
                'to'        => $status,
                'note'      => $note,
                'auto_paid' => $autoPaid,
            ]);
        });

        // Recharge pour que la vue reflète l'état réellement enregistré.
        $fresh = Order::find((int) $order->id());

        return [
            'ok'        => true,
            'error'     => '',
            'order'     => $fresh ?? $order,
            'auto_paid' => $autoPaid,
        ];
    }

    /**
     * Bascule le statut de paiement.
     *
     * @return array{ok: bool, error: string, order: Order}
     */
    public static function changePaymentStatus(Order $order, string $paymentStatus): array
    {
        if (!in_array($paymentStatus, Order::paymentStatuses(), true)) {
            return ['ok' => false, 'error' => 'statut de paiement inconnu', 'order' => $order];
        }

        if ($paymentStatus === (string) $order->payment_status) {
            return ['ok' => true, 'error' => '', 'order' => $order];
        }

        $previous = (string) $order->payment_status;

        Database::transaction(static function () use ($order, $paymentStatus, $previous): void {
            $data = ['payment_status' => $paymentStatus];

            if ($paymentStatus === Order::PAYMENT_PAID) {
                $data['paid_at'] = date('Y-m-d H:i:s');
            } elseif ($paymentStatus === Order::PAYMENT_REFUNDED) {
                // Un remboursement remet la commande en attente : l'équipe
                // décide de la rejouer ou de l'annuler.
                $data['paid_at'] = null;
            }

            Database::update('orders', $data, ['id' => $order->id()]);

            self::addHistory(
                (int) $order->id(),
                (string) $order->status,
                __('admin.order.mark_paid') . ' : ' . $paymentStatus
            );

            AuditService::log(AuditService::ACTION_ORDER_PAYMENT, 'orders', (int) $order->id(), [
                'from' => $previous,
                'to'   => $paymentStatus,
            ]);
        });

        $fresh = Order::find((int) $order->id());

        return ['ok' => true, 'error' => '', 'order' => $fresh ?? $order];
    }

    /**
     * Notes internes et numéro de suivi.
     *
     * @return array{ok: bool, error: string, order: Order}
     */
    public static function updateNotes(Order $order, ?string $notes, ?string $tracking = null): array
    {
        $data = [];

        if ($notes !== null) {
            $data['admin_notes'] = trim($notes) !== '' ? $notes : null;
        }

        if ($tracking !== null) {
            $data['tracking_number'] = trim($tracking) !== '' ? $tracking : null;
        }

        if ($data === []) {
            return ['ok' => true, 'error' => '', 'order' => $order];
        }

        Database::update('orders', $data, ['id' => $order->id()]);

        if (isset($data['admin_notes'])) {
            AuditService::log(AuditService::ACTION_ORDER_NOTES, 'orders', (int) $order->id());
        }

        $fresh = Order::find((int) $order->id());

        return ['ok' => true, 'error' => '', 'order' => $fresh ?? $order];
    }

    /**
     * Trace un changement de statut.
     * changed_by NULL = action automatique ou demandée par le client.
     */
    public static function addHistory(int $orderId, string $status, string $note = '', ?int $userId = null): void
    {
        Database::insert('order_status_history', [
            'order_id'   => $orderId,
            'status'     => $status,
            'note'       => trim($note) !== '' ? $note : null,
            'changed_by' => $userId ?? auth()?->id(),
        ]);
    }

    /**
     * Contrôle de cohérence avant traitement d'une commande.
     * Utilisé par le back-office pour signaler les commandes à vérifier.
     *
     * @return array<int, string>
     */
    public static function warnings(Order $order): array
    {
        $warnings = [];

        $stock = 0;

        foreach ($order->items() as $item) {
            $variant = $item->variant();

            if ($variant === null) {
                $warnings[] = 'Variante supprimée pour « ' . $item->localizedName() . ' »';
                continue;
            }

            $available = (int) $variant->stock;

            if ($available < (int) $item->quantity) {
                $stock += $available;
                $warnings[] = 'Stock insuffisant pour « ' . $item->localizedName() . ' » ('
                    . $available . ' restant' . ($available > 1 ? 's' : '') . ')';
            }
        }

        if ($order->shipping_zone_id === null) {
            $warnings[] = 'Zone de livraison inconnue (tarif probablement 0)';
        }

        if ($order->payment_method === Order::METHOD_WAVE && $order->payment_status === Order::PAYMENT_UNPAID) {
            $warnings[] = 'Paiement Wave en attente de confirmation manuelle';
        }

        return $warnings;
    }
}
