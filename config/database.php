<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

const DB_HOST = '127.0.0.1';
const DB_NAME = 'shoes_inventory';
const DB_USER = 'root';
const DB_PASS = '';

/**
 * Returns a shared PDO instance configured for secure database operations.
 */
function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    return $pdo;
}
