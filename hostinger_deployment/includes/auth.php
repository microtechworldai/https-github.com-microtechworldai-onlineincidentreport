<?php
/**
 * Hard Security Dual-Realm Authentication Guard
 * Enforces total isolation between Super Admin and Security Guard roles.
 * Unauthenticated or cross-realm requests receive 404 (Anti-probing defense).
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Safe secure session initialization
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

/**
 * Super Admin Auth Enforcement
 */
function require_admin_auth() {
    // Check if valid admin session exists
    if (empty($_SESSION['uss_admin_logged_in']) || empty($_SESSION['admin_id'])) {
        // Anti-probing defense: show 404 if not on login page
        $currentScript = basename($_SERVER['PHP_SELF']);
        if ($currentScript !== 'login.php') {
            header("Location: " . APP_URL . "/admin/login.php");
            exit;
        }
        return false;
    }

    // Check session timeout (8 hours default)
    if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity'] > ADMIN_SESSION_LIFETIME)) {
        destroy_admin_session();
        header("Location: " . APP_URL . "/admin/login.php?msg=expired");
        exit;
    }
    $_SESSION['admin_last_activity'] = time();

    // Check if forced password change is required
    if (!empty($_SESSION['must_change_password'])) {
        $currentScript = basename($_SERVER['PHP_SELF']);
        if ($currentScript !== 'change_password.php' && $currentScript !== 'logout.php') {
            header("Location: " . APP_URL . "/admin/change_password.php?forced=1");
            exit;
        }
    }

    return true;
}

/**
 * Guard Auth Enforcement
 */
function require_guard_auth() {
    // Allow logged-in supervisor to report incidents
    if (!empty($_SESSION['uss_supervisor_logged_in']) && !empty($_SESSION['supervisor_id'])) {
        if (empty($_SESSION['uss_guard_logged_in'])) {
            $_SESSION['uss_guard_logged_in'] = true;
            $_SESSION['guard_id'] = $_SESSION['supervisor_id'];
            $_SESSION['guard_name'] = $_SESSION['supervisor_name'];
            $_SESSION['guard_staff_id'] = $_SESSION['supervisor_staff_id'];
            $_SESSION['guard_designation'] = 'Field Supervisor';
        }
    }

    if (empty($_SESSION['uss_guard_logged_in']) || empty($_SESSION['guard_id'])) {
        $token = $_GET['token'] ?? $_SESSION['site_qr_token'] ?? '';
        $redirectUrl = APP_URL . "/g/index.php" . ($token ? "?token=" . urlencode($token) : "");
        header("Location: " . $redirectUrl);
        exit;
    }

    // Guard session lifetime
    if (isset($_SESSION['guard_last_activity']) && (time() - $_SESSION['guard_last_activity'] > GUARD_SESSION_LIFETIME)) {
        destroy_guard_session();
        header("Location: " . APP_URL . "/g/index.php?msg=expired");
        exit;
    }
    $_SESSION['guard_last_activity'] = time();

    return true;
}

function require_supervisor_auth() {
    if (empty($_SESSION['uss_supervisor_logged_in']) || empty($_SESSION['supervisor_id'])) {
        header("Location: " . APP_URL . "/sup/login.php");
        exit;
    }

    $lastActivity = (int)($_SESSION['supervisor_last_activity'] ?? 0);
    if ($lastActivity && time() - $lastActivity > SUPERVISOR_SESSION_LIFETIME) {
        destroy_supervisor_session();
        header("Location: " . APP_URL . "/sup/login.php?msg=expired");
        exit;
    }

    $db = get_db();
    $stmt = $db->prepare("
        SELECT is_active, is_supervisor, supervisor_session_revoked_at
        FROM guards
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([(int)$_SESSION['supervisor_id']]);
    $supervisor = $stmt->fetch();
    $issuedAt = (int)($_SESSION['supervisor_session_issued_at'] ?? 0);
    $revokedAt = !empty($supervisor['supervisor_session_revoked_at'])
        ? strtotime($supervisor['supervisor_session_revoked_at'])
        : false;

    if (
        !$supervisor
        || empty($supervisor['is_active'])
        || empty($supervisor['is_supervisor'])
        || ($revokedAt !== false && $revokedAt >= $issuedAt)
    ) {
        destroy_supervisor_session();
        header("Location: " . APP_URL . "/sup/login.php?msg=revoked");
        exit;
    }

    $_SESSION['supervisor_last_activity'] = time();
    return true;
}

/**
 * Destroy Super Admin Session
 */
function destroy_admin_session() {
    unset($_SESSION['uss_admin_logged_in']);
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_name']);
    unset($_SESSION['must_change_password']);
    unset($_SESSION['admin_last_activity']);
}

/**
 * Destroy Guard Session (Triggered immediately on report submission)
 */
function destroy_guard_session() {
    unset($_SESSION['uss_guard_logged_in']);
    unset($_SESSION['guard_id']);
    unset($_SESSION['guard_staff_id']);
    unset($_SESSION['guard_name']);
    unset($_SESSION['guard_username']);
    unset($_SESSION['guard_designation']);
    unset($_SESSION['assigned_sites']);
    unset($_SESSION['current_site_id']);
    unset($_SESSION['current_site_code']);
    unset($_SESSION['current_site_name']);
    unset($_SESSION['guard_last_activity']);
}

function destroy_supervisor_session() {
    unset($_SESSION['uss_supervisor_logged_in']);
    unset($_SESSION['supervisor_id']);
    unset($_SESSION['supervisor_staff_id']);
    unset($_SESSION['supervisor_name']);
    unset($_SESSION['supervisor_camp_id']);
    unset($_SESSION['supervisor_last_activity']);
    unset($_SESSION['supervisor_session_issued_at']);
}
