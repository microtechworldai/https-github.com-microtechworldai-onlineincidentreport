<?php
/**
 * 1-Click Production Web Installer for Hostinger
 * Automates MySQL table creation, config.php setup, and Super Admin seeding
 */

// Check if already installed
$lockFile = __DIR__ . '/install.lock';
$isReconfiguring = !empty($_GET['reconfigure']) || !empty($_GET['force']);
$alreadyInstalled = file_exists($lockFile) && !$isReconfiguring;

$step = $_GET['step'] ?? ($alreadyInstalled ? 'locked' : 'wizard');
$error = '';
$success = '';

// Pre-flight checks
$checks = [
    'PHP Version (>= 7.4)' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO MySQL Extension'  => extension_loaded('pdo') && extension_loaded('pdo_mysql'),
    'GD Image Library'     => extension_loaded('gd'),
    'Uploads Directory Writable' => is_writable(__DIR__ . '/uploads'),
    'Backups Directory Writable' => is_writable(__DIR__ . '/backups'),
];
$allChecksPassed = !in_array(false, $checks, true);

// Handle Installation Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyInstalled) {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbPort = (int)($_POST['db_port'] ?? 3306);
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $appUrl = rtrim(trim($_POST['app_url'] ?? ''), '/');
    
    $adminUser = trim($_POST['admin_username'] ?? 'superadmin');
    $adminPass = (string)($_POST['admin_password'] ?? '');
    $adminName = trim($_POST['admin_name'] ?? 'Super Administrator');
    $adminEmail = trim($_POST['admin_email'] ?? 'admin@sprinzo.in');

    if (empty($dbName) || empty($dbUser) || empty($adminUser) || empty($adminPass)) {
        $error = "Please fill in all database and administrative account fields.";
    } else {
        try {
            // 1. Test Connection
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);

            // 2. Read database.sql and execute
            $sqlFile = __DIR__ . '/database.sql';
            if (file_exists($sqlFile)) {
                $sqlContent = file_get_contents($sqlFile);
                $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 1);
                $pdo->exec($sqlContent);
                $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
            }

            // 3. Insert or Update Super Admin with exact password
            $adminHash = password_hash($adminPass, PASSWORD_BCRYPT);
            $stmtAdmin = $pdo->prepare("
                INSERT INTO admins (id, username, password_hash, full_name, email, must_change_password, created_at)
                VALUES (1, ?, ?, ?, ?, 0, NOW())
                ON DUPLICATE KEY UPDATE 
                    username = VALUES(username),
                    password_hash = VALUES(password_hash),
                    full_name = VALUES(full_name),
                    email = VALUES(email),
                    must_change_password = 0
            ");
            $stmtAdmin->execute([$adminUser, $adminHash, $adminName, $adminEmail]);

            // 4. Update config.php with these credentials
            $configTemplate = file_get_contents(__DIR__ . '/config.php');
            $configTemplate = preg_replace("/define\('DB_HOST', '.*?'\);/", "define('DB_HOST', '{$dbHost}');", $configTemplate);
            $configTemplate = preg_replace("/define\('DB_PORT', \d+\);/", "define('DB_PORT', {$dbPort});", $configTemplate);
            $configTemplate = preg_replace("/define\('DB_NAME', '.*?'\);/", "define('DB_NAME', '{$dbName}');", $configTemplate);
            $configTemplate = preg_replace("/define\('DB_USER', '.*?'\);/", "define('DB_USER', '{$dbUser}');", $configTemplate);
            $configTemplate = preg_replace("/define\('DB_PASS', '.*?'\);/", "define('DB_PASS', '" . addslashes($dbPass) . "');", $configTemplate);
            if (!empty($appUrl)) {
                $configTemplate = preg_replace("/define\('APP_URL_OVERRIDE', '.*?'\);/", "define('APP_URL_OVERRIDE', '{$appUrl}');", $configTemplate);
            }
            file_put_contents(__DIR__ . '/config.php', $configTemplate);

            // 5. Create lock file
            file_put_contents($lockFile, "Installed on " . date('Y-m-d H:i:s'));

            $success = "Installation completed successfully! You can now log into your Super Admin Portal.";
            $step = 'done';

        } catch (PDOException $e) {
            $error = "Database Connection Error: " . $e->getMessage() . ". Please check that the database and user exist in Hostinger hPanel.";
        } catch (Exception $e) {
            $error = "Error during setup: " . $e->getMessage();
        }
    }
}

// Auto-detect current domain URL
$autoUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://") . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF']);
$autoUrl = rtrim(str_replace('\\', '/', $autoUrl), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USS Incident Reporting System - 1-Click Hostinger Installer</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 30px 16px;
        }
        .wizard-card {
            max-width: 680px;
            width: 100%;
            background: rgba(17, 24, 39, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: 36px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.7);
        }
    </style>
</head>
<body>

<div class="wizard-card">
    <div style="text-align: center; margin-bottom: 28px;">
        <h1 style="font-size: 24px; font-weight: 800; color: #fff;">🚀 Hostinger 1-Click Setup Wizard</h1>
        <p style="font-size: 13.5px; color: var(--accent-cyan); margin-top: 4px; font-weight: 600;">
            Ultimate Security Services (USS) &bull; sprinzo.in
        </p>
    </div>

    <?php if ($step === 'locked'): ?>
        <div class="alert alert-info" style="font-size: 14px; line-height: 1.6;">
            🔒 <strong>System Already Installed:</strong> The application is already installed and protected by <code>install.lock</code>.
            <div style="margin-top: 14px;">
                <a href="admin/login.php" class="btn btn-primary">Go to Super Admin Sign In &rarr;</a>
                <a href="install.php?reconfigure=1" class="btn btn-secondary" style="margin-left: 10px; background: rgba(255,255,255,0.12); color: #fff; border: 1px solid rgba(255,255,255,0.3); padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600;">⚙️ Update Database Password &rarr;</a>
            </div>
        </div>
    <?php elseif ($step === 'done'): ?>
        <div class="alert alert-success" style="font-size: 14px; line-height: 1.6;">
            🎉 <strong><?= htmlspecialchars($success) ?></strong>
            <p style="margin-top: 8px;">All MySQL tables have been created, seed data loaded, and your Super Admin account initialized.</p>
            <div style="margin-top: 18px;">
                <a href="admin/login.php" class="btn btn-primary" style="padding: 12px 24px; font-size: 15px;">
                    Open Admin Sign In Portal &rarr;
                </a>
            </div>
        </div>
    <?php else: ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Server Checks -->
        <div style="background: rgba(15, 23, 42, 0.8); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 14px; margin-bottom: 24px;">
            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-dim); margin-bottom: 8px;">
                Server Pre-Flight Compatibility Checks
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12.5px;">
                <?php foreach ($checks as $title => $passed): ?>
                    <div style="display: flex; align-items: center; gap: 6px; color: <?= $passed ? '#34d399' : '#fb7185' ?>;">
                        <span><?= $passed ? '✓' : '✗' ?></span>
                        <span style="color: <?= $passed ? '#cbd5e1' : '#fb7185' ?>;"><?= $title ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <form method="POST" action="">

            <!-- Database Section -->
            <h3 style="font-size: 15px; font-weight: 700; color: #fff; margin-bottom: 12px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 6px;">
                1. Hostinger MySQL Database Credentials
            </h3>

            <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Database Host</label>
                    <input type="text" name="db_host" class="form-control" value="localhost" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Port</label>
                    <input type="number" name="db_port" class="form-control" value="3306" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Database Name</label>
                    <input type="text" name="db_name" class="form-control" value="<?= htmlspecialchars(defined('DB_NAME') && DB_NAME !== 'uss_incidents' ? DB_NAME : 'u211560622_superadmin') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Database User</label>
                    <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars(defined('DB_USER') && DB_USER !== 'root' ? DB_USER : 'u211560622_superadmin') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Database Password</label>
                <input type="password" name="db_pass" class="form-control" placeholder="Your Hostinger MySQL password" required>
            </div>

            <div class="form-group">
                <label class="form-label">Application Subdomain URL</label>
                <input type="text" name="app_url" class="form-control" value="<?= htmlspecialchars($autoUrl) ?>" placeholder="https://incidents.sprinzo.in" required>
            </div>

            <!-- Admin Section -->
            <h3 style="font-size: 15px; font-weight: 700; color: #fff; margin: 20px 0 12px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 6px;">
                2. Super Admin Credentials
            </h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Admin Username</label>
                    <input type="text" name="admin_username" class="form-control" value="superadmin" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Admin Password</label>
                    <input type="text" name="admin_password" class="form-control" value="Admin@Sprinzo2026!" required minlength="8">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Administrator Full Name</label>
                    <input type="text" name="admin_name" class="form-control" value="USS Chief Administrator" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Admin Email</label>
                    <input type="email" name="admin_email" class="form-control" value="admin@sprinzo.in" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 16px; margin-top: 14px;">
                Install Database &amp; Initialize System &rarr;
            </button>
        </form>

    <?php endif; ?>
</div>

</body>
</html>
