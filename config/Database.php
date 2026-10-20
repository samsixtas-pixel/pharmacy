<?php
/**
 * Database.php
 * -------------------------------------------------------
 * Handles secure PDO database connection using the
 * Singleton design pattern so only ONE connection instance
 * exists throughout the application lifecycle.
 *
 * Demonstrates: Encapsulation, Singleton (a form of
 * controlled object creation), Exception handling.
 * -------------------------------------------------------
 */

class Database
{
    // Database configuration (encapsulated as private properties)
    private string $host = 'localhost';
    private string $dbName = 'pharmacy_ims';
    private string $username = 'root';
    private string $password = '';
    private string $charset = 'utf8mb4';

    // Holds the single PDO instance
    private static ?PDO $instance = null;

    /**
     * Private constructor prevents creating new instances
     * from outside the class (Singleton pattern).
     */
    private function __construct()
    {
    }

    /**
     * getConnection
     * Returns a single shared PDO instance. Creates it on
     * first call, then reuses it for subsequent calls.
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $config = new self();

            $dsn = "mysql:unix_socket=/opt/lampp/var/mysql/mysql.sock;dbname={$config->dbName};charset={$config->charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements (SQL injection safe)
            ];

            try {
                self::$instance = new PDO($dsn, $config->username, $config->password, $options);
            } catch (PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                if (defined('APP_ENV') && APP_ENV === 'development') {
                    die('Database connection failed: ' . $e->getMessage());
                }
                http_response_code(500);
                die('Service temporarily unavailable. Please contact the administrator.');
            }
        }

        return self::$instance;
    }
}
