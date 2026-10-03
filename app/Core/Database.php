<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * Couche d'accès aux données : PDO singleton, requêtes préparées,
 * transactions imbriquées.
 *
 * Toutes les valeurs passent par des requêtes préparées
 * (cf. §13.3 — protection contre l'injection SQL).
 */
final class Database
{
    private static ?PDO $pdo = null;

    private static int $transactionLevel = 0;

    private static int $queryCount = 0;

    /** Empêche l'instanciation directe. */
    private function __construct()
    {
    }

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host     = (string) Config::get('database.connections.mysql.host', '127.0.0.1');
        $port     = (int) Config::get('database.connections.mysql.port', 3306);
        $database = (string) Config::get('database.connections.mysql.database', 'wylde');
        $charset  = (string) Config::get('database.connections.mysql.charset', 'utf8mb4');
        $collation = (string) Config::get('database.connections.mysql.collation', 'utf8mb4_unicode_ci');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        $options = Config::get('database.connections.mysql.options', []);

        try {
            self::$pdo = new PDO(
                $dsn,
                (string) Config::get('database.connections.mysql.username', 'root'),
                (string) Config::get('database.connections.mysql.password', ''),
                $options
            );
        } catch (PDOException $e) {
            Logger::error('DB connection failed: ' . $e->getMessage());

            throw new \RuntimeException('Connexion à la base de données impossible.', 0, $e);
        }

        self::$pdo->exec("SET NAMES {$charset} COLLATE {$collation}");

        if ((bool) Config::get('database.connections.mysql.strict', true)) {
            self::$pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION,ERROR_FOR_DIVISION_BY_ZERO'");
        }

        return self::$pdo;
    }

    public static function pdo(): PDO
    {
        return self::connection();
    }

    public static function isConnected(): bool
    {
        return self::$pdo instanceof PDO;
    }

    /**
     * Prépare et exécute une requête.
     *
     * @param array<string|int, mixed> $bindings
     */
    public static function run(string $sql, array $bindings = []): PDOStatement
    {
        $start = microtime(true);

        try {
            $statement = self::connection()->prepare($sql);
            self::bindValues($statement, $bindings);
            $statement->execute();
        } catch (PDOException $e) {
            Logger::error('SQL error: ' . $e->getMessage() . ' | ' . $sql . ' | ' . json_encode($bindings));
            throw $e;
        }

        self::$queryCount++;

        if (Config::isDebug()) {
            $ms = round((microtime(true) - $start) * 1000, 2);
            Logger::debug(sprintf('Query (%.2fms): %s | %s', $ms, $sql, json_encode($bindings)));
        }

        return $statement;
    }

    /**
     * @param  array<string|int, mixed> $bindings
     * @return array<int, array<string, mixed>>
     */
    public static function select(string $sql, array $bindings = []): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = self::run($sql, $bindings)->fetchAll();

        return $rows;
    }

    /**
     * @param  array<string|int, mixed> $bindings
     * @return array<string, mixed>|null
     */
    public static function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = self::run($sql, $bindings)->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string|int, mixed> $bindings */
    public static function selectValue(string $sql, array $bindings = []): mixed
    {
        $value = self::run($sql, $bindings)->fetchColumn();

        return $value === false ? null : $value;
    }

    /** @param array<string|int, mixed> $bindings */
    public static function statement(string $sql, array $bindings = []): int
    {
        return self::run($sql, $bindings)->rowCount();
    }

    /**
     * Insertion. Les colonnes sont filtrées pour ne transmettre que
     * des identifiants SQL sûrs.
     *
     * @param array<string, mixed> $data
     */
    public static function insert(string $table, array $data): int
    {
        $columns = self::safeColumns(array_keys($data));
        $sql     = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            self::safeIdentifier($table),
            implode('`, `', $columns),
            implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns))
        );

        self::run($sql, $data);

        return (int) self::connection()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     */
    public static function update(string $table, array $data, array $where): int
    {
        if ($data === []) {
            return 0;
        }

        $set        = [];
        $bindings   = [];

        foreach (self::safeColumns(array_keys($data)) as $column) {
            $set[]              = "`{$column}` = :set_{$column}";
            $bindings['set_' . $column] = $data[$column];
        }

        [$clause, $whereBindings] = self::buildWhere($where);

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            self::safeIdentifier($table),
            implode(', ', $set),
            $clause
        );

        return self::statement($sql, array_merge($bindings, $whereBindings));
    }

    /** @param array<string, mixed> $where */
    public static function delete(string $table, array $where): int
    {
        [$clause, $bindings] = self::buildWhere($where);

        return self::statement(
            sprintf('DELETE FROM `%s` WHERE %s', self::safeIdentifier($table), $clause),
            $bindings
        );
    }

    // ── Transactions ──────────────────────────────────────────

    public static function beginTransaction(): void
    {
        if (self::$transactionLevel === 0) {
            self::connection()->beginTransaction();
        } else {
            self::connection()->exec('SAVEPOINT trans' . self::$transactionLevel);
        }

        self::$transactionLevel++;
    }

    public static function commit(): void
    {
        if (self::$transactionLevel === 0) {
            return;
        }

        self::$transactionLevel--;

        if (self::$transactionLevel === 0) {
            self::connection()->commit();
        } else {
            self::connection()->exec('RELEASE SAVEPOINT trans' . self::$transactionLevel);
        }
    }

    public static function rollBack(): void
    {
        if (self::$transactionLevel === 0) {
            return;
        }

        self::$transactionLevel--;

        if (self::$transactionLevel === 0) {
            self::connection()->rollBack();
        } else {
            self::connection()->exec('ROLLBACK TO SAVEPOINT trans' . self::$transactionLevel);
        }
    }

    /**
     * Exécute un callable dans une transaction. En cas d'exception :
     * rollback puis re-lancement (le rollback seul avale l'erreur métier).
     */
    public static function transaction(callable $callback): mixed
    {
        self::beginTransaction();

        try {
            $result = $callback();
            self::commit();

            return $result;
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    public static function inTransaction(): bool
    {
        return self::$transactionLevel > 0;
    }

    public static function queryCount(): int
    {
        return self::$queryCount;
    }

    // ── Internes ─────────────────────────────────────────────

    /**
     * @param  array<string|int, mixed> $bindings
     */
    private static function bindValues(PDOStatement $statement, array $bindings): void
    {
        foreach ($bindings as $key => $value) {
            $param = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');

            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };

            $statement->bindValue($param, $value, $type);
        }
    }

    /**
     * @param  array<string, mixed> $where
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function buildWhere(array $where): array
    {
        if ($where === []) {
            throw new \InvalidArgumentException('Clause WHERE obligatoire pour éviter une affectation complète.');
        }

        $parts    = [];
        $bindings = [];

        foreach ($where as $column => $value) {
            $column = self::safeIdentifier((string) $column);

            if ($value === null) {
                $parts[] = "`{$column}` IS NULL";
                continue;
            }

            $parts[]           = "`{$column}` = :where_{$column}";
            $bindings['where_' . $column] = $value;
        }

        return [implode(' AND ', $parts), $bindings];
    }

    /**
     * @param  array<int, string> $columns
     * @return array<int, string>
     */
    private static function safeColumns(array $columns): array
    {
        $safe = [];

        foreach ($columns as $column) {
            $safe[] = self::safeIdentifier((string) $column);
        }

        if ($safe === []) {
            throw new \InvalidArgumentException('Aucune colonne fournie.');
        }

        return $safe;
    }

    private static function safeIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException("Identifiant SQL invalide : {$identifier}");
        }

        return $identifier;
    }
}
