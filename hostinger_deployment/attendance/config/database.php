<?php
/**
 * Database Connection for uss_attendance
 * Seamlessly integrates with the active Hostinger database or dedicated attendance DB
 */
require_once __DIR__ . '/config.php';

function att_get_db() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    // 1. First priority: Reuse the active main system PDO connection if available
    $parentDbFile = dirname(dirname(__DIR__)) . '/includes/db.php';
    if (!class_exists('Database') && file_exists($parentDbFile)) {
        @require_once $parentDbFile;
    }

    if (class_exists('Database')) {
        try {
            $mainPdo = Database::getInstance();
            if ($mainPdo instanceof PDO) {
                $pdo = $mainPdo;
                return $pdo;
            }
        } catch (Throwable $e) {
            // Fall back to dedicated connection below
        }
    }

    // 2. Second priority: Connect using ATT_DB_* constants
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        defined('ATT_DB_HOST') ? ATT_DB_HOST : 'localhost',
        defined('ATT_DB_PORT') ? ATT_DB_PORT : 3306,
        defined('ATT_DB_NAME') ? ATT_DB_NAME : 'uss_attendance',
        defined('ATT_DB_CHARSET') ? ATT_DB_CHARSET : 'utf8mb4'
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    try {
        $pdo = new PDO($dsn, ATT_DB_USER, ATT_DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        @error_log("[" . date('Y-m-d H:i:s') . "] Attendance DB Error: " . $e->getMessage() . "
", 3, ATT_LOGS_PATH . '/db_errors.log');
        http_response_code(503);
        die("<h3>Attendance Subsystem Unavailable</h3><p>Could not connect to the database. Error: " . htmlspecialchars($e->getMessage()) . "</p>");
    }
}
