<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Database;

/**
 * Base des validateurs de formulaire.
 *
 * Un validateur ne fait qu'enregistrer des erreurs : il ne touche jamais à la
 * base ni à la session. Le contrôleur décide ensuite de renvoyer le
 * formulaire avec les messages, ce qui garde la validation testable.
 */
abstract class Validator
{
    /** @var array<string, string> champ => message */
    protected array $errors = [];

    /** @var array<string, mixed> */
    protected array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** Première erreur, pour un message court côté API. */
    public function firstError(): string
    {
        return (string) (reset($this->errors) ?: '');
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    /**
     * Données validées, limitées aux champs déclarés.
     *
     * @param  array<int, string> $fields
     * @return array<string, mixed>
     */
    protected function only(array $fields): array
    {
        return array_intersect_key($this->data, array_flip($fields));
    }

    // ── Règles réutilisables ────────────────────────────────

    protected function value(string $field): mixed
    {
        $value = $this->data[$field] ?? null;

        return is_string($value) ? trim($value) : $value;
    }

    protected function string(string $field, string $label): string
    {
        return (string) $this->value($field);
    }

    /**
     * Valeur texte avec repli explicite.
     *
     * Indispensable pour les colonnes ENUM : un <select> dont l'option
     * « aucune » est vide transmet '', que MySQL refuse (Data truncated)
     * là où string() renverrait cette chaîne vide.
     *
     * @param string $default Valeur écrite quand le champ est absent ou vide
     */
    protected function stringOr(string $field, string $default): string
    {
        $value = $this->value($field);

        return $value === null || $value === '' ? $default : (string) $value;
    }

    protected function required(string $field, string $label): bool
    {
        $value = $this->value($field);

        if ($value === null || $value === '' || $value === []) {
            $this->addError($field, __('validation.required', ['field' => $label]));

            return false;
        }

        return true;
    }

    protected function email(string $field, string $label): void
    {
        $value = (string) $this->value($field);

        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->addError($field, __('validation.email', ['field' => $label]));
        }
    }

    protected function minLength(string $field, string $label, int $min): void
    {
        $value = (string) $this->value($field);

        if ($value !== '' && mb_strlen($value, 'UTF-8') < $min) {
            $this->addError($field, __('validation.min', ['field' => $label, 'min' => $min]));
        }
    }

    protected function maxLength(string $field, string $label, int $max): void
    {
        $value = (string) $this->value($field);

        if (mb_strlen($value, 'UTF-8') > $max) {
            $this->addError($field, __('validation.max', ['field' => $label, 'max' => $max]));
        }
    }

    protected function integer(string $field, string $label): void
    {
        $value = $this->value($field);

        if ($value === null || $value === '') {
            return;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->addError($field, __('validation.integer', ['field' => $label]));
        }
    }

    protected function positive(string $field, string $label): void
    {
        $value = $this->value($field);

        if ($value === null || $value === '') {
            return;
        }

        if (!is_numeric($value) || (int) $value <= 0) {
            $this->addError($field, __('validation.positive', ['field' => $label]));
        }
    }

    /** @param array<int, string> $allowed */
    protected function inList(string $field, string $label, array $allowed): void
    {
        $value = $this->value($field);

        if ($value === null || $value === '') {
            return;
        }

        if (!in_array((string) $value, $allowed, true)) {
            $this->addError($field, __('validation.in', ['field' => $label]));
        }
    }

    protected function confirmed(string $field, string $confirmation = 'password_confirmation'): void
    {
        if ($this->value($field) !== $this->value($confirmation)) {
            $this->addError($confirmation, __('validation.confirmed'));
        }
    }

    /** Slug unique sur une table, en ignorant la ligne en cours d'édition. */
    protected function slugUnique(string $slug, string $table, ?int $ignoreId = null): void
    {
        if ($slug === '') {
            return;
        }

        $sql      = 'SELECT COUNT(*) FROM `' . preg_replace('/[^a-z_]/i', '', $table) . '` WHERE `slug` = :slug';
        $bindings = ['slug' => $slug];

        if ($ignoreId !== null) {
            $sql            .= ' AND `id` <> :id';
            $bindings['id'] = $ignoreId;
        }

        if ((int) Database::selectValue($sql, $bindings) > 0) {
            $this->addError('slug', __('validation.slug_unique'));
        }
    }

    /** SKU unique lorsqu'il est renseigné. */
    protected function skuUnique(?string $sku, ?int $ignoreId = null): void
    {
        $sku = trim((string) $sku);

        if ($sku === '') {
            return;
        }

        $sql      = 'SELECT COUNT(*) FROM `product_variants` WHERE `sku` = :sku';
        $bindings = ['sku' => $sku];

        if ($ignoreId !== null) {
            $sql            .= ' AND `id` <> :id';
            $bindings['id'] = $ignoreId;
        }

        if ((int) Database::selectValue($sql, $bindings) > 0) {
            $this->addError('sku', __('validation.sku_unique'));
        }
    }

    /** Champ texte optionnel : '' devient null pour la base. */
    protected function nullable(string $field): ?string
    {
        $value = (string) $this->value($field);

        return $value === '' ? null : $value;
    }
}
