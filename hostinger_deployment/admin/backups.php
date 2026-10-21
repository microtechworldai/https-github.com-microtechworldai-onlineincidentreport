<?php
/**
 * Database & System Backup / Restore Hub
 * Ensures 10+ years of reliable data preservation on Hostinger
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$pageTitle = "Database & System Backups";
$pageSubtitle = "Download on-demand backups, restore archives, and configure automated retention";
$db = get_db();
$msg = '';
$msgType = 'success';

$backupDir = BACKUPS_PATH;
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
}

/**
 * Generate Complete MySQL SQL Dump string
 */
function generate_sql_dump($pdo) {
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $dump = "-- ==========================================================\n";
    $dump .= "-- USS INCIDENT REPORT SYSTEM - FULL DATABASE DUMP\n";
    $dump .= "-- Hostinger Production Backup - Generated: " . date('Y-m-d H:i:s') . " GST\n";
    $dump .= "-- ==========================================================\n\n";
    $dump .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $dump .= "SET NAMES utf8mb4;\n\n";

    foreach ($tables as $table) {
        $stmtCreate = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $createRow = $stmtCreate->fetch(PDO::FETCH_NUM);
        $dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $dump .= $createRow[1] . ";\n\n";

        $stmtRows = $pdo->query("SELECT * FROM `{$table}`");
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
                        $values[] = $pdo->quote($val);
                    }
                }
                $rowInserts[] = "(" . implode(", ", $values) . ")";
            }
            $dump .= implode(",\n", $rowInserts) . ";\n\n";
        }
    }

    $dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $dump .= "-- END OF DATABASE DUMP\n";
    return $dump;
}

// 1. Handle On-Demand SQL Download
if (isset($_GET['action']) && $_GET['action'] === 'download_sql') {
    $filename = 'uss_backup_db_' . date('Ymd_His') . '.sql';
    $dump = generate_sql_dump($db);

    // Save to backup registry
    $filePath = $backupDir . '/' . $filename;
    file_put_contents($filePath, $dump);

    $db->prepare("INSERT INTO backups (filename, filepath, file_size, backup_type, created_by, created_at) VALUES (?, ?, ?, 'database', ?, NOW())")
       ->execute([$filename, 'backups/' . $filename, strlen($dump), $_SESSION['admin_name']]);

    log_audit('DOWNLOAD_SQL_BACKUP', 'BACKUP', $filename, 'Downloaded SQL database dump');

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($dump));
    echo $dump;
    exit;
}

// 2. Handle On-Demand Full System ZIP (Database + Photos)
if (isset($_GET['action']) && $_GET['action'] === 'download_full_zip') {
    if (!class_exists('ZipArchive')) {
        $msg = "ZipArchive extension is not enabled on this server. Please use SQL Dump.";
        $msgType = 'danger';
    } else {
        $zipName = 'uss_full_system_backup_' . date('Ymd_His') . '.zip';
        $zipPath = $backupDir . '/' . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            // Add database dump
            $sqlDump = generate_sql_dump($db);
            $zip->addFromString('database.sql', $sqlDump);

            // Add photos
            $photosDir = UPLOADS_PATH . '/photos';
            if (is_dir($photosDir)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($photosDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($files as $name => $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = 'uploads/photos/' . substr($filePath, strlen($photosDir) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
            }

            $zip->close();

            $fileSize = filesize($zipPath);
            $db->prepare("INSERT INTO backups (filename, filepath, file_size, backup_type, created_by, created_at) VALUES (?, ?, ?, 'full', ?, NOW())")
               ->execute([$zipName, 'backups/' . $zipName, $fileSize, $_SESSION['admin_name']]);

            log_audit('DOWNLOAD_FULL_BACKUP', 'BACKUP', $zipName, 'Downloaded full system zip archive');

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zipName . '"');
            header('Content-Length: ' . $fileSize);
            readfile($zipPath);
            exit;
        } else {
            $msg = "Failed to create ZIP archive.";
            $msgType = 'danger';
        }
    }
}

// 3. Handle Database Restore from Uploaded SQL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    if (empty($_FILES['backup_sql']['name']) || !is_uploaded_file($_FILES['backup_sql']['tmp_name'])) {
        $msg = "Please select a valid .sql backup file to restore.";
        $msgType = 'danger';
    } else {
        $uploadedFile = $_FILES['backup_sql']['tmp_name'];
        $sqlContent = file_get_contents($uploadedFile);

        if (empty($sqlContent)) {
            $msg = "The uploaded file is empty.";
            $msgType = 'danger';
        } else {
            try {
                // Execute multi-query restoration
                $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 1);
                $db->exec($sqlContent);
                $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);

                log_audit('RESTORE_DATABASE', 'SYSTEM', null, 'Restored database from uploaded SQL file: ' . $_FILES['backup_sql']['name']);
                $msg = "Database restored successfully! All tables and records have been reloaded.";
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = "Error restoring database: " . $e->getMessage();
                $msgType = 'danger';
            }
        }
    }
}

// 4. Handle Download Specific Backup File
if (isset($_GET['action']) && $_GET['action'] === 'download_file' && !empty($_GET['id'])) {
    $bId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM backups WHERE id = ?");
    $stmt->execute([$bId]);
    $b = $stmt->fetch();
    if ($b) {
        $fileName = basename($b['filename']);
        $filePath = $backupDir . '/' . $fileName;
        if (!file_exists($filePath)) {
            $filePath = ROOT_PATH . '/' . $b['filepath'];
        }
        if (file_exists($filePath)) {
            $mime = ($b['backup_type'] === 'full') ? 'application/zip' : 'application/sql';
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        } else {
            $msg = "Backup file not found on disk: " . htmlspecialchars($fileName);
            $msgType = 'danger';
        }
    }
}

// 5. Handle Restore Database Directly from Archive File
if (isset($_GET['action']) && $_GET['action'] === 'restore_db' && !empty($_GET['id'])) {
    $bId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM backups WHERE id = ?");
    $stmt->execute([$bId]);
    $b = $stmt->fetch();
    if ($b) {
        $fileName = basename($b['filename']);
        $filePath = $backupDir . '/' . $fileName;
        if (!file_exists($filePath)) {
            $filePath = ROOT_PATH . '/' . $b['filepath'];
        }
        if (file_exists($filePath)) {
            try {
                $sqlContent = '';
                if ($b['backup_type'] === 'database' || str_ends_with($fileName, '.sql')) {
                    $sqlContent = file_get_contents($filePath);
                } elseif (class_exists('ZipArchive') && ($b['backup_type'] === 'full' || str_ends_with($fileName, '.zip'))) {
                    $zip = new ZipArchive();
                    if ($zip->open($filePath) === true) {
                        $sqlContent = $zip->getFromName('database.sql');
                        $zip->close();
                    }
                }

                if (empty($sqlContent)) {
                    throw new Exception("Unable to extract valid SQL data from the selected backup file.");
                }

                $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 1);
                $db->exec($sqlContent);
                $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);

                log_audit('RESTORE_DATABASE', 'SYSTEM', $bId, 'Restored database from archive file: ' . $fileName);
                $msg = "Database successfully restored from archive: " . htmlspecialchars($fileName) . "! All tables and records have been refreshed.";
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = "Error restoring database: " . $e->getMessage();
                $msgType = 'danger';
            }
        } else {
            $msg = "Backup archive file not found on disk: " . htmlspecialchars($fileName);
            $msgType = 'danger';
        }
    }
}

// 6. Handle Soft Delete (Move Backup to Trash)
if (isset($_GET['action']) && $_GET['action'] === 'soft_delete' && !empty($_GET['id'])) {
    $bId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT filename FROM backups WHERE id = ?");
    $stmt->execute([$bId]);
    $b = $stmt->fetch();
    if ($b) {
        $db->prepare("UPDATE backups SET deleted_at = NOW() WHERE id = ?")->execute([$bId]);
        log_audit('TRASH_BACKUP', 'BACKUP', $bId, 'Moved backup to trash: ' . $b['filename']);
        $msg = "Backup file \"" . htmlspecialchars($b['filename']) . "\" moved to trash.";
        $msgType = 'success';
    }
}

// 7. Handle Restore Deleted File (Un-delete / Restore back to Active)
if (isset($_GET['action']) && $_GET['action'] === 'restore_file' && !empty($_GET['id'])) {
    $bId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT filename FROM backups WHERE id = ?");
    $stmt->execute([$bId]);
    $b = $stmt->fetch();
    if ($b) {
        $db->prepare("UPDATE backups SET deleted_at = NULL WHERE id = ?")->execute([$bId]);
        log_audit('RESTORE_TRASH_BACKUP', 'BACKUP', $bId, 'Restored backup file from trash: ' . $b['filename']);
        $msg = "Backup file \"" . htmlspecialchars($b['filename']) . "\" restored back to active archives!";
        $msgType = 'success';
    }
}

// 8. Handle Permanent Delete (Purge File from Disk and DB)
if (isset($_GET['action']) && $_GET['action'] === 'permanent_delete' && !empty($_GET['id'])) {
    $bId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM backups WHERE id = ?");
    $stmt->execute([$bId]);
    $b = $stmt->fetch();
    if ($b) {
        $fileName = basename($b['filename']);
        $filePath = $backupDir . '/' . $fileName;
        if (!file_exists($filePath)) {
            $filePath = ROOT_PATH . '/' . $b['filepath'];
        }
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        $db->prepare("DELETE FROM backups WHERE id = ?")->execute([$bId]);
        log_audit('PERMANENT_DELETE_BACKUP', 'BACKUP', $bId, 'Permanently purged backup file: ' . $fileName);
        $msg = "Backup file \"" . htmlspecialchars($fileName) . "\" has been permanently deleted from storage and registry.";
        $msgType = 'success';
    }
}

$showDeleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] === '1';

// Count Active vs Deleted
$activeCount = (int)$db->query("SELECT COUNT(*) FROM backups WHERE deleted_at IS NULL")->fetchColumn();
$deletedCount = (int)$db->query("SELECT COUNT(*) FROM backups WHERE deleted_at IS NOT NULL")->fetchColumn();

// Fetch Backups Registry
if ($showDeleted) {
    $backups = $db->query("SELECT * FROM backups WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT 50")->fetchAll();
} else {
    $backups = $db->query("SELECT * FROM backups WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 50")->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Quick Actions Banner -->
<div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px;">
        <div>
            <h3 style="font-size: 18px; font-weight: 800;">💾 One-Click Data Backups</h3>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 2px;">
                Download your entire system immediately. Keep copies locally for maximum data redundancy.
            </p>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 12px;">
            <a href="<?= APP_URL ?>/admin/backups.php?action=download_sql" class="btn btn-primary">
                📥 Download Database SQL (.sql)
            </a>
            <a href="<?= APP_URL ?>/admin/backups.php?action=download_full_zip" class="btn btn-secondary" style="border-color: var(--accent-cyan); color: var(--accent-cyan);">
                📦 Download Full System Archive (DB + Photos .zip)
            </a>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">

    <!-- Left: Restore Database -->
    <div class="card">
        <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
            🔄 Restore Database from Backup
        </h3>
        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 18px;">
            Upload any previously downloaded <code>.sql</code> file to restore the entire database.
        </p>

        <form method="POST" action="" enctype="multipart/form-data" onsubmit="return confirm('WARNING: Restoring will overwrite existing database records with the contents of the backup. Are you sure you want to proceed?');">
            <input type="hidden" name="restore_backup" value="1">

            <div class="form-group">
                <label class="form-label">Select SQL Backup File (.sql)</label>
                <input type="file" name="backup_sql" class="form-control" accept=".sql" required>
            </div>

            <button type="submit" class="btn btn-danger" style="width: 100%; margin-top: 10px;">
                ⚠️ Execute Database Restoration
            </button>
        </form>
    </div>

    <!-- Right: Hostinger Automated Cron Instructions -->
    <div class="card" style="border-left: 4px solid var(--accent-emerald);">
        <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
            ⏱️ Automated Nightly Cron Job (Hostinger)
        </h3>
        <p style="font-size: 12.5px; color: var(--text-muted); line-height: 1.6; margin-bottom: 14px;">
            To keep your data safe automatically for 10+ years, schedule Hostinger's built-in Cron Job to run nightly at 03:00 GST. It creates automatic snapshots and keeps 30 days of rolling backups.
        </p>

        <div style="background: rgba(15, 23, 42, 0.9); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 12px; font-family: 'JetBrains Mono', monospace; font-size: 12px; color: #38bdf8; word-break: break-all; margin-bottom: 12px;">
            /usr/bin/php <?= ROOT_PATH ?>/cron_backup.php
        </div>

        <ol style="font-size: 12px; color: var(--text-dim); padding-left: 18px; line-height: 1.6;">
            <li>In <strong>Hostinger hPanel</strong>, go to <strong>Advanced &rarr; Cron Jobs</strong>.</li>
            <li>Select <strong>Custom</strong> and enter: <code>0 3 * * *</code> (Runs daily at 3:00 AM).</li>
            <li>Paste the command line above into the <strong>Command</strong> input and click <strong>Save</strong>.</li>
        </ol>
    </div>

</div>

<!-- Recent Backups Table -->
<div class="card">
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 16px;">
        <h3 style="font-size: 16px; font-weight: 700; margin: 0;">
            🗄️ Backup History Registry
        </h3>

        <!-- Active vs Trash Tabs -->
        <div style="display: inline-flex; background: rgba(0,0,0,0.2); border: 1px solid var(--border-subtle); border-radius: 8px; padding: 3px; gap: 4px;">
            <a href="<?= APP_URL ?>/admin/backups.php" class="btn btn-sm" style="<?= !$showDeleted ? 'background: var(--primary); color: var(--primary-text); font-weight: 700;' : 'background: transparent; color: var(--text-muted); border: none;' ?>">
                📁 Active Archives (<?= $activeCount ?>)
            </a>
            <a href="<?= APP_URL ?>/admin/backups.php?show_deleted=1" class="btn btn-sm" style="<?= $showDeleted ? 'background: var(--accent-rose); color: #fff; font-weight: 700;' : 'background: transparent; color: var(--text-muted); border: none;' ?>">
                🗑️ Trash / Deleted (<?= $deletedCount ?>)
            </a>
        </div>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Backup Filename</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Created By</th>
                    <th>Date & Time</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($backups)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 35px; color: var(--text-dim);">
                            <?= $showDeleted ? 'No deleted backup files found in trash.' : 'No archived backups in history yet. Click "Download Database SQL" above to generate your first backup.' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($backups as $b): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--text-main); font-family: 'JetBrains Mono', monospace; font-size: 13px;">
                                    <?= htmlspecialchars($b['filename']) ?>
                                </strong>
                            </td>
                            <td>
                                <span class="badge" style="background: rgba(255,255,255,0.08); color: var(--text-main);">
                                    <?= htmlspecialchars(strtoupper($b['backup_type'])) ?>
                                </span>
                            </td>
                            <td style="color: var(--accent-cyan); font-family: monospace;">
                                <?= number_format($b['file_size'] / 1024, 1) ?> KB
                            </td>
                            <td><?= htmlspecialchars($b['created_by']) ?></td>
                            <td style="font-family: monospace; font-size: 12px; color: var(--text-dim);">
                                <?= format_datetime($b['created_at'], true) ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 6px; flex-wrap: wrap;">
                                    <?php if ($showDeleted): ?>
                                        <!-- Restore Deleted File back to Active -->
                                        <a href="<?= APP_URL ?>/admin/backups.php?action=restore_file&id=<?= $b['id'] ?>&show_deleted=1" 
                                           class="btn btn-secondary btn-sm" 
                                           onclick="return confirm('Restore deleted backup file \'<?= htmlspecialchars(addslashes($b['filename'])) ?>\' back to active registry?');"
                                           style="color: #34d399; border-color: rgba(52, 211, 153, 0.4);" 
                                           title="Restore deleted backup file to active archives">
                                            🔄 Restore File
                                        </a>

                                        <!-- Delete Permanently from Disk & DB -->
                                        <a href="<?= APP_URL ?>/admin/backups.php?action=permanent_delete&id=<?= $b['id'] ?>&show_deleted=1" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('⚠️ PERMANENT PURGE: This will completely delete \'<?= htmlspecialchars(addslashes($b['filename'])) ?>\' from server storage and database. This cannot be undone. Are you sure?');" 
                                           title="Permanently Purge Backup">
                                            🗑️ Delete Permanently
                                        </a>
                                    <?php else: ?>
                                        <!-- Download File -->
                                        <a href="<?= APP_URL ?>/admin/backups.php?action=download_file&id=<?= $b['id'] ?>" 
                                           class="btn btn-secondary btn-sm" 
                                           title="Download this backup file">
                                            📥 Download
                                        </a>

                                        <!-- Restore Database Directly from Archive -->
                                        <a href="<?= APP_URL ?>/admin/backups.php?action=restore_db&id=<?= $b['id'] ?>" 
                                           class="btn btn-secondary btn-sm" 
                                           onclick="return confirm('⚠️ RESTORE DATABASE: Are you sure you want to restore the entire database from \'<?= htmlspecialchars(addslashes($b['filename'])) ?>\'? Current database tables will be reloaded from this archive.');" 
                                           style="color: #38bdf8; border-color: rgba(56, 189, 248, 0.4);"
                                           title="Restore entire database from this archive file">
                                            🔄 Restore DB
                                        </a>

                                        <!-- Soft Delete (Move to Trash) -->
                                        <a href="<?= APP_URL ?>/admin/backups.php?action=soft_delete&id=<?= $b['id'] ?>" 
                                           class="btn btn-secondary btn-sm" 
                                           onclick="return confirm('Move \'<?= htmlspecialchars(addslashes($b['filename'])) ?>\' to trash? You can restore it anytime from the Trash tab.');" 
                                           title="Move to Trash">
                                            🗑️ Trash
                                        </a>

                                        <!-- Delete Permanently directly -->
                                        <a href="<?= APP_URL ?>/admin/backups.php?action=permanent_delete&id=<?= $b['id'] ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('⚠️ PERMANENT PURGE: Delete \'<?= htmlspecialchars(addslashes($b['filename'])) ?>\' permanently from server storage and database? This cannot be undone.');" 
                                           title="Delete Permanently">
                                            Purge
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
