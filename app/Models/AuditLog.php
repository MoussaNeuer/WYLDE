<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Journal des actions sensibles du back-office (§13.2).
 *
 * L'IP n'est jamais stockée en clair : uniquement son HMAC SHA-256
 * (Request::ipHash()), ce qui permet de corréler deux actions sans
 * conserver d'adresse en base.
 */
class AuditLog extends BaseModel
{
    protected string $table = 'audit_logs';

    protected array $intColumns = ['id', 'user_id'];

    protected array $dates = ['created_at'];

    /**
     * Écrit une entrée de journal. Ne lève jamais : un audit ne doit pas
     * faire échouer l'action métier qu'il accompagne.
     *
     * @param array<string, mixed> $metadata
     */
    public static function record(
        string $action,
        ?string $entity = null,
        ?int $entityId = null,
        array $metadata = [],
        ?string $ipHash = null
    ): void {
        try {
            Database::insert('audit_logs', [
                'user_id'   => auth()?->id(),
                'action'    => mb_substr($action, 0, 80),
                'entity'    => $entity === null ? null : mb_substr($entity, 0, 60),
                'entity_id' => $entityId === null ? null : (string) $entityId,
                'ip_hash'   => $ipHash,
                'metadata'  => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable) {
            // Le journal est une trace de sécurité, pas un point de rupture.
        }
    }

    /**
     * Journal paginé, filtre par action ou entité.
     *
     * @param  array<string, mixed> $filters
     * @return array{logs: array<int, static>, total: int, pages: int}
     */
    public static function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $page   = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $clauses  = [];
        $bindings = [];

        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '') {
            $clauses[]          = 'a.`action` = :action';
            $bindings['action'] = $action;
        }

        $entity = trim((string) ($filters['entity'] ?? ''));
        if ($entity !== '') {
            $clauses[]          = 'a.`entity` = :entity';
            $bindings['entity'] = $entity;
        }

        $userId = (int) ($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $clauses[]        = 'a.`user_id` = :user_id';
            $bindings['user_id'] = $userId;
        }

        $where = $clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses);

        $total = (int) Database::selectValue('SELECT COUNT(*) FROM `audit_logs` a ' . $where, $bindings);

        $rows = Database::select(
            'SELECT a.*, u.name AS user_name
             FROM `audit_logs` a
             LEFT JOIN `users` u ON u.id = a.user_id
             ' . $where . '
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $bindings
        );

        return [
            'logs'  => array_map(static fn (array $row): static => (new static())->hydrate($row), $rows),
            'total' => $total,
            'pages' => (int) max(1, (int) ceil($total / $perPage)),
        ];
    }

    /** @return array<int, string> */
    public static function actions(): array
    {
        $rows = Database::select('SELECT DISTINCT `action` FROM `audit_logs` ORDER BY `action` ASC');

        return array_map(
            static fn (array $row): string => (string) $row['action'],
            $rows
        );
    }

    /** Auteur de l'action, absent pour une action système. */
    public function user(): ?User
    {
        $id = $this->attributes['user_id'] ?? null;

        return $id === null ? null : $this->belongsTo(User::class, 'user_id', (int) $id);
    }

    /** @return array<int, string> */
    public static function recentFor(?int $userId, int $limit = 15): array
    {
        $rows = $userId === null
            ? Database::select('SELECT `action` FROM `audit_logs` ORDER BY `id` DESC LIMIT ' . max(1, min(50, $limit)))
            : Database::select(
                'SELECT `action` FROM `audit_logs` WHERE `user_id` = :id ORDER BY `id` DESC LIMIT ' . max(1, min(50, $limit)),
                ['id' => $userId]
            );

        return array_map(
            static fn (array $row): string => (string) $row['action'],
            $rows
        );
    }
}
