<?php
/**
 * Super Admin Logout Handler
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['admin_id'])) {
    log_audit('ADMIN_LOGOUT', 'ADMIN', $_SESSION['admin_id'], 'Logged out cleanly');
}

destroy_admin_session();
header("Location: " . APP_URL . "/admin/login.php?msg=logged_out");
exit;
