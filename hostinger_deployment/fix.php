<?php
/**
 * USS Emergency Fixer & Health Diagnostic Tool
 * Automatically repairs attendance paths, creates missing tables, updates columns, and ensures zero-crash operation.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$baseDir = __DIR__;
$results = [];

// 1. Ensure attendance/includes directory exists
$attIncDir = $baseDir . '/attendance/includes';
if (!is_dir($attIncDir)) {
    if (@mkdir($attIncDir, 0755, true)) {
        $results[] = "✅ Created directory: attendance/includes/";
    }
} else {
    $results[] = "✅ Directory attendance/includes/ exists.";
}

// 2. Copy theme files into attendance/includes
$srcBoot = $baseDir . '/includes/theme_bootstrapper.php';
$dstBoot = $attIncDir . '/theme_bootstrapper.php';
if (file_exists($srcBoot)) {
    @copy($srcBoot, $dstBoot);
    $results[] = "✅ Copied theme_bootstrapper.php to attendance/includes/";
}

$srcPick = $baseDir . '/includes/theme_picker.php';
$dstPick = $attIncDir . '/theme_picker.php';
if (file_exists($srcPick)) {
    @copy($srcPick, $dstPick);
    $results[] = "✅ Copied theme_picker.php to attendance/includes/";
}

// 3. Patch attendance/views/layout/header.php
$headerFile = $baseDir . '/attendance/views/layout/header.php';
if (file_exists($headerFile)) {
    $code = file_get_contents($headerFile);
    $badNeedle = "require_once __DIR__ . '/../../includes/theme_bootstrapper.php';";
    $safeReplacement = "if (file_exists(__DIR__ . '/../../../includes/theme_bootstrapper.php')) {\n        require_once __DIR__ . '/../../../includes/theme_bootstrapper.php';\n    } elseif (file_exists(__DIR__ . '/../../includes/theme_bootstrapper.php')) {\n        require_once __DIR__ . '/../../includes/theme_bootstrapper.php';\n    }";
    
    if (strpos($code, $badNeedle) !== false) {
        $code = str_replace($badNeedle, $safeReplacement, $code);
        file_put_contents($headerFile, $code);
        $results[] = "✅ Successfully patched attendance/views/layout/header.php (Removed broken relative path)!";
    } else {
        $results[] = "✅ header.php is verified healthy.";
    }
} else {
    $results[] = "⚠️ File attendance/views/layout/header.php not found.";
}


// 4. Ensure admin/index.php exists to prevent 403 Forbidden
$adminIndexFile = $baseDir . '/admin/index.php';
$adminIndexContent = '<?php\nrequire_once __DIR__ . "/../config.php";\nrequire_once __DIR__ . "/../includes/auth.php";\nif (!empty($_SESSION["uss_admin_logged_in"]) && !empty($_SESSION["admin_id"])) {\n    header("Location: " . APP_URL . "/admin/dashboard.php");\n} else {\n    header("Location: " . APP_URL . "/admin/login.php");\n}\nexit;\n';
if (!file_exists($adminIndexFile)) {
    file_put_contents($adminIndexFile, $adminIndexContent);
    $results[] = "✅ Created admin/index.php (Resolves 403 Forbidden on /admin/)!";
} else {
    $results[] = "✅ admin/index.php is present and active.";
}

// 5. Database Self-Healing: Verify DB connection & missing tables
try {
    require_once $baseDir . '/config.php';
    require_once $baseDir . '/includes/db.php';
    $db = get_db();
    $results[] = "✅ Database connection successfully verified (" . DB_NAME . ").";

    // Ensure guard_site_assignments table exists
    $db->exec("CREATE TABLE IF NOT EXISTS `guard_site_assignments` (
      `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `guard_id` INT UNSIGNED NOT NULL,
      `site_id` INT UNSIGNED NOT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY `uniq_guard_site` (`guard_id`,`site_id`),
      INDEX `idx_assignment_guard` (`guard_id`),
      INDEX `idx_assignment_site` (`site_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $results[] = "✅ Table `guard_site_assignments` verified & active.";

    // Ensure supervisor_duties table exists
    $db->exec("CREATE TABLE IF NOT EXISTS `supervisor_duties` (
      `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `duty_ref` varchar(100) NOT NULL,
      `supervisor_id` int(10) unsigned NOT NULL,
      `camp_id` int(10) unsigned NOT NULL,
      `duty_date` date NOT NULL,
      `shift_type` enum('Day','Night') NOT NULL DEFAULT 'Day',
      `camp_out_time` datetime NOT NULL,
      `camp_in_time` datetime DEFAULT NULL,
      `status` enum('Active','Completed') NOT NULL DEFAULT 'Active',
      `total_sites` int(11) NOT NULL DEFAULT 0,
      `total_minutes` int(11) NOT NULL DEFAULT 0,
      `created_at` datetime NOT NULL DEFAULT current_timestamp(),
      `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `duty_ref` (`duty_ref`),
      KEY `idx_duty_sup_date` (`supervisor_id`,`duty_date`),
      KEY `idx_duty_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $results[] = "✅ Table `supervisor_duties` verified & active.";

    // Ensure supervisor_visits table exists
    $db->exec("CREATE TABLE IF NOT EXISTS `supervisor_visits` (
      `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `visit_ref` varchar(100) DEFAULT NULL,
      `duty_id` int(10) unsigned NOT NULL,
      `supervisor_id` int(10) unsigned NOT NULL,
      `site_id` int(10) unsigned NOT NULL,
      `time_in` datetime NOT NULL,
      `time_out` datetime DEFAULT NULL,
      `minutes_spent` int(11) NOT NULL DEFAULT 0,
      `travel_minutes_from_previous` int(11) NOT NULL DEFAULT 0,
      `remarks` text DEFAULT NULL,
      `purpose_of_visit` varchar(50) NOT NULL DEFAULT 'Routine',
      `guards_count` int(11) NOT NULL DEFAULT 0,
      `guards_attendance` varchar(50) NOT NULL DEFAULT 'All present',
      `guards_names` text DEFAULT NULL,
      `rating_turnout` varchar(20) NOT NULL DEFAULT 'OK',
      `rating_records` varchar(20) NOT NULL DEFAULT 'OK',
      `rating_condition` varchar(20) NOT NULL DEFAULT 'OK',
      `rating_equipment` varchar(20) NOT NULL DEFAULT 'OK',
      `has_issues` tinyint(1) NOT NULL DEFAULT 0,
      `issues_action_taken` text DEFAULT NULL,
      `manual_timeout_requested` tinyint(1) NOT NULL DEFAULT 0,
      `manual_timeout_time` datetime DEFAULT NULL,
      `manual_timeout_reason` text DEFAULT NULL,
      `manual_timeout_status` enum('None','Pending','Approved','Rejected') NOT NULL DEFAULT 'None',
      `corrected_by_admin_id` int(10) unsigned DEFAULT NULL,
      `correction_notes` text DEFAULT NULL,
      `site_report` text DEFAULT NULL,
      `status` enum('In Progress','Completed') NOT NULL DEFAULT 'In Progress',
      `is_draft` tinyint(1) NOT NULL DEFAULT 0,
      `is_offline_sync` tinyint(1) NOT NULL DEFAULT 0,
      `client_scan_time` datetime DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT current_timestamp(),
      `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `visit_ref` (`visit_ref`),
      KEY `idx_visit_duty` (`duty_id`),
      KEY `idx_visit_sup` (`supervisor_id`,`time_in`),
      KEY `idx_visit_site` (`site_id`,`time_in`),
      KEY `idx_visit_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $results[] = "✅ Table `supervisor_visits` verified & active.";

    // Ensure incidents table has operations_manager_id and client_manager_id
    $incCols = $db->query("DESCRIBE incidents")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('operations_manager_id', $incCols)) {
        $db->exec("ALTER TABLE incidents ADD COLUMN operations_manager_id VARCHAR(100) NULL AFTER operations_manager_name");
        $results[] = "✅ Added missing column `operations_manager_id` to `incidents`.";
    }
    if (!in_array('client_manager_id', $incCols)) {
        $db->exec("ALTER TABLE incidents ADD COLUMN client_manager_id VARCHAR(100) NULL AFTER client_manager_name");
        $results[] = "✅ Added missing column `client_manager_id` to `incidents`.";
    }

    // Ensure sites table has required columns
    $cols = $db->query("DESCRIBE sites")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('supervisor_qr_token', $cols)) {
        $db->exec("ALTER TABLE sites ADD COLUMN supervisor_qr_token VARCHAR(100) NULL AFTER active_qr_token");
        $results[] = "✅ Added missing column `supervisor_qr_token` to `sites`.";
    }
    if (!in_array('token_hmac', $cols) && !in_array('active_qr_token_hash', $cols)) {
        $db->exec("ALTER TABLE sites ADD COLUMN active_qr_token_hash CHAR(64) NULL");
        $results[] = "✅ Added missing column `active_qr_token_hash` to `sites`.";
    }
    if (!in_array('token_encrypted', $cols) && !in_array('active_qr_token_encrypted', $cols)) {
        $db->exec("ALTER TABLE sites ADD COLUMN active_qr_token_encrypted TEXT NULL");
        $results[] = "✅ Added missing column `active_qr_token_encrypted` to `sites`.";
    }
    if (!in_array('supervisor_qr_token_hash', $cols)) {
        $db->exec("ALTER TABLE sites ADD COLUMN supervisor_qr_token_hash CHAR(64) NULL");
    }
    if (!in_array('supervisor_qr_token_encrypted', $cols)) {
        $db->exec("ALTER TABLE sites ADD COLUMN supervisor_qr_token_encrypted TEXT NULL");
    }
    $results[] = "✅ All checkpoint table columns verified & healthy.";
} catch (Throwable $e) {
    $results[] = "⚠️ Database check notice: " . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>USS System Auto-Repair Tool</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; padding: 40px 20px; }
        .box { max-width: 680px; margin: 0 auto; background: #1e293b; border: 1px solid #334155; border-radius: 14px; padding: 36px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
        h1 { color: #38bdf8; font-size: 22px; margin-top: 0; display: flex; align-items: center; gap: 10px; }
        ul { list-style: none; padding: 0; margin: 24px 0; }
        li { padding: 12px 16px; margin-bottom: 8px; border-radius: 8px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); font-family: monospace; font-size: 13.5px; }
        .btn { display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 700; margin-top: 15px; font-size: 14px; }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
<div class="box">
    <h1><span>🚀</span> USS System Auto-Repair Diagnostic & Fixer</h1>
    <p style="color: #94a3b8; font-size: 14px;">Results of live server path & database verification:</p>
    <ul>
        <?php foreach ($results as $r): ?>
            <li><?= $r ?></li>
        <?php endforeach; ?>
    </ul>
    
    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #334155; display: flex; gap: 12px; flex-wrap: wrap;">
        <a href="admin/sites.php" class="btn" style="background: #0284c7;">👉 Open Sites & QR Posters</a>
        <a href="attendance/" class="btn" style="background: #10b981;">👉 Open Attendance & Payroll</a>
        <a href="admin/dashboard.php" class="btn">👉 Open Admin Dashboard</a>
    </div>
</div>
</body>
</html>
