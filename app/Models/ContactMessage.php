<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Message reçu depuis la page contact.
 *
 * Aucun envoi d'email automatique en V1 (§22) : le message reste dans
 * cette table et est consulté depuis le back-office. L'IP est stockée
 * sous forme de HMAC, jamais en clair.
 */
class ContactMessage extends BaseModel
{
    protected string $table = 'contact_messages';

    protected array $intColumns = ['id'];

    public const STATUS_NEW  = 'new';
    public const STATUS_READ = 'read';

    /** Nombre de messages encore non lus (badge du menu admin). */
    public static function countUnread(): int
    {
        return (int) static::count("`status` = 'new'");
    }

    public function isRead(): bool
    {
        return ($this->attributes['status'] ?? self::STATUS_NEW) === self::STATUS_READ;
    }

    /** Marque le message comme lu (sans déranger si c'est déjà le cas). */
    public function markRead(): void
    {
        if ($this->isRead()) {
            return;
        }

        $this->attributes['status'] = self::STATUS_READ;
        $this->save();
    }
}