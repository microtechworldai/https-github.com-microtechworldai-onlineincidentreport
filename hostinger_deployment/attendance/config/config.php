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

// Optional: Auto-load parent project config if present
$parentConfig = dirname(dirname(__DIR__)) . '/config.php';
if (!defined('DB_NAME') && file_exists($parentConfig)) {
    @require_once $parentConfig;
}

if ($isLocal) {
    define('ATT_DB_HOST', 'localhost');
    define('ATT_DB_PORT', 3306);
    define('ATT_DB_NAME', 'uss_attendance');
    define('ATT_DB_USER', 'root');
    define('ATT_DB_PASS', '');
    define('ATT_DB_CHARSET', 'utf8mb4');
} else {
    // In production on Hostinger:
    // If a dedicated attendance database is not set, automatically inherit the active Hostinger database credentials!
    define('ATT_DB_HOST', defined('DB_HOST') ? DB_HOST : 'localhost');
    define('ATT_DB_PORT', defined('DB_PORT') ? DB_PORT : 3306);
    define('ATT_DB_NAME', (defined('ATT_CUSTOM_DB_NAME') ? ATT_CUSTOM_DB_NAME : (defined('DB_NAME') ? DB_NAME : 'u211560622_superadmin')));
    define('ATT_DB_USER', (defined('ATT_CUSTOM_DB_USER') ? ATT_CUSTOM_DB_USER : (defined('DB_USER') ? DB_USER : 'u211560622_superadmin')));
    define('ATT_DB_PASS', (defined('ATT_CUSTOM_DB_PASS') ? ATT_CUSTOM_DB_PASS : (defined('DB_PASS') ? DB_PASS : '')));
    define('ATT_DB_CHARSET', defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
}

// Module Paths
define('ATT_ROOT_PATH', dirname(__DIR__));
define('ATT_EXPORTS_PATH', ATT_ROOT_PATH . '/exports');
define('ATT_LOGS_PATH', ATT_ROOT_PATH . '/logs');
define('ATT_BACKUPS_PATH', ATT_ROOT_PATH . '/backups');
define('ATT_DATABASE_PATH', ATT_ROOT_PATH . '/database');

// Valid Daily Shift / Attendance Codes
define('ATT_VALID_CODES', ['D', 'N', 'OFF', 'S', 'B']);
