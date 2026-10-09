<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;
use JsonSerializable;
use PDO;
use ReflectionClass;
use ReflectionProperty;

/**
 * Modèle de base : accès PDO, typage automatique, garde-fous.
 *
 * Le nom de table est déduit du nom de classe (Product -> products).
 * Les montants et identifiants sont hydratés avec le type PHP correct.
 */
abstract class BaseModel implements JsonSerializable
{
    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var array<string, mixed> Valeurs d'origine, pour detecter les changements. */
    protected array $original = [];

    protected bool $exists = false;

    /** Clés primaires composites non supportées en V1. */
    protected string $primaryKey = 'id';

    protected string $table = '';

    /** @var array<int, string> */
    protected array $fillable = [];

    /** @var array<int, string> Colonnes date à convertir en objets DateTime. */
    protected array $dates = ['created_at', 'updated_at'];

    /** Colonnes gérées comme entières. */
    protected array $intColumns = ['id'];

    /** @var array<int, string> Colonnes DECIMAL(12,0) : entières, jamais float. */
    protected array $moneyColumns = [];

    protected array $boolColumns = [];

    public function __construct(array $attributes = [])
    {
        $this->table = $this->table !== '' ? $this->table : $this->guessTable();

        $this->fill($attributes);
    }

    protected function guessTable(): string
    {
        $short = (new ReflectionClass($this))->getShortName();

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $short) ?? $short) . 's';
    }

    public function table(): string
    {
        return $this->table;
    }

    // ── Hydratation ──────────────────────────────────────────

    /** @param array<string, mixed> $attributes */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute((string) $key, $value);
        }

        return $this;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $this->castOut($key, $value);

        return $this;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        if (method_exists($this, $key) && $key !== 'id') {
            return $this->{$key}();
        }

        return $default;
    }

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Sérialisation JSON : un modèle doit survivre à un passage dans le
     * cache (json_encode) sans perdre ses données. Sans cela, Cache::put
     * écrirait « {} » et la page lue depuis le cache serait vide.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    public function id(): ?int
    {
        $value = $this->attributes[$this->primaryKey] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    protected function castIn(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (in_array($key, $this->intColumns, true)) {
            return (int) $value;
        }

        if (in_array($key, $this->moneyColumns, true)) {
            return money_int($value);
        }

        if (in_array($key, $this->boolColumns, true)) {
            return (bool) $value;
        }

        return $value;
    }

    protected function castOut(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (in_array($key, $this->moneyColumns, true)) {
            return money_int($value);
        }

        if (in_array($key, $this->boolColumns, true)) {
            return (bool) $value;
        }

        return $value;
    }

    // ── Requêtes ─────────────────────────────────────────────

    /** @param array<string, mixed> $bindings */
    public static function query(string $sql, array $bindings = []): static
    {
        $row = Database::selectOne($sql, $bindings);

        $model = new static();

        return $row === null ? $model->setExists(false) : $model->hydrate($row);
    }

    /**
     * @param  array<string, mixed> $bindings
     * @return array<int, static>
     */
    public static function all(string $orderBy = '', array $bindings = []): array
    {
        $sql = 'SELECT * FROM `' . (new static())->table() . '`';

        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $orderBy;
        }

        return array_map(
            static fn (array $row): static => (new static())->hydrate($row),
            Database::select($sql, $bindings)
        );
    }

    public static function find(int $id): ?static
    {
        $model = static::query(
            'SELECT * FROM `' . (new static())->table() . '` WHERE `id` = :id LIMIT 1',
            ['id' => $id]
        );

        return $model->exists() ? $model : null;
    }

    public static function findOrFail(int $id): static
    {
        $model = static::find($id);

        if ($model === null) {
            throw new \App\Core\HttpException(404, __('errors.not_found'));
        }

        return $model;
    }

    public static function findBySlug(string $slug): ?static
    {
        $model = static::query(
            'SELECT * FROM `' . (new static())->table() . '` WHERE `slug` = :slug LIMIT 1',
            ['slug' => $slug]
        );

        return $model->exists() ? $model : null;
    }

    public static function count(string $where = '', array $bindings = []): int
    {
        $sql = 'SELECT COUNT(*) FROM `' . (new static())->table() . '`';

        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }

        return (int) Database::selectValue($sql, $bindings);
    }

    // ── Écriture ─────────────────────────────────────────────

    /** @param array<string, mixed> $attributes */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->persist();

        return $model;
    }

    public function save(): static
    {
        return $this->persist();
    }

    protected function persist(): static
    {
        // $original n'est réaligné qu'après écriture (dans performUpdate /
        // performInsert) : le remettre ici viderait getDirty() et aucun
        // UPDATE ne partirait jamais.
        return $this->exists ? $this->performUpdate() : $this->performInsert();
    }

    protected function performInsert(): static
    {
        $now = date('Y-m-d H:i:s');
        $data = $this->attributes;

        $data['created_at'] = $data['created_at'] ?? $now;
        $data['updated_at'] = $data['updated_at'] ?? $now;

        $data = $this->filterFillable($data);
        $data = $this->stripPrimaryKey($data);

        $id = Database::insert($this->table, $data);

        $this->attributes[$this->primaryKey] = $id;
        $this->exists = true;
        $this->original = $this->attributes;
        $this->afterSave();

        return $this;
    }

    protected function performUpdate(): static
    {
        // On n'écrit que les colonnes réellement modifiées.
        $data = $this->filterFillable($this->getDirty());

        if (isset($data['updated_at']) === false && $this->hasUpdatedAt()) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->attributes['updated_at'] = $data['updated_at'];
        }

        $data = $this->stripPrimaryKey($data);

        if ($data === []) {
            return $this;
        }

        Database::update($this->table, $data, [$this->primaryKey => $this->id()]);

        $this->original = $this->attributes;
        $this->afterSave();

        return $this;
    }

    protected function hasUpdatedAt(): bool
    {
        return true;
    }

    public function delete(): bool
    {
        if (!$this->exists || $this->id() === null) {
            return false;
        }

        $deleted = Database::delete($this->table, [$this->primaryKey => $this->id()]);

        $this->exists = false;

        return $deleted > 0;
    }

    // ── Relations simples ────────────────────────────────────

    /** @return array<int, static> */
    public function hasMany(string $related, string $foreignKey, ?int $parentId = null): array
    {
        $parentId ??= $this->id();

        if ($parentId === null) {
            return [];
        }

        $instance = new $related();
        $rows     = Database::select(
            'SELECT * FROM `' . $instance->table() . '` WHERE `' . $foreignKey . '` = :id ORDER BY `id` ASC',
            ['id' => $parentId]
        );

        return array_map(
            static fn (array $row): BaseModel => (new $related())->hydrate($row),
            $rows
        );
    }

    public function belongsTo(string $related, string $foreignKey, mixed $value): ?BaseModel
    {
        if ($value === null) {
            return null;
        }

        $instance = new $related();

        $row = Database::selectOne(
            'SELECT * FROM `' . $instance->table() . '` WHERE `id` = :id LIMIT 1',
            ['id' => $value]
        );

        return $row === null ? null : (new $related())->hydrate($row);
    }

    // ── Internes ─────────────────────────────────────────────

    /** @param array<string, mixed> $row */
    public function hydrate(array $row): static
    {
        $this->attributes = [];

        foreach ($row as $key => $value) {
            $this->attributes[(string) $key] = $this->castIn((string) $key, $value);
        }

        $this->original = $this->attributes;
        $this->exists   = true;

        return $this;
    }

    public function setExists(bool $exists): static
    {
        $this->exists = $exists;

        return $this;
    }

    protected function afterSave(): void
    {
        // Surcharge possible (product : normalisation du slug, etc.).
    }

    /** @param array<string, mixed> $data */
    protected function filterFillable(array $data): array
    {
        if ($this->fillable === []) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }

    /** @param array<string, mixed> $data */
    protected function stripPrimaryKey(array $data): array
    {
        unset($data[$this->primaryKey]);

        return $data;
    }

    /**
     * Colonnes réellement modifiées depuis le dernier chargement.
     *
     * @return array<string, mixed>
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function isDirty(): bool
    {
        return $this->getDirty() !== [];
    }

    /** @return array<int, string> */
    public function dates(): array
    {
        return $this->dates;
    }
}
