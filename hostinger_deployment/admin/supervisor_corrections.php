<?php
/**
 * Super Admin - Supervisor Manual Visit Time-Out Approvals & Corrections
 * Ultimate Security Services
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$db = get_db();
$pageTitle = "Manual Visit Corrections";
$pageSubtitle = "Review and approve supervisor manual time-out requests with audit control";

$msg = '';
$msgType = 'success';

// Handle Action (Approve / Adjust & Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $visitId = intval($_POST['visit_id'] ?? 0);
    $action = $_POST['action'];
    $adminNotes = trim($_POST['correction_notes'] ?? '');
    $adminId = $_SESSION['admin_user_id'] ?? 'admin';

    $stmt = $db->prepare("
        SELECT v.*, g.full_name as supervisor_name, s.name as site_name, s.code as site_code 
        FROM supervisor_visits v
        JOIN guards g ON v.supervisor_id = g.id
        JOIN sites s ON v.site_id = s.id
        WHERE v.id = ?
    ");
    $stmt->execute([$visitId]);
    $visit = $stmt->fetch();

    if (!$visit) {
        $msg = "Visit record not found.";
        $msgType = "danger";
    } else {
        if ($action === 'approve') {
            // Use requested manual time out or adjusted time out
            $finalTimeOut = trim($_POST['manual_timeout_time'] ?? $visit['manual_timeout_time']);
            if (empty($finalTimeOut)) {
                $finalTimeOut = $visit['manual_timeout_time'] ?: date('Y-m-d H:i:s');
            }

            // Ensure format Y-m-d H:i:s
            $finalTimeOutFormatted = date('Y-m-d H:i:s', strtotime($finalTimeOut));
            $timeIn = $visit['time_in'];

            // Calculate minutes
            $tIn = strtotime($timeIn);
            $tOut = strtotime($finalTimeOutFormatted);
            if ($tOut <= $tIn) {
                $msg = "Time out must be after Time in (" . date('d M Y, h:i A', $tIn) . ").";
                $msgType = "danger";
            } else {
                $minutesSpent = max(1, round(($tOut - $tIn) / 60));

                $updateStmt = $db->prepare("
                    UPDATE supervisor_visits SET
                        time_out = ?,
                        minutes_spent = ?,
                        status = 'Completed',
                        is_draft = 0,
                        manual_timeout_status = 'Approved',
                        corrected_by_admin_id = ?,
                        correction_notes = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $finalTimeOutFormatted,
                    $minutesSpent,
                    $adminId,
                    $adminNotes,
                    $visitId
                ]);

                // Update duty totals if duty_id is linked
                if (!empty($visit['duty_id'])) {
                    $sumStmt = $db->prepare("SELECT SUM(minutes_spent) FROM supervisor_visits WHERE duty_id = ? AND status = 'Completed'");
                    $sumStmt->execute([$visit['duty_id']]);
                    $totalMins = intval($sumStmt->fetchColumn() ?: 0);

                    $updDuty = $db->prepare("UPDATE supervisor_duties SET total_minutes_on_sites = ?, updated_at = NOW() WHERE id = ?");
                    $updDuty->execute([$totalMins, $visit['duty_id']]);
                }

                log_audit('APPROVE_MANUAL_TIMEOUT', 'SUPERVISOR_VISIT', $visit['visit_ref'] ?: (string)$visitId, "Approved manual time out of {$minutesSpent}m for {$visit['supervisor_name']} at {$visit['site_name']}. Notes: {$adminNotes}");
                $msg = "Visit [{$visit['visit_ref']}] successfully approved and finalized ({$minutesSpent} minutes).";
            }
        } elseif ($action === 'reject') {
            if (empty($adminNotes)) {
                $msg = "Rejection reason / admin notes are mandatory when rejecting a manual time out request.";
                $msgType = "danger";
            } else {
                $updateStmt = $db->prepare("
                    UPDATE supervisor_visits SET
                        manual_timeout_status = 'Rejected',
                        status = 'In Progress',
                        corrected_by_admin_id = ?,
                        correction_notes = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $adminId,
                    $adminNotes,
                    $visitId
                ]);

                log_audit('REJECT_MANUAL_TIMEOUT', 'SUPERVISOR_VISIT', $visit['visit_ref'] ?: (string)$visitId, "Rejected manual time out request for {$visit['supervisor_name']} at {$visit['site_name']}. Reason: {$adminNotes}");
                $msg = "Manual time-out request for visit [{$visit['visit_ref']}] has been rejected. Visit remains flagged as In Progress.";
            }
        }
    }
}

// Filter handling
$statusFilter = $_GET['status'] ?? 'Pending';
$whereClause = "WHERE 1=1";
$params = [];

if ($statusFilter !== 'all') {
    $whereClause .= " AND v.manual_timeout_status = ?";
    $params[] = $statusFilter;
}

$stmt = $db->prepare("
    SELECT v.*, g.full_name as supervisor_name, g.staff_id, s.name as site_name, s.code as site_code
    FROM supervisor_visits v
    JOIN guards g ON v.supervisor_id = g.id
    JOIN sites s ON v.site_id = s.id
    {$whereClause}
    ORDER BY v.id DESC
");
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Counts for tabs
$cntPending = $db->query("SELECT COUNT(*) FROM supervisor_visits WHERE manual_timeout_status = 'Pending'")->fetchColumn();
$cntApproved = $db->query("SELECT COUNT(*) FROM supervisor_visits WHERE manual_timeout_status = 'Approved'")->fetchColumn();
$cntRejected = $db->query("SELECT COUNT(*) FROM supervisor_visits WHERE manual_timeout_status = 'Rejected'")->fetchColumn();
$cntAll = $db->query("SELECT COUNT(*) FROM supervisor_visits WHERE manual_timeout_requested = 1")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" style="margin-bottom: 20px; font-weight: 600;">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<!-- Filter Tabs -->
<div style="display: flex; gap: 10px; margin-bottom: 22px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <a href="?status=Pending" class="btn <?= $statusFilter === 'Pending' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 13px; padding: 7px 14px;">
            ⏳ Pending Approval (<?= $cntPending ?>)
        </a>
        <a href="?status=Approved" class="btn <?= $statusFilter === 'Approved' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 13px; padding: 7px 14px;">
            ✅ Approved (<?= $cntApproved ?>)
        </a>
        <a href="?status=Rejected" class="btn <?= $statusFilter === 'Rejected' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 13px; padding: 7px 14px;">
            ❌ Rejected (<?= $cntRejected ?>)
        </a>
        <a href="?status=all" class="btn <?= $statusFilter === 'all' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 13px; padding: 7px 14px;">
            📋 All Correction Requests (<?= $cntAll ?>)
        </a>
    </div>

    <a href="<?= APP_URL ?>/admin/supervisor_visits.php" class="btn btn-secondary" style="font-size: 13px;">
        ← Back to Visits Registry
    </a>
</div>

<!-- Requests Table Card -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div style="padding: 16px 20px; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.02);">
        <h3 style="margin: 0; font-size: 15px; font-weight: 700;">
            Manual Correction Requests List (<?= count($requests) ?>)
        </h3>
        <span style="font-size: 12px; color: var(--text-dim);">Strict Super Admin Approval Only</span>
    </div>

    <div style="overflow-x: auto;">
        <table class="table" style="margin: 0;">
            <thead>
                <tr>
                    <th style="padding-left: 20px;">Ref / Supervisor</th>
                    <th>Site</th>
                    <th>Actual Time In</th>
                    <th>Requested Time Out</th>
                    <th>Est. Time</th>
                    <th>Reason Given</th>
                    <th>Status</th>
                    <th style="text-align: right; padding-right: 20px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-dim);">
                            No manual time-out correction requests found matching filter "<?= htmlspecialchars($statusFilter) ?>".
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $r): 
                        $tIn = strtotime($r['time_in']);
                        $tReq = $r['manual_timeout_time'] ? strtotime($r['manual_timeout_time']) : null;
                        $diffMins = ($tReq && $tReq > $tIn) ? round(($tReq - $tIn) / 60) : 0;
                    ?>
                        <tr>
                            <td style="padding-left: 20px;">
                                <div style="font-weight: 800; font-size: 13px; color: var(--primary);">
                                    <?= htmlspecialchars($r['visit_ref'] ?: 'VIS-' . str_pad($r['id'], 5, '0', STR_PAD_LEFT)) ?>
                                </div>
                                <div style="font-size: 12.5px; font-weight: 600; margin-top: 2px;">
                                    <?= htmlspecialchars($r['supervisor_name']) ?>
                                    <span style="font-size: 11px; color: var(--text-dim);">(<?= htmlspecialchars($r['staff_id']) ?>)</span>
                                </div>
                            </td>
                            <td>
                                <span style="font-weight: 700; font-size: 13px;"><?= htmlspecialchars($r['site_name']) ?></span>
                                <div style="font-size: 11px; color: var(--text-dim);"><?= htmlspecialchars($r['site_code']) ?></div>
                            </td>
                            <td>
                                <span style="font-size: 12.5px; font-weight: 600; color: #10b981;">
                                    <?= date('d M, h:i A', $tIn) ?>
                                </span>
                                <div style="font-size: 10.5px; color: var(--text-dim);">Actual QR Scan</div>
                            </td>
                            <td>
                                <?php if ($tReq): ?>
                                    <span style="font-size: 12.5px; font-weight: 700; color: #f59e0b;">
                                        <?= date('d M, h:i A', $tReq) ?>
                                    </span>
                                    <div style="font-size: 10.5px; color: var(--text-dim);">Manual Departure</div>
                                <?php else: ?>
                                    <span style="color: var(--text-dim);">Not specified</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($diffMins > 0): ?>
                                    <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #d97706; font-weight: 800;">
                                        <?= $diffMins ?> mins
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-dim); font-size: 12px;">--</span>
                                <?php endif; ?>
                            </td>
                            <td style="max-width: 250px;">
                                <div style="font-size: 12px; font-weight: 600; color: var(--text-main); white-space: normal; line-height: 1.4;">
                                    <?= nl2br(htmlspecialchars($r['manual_timeout_reason'] ?: 'No explanation provided')) ?>
                                </div>
                                <?php if (!empty($r['remarks'])): ?>
                                    <div style="font-size: 11px; color: var(--text-dim); margin-top: 4px; font-style: italic;">
                                        Remarks: "<?= htmlspecialchars(mb_strimwidth($r['remarks'], 0, 45, '...')) ?>"
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['manual_timeout_status'] === 'Pending'): ?>
                                    <span class="badge" style="background: #fef3c7; color: #92400e; font-weight: 800; border: 1px solid #fde68a;">
                                        ⏳ Pending
                                    </span>
                                <?php elseif ($r['manual_timeout_status'] === 'Approved'): ?>
                                    <span class="badge" style="background: #d1fae5; color: #065f46; font-weight: 800; border: 1px solid #a7f3d0;">
                                        ✅ Approved
                                    </span>
                                <?php elseif ($r['manual_timeout_status'] === 'Rejected'): ?>
                                    <span class="badge" style="background: #fee2e2; color: #991b1b; font-weight: 800; border: 1px solid #fecaca;">
                                        ❌ Rejected
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; padding-right: 20px;">
                                <?php if ($r['manual_timeout_status'] === 'Pending'): ?>
                                    <button type="button" class="btn btn-primary" style="padding: 5px 12px; font-size: 12px;" onclick='openReviewModal(<?= json_encode($r) ?>)'>
                                        Review & Decide
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-secondary" style="padding: 5px 12px; font-size: 12px;" onclick='openReviewModal(<?= json_encode($r) ?>)'>
                                        View Details
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Review & Approval Modal -->
<div id="reviewModal" class="modal" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="modal-dialog" style="background: var(--bg-card); width: 100%; max-width: 600px; border-radius: 12px; border: 1px solid var(--border-subtle); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3); overflow: hidden;">
        
        <div style="padding: 18px 24px; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 800;">
                Review Manual Time Out Request
            </h3>
            <button type="button" onclick="closeReviewModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-dim);">&times;</button>
        </div>

        <form method="POST" action="" id="approvalForm" style="margin: 0;">
            <input type="hidden" name="visit_id" id="modalVisitId">
            <input type="hidden" name="action" id="modalAction" value="approve">

            <div style="padding: 24px; max-height: 75vh; overflow-y: auto;">
                
                <!-- Visit Info Header Box -->
                <div style="background: rgba(0,0,0,0.03); border: 1px solid var(--border-subtle); border-radius: 8px; padding: 14px 16px; margin-bottom: 18px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span style="font-size: 12px; color: var(--text-dim); text-transform: uppercase; font-weight: 700;">Visit Reference:</span>
                        <span id="modalVisitRef" style="font-weight: 800; color: var(--primary);"></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span style="font-size: 12px; color: var(--text-dim); text-transform: uppercase; font-weight: 700;">Supervisor:</span>
                        <span id="modalSupervisor" style="font-weight: 700;"></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span style="font-size: 12px; color: var(--text-dim); text-transform: uppercase; font-weight: 700;">Site:</span>
                        <span id="modalSite" style="font-weight: 700;"></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="font-size: 12px; color: var(--text-dim); text-transform: uppercase; font-weight: 700;">Time In (Scan):</span>
                        <span id="modalTimeIn" style="font-weight: 700; color: #10b981;"></span>
                    </div>
                </div>

                <!-- Supervisor's Reason -->
                <div class="form-group" style="margin-bottom: 18px;">
                    <label class="form-label" style="font-weight: 700;">Reason Stated by Supervisor:</label>
                    <div id="modalReason" style="background: #fffbeb; color: #92400e; border: 1px solid #fde68a; border-radius: 6px; padding: 10px 14px; font-size: 13px; line-height: 1.5; font-weight: 600;"></div>
                </div>

                <!-- Supervisor's Remarks -->
                <div class="form-group" style="margin-bottom: 18px;">
                    <label class="form-label" style="font-weight: 700;">Site Visit Remarks:</label>
                    <div id="modalRemarks" style="background: rgba(0,0,0,0.02); border: 1px solid var(--border-subtle); border-radius: 6px; padding: 10px 14px; font-size: 12.5px; line-height: 1.4; color: var(--text-main);"></div>
                </div>

                <!-- Time Out Input (Editable by Admin) -->
                <div class="form-group" style="margin-bottom: 18px;" id="timeoutInputGroup">
                    <label class="form-label" style="font-weight: 700;">
                        Approve / Adjust Departure Date & Time (GST):
                    </label>
                    <input type="datetime-local" name="manual_timeout_time" id="modalTimeOutInput" class="form-control" style="font-size: 14px; font-weight: 700;" required>
                    <span style="font-size: 11.5px; color: var(--text-dim); display: block; margin-top: 4px;">
                        Super Admin can adjust the departure time if verified via site logbook or CCTV.
                    </span>
                </div>

                <!-- Admin Notes / Rejection Reason -->
                <div class="form-group" style="margin-bottom: 10px;">
                    <label class="form-label" style="font-weight: 700;">
                        Super Admin Decision Notes / Audit Justification:
                    </label>
                    <textarea name="correction_notes" id="modalAdminNotes" class="form-control" rows="3" placeholder="Enter notes or audit justification (Mandatory if rejecting)..."></textarea>
                </div>

            </div>

            <!-- Modal Footer -->
            <div style="padding: 16px 24px; border-top: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.02);" id="modalFooterAction">
                <button type="button" class="btn btn-secondary" onclick="closeReviewModal()">Cancel</button>

                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn" style="background: #ef4444; color: white;" onclick="submitDecision('reject')">
                        ❌ Reject Request
                    </button>
                    <button type="button" class="btn btn-primary" onclick="submitDecision('approve')">
                        ✅ Approve & Complete Visit
                    </button>
                </div>
            </div>
        </form>

    </div>
</div>

<script>
function openReviewModal(visit) {
    document.getElementById('modalVisitId').value = visit.id;
    document.getElementById('modalVisitRef').innerText = visit.visit_ref || ('VIS-' + visit.id);
    document.getElementById('modalSupervisor').innerText = visit.supervisor_name + ' (' + visit.staff_id + ')';
    document.getElementById('modalSite').innerText = visit.site_name;
    document.getElementById('modalTimeIn').innerText = visit.time_in;
    document.getElementById('modalReason').innerText = visit.manual_timeout_reason || 'No reason provided';
    document.getElementById('modalRemarks').innerText = visit.remarks || '(No remarks entered)';
    document.getElementById('modalAdminNotes').value = visit.correction_notes || '';

    // Set time-out input default (convert to YYYY-MM-DDTHH:MM)
    var defaultTime = visit.manual_timeout_time || visit.time_in;
    if (defaultTime) {
        var dt = new Date(defaultTime.replace(' ', 'T'));
        if (!isNaN(dt.getTime())) {
            var tzOffset = dt.getTimezoneOffset() * 60000;
            var localISOTime = (new Date(dt - tzOffset)).toISOString().slice(0, 16);
            document.getElementById('modalTimeOutInput').value = localISOTime;
        }
    }

    if (visit.manual_timeout_status !== 'Pending') {
        document.getElementById('modalFooterAction').style.display = 'none';
        document.getElementById('modalTimeOutInput').disabled = true;
        document.getElementById('modalAdminNotes').disabled = true;
    } else {
        document.getElementById('modalFooterAction').style.display = 'flex';
        document.getElementById('modalTimeOutInput').disabled = false;
        document.getElementById('modalAdminNotes').disabled = false;
    }

    var modal = document.getElementById('reviewModal');
    modal.style.display = 'flex';
}

function closeReviewModal() {
    document.getElementById('reviewModal').style.display = 'none';
}

function submitDecision(action) {
    document.getElementById('modalAction').value = action;
    var notes = document.getElementById('modalAdminNotes').value.trim();

    if (action === 'reject') {
        if (!notes) {
            alert('Please provide Admin Decision Notes stating why the request is rejected.');
            document.getElementById('modalAdminNotes').focus();
            return;
        }
        if (!confirm('Are you sure you want to REJECT this manual time-out request? The visit will remain flagged as In Progress.')) {
            return;
        }
    } else if (action === 'approve') {
        if (!confirm('Approve this time out and complete the site visit? Total minutes spent will be recalculated and saved.')) {
            return;
        }
    }

    document.getElementById('approvalForm').submit();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
