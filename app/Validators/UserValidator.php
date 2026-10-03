<?php

declare(strict_types=1);

namespace App\Validators;

use App\Models\User;

/**
 * Validation des comptes : inscription, profil et sécurité.
 */
class UserValidator extends Validator
{
    public const MODE_PROFILE = 'profile';
    public const MODE_SECURITY = 'security';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [], private string $mode = self::MODE_PROFILE)
    {
        parent::__construct($data);
    }

    public function validate(): void
    {
        $mode = $this->mode;

        $this->maxLength('name', __('auth.name'), 120);

        if ($mode !== self::MODE_SECURITY) {
            $name = $this->string('name', __('auth.name'));

            $this->required('name', $name);

            $email = $this->string('email', __('auth.email'));

            $this->required('email', $email);
            $this->email('email', __('auth.email'));
            $this->maxLength('email', __('auth.email'), 190);

            if ($email !== '') {
                $this->emailUnique($email, (int) ($this->data['user_id'] ?? 0));
            }
        }

        $this->maxLength('phone', 'Téléphone', 30);

        $password = (string) $this->value('password');

        if ($password !== '') {
            $this->password($password);
            $this->confirmed('password', 'password_confirmation');
        }
    }

    /** @return array<string, string|int|null> */
    public function profileData(): array
    {
        return [
            'name'  => $this->string('name', 'name'),
            'phone' => $this->nullable('phone'),
        ];
    }

    /** Validation du mot de passe seul (changement de mot de passe). */
    public function validatePasswordOnly(): void
    {
        $password = (string) $this->value('password');

        $this->password($password);
        $this->confirmed('password', 'password_confirmation');
    }

    private function password(string $password): void
    {
        $min = (int) config('security.password.min_length', 10);

        if (mb_strlen($password, 'UTF-8') < $min) {
            $this->addError(
                'password',
                __('validation.password_min', ['min' => $min])
            );

            return;
        }

        if (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/\d/', $password)) {
            $this->addError('password', __('validation.password_complex'));
        }
    }

    private function emailUnique(string $email, int $ignoreUserId): void
    {
        $sql      = 'SELECT COUNT(*) FROM `users` WHERE `email` = :email';
        $bindings = ['email' => $email];

        if ($ignoreUserId > 0) {
            $sql            .= ' AND `id` <> :id';
            $bindings['id'] = $ignoreUserId;
        }

        if ((int) \App\Core\Database::selectValue($sql, $bindings) > 0) {
            $this->addError('email', __('auth.email_taken'));
        }
    }
}