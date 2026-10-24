<?php
// Attendance & Payroll Module - Main Entry Point (Front Controller)
// File: hostinger_deployment/attendance/index.php

// Define Module Access Constant
define('ATT_ACCESS', true);

// 1. Module Configuration & Kill-Switch
require_once __DIR__ . '/config/config.php';

// 2. Strict Authentication Check (Option A: Read-Only Incident Super Admin Session Check)
require_once __DIR__ . '/core/AuthCheck.php';
att_require_admin_auth();

// 3. Dedicated Database Connection (uss_attendance)
require_once __DIR__ . '/config/database.php';
$pdo = att_get_db();

// 4. CSRF Protection & Audit Helpers
require_once __DIR__ . '/core/Csrf.php';
require_once __DIR__ . '/core/Audit.php';

// 5. Load Domain Models
require_once __DIR__ . '/models/SiteDirectory.php';
require_once __DIR__ . '/models/StaffDirectory.php';
require_once __DIR__ . '/models/AttendanceSheet.php';
require_once __DIR__ . '/models/PayrollEngine.php';

// 6. Dispatch Action via Router
require_once __DIR__ . '/core/Router.php';
$router = new Router($pdo);
$router->dispatch();
