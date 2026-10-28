<?php
/**
 * Edit Incident Dossier
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
$settings = get_system_settings();
$error = '';
$success = '';

// Fetch incident
$stmt = $db->prepare("
    SELECT i.*, s.client_name, s.client_contact 
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $summary = trim($_POST['summary_line'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $incidentDate = trim($_POST['incident_date'] ?? '');
    $incidentTime = trim($_POST['incident_time'] ?? '');
    $shift = trim($_POST['shift'] ?? 'Day');
    $priority = trim($_POST['priority'] ?? 'Medium');
    $status = trim($_POST['status'] ?? 'Submitted');
    
    $causeMain = isset($_POST['cause_main']) ? json_encode(array_values($_POST['cause_main'])) : '[]';
    $discReasons = isset($_POST['disciplinary_reasons']) ? json_encode(array_values($_POST['disciplinary_reasons'])) : '[]';
    $incTypes = isset($_POST['incident_types']) ? json_encode(array_values($_POST['incident_types'])) : '[]';
    $notified = isset($_POST['who_notified']) ? json_encode(array_values($_POST['who_notified'])) : '[]';
    
    $discOther = trim($_POST['disciplinary_other_text'] ?? '');
    $incOther = trim($_POST['incident_other_text'] ?? '');
    $notifiedOther = trim($_POST['notified_other_text'] ?? '');

    $witness1 = trim($_POST['witness_1'] ?? '');
    $witness2 = trim($_POST['witness_2'] ?? '');
    $witness3 = trim($_POST['witness_3'] ?? '');

    $supName = trim($_POST['site_supervisor_name'] ?? '');
    $supId = trim($_POST['site_supervisor_id'] ?? '');
    $supDate = !empty($_POST['site_supervisor_date']) ? $_POST['site_supervisor_date'] : null;
    $supRec = trim($_POST['recommendation_action_taken'] ?? '');

    $opsName = trim($_POST['operations_manager_name'] ?? '');
    $opsId = trim($_POST['operations_manager_id'] ?? '');
    $opsDate = !empty($_POST['operations_manager_date']) ? $_POST['operations_manager_date'] : null;

    $clientName = trim($_POST['client_manager_name'] ?? '');
    $clientId = trim($_POST['client_manager_id'] ?? '');
    $clientAction = trim($_POST['client_manager_action_taken'] ?? '');
    $clientDate = !empty($_POST['client_manager_date']) ? $_POST['client_manager_date'] : null;

    if (empty($summary) || empty($description) || empty($incidentDate) || empty($incidentTime)) {
        $error = 'Please fill all required fields (Summary, Description, Date, Time).';
    } else {
        $updateStmt = $db->prepare("
            UPDATE incidents SET
                summary_line = ?,
                description = ?,
                incident_date = ?,
                incident_time = ?,
                shift = ?,
                priority = ?,
                status = ?,
                cause_main = ?,
                disciplinary_reasons = ?,
                disciplinary_other_text = ?,
                incident_types = ?,
                incident_other_text = ?,
                who_notified = ?,
                notified_other_text = ?,
                witness_1 = ?,
                witness_2 = ?,
                witness_3 = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([
            $summary,
            $description,
            $incidentDate,
            $incidentTime,
            $shift,
            $priority,
            $status,
            $causeMain,
            $discReasons,
            $discOther,
            $incTypes,
            $incOther,
            $notified,
            $notifiedOther,
            $witness1,
            $witness2,
            $witness3,
            $id
        ]);

        log_audit('EDIT_INCIDENT', 'INCIDENT', $id, ['summary' => $summary]);
        $success = 'Incident updated successfully!';
        
        // Refresh
        $stmt->execute([$id]);
        $incident = $stmt->fetch();
    }
}

$causes = json_decode($incident['cause_main'] ?: '[]', true) ?: [];
$discReasons = json_decode($incident['disciplinary_reasons'] ?: '[]', true) ?: [];
$incTypes = json_decode($incident['incident_types'] ?: '[]', true) ?: [];
$notified = json_decode($incident['who_notified'] ?: '[]', true) ?: [];

$pageTitle = "Edit Incident " . htmlspecialchars($incident['ref_number']);
$pageSubtitle = "Modify incident details and classifications";

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 900px; margin: 0 auto;">
    <!-- Header Banner Styled matching Image 1: #081426 background & #fbbf24 gold text -->
    <div style="background: #081426 !important; color: var(--brand-primary); padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; border: 1px solid rgba(251, 191, 36, 0.35); box-shadow: 0 4px 12px rgba(0,0,0,0.18);">
        <div>
            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1.2px; font-weight: 800; color: var(--brand-primary);">Edit Incident Report</div>
            <h2 style="font-family: 'JetBrains Mono', monospace; font-size: 20px; font-weight: 800; color: #ffffff !important; margin: 3px 0 0 0; letter-spacing: 0.5px;">
                <?= htmlspecialchars($incident['ref_number']) ?>
            </h2>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="<?= APP_URL ?>/admin/incident_view.php?id=<?= $id ?>" class="btn btn-secondary btn-sm" style="color: #ffffff !important; background: rgba(255,255,255,0.12); border-color: rgba(255,255,255,0.25);">&larr; Back to Dossier</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px;">
            <div>
                <strong><?= htmlspecialchars($success) ?></strong>
                <div style="font-size: 12.5px; opacity: 0.9; margin-top: 2px;">Your edits are recorded and the official PDF dossier has been updated.</div>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="<?= APP_URL ?>/admin/incident_pdf.php?id=<?= $id ?>" target="_blank" class="btn btn-primary btn-sm">
                    📄 View PDF Dossier
                </a>
                <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-secondary btn-sm">
                    📊 Dashboard
                </a>
            </div>
        </div>
    <?php endif; ?>

    
<style>
/* Edit Incident Dashboard - Bold and Dark Black Features (Image 5) */
.incident-edit-card {
    background: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 10px !important;
    box-shadow: 0 4px 16px rgba(0,0,0,0.08) !important;
}

.incident-edit-card .form-label {
    color: #000000 !important;
    font-weight: 800 !important;
    font-size: 13.5px !important;
    letter-spacing: 0.1px !important;
    margin-bottom: 6px !important;
    display: block !important;
}

.incident-edit-card .form-control,
.incident-edit-card select.form-control,
.incident-edit-card input[type="date"],
.incident-edit-card input[type="time"],
.incident-edit-card input[type="text"],
.incident-edit-card textarea.form-control {
    color: #000000 !important;
    font-weight: 700 !important;
    font-size: 14px !important;
    background: #ffffff !important;
    border: 1.5px solid #0f172a !important;
    border-radius: 6px !important;
    padding: 9px 12px !important;
}

.incident-edit-card .form-control:focus,
.incident-edit-card select.form-control:focus {
    border-color: #081426 !important;
    box-shadow: 0 0 0 3px rgba(8, 20, 38, 0.15) !important;
}

.incident-edit-card .form-control::placeholder {
    color: #475569 !important;
    font-weight: 600 !important;
}

.incident-edit-card .feature-option-label {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    font-size: 13.5px !important;
    color: #000000 !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    user-select: none !important;
}

.incident-edit-card .feature-option-label input[type="checkbox"] {
    width: 17px !important;
    height: 17px !important;
    accent-color: #081426 !important;
    cursor: pointer !important;
}

.incident-edit-card .rich-editor-box {
    color: #000000 !important;
    background: #ffffff !important;
    border: 1.5px solid #0f172a !important;
    font-weight: 500 !important;
    font-size: 14px !important;
    min-height: 180px !important;
}

.incident-edit-card .rich-toolbar {
    background: #f1f5f9 !important;
    border: 1.5px solid #0f172a !important;
    border-bottom: none !important;
}
</style>

    <div class="card incident-edit-card">
        <form method="POST" action="">
                        <!-- Section Heading 1: Incident Timeline & Priority -->
            <h4 class="section-header-banner" style="color: #ffffff !important; font-weight: 800 !important;">Incident Timeline &amp; Priority</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
                <div class="form-group">
                    <label class="form-label">Incident Date (dd/mm/yyyy)</label>
                    <input type="date" name="incident_date" class="form-control" value="<?= htmlspecialchars($incident['incident_date']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Incident Time (24h)</label>
                    <input type="time" name="incident_time" class="form-control" value="<?= htmlspecialchars($incident['incident_time']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Shift</label>
                    <select name="shift" class="form-control">
                        <?php foreach ($settings['shift_options'] as $sh): ?>
                            <option value="<?= htmlspecialchars($sh) ?>" <?= $incident['shift'] === $sh ? 'selected' : '' ?>><?= htmlspecialchars($sh) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-control">
                        <option value="Critical" <?= $incident['priority'] === 'Critical' ? 'selected' : '' ?>>Critical</option>
                        <option value="High" <?= $incident['priority'] === 'High' ? 'selected' : '' ?>>High</option>
                        <option value="Medium" <?= $incident['priority'] === 'Medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="Low" <?= $incident['priority'] === 'Low' ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Lifecycle Status</label>
                <select name="status" class="form-control">
                    <option value="Submitted" <?= $incident['status'] === 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                    <option value="Under Review" <?= $incident['status'] === 'Under Review' ? 'selected' : '' ?>>Under Review</option>
                    <option value="Follow-up Required" <?= $incident['status'] === 'Follow-up Required' ? 'selected' : '' ?>>Follow-up Required</option>
                    <option value="Resolved" <?= $incident['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                    <option value="Submitted to Client" <?= $incident['status'] === 'Submitted to Client' ? 'selected' : '' ?>>Submitted to Client</option>
                </select>
            </div>

                        <!-- Section Heading 2: Classification & Triggers -->
            <h4 class="section-header-banner" style="color: #ffffff !important; font-weight: 800 !important;">Classification &amp; Triggers</h4>
            <!-- Main Causes -->
            <div class="form-group">
                <label class="form-label">Main Causes</label>
                <div style="display: flex; gap: 14px; flex-wrap: wrap;">
                    <?php foreach ($settings['cause_options'] as $opt): ?>
                        <label class="feature-option-label">
                            <input type="checkbox" name="cause_main[]" value="<?= htmlspecialchars($opt) ?>" <?= in_array($opt, $causes) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($opt) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Incident Categories -->
            <div class="form-group">
                <label class="form-label">Incident Categories</label>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px;">
                    <?php foreach ($settings['incident_options'] as $opt): ?>
                        <label class="feature-option-label">
                            <input type="checkbox" name="incident_types[]" value="<?= htmlspecialchars($opt) ?>" <?= in_array($opt, $incTypes) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($opt) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <input type="text" name="incident_other_text" class="form-control" style="margin-top: 8px;" placeholder="Other category details..." value="<?= htmlspecialchars($incident['incident_other_text'] ?? '') ?>">
            </div>

            <!-- Disciplinary -->
            <div class="form-group">
                <label class="form-label">Disciplinary Reasons</label>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px;">
                    <?php foreach ($settings['disciplinary_options'] as $opt): ?>
                        <label class="feature-option-label">
                            <input type="checkbox" name="disciplinary_reasons[]" value="<?= htmlspecialchars($opt) ?>" <?= in_array($opt, $discReasons) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($opt) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Authorities Notified -->
            <div class="form-group">
                <label class="form-label">Authorities & Personnel Notified</label>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px;">
                    <?php foreach ($settings['notified_options'] as $opt): ?>
                        <label class="feature-option-label">
                            <input type="checkbox" name="who_notified[]" value="<?= htmlspecialchars($opt) ?>" <?= in_array($opt, $notified) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($opt) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <input type="text" name="notified_other_text" class="form-control" style="margin-top: 8px;" placeholder="Other authorities notified details..." value="<?= htmlspecialchars($incident['notified_other_text'] ?? '') ?>">
            </div>

            <!-- People who saw the Incident (Witnesses) -->
            <h4 class="section-header-banner" style="color: #ffffff !important; font-weight: 800 !important; font-style: italic;"><?= htmlspecialchars($settings['witness_section_title'] ?? 'People who saw the Incident') ?></h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 20px;">
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars($settings['witness_1_label'] ?? 'Witness 1') ?></label>
                    <input type="text" name="witness_1" class="form-control" value="<?= htmlspecialchars($incident['witness_1'] ?? '') ?>" placeholder="Name, Contact No., ID / Details">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars($settings['witness_2_label'] ?? 'Witness 2') ?></label>
                    <input type="text" name="witness_2" class="form-control" value="<?= htmlspecialchars($incident['witness_2'] ?? '') ?>" placeholder="Name, Contact No., ID / Details">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars($settings['witness_3_label'] ?? 'Witness 3') ?></label>
                    <input type="text" name="witness_3" class="form-control" value="<?= htmlspecialchars($incident['witness_3'] ?? '') ?>" placeholder="Name, Contact No., ID / Details">
                </div>
            </div>

                        <!-- Section Heading 3: Executive Summary & Narrative -->
            <h4 class="section-header-banner" style="color: #ffffff !important; font-weight: 800 !important;">Executive Summary</h4>
            <!-- Summary -->
            <div class="form-group">
                <label class="form-label">Executive Summary Line</label>
                <input type="text" name="summary_line" class="form-control" value="<?= htmlspecialchars($incident['summary_line']) ?>" required>
            </div>

            <!-- Description with Rich Formatting Toolbar -->
            <div class="form-group">
                <label class="form-label">Detailed Narrative Description (Rich Typography)</label>
                <div class="rich-text-wrapper">
                    <!-- Rich Formatting Toolbar -->
                    <div class="rich-toolbar">
                        <!-- Font Family -->
                        <select class="rt-select" onchange="applyRichFont(this.value)" title="Change Font">
                            <option value="inherit">Font: Default</option>
                            <option value="Arial, sans-serif">Arial</option>
                            <option value="'Times New Roman', serif">Times New Roman</option>
                            <option value="Georgia, serif">Georgia</option>
                            <option value="'Courier New', monospace">Courier New</option>
                            <option value="Verdana, sans-serif">Verdana</option>
                        </select>

                        <!-- Font Size -->
                        <select class="rt-select" onchange="applyRichFontSize(this.value)" title="Change Font Size">
                            <option value="14px">Size: 14px</option>
                            <option value="12px">12px (Small)</option>
                            <option value="16px">16px (Medium)</option>
                            <option value="18px">18px (Large)</option>
                            <option value="22px">22px (Heading)</option>
                        </select>

                        <!-- Bold & Italic -->
                        <div class="rt-btn-group">
                            <button type="button" class="rt-btn" onclick="execRichCmd('bold')" title="Bold (Ctrl+B)">
                                <strong>B</strong>
                            </button>
                            <button type="button" class="rt-btn" onclick="execRichCmd('italic')" title="Italic (Ctrl+I)">
                                <em>I</em>
                            </button>
                        </div>

                        <!-- Alignment: Left, Center, Right -->
                        <div class="rt-btn-group">
                            <button type="button" class="rt-btn" onclick="execRichCmd('justifyLeft')" title="Align Left">
                                <svg style="width:14px;height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="19" y2="18"/></svg>
                            </button>
                            <button type="button" class="rt-btn" onclick="execRichCmd('justifyCenter')" title="Align Center">
                                <svg style="width:14px;height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
                            </button>
                            <button type="button" class="rt-btn" onclick="execRichCmd('justifyRight')" title="Align Right">
                                <svg style="width:14px;height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="9" y1="12" x2="21" y2="12"/><line x1="5" y1="18" x2="21" y2="18"/></svg>
                            </button>
                        </div>

                        <!-- Font Color Swatches -->
                        <div class="rt-colors">
                            <span class="rt-color-swatch" style="background:#f8fafc;" onclick="applyRichColor('#f8fafc')" title="Light / White"></span>
                            <span class="rt-color-swatch" style="background:#8b0000;" onclick="applyRichColor('#8b0000')" title="Dark Red"></span>
                            <span class="rt-color-swatch" style="background:#0c2356;" onclick="applyRichColor('#0c2356')" title="Navy Blue"></span>
                            <span class="rt-color-swatch" style="background:#15803d;" onclick="applyRichColor('#15803d')" title="Dark Green"></span>
                            <label class="rt-color-picker-label" title="Custom Color Palette">
                                <input type="color" class="rt-color-input" onchange="applyRichColor(this.value)">
                                <span>🎨</span>
                            </label>
                        </div>
                    </div>

                    <!-- Editable Box pre-populated with rendered content -->
                    <div id="richDescriptionEditor" class="rich-editor-box" contenteditable="true" oninput="syncAdminRichEditor()" onblur="syncAdminRichEditor()"><?= render_rich_description($incident['description']) ?></div>
                    <textarea id="narrativeDesc" name="description" style="display: none;" required><?= htmlspecialchars($incident['description']) ?></textarea>
                </div>
            </div>

            
            <!-- Section Heading 4: Official Management Sign-Off -->
            <h4 class="section-header-banner" style="color: #ffffff !important; font-weight: 800 !important; margin-top: 28px;">Official Management Sign-Off</h4>
            
            <!-- 1. Supervisor Sign-off -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <div style="font-size: 13px; font-weight: 800; color: #38bdf8; margin-bottom: 12px; text-transform: uppercase;">
                    1. Site Supervisor Sign-Off
                </div>
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="color: #ffffff !important; font-weight: 800 !important;">Supervisor Name &amp; ID</label>
                        <input type="text" name="site_supervisor_name" class="form-control" placeholder="Supervisor Name" value="<?= htmlspecialchars($incident['site_supervisor_name'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="color: #ffffff !important; font-weight: 800 !important;">Staff ID</label>
                        <input type="text" name="site_supervisor_id" class="form-control" placeholder="Staff ID" value="<?= htmlspecialchars($incident['site_supervisor_id'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" style="color: #ffffff !important; font-weight: 800 !important;">Sign-Off Date (dd/mm/yyyy)</label>
                    <input type="date" name="site_supervisor_date" class="form-control" value="<?= htmlspecialchars($incident['site_supervisor_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" style="color: #ffffff !important; font-weight: 800 !important;">Recommendation / Action Taken</label>
                    <textarea name="recommendation_action_taken" class="form-control" rows="2" placeholder="Corrective actions initiated..."><?= htmlspecialchars($incident['recommendation_action_taken'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- 2. Operations Manager Sign-off -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <div style="font-size: 13px; font-weight: 800; color: #38bdf8; margin-bottom: 12px; text-transform: uppercase;">
                    2. Operations Manager Sign-Off
                </div>
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="color: #ffffff !important; font-weight: 800 !important;">Operations Manager Name &amp; Staff ID</label>
                        <input type="text" name="operations_manager_name" class="form-control" placeholder="e.g. Tariq Mansoor" value="<?= htmlspecialchars($incident['operations_manager_name'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="color: #ffffff !important; font-weight: 800 !important;">Staff ID</label>
                        <input type="text" name="operations_manager_id" class="form-control" placeholder="Staff ID (e.g. OPS-01)" value="<?= htmlspecialchars($incident['operations_manager_id'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" style="color: #ffffff !important; font-weight: 800 !important;">Ops Sign-off Date (dd/mm/yyyy)</label>
                    <input type="date" name="operations_manager_date" class="form-control" value="<?= htmlspecialchars($incident['operations_manager_date'] ?? '') ?>">
                </div>
            </div>

            <!-- 3. Client Representative Sign-off -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <div style="font-size: 13px; font-weight: 800; color: #38bdf8; margin-bottom: 12px; text-transform: uppercase;">
                    3. Client Representative Sign-Off
                </div>
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="color: #ffffff !important; font-weight: 800 !important;">Client Representative Name &amp; ID</label>
                        <input type="text" name="client_manager_name" class="form-control" placeholder="Client Manager Name" value="<?= htmlspecialchars($incident['client_manager_name'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="color: #ffffff !important; font-weight: 800 !important;">Client Staff ID / Rep ID</label>
                        <input type="text" name="client_manager_id" class="form-control" placeholder="Client ID / Badge" value="<?= htmlspecialchars($incident['client_manager_id'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" style="color: #ffffff !important; font-weight: 800 !important;">Client Action Taken</label>
                    <textarea name="client_manager_action_taken" class="form-control" rows="2" placeholder="Client action or notes..."><?= htmlspecialchars($incident['client_manager_action_taken'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" style="color: #ffffff !important; font-weight: 800 !important;">Client Date (dd/mm/yyyy)</label>
                    <input type="date" name="client_manager_date" class="form-control" value="<?= htmlspecialchars($incident['client_manager_date'] ?? '') ?>">
                </div>
            </div>
<div style="display: flex; gap: 12px; margin-top: 24px;">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="<?= APP_URL ?>/admin/incident_view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function execRichCmd(cmd, val = null) {
    const editor = document.getElementById('richDescriptionEditor');
    editor.focus();
    document.execCommand(cmd, false, val);
    syncAdminRichEditor();
}

function applyRichFont(font) {
    const editor = document.getElementById('richDescriptionEditor');
    editor.focus();
    if (!font || font === 'inherit') {
        document.execCommand('fontName', false, 'inherit');
    } else {
        document.execCommand('fontName', false, font);
    }
    syncAdminRichEditor();
}

function applyRichFontSize(size) {
    const editor = document.getElementById('richDescriptionEditor');
    editor.focus();
    const sel = window.getSelection();
    if (!sel || !sel.rangeCount || sel.isCollapsed) {
        editor.style.fontSize = size;
        syncAdminRichEditor();
        return;
    }
    document.execCommand('fontSize', false, '7');
    const fontTags = editor.querySelectorAll('font[size="7"]');
    fontTags.forEach(f => {
        const span = document.createElement('span');
        span.style.fontSize = size;
        span.innerHTML = f.innerHTML;
        f.parentNode.replaceChild(span, f);
    });
    syncAdminRichEditor();
}

function applyRichColor(color) {
    const editor = document.getElementById('richDescriptionEditor');
    editor.focus();
    document.execCommand('foreColor', false, color);
    syncAdminRichEditor();
}

function syncAdminRichEditor() {
    const editor = document.getElementById('richDescriptionEditor');
    const descInput = document.getElementById('narrativeDesc');
    if (editor && descInput) {
        descInput.value = editor.innerHTML.trim();
    }
}

document.querySelector('form').addEventListener('submit', function() {
    syncAdminRichEditor();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
