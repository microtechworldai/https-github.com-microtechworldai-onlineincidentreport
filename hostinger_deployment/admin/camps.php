<?php
/**
 * Camp Management & Supervisor QR Checkpoint Generation
 * Ultimate Security Services (USS)
 */
$pageTitle = "Camp Accommodations";
require_once __DIR__ . '/../includes/header.php';

$db = get_db();
$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_camp') {
        $campCode = strtoupper(trim($_POST['camp_code'] ?? ''));
        $campName = trim($_POST['camp_name'] ?? '');
        $location = trim($_POST['location'] ?? '');

        if (empty($campCode) || empty($campName)) {
            $error = "Camp Code and Camp Name are required.";
        } else {
            $stmt = $db->prepare("SELECT id FROM camps WHERE camp_code = ? LIMIT 1");
            $stmt->execute([$campCode]);
            if ($stmt->fetch()) {
                $error = "Camp Code '{$campCode}' already exists.";
            } else {
                $tokenSecret = create_qr_token_secret('CMP_');

                $stmt = $db->prepare("
                    INSERT INTO camps (camp_code, camp_name, location, qr_token, qr_token_hash, qr_token_encrypted, is_active, created_at, updated_at)
                    VALUES (?, ?, ?, NULL, ?, ?, 1, NOW(), NOW())
                ");
                $stmt->execute([$campCode, $campName, $location, $tokenSecret['hash'], $tokenSecret['encrypted']]);
                $campId = $db->lastInsertId();

                log_audit('CAMP_CREATED', "Created camp {$campCode} ({$campName})", $_SESSION['admin_id'] ?? null, 'camps', $campId);
                $message = "Camp '{$campName}' successfully created with unique QR checkpoint.";
            }
        }
    } elseif ($action === 'update_camp') {
        $campId = (int)($_POST['camp_id'] ?? 0);
        $campName = trim($_POST['camp_name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $status = ((int)($_POST['is_active'] ?? 1) === 1) ? 1 : 0;

        if (empty($campName)) {
            $error = "Camp Name cannot be blank.";
        } else {
            $stmt = $db->prepare("
                UPDATE camps 
                SET camp_name = ?, location = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$campName, $location, $status, $campId]);
            log_audit('CAMP_UPDATED', "Updated camp ID {$campId} ({$campName})", $_SESSION['admin_id'] ?? null, 'camps', $campId);
            $message = "Camp details updated successfully.";
        }
    } elseif ($action === 'regenerate_camp_qr') {
        $campId = (int)($_POST['camp_id'] ?? 0);
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            http_response_code(400);
            $error = "The form expired. Refresh the page and try again.";
        } elseif ($campId < 1) {
            $error = "Select a valid camp.";
        } else {
            $tokenSecret = create_qr_token_secret('CMP_');
            $stmt = $db->prepare("
                UPDATE camps
                SET qr_token = NULL, qr_token_hash = ?, qr_token_encrypted = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$tokenSecret['hash'], $tokenSecret['encrypted'], $campId]);
            if ($stmt->rowCount() !== 1) {
                $error = "Camp QR was not changed. Confirm the camp exists and try again.";
            } else {
                log_audit('REGENERATE_CAMP_QR', 'CAMP', $campId, ['token' => 'replaced']);
                $message = "Camp QR token regenerated. Any previous camp poster has been invalidated.";
            }
        }
    }
}

// Fetch all camps with active supervisors count
$camps = $db->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM guards g WHERE g.assigned_camp_id = c.id AND g.is_supervisor = 1) AS assigned_supervisors,
           (SELECT COUNT(*) FROM supervisor_duties d WHERE d.camp_id = c.id AND d.status = 'Active') AS active_duties
    FROM camps c
    ORDER BY c.camp_name ASC
")->fetchAll();
?>

<div class="content-header">
    <div class="header-title">
        <h1 class="page-title">Camp Accommodations & QR Checkpoints</h1>
        <p class="text-muted">Manage base camps where supervisors scan Camp Out (start duty) and Camp In (end duty).</p>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-primary" onclick="openModal('createCampModal')">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add New Camp
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success" style="margin-bottom: 20px;"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger" style="margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="table" style="width: 100%;">
            <thead>
                <tr>
                    <th style="width: 120px;">Camp Code</th>
                    <th>Camp Name</th>
                    <th>Location / Address</th>
                    <th style="text-align: center;">Supervisors</th>
                    <th style="text-align: center;">Active Patrols</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: right; width: 200px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($camps)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 36px; color: var(--text-muted);">
                            No camps registered yet. Click "Add New Camp" to register the primary accommodation.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($camps as $camp): ?>
                        <tr>
                            <td>
                                <span class="badge" style="background: rgba(14, 165, 233, 0.15); color: var(--accent-cyan); font-weight: 700;">
                                    <?= htmlspecialchars($camp['camp_code']) ?>
                                </span>
                            </td>
                            <td>
                                <strong style="color: var(--text-main); font-size: 14.5px;"><?= htmlspecialchars($camp['camp_name']) ?></strong>
                            </td>
                            <td style="color: var(--text-muted); font-size: 13px;">
                                <?= htmlspecialchars($camp['location'] ?: 'Not specified') ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge" style="background: var(--bg-card-hover); color: var(--text-main);">
                                    <?= (int)$camp['assigned_supervisors'] ?> Assigned
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($camp['active_duties'] > 0): ?>
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: var(--accent-emerald); font-weight: 700;">
                                        ● <?= (int)$camp['active_duties'] ?> On Duty
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-dim); font-size: 12px;">Idle</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge <?= $camp['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $camp['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px; align-items: center;">
                                    <a href="<?= APP_URL ?>/admin/camp_qr_poster.php?id=<?= $camp['id'] ?>" target="_blank" class="btn btn-sm btn-outline" title="Print Official A4 QR Poster">
                                        🖨️ Poster
                                    </a>
                                    <form method="post" style="display:inline" onsubmit="return confirm('Regenerate the camp QR? The current camp poster will stop working.');">
                                        <input type="hidden" name="action" value="regenerate_camp_qr">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                                        <input type="hidden" name="camp_id" value="<?= (int)$camp['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline" title="Regenerate Camp QR">🔄 QR</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline" onclick='openEditModal(<?= json_encode($camp) ?>)' title="Edit Details">
                                        ✏️ Edit
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Create Camp -->
<div class="modal-backdrop" id="createCampModal" style="display: none;">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Add New Camp Accommodation</h3>
            <button type="button" class="modal-close" onclick="closeModal('createCampModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create_camp">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Camp Code <span style="color: var(--accent-rose);">*</span></label>
                    <input type="text" name="camp_code" class="form-control" placeholder="e.g. CAMP-02" required pattern="[A-Za-z0-9\-_]{2,20}">
                    <span class="form-help">Unique short code used in reports and posters</span>
                </div>
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Camp Name <span style="color: var(--accent-rose);">*</span></label>
                    <input type="text" name="camp_name" class="form-control" placeholder="e.g. Sonapur Accommodation" required>
                </div>
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Location / Address</label>
                    <textarea name="location" class="form-control" rows="2" placeholder="e.g. Muhaisnah 2, Sonapur, Dubai"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createCampModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Camp Checkpoint</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Camp -->
<div class="modal-backdrop" id="editCampModal" style="display: none;">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Edit Camp Accommodation</h3>
            <button type="button" class="modal-close" onclick="closeModal('editCampModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_camp">
            <input type="hidden" name="camp_id" id="edit_camp_id">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Camp Code</label>
                    <input type="text" id="edit_camp_code" class="form-control" readonly style="opacity: 0.7;">
                </div>
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Camp Name <span style="color: var(--accent-rose);">*</span></label>
                    <input type="text" name="camp_name" id="edit_camp_name" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Location / Address</label>
                    <textarea name="location" id="edit_location" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Status</label>
                    <select name="is_active" id="edit_status" class="form-control">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editCampModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).style.display = 'flex';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}
function openEditModal(camp) {
    document.getElementById('edit_camp_id').value = camp.id;
    document.getElementById('edit_camp_code').value = camp.camp_code;
    document.getElementById('edit_camp_name').value = camp.camp_name;
    document.getElementById('edit_location').value = camp.location || '';
    document.getElementById('edit_status').value = camp.is_active;
    openModal('editCampModal');
}
window.onclick = function(e) {
    if (e.target.classList.contains('modal-backdrop')) {
        e.target.style.display = 'none';
    }
};
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
