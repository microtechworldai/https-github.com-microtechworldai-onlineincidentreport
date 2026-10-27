<?php
/**
 * Guard & Field Supervisor Patrol Entry & Authentication Portal
 * Validates Site QR token and logs in patrolling security officer or supervisor
 * Ultimate Security Services (USS)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$settings = get_system_settings();
$db = get_db();
$error = '';
$site = null;

// Handle unauthorized redirection message
if (isset($_GET['msg']) && $_GET['msg'] === 'unauthorized_site') {
    $error = "ACCESS DENIED: You are not authorized for that site location. You are strictly restricted to locations created and assigned by the Super Admin.";
}


// Retrieve QR token from URL parameter (?token=... or ?qr=...)
$token = trim($_GET['token'] ?? $_GET['qr'] ?? '');

// If token provided, look up site using permanent active_qr_token or unique site code
if (!empty($token)) {
    try {
        $site = find_site_by_qr_token($db, $token);
        if (!$site) {
            $error = "This QR code token is invalid, revoked, or the site is currently inactive. Please report to the Super Admin.";
        }
    } catch (Exception $e) {
        $error = "Unable to connect to checkpoint. Please retry or contact system administrator.";
    }
}

// Supervisor Checkpoint Router: If supervisor scans ANY site QR, take them directly to Supervisor Inspection & Time In/Out
if (!empty($_SESSION['uss_supervisor_logged_in']) && !empty($_SESSION['supervisor_id'])) {
    if ($site || !empty($token)) {
        $tokenParam = $token ?: ($site['active_qr_token'] ?? $site['code'] ?? '');
        header("Location: " . APP_URL . "/sup/index.php?token=" . urlencode($tokenParam));
        exit;
    } else {
        header("Location: " . APP_URL . "/sup/duty.php");
        exit;
    }
}

// Guard Instant Connect: If guard is already logged in on this phone and scans a QR poster
if (!empty($_SESSION['uss_guard_logged_in']) && !empty($_SESSION['guard_id'])) {
    if ($site) {
        // Enforce strict site authorization check:
        // A security guard is ONLY allowed to access sites assigned by Super Admin!
        $stmtAuth = $db->prepare("SELECT id FROM guard_site_assignments WHERE guard_id = ? AND site_id = ? LIMIT 1");
        $stmtAuth->execute([$_SESSION['guard_id'], $site['id']]);
        $isAssigned = (bool)$stmtAuth->fetch();

        if (!$isAssigned) {
            // Retrieve guard's authorized sites for high-clarity error feedback
            $stmtMySites = $db->prepare("
                SELECT s.name 
                FROM sites s 
                JOIN guard_site_assignments gsa ON gsa.site_id = s.id 
                WHERE gsa.guard_id = ? AND s.is_active = 1
            ");
            $stmtMySites->execute([$_SESSION['guard_id']]);
            $mySites = $stmtMySites->fetchAll(PDO::FETCH_COLUMN);
            $mySitesStr = !empty($mySites) ? implode(', ', $mySites) : 'None assigned';

            $error = "ACCESS DENIED: You are assigned to [" . htmlspecialchars($mySitesStr) . "]. "
                   . "You are NOT authorized to enter or scan checkpoints for '" . htmlspecialchars($site['name']) . "'. "
                   . "Security Officers are strictly restricted to locations created and assigned by the Super Admin.";
            log_audit('GUARD_UNAUTHORIZED_QR_SCAN_BLOCKED', 'SITE', (string)$site['id'], 
                "Guard " . ($_SESSION['guard_name'] ?? 'Guard') . " (ID {$_SESSION['guard_id']}) blocked from scanning unassigned site '{$site['name']}'", 
                'GUARD', $_SESSION['guard_id'], $_SESSION['guard_name'] ?? 'Guard');
        } else {
            $_SESSION['current_site_id'] = $site['id'];
            $_SESSION['current_site_code'] = $site['code'];
            $_SESSION['current_site_name'] = $site['name'];
            $_SESSION['current_site_lat'] = $site['latitude'] ?? '';
            $_SESSION['current_site_lng'] = $site['longitude'] ?? '';
            $_SESSION['guard_last_activity'] = time();
            header("Location: " . APP_URL . "/g/submit.php");
            exit;
        }
    } else {
        header("Location: " . APP_URL . "/g/submit.php");
        exit;
    }
}

// Handle Guard / Supervisor Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Please enter your Officer Staff ID / Username and Password.";
    } else {
        // Fetch Guard or Supervisor by Username or Staff ID
        $stmtGuard = $db->prepare("SELECT * FROM guards WHERE (LOWER(username) = ? OR UPPER(staff_id) = ?) AND is_active = 1 LIMIT 1");
        $stmtGuard->execute([$username, strtoupper($username)]);
        $guard = $stmtGuard->fetch();

        // Verify password with Argon2id + Pepper (with legacy fallback and whitespace resilience)
        $isValidPass = $guard && verify_password_argon2id($password, $guard['password_hash']);

        if (!$guard || !$isValidPass) {
            $error = "Invalid Officer credentials. Please verify your Staff ID / Username and Password.";
            log_audit('GUARD_LOGIN_FAILED', 'GUARD', null, ['username' => $username], 'GUARD');
        } else {
            if (!empty($guard['is_supervisor'])) {
                $_SESSION['uss_supervisor_logged_in'] = true;
                $_SESSION['supervisor_id'] = (int)$guard['id'];
                $_SESSION['supervisor_staff_id'] = $guard['staff_id'];
                $_SESSION['supervisor_name'] = $guard['full_name'];
                $_SESSION['supervisor_camp_id'] = $guard['assigned_camp_id'] ?? null;
                $_SESSION['supervisor_session_issued_at'] = time();
                $_SESSION['supervisor_last_activity'] = time();
                $db->prepare("UPDATE guards SET last_login_at = NOW() WHERE id = ?")->execute([$guard['id']]);
                log_audit('SUPERVISOR_LOGIN', 'SUPERVISOR', (string)$guard['id'], 'Authenticated from the checkpoint portal.', 'SUPERVISOR', $guard['id'], $guard['full_name']);
                if ($token !== '') {
                    header("Location: " . APP_URL . "/sup/index.php?token=" . urlencode($token));
                } else {
                    header("Location: " . APP_URL . "/sup/duty.php");
                }
                exit;
            }

            // Guard authenticated successfully!
            // Retrieve ALL assigned posts configured by Super Admin
            $stmtSites = $db->prepare("
                SELECT s.* 
                FROM sites s 
                INNER JOIN guard_site_assignments gsa ON gsa.site_id = s.id 
                WHERE gsa.guard_id = ? AND s.is_active = 1 
                ORDER BY gsa.id ASC
            ");
            $stmtSites->execute([$guard['id']]);
            $assignedSites = $stmtSites->fetchAll();
            $assignedIds = array_column($assignedSites, 'id');
            $assignedNames = array_column($assignedSites, 'name');
            $assignedStr = !empty($assignedNames) ? implode(', ', $assignedNames) : 'No sites assigned by Super Admin';

            $currentSite = null;

            if ($site) {
                // If scanned a checkpoint QR poster, strictly verify authorization
                if (!in_array((int)$site['id'], array_map('intval', $assignedIds), true)) {
                    $error = "ACCESS DENIED: Officer {$guard['full_name']} ({$guard['staff_id']}) is authorized ONLY for [" . htmlspecialchars($assignedStr) . "]. "
                           . "You are NOT allowed to enter or access '" . htmlspecialchars($site['name']) . "'. "
                           . "You are authorized to use Super Admin assigned locations only.";
                    log_audit('GUARD_UNAUTHORIZED_SITE_LOGIN_BLOCKED', 'SITE', (string)$site['id'], 
                        "Guard {$guard['username']} attempted login at unassigned site '{$site['name']}'", 
                        'GUARD', $guard['id'], $guard['full_name']);
                } else {
                    $currentSite = $site;
                }
            } else {
                // Guard accessed portal without scanning a specific QR code
                if (empty($assignedSites)) {
                    $error = "ACCESS DENIED: Officer {$guard['full_name']} has no assigned post. "
                           . "You must be assigned to an active site created by the Super Admin before accessing the patrol portal.";
                    log_audit('GUARD_NO_ASSIGNED_SITE', 'GUARD', (string)$guard['id'], 'Login blocked: no site assigned by Super Admin', 'GUARD', $guard['id'], $guard['full_name']);
                } else {
                    $currentSite = $assignedSites[0];
                }
            }

            if ($currentSite) {
                // Start Session
                $_SESSION['uss_guard_logged_in'] = true;
                $_SESSION['guard_id'] = $guard['id'];
                $_SESSION['guard_staff_id'] = $guard['staff_id'];
                $_SESSION['guard_name'] = $guard['full_name'];
                $_SESSION['guard_username'] = $guard['username'];
                $_SESSION['guard_designation'] = !empty($guard['is_supervisor']) ? 'Field Supervisor' : ($guard['designation'] ?: 'Security Officer');
                $_SESSION['current_site_id'] = $currentSite['id'];
                $_SESSION['current_site_code'] = $currentSite['code'];
                $_SESSION['current_site_name'] = $currentSite['name'];
                $_SESSION['current_site_lat'] = $currentSite['latitude'] ?? '';
                $_SESSION['current_site_lng'] = $currentSite['longitude'] ?? '';
                $_SESSION['guard_last_activity'] = time();

                // Update last login timestamp
                try {
                    $db->prepare("UPDATE guards SET last_login_at = NOW() WHERE id = ?")->execute([$guard['id']]);
                } catch (Exception $e) {}

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patrol Login | <?= htmlspecialchars($settings['company_name']) ?></title>
    
    <link rel="preload" href="<?= APP_URL ?>/assets/fonts/noto-sans-variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/design-tokens.css?v=<?= time() ?>">
    <?php require_once __DIR__ . '/../includes/theme_bootstrapper.php'; ?>
        
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
        <h3 style="font-size: 17px; font-weight: 700; color: #fff; margin-bottom: 6px; text-align: center;">
            👮 Security Guard &amp; 👔 Supervisor Sign In
        </h3>
        <p style="font-size: 12.5px; color: var(--g-text-muted); text-align: center; margin-bottom: 18px;">
            Enter Staff ID / Username to access the Online Incident Report Form
        </p>

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

        <div style="text-align: center; margin-top: 18px; padding-top: 14px; border-top: 1px dashed rgba(255,255,255,0.1);">
            <a href="<?= APP_URL ?>/sup/login.php<?= $token ? '?token=' . urlencode($token) : '' ?>" style="color: var(--g-accent, #38bdf8); font-size: 13px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <span>👔 Switch to Supervisor Mobile Patrol Shift Cockpit &rarr;</span>
            </a>
        </div>
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
