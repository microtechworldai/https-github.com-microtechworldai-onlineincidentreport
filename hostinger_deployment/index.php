<?php
/**
 * Main Web Gateway & URL Router
 * Ultimate Security Services (USS) - sprinzo.in
 */
require_once __DIR__ . '/config.php';

// Check if install.lock exists; if not, direct to installer
if (!file_exists(__DIR__ . '/install.lock')) {
    header("Location: " . APP_URL . "/install.php");
    exit;
}

// If camp token provided in query, redirect to supervisor portal
if (!empty($_GET['camp']) || !empty($_GET['camp_token'])) {
    $c = $_GET['camp'] ?? $_GET['camp_token'];
    header("Location: " . APP_URL . "/sup/index.php?camp=" . urlencode($c));
    exit;
}

// If supervisor session active, route site tokens and sessions to supervisor portal
if (!empty($_SESSION['uss_supervisor_logged_in'])) {
    if (!empty($_GET['token']) || !empty($_GET['qr'])) {
        $t = $_GET['token'] ?? $_GET['qr'];
        header("Location: " . APP_URL . "/sup/index.php?token=" . urlencode($t));
        exit;
    }
    header("Location: " . APP_URL . "/sup/duty.php");
    exit;
}

// If token provided in query, redirect to guard checkpoint
if (!empty($_GET['token']) || !empty($_GET['qr'])) {
    $t = $_GET['token'] ?? $_GET['qr'];
    header("Location: " . APP_URL . "/g/index.php?token=" . urlencode($t));
    exit;
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// If admin session active, go straight to dashboard
if (!empty($_SESSION['uss_admin_logged_in'])) {
    header("Location: " . APP_URL . "/admin/dashboard.php");
    exit;
}

// If guard session active, go straight to submit
if (!empty($_SESSION['uss_guard_logged_in'])) {
    header("Location: " . APP_URL . "/g/submit.php");
    exit;
}

$settings = get_system_settings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($settings['company_name']) ?> | Online Incident Report Portal</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= file_exists(__DIR__ . '/assets/css/style.css') ? filemtime(__DIR__ . '/assets/css/style.css') : time() ?>">
    <script>
        (function() {
            var theme = localStorage.getItem('uss_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
        }
        .portal-card {
            max-width: 580px;
            width: 100%;
            background: var(--bg-card);
            backdrop-filter: blur(24px);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: 44px 36px;
            text-align: center;
            box-shadow: var(--shadow-card);
            position: relative;
        }
        .portal-logo {
            height: 58px;
            width: auto;
            margin-bottom: 16px;
        }
        .gateway-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 32px;
        }
        .gateway-box {
            background: var(--bg-card-hover);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 24px 18px;
            text-decoration: none;
            color: var(--text-main);
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        .gateway-box:hover {
            border-color: var(--accent-cyan);
            background: rgba(2, 132, 199, 0.12);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(2, 132, 199, 0.2);
        }
        .gateway-box svg {
            width: 36px;
            height: 36px;
            color: var(--accent-cyan);
        }
    </style>
</head>
<body>

<div class="portal-card">
    <div style="position: absolute; top: 18px; right: 18px;">
        <button type="button" class="theme-toggle-btn" onclick="openThemePaletteModal()" title="Choose Theme Palette" style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
            <span>🎨</span>
            <span id="activeThemeNameLabel">Theme</span>
        </button>
    </div>

    <img class="portal-logo" src="<?= APP_URL ?>/<?= htmlspecialchars($settings['logo_url']) ?>" alt="Logo" onerror="this.style.display='none'">
    <h1 style="font-size: 22px; font-weight: 800; color: var(--text-main); letter-spacing: -0.3px;">
        <?= htmlspecialchars($settings['company_name']) ?>
    </h1>
    <p style="font-size: 13.5px; color: var(--accent-cyan); font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px;">
        Patrol & Incident Operations Command
    </p>
    <p style="font-size: 13px; color: var(--text-muted); margin-top: 10px; line-height: 1.6;">
        Welcome to the centralized incident reporting platform. Please select your operational portal below to proceed.
    </p>

    <div class="gateway-grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
        <a href="<?= APP_URL ?>/g/index.php" class="gateway-box">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
            <strong style="font-size: 15px;">Guard Post</strong>
            <span style="font-size: 12px; color: var(--text-dim); text-align: center;">Report incident at verified checkpoint facility</span>
        </a>

        <a href="<?= APP_URL ?>/sup/login.php" class="gateway-box" style="border-color: rgba(16, 185, 129, 0.35);">
            <svg style="color: var(--accent-emerald);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
            <strong style="font-size: 15px;">Supervisor Visit</strong>
            <span style="font-size: 12px; color: var(--text-dim); text-align: center;">Camp & site QR patrol clocking & visit dossiers</span>
        </a>

        <a href="<?= APP_URL ?>/admin/login.php" class="gateway-box" style="border-color: rgba(99, 102, 241, 0.3);">
            <svg style="color: var(--accent-indigo);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            <strong style="font-size: 15px;">Super Admin</strong>
            <span style="font-size: 12px; color: var(--text-dim); text-align: center;">Command desk, daily dossiers, and audits</span>
        </a>
    </div>

    <div style="margin-top: 36px; border-top: 1px solid var(--border-subtle); padding-top: 16px; font-size: 12px; color: var(--text-dim);">
        Hostinger Production Deployment &bull; Subdomain: sprinzo.in
    </div>
</div>

<script>
function toggleAppTheme() {
    if (typeof openThemePaletteModal === 'function') {
        openThemePaletteModal();
    }
}
</script>
<?php require_once __DIR__ . '/includes/theme_picker.php'; ?>
</body>
</html>
