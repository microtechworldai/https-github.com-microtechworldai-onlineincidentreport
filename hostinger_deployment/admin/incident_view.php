<?php
/**
 * Detailed Incident Dossier & Management Sign-Off
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header("Location: " . APP_URL . "/admin/incidents.php");
    exit;
}

$db = get_db();
$msg = '';
$msgType = 'success';

// Handle Management Sign-Off & Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_management'])) {
    $newStatus = trim($_POST['status'] ?? 'Submitted');
    $supervisorName = trim($_POST['site_supervisor_name'] ?? '');
    $supervisorId = trim($_POST['site_supervisor_id'] ?? '');
    $supervisorDate = !empty($_POST['site_supervisor_date']) ? $_POST['site_supervisor_date'] : null;
    $recommendationAction = trim($_POST['recommendation_action_taken'] ?? '');
    
    $opsManagerName = trim($_POST['operations_manager_name'] ?? '');
    $opsManagerDate = !empty($_POST['operations_manager_date']) ? $_POST['operations_manager_date'] : null;

    $clientManagerName = trim($_POST['client_manager_name'] ?? '');
    $clientManagerAction = trim($_POST['client_manager_action_taken'] ?? '');
    $clientManagerDate = !empty($_POST['client_manager_date']) ? $_POST['client_manager_date'] : null;

    $stmtUpdate = $db->prepare("
        UPDATE incidents SET
            status = ?,
            site_supervisor_name = ?,
            site_supervisor_id = ?,
            site_supervisor_date = ?,
            recommendation_action_taken = ?,
            operations_manager_name = ?,
            operations_manager_date = ?,
            client_manager_name = ?,
            client_manager_action_taken = ?,
            client_manager_date = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmtUpdate->execute([
        $newStatus,
        $supervisorName,
        $supervisorId,
        $supervisorDate,
        $recommendationAction,
        $opsManagerName,
        $opsManagerDate,
        $clientManagerName,
        $clientManagerAction,
        $clientManagerDate,
        $id
    ]);

    log_audit('UPDATE_MANAGEMENT_SIGNOFF', 'INCIDENT', $id, [
        'status' => $newStatus,
        'supervisor' => $supervisorName
    ]);
    $msg = "Management sign-off and report status updated successfully.";
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

// Fetch Incident Record
$stmt = $db->prepare("
    SELECT i.*, s.client_name, s.client_contact, s.latitude as site_latitude, s.longitude as site_longitude, s.code as site_official_code
    FROM incidents i 
    LEFT JOIN sites s ON s.id = i.site_id 
    WHERE i.id = ? 
    LIMIT 1
");
$stmt->execute([$id]);
$incident = $stmt->fetch();

if (!$incident) {
    die("Incident not found.");
}

// Decode JSON fields safely
$causes = json_decode($incident['cause_main'] ?: '[]', true) ?: [];
$discReasons = json_decode($incident['disciplinary_reasons'] ?: '[]', true) ?: [];
$incTypes = json_decode($incident['incident_types'] ?: '[]', true) ?: [];
$notified = json_decode($incident['who_notified'] ?: '[]', true) ?: [];

// Fetch Attached Photos
$stmtPhotos = $db->prepare("SELECT * FROM incident_photos WHERE incident_id = ? ORDER BY sort_order ASC, id ASC");
$stmtPhotos->execute([$id]);
$photos = $stmtPhotos->fetchAll();

// Fetch Notes
$stmtNotes = $db->prepare("SELECT * FROM incident_notes WHERE incident_id = ? ORDER BY created_at DESC");
$stmtNotes->execute([$id]);
$notes = $stmtNotes->fetchAll();

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
            <!-- Reference Header -->
            <div style="display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 12px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 16px; margin-bottom: 20px;">
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--accent-cyan); font-weight: 700;">Official Reference</span>
                    <h2 style="font-family: 'JetBrains Mono', monospace; font-size: 22px; font-weight: 800; margin-top: 4px;">
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
                <h4 style="font-size: 13px; text-transform: uppercase; color: var(--text-dim); letter-spacing: 0.5px; margin-bottom: 10px;">Classification & Triggers</h4>
                
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

            <!-- Summary & Detailed Description -->
            <div style="margin-bottom: 24px;">
                <h4 style="font-size: 13px; text-transform: uppercase; color: var(--text-dim); letter-spacing: 0.5px; margin-bottom: 8px;">Executive Summary</h4>
                <div style="background: var(--bg-card-hover); border: 1px solid var(--border-subtle); padding: 14px 18px; border-radius: var(--radius-sm); font-size: 15px; font-weight: 600; margin-bottom: 18px;">
                    <?= htmlspecialchars($incident['summary_line']) ?>
                </div>

                <h4 style="font-size: 13px; text-transform: uppercase; color: var(--text-dim); letter-spacing: 0.5px; margin-bottom: 8px;">Patrol Officer Narrative (Detailed Occurrence)</h4>
                <div style="background: var(--bg-card-hover); border: 1px solid var(--border-subtle); padding: 18px; border-radius: var(--radius-sm); font-size: 14.5px; line-height: 1.7;">
<?= render_rich_description($incident['description']) ?>
                </div>
            </div>

            <!-- Attached Photographic Evidence Gallery -->
            <div>
                <h4 style="font-size: 13px; text-transform: uppercase; color: var(--text-dim); letter-spacing: 0.5px; margin-bottom: 12px;">
                    Attached Photographic Evidence (<?= count($photos) ?> / <?= MAX_PHOTOS_PER_INCIDENT ?>)
                </h4>

                <?php if (empty($photos)): ?>
                    <p style="font-size: 13px; color: var(--text-dim); font-style: italic;">No photographic evidence was attached to this report.</p>
                <?php else: ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px;">
                        <?php foreach ($photos as $p): ?>
                            <div style="background: rgba(15, 23, 42, 0.9); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); overflow: hidden;">
                                <a href="<?= APP_URL ?>/<?= htmlspecialchars($p['file_path']) ?>" target="_blank">
                                    <img src="<?= APP_URL ?>/<?= htmlspecialchars($p['file_path']) ?>" alt="Evidence Photo" style="width: 100%; height: 160px; object-fit: cover; display: block; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform='scale(1)'">
                                </a>
                                <div style="padding: 10px; font-size: 11.5px; color: var(--text-muted);">
                                    <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600;" title="<?= htmlspecialchars($p['original_filename']) ?>">
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
            <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                ✍️ Official Management Sign-Off
            </h3>

            <form method="POST" action="">
                <input type="hidden" name="update_management" value="1">

                <div class="form-group">
                    <label class="form-label">Investigation / Lifecycle Status</label>
                    <select name="status" class="form-control">
                        <option value="Submitted" <?= $incident['status'] === 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                        <option value="Under Review" <?= $incident['status'] === 'Under Review' ? 'selected' : '' ?>>Under Review</option>
                        <option value="Follow-up Required" <?= $incident['status'] === 'Follow-up Required' ? 'selected' : '' ?>>Follow-up Required</option>
                        <option value="Resolved" <?= $incident['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                        <option value="Submitted to Client" <?= $incident['status'] === 'Submitted to Client' ? 'selected' : '' ?>>Submitted to Client</option>
                    </select>
                </div>

                <!-- Site Supervisor -->
                <div style="border-top: 1px solid var(--border-subtle); padding-top: 14px; margin-top: 14px;">
                    <div style="font-size: 12px; font-weight: 700; color: var(--accent-cyan); margin-bottom: 10px; text-transform: uppercase;">
                        1. Site Supervisor Sign-Off
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Supervisor Name & ID</label>
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 8px;">
                            <input type="text" name="site_supervisor_name" class="form-control" placeholder="Supervisor Name" value="<?= htmlspecialchars($incident['site_supervisor_name'] ?? '') ?>">
                            <input type="text" name="site_supervisor_id" class="form-control" placeholder="Staff ID" value="<?= htmlspecialchars($incident['site_supervisor_id'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Sign-Off Date</label>
                        <input type="date" name="site_supervisor_date" class="form-control" value="<?= htmlspecialchars($incident['site_supervisor_date'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Recommendation / Action Taken</label>
                        <textarea name="recommendation_action_taken" class="form-control" rows="2" placeholder="Corrective actions initiated at post..."><?= htmlspecialchars($incident['recommendation_action_taken'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Operations Manager -->
                <div style="border-top: 1px solid var(--border-subtle); padding-top: 14px; margin-top: 14px;">
                    <div style="font-size: 12px; font-weight: 700; color: var(--accent-cyan); margin-bottom: 10px; text-transform: uppercase;">
                        2. Operations Manager Sign-Off
                    </div>

                    <div class="form-group">
                        <label class="form-label">Operations Manager Name</label>
                        <input type="text" name="operations_manager_name" class="form-control" placeholder="e.g. Tariq Mansoor" value="<?= htmlspecialchars($incident['operations_manager_name'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Ops Sign-off Date</label>
                        <input type="date" name="operations_manager_date" class="form-control" value="<?= htmlspecialchars($incident['operations_manager_date'] ?? '') ?>">
                    </div>
                </div>

                <!-- Client Representative -->
                <div style="border-top: 1px solid var(--border-subtle); padding-top: 14px; margin-top: 14px;">
                    <div style="font-size: 12px; font-weight: 700; color: var(--accent-cyan); margin-bottom: 10px; text-transform: uppercase;">
                        3. Client Representative Sign-Off
                    </div>

                    <div class="form-group">
                        <label class="form-label">Client Representative Name</label>
                        <input type="text" name="client_manager_name" class="form-control" placeholder="Client Manager Name" value="<?= htmlspecialchars($incident['client_manager_name'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Client Action Taken</label>
                        <textarea name="client_manager_action_taken" class="form-control" rows="2" placeholder="Client acknowledgement or notes..."><?= htmlspecialchars($incident['client_manager_action_taken'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Client Date</label>
                        <input type="date" name="client_manager_date" class="form-control" value="<?= htmlspecialchars($incident['client_manager_date'] ?? '') ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 14px;">
                    Save Management Sign-Off
                </button>
            </form>
        </div>

        <!-- Internal Notes Box -->
        <div class="card">
            <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 16px;">
                💬 Internal Audit Notes
            </h3>

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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
