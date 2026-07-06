<?php

declare(strict_types=1);

/**
 * ================================================================
 *  Database — single shared PDO connection
 * ================================================================
 */
final class Database
{
    private static ?PDO $instance = null;

    public static function connection(array $config): PDO
    {
        if (self::$instance === null) {
            $db = $config['db'];

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $db['host'],
                $db['port'],
                $db['name'],
                $db['charset']
            );

            try {
                self::$instance = new PDO($dsn, $db['user'], $db['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                error_log('[Database] Connection failed: ' . $e->getMessage());
                throw new RuntimeException('Database connection failed.');
            }
        }

        return self::$instance;
    }
}
