<?php
/**
 * Super Admin Layout Header
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

require_admin_auth();

$settings = get_system_settings();
$currentPage = basename($_SERVER['PHP_SELF']);
$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Super Admin');
$adminUsername = htmlspecialchars($_SESSION['admin_username'] ?? 'admin');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?><?= htmlspecialchars($settings['company_name']) ?> - Admin Portal</title>
    <link rel="icon" type="image/png" href="<?= APP_URL ?>/<?= htmlspecialchars($settings['logo_url']) ?>">
    <!-- USS Enterprise Design System Tokens & Fonts -->
    <link rel="preload" href="<?= APP_URL ?>/assets/fonts/noto-sans-variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= APP_URL ?>/assets/fonts/noto-sans-arabic-variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/design-tokens.css?v=<?= file_exists(__DIR__ . '/../assets/css/design-tokens.css') ? filemtime(__DIR__ . '/../assets/css/design-tokens.css') : time() ?>">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time() ?>">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time() ?>">
    <style>
        /* Defensive resets to prevent unstyled icon/layout explosions */
        svg { max-width: 100%; box-sizing: border-box; }
        .sidebar-brand img, .brand-logo-frame img { max-width: 44px; max-height: 44px; object-fit: contain; }
        .sidebar-nav svg, .nav-item svg { width: 20px; height: 20px; min-width: 20px; max-height: 20px; flex-shrink: 0; }
        .kpi-icon svg { width: 24px; height: 24px; }
    </style>
    <?php require_once __DIR__ . '/theme_bootstrapper.php'; ?>
</head>
<body>

<div class="app-container">

    <!-- Sidebar Navigation -->
    <aside class="app-sidebar" id="appSidebar">
        <a href="<?= APP_URL ?>/admin/dashboard.php" class="sidebar-brand" style="text-decoration: none;">
            <div class="brand-logo-frame">
                <img src="<?= APP_URL ?>/<?= htmlspecialchars($settings['logo_url']) ?>" alt="Logo" onerror="this.style.display='none'">
            </div>
            <div class="brand-title">
                <span class="brand-company" title="<?= htmlspecialchars($settings['company_name']) ?>"><?= htmlspecialchars($settings['company_name']) ?></span>
                <span class="brand-sub">Incident Control</span>
            </div>
        </a>

        <nav class="sidebar-nav">
            <span class="nav-section-title">Operations</span>
            
            <a href="<?= APP_URL ?>/admin/dashboard.php" class="nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                Dashboard
            </a>

            <a href="<?= APP_URL ?>/admin/incidents.php" class="nav-item <?= in_array($currentPage, ['incidents.php', 'incident_view.php', 'incident_edit.php']) ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Incident Reports
            </a>

            <a href="<?= APP_URL ?>/admin/daily_report.php" class="nav-item <?= $currentPage === 'daily_report.php' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg>
                Daily Incident Consolidated Report
            </a>

            <span class="nav-section-title">Field Management</span>

            <a href="<?= APP_URL ?>/admin/supervisor_board.php" class="nav-item <?= $currentPage === 'supervisor_board.php' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                <span>Live Supervisor Board</span>
                <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#10b981;box-shadow:0 0 6px #10b981;margin-left:auto;"></span>
            </a>

            <a href="<?= APP_URL ?>/admin/supervisor_visits.php" class="nav-item <?= in_array($currentPage, ['supervisor_visits.php', 'report_daily_supervisor_pdf.php', 'report_monthly_supervisor_pdf.php', 'report_site_monthly_pdf.php', 'report_supervisor_export_excel.php']) ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                Supervisor Site Visits
            </a>

            <a href="<?= APP_URL ?>/admin/supervisor_schedules.php" class="nav-item <?= $currentPage === 'supervisor_schedules.php' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Patrol Schedules
            </a>

            <?php
            $navPendingCorrections = 0;
            try {
                $db = get_db();
                $navPendingCorrections = (int)$db->query("SELECT COUNT(*) FROM supervisor_visits WHERE manual_timeout_status = 'Pending'")->fetchColumn();
            } catch (Exception $e) {}
            ?>
            <a href="<?= APP_URL ?>/admin/supervisor_corrections.php" class="nav-item <?= $currentPage === 'supervisor_corrections.php' ? 'active' : '' ?>" style="display: flex; align-items: center; justify-content: space-between;">
                <span style="display: flex; align-items: center; gap: 12px;">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Manual Corrections
                </span>
                <?php if ($navPendingCorrections > 0): ?>
                    <span style="background: #ef4444; color: #ffffff; font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 12px; margin-left: auto;">
                        <?= $navPendingCorrections ?>
                    </span>
                <?php endif; ?>
            </a>

            <a href="<?= APP_URL ?>/admin/camps.php" class="nav-item <?= in_array($currentPage, ['camps.php', 'camp_qr_poster.php']) ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                Camps & Checkpoints
            </a>

            <a href="<?= APP_URL ?>/admin/sites.php" class="nav-item <?= in_array($currentPage, ['sites.php', 'qr_poster.php']) ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                Sites & QR Posters
            </a>

            <!-- Attendance & Payroll Module -->
            <a href="<?= APP_URL ?>/attendance/" class="nav-item <?= (strpos($_SERVER['REQUEST_URI'] ?? '', '/attendance') !== false) ? 'active' : '' ?>" style="border-left: 3px solid #0a9396;">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <span>Attendance &amp; Payroll</span>
            </a>
            <a href="<?= APP_URL ?>/admin/guards.php" class="nav-item <?= $currentPage === 'guards.php' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                Security Staff (Guards)
            </a>

            <span class="nav-section-title">System & Security</span>

            <a href="<?= APP_URL ?>/admin/backups.php" class="nav-item <?= $currentPage === 'backups.php' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                Database & Backups
            </a>

            <a href="<?= APP_URL ?>/admin/settings.php" class="nav-item <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                Company & Settings
            </a>

            <a href="<?= APP_URL ?>/admin/audit_logs.php" class="nav-item <?= $currentPage === 'audit_logs.php' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                Audit Trail
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="admin-badge">
                <div class="admin-avatar"><?= strtoupper(substr($adminName, 0, 1)) ?></div>
                <div class="admin-meta">
                    <div class="name"><?= $adminName ?></div>
                    <div class="role">Super Administrator</div>
                </div>
            </div>
            <a href="<?= APP_URL ?>/admin/logout.php" title="Sign Out" style="color: #94a3b8; transition: color 0.2s;" onmouseover="this.style.color='#f43f5e'" onmouseout="this.style.color='#94a3b8'">
                <svg style="width:20px;height:20px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            </a>
        </div>
    </aside>

    <!-- Main Content Wrapper -->
    <main class="app-main">
        <header class="app-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="mobile-toggle" onclick="toggleSidebar()">
                    <svg style="width:24px;height:24px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <div class="header-title-box">
                    <h1><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Operations Dashboard' ?></h1>
                    <p><?= isset($pageSubtitle) ? htmlspecialchars($pageSubtitle) : 'Real-time Security Patrol Incident Monitoring' ?></p>
                </div>
            </div>

            <div class="header-actions">
                <button type="button" class="theme-toggle-btn" onclick="openThemePaletteModal()" title="Choose Interface Theme Palette" style="display: inline-flex; align-items: center; gap: 7px; cursor: pointer;">
                    <span>🎨</span>
                    <span id="activeThemeNameLabel">Theme</span>
                </button>

                <div class="uae-time-box" title="Current GST / Dubai Time">
                    <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;box-shadow:0 0 8px #10b981;"></span>
                    <span id="liveClock">GST --:--:--</span>
                </div>
                <a href="<?= APP_URL ?>/admin/change_password.php" class="btn btn-secondary btn-sm" title="Change Security Password">
                    🔑 Security
                </a>
            </div>
        </header>

        <div class="app-content">
