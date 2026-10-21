<?php
/**
 * Change Super Admin Password
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Check admin auth without redirect loop
if (empty($_SESSION['uss_admin_logged_in']) || empty($_SESSION['admin_id'])) {
    header("Location: " . APP_URL . "/admin/login.php");
    exit;
}

$pageTitle = "Security & Password";
$pageSubtitle = "Update administrative credentials";
$settings = get_system_settings();

$error = '';
$success = '';
$isForced = !empty($_GET['forced']) || !empty($_SESSION['must_change_password']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPass = (string)($_POST['current_password'] ?? '');
    $newPass = (string)($_POST['new_password'] ?? '');
    $confirmPass = (string)($_POST['confirm_password'] ?? '');

    if (empty($currentPass) || empty($newPass)) {
        $error = 'All fields are required.';
    } elseif ($newPass !== $confirmPass) {
        $error = 'New password and confirmation do not match.';
    } elseif (strlen($newPass) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } else {
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM admins WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($currentPass, $admin['password_hash'])) {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $updateStmt = $db->prepare("UPDATE admins SET password_hash = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?");
            $updateStmt->execute([$newHash, $admin['id']]);

            $_SESSION['must_change_password'] = 0;
            log_audit('PASSWORD_CHANGED', 'ADMIN', $admin['id'], 'Admin updated password');
            $success = 'Password successfully updated! Your account is now secured.';
            $isForced = false;
        } else {
            $error = 'Current password is incorrect.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 600px; margin: 0 auto;">
    <?php if ($isForced): ?>
        <div class="alert alert-danger">
            <strong>⚠️ Security Requirement:</strong> This is your first administrative sign-in or a forced security update. You must set a new strong password before accessing the dashboard.
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
            <div style="margin-top: 10px;">
                <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-primary btn-sm">Go to Dashboard &rarr;</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">Change Super Admin Password</h3>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label" for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password">
            </div>

            <div class="form-group">
                <label class="form-label" for="new_password">New Password (Min 8 characters)</label>
                <input type="password" id="new_password" name="new_password" class="form-control" minlength="8" required autocomplete="new-password">
            </div>

            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="8" required autocomplete="new-password">
            </div>

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <button type="submit" class="btn btn-primary">Update Password</button>
                <?php if (!$isForced): ?>
                    <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
