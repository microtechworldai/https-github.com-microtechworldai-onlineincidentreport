<?php
/**
 * Supervisor Visits Registry & Daily Field Tracking
 * Ultimate Security Services (USS)
 */
$pageTitle = "Supervisor Site Visits";
require_once __DIR__ . '/../includes/header.php';

$db = get_db();

// Filter parameters
$selectedDate = trim($_GET['date'] ?? date('Y-m-d'));
$selectedSup = (int)($_GET['supervisor_id'] ?? 0);
$selectedShift = trim($_GET['shift'] ?? '');

$where = ["DATE(v.time_in) = ?"];
$params = [$selectedDate];

if ($selectedSup > 0) {
    $where[] = "v.supervisor_id = ?";
    $params[] = $selectedSup;
}
if (!empty($selectedShift)) {
    $where[] = "d.shift_type = ?";
    $params[] = $selectedShift;
}

$whereSql = "WHERE " . implode(" AND ", $where);

// Fetch visits
$query = "
    SELECT v.*, 
           s.name AS site_name, s.code AS site_code, s.client_name, s.address AS site_address,
           g.full_name AS supervisor_name, g.staff_id AS supervisor_staff_id,
           d.duty_ref, d.shift_type, d.camp_out_time, d.camp_in_time,
           c.camp_name
    FROM supervisor_visits v
    JOIN sites s ON s.id = v.site_id
    JOIN guards g ON g.id = v.supervisor_id
    LEFT JOIN supervisor_duties d ON d.id = v.duty_id
    LEFT JOIN camps c ON c.id = d.camp_id
    {$whereSql}
    ORDER BY v.time_in ASC
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$visits = $stmt->fetchAll();

// Calculate Day Totals
$totalSites = count($visits);
$totalMins = 0;
foreach ($visits as $v) {
    $totalMins += (int)$v['minutes_spent'];
}
$totHours = floor($totalMins / 60);
$totRemMins = $totalMins % 60;

// Fetch supervisors for filter
$supervisors = $db->query("SELECT id, staff_id, full_name FROM guards WHERE is_supervisor = 1 ORDER BY full_name ASC")->fetchAll();

// Batch fetch visit photos
$photosByVisit = [];
$visitIds = array_filter(array_column($visits, 'id'));
if (!empty($visitIds)) {
    $inClause = implode(',', array_map('intval', $visitIds));
    $photoRows = $db->query("SELECT * FROM supervisor_visit_photos WHERE visit_id IN ($inClause) ORDER BY id ASC")->fetchAll();
    foreach ($photoRows as $pr) {
        $photosByVisit[$pr['visit_id']][] = $pr;
    }
}
?>

<div class="content-header">
    <div class="header-title">
        <h1 class="page-title">Supervisor Daily Site Visits</h1>
        <p class="text-muted">Daily patrol tracking: Camp Out, Site Time In, Time Out, Minutes Spent, Mandatory Remarks & Site Report.</p>
    </div>
    <div class="header-actions" style="display: flex; flex-wrap: wrap; gap: 8px;">
        <a href="<?= APP_URL ?>/admin/supervisor_corrections.php" class="btn btn-outline" style="font-size: 12px; padding: 7px 12px;">
            📝 Corrections
        </a>
        <a href="<?= APP_URL ?>/admin/report_daily_supervisor_pdf.php?date=<?= urlencode($selectedDate) ?><?= $selectedSup ? '&supervisor_id=' . $selectedSup : '' ?>" target="_blank" class="btn btn-primary" style="font-size: 12px; padding: 7px 12px;" title="Print Official One-Page A4 Daily Report">
            📄 Daily A4
        </a>
        <a href="<?= APP_URL ?>/admin/report_monthly_supervisor_pdf.php?month=<?= urlencode(substr($selectedDate, 0, 7)) ?><?= $selectedSup ? '&supervisor_id=' . $selectedSup : '' ?>" target="_blank" class="btn btn-outline" style="font-size: 12px; padding: 7px 12px;" title="Print Official One-Page A4 Monthly Report">
            📊 Monthly Supervisor A4
        </a>
        <a href="<?= APP_URL ?>/admin/report_site_monthly_pdf.php?month=<?= urlencode(substr($selectedDate, 0, 7)) ?>" target="_blank" class="btn btn-outline" style="font-size: 12px; padding: 7px 12px;" title="Print Official One-Page A4 Monthly Site Dossier">
            🏢 Monthly Site A4
        </a>
        <a href="<?= APP_URL ?>/admin/report_supervisor_export_excel.php?month=<?= urlencode(substr($selectedDate, 0, 7)) ?>" class="btn btn-secondary" style="font-size: 12px; padding: 7px 12px; background: #059669; border-color: #059669; color: #fff;" title="Download Multi-Sheet Excel Workbook">
            📥 Export Excel (5 Sheets)
        </a>
    </div>
</div>

<!-- Filters Bar -->
<div class="card" style="margin-bottom: 20px; padding: 16px;">
    <form method="GET" style="display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end;">
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" style="font-size: 12px;">Visit Date</label>
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($selectedDate) ?>" style="font-size: 13px;" onchange="this.form.submit()">
        </div>

        <div class="form-group" style="margin-bottom: 0; min-width: 200px;">
            <label class="form-label" style="font-size: 12px;">Supervisor</label>
            <select name="supervisor_id" class="form-control" style="font-size: 13px;" onchange="this.form.submit()">
                <option value="0">All Supervisors</option>
                <?php foreach ($supervisors as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $selectedSup == $s['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['staff_id']) ?> - <?= htmlspecialchars($s['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" style="font-size: 12px;">Shift</label>
            <select name="shift" class="form-control" style="font-size: 13px;" onchange="this.form.submit()">
                <option value="">All Shifts</option>
                <option value="Day" <?= $selectedShift === 'Day' ? 'selected' : '' ?>>Day Shift</option>
                <option value="Night" <?= $selectedShift === 'Night' ? 'selected' : '' ?>>Night Shift</option>
            </select>
        </div>

        <div style="margin-bottom: 0; display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary" style="padding: 9px 18px; font-size: 13px;">Filter</button>
            <a href="<?= APP_URL ?>/admin/supervisor_visits.php" class="btn btn-outline" style="padding: 9px 14px; font-size: 13px;">Reset</a>
        </div>
    </form>
</div>

<!-- Day Summary Metric Cards -->
<div class="grid-dashboard" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
    <div class="card" style="padding: 18px; text-align: center;">
        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Sites Visited</div>
        <div style="font-size: 32px; font-weight: 900; color: var(--accent-cyan); margin-top: 4px; font-family: monospace;">
            <?= $totalSites ?>
        </div>
        <div style="font-size: 11.5px; color: var(--text-dim); margin-top: 2px;">Inspections on <?= date('d M Y', strtotime($selectedDate)) ?></div>
    </div>

    <div class="card" style="padding: 18px; text-align: center;">
        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Total Time on Sites</div>
        <div style="font-size: 32px; font-weight: 900; color: var(--accent-emerald); margin-top: 4px; font-family: monospace;">
            <?= $totHours > 0 ? "{$totHours}h {$totRemMins}m" : "{$totRemMins}m" ?>
        </div>
        <div style="font-size: 11.5px; color: var(--text-dim); margin-top: 2px;">Cumulative audit duration</div>
    </div>

    <div class="card" style="padding: 18px; text-align: center;">
        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Average Time / Site</div>
        <div style="font-size: 32px; font-weight: 900; color: var(--accent-amber); margin-top: 4px; font-family: monospace;">
            <?= $totalSites > 0 ? round($totalMins / $totalSites) : 0 ?>m
        </div>
        <div style="font-size: 11.5px; color: var(--text-dim); margin-top: 2px;">Per visited checkpoint</div>
    </div>
</div>

<!-- Visits Table -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div style="padding: 16px 20px; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.02);">
        <h3 style="margin: 0; font-size: 15px; font-weight: 700;">
            Site Visits Log - <?= date('l, d F Y', strtotime($selectedDate)) ?> (<?= count($visits) ?> Records)
        </h3>
        <span style="font-size: 12px; color: var(--text-dim);">Live Operations</span>
    </div>

    <div class="table-responsive">
        <table class="table" style="width: 100%; margin: 0;">
            <thead>
                <tr>
                    <th style="padding-left: 20px; width: 140px;">Ref / Supervisor</th>
                    <th>Shift</th>
                    <th>Site Name & Code</th>
                    <th style="text-align: center;">Time In</th>
                    <th style="text-align: center;">Time Out</th>
                    <th style="text-align: center;">Time Spent</th>
                    <th>Mandatory Remarks & Assessment</th>
                    <th style="text-align: center; width: 110px;">Status</th>
                    <th style="text-align: right; padding-right: 20px; width: 90px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($visits)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-muted);">
                            No supervisor visits recorded for the selected date and filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($visits as $idx => $v): 
                        $vPhotos = $photosByVisit[$v['id']] ?? [];
                        $v['photo_list'] = $vPhotos;
                    ?>
                        <tr>
                            <td style="padding-left: 20px;">
                                <div style="font-weight: 800; font-size: 12.5px; color: var(--primary);">
                                    <?= htmlspecialchars($v['visit_ref'] ?: 'VIS-' . str_pad($v['id'], 5, '0', STR_PAD_LEFT)) ?>
                                </div>
                                <strong style="color: var(--text-main); font-size: 13px; display: block; margin-top: 2px;">
                                    <?= htmlspecialchars($v['supervisor_name']) ?>
                                </strong>
                                <div style="font-size: 11px; color: var(--text-dim);"><?= htmlspecialchars($v['supervisor_staff_id']) ?></div>
                            </td>
                            <td>
                                <span class="badge" style="background: <?= $v['shift_type'] === 'Night' ? 'rgba(99, 102, 241, 0.2)' : 'rgba(245, 158, 11, 0.2)' ?>; color: <?= $v['shift_type'] === 'Night' ? 'var(--accent-indigo)' : 'var(--accent-amber)' ?>; font-weight: 700;">
                                    <?= htmlspecialchars($v['shift_type']) ?>
                                </span>
                            </td>
                            <td>
                                <strong style="color: var(--text-main); font-size: 13.5px;"><?= htmlspecialchars($v['site_name']) ?></strong>
                                <div style="font-size: 11.5px; color: var(--text-muted);"><?= htmlspecialchars($v['site_code']) ?></div>
                            </td>
                            <td style="text-align: center; font-family: monospace; font-size: 13px; color: #10b981; font-weight: 700;">
                                <?= date('h:i A', strtotime($v['time_in'])) ?>
                            </td>
                            <td style="text-align: center; font-family: monospace; font-size: 13px;">
                                <?= $v['time_out'] ? date('h:i A', strtotime($v['time_out'])) : '<span style="color: #f59e0b; font-weight: 700;">In Progress</span>' ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($v['status'] === 'Completed'): ?>
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #059669; font-weight: 800; font-family: monospace;">
                                        <?= (int)$v['minutes_spent'] ?> mins
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #d97706;">Ticking...</span>
                                <?php endif; ?>
                            </td>
                            <td style="max-width: 340px;">
                                <?php if (!empty($v['remarks'])): ?>
                                    <div style="font-size: 12.5px; color: var(--text-main); line-height: 1.4; margin-bottom: 6px;">
                                        <?= htmlspecialchars(mb_strimwidth($v['remarks'], 0, 90, '...')) ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--text-dim); font-size: 12px; display: block; margin-bottom: 4px;">No remarks logged yet</span>
                                <?php endif; ?>

                                <!-- Client Rep & Checklist Pills -->
                                <?php if (!empty($v['client_rep_name'])): ?>
                                    <div style="font-size: 11px; color: var(--text-dim); margin-bottom: 4px;">
                                        👤 Client Rep: <strong style="color: var(--text-main);"><?= htmlspecialchars($v['client_rep_name']) ?></strong>
                                    </div>
                                <?php endif; ?>

                                <!-- Checklist Pills -->
                                <div style="display: flex; flex-wrap: wrap; gap: 4px; font-size: 10.5px;">
                                    <?php if (!empty($v['checklist_summary'])): ?>
                                        <span class="badge" style="background: rgba(14, 165, 233, 0.15); color: #0284c7; font-weight: 800;">
                                            📋 <?= htmlspecialchars($v['checklist_summary']) ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ((int)$v['checklist_no_count'] > 0): ?>
                                        <span class="badge" style="background: #fee2e2; color: #dc2626; font-weight: 800; border: 1px solid #fca5a5;">
                                            ⚠️ <?= (int)$v['checklist_no_count'] ?> Deficiencies
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($v['corrective_action_status'])): ?>
                                        <span class="badge" style="background: <?= $v['corrective_action_status'] === 'Resolved' ? '#dcfce7' : '#fef3c7' ?>; color: <?= $v['corrective_action_status'] === 'Resolved' ? '#166534' : '#92400e' ?>; font-weight: 700;">
                                            🔧 Action: <?= htmlspecialchars($v['corrective_action_status']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($v['purpose_of_visit'])): ?>
                                        <span class="badge" style="background: rgba(59, 130, 246, 0.12); color: #2563eb; font-weight: 700;">
                                            🎯 <?= htmlspecialchars($v['purpose_of_visit']) ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($v['guards_count'] !== null): ?>
                                        <span class="badge" style="background: rgba(0,0,0,0.06); color: var(--text-main); font-weight: 600;">
                                            👮 <?= (int)$v['guards_count'] ?> (<?= htmlspecialchars($v['guards_attendance'] ?: 'All Present') ?>)
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($v['rating_turnout'])): ?>
                                        <span class="badge" style="background: <?= $v['rating_turnout'] === 'OK' ? '#d1fae5' : ($v['rating_turnout'] === 'Issue' ? '#fee2e2' : '#f1f5f9') ?>; color: <?= $v['rating_turnout'] === 'OK' ? '#065f46' : ($v['rating_turnout'] === 'Issue' ? '#991b1b' : '#64748b') ?>; font-weight: 700;">
                                            Turnout: <?= htmlspecialchars($v['rating_turnout']) ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($v['has_issues']): ?>
                                        <span class="badge" style="background: #fee2e2; color: #dc2626; font-weight: 800; border: 1px solid #fca5a5;">
                                            ⚠️ Issues Logged
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($vPhotos)): ?>
                                        <span class="badge" style="background: #e0f2fe; color: #0369a1; font-weight: 700;">
                                            📷 <?= count($vPhotos) ?> photos
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($v['status'] === 'Completed'): ?>
                                    <span class="badge" style="background: #d1fae5; color: #065f46; font-weight: 800;">
                                        Completed
                                    </span>
                                <?php elseif ($v['status'] === 'Manual correction (pending)' || $v['manual_timeout_status'] === 'Pending'): ?>
                                    <span class="badge" style="background: #fef3c7; color: #92400e; font-weight: 800;">
                                        Pending Admin
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background: #fef3c7; color: #b45309; font-weight: 800;">
                                        In Progress
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; padding-right: 20px;">
                                <button type="button" class="btn btn-secondary" style="padding: 5px 10px; font-size: 11.5px; white-space: nowrap;" onclick='openVisitDetailModal(<?= json_encode($v) ?>)'>
                                    👁️ View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Visit Details Modal -->
<div id="visitDetailModal" class="modal" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="modal-dialog" style="background: var(--bg-card); width: 100%; max-width: 840px; border-radius: 12px; border: 1px solid var(--border-subtle); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.4); overflow: hidden; max-height: 92vh; display: flex; flex-direction: column;">
        
        <div style="padding: 16px 22px; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.03);">
            <div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--text-main);" id="vdModalTitle">
                    Site Visit Report Details
                </h3>
                <div style="font-size: 11.5px; color: var(--text-dim); margin-top: 2px;" id="vdModalSubtitle"></div>
            </div>
            <button type="button" onclick="closeVisitDetailModal()" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--text-dim);">&times;</button>
        </div>

        <div style="padding: 22px; overflow-y: auto; flex: 1;">
            
            <!-- 1. Site Visit & Attendance Details Card -->
            <div style="background: rgba(0,0,0,0.02); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 14px 16px; margin-bottom: 18px;">
                <div style="font-size: 12px; font-weight: 800; color: var(--primary); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">
                    1. Site Visit &amp; Attendance Details
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px 16px; font-size: 12.5px;">
                    <div>
                        <span style="font-size: 11px; color: var(--text-dim); display: block;">Site Name &amp; Code:</span>
                        <strong id="vdSiteNameCode" style="color: var(--text-main);"></strong>
                    </div>
                    <div>
                        <span style="font-size: 11px; color: var(--text-dim); display: block;">Client &amp; Location:</span>
                        <strong id="vdClientLoc" style="color: var(--text-main);"></strong>
                    </div>
                    <div>
                        <span style="font-size: 11px; color: var(--text-dim); display: block;">Supervisor Name &amp; ID:</span>
                        <strong id="vdSupNameId" style="color: var(--text-main);"></strong>
                    </div>
                    <div>
                        <span style="font-size: 11px; color: var(--text-dim); display: block;">Date &amp; Shift:</span>
                        <strong id="vdDateShift" style="color: var(--text-main);"></strong>
                    </div>
                    <div>
                        <span style="font-size: 11px; color: var(--text-dim); display: block;">Arrival (QR Check-in):</span>
                        <strong id="vdTimeIn" style="color: #10b981; font-family: monospace;"></strong>
                    </div>
                    <div>
                        <span style="font-size: 11px; color: var(--text-dim); display: block;">Departure (QR Check-out):</span>
                        <strong id="vdTimeOut" style="font-family: monospace;"></strong>
                    </div>
                    <div>
                        <span style="font-size: 11px; color: var(--text-dim); display: block;">Total Time Spent at Site:</span>
                        <strong id="vdTimeSpent" style="color: #0284c7; font-weight: 850;"></strong>
                    </div>
                    <div>
                        <span style="font-size: 11px; color: var(--text-dim); display: block;">Client Representative:</span>
                        <strong id="vdClientRep" style="color: var(--text-main);"></strong>
                    </div>
                </div>
            </div>

            <!-- 2. Field Supervisor – Site Inspection Checklist (22 Items) -->
            <div style="margin-bottom: 18px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <div style="font-size: 12px; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px;">
                        2. Field Supervisor – Site Inspection Checklist (22 Items)
                    </div>
                    <span id="vdChecklistSummaryBadge" class="badge" style="background: rgba(2, 132, 199, 0.15); color: #0284c7; font-weight: 800; font-size: 11px;">
                        Loading summary...
                    </span>
                </div>
                
                <div id="vdChecklistTableContainer" style="border: 1px solid var(--border-subtle); border-radius: 8px; overflow: hidden; max-height: 240px; overflow-y: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                        <thead>
                            <tr style="background: rgba(0,0,0,0.04); text-align: left;">
                                <th style="padding: 7px 10px; width: 35px; border-bottom: 1px solid var(--border-subtle);">#</th>
                                <th style="padding: 7px 10px; border-bottom: 1px solid var(--border-subtle);">Inspection Item</th>
                                <th style="padding: 7px 10px; width: 90px; text-align: center; border-bottom: 1px solid var(--border-subtle);">Answer</th>
                                <th style="padding: 7px 10px; border-bottom: 1px solid var(--border-subtle);">Deficiency Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="vdChecklistTbody"></tbody>
                    </table>
                </div>
            </div>

            <!-- Mandatory Remarks Box -->
            <div style="margin-bottom: 18px;">
                <label style="font-size: 12px; font-weight: 800; color: var(--text-dim); text-transform: uppercase; display: block; margin-bottom: 6px;">
                    Mandatory Supervisor Remarks
                </label>
                <div id="vdRemarks" style="background: rgba(0,0,0,0.02); border: 1px solid var(--border-subtle); border-radius: 8px; padding: 12px 14px; font-size: 12.5px; line-height: 1.5; color: var(--text-main); font-weight: 600; white-space: pre-wrap;"></div>
            </div>

            <!-- 4. Corrective-Action Tracking -->
            <div id="vdCorrectiveActionSection" style="margin-bottom: 18px;">
                <div style="font-size: 12px; font-weight: 800; color: #dc2626; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">
                    4. Corrective-Action Tracking
                </div>
                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 14px;">
                    <div style="margin-bottom: 8px;">
                        <span style="font-size: 11px; color: #991b1b; font-weight: 700; text-transform: uppercase; display: block;">Issues Found &amp; Action Taken:</span>
                        <div id="vdIssuesAction" style="font-size: 12.5px; color: #7f1d1d; font-weight: 600; white-space: pre-wrap; margin-top: 2px;"></div>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; font-size: 12px; border-top: 1px solid #fee2e2; padding-top: 8px;">
                        <div>
                            <span style="font-size: 10.5px; color: #991b1b; display: block;">Responsible Person:</span>
                            <strong id="vdActionAssignee" style="color: #7f1d1d;"></strong>
                        </div>
                        <div>
                            <span style="font-size: 10.5px; color: #991b1b; display: block;">Due Date:</span>
                            <strong id="vdActionDueDate" style="color: #7f1d1d;"></strong>
                        </div>
                        <div>
                            <span style="font-size: 10.5px; color: #991b1b; display: block;">Status:</span>
                            <span id="vdActionStatusBadge"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Photos Gallery with Captions -->
            <div id="vdPhotosSection" style="margin-bottom: 10px; display: none;">
                <label style="font-size: 12px; font-weight: 800; color: var(--text-dim); text-transform: uppercase; display: block; margin-bottom: 8px;">
                    5. Photographic Evidence (<span id="vdPhotoCount">0</span> Photos)
                </label>
                <div id="vdPhotosGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 12px;"></div>
            </div>

        </div>

        <div style="padding: 14px 22px; border-top: 1px solid var(--border-subtle); display: flex; justify-content: flex-end; background: rgba(0,0,0,0.02);">
            <button type="button" class="btn btn-secondary" onclick="closeVisitDetailModal()">Close</button>
        </div>

    </div>
</div>

<script>
function renderBadgeRating(rating) {
    if (!rating) return '<span style="color:var(--text-dim);">N/A</span>';
    var bg = '#f1f5f9', color = '#64748b';
    if (rating === 'OK' || rating === 'Yes') { bg = '#d1fae5'; color = '#065f46'; }
    else if (rating === 'Issue' || rating === 'No') { bg = '#fee2e2'; color = '#991b1b'; }
    else if (rating === 'Unanswered') { bg = '#fef3c7'; color = '#92400e'; }
    return '<span class="badge" style="background:' + bg + '; color:' + color + '; font-weight:800; font-size:11px; padding:2px 7px; border-radius:4px;">' + rating + '</span>';
}

function openVisitDetailModal(v) {
    document.getElementById('vdModalTitle').innerText = (v.visit_ref || ('VIS-' + v.id)) + ' - ' + v.site_name;
    document.getElementById('vdModalSubtitle').innerText = 'Supervisor: ' + v.supervisor_name + ' (' + v.supervisor_staff_id + ') | Camp: ' + (v.camp_name || 'Camp');
    
    // 1. Site Visit & Attendance
    document.getElementById('vdSiteNameCode').innerText = v.site_name + ' (' + v.site_code + ')';
    document.getElementById('vdClientLoc').innerText = (v.client_name || 'Danube Properties') + ' • ' + (v.site_address || 'Dubai, UAE');
    document.getElementById('vdSupNameId').innerText = v.supervisor_name + ' (' + v.supervisor_staff_id + ')';
    document.getElementById('vdDateShift').innerText = (v.time_in ? v.time_in.substring(0, 10) : '--') + ' • ' + (v.shift_type || 'Day') + ' Shift';
    
    document.getElementById('vdTimeIn').innerText = v.time_in ? v.time_in.substring(11, 16) : '--';
    document.getElementById('vdTimeOut').innerText = v.time_out ? v.time_out.substring(11, 16) : 'In Progress';
    document.getElementById('vdTimeSpent').innerText = v.status === 'Completed' ? (v.minutes_spent + ' mins') : 'Ticking...';
    document.getElementById('vdClientRep').innerText = v.client_rep_name || '(None listed)';

    // Remarks
    document.getElementById('vdRemarks').innerText = v.remarks || '(No remarks entered)';

    // 2. 22-Item Checklist Table
    var summaryBadge = document.getElementById('vdChecklistSummaryBadge');
    var tbody = document.getElementById('vdChecklistTbody');
    tbody.innerHTML = '';

    if (v.checklist_summary) {
        summaryBadge.innerText = v.checklist_summary;
    } else {
        summaryBadge.innerText = 'Yes: ' + (v.checklist_yes_count || 0) + '; No: ' + (v.checklist_no_count || 0) + '; N/A: ' + (v.checklist_na_count || 0) + '; Unanswered: ' + (v.checklist_unanswered_count || 22);
    }

    var answers = null;
    try {
        if (v.checklist_answers) {
            answers = typeof v.checklist_answers === 'string' ? JSON.parse(v.checklist_answers) : v.checklist_answers;
        }
    } catch(e) {
        console.warn('Checklist parse error', e);
    }

    if (answers && Object.keys(answers).length > 0) {
        for (var qId in answers) {
            var item = answers[qId];
            var tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid var(--border-subtle)';
            if (item.answer === 'No') {
                tr.style.background = 'rgba(239, 68, 68, 0.05)';
            }

            var tdId = document.createElement('td');
            tdId.style.padding = '6px 10px';
            tdId.style.fontWeight = '700';
            tdId.innerText = item.id;

            var tdQ = document.createElement('td');
            tdQ.style.padding = '6px 10px';
            tdQ.innerText = item.question;

            var tdAns = document.createElement('td');
            tdAns.style.padding = '6px 10px';
            tdAns.style.textAlign = 'center';
            tdAns.innerHTML = renderBadgeRating(item.answer);

            var tdRem = document.createElement('td');
            tdRem.style.padding = '6px 10px';
            if (item.remarks) {
                tdRem.innerHTML = '<span style="color:#b91c1c; font-weight:600;">' + item.remarks + '</span>';
            } else {
                tdRem.innerHTML = '<span style="color:var(--text-dim); font-size:11px;">--</span>';
            }

            tr.appendChild(tdId);
            tr.appendChild(tdQ);
            tr.appendChild(tdAns);
            tr.appendChild(tdRem);
            tbody.appendChild(tr);
        }
    } else {
        var tr = document.createElement('tr');
        var td = document.createElement('td');
        td.colSpan = 4;
        td.style.padding = '14px';
        td.style.textAlign = 'center';
        td.style.color = 'var(--text-dim)';
        td.innerText = 'Legacy visit record: Detailed 22-item checklist not recorded for this historical visit.';
        tr.appendChild(td);
        tbody.appendChild(tr);
    }

    // 4. Corrective Action Section
    var caSection = document.getElementById('vdCorrectiveActionSection');
    if (v.has_issues == 1 || v.issues_action_taken || (v.checklist_no_count > 0)) {
        document.getElementById('vdIssuesAction').innerText = v.issues_action_taken || '(No specific action note entered)';
        document.getElementById('vdActionAssignee').innerText = v.corrective_action_assignee || 'Not specified';
        document.getElementById('vdActionDueDate').innerText = v.corrective_action_due_date || 'Immediate';
        var st = v.corrective_action_status || 'Pending';
        var stBg = st === 'Resolved' ? '#dcfce7' : '#fef3c7';
        var stColor = st === 'Resolved' ? '#166534' : '#92400e';
        document.getElementById('vdActionStatusBadge').innerHTML = '<span class="badge" style="background:' + stBg + '; color:' + stColor + '; font-weight:800;">' + st + '</span>';
        caSection.style.display = 'block';
    } else {
        caSection.style.display = 'none';
    }

    // 5. Photos Gallery with Captions
    var photosBox = document.getElementById('vdPhotosSection');
    var photosGrid = document.getElementById('vdPhotosGrid');
    photosGrid.innerHTML = '';
    var photos = v.photo_list || [];
    document.getElementById('vdPhotoCount').innerText = photos.length;
    
    if (photos.length > 0) {
        photos.forEach(function(p, pI) {
            var photoSrc = p.file_path || p.photo_url || '';
            var card = document.createElement('div');
            card.style.border = '1px solid var(--border-subtle)';
            card.style.borderRadius = '8px';
            card.style.overflow = 'hidden';
            card.style.background = 'rgba(0,0,0,0.02)';

            var a = document.createElement('a');
            a.href = '<?= APP_URL ?>/' + photoSrc;
            a.target = '_blank';
            a.style.display = 'block';
            a.style.aspectRatio = '4 / 3';
            a.style.overflow = 'hidden';
            
            var img = document.createElement('img');
            img.src = '<?= APP_URL ?>/' + photoSrc;
            img.style.width = '100%';
            img.style.height = '100%';
            img.style.objectFit = 'cover';
            img.style.display = 'block';
            a.appendChild(img);

            var captionDiv = document.createElement('div');
            captionDiv.style.padding = '6px 8px';
            captionDiv.style.fontSize = '11px';
            captionDiv.style.color = 'var(--text-main)';
            captionDiv.style.fontWeight = '600';
            captionDiv.innerText = p.caption ? p.caption : ('Evidence #' + (pI + 1));

            card.appendChild(a);
            card.appendChild(captionDiv);
            photosGrid.appendChild(card);
        });
        photosBox.style.display = 'block';
    } else {
        photosBox.style.display = 'none';
    }

    document.getElementById('visitDetailModal').style.display = 'flex';
}

function closeVisitDetailModal() {
    document.getElementById('visitDetailModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
