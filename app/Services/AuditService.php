<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;

/**
 * Journalisation des actions sensibles du back-office (§13.2).
 *
 * Point d'entrée unique : il complète automatiquement l'auteur et
 * l'empreinte de l'IP, ce qui évite qu'un appelant oublie de les passer
 * et laisse une trace inexploitable.
 */
final class AuditService
{
    /** @var array<int, string> Actions métier, alignées sur les clés admin.audit. */
    public const ACTION_LOGIN            = 'login';
    public const ACTION_LOGIN_FAILED     = 'login_failed';
    public const ACTION_LOGOUT           = 'logout';
    public const ACTION_PASSWORD_CHANGE  = 'password_change';
    public const ACTION_PROFILE_UPDATE   = 'profile_update';
    public const ACTION_SESSIONS_REVOKE  = 'sessions_revoke';
    public const ACTION_PRODUCT_CREATE   = 'product_create';
    public const ACTION_PRODUCT_UPDATE   = 'product_update';
    public const ACTION_PRODUCT_DELETE   = 'product_delete';
    public const ACTION_PRODUCT_BULK     = 'product_bulk';
    public const ACTION_CATEGORY_CREATE  = 'category_create';
    public const ACTION_CATEGORY_UPDATE  = 'category_update';
    public const ACTION_CATEGORY_DELETE  = 'category_delete';
    public const ACTION_ORDER_STATUS     = 'order_status';
    public const ACTION_ORDER_PAYMENT    = 'order_payment';
    public const ACTION_ORDER_NOTES      = 'order_notes';
    public const ACTION_INVENTORY_ADJUST = 'inventory_adjust';
    public const ACTION_MEDIA_UPLOAD     = 'media_upload';
    public const ACTION_MEDIA_DELETE     = 'media_delete';
    public const ACTION_MEDIA_REORDER    = 'media_reorder';
    public const ACTION_MEDIA_PRIMARY    = 'media_primary';
    public const ACTION_SETTINGS_UPDATE  = 'settings_update';
    public const ACTION_ZONE_CREATE      = 'zone_create';
    public const ACTION_ZONE_UPDATE      = 'zone_update';
    public const ACTION_ZONE_DELETE      = 'zone_delete';
    public const ACTION_SIZE_CREATE      = 'size_create';
    public const ACTION_SIZE_UPDATE      = 'size_update';
    public const ACTION_SIZE_DELETE      = 'size_delete';
    public const ACTION_MESSAGE_DELETE   = 'message_delete';
    public const ACTION_MESSAGE_READ     = 'message_read';

    /**
     * Enregistre une action.
     *
     * @param array<string, mixed> $metadata
     */
    public static function log(
        string $action,
        ?string $entity = null,
        ?int $entityId = null,
        array $metadata = []
    ): void {
        AuditLog::record(
            $action,
            $entity,
            $entityId,
            $metadata,
            self::currentIpHash()
        );
    }

    /**
     * Libellé lisible d'une action, traduit dans la locale courante.
     * Une action inconnue retombe sur son nom brut plutôt que de
     * disparaître du journal.
     */
    public static function label(string $action): string
    {
        $translated = __('admin.audit.' . $action);

        return $translated === 'admin.audit.' . $action ? $action : $translated;
    }

    /** Couleur de badge pour l'affichage du journal. */
    public static function level(string $action): string
    {
        return match (true) {
            str_contains($action, 'delete')          => 'is-cancelled',
            str_contains($action, 'failed')          => 'is-cancelled',
            str_contains($action, 'order')           => 'is-preparing',
            str_contains($action, 'password'),
            str_contains($action, 'login'),
            str_contains($action, 'logout')          => 'is-shipped',
            default                                  => 'is-confirmed',
        };
    }

    /**
     * Empreinte HMAC de l'IP courante.
     * Aucune IP n'est conservée en clair, y compris dans les logs techniques.
     */
    private static function currentIpHash(): ?string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        if ($ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key', ''));
    }
}
