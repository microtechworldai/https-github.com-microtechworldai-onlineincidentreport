<?php
/**
 * Admin Gateway - Auto Route to Dashboard / Login
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

if (!empty($_SESSION['uss_admin_logged_in']) && !empty($_SESSION['admin_id'])) {
    header("Location: " . APP_URL . "/admin/dashboard.php");
} else {
    header("Location: " . APP_URL . "/admin/login.php");
}
exit;
