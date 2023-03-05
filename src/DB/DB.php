<?php

declare(strict_types=1);

namespace LeanPHP\DB;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

class DB
{
    private static ?PDO $connection = null;

    /**
     * Get or create the database connection
     */
    private static function connection(): PDO
    {
        if (self::$connection === null) {
            self::connect();
        }

        if (self::$connection === null) {
            throw new RuntimeException('Database connection could not be established');
        }

        return self::$connection;
    }

    /**
     * Connect to the database using environment variables
     */
    private static function connect(): void
    {
        $dsn = \env_string('DB_DSN', 'sqlite::memory:') ?? 'sqlite::memory:';
        $user = \env_string('DB_USER', '') ?? '';
        $password = \env_string('DB_PASSWORD', '') ?? '';
        $persistent = \env_bool('DB_ATTR_PERSISTENT', false) ?? false;

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($persistent) {
            $options[PDO::ATTR_PERSISTENT] = true;
        }


        try {
            self::$connection = new PDO($dsn, $user, $password, $options);

            // Enable foreign key constraints for SQLite
            if (str_starts_with($dsn, 'sqlite:')) {
                self::$connection->exec('PRAGMA foreign_keys = ON');
            }
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Execute a SELECT query and return all rows as associative arrays
     *
     * @param string $sql The SQL query
     * @param array $params Parameters for prepared statement
     * @return array Array of associative arrays
     */
    public static function select(string $sql, array $params = []): array
    {
        $statement = self::prepare($sql, $params);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * Execute a query that doesn't return data (INSERT, UPDATE, DELETE)
     *
     * @param string $sql The SQL query
     * @param array $params Parameters for prepared statement
     * @return int Number of affected rows
     */
    public static function execute(string $sql, array $params = []): int
    {
        $statement = self::prepare($sql, $params);
        $statement->execute();

        return $statement->rowCount();
    }

    /**
     * Execute a callable within a database transaction
     *
     * @param callable $callback The function to execute within the transaction
     * @return mixed The return value of the callback
     * @throws RuntimeException If the transaction fails
     */
    public static function transaction(callable $callback): mixed
    {
        $connection = self::connection();

        try {
            $connection->beginTransaction();
            $result = $callback();
            $connection->commit();

            return $result;
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Prepare a SQL statement with parameters
     *
     * @param string $sql The SQL query
     * @param array $params Parameters for prepared statement
     * @return PDOStatement
     */
    private static function prepare(string $sql, array $params): PDOStatement
    {
        $connection = self::connection();
        $statement = $connection->prepare($sql);

        foreach ($params as $key => $value) {
            if (is_int($key)) {
                // Positional parameters (1-indexed)
                $statement->bindValue($key + 1, $value, self::getPdoType($value));
            } else {
                // Named parameters
                $statement->bindValue($key, $value, self::getPdoType($value));
            }
        }

        return $statement;
    }

    /**
     * Get the appropriate PDO parameter type for a value
     *
     * @param mixed $value
     * @return int
     */
    private static function getPdoType(mixed $value): int
    {
        return match (gettype($value)) {
            'boolean' => PDO::PARAM_BOOL,
            'integer' => PDO::PARAM_INT,
            'NULL' => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    /**
     * Get the raw PDO connection (for advanced usage)
     *
     * @return PDO
     */
    public static function getPdo(): PDO
    {
        return self::connection();
    }

    /**
     * Reset the connection (useful for testing)
     */
    public static function resetConnection(): void
    {
        self::$connection = null;
    }
}
