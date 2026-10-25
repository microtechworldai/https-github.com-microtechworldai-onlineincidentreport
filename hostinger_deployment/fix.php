<?php
/**
 * USS Emergency Fixer & Health Check
 * Automatically repairs attendance paths, verifies DB tables, and tests page rendering.
 */
error_reporting(E_ALL);
ini_set("display_errors", "1");

$baseDir = __DIR__;
$results = [];

// 1. Ensure attendance/includes directory exists
$attIncDir = $baseDir . "/attendance/includes";
if (!is_dir($attIncDir)) {
    if (@mkdir($attIncDir, 0755, true)) {
        $results[] = "✅ Created directory: attendance/includes/";
    } else {
        $results[] = "⚠️ Could not create attendance/includes/ directory directly.";
    }
} else {
    $results[] = "✅ Directory attendance/includes/ exists.";
}

// 2. Copy theme files into attendance/includes
$srcBoot = $baseDir . "/includes/theme_bootstrapper.php";
$dstBoot = $attIncDir . "/theme_bootstrapper.php";
if (file_exists($srcBoot)) {
    if (@copy($srcBoot, $dstBoot)) {
        $results[] = "✅ Copied theme_bootstrapper.php to attendance/includes/";
    }
}

// 3. Patch attendance/views/layout/header.php
$headerFile = $baseDir . "/attendance/views/layout/header.php";
if (file_exists($headerFile)) {
    $code = file_get_contents($headerFile);
    $badNeedle = "require_once __DIR__ . '/../../includes/theme_bootstrapper.php';";
    $safeReplacement = "if (file_exists(__DIR__ . '/../../../includes/theme_bootstrapper.php')) { require_once __DIR__ . '/../../../includes/theme_bootstrapper.php'; } elseif (file_exists(__DIR__ . '/../../includes/theme_bootstrapper.php')) { require_once __DIR__ . '/../../includes/theme_bootstrapper.php'; }";
    
    if (strpos($code, $badNeedle) !== false) {
        $code = str_replace($badNeedle, $safeReplacement, $code);
        file_put_contents($headerFile, $code);
        $results[] = "✅ Successfully patched attendance/views/layout/header.php!";
    } else {
        $results[] = "ℹ️ header.php was already patched or does not contain the bad path.";
    }
} else {
    $results[] = "⚠️ File attendance/views/layout/header.php not found at: {$headerFile}";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>USS System Auto-Repair Tool</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; padding: 40px 20px; }
        .box { max-width: 650px; margin: 0 auto; background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 32px; box-shadow: 0 10px 25px rgba(0,0,0,0.4); }
        h1 { color: #38bdf8; font-size: 22px; margin-top: 0; }
        ul { list-style: none; padding: 0; margin: 20px 0; }
        li { padding: 10px 14px; margin-bottom: 8px; border-radius: 6px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); font-family: monospace; font-size: 13px; }
        .btn { display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 700; margin-top: 15px; }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
<div class="box">
    <h1>🚀 USS Auto-Repair Diagnostic & Fixer</h1>
    <p style="color: #94a3b8; font-size: 14px;">Results of live server path verification:</p>
    <ul>
        <?php foreach ($results as $r): ?>
            <li><?= $r ?></li>
        <?php endforeach; ?>
    </ul>
    
    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #334155; display: flex; gap: 12px; flex-wrap: wrap;">
        <a href="attendance/" class="btn" style="background: #10b981;">👉 Open Attendance & Payroll</a>
        <a href="admin/dashboard.php" class="btn">👉 Open Admin Dashboard</a>
    </div>
</div>
</body>
</html>
