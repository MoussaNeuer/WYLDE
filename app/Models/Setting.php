<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Paramètre de boutique (table settings, clé / valeur).
 *
 * Mis en cache par lots pour éviter une requête par lecture.
 *
 * @property string $key
 * @property string $value
 */
final class Setting extends BaseModel
{
    protected string $table = 'settings';

    protected string $primaryKey = 'key';

    protected array $intColumns = [];

    protected array $moneyColumns = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Database::selectValue(
            'SELECT `value` FROM `settings` WHERE `key` = :key LIMIT 1',
            ['key' => $key]
        );

        if ($value === null) {
            return $default;
        }

        // Un champ vide stored signifies "not configured" : on rend le défaut.
        return trim((string) $value) === '' ? $default : $value;
    }

    public static function put(string $key, mixed $value): void
    {
        // :value et :update doivent rester distincts : PDO n'accepte pas
        // un placeholder nommé réutilisé quand l'émulation est désactivée.
        Database::run(
            'INSERT INTO `settings` (`key`, `value`) VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE `value` = :update',
            ['key' => $key, 'value' => (string) $value, 'update' => (string) $value]
        );
    }

    /** Écrit plusieurs paramètres en une transaction. */
    public static function putMany(array $pairs): void
    {
        Database::transaction(static function () use ($pairs): void {
            foreach ($pairs as $key => $value) {
                self::put((string) $key, $value);
            }
        });
    }

    public static function forget(string $key): void
    {
        Database::delete('settings', ['key' => $key]);
    }

    /**
     * Tous les paramètres en une fois, sous forme clé => valeur.
     *
     * Volontairement pas surchargé : BaseModel::all() renvoie des
     * modèles, alors que settings n'a pas de clé numérique.
     *
     * @return array<string, string>
     */
    public static function pairs(): array
    {
        $rows  = Database::select('SELECT `key`, `value` FROM `settings`');
        $pairs = [];

        foreach ($rows as $row) {
            $pairs[(string) $row['key']] = (string) $row['value'];
        }

        return $pairs;
    }
}
