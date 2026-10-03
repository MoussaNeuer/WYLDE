<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Fiche client.
 *
 * Une ligne « invité » porte user_id = NULL (commande sans compte) ;
 * un client inscrit porte user_id unique. La fiche garde l'historique
 * même après la suppression éventuelle du compte (§4).
 */
class Customer extends BaseModel
{
    protected string $table = 'customers';

    protected array $intColumns = ['id', 'user_id'];

    public function findByUserId(?int $userId): ?self
    {
        if ($userId === null) {
            return null;
        }

        $model = self::query(
            'SELECT * FROM `customers` WHERE `user_id` = :id LIMIT 1',
            ['id' => $userId]
        );

        return $model->exists() ? $model : null;
    }

    public function fullName(): string
    {
        return trim(
            ($this->attributes['first_name'] ?? '') . ' ' . ($this->attributes['last_name'] ?? '')
        );
    }

    /** Compte associé, absent pour une commande invitée. */
    public function user(): ?User
    {
        $id = $this->attributes['user_id'] ?? null;

        return $id === null ? null : $this->belongsTo(User::class, 'user_id', (int) $id);
    }
}