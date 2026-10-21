<?php
/**
 * Guard Patrol Entry & Authentication Portal
 * Validates Site QR token and logs in patrolling security officer
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$settings = get_system_settings();
$db = get_db();
$error = '';
$site = null;

// Retrieve QR token from URL parameter
$token = trim($_GET['token'] ?? '');

// If token provided, look up site using keyed HMAC hash
if (!empty($token)) {
    $tokenHmac = hash_qr_token($token);
    $stmt = $db->prepare("SELECT * FROM sites WHERE (token_hmac = ? OR active_qr_token = ?) AND is_active = 1 LIMIT 1");
    $stmt->execute([$tokenHmac, $token]);
    $site = $stmt->fetch();
    if ($site) {
        // Automatically populate HMAC and encrypted vault if not yet stored
        if (empty($site['token_hmac']) || empty($site['token_encrypted'])) {
            $db->prepare("UPDATE sites SET token_hmac = ?, token_encrypted = ? WHERE id = ?")->execute([
                $tokenHmac,
                encrypt_field($token),
                $site['id']
            ]);
        }
    } else {
        $error = "This QR code token is invalid, revoked, or the site is currently inactive. Please report to the Super Admin.";
    }
}

// INSTANT QR CONNECT: If guard is already authenticated on this phone and scans a QR poster, switch checkpoint and connect immediately with zero delay!
if (!empty($_SESSION['uss_guard_logged_in']) && !empty($_SESSION['guard_id'])) {
    if ($site) {
        $stmtAuth = $db->prepare("SELECT id FROM guard_site_assignments WHERE guard_id = ? AND site_id = ? LIMIT 1");
        $stmtAuth->execute([$_SESSION['guard_id'], $site['id']]);
        if (!$stmtAuth->fetch()) {
            $db->prepare("INSERT INTO guard_site_assignments (guard_id, site_id) VALUES (?, ?)")->execute([$_SESSION['guard_id'], $site['id']]);
        }
        $_SESSION['current_site_id'] = $site['id'];
        $_SESSION['current_site_code'] = $site['code'];
        $_SESSION['current_site_name'] = $site['name'];
        $_SESSION['guard_last_activity'] = time();
        header("Location: " . APP_URL . "/g/submit.php");
        exit;
    } else {
        header("Location: " . APP_URL . "/g/submit.php");
        exit;
    }
}

// Handle Guard Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Please enter your Officer Staff ID / Username and Password.";
    } else {
        // Fetch Guard by Username or Staff ID
        $stmtGuard = $db->prepare("SELECT * FROM guards WHERE (LOWER(username) = ? OR UPPER(staff_id) = ?) AND is_active = 1 LIMIT 1");
        $stmtGuard->execute([$username, strtoupper($username)]);
        $guard = $stmtGuard->fetch();

        // Verify password with Argon2id + Pepper (with legacy Bcrypt fallback and whitespace resilience)
        $isValidPass = $guard && verify_password_argon2id($password, $guard['password_hash']);

        if (!$guard || !$isValidPass) {
            $error = "Invalid Officer credentials. Please verify your Staff ID / Username and Password.";
            log_audit('GUARD_LOGIN_FAILED', 'GUARD', null, ['username' => $username], 'GUARD');
        } else {
            // Guard authenticated successfully!
            // Retrieve assigned post(s) configured by Admin
            $currentSite = null;

            if ($site) {
                // If guard scanned a checkpoint QR poster, grant instant checkpoint access and record assignment
                $stmtAuth = $db->prepare("SELECT id FROM guard_site_assignments WHERE guard_id = ? AND site_id = ? LIMIT 1");
                $stmtAuth->execute([$guard['id'], $site['id']]);
                if (!$stmtAuth->fetch()) {
                    $db->prepare("INSERT INTO guard_site_assignments (guard_id, site_id) VALUES (?, ?)")->execute([$guard['id'], $site['id']]);
                }
                $currentSite = $site;
            } else {
                // Look up assigned post from admin configuration
                $stmtSites = $db->prepare("
                    SELECT s.* 
                    FROM sites s 
                    INNER JOIN guard_site_assignments gsa ON gsa.site_id = s.id 
                    WHERE gsa.guard_id = ? AND s.is_active = 1 
                    ORDER BY gsa.id ASC
                ");
                $stmtSites->execute([$guard['id']]);
                $assignedSites = $stmtSites->fetchAll();

                if (empty($assignedSites)) {
                    // Auto-assign to first active site if no post assigned yet
                    $fallbackSite = $db->query("SELECT * FROM sites WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetch();
                    if ($fallbackSite) {
                        $db->prepare("INSERT INTO guard_site_assignments (guard_id, site_id) VALUES (?, ?)")->execute([$guard['id'], $fallbackSite['id']]);
                        $currentSite = $fallbackSite;
                    } else {
                        $error = "Access Denied: Officer {$guard['full_name']} has no assigned post. Please create a patrol site first in Admin.";
                        log_audit('GUARD_NO_ASSIGNED_SITE', 'GUARD', $guard['id'], 'No assigned site found in admin', 'GUARD', $guard['id'], $guard['full_name']);
                    }
                } else {
                    // Automatically take the assigned post configured by Admin
                    $currentSite = $assignedSites[0];
                }
            }

            if ($currentSite) {
                // Start Guard Session
                $_SESSION['uss_guard_logged_in'] = true;
                $_SESSION['guard_id'] = $guard['id'];
                $_SESSION['guard_staff_id'] = $guard['staff_id'];
                $_SESSION['guard_name'] = $guard['full_name'];
                $_SESSION['guard_username'] = $guard['username'];
                $_SESSION['guard_designation'] = $guard['designation'];
                $_SESSION['current_site_id'] = $currentSite['id'];
                $_SESSION['current_site_code'] = $currentSite['code'];
                $_SESSION['current_site_name'] = $currentSite['name'];
                $_SESSION['guard_last_activity'] = time();

                // Update last login timestamp
                $db->prepare("UPDATE guards SET last_login_at = NOW() WHERE id = ?")->execute([$guard['id']]);

                log_audit('GUARD_LOGIN', 'GUARD', $guard['id'], ['site_code' => $currentSite['code'], 'site_name' => $currentSite['name']], 'GUARD', $guard['id'], $guard['full_name']);

                header("Location: " . APP_URL . "/g/submit.php");
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Patrol Login | <?= htmlspecialchars($settings['company_name']) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/guard.css">
    <script>
        (function() {
            var theme = localStorage.getItem('uss_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <style>
        .login-wrap {
            max-width: 440px;
            margin: 40px auto 20px;
            padding: 0 16px;
            width: 100%;
        }
    </style>
</head>
<body class="guard-body">

<header class="guard-header">
    <div class="guard-logo-box">
        <img src="<?= APP_URL ?>/<?= htmlspecialchars($settings['logo_url']) ?>" alt="Logo" onerror="this.style.display='none'">
        <div class="guard-logo-text">
            <div class="title"><?= htmlspecialchars($settings['company_name']) ?></div>
            <div class="sub"><?= htmlspecialchars($settings['tagline'] ?: 'Patrol Incident Reporting') ?></div>
        </div>
    </div>
    <div>
        <button type="button" class="guard-theme-btn" onclick="openThemePaletteModal()" title="Choose Theme Palette" style="display: inline-flex; align-items: center; gap: 5px; font-weight: 700; font-size: 12px; padding: 6px 10px; border-radius: 20px; cursor: pointer;">
            <span>🎨</span>
            <span id="activeThemeNameLabel">Theme</span>
        </button>
    </div>
</header>

<div class="login-wrap">

    <?php if ($site): ?>
        <!-- Verified Checkpoint Banner -->
        <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 12px; padding: 16px; text-align: center; margin-bottom: 20px;">
            <div style="font-size: 11px; text-transform: uppercase; color: #34d399; font-weight: 700; letter-spacing: 0.5px;">
                ✓ Verified Patrol Checkpoint
            </div>
            <div style="font-size: 18px; font-weight: 800; color: #fff; margin-top: 4px;">
                <?= htmlspecialchars($site['name']) ?>
            </div>
            <div style="font-family: monospace; font-size: 13px; color: var(--g-accent); margin-top: 2px;">
                SITE CODE: <?= htmlspecialchars($site['code']) ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background: rgba(244, 63, 94, 0.15); border: 1px solid rgba(244, 63, 94, 0.3); color: #fb7185; padding: 14px; border-radius: 10px; font-size: 13.5px; margin-bottom: 20px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="section-card">
        <h3 style="font-size: 17px; font-weight: 700; color: #fff; margin-bottom: 16px; text-align: center;">
            👮 Security Officer Sign In
        </h3>

        <form method="POST" action="">
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13.5px; font-weight: 700; color: var(--g-text); margin-bottom: 8px;">Staff ID or Username</label>
                <input type="text" id="officerUsername" name="username" class="guard-input" placeholder="e.g. mohammed or USS-1001" required autocomplete="username" autofocus style="font-weight: 700; font-size: 15px;">
            </div>

            <div style="margin-bottom: 22px;">
                <label style="display: block; font-size: 13.5px; font-weight: 700; color: var(--g-text); margin-bottom: 8px;">Security Password</label>
                <div style="position: relative; display: flex; align-items: center;">
                    <input type="password" id="officerPassword" name="password" class="guard-input" placeholder="••••••••" required autocomplete="current-password" style="font-weight: 700; font-size: 15px; padding-right: 48px; width: 100%;">
                    <button type="button" id="togglePassBtn" onclick="togglePasswordVisibility()" style="position: absolute; right: 10px; background: none; border: none; font-size: 18px; cursor: pointer; color: var(--g-text-muted); padding: 4px; display: flex; align-items: center; justify-content: center;" title="Show/Hide Password" aria-label="Toggle password visibility">
                        👁️
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-guard-submit" style="width: 100%; font-size: 15.5px; font-weight: 800; padding: 13px;">
                Enter Incident Form &rarr;
            </button>
        </form>
    </div>

    <div style="text-align: center; margin-top: 24px; font-size: 12.5px; font-weight: 600; color: var(--g-text-muted);">
        <?= htmlspecialchars($settings['company_name']) ?> &bull; <?= htmlspecialchars($settings['tagline'] ?: 'Dubai Patrol Operations') ?>
    </div>
</div>

<script>
// Toggle Password Visibility (Eye button)
function togglePasswordVisibility() {
    const pInput = document.getElementById('officerPassword');
    const pBtn = document.getElementById('togglePassBtn');
    if (!pInput) return;
    if (pInput.type === 'password') {
        pInput.type = 'text';
        pBtn.textContent = '🙈';
    } else {
        pInput.type = 'password';
        pBtn.textContent = '👁️';
    }
}

// Prefill saved username for rapid login
(function() {
    try {
        const uField = document.getElementById('officerUsername');
        const saved = localStorage.getItem('uss_saved_username');
        if (saved && uField && !uField.value) {
            uField.value = saved;
        }
    } catch(e) {}
})();

document.querySelector('form')?.addEventListener('submit', function() {
    try {
        const u = document.getElementById('officerUsername')?.value;
        if (u) localStorage.setItem('uss_saved_username', u.trim());
    } catch(e) {}
});

function toggleGuardTheme() {
    if (typeof openThemePaletteModal === 'function') {
        openThemePaletteModal();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/theme_picker.php'; ?>
</body>
</html>
