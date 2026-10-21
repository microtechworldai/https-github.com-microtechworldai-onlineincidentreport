<?php
/**
 * Security Guard Logout Handler
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

destroy_guard_session();
header("Location: " . APP_URL . "/g/index.php");
exit;
