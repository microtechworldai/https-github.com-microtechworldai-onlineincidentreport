<?php
/**
 * Dedicated Database Connection for uss_attendance
 * Strictly independent from incident database
 */
require_once __DIR__ . '/config.php';

function att_get_db() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        ATT_DB_HOST,
        ATT_DB_PORT,
        ATT_DB_NAME,
        ATT_DB_CHARSET
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
        // Log to module's private log and never output credentials
        @error_log("[" . date('Y-m-d H:i:s') . "] Attendance DB Error: " . $e->getMessage() . "\n", 3, ATT_LOGS_PATH . '/db_errors.log');
        http_response_code(503);
        die("<h3>Attendance Subsystem Unavailable</h3><p>Could not connect to the attendance database. Please verify the attendance database connection settings.</p>");
    }
}
