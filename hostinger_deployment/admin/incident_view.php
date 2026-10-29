<?php
// Emergency Debug & Crash Trapper for Hostinger
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (!headers_sent()) {
            http_response_code(200);
        }
        echo "<div style='font-family:sans-serif; background:#fee2e2; border:3px solid #dc2626; color:#7f1d1d; padding:24px; margin:24px; border-radius:10px; max-width:800px; z-index:999999; position:relative;'>";
        echo "<h2 style='margin-top:0; color:#b91c1c;'>⚠️ Server Fatal Error Detected</h2>";
        echo "<p style='font-size:16px;'><strong>Error:</strong> " . htmlspecialchars($err['message']) . "</p>";
        echo "<p style='font-size:14px;'><strong>File:</strong> " . htmlspecialchars($err['file']) . " (Line " . $err['line'] . ")</p>";
        echo "</div>";
    }
});
/**
 * Detailed Incident Dossier & Management Sign-Off
 */
require_once __DIR__ . '/../config.php';

// Keep error display active for instant visibility if any issue occurs
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
if (!$id) {
    header("Location: " . APP_URL . "/admin/incidents.php");
    exit;
}

$db = get_db();
$msg = '';
$msgType = 'success';

// Date cleaner helper to prevent MySQL 1292 Incorrect date value errors
if (!function_exists('safe_mysql_date')) {
    function safe_mysql_date($d) {
        if (empty($d)) return null;
        $d = trim((string)$d);
        if ($d === '' || $d === '0000-00-00' || $d === '00-00-0000' || $d === 'null') return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return $d;
        $ts = strtotime(str_replace('/', '-', $d));
        return $ts ? date('Y-m-d', $ts) : null;
    }
}

// 100% reliable helper to check if a column actually exists in incidents table
$checkColExists = function($dbConn, $colName) {
    try {
        $st = $dbConn->query("SELECT `{$colName}` FROM incidents LIMIT 0");
        if ($st !== false) {
            $st->closeCursor();
            return true;
        }
    } catch (\Throwable $t) {}
    return false;
};

$requiredCols = [
    'site_supervisor_name' => "VARCHAR(150) NULL",
    'site_supervisor_id' => "VARCHAR(100) NULL",
    'site_supervisor_signature' => "TEXT NULL",
    'site_supervisor_date' => "DATE NULL",
    'recommendation_action_taken' => "TEXT NULL",
    'operations_manager_name' => "VARCHAR(150) NULL",
    'operations_manager_id' => "VARCHAR(100) NULL",
    'operations_manager_signature' => "TEXT NULL",
    'operations_manager_date' => "DATE NULL",
    'client_manager_name' => "VARCHAR(150) NULL",
    'client_manager_id' => "VARCHAR(100) NULL",
    'client_manager_action_taken' => "TEXT NULL",
    'client_manager_signature' => "TEXT NULL",
    'client_manager_date' => "DATE NULL",
    'witness_1' => "TEXT NULL",
    'witness_2' => "TEXT NULL",
    'witness_3' => "TEXT NULL"
];

$existingColsMap = [];
foreach ($requiredCols as $col => $def) {
    if (!$checkColExists($db, $col)) {
        try {
            $db->exec("ALTER TABLE incidents ADD COLUMN `{$col}` {$def}");
            if ($checkColExists($db, $col)) {
                $existingColsMap[$col] = true;
            }
        } catch (\Throwable $te) {}
    } else {
        $existingColsMap[$col] = true;
    }
}

// Fetch Incident Record helper with clean cursor and fallback
$fetchIncidentData = function($dbConn, $incId) {
    try {
        $st = $dbConn->prepare("
            SELECT i.*, s.client_name, s.client_contact, s.latitude as site_latitude, s.longitude as site_longitude, s.code as site_official_code
            FROM incidents i 
            LEFT JOIN sites s ON s.id = i.site_id 
            WHERE i.id = ? 
            LIMIT 1
        ");
        $st->execute([$incId]);
        $row = $st->fetch();
        $st->closeCursor();
        if ($row) return $row;
    } catch (\Throwable $t) {}

    // Fallback without JOIN in case sites table or site columns differ
    try {
        $st2 = $dbConn->prepare("SELECT * FROM incidents WHERE id = ? LIMIT 1");
        $st2->execute([$incId]);
        $row2 = $st2->fetch();
        $st2->closeCursor();
        return $row2 ?: false;
    } catch (\Throwable $t2) {
        return false;
    }
};

$incident = $fetchIncidentData($db, $id);
if (!$incident) {
    die("Incident not found.");
}

// Handle Management Sign-Off & Status Update (with Signature Image Uploads)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['update_management']) || isset($_POST['save_signoff']))) {
    $newStatus = trim($_POST['status'] ?? ($incident['status'] ?? 'Submitted'));
    $supervisorName = trim($_POST['site_supervisor_name'] ?? '');
    $supervisorId = trim($_POST['site_supervisor_id'] ?? '');
    $supervisorDate = safe_mysql_date($_POST['site_supervisor_date'] ?? null);
    $recommendationAction = trim($_POST['recommendation_action_taken'] ?? '');
    
    $opsManagerName = trim($_POST['operations_manager_name'] ?? '');
    $opsManagerId = trim($_POST['operations_manager_id'] ?? '');
    $opsManagerDate = safe_mysql_date($_POST['operations_manager_date'] ?? null);

    $clientManagerName = trim($_POST['client_manager_name'] ?? '');
    $clientManagerId = trim($_POST['client_manager_id'] ?? '');
    $clientManagerAction = trim($_POST['client_manager_action_taken'] ?? '');
    $clientManagerDate = safe_mysql_date($_POST['client_manager_date'] ?? null);

    $supSig = !empty($_POST['remove_supervisor_sig']) ? null : ($incident['site_supervisor_signature'] ?? null);
    $opsSig = !empty($_POST['remove_ops_sig']) ? null : ($incident['operations_manager_signature'] ?? null);
    $clientSig = !empty($_POST['remove_client_sig']) ? null : ($incident['client_manager_signature'] ?? null);

    $sigDir = (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__)) . '/uploads/signatures';
    if (!is_dir($sigDir)) {
        @mkdir($sigDir, 0777, true);
    }

    if (!empty($_FILES['site_supervisor_sig_file']['tmp_name']) && is_uploaded_file($_FILES['site_supervisor_sig_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['site_supervisor_sig_file']['name'], PATHINFO_EXTENSION)) ?: 'png';
        $fname = 'sup_sig_' . $id . '_' . time() . '.' . $ext;
        if (@move_uploaded_file($_FILES['site_supervisor_sig_file']['tmp_name'], $sigDir . '/' . $fname)) {
            $supSig = 'uploads/signatures/' . $fname;
        }
    }
    if (!empty($_FILES['operations_manager_sig_file']['tmp_name']) && is_uploaded_file($_FILES['operations_manager_sig_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['operations_manager_sig_file']['name'], PATHINFO_EXTENSION)) ?: 'png';
        $fname = 'ops_sig_' . $id . '_' . time() . '.' . $ext;
        if (@move_uploaded_file($_FILES['operations_manager_sig_file']['tmp_name'], $sigDir . '/' . $fname)) {
            $opsSig = 'uploads/signatures/' . $fname;
        }
    }
    if (!empty($_FILES['client_manager_sig_file']['tmp_name']) && is_uploaded_file($_FILES['client_manager_sig_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['client_manager_sig_file']['name'], PATHINFO_EXTENSION)) ?: 'png';
        $fname = 'client_sig_' . $id . '_' . time() . '.' . $ext;
        if (@move_uploaded_file($_FILES['client_manager_sig_file']['tmp_name'], $sigDir . '/' . $fname)) {
            $clientSig = 'uploads/signatures/' . $fname;
        }
    }

    try {
        // Build resilient dynamic SET clause based strictly on verified existing columns
        $candidateFields = [
            'status' => $newStatus,
            'site_supervisor_name' => $supervisorName,
            'site_supervisor_id' => $supervisorId,
            'site_supervisor_signature' => $supSig,
            'site_supervisor_date' => $supervisorDate,
            'recommendation_action_taken' => $recommendationAction,
            'operations_manager_name' => $opsManagerName,
            'operations_manager_id' => $opsManagerId,
            'operations_manager_signature' => $opsSig,
            'operations_manager_date' => $opsManagerDate,
            'client_manager_name' => $clientManagerName,
            'client_manager_id' => $clientManagerId,
            'client_manager_action_taken' => $clientManagerAction,
            'client_manager_signature' => $clientSig,
            'client_manager_date' => $clientManagerDate
        ];

        $setSql = [];
        $params = [];
        foreach ($candidateFields as $colKey => $colVal) {
            if ($colKey === 'status' || !empty($existingColsMap[$colKey]) || $checkColExists($db, $colKey)) {
                $setSql[] = "`{$colKey}` = ?";
                $params[] = $colVal;
            }
        }

        if ($checkColExists($db, 'updated_at')) {
            $setSql[] = "`updated_at` = NOW()";
        }

        if (empty($setSql)) {
            $setSql[] = "`status` = ?";
            $params[] = $newStatus;
        }

        $params[] = $id;
        $stmtUpdate = $db->prepare("UPDATE incidents SET " . implode(', ', $setSql) . " WHERE id = ?");
        $stmtUpdate->execute($params);
        $stmtUpdate->closeCursor();

        try {
            log_audit('UPDATE_MANAGEMENT_SIGNOFF', 'INCIDENT', $id, [
                'status' => $newStatus,
                'supervisor' => $supervisorName
            ]);
        } catch (\Throwable $ae) {}

        $msg = "Management sign-off and report status updated successfully.";
        $msgType = 'success';

        // Re-fetch updated incident data cleanly
        $updatedData = $fetchIncidentData($db, $id);
        if ($updatedData) {
            $incident = $updatedData;
        }

        if (!empty($_POST['ajax_signoff'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => $msg,
                'status' => $newStatus,
                'status_badge' => function_exists('render_status_badge') ? render_status_badge($newStatus) : $newStatus
            ]);
            exit;
        }
    } catch (\Throwable $e) {
        $msg = "Notice: Sign-off saved with notice: " . $e->getMessage();
        $msgType = 'danger';
        error_log("Error saving sign-off: " . $e->getMessage());

        if (!empty($_POST['ajax_signoff'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }
}
// Handle Updating Photo Caption (Image by Image in Dashboard)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_photo_caption'])) {
    $photoId = (int)($_POST['photo_id'] ?? 0);
    $newCaption = trim($_POST['caption'] ?? '');
    if ($photoId > 0) {
        $stmtCap = $db->prepare("UPDATE incident_photos SET caption = ? WHERE id = ? AND incident_id = ?");
        $stmtCap->execute([$newCaption, $photoId, $id]);
        log_audit('UPDATE_PHOTO_CAPTION', 'INCIDENT_PHOTO', $photoId, ['caption' => $newCaption]);
        
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'photo_id' => $photoId, 'caption' => $newCaption]);
            exit;
        }
        $msg = "Photo caption updated successfully.";
    }
}

// Handle Adding Internal Note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $noteText = trim($_POST['note_text'] ?? '');
    if (!empty($noteText)) {
        $stmtNote = $db->prepare("INSERT INTO incident_notes (incident_id, admin_id, admin_name, note, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmtNote->execute([$id, $_SESSION['admin_id'], $_SESSION['admin_name'], $noteText]);
        log_audit('ADD_INCIDENT_NOTE', 'INCIDENT', $id, 'Added internal follow-up note');
        $msg = "Internal note recorded.";
    }
}

// Incident Record already fetched and validated upfront

// Decode JSON fields safely
$causes = json_decode($incident['cause_main'] ?: '[]', true) ?: [];
$discReasons = json_decode($incident['disciplinary_reasons'] ?: '[]', true) ?: [];
$incTypes = json_decode($incident['incident_types'] ?: '[]', true) ?: [];
$notified = json_decode($incident['who_notified'] ?: '[]', true) ?: [];

// Fetch Attached Photos safely
$photos = [];
try {
    $stmtPhotos = $db->prepare("SELECT * FROM incident_photos WHERE incident_id = ? ORDER BY sort_order ASC, id ASC");
    $stmtPhotos->execute([$id]);
    $photos = $stmtPhotos->fetchAll();
    $stmtPhotos->closeCursor();
} catch (\Throwable $e) {}

// Fetch Notes safely
$notes = [];
try {
    $stmtNotes = $db->prepare("SELECT * FROM incident_notes WHERE incident_id = ? ORDER BY created_at DESC");
    $stmtNotes->execute([$id]);
    $notes = $stmtNotes->fetchAll();
    $stmtNotes->closeCursor();
} catch (\Throwable $e) {}

$pageTitle = "Incident " . htmlspecialchars($incident['ref_number']);
$pageSubtitle = "Detailed Investigation & Client Sign-Off Dossier";

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if (!empty($incident['deleted_at'])): ?>
    <div class="alert alert-danger" style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 20px;">
        <div>
            <strong>⚠️ This incident report is currently in Trash (Soft-Deleted on <?= format_datetime($incident['deleted_at']) ?>).</strong>
            <div style="font-size: 12.5px; opacity: 0.9; margin-top: 2px;">It is excluded from active dashboards and client summaries. You can restore it or permanently purge it.</div>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="<?= APP_URL ?>/admin/incidents.php?action=restore&incident_id=<?= $incident['id'] ?>" class="btn btn-secondary btn-sm" onclick="return confirm('Restore this incident report back to active reports?');" style="color: #34d399; border-color: rgba(52, 211, 153, 0.5);">
                🔄 Restore Item
            </a>
            <a href="<?= APP_URL ?>/admin/incidents.php?action=permanent_delete&incident_id=<?= $incident['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('⚠️ PERMANENT PURGE: Delete this incident report and all photos permanently? This cannot be undone.');">
                🗑️ Delete Permanently
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- Top Actions Bar -->
<div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 24px;">
    <div>
        <a href="<?= APP_URL ?>/admin/incidents.php<?= !empty($incident['deleted_at']) ? '?show_deleted=1' : '' ?>" class="btn btn-secondary btn-sm">&larr; Back to Incidents</a>
    </div>
    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
        <a href="<?= APP_URL ?>/admin/incident_pdf.php?id=<?= $incident['id'] ?>" target="_blank" class="btn btn-primary btn-sm">
            📄 Print / Download Official USS PDF
        </a>
        <a href="<?= APP_URL ?>/admin/incident_edit.php?id=<?= $incident['id'] ?>" class="btn btn-secondary btn-sm">
            ✏️ Edit Report
        </a>
        <?php if (!empty($incident['deleted_at'])): ?>
            <a href="<?= APP_URL ?>/admin/incidents.php?action=restore&incident_id=<?= $incident['id'] ?>" class="btn btn-secondary btn-sm" onclick="return confirm('Restore this incident report back to active reports?');" style="color: #34d399; border-color: rgba(52, 211, 153, 0.4);">
                🔄 Restore Item
            </a>
            <a href="<?= APP_URL ?>/admin/incidents.php?action=permanent_delete&incident_id=<?= $incident['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('⚠️ PERMANENT PURGE: Delete this incident report permanently? This cannot be undone.');">
                🗑️ Delete Permanently
            </a>
        <?php else: ?>
            <a href="<?= APP_URL ?>/admin/incidents.php?action=soft_delete&incident_id=<?= $incident['id'] ?>" class="btn btn-secondary btn-sm" onclick="return confirm('Move this incident report to trash?');" title="Move to Trash">
                🗑️ Move to Trash
            </a>
            <a href="<?= APP_URL ?>/admin/incidents.php?action=permanent_delete&incident_id=<?= $incident['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('⚠️ PERMANENT PURGE: Delete this incident report and photos permanently right now? This cannot be undone.');" title="Delete Permanently">
                Purge Permanently
            </a>
        <?php endif; ?>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">

    <!-- Left Column: Incident Details -->
    <div>
        <div class="card" style="margin-bottom: 24px;">
            <!-- Reference Header Banner (Image 1 Theme: #081426 background & #fbbf24 gold text) -->
            <div class="live-patrol-banner" style="margin-bottom: 20px;">
                <div>
                    <span style="display: block; font-size: 11.5px; text-transform: uppercase; letter-spacing: 1.2px; color: var(--brand-primary); font-weight: 800;">Official Reference</span>
                    <h2 style="font-family: 'JetBrains Mono', monospace; font-size: 22px; font-weight: 800; color: #ffffff !important; margin: 4px 0 0 0; letter-spacing: 0.5px;">
                        <?= htmlspecialchars($incident['ref_number']) ?>
                    </h2>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <?= render_priority_badge($incident['priority']) ?>
                    <?= render_status_badge($incident['status']) ?>
                </div>
            </div>

            <!-- Key Patrol Metadata -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; background: var(--bg-card-hover); padding: 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                <div>
                    <div style="font-size: 11px; color: var(--text-dim); text-transform: uppercase;">Patrol Site</div>
                    <strong style="font-size: 14px;"><?= htmlspecialchars($incident['site_name']) ?></strong>
                    <div style="font-size: 11px; color: var(--accent-cyan); font-family: monospace;"><?= htmlspecialchars($incident['site_code']) ?></div>
                    <?php if (!empty($incident['site_latitude']) || !empty($incident['site_longitude'])): ?>
                        <div style="font-size: 11px; color: #38bdf8; font-weight: 600; margin-top: 3px;">
                            📍 GPS: <?= htmlspecialchars($incident['site_latitude'] ?? '') ?>, <?= htmlspecialchars($incident['site_longitude'] ?? '') ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <div style="font-size: 11px; color: var(--text-dim); text-transform: uppercase;">Reporting Officer</div>
                    <strong style="font-size: 14px;"><?= htmlspecialchars($incident['reported_by_name']) ?></strong>
                    <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($incident['reported_by_designation']) ?></div>
                </div>

                <div>
                    <div style="font-size: 11px; color: var(--text-dim); text-transform: uppercase;">Date & Time</div>
                    <strong style="font-size: 14px;"><?= format_date($incident['incident_date']) ?></strong>
                    <div style="font-size: 11px; color: var(--text-muted);"><?= format_time($incident['incident_time']) ?> GST (<?= htmlspecialchars($incident['shift']) ?> Shift)</div>
                </div>

                <div>
                    <div style="font-size: 11px; color: var(--text-dim); text-transform: uppercase;">Filed In System</div>
                    <strong style="font-size: 13px;"><?= format_datetime($incident['created_at']) ?></strong>
                    <div style="font-size: 11px; color: var(--text-muted);"><?= format_datetime($incident['created_at'], true) ?> GST</div>
                </div>
            </div>

            <!-- Category Classifications -->
            <div style="margin-bottom: 24px;">
                <h4 class="section-header-banner" style="color: #ffffff !important; font-weight: 800 !important;">Classification &amp; Triggers</h4>
                
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <div>
                        <span style="font-size: 12px; color: var(--text-muted); margin-right: 8px;">Main Cause:</span>
                        <?php foreach ($causes as $c): ?>
                            <span class="badge" style="background: rgba(2, 132, 199, 0.2); color: #38bdf8; margin-right: 4px;"><?= htmlspecialchars($c) ?></span>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($incTypes)): ?>
                        <div>
                            <span style="font-size: 12px; color: var(--text-muted); margin-right: 8px;">Incident Categories:</span>
                            <?php foreach ($incTypes as $it): ?>
                                <span class="badge" style="background: rgba(255, 255, 255, 0.08); color: #fff; margin-right: 4px;"><?= htmlspecialchars($it) ?></span>
                            <?php endforeach; ?>
                            <?php if (!empty($incident['incident_other_text'])): ?>
                                <span style="font-size: 12px; color: var(--text-dim);">(Other: <?= htmlspecialchars($incident['incident_other_text']) ?>)</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($discReasons)): ?>
                        <div>
                            <span style="font-size: 12px; color: var(--text-muted); margin-right: 8px;">Disciplinary Items:</span>
                            <?php foreach ($discReasons as $dr): ?>
                                <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24; margin-right: 4px;"><?= htmlspecialchars($dr) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($notified)): ?>
                        <div>
                            <span style="font-size: 12px; color: var(--text-muted); margin-right: 8px;">Authorities Notified:</span>
                            <?php foreach ($notified as $nt): ?>
                                <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399; margin-right: 4px;"><?= htmlspecialchars($nt) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- People who saw the Incident (Witnesses) -->
            <?php if (!empty($incident['witness_1']) || !empty($incident['witness_2']) || !empty($incident['witness_3'])): ?>
            <div style="margin-bottom: 24px;">
                <h4 class="section-header-banner" style="color: #ffffff !important; font-weight: 800 !important; font-style: italic;">
                    <?= htmlspecialchars($settings['witness_section_title'] ?? 'People who saw the Incident') ?>
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
                    <div style="background: var(--bg-card-hover); border: 1px solid var(--border-subtle); padding: 12px 16px; border-radius: var(--radius-sm);">
                        <div style="font-size: 11px; text-transform: uppercase; color: var(--text-dim); margin-bottom: 4px; font-weight: 700;"><?= htmlspecialchars($settings['witness_1_label'] ?? 'Witness 1') ?></div>
                        <div style="font-size: 13.5px; font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($incident['witness_1'] ?: 'None recorded') ?></div>
                    </div>
                    <div style="background: var(--bg-card-hover); border: 1px solid var(--border-subtle); padding: 12px 16px; border-radius: var(--radius-sm);">
                        <div style="font-size: 11px; text-transform: uppercase; color: var(--text-dim); margin-bottom: 4px; font-weight: 700;"><?= htmlspecialchars($settings['witness_2_label'] ?? 'Witness 2') ?></div>
                        <div style="font-size: 13.5px; font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($incident['witness_2'] ?: 'None recorded') ?></div>
                    </div>
                    <div style="background: var(--bg-card-hover); border: 1px solid var(--border-subtle); padding: 12px 16px; border-radius: var(--radius-sm);">
                        <div style="font-size: 11px; text-transform: uppercase; color: var(--text-dim); margin-bottom: 4px; font-weight: 700;"><?= htmlspecialchars($settings['witness_3_label'] ?? 'Witness 3') ?></div>
                        <div style="font-size: 13.5px; font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($incident['witness_3'] ?: 'None recorded') ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Summary & Detailed Description -->
            <div style="margin-bottom: 24px;">
                <h4 class="section-header-banner" style="color: #ffffff !important; font-weight: 800 !important;">Executive Summary</h4>
                <div style="background: var(--bg-card-hover); border: 1px solid var(--border-subtle); padding: 14px 18px; border-radius: var(--radius-sm); font-size: 15px; font-weight: 600; margin-bottom: 18px;">
                    <?= htmlspecialchars($incident['summary_line']) ?>
                </div>

                <h4 class="section-header-banner" style="color: #ffffff !important; font-weight: 800 !important;">Patrol Officer Narrative (Detailed Occurrence)</h4>
                <div style="background: var(--bg-card-hover); border: 1px solid var(--border-subtle); padding: 18px; border-radius: var(--radius-sm); font-size: 14.5px; line-height: 1.7;">
<?= render_rich_description($incident['description']) ?>
                </div>
            </div>

            <!-- Attached Photographic Evidence Gallery -->
            <div>
                <h4 class="section-header-banner" style="color: #ffffff !important; font-weight: 800 !important;">Attached Photographic Evidence (<?= count($photos) ?> / <?= MAX_PHOTOS_PER_INCIDENT ?>)</h4>

                <?php if (empty($photos)): ?>
                    <p style="font-size: 13px; color: var(--text-dim); font-style: italic;">No photographic evidence was attached to this report.</p>
                <?php else: ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px;">
                        <?php foreach ($photos as $p): ?>
                            <?php 
                                $photoViewUrl = APP_URL . '/admin/serve_file.php?photo_id=' . (int)$p['id'];
                            ?>
                            <div style="background: rgba(15, 23, 42, 0.9); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); overflow: hidden; display: flex; flex-direction: column; justify-content: space-between;">
                                <div>
                                    <a href="<?= $photoViewUrl ?>" target="_blank" title="Click to view full high-res photo">
                                        <img src="<?= $photoViewUrl ?>" alt="Evidence Photo" style="width: 100%; height: 165px; object-fit: cover; display: block; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform='scale(1)'" onerror="this.onerror=null; this.src='<?= APP_URL ?>/<?= htmlspecialchars($p['file_path']) ?>';">
                                    </a>
                                    <div style="padding: 10px 10px 4px 10px; font-size: 11.5px; color: var(--text-muted);">
                                        <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 700; color: #fff;" title="<?= htmlspecialchars($p['original_filename']) ?>">
                                            📷 <?= htmlspecialchars($p['original_filename']) ?>
                                        </div>
                                        <?php 
                                            $pLat = !empty($p['latitude']) ? $p['latitude'] : ($incident['site_latitude'] ?? '');
                                            $pLng = !empty($p['longitude']) ? $p['longitude'] : ($incident['site_longitude'] ?? '');
                                            if (!empty($pLat) || !empty($pLng)):
                                        ?>
                                            <div style="color: #38bdf8; font-size: 11px; margin-top: 3px; font-weight: 600;">
                                                📍 <?= htmlspecialchars($pLat) ?>, <?= htmlspecialchars($pLng) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div style="color: var(--text-dim); font-size: 10.5px; margin-top: 2px;">
                                            <?= round($p['file_size'] / 1024) ?> KB &bull; <?= format_datetime($p['created_at']) ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Caption Feature Image by Image in Dashboard -->
                                <div style="padding: 8px 10px 10px 10px; background: rgba(0,0,0,0.25); border-top: 1px solid rgba(255,255,255,0.08);">
                                    <form method="POST" action="" onsubmit="savePhotoCaptionAjax(event, this);" style="margin: 0;">
                                        <input type="hidden" name="update_photo_caption" value="1">
                                        <input type="hidden" name="photo_id" value="<?= (int)$p['id'] ?>">
                                        <label style="font-size: 10.5px; text-transform: uppercase; color: #fbbf24; font-weight: 800; display: block; margin-bottom: 4px; letter-spacing: 0.5px;">
                                            🏷️ Edit Photo Caption
                                        </label>
                                        <div style="display: flex; gap: 4px; align-items: center;">
                                            <input type="text" name="caption" class="form-control" 
                                                   value="<?= htmlspecialchars($p['caption'] ?? '') ?>" 
                                                   placeholder="Enter photo caption..." 
                                                   style="font-size: 12px; padding: 4px 8px; height: 32px; font-weight: 600; flex: 1; color: #000 !important; background: #fff !important; border: 1.5px solid #0f172a !important;">
                                            <button type="submit" class="btn btn-primary btn-sm" 
                                                    style="font-size: 11.5px; padding: 4px 10px; height: 32px; white-space: nowrap; font-weight: 700;">
                                                💾 Save
                                            </button>
                                        </div>
                                        <div class="caption-status" style="display: none; font-size: 11px; color: #10b981; font-weight: 800; margin-top: 3px;">
                                            ✓ Caption Saved!
                                        </div>
                                        <?php if (!empty($p['caption'])): ?>
                                            <div class="caption-preview" style="font-size: 11px; color: #fbbf24; font-weight: 700; margin-top: 4px; line-height: 1.3;">
                                                Current: <?= htmlspecialchars($p['caption']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Official Management Sign-Off & Notes -->
    <div>
        <!-- Management Sign-Off Box -->
        <div class="card" style="margin-bottom: 24px;">
            <h3 class="section-header-banner" style="color: #ffffff !important; font-weight: 800 !important;">Official Management Sign-Off</h3>

            <form method="POST" action="" enctype="multipart/form-data" id="form_management_signoff" onsubmit="handleSignOffSubmit(event, this);">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="update_management" value="1">
                <input type="hidden" name="save_signoff" value="1">

                <div id="signoff_ajax_alert" class="alert" style="display: none; margin-bottom: 14px;"></div>
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" style="font-weight: 800; color: #000000 !important;">Status Transition</label>
                    <select name="status" class="form-control" style="font-weight: 700; width: 100%; box-sizing: border-box;">
                        <?php
                        $allowedStatuses = ['Submitted', 'Under Review', 'Follow-up Required', 'Resolved', 'Submitted to Client'];
                        foreach ($allowedStatuses as $st):
                        ?>
                            <option value="<?= $st ?>" <?= $incident['status'] === $st ? 'selected' : '' ?>>
                                <?= $st ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 1. Site Supervisor Sign-Off -->
                <div style="margin-bottom: 16px;">
                    <div style="font-size: 12px; font-weight: 800; color: var(--accent-cyan); margin-bottom: 10px; text-transform: uppercase;">
                        1. Site Supervisor Sign-Off
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 800; color: #000000 !important;">Supervisor Name &amp; ID</label>
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 8px;">
                            <input type="text" name="site_supervisor_name" class="form-control" placeholder="Supervisor Name" value="<?= htmlspecialchars($incident['site_supervisor_name'] ?? '') ?>" style="font-weight: 700; width: 100%; box-sizing: border-box;">
                            <input type="text" name="site_supervisor_id" class="form-control" placeholder="Staff ID" value="<?= htmlspecialchars($incident['site_supervisor_id'] ?? '') ?>" style="font-weight: 700; width: 100%; box-sizing: border-box;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 800; color: #000000 !important;">Sign-Off Date (dd/mm/yyyy)</label>
                        <input type="date" name="site_supervisor_date" class="form-control" value="<?= htmlspecialchars($incident['site_supervisor_date'] ?? '') ?>" style="font-weight: 700; width: 100%; box-sizing: border-box;">
                    </div>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label class="form-label" style="font-weight: 800; color: #000000 !important; margin-bottom: 0;">Supervisor Signature Image</label>
                            <?php if (!empty($incident['site_supervisor_signature'])): ?>
                                <button type="button" onclick="toggleRemoveExistingSig('sup')" id="btn_rem_existing_sup" title="Remove currently saved signature image" style="background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                    🗑️ <span id="txt_rem_existing_sup">Remove Attached Image</span>
                                </button>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="remove_supervisor_sig" id="input_remove_sup" value="0">
                        <?php if (!empty($incident['site_supervisor_signature'])): ?>
                            <div id="preview_existing_sup" style="margin-bottom: 8px; display: inline-flex; align-items: center; gap: 8px; padding: 4px 8px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px;">
                                <img src="<?= APP_URL ?>/<?= htmlspecialchars($incident['site_supervisor_signature']) ?>" alt="Supervisor Signature" style="max-height: 34px; display: block;">
                                <span style="font-size: 11px; color: #64748b; font-weight: 600;">(Current Attached Signature)</span>
                            </div>
                            <div id="msg_removed_sup" style="display: none; margin-bottom: 8px; padding: 4px 10px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 6px; color: #991b1b; font-size: 11.5px; font-weight: 700;">
                                ⚠️ Image marked for removal (Save to finalize or click "Undo")
                            </div>
                        <?php endif; ?>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <input type="file" id="site_supervisor_sig_file" name="site_supervisor_sig_file" accept="image/*" class="form-control" onchange="onSigFileSelected(this, 'sup')" style="font-size: 11.5px; padding: 5px 8px; font-weight: 600; flex: 1;">
                            <button type="button" title="Remove / Clear chosen file" onclick="clearSelectedSigFile('site_supervisor_sig_file', 'sup')" style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 5px 10px; cursor: pointer; color: #dc2626; font-weight: 700; font-size: 12px; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#f8fafc'">
                                🗑️ Remove
                            </button>
                        </div>
                        <div id="new_preview_sup" style="display: none; margin-top: 6px; align-items: center; gap: 8px; padding: 4px 8px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px;">
                            <img id="new_img_sup" src="" alt="New Preview" style="max-height: 30px; display: block; border-radius: 4px;">
                            <span id="new_filename_sup" style="font-size: 11.5px; color: #166534; font-weight: 600; flex: 1;"></span>
                            <button type="button" onclick="clearSelectedSigFile('site_supervisor_sig_file', 'sup')" style="background: none; border: none; color: #dc2626; font-weight: 800; font-size: 14px; cursor: pointer;" title="Remove this file">✕</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 800; color: #000000 !important;">Recommendation / Action Taken</label>
                        <textarea name="recommendation_action_taken" class="form-control" rows="2" placeholder="Corrective actions initiated at post..." style="font-weight: 700; width: 100%; box-sizing: border-box; text-align: left !important; vertical-align: top !important; padding: 8px 12px !important; line-height: 1.4 !important; font-family: inherit !important; resize: vertical;"><?= htmlspecialchars($incident['recommendation_action_taken'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- 2. Operations Manager Sign-Off -->
                <div style="border-top: 1px solid var(--border-subtle); padding-top: 14px; margin-top: 14px;">
                    <div style="font-size: 12px; font-weight: 800; color: var(--accent-cyan); margin-bottom: 10px; text-transform: uppercase;">
                        2. Operations Manager Sign-Off
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 800; color: #000000 !important;">Operations Manager Name &amp; Staff ID</label>
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 8px;">
                            <input type="text" name="operations_manager_name" class="form-control" placeholder="e.g. Tariq Mansoor" value="<?= htmlspecialchars($incident['operations_manager_name'] ?? '') ?>" style="font-weight: 700; width: 100%; box-sizing: border-box;">
                            <input type="text" name="operations_manager_id" class="form-control" placeholder="Staff ID (e.g. OPS-01)" value="<?= htmlspecialchars($incident['operations_manager_id'] ?? '') ?>" style="font-weight: 700; width: 100%; box-sizing: border-box;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 800; color: #000000 !important;">Ops Sign-off Date (dd/mm/yyyy)</label>
                        <input type="date" name="operations_manager_date" class="form-control" value="<?= htmlspecialchars($incident['operations_manager_date'] ?? '') ?>" style="font-weight: 700; width: 100%; box-sizing: border-box;">
                    </div>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label class="form-label" style="font-weight: 800; color: #000000 !important; margin-bottom: 0;">Operations Manager Signature Image</label>
                            <?php if (!empty($incident['operations_manager_signature'])): ?>
                                <button type="button" onclick="toggleRemoveExistingSig('ops')" id="btn_rem_existing_ops" title="Remove currently saved signature image" style="background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                    🗑️ <span id="txt_rem_existing_ops">Remove Attached Image</span>
                                </button>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="remove_ops_sig" id="input_remove_ops" value="0">
                        <?php if (!empty($incident['operations_manager_signature'])): ?>
                            <div id="preview_existing_ops" style="margin-bottom: 8px; display: inline-flex; align-items: center; gap: 8px; padding: 4px 8px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px;">
                                <img src="<?= APP_URL ?>/<?= htmlspecialchars($incident['operations_manager_signature']) ?>" alt="Ops Signature" style="max-height: 34px; display: block;">
                                <span style="font-size: 11px; color: #64748b; font-weight: 600;">(Current Attached Signature)</span>
                            </div>
                            <div id="msg_removed_ops" style="display: none; margin-bottom: 8px; padding: 4px 10px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 6px; color: #991b1b; font-size: 11.5px; font-weight: 700;">
                                ⚠️ Image marked for removal (Save to finalize or click "Undo")
                            </div>
                        <?php endif; ?>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <input type="file" id="operations_manager_sig_file" name="operations_manager_sig_file" accept="image/*" class="form-control" onchange="onSigFileSelected(this, 'ops')" style="font-size: 11.5px; padding: 5px 8px; font-weight: 600; flex: 1;">
                            <button type="button" title="Remove / Clear chosen file" onclick="clearSelectedSigFile('operations_manager_sig_file', 'ops')" style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 5px 10px; cursor: pointer; color: #dc2626; font-weight: 700; font-size: 12px; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#f8fafc'">
                                🗑️ Remove
                            </button>
                        </div>
                        <div id="new_preview_ops" style="display: none; margin-top: 6px; align-items: center; gap: 8px; padding: 4px 8px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px;">
                            <img id="new_img_ops" src="" alt="New Preview" style="max-height: 30px; display: block; border-radius: 4px;">
                            <span id="new_filename_ops" style="font-size: 11.5px; color: #166534; font-weight: 600; flex: 1;"></span>
                            <button type="button" onclick="clearSelectedSigFile('operations_manager_sig_file', 'ops')" style="background: none; border: none; color: #dc2626; font-weight: 800; font-size: 14px; cursor: pointer;" title="Remove this file">✕</button>
                        </div>
                    </div>
                </div>

                <!-- 3. Client Representative Sign-Off -->
                <div style="border-top: 1px solid var(--border-subtle); padding-top: 14px; margin-top: 14px;">
                    <div style="font-size: 12px; font-weight: 800; color: var(--accent-cyan); margin-bottom: 10px; text-transform: uppercase;">
                        3. Client Representative Sign-Off
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 800; color: #000000 !important;">Client Representative Name &amp; ID</label>
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 8px;">
                            <input type="text" name="client_manager_name" class="form-control" placeholder="Client Manager Name" value="<?= htmlspecialchars($incident['client_manager_name'] ?? '') ?>" style="font-weight: 700; width: 100%; box-sizing: border-box;">
                            <input type="text" name="client_manager_id" class="form-control" placeholder="Client ID / Badge" value="<?= htmlspecialchars($incident['client_manager_id'] ?? '') ?>" style="font-weight: 700; width: 100%; box-sizing: border-box;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 800; color: #000000 !important;">Client Action Taken</label>
                        <textarea name="client_manager_action_taken" class="form-control" rows="2" placeholder="Client acknowledgement or notes..." style="font-weight: 700; width: 100%; box-sizing: border-box; text-align: left !important; vertical-align: top !important; padding: 8px 12px !important; line-height: 1.4 !important; font-family: inherit !important; resize: vertical;"><?= htmlspecialchars($incident['client_manager_action_taken'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 800; color: #000000 !important;">Client Date (dd/mm/yyyy)</label>
                        <input type="date" name="client_manager_date" class="form-control" value="<?= htmlspecialchars($incident['client_manager_date'] ?? '') ?>" style="font-weight: 700; width: 100%; box-sizing: border-box;">
                    </div>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label class="form-label" style="font-weight: 800; color: #000000 !important; margin-bottom: 0;">Client Signature Image</label>
                            <?php if (!empty($incident['client_manager_signature'])): ?>
                                <button type="button" onclick="toggleRemoveExistingSig('client')" id="btn_rem_existing_client" title="Remove currently saved signature image" style="background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                    🗑️ <span id="txt_rem_existing_client">Remove Attached Image</span>
                                </button>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="remove_client_sig" id="input_remove_client" value="0">
                        <?php if (!empty($incident['client_manager_signature'])): ?>
                            <div id="preview_existing_client" style="margin-bottom: 8px; display: inline-flex; align-items: center; gap: 8px; padding: 4px 8px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px;">
                                <img src="<?= APP_URL ?>/<?= htmlspecialchars($incident['client_manager_signature']) ?>" alt="Client Signature" style="max-height: 34px; display: block;">
                                <span style="font-size: 11px; color: #64748b; font-weight: 600;">(Current Attached Signature)</span>
                            </div>
                            <div id="msg_removed_client" style="display: none; margin-bottom: 8px; padding: 4px 10px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 6px; color: #991b1b; font-size: 11.5px; font-weight: 700;">
                                ⚠️ Image marked for removal (Save to finalize or click "Undo")
                            </div>
                        <?php endif; ?>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <input type="file" id="client_manager_sig_file" name="client_manager_sig_file" accept="image/*" class="form-control" onchange="onSigFileSelected(this, 'client')" style="font-size: 11.5px; padding: 5px 8px; font-weight: 600; flex: 1;">
                            <button type="button" title="Remove / Clear chosen file" onclick="clearSelectedSigFile('client_manager_sig_file', 'client')" style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 5px 10px; cursor: pointer; color: #dc2626; font-weight: 700; font-size: 12px; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#f8fafc'">
                                🗑️ Remove
                            </button>
                        </div>
                        <div id="new_preview_client" style="display: none; margin-top: 6px; align-items: center; gap: 8px; padding: 4px 8px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px;">
                            <img id="new_img_client" src="" alt="New Preview" style="max-height: 30px; display: block; border-radius: 4px;">
                            <span id="new_filename_client" style="font-size: 11.5px; color: #166534; font-weight: 600; flex: 1;"></span>
                            <button type="button" onclick="clearSelectedSigFile('client_manager_sig_file', 'client')" style="background: none; border: none; color: #dc2626; font-weight: 800; font-size: 14px; cursor: pointer;" title="Remove this file">✕</button>
                        </div>
                    </div>
                </div>

                <button type="submit" name="save_signoff" value="1" class="btn btn-primary" style="width: 100%; margin-top: 14px; font-weight: 800;">
                    Save Management Sign-Off
                </button>
            </form>
        </div>

        <!-- Internal Notes Box -->
        <div class="card">
            <h3 class="section-header-banner" style="color: #ffffff !important; font-weight: 800 !important;">Internal Audit Notes</h3>

            <form method="POST" action="" style="margin-bottom: 18px;">
                <input type="hidden" name="add_note" value="1">
                <div class="form-group" style="margin-bottom: 10px;">
                    <textarea name="note_text" class="form-control" rows="3" placeholder="Add confidential internal note or follow-up status..." required></textarea>
                </div>
                <button type="submit" class="btn btn-secondary btn-sm" style="width: 100%;">
                    Post Note
                </button>
            </form>

            <div style="display: flex; flex-direction: column; gap: 12px; max-height: 380px; overflow-y: auto;">
                <?php if (empty($notes)): ?>
                    <p style="font-size: 12px; color: var(--text-dim); text-align: center; padding: 16px;">No internal follow-up notes yet.</p>
                <?php else: ?>
                    <?php foreach ($notes as $n): ?>
                        <div style="background: rgba(15, 23, 42, 0.7); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 12px;">
                            <div style="display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 6px;">
                                <strong style="color: var(--accent-cyan);"><?= htmlspecialchars($n['admin_name']) ?></strong>
                                <span style="color: var(--text-dim);"><?= time_ago($n['created_at']) ?></span>
                            </div>
                            <div style="font-size: 13px; color: var(--text-main); line-height: 1.5; white-space: pre-wrap;">
                                <?= htmlspecialchars($n['note']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>


<script>
function clearSelectedSigFile(inputId, role) {
    var input = document.getElementById(inputId);
    if (input) {
        input.value = "";
    }
    var newPrev = document.getElementById("new_preview_" + role);
    if (newPrev) {
        newPrev.style.display = "none";
    }
    var newImg = document.getElementById("new_img_" + role);
    if (newImg) {
        newImg.src = "";
    }
    var newName = document.getElementById("new_filename_" + role);
    if (newName) {
        newName.innerText = "";
    }
}

function onSigFileSelected(input, role) {
    var newPrev = document.getElementById("new_preview_" + role);
    var newImg = document.getElementById("new_img_" + role);
    var newName = document.getElementById("new_filename_" + role);
    
    if (input.files && input.files[0]) {
        var file = input.files[0];
        if (newName) newName.innerText = file.name + " (" + Math.round(file.size / 1024) + " KB)";
        if (newImg) {
            var reader = new FileReader();
            reader.onload = function(e) {
                newImg.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
        if (newPrev) newPrev.style.display = "inline-flex";
        
        var inputRem = document.getElementById("input_remove_" + role);
        if (inputRem && inputRem.value === "1") {
            toggleRemoveExistingSig(role);
        }
    } else {
        if (newPrev) newPrev.style.display = "none";
    }
}

function toggleRemoveExistingSig(role) {
    var inputRem = document.getElementById("input_remove_" + role);
    var prev = document.getElementById("preview_existing_" + role);
    var msg = document.getElementById("msg_removed_" + role);
    var btn = document.getElementById("btn_rem_existing_" + role);
    var txt = document.getElementById("txt_rem_existing_" + role);

    if (!inputRem) return;

    if (inputRem.value === "0") {
        inputRem.value = "1";
        if (prev) prev.style.opacity = "0.35";
        if (msg) msg.style.display = "block";
        if (btn) {
            btn.style.background = "#ecfdf5";
            btn.style.borderColor = "#a7f3d0";
            btn.style.color = "#059669";
        }
        if (txt) txt.innerText = "↩ Undo Remove";
    } else {
        inputRem.value = "0";
        if (prev) prev.style.opacity = "1";
        if (msg) msg.style.display = "none";
        if (btn) {
            btn.style.background = "#fef2f2";
            btn.style.borderColor = "#fecaca";
            btn.style.color = "#dc2626";
        }
        if (txt) txt.innerText = "Remove Attached Image";
    }
}
function handleSignOffSubmit(e, form) {
    if (window.fetch && window.FormData) {
        e.preventDefault();
        var btn = form.querySelector('button[type="submit"]');
        var originalBtnHtml = btn ? btn.innerHTML : 'Save Management Sign-Off';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '⏳ Saving Management Sign-Off...';
        }

        var alertBox = document.getElementById('signoff_ajax_alert');
        if (alertBox) {
            alertBox.style.display = 'none';
            alertBox.className = 'alert';
        }

        var formData = new FormData(form);
        formData.append('ajax_signoff', '1');

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.text().then(function(text) {
                try {
                    return JSON.parse(text);
                } catch (parseErr) {
                    return { success: response.ok, raw: text };
                }
            });
        })
        .then(function(data) {
            if (btn) btn.disabled = false;
            if (data.success) {
                if (btn) {
                    btn.innerHTML = '✓ Saved Successfully!';
                    btn.style.background = '#10b981';
                    setTimeout(function() {
                        btn.innerHTML = originalBtnHtml;
                        btn.style.background = '';
                    }, 3500);
                }
                if (alertBox) {
                    alertBox.className = 'alert alert-success';
                    alertBox.innerHTML = '<strong>✓ Success:</strong> ' + (data.message || 'Management sign-off and report status updated successfully.');
                    alertBox.style.display = 'block';
                    alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
                if (data.status_badge) {
                    var badgeEls = document.querySelectorAll('.live-patrol-banner span.badge');
                    if (badgeEls && badgeEls.length > 1) {
                        badgeEls[1].outerHTML = data.status_badge;
                    }
                }
            } else {
                if (btn) btn.innerHTML = originalBtnHtml;
                if (alertBox) {
                    alertBox.className = 'alert alert-danger';
                    alertBox.innerHTML = '<strong>⚠️ Save Notice:</strong> ' + (data.error || data.raw || 'Encountered issue while saving.');
                    alertBox.style.display = 'block';
                    alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        })
        .catch(function(err) {
            console.warn('AJAX submit issue, submitting standard:', err);
            if (btn) btn.innerHTML = 'Saving...';
            form.submit();
        });
        return false;
    }
    return true;
}

function savePhotoCaptionAjax(e, form) {
    if (e && e.preventDefault) e.preventDefault();
    var formData = new FormData(form);
    formData.append('ajax', '1');
    var statusEl = form.querySelector('.caption-status');
    var btn = form.querySelector('button[type="submit"]');
    if (btn) btn.disabled = true;
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (btn) btn.disabled = false;
        if (statusEl) {
            statusEl.style.display = 'block';
            setTimeout(function() { statusEl.style.display = 'none'; }, 3000);
        }
        var prev = form.querySelector('.caption-preview');
        if (prev && data.caption) {
            prev.innerText = 'Current: ' + data.caption;
            prev.style.display = 'block';
        }
    })
    .catch(function() {
        if (btn) btn.disabled = false;
        form.submit();
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
