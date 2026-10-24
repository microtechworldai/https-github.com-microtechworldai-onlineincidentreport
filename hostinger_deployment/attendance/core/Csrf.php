<?php
/**
 * CSRF Protection Token Helper for Attendance Subsystem
 */

function att_generate_csrf() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['att_csrf_token'])) {
        $_SESSION['att_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['att_csrf_token'];
}

function att_get_csrf_token() {
    return att_generate_csrf();
}

function att_validate_csrf($token = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = $token ?: ($_POST['att_csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_ATT_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($token) || empty($_SESSION['att_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['att_csrf_token'], $token);
}

function att_verify_csrf_token($token = null) {
    return att_validate_csrf($token);
}
