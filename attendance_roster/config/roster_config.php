<?php
/**
 * Universal Security Services - Attendance Roster Subsystem
 * Configuration & Kill Switch
 * 
 * All files for this subsystem reside exclusively in /attendance_roster/
 */

// 1. KILL-SWITCH: Set to false to immediately disable and hide the entire roster module
if (!defined('ROSTER_ENABLED')) {
    define('ROSTER_ENABLED', true);
}

// Check kill-switch immediately
if (!ROSTER_ENABLED) {
    http_response_code(503);
    die('<!DOCTYPE html><html><head><title>503 Service Unavailable</title></head><body style="font-family:sans-serif;text-align:center;padding:50px;"><h2>Roster Subsystem Disabled</h2><p>The attendance roster subsystem is currently disabled by system administrator.</p></body></html>');
}

// 2. Base Paths & URLs
if (!defined('ROSTER_ROOT')) {
    define('ROSTER_ROOT', dirname(__DIR__));
}

// 3. Allowed Shift Codes
if (!defined('ROSTER_VALID_CODES')) {
    define('ROSTER_VALID_CODES', ['D', 'N', 'OFF', 'S', 'B']);
}

// 4. Session & Authentication Check (Strict Super Admin Only)
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

function roster_require_super_admin() {
    // Check Super Admin session
    $isLoggedIn = !empty($_SESSION['admin_logged_in']) || !empty($_SESSION['admin_id']);
    $role = $_SESSION['admin_role'] ?? $_SESSION['role'] ?? '';
    
    // Fallback for incident super admin session format
    if (!$isLoggedIn && !empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
        $isLoggedIn = true;
        $role = $_SESSION['role'];
    }

    if (!$isLoggedIn || (strcasecmp($role, 'Super Admin') !== 0 && strcasecmp($role, 'admin') !== 0)) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized: Super Admin access required.']);
            exit;
        }
        http_response_code(403);
        die('<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body style="font-family:sans-serif;text-align:center;padding:60px;"><h2>Access Denied</h2><p>Only the Super Admin has permission to view or manage attendance rosters.</p><p><a href="/admin/login.php">Return to Admin Login</a></p></body></html>');
    }
}

// 5. Automated Zero-Crash Schema Initializer
function roster_auto_ensure_schema(PDO $pdo) {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'roster_staff'");
        if (!$stmt->fetch()) {
            $migrationSql = dirname(__DIR__) . '/migrations/001_create_roster_tables.sql';
            if (file_exists($migrationSql)) {
                $sql = file_get_contents($migrationSql);
                $queries = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($queries as $q) {
                    if (!empty($q)) {
                        $pdo->exec($q);
                    }
                }
            }
        }
    } catch (Throwable $e) {
        @error_log("[" . date('Y-m-d H:i:s') . "] Roster Auto-Schema Error: " . $e->getMessage() . "\n");
    }
}

// 6. Database Connection Helper (Reuses system PDO or connects seamlessly on Hostinger)
function roster_get_db() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    // Priority 1: Check root includes/db.php (when deployed at Hostinger public_html/attendance_roster)
    $hostingerDb = dirname(__DIR__) . '/includes/db.php';
    if (!class_exists('Database') && file_exists($hostingerDb)) {
        @require_once $hostingerDb;
    }

    // Priority 2: Check local development hostinger_deployment/includes/db.php
    $localDevDb = dirname(dirname(__DIR__)) . '/hostinger_deployment/includes/db.php';
    if (!class_exists('Database') && file_exists($localDevDb)) {
        @require_once $localDevDb;
    }

    if (class_exists('Database')) {
        try {
            $mainPdo = Database::getInstance();
            if ($mainPdo instanceof PDO) {
                $pdo = $mainPdo;
                roster_auto_ensure_schema($pdo);
                return $pdo;
            }
        } catch (Throwable $e) {}
    }

    // Fallback: connect using system DB_* constants or environment variables
    $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: 'localhost');
    $dbname = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'uss_attendance');
    $user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'root');
    $pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: '');
    $port = defined('DB_PORT') ? DB_PORT : (getenv('DB_PORT') ?: 3306);
    $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci"
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        roster_auto_ensure_schema($pdo);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(503);
        die("<h3>Attendance Roster Database Unavailable</h3><p>" . htmlspecialchars($e->getMessage()) . "</p>");
    }
}

