<?php
/**
 * Universal Security Services - Attendance Roster Subsystem
 * Master Interactive Sheet Template
 * File: /attendance_roster/templates/roster_sheet.php
 */
require_once dirname(__DIR__) . '/config/roster_config.php';
require_once dirname(__DIR__) . '/src/RosterEntry.php';
require_once dirname(__DIR__) . '/src/RosterSite.php';

$sites = RosterSite::getAll();
$curSiteId = isset($_GET['site_id']) ? (int)$_GET['site_id'] : ($sites[0]['site_id'] ?? 1);
$curYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$curMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

$data = RosterEntry::getMonthlySheet($curSiteId, $curYear, $curMonth);
$site = $data['site'];
$daysInMonth = $data['daysInMonth'];
$staffList = $data['staffList'];
$entries = $data['entries'];
$isLocked = $data['isLocked'];
$monthName = strtoupper(date('F', mktime(0, 0, 0, $curMonth, 1, $curYear)));
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USS Attendance Roster - <?= htmlspecialchars($site['site_name'] ?? 'Roster') ?></title>
    <link rel="stylesheet" href="public/css/roster.css">
</head>
<body>

<header class="roster-header-bar">
    <div class="roster-title-block">
        <h1>🏢 USS ATTENDANCE ROSTER</h1>
    </div>

    <form method="GET" action="index.php" class="roster-controls">
        <label for="select-site"><strong>Site:</strong></label>
        <select id="select-site" name="site_id" class="roster-select" onchange="this.form.submit()">
            <?php foreach ($sites as $s): ?>
                <option value="<?= $s['site_id'] ?>" <?= $s['site_id'] == $curSiteId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['site_name']) ?> <?= $s['site_code'] ? '(' . htmlspecialchars($s['site_code']) . ')' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="select-month"><strong>Month:</strong></label>
        <select id="select-month" name="month" class="roster-select" onchange="this.form.submit()">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m == $curMonth ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 10)) ?></option>
            <?php endfor; ?>
        </select>

        <label for="select-year"><strong>Year:</strong></label>
        <select id="select-year" name="year" class="roster-select" onchange="this.form.submit()">
            <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                <option value="<?= $y ?>" <?= $y == $curYear ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>

        <span id="roster-status-badge" style="font-size:12px;font-weight:700;color:#10b981;margin-left:8px;">● Stored in Database (MySQL)</span>
    </form>

    <div class="roster-controls">
        <button type="button" class="roster-btn" onclick="openModal('modal-additional-deployment')">➕ Additional Deployment</button>
        <button type="button" class="roster-btn" onclick="openModal('modal-find-replacement')">🔍 Find Replacement</button>
        <button type="button" class="roster-btn" onclick="openModal('modal-audit-trail')">📜 Audit &amp; Undo</button>
        <button type="button" class="roster-btn" onclick="openModal('modal-double-duty')">⚠️ Double Duty Report</button>
        <a href="index.php?action=export_excel&site_id=<?= $curSiteId ?>&year=<?= $curYear ?>&month=<?= $curMonth ?>" class="roster-btn roster-btn-primary">⬇ Excel (.xls)</a>
        <a href="index.php?action=export_pdf&site_id=<?= $curSiteId ?>&year=<?= $curYear ?>&month=<?= $curMonth ?>" class="roster-btn" target="_blank">🖨 Print / PDF</a>
        
        <form method="POST" action="index.php?action=toggle_lock" style="display:inline;">
            <input type="hidden" name="site_id" value="<?= $curSiteId ?>">
            <input type="hidden" name="year" value="<?= $curYear ?>">
            <input type="hidden" name="month" value="<?= $curMonth ?>">
            <input type="hidden" name="lock_action" value="<?= $isLocked ? 'unlock' : 'lock' ?>">
            <button type="submit" class="roster-btn <?= $isLocked ? 'roster-btn-warning' : '' ?>" onclick="return confirm('<?= $isLocked ? 'Unlock this month for adjustments?' : 'Lock this month? It will become read-only.' ?>')">
                <?= $isLocked ? '🔒 Unlock Month' : '🔓 Lock Month' ?>
            </button>
        </form>

        <button type="button" class="roster-btn" onclick="document.documentElement.dataset.theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'" title="Toggle Light/Dark Theme">🌓</button>
    </div>
</header>

<main class="roster-container">
    <div class="roster-grid-wrapper">
        <table class="roster-table" id="roster-grid-table" data-site-id="<?= $curSiteId ?>" data-year="<?= $curYear ?>" data-month="<?= $curMonth ?>" data-locked="<?= $isLocked ? '1' : '0' ?>">
            <thead>
                <!-- Executive Teal Title Banner (45px Height) -->
                <tr>
                    <th colspan="<?= $daysInMonth + 4 ?>" class="roster-banner-th">
                        Ultimate Security Services &bull; Attendance Roster for <?= htmlspecialchars($site['site_name'] ?? 'Site') ?> &bull; <?= $monthName ?> - <?= $curYear ?>
                    </th>
                </tr>

                <!-- Header Row 1: Emp No, Name, Site, Days 1..31 -->
                <tr>
                    <th rowspan="2" class="roster-th-meta roster-col-emp">Emp No</th>
                    <th rowspan="2" class="roster-th-meta roster-col-name">Guard Name</th>
                    <th rowspan="2" class="roster-th-meta roster-col-site">Site Name</th>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                        $w = strtoupper(substr(date('D', mktime(0, 0, 0, $curMonth, $d, $curYear)), 0, 3));
                        $isWk = in_array($w, ['FRI', 'SUN']);
                    ?>
                        <th class="roster-th-meta roster-col-day <?= $isWk ? 'roster-weekend' : '' ?>"><?= $d ?></th>
                    <?php endfor; ?>
                    <th rowspan="2" class="roster-th-meta" style="width:50px;">Action</th>
                </tr>

                <!-- Weekday Row 2: THU, FRI, SAT... -->
                <tr>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                        $w = strtoupper(substr(date('D', mktime(0, 0, 0, $curMonth, $d, $curYear)), 0, 3));
                        $isWk = in_array($w, ['FRI', 'SUN']);
                    ?>
                        <th class="roster-th-sub roster-col-day <?= $isWk ? 'roster-weekend' : '' ?>"><?= $w ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($staffList)): ?>
                    <tr>
                        <td colspan="<?= $daysInMonth + 4 ?>" style="padding:40px;color:var(--roster-text-muted);">
                            No guards assigned to this site for <?= $monthName ?> <?= $curYear ?>. Click "➕ Assign Staff" to populate.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($staffList as $st): $sid = $st['id']; ?>
                        <tr class="roster-row-staff" data-staff-id="<?= $sid ?>">
                            <td class="roster-col-emp"><?= htmlspecialchars($st['emp_no']) ?></td>
                            <td class="roster-col-name"><?= htmlspecialchars($st['name']) ?></td>
                            <td class="roster-col-site"><?= htmlspecialchars($site['site_name'] ?? '') ?></td>

                            <!-- Day Cells (Height 20px) -->
                            <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                                $dateStr = sprintf('%04d-%02d-%02d', $curYear, $curMonth, $d);
                                $entry = $entries[$sid][$d] ?? null;
                                $code = $entry['code'] ?? '';
                                $isDouble = !empty($entry['is_forced_double']);
                                $cls = $code ? 'roster-code-' . $code : '';
                                if ($isDouble) $cls .= ' roster-forced-double';
                            ?>
                                <td class="roster-col-day">
                                    <input type="text" 
                                           class="roster-cell-input <?= $cls ?>"
                                           data-day="<?= $d ?>"
                                           data-date="<?= $dateStr ?>"
                                           value="<?= htmlspecialchars($code) ?>"
                                           maxlength="3"
                                           <?= $isLocked ? 'readonly' : '' ?>
                                           autocomplete="off">
                                </td>
                            <?php endfor; ?>

                            <td>
                                <?php if (!$isLocked): ?>
                                    <form method="POST" action="index.php?action=remove_staff" onsubmit="return confirm('Remove empty future dates for this staff member? (Past attendance entries will strictly stay intact)')">
                                        <input type="hidden" name="staff_id" value="<?= $sid ?>">
                                        <input type="hidden" name="site_id" value="<?= $curSiteId ?>">
                                        <button type="submit" style="background:none;border:none;cursor:pointer;" title="De-link Staff">🗑️</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Separator Row -->
                <tr style="height:10px;background:#ffffff;"><td colspan="<?= $daysInMonth + 4 ?>" style="border:none;"></td></tr>

                <!-- Summary Row 1: REQUIRED DAY -->
                <tr>
                    <td colspan="3" class="roster-summary-lbl">REQUIRED DAY:</td>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                        <td class="roster-col-day" style="font-weight:700;"><?= $data['requirements'][$d]['required_day'] ?></td>
                    <?php endfor; ?>
                    <td></td>
                </tr>

                <!-- Summary Row 2: REQUIRED NIGHT -->
                <tr>
                    <td colspan="3" class="roster-summary-lbl">REQUIRED NIGHT:</td>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                        <td class="roster-col-day" style="font-weight:700;"><?= $data['requirements'][$d]['required_night'] ?></td>
                    <?php endfor; ?>
                    <td></td>
                </tr>

                <!-- Summary Row 3: ACTUAL DAY SHIFT (D) with WCAG AA Color Control -->
                <tr>
                    <td colspan="3" class="roster-summary-lbl">ACTUAL DAY SHIFT (D):</td>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                        $act = $data['dailySummary'][$d]['act_day'];
                        $req = $data['requirements'][$d]['required_day'];
                        $st = ($req === 0 && $act === 0) ? 'roster-deploy-total' : ($act === $req ? 'roster-deploy-ok' : ($act < $req ? 'roster-deploy-less' : 'roster-deploy-over'));
                        $sym = ($req === 0) ? '' : ($act === $req ? ' =' : ($act < $req ? ' ▼' . ($req - $act) : ' ▲' . ($act - $req)));
                    ?>
                        <td class="roster-col-day roster-act-day <?= $st ?>" data-day="<?= $d ?>" data-req-d="<?= $req ?>">
                            <?= $act ?> <span style="font-size:9.5px;"><?= $sym ?></span>
                        </td>
                    <?php endfor; ?>
                    <td></td>
                </tr>

                <!-- Summary Row 4: ACTUAL NIGHT SHIFT (N) with WCAG AA Color Control -->
                <tr>
                    <td colspan="3" class="roster-summary-lbl">ACTUAL NIGHT SHIFT (N):</td>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                        $act = $data['dailySummary'][$d]['act_night'];
                        $req = $data['requirements'][$d]['required_night'];
                        $st = ($req === 0 && $act === 0) ? 'roster-deploy-total' : ($act === $req ? 'roster-deploy-ok' : ($act < $req ? 'roster-deploy-less' : 'roster-deploy-over'));
                        $sym = ($req === 0) ? '' : ($act === $req ? ' =' : ($act < $req ? ' ▼' . ($req - $act) : ' ▲' . ($act - $req)));
                    ?>
                        <td class="roster-col-day roster-act-night <?= $st ?>" data-day="<?= $d ?>" data-req-n="<?= $req ?>">
                            <?= $act ?> <span style="font-size:9.5px;"><?= $sym ?></span>
                        </td>
                    <?php endfor; ?>
                    <td></td>
                </tr>

                <!-- Summary Row 5: TOTAL ON DUTY (D + N) (Light Grey) -->
                <tr>
                    <td colspan="3" class="roster-summary-lbl roster-deploy-total">TOTAL ON DUTY (D + N):</td>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                        <td class="roster-col-day roster-act-total roster-deploy-total" data-day="<?= $d ?>">
                            <?= $data['dailySummary'][$d]['act_total'] ?>
                        </td>
                    <?php endfor; ?>
                    <td></td>
                </tr>

                <!-- Summary Row 6: DAY VARIANCE -->
                <tr>
                    <td colspan="3" class="roster-summary-lbl">DAY VARIANCE:</td>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                        $var = $data['dailySummary'][$d]['var_day'];
                    ?>
                        <td class="roster-col-day roster-var-day" data-day="<?= $d ?>"><?= $var > 0 ? "+$var" : $var ?></td>
                    <?php endfor; ?>
                    <td></td>
                </tr>

                <!-- Summary Row 7: NIGHT VARIANCE -->
                <tr>
                    <td colspan="3" class="roster-summary-lbl">NIGHT VARIANCE:</td>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                        $var = $data['dailySummary'][$d]['var_night'];
                    ?>
                        <td class="roster-col-day roster-var-night" data-day="<?= $d ?>"><?= $var > 0 ? "+$var" : $var ?></td>
                    <?php endfor; ?>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Monthly Staff-Days & Variance Summary Card -->
    <div style="margin-top:16px;display:flex;gap:16px;flex-wrap:wrap;">
        <div style="flex:1;background:var(--roster-card-bg);border:1px solid var(--roster-border);border-radius:8px;padding:14px;">
            <div style="font-size:11px;font-weight:800;color:var(--roster-text-muted);text-transform:uppercase;">Month Shifts Completed</div>
            <div style="font-size:20px;font-weight:900;margin-top:4px;">
                ☀️ Day: <span id="roster-month-tot-day"><?= $data['monthTotalDay'] ?></span> | 
                🌙 Night: <span id="roster-month-tot-night"><?= $data['monthTotalNight'] ?></span> | 
                📋 Total Duties: <span id="roster-month-tot-grand"><?= $data['monthTotalDuty'] ?></span>
            </div>
        </div>

        <div style="flex:1;background:var(--roster-card-bg);border:1px solid var(--roster-border);border-radius:8px;padding:14px;">
            <div style="font-size:11px;font-weight:800;color:var(--roster-text-muted);text-transform:uppercase;">Additional Staff-Days &amp; Variances</div>
            <div style="font-size:13px;font-weight:700;margin-top:4px;">
                ➕ Additional Deployed: <strong><?= $data['totalAdditionalDayStaffDays'] ?> Day / <?= $data['totalAdditionalNightStaffDays'] ?> Night staff-days</strong><br>
                🔴 Shortages: <strong><?= $data['totalShortageStaffDays'] ?> staff-days</strong> | 
                🟡 Surplus: <strong><?= $data['totalSurplusStaffDays'] ?> staff-days</strong>
            </div>
        </div>
    </div>
</main>

<!-- ========================================================================
     MODAL: RULE R4 CONFLICT RESOLUTION
     ======================================================================== -->
<div id="modal-roster-conflict" class="roster-modal">
    <div class="roster-modal-content">
        <div class="roster-modal-header" style="background:#b91c1c;">
            <h3 style="margin:0;font-size:15px;display:flex;align-items:center;gap:8px;">⚠️ Attendance Conflict Detected</h3>
            <button type="button" onclick="closeModal('modal-roster-conflict')" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
        </div>
        <div class="roster-modal-body">
            <p id="roster-conflict-message" style="font-size:14px;font-weight:700;color:#991b1b;background:#fee2e2;padding:12px;border-radius:6px;line-height:1.4;margin:0 0 16px 0;"></p>
            <p style="font-size:12px;color:var(--roster-text-muted);margin:0 0 12px 0;">Per company rule <strong>R3</strong>, an employee may only work one duty per calendar date across all USS sites. Choose one of the authorized administrative actions below:</p>

            <div style="margin-bottom:14px;">
                <label for="roster-conflict-reason" style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Administrative Reason (Mandatory for Move or Force Double):</label>
                <input type="text" id="roster-conflict-reason" style="width:100%;box-sizing:border-box;padding:8px;border:1px solid var(--roster-border);border-radius:6px;font-size:13px;" placeholder="e.g. Relocated for client urgent event / Emergency coverage">
            </div>

            <div style="display:flex;flex-direction:column;gap:8px;">
                <button type="button" class="roster-btn" id="btn-conflict-cancel" style="width:100%;justify-content:center;">1. Cancel Action (Make No Changes)</button>
                <button type="button" class="roster-btn" id="btn-conflict-skip" style="width:100%;justify-content:center;">2. Assign Free Dates Only (Skip Conflicting Date)</button>
                <button type="button" class="roster-btn roster-btn-warning" id="btn-conflict-move" style="width:100%;justify-content:center;">3. Move Attendance to This Site (Previous site loses duty)</button>
                <button type="button" class="roster-btn roster-btn-danger" id="btn-conflict-force" style="width:100%;justify-content:center;">4. Force Assign as Double Duty (Super Admin Only)</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================
     MODAL: ADDITIONAL DEPLOYMENT MANAGER
     ======================================================================== -->
<div id="modal-additional-deployment" class="roster-modal">
    <div class="roster-modal-content">
        <div class="roster-modal-header">
            <h3 style="margin:0;font-size:15px;">➕ Configure Additional Deployment</h3>
            <button type="button" onclick="closeModal('modal-additional-deployment')" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
        </div>
        <div class="roster-modal-body">
            <form method="POST" action="index.php?action=add_deployment">
                <input type="hidden" name="site_id" value="<?= $curSiteId ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                    <div>
                        <label style="font-size:11px;font-weight:700;display:block;">Shift:</label>
                        <select name="shift" class="roster-select" style="width:100%;">
                            <option value="D">Day Shift (D)</option>
                            <option value="N">Night Shift (N)</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:11px;font-weight:700;display:block;">Extra Staff Count:</label>
                        <input type="number" name="staff_count" value="1" min="1" max="50" style="width:100%;padding:6px;box-sizing:border-box;border:1px solid var(--roster-border);border-radius:6px;">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                    <div>
                        <label style="font-size:11px;font-weight:700;display:block;">Date From:</label>
                        <input type="date" name="date_from" required style="width:100%;padding:6px;box-sizing:border-box;border:1px solid var(--roster-border);border-radius:6px;">
                    </div>
                    <div>
                        <label style="font-size:11px;font-weight:700;display:block;">Date To:</label>
                        <input type="date" name="date_to" required style="width:100%;padding:6px;box-sizing:border-box;border:1px solid var(--roster-border);border-radius:6px;">
                    </div>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:11px;font-weight:700;display:block;">Reason / Request Reference:</label>
                    <input type="text" name="reason" required placeholder="e.g. VIP Exhibition event extra guard" style="width:100%;padding:6px;box-sizing:border-box;border:1px solid var(--roster-border);border-radius:6px;">
                </div>
                <button type="submit" class="roster-btn roster-btn-primary" style="width:100%;">Save Additional Deployment</button>
            </form>

            <h4 style="margin:20px 0 8px 0;font-size:12px;border-bottom:1px solid var(--roster-border);padding-bottom:4px;">Active Additional Deployments for this Month:</h4>
            <?php if (empty($data['additionalDeployments'])): ?>
                <p style="font-size:11px;color:var(--roster-text-muted);">None configured for this month.</p>
            <?php else: ?>
                <table style="width:100%;font-size:11px;text-align:left;">
                    <thead><tr style="background:#f1f5f9;"><th>Shift</th><th>Count</th><th>Dates</th><th>Reason</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($data['additionalDeployments'] as $add): ?>
                            <tr>
                                <td><strong><?= $add['shift'] === 'D' ? '☀️ Day' : '🌙 Night' ?></strong></td>
                                <td>+<?= $add['staff_count'] ?></td>
                                <td><?= date('d M', strtotime($add['date_from'])) ?> &rarr; <?= date('d M', strtotime($add['date_to'])) ?></td>
                                <td><?= htmlspecialchars($add['reason']) ?></td>
                                <td>
                                    <form method="POST" action="index.php?action=delete_deployment" style="display:inline;">
                                        <input type="hidden" name="deployment_id" value="<?= $add['id'] ?>">
                                        <button type="submit" style="background:none;border:none;color:#dc2626;cursor:pointer;">&times; Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========================================================================
     MODAL: FIND REPLACEMENT GUARDS
     ======================================================================== -->
<div id="modal-find-replacement" class="roster-modal">
    <div class="roster-modal-content">
        <div class="roster-modal-header">
            <h3 style="margin:0;font-size:15px;">🔍 Find Replacement Guard</h3>
            <button type="button" onclick="closeModal('modal-find-replacement')" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
        </div>
        <div class="roster-modal-body">
            <p style="font-size:12px;color:var(--roster-text-muted);margin:0 0 10px 0;">Find guards with <strong>zero scheduled duties anywhere</strong> on the selected date:</p>
            <div style="display:flex;gap:10px;margin-bottom:14px;">
                <input type="date" id="find-rep-date" value="<?= date('Y-m-d') ?>" style="padding:6px;border:1px solid var(--roster-border);border-radius:6px;">
                <button type="button" class="roster-btn roster-btn-primary" onclick="searchFreeGuards()">Search Free Staff</button>
            </div>
            <div id="find-rep-results" style="max-height:220px;overflow-y:auto;font-size:12px;"></div>
        </div>
    </div>
</div>

<!-- ========================================================================
     MODAL: AUDIT TRAIL & UNDO
     ======================================================================== -->
<div id="modal-audit-trail" class="roster-modal">
    <div class="roster-modal-content" style="max-width:700px;">
        <div class="roster-modal-header">
            <h3 style="margin:0;font-size:15px;">📜 Administrative Audit Trail &amp; Undo Stack</h3>
            <button type="button" onclick="closeModal('modal-audit-trail')" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
        </div>
        <div class="roster-modal-body" id="audit-trail-body">
            <p style="font-size:11px;color:var(--roster-text-muted);">Loading audit trail...</p>
        </div>
    </div>
</div>

<!-- ========================================================================
     MODAL: DOUBLE DUTY REPORT
     ======================================================================== -->
<div id="modal-double-duty" class="roster-modal">
    <div class="roster-modal-content">
        <div class="roster-modal-header" style="background:#b45309;">
            <h3 style="margin:0;font-size:15px;color:#fff;">⚠️ Forced Double Duty Audit Report</h3>
            <button type="button" onclick="closeModal('modal-double-duty')" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
        </div>
        <div class="roster-modal-body" id="double-duty-body">
            <p style="font-size:11px;color:var(--roster-text-muted);">Loading double duties...</p>
        </div>
    </div>
</div>

<script src="public/js/roster.js"></script>
<script>
function searchFreeGuards() {
    const date = document.getElementById('find-rep-date').value;
    const resEl = document.getElementById('find-rep-results');
    resEl.innerHTML = '<p>Searching free staff...</p>';

    fetch(`index.php?action=free_staff&date=${date}`)
    .then(r => r.json())
    .then(data => {
        if (!data.staff || data.staff.length === 0) {
            resEl.innerHTML = '<p style="color:#ef4444;">No free staff found on this date.</p>';
            return;
        }
        let html = '<table style="width:100%;border-collapse:collapse;">';
        data.staff.forEach(s => {
            html += `<tr style="border-bottom:1px solid #e2e8f0;padding:6px 0;">
                <td style="padding:6px;"><strong>${s.name}</strong> (${s.emp_no})</td>
                <td style="padding:6px;color:#64748b;">${s.designation}</td>
            </tr>`;
        });
        html += '</table>';
        resEl.innerHTML = html;
    });
}
</script>
</body>
</html>
