<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Commande client.
 *
 * Les coordonnées et les prix sont figés au moment de la commande
 * (colonnes shipping_*, customer_*, order_items.unit_price) : le back-office
 * affiche donc toujours l'achat réel, même si le produit ou la fiche client
 * ont évolué depuis (§7).
 */
class Order extends BaseModel
{
    protected string $table = 'orders';

    /** @var array<int, string> */
    protected array $fillable = [
        'reference', 'customer_id', 'user_id', 'status',
        'payment_method', 'payment_status', 'subtotal', 'shipping_cost',
        'discount', 'total', 'item_count', 'customer_email', 'customer_phone',
        'shipping_first_name', 'shipping_last_name', 'shipping_address',
        'shipping_city', 'shipping_country_code', 'shipping_zone_id',
        'shipping_method', 'notes', 'admin_notes', 'tracking_number',
        'cancelled_reason', 'paid_at', 'shipped_at', 'delivered_at', 'cancelled_at',
    ];

    protected array $intColumns = [
        'id', 'customer_id', 'user_id', 'item_count', 'shipping_zone_id',
    ];

    /** DECIMAL(12,0) : FCFA, entiers. */
    protected array $moneyColumns = [
        'subtotal', 'shipping_cost', 'discount', 'total',
    ];

    protected array $dates = [
        'created_at', 'updated_at', 'paid_at',
        'shipped_at', 'delivered_at', 'cancelled_at',
    ];

    // ── Statuts alignés sur l'énumération SQL ──────────────────

    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_SHIPPED   = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_UNPAID   = 'unpaid';
    public const PAYMENT_PAID     = 'paid';
    public const PAYMENT_REFUNDED = 'refunded';

    public const METHOD_COD  = 'cod';
    public const METHOD_WAVE = 'wave';

    /** @return array<int, string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_PREPARING,
            self::STATUS_SHIPPED,
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
        ];
    }

    /** @return array<int, string> */
    public static function paymentStatuses(): array
    {
        return [self::PAYMENT_UNPAID, self::PAYMENT_PAID, self::PAYMENT_REFUNDED];
    }

    /**
     * Transitions autorisées depuis le back-office.
     *
     * Une commande livrée ou annulée est terminale : elle ne repasse plus
     * en préparation. Une annulation depuis un statut avéré repose le stock
     * (voir OrderService).
     *
     * @return array<int, string>
     */
    public function allowedTransitions(): array
    {
        $current = $this->status;

        $transitions = [
            self::STATUS_PENDING   => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
            self::STATUS_CONFIRMED => [self::STATUS_PREPARING, self::STATUS_CANCELLED],
            self::STATUS_PREPARING => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
            self::STATUS_SHIPPED   => [self::STATUS_DELIVERED],
            self::STATUS_DELIVERED => [],
            self::STATUS_CANCELLED => [],
        ];

        return $transitions[$current] ?? [];
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public function isCancellable(): bool
    {
        return in_array(self::STATUS_CANCELLED, $this->allowedTransitions(), true);
    }

    public function isPayable(): bool
    {
        return $this->status !== self::STATUS_CANCELLED
            && $this->payment_status === self::PAYMENT_UNPAID;
    }

    // ── Lectures ─────────────────────────────────────────────

    public static function findByReference(string $reference): ?static
    {
        $model = static::query(
            'SELECT * FROM `orders` WHERE `reference` = :reference LIMIT 1',
            ['reference' => $reference]
        );

        return $model->exists() ? $model : null;
    }

    /**
     * Liste paginée pour le back-office, filtres et tri inclus.
     *
     * @param  array<string, mixed> $params  q, status, payment_status, method, from, to, sort
     * @return array{orders: array<int, static>, total: int, page: int, per_page: int, pages: int}
     */
    public static function adminList(array $params = [], int $perPage = 20): array
    {
        $page   = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(100, $perPage));

        [$clauses, $bindings] = self::buildAdminFilters($params);

        $where = $clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses);

        $total = (int) Database::selectValue('SELECT COUNT(*) FROM `orders` ' . $where, $bindings);

        $sort = match ((string) ($params['sort'] ?? 'recent')) {
            'oldest'     => 'created_at ASC, id ASC',
            'total_desc' => 'total DESC, id DESC',
            'total_asc'  => 'total ASC, id ASC',
            'status'     => 'status ASC, created_at DESC',
            default      => 'created_at DESC, id DESC',
        };

        $rows = Database::select(
            'SELECT * FROM `orders` ' . $where . '
             ORDER BY ' . $sort . '
             LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $bindings
        );

        return [
            'orders'  => array_map(static fn (array $row): static => (new static())->hydrate($row), $rows),
            'total'   => $total,
            'page'    => $page,
            'per_page' => $perPage,
            'pages'   => (int) max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Commandes d'un utilisateur (espace client).
     *
     * Le scoping passe par user_id ET par l'email de la commande : un
     * client ayant commandé en invité avant sa connexion retrouve son
     * historique sans que l'on doive fusionner les fiches.
     *
     * @return array{orders: array<int, static>, total: int}
     */
    public static function listForUser(int $userId, string $email, int $limit = 50, int $offset = 0): array
    {
        $params = ['user' => $userId, 'email' => $email];
        $limit  = max(1, min(200, $limit));
        $offset = max(0, $offset);

        $total = (int) Database::selectValue(
            'SELECT COUNT(*) FROM `orders`
             WHERE `user_id` = :user OR `customer_email` = :email',
            $params
        );

        $rows = Database::select(
            'SELECT * FROM `orders`
             WHERE `user_id` = :user OR `customer_email` = :email
             ORDER BY `created_at` DESC
             LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );

        return [
            'orders' => array_map(static fn (array $row): static => (new static())->hydrate($row), $rows),
            'total'  => $total,
        ];
    }

    /** Détail d'une commande appartenant à l'utilisateur (sécurisé). */
    public static function findForUser(int $id, int $userId, string $email): ?static
    {
        $model = static::query(
            'SELECT * FROM `orders`
             WHERE `id` = :id AND (`user_id` = :user OR `customer_email` = :email)
             LIMIT 1',
            ['id' => $id, 'user' => $userId, 'email' => $email]
        );

        return $model->exists() ? $model : null;
    }

    /**
     * Commandes rattachées à une fiche client (back-office).
     *
     * @return array<int, static>
     */
    public static function allWhereCustomer(int $customerId, int $offset = 0, int $limit = 100): array
    {
        $rows = Database::select(
            'SELECT * FROM `orders`
             WHERE `customer_id` = :id
             ORDER BY `created_at` DESC, `id` DESC
             LIMIT ' . max(1, min(500, $limit)) . ' OFFSET ' . max(0, $offset),
            ['id' => $customerId]
        );

        return array_map(static fn (array $row): static => (new static())->hydrate($row), $rows);
    }

    /**
     * Commandes récentes pour le tableau de bord.
     *
     * @return array<int, static>
     */
    public static function recent(int $limit = 8): array
    {
        $rows = Database::select(
            'SELECT * FROM `orders` ORDER BY `created_at` DESC, `id` DESC LIMIT ' . max(1, min(50, $limit))
        );

        return array_map(static fn (array $row): static => (new static())->hydrate($row), $rows);
    }

    /**
     * Commandes en attente de traitement, pour le badge de la sidebar.
     */
    public static function countActionable(): int
    {
        return self::count(
            '`status` IN (:pending, :confirmed)',
            ['pending' => self::STATUS_PENDING, 'confirmed' => self::STATUS_CONFIRMED]
        );
    }

    /**
     * Chiffre d'affaires encaissé : paiements marqués payés, annulations
     * et remboursements exclus.
     */
    public static function revenueSince(string $date): int
    {
        return (int) Database::selectValue(
            'SELECT COALESCE(SUM(`total`), 0) FROM `orders`
             WHERE `payment_status` = :paid AND `status` <> :cancelled AND `created_at` >= :date',
            ['paid' => self::PAYMENT_PAID, 'cancelled' => self::STATUS_CANCELLED, 'date' => $date]
        );
    }

    public static function countSince(string $date, ?string $status = null): int
    {
        if ($status === null) {
            return self::count('`created_at` >= :date', ['date' => $date]);
        }

        return self::count(
            '`created_at` >= :date AND `status` = :status',
            ['date' => $date, 'status' => $status]
        );
    }

    /**
     * @param  array<string, mixed> $params
     * @return array{0: array<int, string>, 1: array<string, mixed>}
     */
    private static function buildAdminFilters(array $params): array
    {
        $clauses  = [];
        $bindings = [];

        $status = (string) ($params['status'] ?? '');
        if (in_array($status, self::statuses(), true)) {
            $clauses[]        = '`status` = :status';
            $bindings['status'] = $status;
        }

        $payment = (string) ($params['payment_status'] ?? '');
        if (in_array($payment, self::paymentStatuses(), true)) {
            $clauses[]            = '`payment_status` = :payment_status';
            $bindings['payment_status'] = $payment;
        }

        $method = (string) ($params['payment_method'] ?? '');
        if (in_array($method, [self::METHOD_COD, self::METHOD_WAVE], true)) {
            $clauses[]            = '`payment_method` = :payment_method';
            $bindings['payment_method'] = $method;
        }

        $query = trim((string) ($params['q'] ?? ''));
        if ($query !== '') {
            // Placeholders distincts : avec EMULATE_PREPARES=false, PDO
            // refuse qu'un même nom apparaisse deux fois dans un statement.
            $like                     = '%' . str_replace(['%', '_'], ['\%', '\_'], $query) . '%';
            $clauses[]                = '(`reference` LIKE :search
                                     OR `customer_email` LIKE :search_email
                                     OR `customer_phone` LIKE :search_phone
                                     OR CONCAT(`shipping_first_name`, \' \', `shipping_last_name`) LIKE :search_name
                                     OR `shipping_city` LIKE :search_city)';
            $bindings['search']       = $like;
            $bindings['search_email'] = $like;
            $bindings['search_phone'] = $like;
            $bindings['search_name']  = $like;
            $bindings['search_city']  = $like;
        }

        $from = (string) ($params['from'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) === 1) {
            $clauses[]        = '`created_at` >= :from';
            $bindings['from'] = $from . ' 00:00:00';
        }

        $to = (string) ($params['to'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1) {
            $clauses[]      = '`created_at` <= :to';
            $bindings['to'] = $to . ' 23:59:59';
        }

        return [$clauses, $bindings];
    }

    // ── Relations ────────────────────────────────────────────

    /** @return array<int, OrderItem> */
    public function items(): array
    {
        $rows = Database::select(
            'SELECT * FROM `order_items` WHERE `order_id` = :id ORDER BY `id` ASC',
            ['id' => $this->id()]
        );

        return array_map(
            static fn (array $row): OrderItem => (new OrderItem())->hydrate($row),
            $rows
        );
    }

    /**
     * Historique des changements de statut.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(): array
    {
        return Database::select(
            'SELECT h.*, u.name AS changed_by_name
             FROM `order_status_history` h
             LEFT JOIN `users` u ON u.id = h.changed_by
             WHERE h.`order_id` = :id
             ORDER BY h.created_at DESC, h.id DESC',
            ['id' => $this->id()]
        );
    }

    public function user(): ?User
    {
        $id = $this->attributes['user_id'] ?? null;

        return $id === null ? null : $this->belongsTo(User::class, 'user_id', $id);
    }

    public function customer(): ?Customer
    {
        $id = $this->attributes['customer_id'] ?? null;

        return $id === null ? null : $this->belongsTo(Customer::class, 'customer_id', $id);
    }

    public function zone(): ?ShippingZone
    {
        $id = $this->attributes['shipping_zone_id'] ?? null;

        return $id === null ? null : $this->belongsTo(ShippingZone::class, 'shipping_zone_id', $id);
    }

    // ── Présentation ─────────────────────────────────────────

    public function customerName(): string
    {
        $first  = trim((string) ($this->attributes['shipping_first_name'] ?? ''));
        $last   = trim((string) ($this->attributes['shipping_last_name'] ?? ''));

        $name = trim($first . ' ' . $last);

        return $name !== '' ? $name : (string) ($this->attributes['customer_email'] ?? '');
    }

    public function shippingAddress(): string
    {
        return implode(', ', array_filter([
            (string) ($this->attributes['shipping_address'] ?? ''),
            (string) ($this->attributes['shipping_city'] ?? ''),
        ], static fn (string $part): bool => trim($part) !== ''));
    }

    public function countryName(): string
    {
        $code = strtoupper((string) ($this->attributes['shipping_country_code'] ?? ''));

        $names = ShippingZone::availableCountries();

        return $names[$code] ?? $code;
    }

    /** Classe CSS du badge de statut (couleurs de components.css). */
    public function statusBadge(): string
    {
        return match ((string) $this->status) {
            self::STATUS_PENDING   => 'is-pending',
            self::STATUS_CONFIRMED => 'is-confirmed',
            self::STATUS_PREPARING => 'is-preparing',
            self::STATUS_SHIPPED   => 'is-shipped',
            self::STATUS_DELIVERED => 'is-delivered',
            self::STATUS_CANCELLED => 'is-cancelled',
            default                => 'is-pending',
        };
    }

    public function paymentBadge(): string
    {
        return match ((string) $this->payment_status) {
            self::PAYMENT_PAID     => 'is-delivered',
            self::PAYMENT_REFUNDED => 'is-cancelled',
            default                => 'is-pending',
        };
    }

    public function isGuest(): bool
    {
        return ($this->attributes['user_id'] ?? null) === null;
    }
}
