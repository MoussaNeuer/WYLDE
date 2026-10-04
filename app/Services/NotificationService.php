<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Product;

/**
 * Notifications du back-office.
 *
 * Tout est déduit de l'état réel de la boutique : rien n'est stocké, donc
 * rien ne peut devenir obsolète. Les compteurs sont volontairement peu
 * nombreux (une requête chacun) car le menu les affiche sur chaque écran.
 */
final class NotificationService
{
    /** @var array<int, array<string, mixed>>|null Mémoïsation de la requête courante. */
    private static ?array $cache = null;

    /**
     * Liste des alertes, la plus urgente en premier.
     *
     * Chaque entrée : type, level (danger|warning|info), count, libellé,
     * détail et URL de l'action corrective.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function alerts(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $actionable = Order::countActionable();
        $out        = StockService::countOut();
        $low        = StockService::countLow();
        $unpaid     = Order::countUnpaid();
        $drafts     = Product::countByStatus(Product::STATUS_DRAFT);

        $alerts = [];

        if ($out > 0) {
            $alerts[] = self::alert(
                'out_of_stock',
                'danger',
                $out,
                __('admin.notifications.out_of_stock'),
                __('admin.notifications.out_of_stock_hint'),
                '/admin/inventory?level=out'
            );
        }

        if ($actionable > 0) {
            $alerts[] = self::alert(
                'orders',
                'warning',
                $actionable,
                __('admin.notifications.orders'),
                __('admin.notifications.orders_hint'),
                '/admin/orders?status=actionable'
            );
        }

        if ($unpaid > 0) {
            $alerts[] = self::alert(
                'unpaid',
                'warning',
                $unpaid,
                __('admin.notifications.unpaid'),
                __('admin.notifications.unpaid_hint'),
                '/admin/orders?payment_status=unpaid'
            );
        }

        if ($low > 0) {
            $alerts[] = self::alert(
                'low_stock',
                'info',
                $low,
                __('admin.notifications.low_stock'),
                __('admin.notifications.low_stock_hint'),
                '/admin/inventory?level=low'
            );
        }

        if ($drafts > 0) {
            $alerts[] = self::alert(
                'drafts',
                'info',
                $drafts,
                __('admin.notifications.drafts'),
                __('admin.notifications.drafts_hint'),
                '/admin/products?status=draft'
            );
        }

        return self::$cache = $alerts;
    }

    /** Nombre total d'alertes (pastille du menu). */
    public static function count(): int
    {
        $total = 0;

        foreach (self::alerts() as $alert) {
            $total += (int) $alert['count'];
        }

        return $total;
    }

    /**
     * Alerte unique dont le nombre de points dépasse celui de la seconde :
     * c'est le chiffre qui mérite la pastille rouge.
     */
    public static function worstLevel(): string
    {
        foreach (self::alerts() as $alert) {
            if ($alert['level'] === 'danger') {
                return 'danger';
            }
        }

        foreach (self::alerts() as $alert) {
            if ($alert['level'] === 'warning') {
                return 'warning';
            }
        }

        return 'info';
    }

    /**
     * Détail d'une alerte : les lignes concernées (ruptures, commandes à
     * traiter...) pour que la page puisse agir sans changer d'écran.
     *
     * Les variantes viennent du stock (tableaux SQL), les commandes et
     * produits de modèles : la vue distingue les deux formes.
     *
     * @return array<int, mixed>
     */
    public static function items(string $type, int $limit = 8): array
    {
        return match ($type) {
            'out_of_stock', 'low_stock' => StockService::alertList(
                $type === 'out_of_stock' ? 'out' : 'low',
                null,
                $limit
            ),
            'orders'     => Order::adminList(['status' => 'actionable'], $limit)['orders'],
            'unpaid'     => Order::adminList(['payment_status' => Order::PAYMENT_UNPAID], $limit)['orders'],
            'drafts'     => Product::adminList(['status' => Product::STATUS_DRAFT], $limit)['products'],
            default      => [],
        };
    }

    /** @param array<string, mixed> $alert */
    private static function alert(string $type, string $level, int $count, string $label, string $hint, string $url): array
    {
        return [
            'type'  => $type,
            'level' => $level,
            'count' => $count,
            'label' => $label,
            'hint'  => $hint,
            'url'   => $url,
        ];
    }
}
