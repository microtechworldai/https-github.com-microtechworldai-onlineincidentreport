<?php
/**
 * One-Click Hostinger Database Configurator
 * Safely updates config.php with the verified working MySQL password
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$configFile = __DIR__ . '/config.php';
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbPort = 3306;
    $dbName = trim($_POST['db_name'] ?? 'u211560622_superadmin');
    $dbUser = trim($_POST['db_user'] ?? 'u211560622_superadmin');
    $dbPass = (string)($_POST['db_pass'] ?? '');

    try {
        // 1. Test live connection first
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]);

        // 2. If connected, update config.php safely
        if (file_exists($configFile)) {
            $content = file_get_contents($configFile);

            // Replace DB constants
            $content = preg_replace("/define\('DB_HOST', '.*?'\);/", "define('DB_HOST', '{$dbHost}');", $content);
            $content = preg_replace("/define\('DB_NAME', '.*?'\);/", "define('DB_NAME', '{$dbName}');", $content);
            $content = preg_replace("/define\('DB_USER', '.*?'\);/", "define('DB_USER', '{$dbUser}');", $content);
            $content = preg_replace("/define\('DB_PASS', '.*?'\);/", "define('DB_PASS', '" . addslashes($dbPass) . "');", $content);

            // Also update any placeholders
            $content = str_replace('YOUR_HOSTINGER_DATABASE_NAME', $dbName, $content);
            $content = str_replace('YOUR_HOSTINGER_DATABASE_USER', $dbUser, $content);
            $content = str_replace('YOUR_HOSTINGER_DATABASE_PASSWORD', addslashes($dbPass), $content);

            file_put_contents($configFile, $content);
        }

        // 3. Create install.lock so system is ready
        file_put_contents(__DIR__ . '/install.lock', 'Installed on ' . date('Y-m-d H:i:s'));

        $msg = "Connected successfully! Database configured and verified.";
        $msgType = "success";

    } catch (PDOException $e) {
        $msg = "Connection Failed: " . $e->getMessage();
        $msgType = "error";
    }
}

// Current values from config.php if readable
$curDb = 'u211560622_superadmin';
$curUser = 'u211560622_superadmin';
if (file_exists($configFile)) {
    $c = file_get_contents($configFile);
    if (preg_match("/define\('DB_NAME',\s*'([^']+)'\)/", $c, $m)) $curDb = $m[1];
    if (preg_match("/define\('DB_USER',\s*'([^']+)'\)/", $c, $m)) $curUser = $m[1];
}
if ($curDb === 'YOUR_HOSTINGER_DATABASE_NAME') $curDb = 'u211560622_superadmin';
if ($curUser === 'YOUR_HOSTINGER_DATABASE_USER') $curUser = 'u211560622_superadmin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Ultimate Security Services</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0f172a;
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 36px;
            max-width: 520px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        h2 {
            color: #38bdf8;
            margin-top: 0;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        p {
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.5;
        }
        .form-group {
            margin-bottom: 18px;
        }
        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: 6px;
        }
        input {
            width: 100%;
            padding: 12px 14px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            color: #f8fafc;
            font-size: 14px;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
        }
        input:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2);
        }
        .btn {
            display: inline-block;
            width: 100%;
            background: #2563eb;
            color: #fff;
            padding: 13px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 8px;
        }
        .btn:hover {
            background: #1d4ed8;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid #ef4444;
            color: #fca5a5;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid #22c55e;
            color: #86efac;
            padding: 14px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            text-align: center;
        }
        .btn-success {
            background: #16a34a;
            text-decoration: none;
            display: inline-block;
            margin-top: 12px;
            padding: 10px 20px;
            border-radius: 6px;
            color: #fff;
            font-weight: 700;
        }
    </style>
</head>
<body>

<div class="card">
    <h2>⚙️ Connect Database</h2>
    <p>Enter your Hostinger MySQL database password below to connect the portal.</p>

    <?php if ($msgType === 'error'): ?>
        <div class="alert-error">
            <strong>❌ Error:</strong> <?= htmlspecialchars($msg) ?>
        </div>
    <?php elseif ($msgType === 'success'): ?>
        <div class="alert-success">
            <strong>✅ <?= htmlspecialchars($msg) ?></strong>
            <div>
                <a href="admin/dashboard.php" class="btn-success">Open Dashboard &rarr;</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($msgType !== 'success'): ?>
    <form method="POST" action="">
        <div class="form-group">
            <label>Database Host</label>
            <input type="text" name="db_host" value="localhost" required>
        </div>

        <div class="form-group">
            <label>Database Name</label>
            <input type="text" name="db_name" value="<?= htmlspecialchars($curDb) ?>" required>
        </div>

        <div class="form-group">
            <label>Database Username</label>
            <input type="text" name="db_user" value="<?= htmlspecialchars($curUser) ?>" required>
        </div>

        <div class="form-group">
            <label>Database Password</label>
            <input type="password" name="db_pass" placeholder="Enter Hostinger database password" required autofocus>
        </div>

        <button type="submit" class="btn">Save &amp; Connect to Database &rarr;</button>
    </form>
    <?php endif; ?>
</div>

</body>
</html>
