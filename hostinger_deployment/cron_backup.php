<?php
/**
 * Automated Nightly Backup Cron Script
 * Designed for Hostinger Cron Jobs
 * Runs daily via CLI or HTTP trigger with security key
 */

// Allow CLI or HTTP with secret key
$isCli = (php_sapi_name() === 'cli' || empty($_SERVER['REMOTE_ADDR']));
if (!$isCli) {
    require_once __DIR__ . '/config.php';
    $providedKey = $_GET['key'] ?? '';
    if ($providedKey !== 'uss_prod_sec_key_sprinzo_9988224411_!@#$%^') {
        http_response_code(403);
        die("Unauthorized cron access.");
    }
} else {
    require_once __DIR__ . '/config.php';
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting Automated Hostinger Backup...\n";

try {
    $db = get_db();
    $backupDir = BACKUPS_PATH;
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0755, true);
    }

    // 1. Generate Dump
    $tables = [];
    $stmt = $db->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $dump = "-- ==========================================================\n";
    $dump .= "-- AUTOMATED CRON BACKUP - HOSTINGER PRODUCTION\n";
    $dump .= "-- Date: " . date('Y-m-d H:i:s') . " GST\n";
    $dump .= "-- ==========================================================\n\n";
    $dump .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $dump .= "SET NAMES utf8mb4;\n\n";

    foreach ($tables as $table) {
        $stmtCreate = $db->query("SHOW CREATE TABLE `{$table}`");
        $createRow = $stmtCreate->fetch(PDO::FETCH_NUM);
        $dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $dump .= $createRow[1] . ";\n\n";

        $stmtRows = $db->query("SELECT * FROM `{$table}`");
        $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $dump .= "INSERT INTO `{$table}` VALUES\n";
            $rowInserts = [];
            foreach ($rows as $r) {
                $values = [];
                foreach ($r as $val) {
                    if ($val === null) {
                        $values[] = "NULL";
                    } else {
                        $values[] = $db->quote($val);
                    }
                }
                $rowInserts[] = "(" . implode(", ", $values) . ")";
            }
            $dump .= implode(",\n", $rowInserts) . ";\n\n";
        }
    }
    $dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    $filename = 'cron_db_backup_' . date('Ymd_His') . '.sql';
    $filePath = $backupDir . '/' . $filename;

    // Save
    file_put_contents($filePath, $dump);
    $fileSize = filesize($filePath);

    // Register in DB
    $db->prepare("INSERT INTO backups (filename, filepath, file_size, backup_type, created_by, created_at) VALUES (?, ?, ?, 'database', 'Hostinger Cron', NOW())")
       ->execute([$filename, 'backups/' . $filename, $fileSize]);

    // 2. Retention Policy: Auto-purge backups older than 30 days
    $purgeDate = date('Y-m-d H:i:s', strtotime('-30 days'));
    $stmtOld = $db->prepare("SELECT id, filename, filepath FROM backups WHERE created_at < ?");
    $stmtOld->execute([$purgeDate]);
    $oldBackups = $stmtOld->fetchAll();

    foreach ($oldBackups as $ob) {
        $oldFile = ROOT_PATH . '/' . $ob['filepath'];
        if (file_exists($oldFile)) {
            @unlink($oldFile);
        }
        $db->prepare("DELETE FROM backups WHERE id = ?")->execute([$ob['id']]);
        echo "Purged expired backup: " . $ob['filename'] . "\n";
    }

    log_audit('CRON_BACKUP_SUCCESS', 'SYSTEM', null, 'Automated nightly backup created: ' . $filename, 'SYSTEM', 'CRON', 'Hostinger Cron Job');
    echo "[" . date('Y-m-d H:i:s') . "] Backup completed successfully: {$filename} ({$fileSize} bytes)\n";

} catch (Exception $e) {
    echo "ERROR during cron backup: " . $e->getMessage() . "\n";
    log_audit('CRON_BACKUP_FAILED', 'SYSTEM', null, 'Error: ' . $e->getMessage(), 'SYSTEM', 'CRON', 'Hostinger Cron Job');
}
