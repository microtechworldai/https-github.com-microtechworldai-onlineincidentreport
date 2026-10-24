<?php
/**
 * Attendance & Payroll Subsystem Configuration
 * Strictly isolated from incident project configuration
 */

if (defined('ATT_CONFIG_LOADED')) {
    return;
}
define('ATT_CONFIG_LOADED', true);

// Master Module Enable/Disable Kill-Switch
define('ATT_ENABLED', true);

// Timezone
define('ATT_TIMEZONE', 'Asia/Dubai');
date_default_timezone_set(ATT_TIMEZONE);

// Database Configuration - Dedicated uss_attendance database
$isLocal = (php_sapi_name() === 'cli'
    || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false
    || strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false
    || is_dir('C:\\xampp'));

if ($isLocal) {
    define('ATT_DB_HOST', 'localhost');
    define('ATT_DB_PORT', 3306);
    define('ATT_DB_NAME', 'uss_attendance');
    define('ATT_DB_USER', 'root');
    define('ATT_DB_PASS', '');
    define('ATT_DB_CHARSET', 'utf8mb4');
} else {
    // Hostinger production credentials (configured separately in production)
    define('ATT_DB_HOST', 'localhost');
    define('ATT_DB_PORT', 3306);
    define('ATT_DB_NAME', 'u123456789_uss_attendance');
    define('ATT_DB_USER', 'u123456789_att_admin');
    define('ATT_DB_PASS', 'YourStrongAttDbPass123!');
    define('ATT_DB_CHARSET', 'utf8mb4');
}

// Module Paths
define('ATT_ROOT_PATH', dirname(__DIR__));
define('ATT_EXPORTS_PATH', ATT_ROOT_PATH . '/exports');
define('ATT_LOGS_PATH', ATT_ROOT_PATH . '/logs');
define('ATT_BACKUPS_PATH', ATT_ROOT_PATH . '/backups');
define('ATT_DATABASE_PATH', ATT_ROOT_PATH . '/database');

// Valid Daily Shift / Attendance Codes
define('ATT_VALID_CODES', ['D', 'N', 'OFF', 'S', 'B']);
