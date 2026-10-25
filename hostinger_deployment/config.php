<?php
/**
 * ====================================================================
 * ULTIMATE SECURITY SERVICES (USS) - INCIDENT REPORT SYSTEM
 * Hostinger Production Configuration File
 * ====================================================================
 * Update the database credentials below to match your Hostinger MySQL database.
 * You can create this database inside your Hostinger hPanel -> Databases -> MySQL.
 */

// Prevent multiple inclusion
if (defined('USS_CONFIG_LOADED')) {
    return;
}
define('USS_CONFIG_LOADED', true);

// Keep host-specific credentials outside the deployable source tree.
$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    require_once $localConfigPath;
}

// 1. Error Reporting (Set to false / 0 in strict production)
define('APP_DEBUG', false);
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// 2. MySQL / MariaDB Database Credentials
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = php_sapi_name() === 'cli'
    || is_dir('C:\\xampp')
    || strpos($httpHost, 'localhost') !== false
    || strpos($httpHost, '127.0.0.1') !== false
    || strpos($httpHost, '::1') !== false
    || strpos($httpHost, ':8080') !== false
    || strpos($httpHost, ':8000') !== false
    || strpos($httpHost, 'trycloudflare.com') !== false
    || strpos($httpHost, 'localtunnel.me') !== false
    || preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $httpHost);

if ($isLocal) {
    // Localhost / Local Wi-Fi Development (XAMPP MySQL)
    define('DB_HOST', 'localhost');
    define('DB_PORT', 3306);
    define('DB_NAME', 'uss_incidents');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
} else {
    // ====================================================================
    // HOSTINGER DATABASE CREDENTIALS
    // ====================================================================
    define('DB_HOST', 'localhost');
    define('DB_PORT', 3306);
    define('DB_NAME', 'u211560622_superadmin');
    define('DB_USER', 'u211560622_superadmin');
    define('DB_PASS', ''); // <--- TYPE YOUR DATABASE PASSWORD BETWEEN THESE QUOTES
    define('DB_CHARSET', 'utf8mb4');
}

// 3. Application Domain & URLs
// Set to your Hostinger subdomain (e.g. 'https://incidents.sprinzo.in')
// If empty, auto-detects current protocol and host
if (!defined('APP_URL_OVERRIDE')) {
    define('APP_URL_OVERRIDE', getenv('USS_APP_URL') ?: '');
}

// 4. Timezone & Security
define('DEFAULT_TIMEZONE', 'Asia/Dubai');
date_default_timezone_set(DEFAULT_TIMEZONE);

// Cryptographic Salt & Pepper for Session Signatures, Argon2id & Token HMAC
define('APP_KEY', getenv('USS_APP_KEY') ?: 'uss_prod_sec_key_sprinzo_9988224411_!@#$%^');
define('APP_PEPPER', getenv('USS_APP_PEPPER') ?: 'uss_pepper_sec_vault_sprinzo_88992211');
define('APP_ENCRYPTION_KEY', getenv('USS_ENCRYPTION_KEY') ?: 'uss_aes256_enc_key_993377114422');

// Password Security Algorithm Preference (Argon2id with fallback to Bcrypt)
define('PASSWORD_ALGO_PREFERRED', defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
define('ARGON2_OPTIONS', [
    'memory_cost' => 65536, // 64 MB
    'time_cost'   => 4,     // 4 passes
    'threads'     => 1
]);

// Strict Admin-Only System Policies (No Automatic Modifications or Purging)
define('ENABLE_AUTO_TRASH_PURGE', false);   // Trash is NEVER purged automatically; manual Super Admin action only
define('ENABLE_AUTO_EMAIL_REPORTS', false); // Scheduled report emailing is OFF by default; requires explicit admin enablement

// Session Cookie Lifetimes
define('ADMIN_SESSION_LIFETIME', 28800);       // 8 Hours for Admin
define('GUARD_SESSION_LIFETIME', 28800);       // 8 Hours for Guard patrol shift (prevents premature logouts)

// File Upload Constraints
define('MAX_FILE_SIZE_MB', 15);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/heic']);
define('MAX_PHOTOS_PER_INCIDENT', 12);

// Base & Storage Directories
define('ROOT_PATH', __DIR__);
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('STORAGE_PRIVATE_PATH', ROOT_PATH . '/storage/private');
define('PHOTOS_PRIVATE_PATH', STORAGE_PRIVATE_PATH . '/photos');
define('BACKUPS_PATH', ROOT_PATH . '/backups');

/**
 * Get active Cloudflare/public tunnel URL if running in dev mode
 */
function get_active_tunnel_url() {
    $searchPaths = [
        dirname(__DIR__) . '/onlineincidentreport/data/active_tunnel.json',
        dirname(__DIR__) . '/hostinger_deployment/data/active_tunnel.json',
        dirname(__DIR__) . '/data/active_tunnel.json',
        __DIR__ . '/data/active_tunnel.json',
        __DIR__ . '/../onlineincidentreport/data/active_tunnel.json',
        __DIR__ . '/../data/active_tunnel.json',
        'C:/Users/micro/OneDrive/Desktop/onlineincidentreport/onlineincidentreport/data/active_tunnel.json',
        'C:/Users/micro/OneDrive/Desktop/onlineincidentreport/hostinger_deployment/data/active_tunnel.json',
        'C:/Users/micro/OneDrive/Desktop/onlineincidentreport/data/active_tunnel.json',
    ];
    
    foreach ($searchPaths as $path) {
        if (file_exists($path)) {
            $content = @file_get_contents($path);
            if ($content) {
                $data = json_decode($content, true);
                if (!empty($data['url']) && !empty($data['active'])) {
                    // Check heartbeat freshness (within 2 minutes)
                    if (isset($data['heartbeat']) && (time() * 1000 - $data['heartbeat'] > 120000)) {
                        continue;
                    }
                    $url = rtrim($data['url'], '/');
                    if (strpos($url, 'http://') === 0) {
                        $url = 'https://' . substr($url, 7);
                    }
                    return $url;
                }
            }
        }
    }
    return '';
}

/**
 * Get active LAN IPs for Wi-Fi testing
 */
function get_lan_ips() {
    static $cachedIps = null;
    if ($cachedIps !== null) return $cachedIps;
    
    $lanIps = [];
    $out = @shell_exec('ipconfig');
    if ($out) {
        preg_match_all('/IPv4 Address[.\s]+:\s*([0-9.]+)/i', $out, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $ip) {
                if ($ip !== '127.0.0.1' && !in_array($ip, $lanIps)) {
                    $lanIps[] = $ip;
                }
            }
        }
    }
    if (empty($lanIps)) {
        $hostIp = @gethostbyname(gethostname());
        if ($hostIp && $hostIp !== '127.0.0.1') {
            $lanIps[] = $hostIp;
        }
    }
    $cachedIps = $lanIps;
    return $lanIps;
}

/**
 * Determine dynamic Base URL with full TLS & reverse proxy support
 */
function get_base_url() {
    if (defined('APP_URL_OVERRIDE') && !empty(APP_URL_OVERRIDE)) {
        return rtrim(APP_URL_OVERRIDE, '/');
    }
    
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    
    // Check if accessed through reverse proxy/tunnel with HTTPS
    $isHttps = false;
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $isHttps = true;
    } elseif (isset($_SERVER['SERVER_PORT']) && in_array((int)$_SERVER['SERVER_PORT'], [443, 8443])) {
        $isHttps = true;
    } elseif (!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') {
        $isHttps = true;
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        $isHttps = true;
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
        $isHttps = true;
    } elseif (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false) {
        $isHttps = true;
    } elseif (strpos($host, 'sprinzo.in') !== false || strpos($host, 'trycloudflare.com') !== false || strpos($host, 'localtunnel.me') !== false || strpos($host, 'ngrok') !== false) {
        $isHttps = true;
    }
    
    $protocol = $isHttps ? "https://" : "http://";
    
    // Calculate folder offset if hosted in a subfolder
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptDir = $scriptName ? dirname($scriptName) : '';
    $scriptDir = str_replace(['/admin', '/g', '/api'], '', $scriptDir);
    $scriptDir = str_replace('\\', '/', $scriptDir);
    $scriptDir = rtrim($scriptDir, '/');
    if ($scriptDir === '.' || $scriptDir === '/') {
        $scriptDir = '';
    }
    
    return rtrim($protocol . $host . $scriptDir, '/');
}
define('APP_URL', get_base_url());
