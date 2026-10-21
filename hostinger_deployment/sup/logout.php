<?php
/**
 * Supervisor Safe Logout
 * Ultimate Security Services (USS)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$supId = $_SESSION['supervisor_id'] ?? null;
$supName = $_SESSION['supervisor_name'] ?? 'Supervisor';

if ($supId) {
    log_audit('SUPERVISOR_LOGOUT', "Supervisor logged out ({$supName})", null, 'guards', $supId);
}

unset($_SESSION['uss_supervisor_logged_in']);
unset($_SESSION['supervisor_id']);
unset($_SESSION['supervisor_staff_id']);
unset($_SESSION['supervisor_name']);
unset($_SESSION['supervisor_camp_id']);

header("Location: " . APP_URL . "/sup/login.php?msg=logged_out");
exit;
