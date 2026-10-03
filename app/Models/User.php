<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;

/**
 * Compte utilisateur : client, gestionnaire ou administrateur.
 *
 * Les rôles sont preparedness pour la V2 (cf. §4) : un « manager » peut
 * être ajouté plus tard sans modifier le schéma.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $email
 * @property string      $password_hash
 * @property string      $role
 * @property string      $status
 */
final class User extends BaseModel
{
    protected string $table = 'users';

    /** @var array<int, string> */
    protected array $fillable = [
        'name', 'email', 'password_hash', 'role', 'status', 'phone',
        'last_login_at', 'last_login_ip',
    ];

    protected array $intColumns = ['id'];

    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_MANAGER  = 'manager';
    public const ROLE_ADMIN    = 'admin';

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_SUSPENDED = 'suspended';

    /** @return array<int, string> */
    public static function roles(): array
    {
        return [self::ROLE_CUSTOMER, self::ROLE_MANAGER, self::ROLE_ADMIN];
    }

    public static function findByEmail(string $email): ?self
    {
        $model = self::query(
            'SELECT * FROM `users` WHERE `email` = :email LIMIT 1',
            ['email' => mb_strtolower(trim($email), 'UTF-8')]
        );

        return $model->exists() ? $model : null;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** Administrateur ou gestionnaire : accès au back-office. */
    public function isStaff(): bool
    {
        return $this->role === self::ROLE_ADMIN || $this->role === self::ROLE_MANAGER;
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    public function setPassword(string $plain): void
    {
        $this->setAttribute('password_hash', password_hash($plain, PASSWORD_DEFAULT));
    }

    public function verifyPassword(string $plain): bool
    {
        $hash = (string) $this->getAttribute('password_hash', '');

        return $hash !== '' && password_verify($plain, $hash);
    }

    public function needsRehash(): bool
    {
        $hash = (string) $this->getAttribute('password_hash', '');

        return $hash !== '' && password_needs_rehash($hash, PASSWORD_DEFAULT);
    }

    public function touchLogin(string $ipHash): void
    {
        $this->setAttribute('last_login_at', date('Y-m-d H:i:s'));
        $this->setAttribute('last_login_ip', $ipHash);
        $this->save();
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        $letters = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
        }

        return $letters === '' ? '?' : $letters;
    }

    /** Profil client associé, s'il existe. */
    public function customer(): ?Customer
    {
        $customer = Customer::query(
            'SELECT * FROM `customers` WHERE `user_id` = :id LIMIT 1',
            ['id' => $this->id()]
        );

        return $customer->exists() ? $customer : null;
    }

    /** Nombre de commandes de cet utilisateur. */
    public function ordersCount(): int
    {
        return (int) Database::selectValue(
            'SELECT COUNT(*) FROM `orders` WHERE `user_id` = :id',
            ['id' => $this->id()]
        );
    }

    public function totalSpent(): int
    {
        return money_int(Database::selectValue(
            "SELECT COALESCE(SUM(`total`), 0) FROM `orders`
             WHERE `user_id` = :id AND `status` <> 'cancelled'",
            ['id' => $this->id()]
        ));
    }

    public static function countByRole(string $role): int
    {
        return self::count('`role` = :role', ['role' => $role]);
    }

    /** Connexion : vérifie les identifiants et renvoie un modèle. */
    public static function authenticate(string $email, string $password): ?self
    {
        return Auth::attempt($email, $password);
    }
}
