<?php
// Attendance & Payroll Module - Enterprise Database Backup & Snapshot Engine
// File: hostinger_deployment/attendance/core/BackupEngine.php

if (!defined('ATT_ACCESS')) {
    die('Direct access not permitted.');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Audit.php';

class BackupEngine {
    /**
     * Tables in strict dependency order for clean restore
     */
    private static $tables = [
        'att_settings',
        'att_sites',
        'att_staff',
        'att_sheets',
        'att_rows',
        'att_cells',
        'att_site_assignments',
        'att_payroll_columns',
        'att_payroll_values',
        'att_payroll_remarks',
        'att_audit'
    ];

    /**
     * Generate complete pure-PHP SQL dump compatible with MySQL and MariaDB
     * Independent of mysqldump binary availability
     */
    public static function generateSqlDump(PDO $pdo, $includeDrop = true) {
        $timestamp = date('Y-m-d H:i:s');
        $dbName = defined('ATT_DB_NAME') ? ATT_DB_NAME : 'uss_attendance';

        $out = "-- ==================================================================\n";
        $out .= "-- Ultimate Security Services Attendance & Payroll Subsystem\n";
        $out .= "-- Standalone Database Backup Dump: `{$dbName}`\n";
        $out .= "-- Generated: {$timestamp} (Asia/Dubai)\n";
        $out .= "-- Engine: Enterprise BackupEngine (Pure PHP / InnoDB / UTF-8)\n";
        $out .= "-- ==================================================================\n\n";

        $out .= "SET NAMES utf8mb4;\n";
        $out .= "SET FOREIGN_KEY_CHECKS = 0;\n";
        $out .= "SET UNIQUE_CHECKS = 0;\n";
        $out .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
        $out .= "SET time_zone = '+04:00';\n\n";

        // Fetch actual existing tables in DB
        $stmtTbls = $pdo->query("SHOW TABLES");
        $existingTables = $stmtTbls->fetchAll(PDO::FETCH_COLUMN);

        // Sort tables matching preferred order, then append any extra tables
        $tablesToDump = [];
        foreach (self::$tables as $t) {
            if (in_array($t, $existingTables)) {
                $tablesToDump[] = $t;
            }
        }
        foreach ($existingTables as $t) {
            if (!in_array($t, $tablesToDump)) {
                $tablesToDump[] = $t;
            }
        }

        foreach ($tablesToDump as $table) {
            $out .= "-- ------------------------------------------------------------------\n";
            $out .= "-- Table structure for table `{$table}`\n";
            $out .= "-- ------------------------------------------------------------------\n";

            if ($includeDrop) {
                $out .= "DROP TABLE IF EXISTS `{$table}`;\n";
            }

            $stmtCreate = $pdo->query("SHOW CREATE TABLE `{$table}`");
            $rowCreate = $stmtCreate->fetch(PDO::FETCH_ASSOC);
            $createSql = $rowCreate['Create Table'] ?? '';
            $out .= $createSql . ";\n\n";

            // Dump Table Data
            $stmtCount = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
            $rowCount = (int)$stmtCount->fetchColumn();

            if ($rowCount > 0) {
                $out .= "-- Dumping data for table `{$table}` ({$rowCount} rows)\n";
                $out .= "LOCK TABLES `{$table}` WRITE;\n";
                $out .= "ALTER TABLE `{$table}` DISABLE KEYS;\n";

                $stmtRows = $pdo->query("SELECT * FROM `{$table}`");
                $chunk = [];
                $chunkSize = 50;
                $cols = [];

                while ($row = $stmtRows->fetch(PDO::FETCH_ASSOC)) {
                    if (empty($cols)) {
                        $cols = array_map(function($c) { return "`{$c}`"; }, array_keys($row));
                    }

                    $vals = [];
                    foreach ($row as $v) {
                        if ($v === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = $pdo->quote($v);
                        }
                    }
                    $chunk[] = '(' . implode(', ', $vals) . ')';

                    if (count($chunk) >= $chunkSize) {
                        $out .= "INSERT INTO `{$table}` (" . implode(', ', $cols) . ") VALUES\n" . implode(",\n", $chunk) . ";\n";
                        $chunk = [];
                    }
                }

                if (!empty($chunk)) {
                    $out .= "INSERT INTO `{$table}` (" . implode(', ', $cols) . ") VALUES\n" . implode(",\n", $chunk) . ";\n";
                }

                $out .= "ALTER TABLE `{$table}` ENABLE KEYS;\n";
                $out .= "UNLOCK TABLES;\n\n";
            }
        }

        $out .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        $out .= "SET UNIQUE_CHECKS = 1;\n";
        $out .= "-- End of Dump --\n";

        return $out;
    }

    /**
     * Create an on-disk snapshot file in backups directory
     */
    public static function createSnapshot(PDO $pdo, $tag = 'manual') {
        $backupDir = defined('ATT_BACKUPS_PATH') ? ATT_BACKUPS_PATH : (dirname(__DIR__) . '/backups');
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        // Ensure folder security
        self::secureBackupDirectory($backupDir);

        $safeTag = preg_replace('/[^a-zA-Z0-9_-]/', '', $tag);
        $filename = 'uss_attendance_backup_' . date('Ymd_His') . ($safeTag ? "_{$safeTag}" : '') . '.sql';
        $filepath = $backupDir . '/' . $filename;

        $sql = self::generateSqlDump($pdo, true);
        $bytes = @file_put_contents($filepath, $sql);

        if ($bytes === false) {
            throw new RuntimeException("Failed to write database backup to {$filepath}");
        }

        // Keep last 30 backups, prune older
        self::rotateBackups($backupDir, 30);

        if (function_exists('att_log_audit')) {
            att_log_audit('DB_BACKUP_CREATED', 'database', 0, [
                'filename' => $filename,
                'tag'      => $tag,
                'bytes'    => $bytes
            ], 'System Backup Engine');
        }

        return [
            'success'  => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'size'     => $bytes,
            'size_kb'  => round($bytes / 1024, 2)
        ];
    }

    /**
     * Stream backup directly to user browser for immediate download
     */
    public static function downloadBackup(PDO $pdo, $tag = 'export') {
        $sql = self::generateSqlDump($pdo, true);
        $filename = 'uss_attendance_backup_' . date('Y-m-d_His') . '.sql';

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sql));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $sql;
        exit;
    }

    /**
     * Check and run automatic daily backup if not already completed today
     */
    public static function autoDailyBackup(PDO $pdo) {
        $backupDir = defined('ATT_BACKUPS_PATH') ? ATT_BACKUPS_PATH : (dirname(__DIR__) . '/backups');
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        $todayPattern = 'uss_attendance_backup_' . date('Ymd') . '_*.sql';
        $todayFiles = glob($backupDir . '/' . $todayPattern);

        if (empty($todayFiles)) {
            try {
                return self::createSnapshot($pdo, 'auto_daily');
            } catch (Exception $e) {
                @error_log("[" . date('Y-m-d H:i:s') . "] Auto backup failed: " . $e->getMessage() . "\n", 3, ATT_LOGS_PATH . '/db_errors.log');
            }
        }
        return false;
    }

    /**
     * List all available backups
     */
    public static function listBackups() {
        $backupDir = defined('ATT_BACKUPS_PATH') ? ATT_BACKUPS_PATH : (dirname(__DIR__) . '/backups');
        if (!is_dir($backupDir)) {
            return [];
        }

        $files = glob($backupDir . '/*.sql');
        if (!$files) return [];

        rsort($files); // Most recent first
        $list = [];
        foreach ($files as $f) {
            $list[] = [
                'filename' => basename($f),
                'size_kb'  => round(filesize($f) / 1024, 2),
                'created'  => date('Y-m-d H:i:s', filemtime($f))
            ];
        }
        return $list;
    }

    /**
     * Keep newest $maxFiles backups, delete older
     */
    private static function rotateBackups($dir, $maxFiles = 30) {
        $files = glob($dir . '/*.sql');
        if (!$files || count($files) <= $maxFiles) {
            return;
        }

        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $toDelete = array_slice($files, $maxFiles);
        foreach ($toDelete as $f) {
            @unlink($f);
        }
    }

    /**
     * Ensure security files exist in backups folder
     */
    public static function secureBackupDirectory($dir) {
        // 1. Apache .htaccess
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            $content = "Require all denied\n<Files \"*\">\n    Order Deny,Allow\n    Deny from all\n</Files>\n";
            @file_put_contents($htaccess, $content);
        }

        // 2. index.html
        $indexHtml = $dir . '/index.html';
        if (!file_exists($indexHtml)) {
            $content = "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1></body></html>";
            @file_put_contents($indexHtml, $content);
        }

        // 3. IIS web.config
        $webConfig = $dir . '/web.config';
        if (!file_exists($webConfig)) {
            $content = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n  <system.webServer>\n    <authorization>\n      <deny users=\"*\" />\n    </authorization>\n  </system.webServer>\n</configuration>";
            @file_put_contents($webConfig, $content);
        }
    }
}
