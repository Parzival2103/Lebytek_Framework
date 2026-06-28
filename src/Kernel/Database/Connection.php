<?php

declare(strict_types=1);

namespace App\Kernel\Database;

use PDO;
use PDOException;
use App\Kernel\Exceptions\AppException;

/*
|--------------------------------------------------------------------------
| Connection — Singleton PDO para MySQL
|--------------------------------------------------------------------------
| Proporciona una sola instancia de PDO reutilizable en toda la aplicación.
| Configurado para lanzar excepciones, usar UTF8MB4 y deshabilitar
| emulación de prepared statements.
*/

final class Connection
{
    private static ?PDO $instance = null;
    private static array $config  = [];

    private function __construct() {}
    private function __clone() {}

    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    public static function getInstance(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        if (empty(self::$config)) {
            throw new AppException('La conexión a la base de datos no ha sido configurada.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            self::$config['host']     ?? '127.0.0.1',
            (int) (self::$config['port']  ?? 3306),
            self::$config['database'] ?? '',
            self::$config['charset']  ?? 'utf8mb4'
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ];

        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
        }

        // PHP 8.5 movió la constante a Pdo\Mysql::ATTR_FOUND_ROWS
        if (defined('Pdo\Mysql::ATTR_FOUND_ROWS')) {
            $options[\Pdo\Mysql::ATTR_FOUND_ROWS] = true;
        } elseif (defined('PDO::MYSQL_ATTR_FOUND_ROWS')) {
            $options[PDO::MYSQL_ATTR_FOUND_ROWS] = true;
        }

        try {
            self::$instance = new PDO($dsn, self::$config['username'] ?? '', self::$config['password'] ?? '', $options);
        } catch (PDOException $e) {
            throw new AppException('Error de conexión a la base de datos: ' . $e->getMessage());
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
