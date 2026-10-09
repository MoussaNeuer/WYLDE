<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Order;
use App\Models\Product;

/**
 * Agrégats du back-office : tableau de bord et analytics.
 *
 * Le chiffre d'affaires ne compte que les commandes payées et non
 * annulées : encaoisser et « encaissé » sont deux choses différentes, et
 * mélanger les deux donne l'impression d'une boutique qui vend plus
 * qu'elle n'encaisse.
 */
final class AnalyticsService
{
    /**
     * Périodes affichables du back-office : une seule source pour le menu
     * de la page analytics, la borne de durées et les libellés i18n.
     */
    public const PERIODS = [7, 30, 90];

    /** Libellé i18n (suffixe de admin.period.*) associé à chaque période. */
    public const PERIOD_LABELS = [
        7  => 'week',
        30 => 'month',
        90 => 'quarter',
    ];

    /**
     * Borne une durée en jours dans les limites du back-office.
     */
    public static function normalizeDays(int $days, int $default = 30, int $max = 180): int
    {
        $value = $days < 1 ? $default : $days;

        return max(7, min($max, $value));
    }

    /** @return array<int, string> */
    public static function periods(): array
    {
        return self::PERIOD_LABELS;
    }

    /** Libellé de la période couramment sélectionnée. */
    public static function periodLabel(int $days): string
    {
        return self::PERIOD_LABELS[$days] ?? 'custom';
    }

    /**
     * Compteurs de base du tableau de bord.
     *
     * @return array<string, int>
     */
    public static function summary(): array
    {
        $today = date('Y-m-d 00:00:00');
        $week  = date('Y-m-d 00:00:00', strtotime('-6 days'));
        $month = date('Y-m-d 00:00:00', strtotime('-29 days'));

        return [
            'revenue_today'   => Order::revenueSince($today),
            'revenue_week'    => Order::revenueSince($week),
            'revenue_month'   => Order::revenueSince($month),
            'orders_today'    => Order::countSince($today),
            'orders_week'     => Order::countSince($week),
            'orders_month'    => Order::countSince($month),
            'pending_orders'  => Order::count('`status` = :status', ['status' => Order::STATUS_PENDING]),
            'actionable'      => Order::countActionable(),
            'customers_total' => (int) Database::selectValue('SELECT COUNT(*) FROM `customers`'),
            'customers_week'  => (int) Database::selectValue(
                'SELECT COUNT(*) FROM `customers` WHERE `created_at` >= :date',
                ['date' => $week]
            ),
            'products_live'   => (int) Database::selectValue(
                'SELECT COUNT(*) FROM `products` WHERE `status` = :status',
                ['status' => Product::STATUS_PUBLISHED]
            ),
            'products_total'  => (int) Database::selectValue('SELECT COUNT(*) FROM `products`'),
            'products_draft'  => (int) Database::selectValue(
                'SELECT COUNT(*) FROM `products` WHERE `status` = :status',
                ['status' => Product::STATUS_DRAFT]
            ),
            'low_stock'       => StockService::countLow(),
            'out_of_stock'    => StockService::countOut(),
        ];
    }

    /**
     * Repartition des commandes par statut, pour le tableau de bord.
     *
     * @return array<string, int>
     */
    public static function ordersByStatus(): array
    {
        $rows = Database::select('SELECT `status`, COUNT(*) AS total FROM `orders` GROUP BY `status`');

        $counts = array_fill_keys(Order::statuses(), 0);

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Série du chiffre d'affaires encaissé, jour par jour.
     *
     * @return array<int, array{date: string, label: string, revenue: int, orders: int}>
     */
    public static function revenueSeries(int $days = 14): array
    {
        $days = self::normalizeDays($days, 14);
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

        $rows = Database::select(
            'SELECT DATE(`created_at`) AS day,
                    COUNT(*) AS orders,
                    COALESCE(SUM(CASE WHEN `payment_status` = :paid AND `status` <> :cancelled
                                      THEN `total` ELSE 0 END), 0) AS revenue
             FROM `orders`
             WHERE `created_at` >= :from
             GROUP BY DATE(`created_at`)
             ORDER BY day ASC',
            ['paid' => Order::PAYMENT_PAID, 'cancelled' => Order::STATUS_CANCELLED, 'from' => $from]
        );

        $indexed = [];

        foreach ($rows as $row) {
            $indexed[(string) $row['day']] = $row;
        }

        $series = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $day   = date('Y-m-d', strtotime('-' . $offset . ' days'));
            $row   = $indexed[$day] ?? ['orders' => 0, 'revenue' => 0];

            $series[] = [
                'date'    => $day,
                'label'   => date('d/m', strtotime($day)),
                'revenue' => money_int($row['revenue'] ?? 0),
                'orders'  => (int) ($row['orders'] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Palettes les plus vendues, par articles écoulés.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function topProducts(int $limit = 5): array
    {
        return Database::select(
            'SELECT p.id, p.name, p.name_en, p.slug, p.status, p.price,
                    COALESCE(SUM(oi.quantity), 0) AS sold,
                    COALESCE(SUM(oi.line_total), 0) AS revenue
             FROM `order_items` oi
             INNER JOIN `orders` o ON o.id = oi.order_id
             INNER JOIN `products` p ON p.id = oi.product_id
             WHERE o.`status` <> :cancelled
             GROUP BY p.id, p.name, p.name_en, p.slug, p.status, p.price
             ORDER BY sold DESC, revenue DESC
             LIMIT ' . max(1, min(50, $limit)),
            ['cancelled' => Order::STATUS_CANCELLED]
        );
    }

    /**
     * Clients les plus actifs.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function topCustomers(int $limit = 5): array
    {
        return Database::select(
            'SELECT o.customer_email, o.customer_phone,
                    COUNT(DISTINCT o.id) AS orders,
                    COALESCE(SUM(CASE WHEN o.payment_status = :paid AND o.status <> :cancelled
                                      THEN o.total ELSE 0 END), 0) AS spent,
                    MAX(o.created_at) AS last_order_at
             FROM `orders` o
             GROUP BY o.customer_email, o.customer_phone
             ORDER BY spent DESC, orders DESC
             LIMIT ' . max(1, min(50, $limit)),
            ['paid' => Order::PAYMENT_PAID, 'cancelled' => Order::STATUS_CANCELLED]
        );
    }

    /**
     * Répartition des ventes par jour de la semaine.
     *
     * @return array<int, array{label: string, orders: int, revenue: int}>
     */
    public static function weekdayBreakdown(int $days = 90): array
    {
        $from = date('Y-m-d 00:00:00', strtotime('-' . self::normalizeDays($days, 90, 365) . ' days'));

        $rows = Database::select(
            'SELECT DAYOFWEEK(`created_at`) AS dow,
                    COUNT(*) AS orders,
                    COALESCE(SUM(CASE WHEN `payment_status` = :paid AND `status` <> :cancelled
                                      THEN `total` ELSE 0 END), 0) AS revenue
             FROM `orders`
             WHERE `created_at` >= :from
             GROUP BY DAYOFWEEK(`created_at`)
             ORDER BY dow ASC',
            ['paid' => Order::PAYMENT_PAID, 'cancelled' => Order::STATUS_CANCELLED, 'from' => $from]
        );

        $indexed = [];

        foreach ($rows as $row) {
            $indexed[(int) $row['dow']] = $row;
        }

        $names = [1 => 'dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];

        $series = [];

        for ($dow = 2; $dow <= 7; $dow++) {
            $row = $indexed[$dow] ?? ['orders' => 0, 'revenue' => 0];

            $series[] = [
                'label'   => $names[$dow],
                'orders'  => (int) ($row['orders'] ?? 0),
                'revenue' => money_int($row['revenue'] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Comparaison de deux periodes pour la page analytics.
     *
     * @return array{current: array<string, int>, previous: array<string, int>, delta: array<string, float>}
     */
    public static function comparison(int $days = 30): array
    {
        $days = self::normalizeDays($days, 30);

        $currentFrom  = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
        $previousFrom = date('Y-m-d 00:00:00', strtotime('-' . (2 * $days - 1) . ' days'));

        $current  = self::window($currentFrom, $days);
        $previous = self::window($previousFrom, $days);

        $delta = [];

        foreach ($current as $key => $value) {
            $before = (int) ($previous[$key] ?? 0);

            $delta[$key] = $before === 0
                ? ($value > 0 ? 100.0 : 0.0)
                : round((($value - $before) / $before) * 100, 1);
        }

        return ['current' => $current, 'previous' => $previous, 'delta' => $delta];
    }

    /**
     * @return array<string, int>
     */
    private static function window(string $from, int $days): array
    {
        $until = date('Y-m-d 23:59:59', strtotime($from . ' +' . ($days - 1) . ' days'));

        $row = Database::selectOne(
            'SELECT COUNT(*) AS orders,
                    COALESCE(SUM(`total`), 0) AS turnover,
                    COALESCE(SUM(CASE WHEN `payment_status` = :paid AND `status` <> :not_cancelled
                                      THEN `total` ELSE 0 END), 0) AS revenue,
                    COALESCE(SUM(CASE WHEN `status` = :cancelled THEN 1 ELSE 0 END), 0) AS cancelled,
                    COALESCE(AVG(`total`), 0) AS average
             FROM `orders`
             WHERE `created_at` BETWEEN :from AND :until',
            [
                'paid'          => Order::PAYMENT_PAID,
                'not_cancelled' => Order::STATUS_CANCELLED,
                'cancelled'     => Order::STATUS_CANCELLED,
                'from'          => $from,
                'until'         => $until,
            ]
        ) ?? [];

        return [
            'orders'    => (int) ($row['orders'] ?? 0),
            'turnover'  => money_int($row['turnover'] ?? 0),
            'revenue'   => money_int($row['revenue'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
            'average'   => money_int($row['average'] ?? 0),
        ];
    }
}
