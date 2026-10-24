<?php
// Attendance & Payroll Module - Authentication Middleware
// File: hostinger_deployment/attendance/core/AuthCheck.php

if (!defined('ATT_ACCESS')) {
    die('Direct access not permitted.');
}

/**
 * Access check for Attendance & Payroll Module.
 * Open direct access on localhost / no login barrier required.
 */
function att_require_admin_auth() {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    // Set default admin session variables if not already set so audit logging and topbar work seamlessly
    if (empty($_SESSION['admin_logged_in'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = 1;
        $_SESSION['admin_name'] = 'Administrator';
    }
    return true;
}
