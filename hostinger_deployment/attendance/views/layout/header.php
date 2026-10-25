<?php
// Attendance & Payroll Module - Main View Layout Header
// File: hostinger_deployment/attendance/views/layout/header.php

if (!defined('ATT_ACCESS')) {
    die('Direct access not permitted.');
}

$pageTitle = $pageTitle ?? 'Attendance & Payroll Module';
$activeTab = $activeTab ?? 'sheet';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(att_get_csrf_token()) ?>">
    <title><?= htmlspecialchars($pageTitle) ?> - Universal Security Services</title>
    <link rel="stylesheet" href="assets/css/att-style.css?v=<?= time() ?>">

    <!-- USS Enterprise Design System Fonts Preload (Zero Third-Party Requests) -->
    <link rel="preload" href="<?= APP_URL ?>/assets/fonts/noto-sans-variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= APP_URL ?>/assets/fonts/noto-sans-arabic-variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= APP_URL ?>/attendance/assets/css/design-tokens.css?v=<?= time() ?>">
    <?php 
if (file_exists(__DIR__ . '/../../../includes/theme_bootstrapper.php')) {
    require_once __DIR__ . '/../../../includes/theme_bootstrapper.php';
} elseif (file_exists(__DIR__ . '/../../includes/theme_bootstrapper.php')) {
    require_once __DIR__ . '/../../includes/theme_bootstrapper.php';
} 
?>
    
</head>
<body class="att-body">

<header class="att-topbar">
    <div class="att-topbar-left">
        <div class="att-logo-badge">USS ROSTER &amp; PAYROLL</div>
        <nav class="att-nav-links">
            <a href="index.php?action=sheet" class="att-nav-btn <?= $activeTab === 'sheet' ? 'att-active' : '' ?>">Attendance Roster</a>
            <a href="index.php?action=payroll" class="att-nav-btn <?= $activeTab === 'payroll' ? 'att-active' : '' ?>">Consolidated Payroll</a>
        </nav>
    </div>
    <div class="att-topbar-right">
        <span>Logged in as: <strong><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Super Admin') ?></strong></span>
        <a href="../admin/dashboard.php" class="att-nav-btn att-back">◂ Return to Incident Admin</a>
    </div>
</header>
