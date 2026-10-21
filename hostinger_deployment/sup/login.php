<?php
/**
 * Supervisor Mobile Patrol Login
 * Ultimate Security Services (USS)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// If already logged in, redirect to active duty or scanner
if (!empty($_SESSION['uss_supervisor_logged_in'])) {
    $redirect = $_GET['redirect'] ?? '';
    if ($redirect && strpos($redirect, 'http') === false) {
        header("Location: " . APP_URL . "/sup/" . ltrim($redirect, '/'));
    } else {
        header("Location: " . APP_URL . "/sup/duty.php");
    }
    exit;
}

$db = get_db();
$settings = get_system_settings();
$error = '';

$pendingCamp = $_GET['camp'] ?? '';
$pendingToken = $_GET['token'] ?? $_GET['qr'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (empty($identifier) || empty($password)) {
        $error = "Please enter your Staff ID / Username and Password.";
    } else {
        $stmt = $db->prepare("
            SELECT * FROM guards 
            WHERE (staff_id = ? OR username = ?) AND is_active = 1 
            LIMIT 1
        ");
        $stmt->execute([$identifier, $identifier]);
        $guard = $stmt->fetch();

        if ($guard && verify_password($password, $guard['password_hash'])) {
            if (empty($guard['is_supervisor'])) {
                $error = "This account is not authorized as a Field Supervisor. Contact Super Admin.";
            } else {
                // Initialize Supervisor Session
                $_SESSION['uss_supervisor_logged_in'] = true;
                $_SESSION['supervisor_id'] = $guard['id'];
                $_SESSION['supervisor_staff_id'] = $guard['staff_id'];
                $_SESSION['supervisor_name'] = $guard['full_name'];
                $_SESSION['supervisor_camp_id'] = $guard['assigned_camp_id'];

                $db->prepare("UPDATE guards SET last_login_at = NOW() WHERE id = ?")->execute([$guard['id']]);
                log_audit('SUPERVISOR_LOGIN', "Supervisor logged in ({$guard['staff_id']} - {$guard['full_name']})", null, 'guards', $guard['id']);

                if ($pendingCamp) {
                    header("Location: " . APP_URL . "/sup/index.php?camp=" . urlencode($pendingCamp));
                } elseif ($pendingToken) {
                    header("Location: " . APP_URL . "/sup/index.php?token=" . urlencode($pendingToken));
                } else {
                    header("Location: " . APP_URL . "/sup/duty.php");
                }
                exit;
            }
        } else {
            $error = "Invalid credentials. Please verify your Staff ID / Password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Supervisor Login | <?= htmlspecialchars($settings['company_name']) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= time() ?>">
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
            padding: 16px;
            background: var(--bg-main);
        }
        .login-card {
            max-width: 420px;
            width: 100%;
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: 32px 24px;
            box-shadow: var(--shadow-card);
        }
        .brand-header {
            text-align: center;
            margin-bottom: 24px;
        }
        .brand-header img {
            height: 48px;
            margin-bottom: 12px;
        }
        .brand-title {
            font-size: 19px;
            font-weight: 800;
            color: var(--text-main);
        }
        .brand-subtitle {
            font-size: 12px;
            font-weight: 700;
            color: var(--accent-emerald);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 4px;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="brand-header">
        <img src="<?= APP_URL ?>/<?= htmlspecialchars($settings['logo_url']) ?>" alt="Logo" onerror="this.style.display='none'">
        <div class="brand-title"><?= htmlspecialchars($settings['company_name']) ?></div>
        <div class="brand-subtitle">Supervisor Patrol Operations</div>
    </div>

    <?php if ($pendingCamp || $pendingToken): ?>
        <div class="alert alert-info" style="margin-bottom: 16px; font-size: 12.5px; padding: 10px 14px;">
            QR checkpoint scanned. Log in to register your timestamp.
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom: 16px; font-size: 13px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group" style="margin-bottom: 16px;">
            <label class="form-label">Staff ID or Username</label>
            <input type="text" name="identifier" class="form-control" placeholder="e.g. SG1421" required autofocus autocapitalize="none">
        </div>

        <div class="form-group" style="margin-bottom: 24px;">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 15px; font-weight: 700;">
            Log In & Start Patrol
        </button>
    </form>

    <div style="text-align: center; margin-top: 24px; border-top: 1px solid var(--border-subtle); padding-top: 14px; font-size: 12px; color: var(--text-dim);">
        <a href="<?= APP_URL ?>/index.php" style="color: var(--text-muted); text-decoration: none;">&larr; Return to Main Portal</a>
    </div>
</div>

</body>
</html>
