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
<html lang="en" data-theme="dark">
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

<header class="att-topbar no-print">
    <div class="att-topbar-left">
        <div class="att-brand-block">
            <div class="att-logo-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>
            </div>
            <div>
                <div class="att-logo-badge">USS ROSTER &amp; PAYROLL</div>
                <div class="att-logo-sub">Enterprise Management</div>
            </div>
        </div>
        <nav class="att-nav-links">
            <a href="index.php?action=sheet" class="att-nav-btn <?= $activeTab === 'sheet' ? 'att-active' : '' ?>">
                <span class="att-tab-icon">📅</span> Attendance Roster
            </a>
            <a href="index.php?action=payroll" class="att-nav-btn <?= $activeTab === 'payroll' ? 'att-active' : '' ?>">
                <span class="att-tab-icon">💼</span> Consolidated Payroll
            </a>
        </nav>
    </div>
    <div class="att-topbar-right">
        <div class="att-user-chip">
            <span class="att-user-dot"></span>
            <span class="att-user-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Super Admin') ?></span>
        </div>
        <button type="button" onclick="openThemePaletteModal()" class="att-nav-btn att-theme-btn" title="Toggle Appearance Theme">
            🌓 Theme
        </button>
        <a href="../admin/dashboard.php" class="att-nav-btn att-back">
            <span>◂</span> Incident Admin
        </a>
    </div>
</header>
<?php 
if (file_exists(__DIR__ . '/../../../includes/theme_picker.php')) {
    require_once __DIR__ . '/../../../includes/theme_picker.php';
} elseif (file_exists(__DIR__ . '/../../includes/theme_picker.php')) {
    require_once __DIR__ . '/../../includes/theme_picker.php';
}
?>
