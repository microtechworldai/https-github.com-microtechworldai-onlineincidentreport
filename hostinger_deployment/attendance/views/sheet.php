<?php
// Attendance & Payroll Module - Attendance Roster Main View
// Enterprise Executive Redesign
// File: hostinger_deployment/attendance/views/sheet.php

if (!defined('ATT_ACCESS')) {
    die('Direct access not permitted.');
}

$pageTitle = 'Attendance Roster';
$activeTab = 'sheet';
require __DIR__ . '/layout/header.php';

$curYear = (int)($selectedYear ?? date('Y'));
$curMonth = (int)($selectedMonth ?? date('n'));
$curSiteId = (int)($selectedSiteId ?? 0);

$sheet = $sheetData['sheet'] ?? null;
$rows = $sheetData['rows'] ?? [];
$daysInMonth = (int)($sheetData['daysInMonth'] ?? $sheetData['days_in_month'] ?? date('t', mktime(0, 0, 0, $curMonth, 1, $curYear)));
$dayShiftCounts = $sheetData['dayShiftCounts'] ?? array_fill(1, $daysInMonth, 0);
$nightShiftCounts = $sheetData['nightShiftCounts'] ?? array_fill(1, $daysInMonth, 0);
$totalDutyCounts = $sheetData['totalDutyCounts'] ?? array_fill(1, $daysInMonth, 0);
$isLocked = !empty($sheet) && ($sheet['status'] === 'Locked' || !empty($sheet['is_locked']));

$dayRequirements = $sheetData['dayRequirements'] ?? array_fill(1, $daysInMonth, 1);
$nightRequirements = $sheetData['nightRequirements'] ?? array_fill(1, $daysInMonth, 1);
$totalRequirements = $sheetData['totalRequirements'] ?? array_fill(1, $daysInMonth, 2);
$dayDeployStatuses = $sheetData['dayDeployStatuses'] ?? [];
$nightDeployStatuses = $sheetData['nightDeployStatuses'] ?? [];
$totalDeployStatuses = $sheetData['totalDeployStatuses'] ?? [];
$additionalDeployments = $sheetData['additionalDeployments'] ?? [];

$siteReqDay = isset($sheet['req_day']) ? (int)$sheet['req_day'] : 1;
$siteReqNight = isset($sheet['req_night']) ? (int)$sheet['req_night'] : 1;
$siteReqTotal = $siteReqDay + $siteReqNight;
$contractStart = $sheet['contract_start'] ?? '';
$contractEnd = $sheet['contract_end'] ?? '';

$monthName = date('F', mktime(0, 0, 0, $curMonth, 10));
$prevMonth = $curMonth === 1 ? 12 : $curMonth - 1;
$prevYear = $curMonth === 1 ? $curYear - 1 : $curYear;

$monthTotalDayShifts = array_sum($dayShiftCounts);
$monthTotalNightShifts = array_sum($nightShiftCounts);
$monthTotalDuties = $monthTotalDayShifts + $monthTotalNightShifts;
$expectedMonthlyTotal = $siteReqTotal * $daysInMonth;
$fulfillmentPct = $expectedMonthlyTotal > 0 ? round(($monthTotalDuties / $expectedMonthlyTotal) * 100, 1) : 100;
?>

<?php if ($sheet): ?>
<!-- 1. EXECUTIVE KPI STATS RIBBON -->
<div class="att-kpi-ribbon no-print">
    <!-- Card 1: Site & Contract Details -->
    <div class="att-kpi-card att-kpi-primary">
        <div class="att-kpi-top">
            <span class="att-kpi-title">Deployment Post</span>
            <span class="att-kpi-tag att-tag-blue">Active Site</span>
        </div>
        <div class="att-kpi-value" style="font-size: 19px;">
            🏢 <?= htmlspecialchars($sheet['site_name']) ?>
        </div>
        <div class="att-kpi-meta">
            <span>📅 Contract: <strong><?= !empty($contractStart) ? htmlspecialchars($contractStart) : 'Open' ?> &rarr; <?= !empty($contractEnd) ? htmlspecialchars($contractEnd) : 'Ongoing' ?></strong></span>
            <?php if (!empty($additionalDeployments)): ?>
                <span class="att-kpi-tag att-tag-amber" style="margin-left:auto;">⚡ <?= count($additionalDeployments) ?> Extra Days</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Card 2: Required Post Strength -->
    <div class="att-kpi-card att-kpi-warning">
        <div class="att-kpi-top">
            <span class="att-kpi-title">Required Post Strength</span>
            <span class="att-kpi-tag att-tag-amber"><?= $siteReqTotal ?> Guards / Day</span>
        </div>
        <div class="att-kpi-value">
            <span>☀️ <?= $siteReqDay ?> Day</span>
            <span class="att-kpi-subval">&bull;</span>
            <span>🌙 <?= $siteReqNight ?> Night</span>
        </div>
        <div class="att-kpi-meta">
            <span>Expected Month Total: <strong><?= $expectedMonthlyTotal ?> Duties</strong></span>
        </div>
    </div>

    <!-- Card 3: Roster Fulfillment Health -->
    <div class="att-kpi-card att-kpi-success">
        <div class="att-kpi-top">
            <span class="att-kpi-title">Roster Delivery Rate</span>
            <span class="att-kpi-tag <?= $fulfillmentPct >= 100 ? 'att-tag-green' : 'att-tag-red' ?>">
                <?= $fulfillmentPct >= 100 ? '🟢 100% Fulfilled' : '🔴 ' . $fulfillmentPct . '% Coverage' ?>
            </span>
        </div>
        <div class="att-kpi-value" style="color: #10b981;">
            <?= $monthTotalDuties ?> <span class="att-kpi-subval">/ <?= $expectedMonthlyTotal ?> Duties</span>
        </div>
        <div class="att-kpi-meta">
            <span>Day: <strong><?= $monthTotalDayShifts ?></strong> &bull; Night: <strong><?= $monthTotalNightShifts ?></strong></span>
        </div>
    </div>

    <!-- Card 4: Personnel & Roster Status -->
    <div class="att-kpi-card att-kpi-purple">
        <div class="att-kpi-top">
            <span class="att-kpi-title">Assigned Personnel</span>
            <span class="att-kpi-tag <?= $isLocked ? 'att-tag-amber' : 'att-tag-green' ?>">
                <?= $isLocked ? '🔒 Locked' : '🔓 Live Edit' ?>
            </span>
        </div>
        <div class="att-kpi-value">
            👥 <?= count($rows) ?> <span class="att-kpi-subval">Guards Rostered</span>
        </div>
        <div class="att-kpi-meta">
            <span>Period: <strong><?= $monthName ?> <?= $curYear ?></strong></span>
            <span style="margin-left:auto; font-size:11px; color:#10b981;">● Auto-Saved</span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 2. MODERN CONTROL & ACTION TOOLBAR -->
<div class="att-controlbar no-print">
    <form method="GET" action="index.php" class="att-filters">
        <input type="hidden" name="action" value="sheet">
        
        <label for="att-select-site"><strong>Site:</strong></label>
        <select id="att-select-site" name="site_id" class="att-select" onchange="this.form.submit()">
            <?php if (empty($sites)): ?>
                <option value="">-- No Sites Created Yet --</option>
            <?php else: ?>
                <?php foreach ($sites as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $s['id'] == $curSiteId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['site_name']) ?> <?= !empty($s['site_code']) ? '(' . htmlspecialchars($s['site_code']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>

        <label for="att-select-month"><strong>Month:</strong></label>
        <select id="att-select-month" name="month" class="att-select" onchange="this.form.submit()">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m == $curMonth ? 'selected' : '' ?>>
                    <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                </option>
            <?php endfor; ?>
        </select>

        <label for="att-select-year"><strong>Year:</strong></label>
        <select id="att-select-year" name="year" class="att-select" onchange="this.form.submit()">
            <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                <option value="<?= $y ?>" <?= $y == $curYear ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>

        <span id="att-status-indicator" class="att-status-indicator att-status-saved">
            <span>●</span> Stored in MySQL
        </span>
    </form>

    <div class="att-actions">
        <button type="button" class="att-btn att-btn-secondary" id="att-btn-add-site">+ New Site</button>
        <?php if ($sheet): ?>
            <button type="button" class="att-btn att-btn-secondary" id="att-btn-staff-list">👥 Staff List</button>
            <button type="button" class="att-btn att-btn-primary" id="att-btn-add-staff">+ Add Staff</button>
            <button type="button" class="att-btn att-btn-secondary" id="att-btn-add-deployment" title="Manage additional deployment for this month or specific dates">➕ Additional Deployment</button>
            <button type="button" class="att-btn att-btn-secondary" id="att-btn-site-reqs" title="Edit site requirements & contract dates">⚙️ Requirements</button>
            <button type="button" class="att-btn att-btn-secondary" id="att-btn-copy-prev" 
                    data-site-id="<?= $sheet['site_id'] ?>" data-year="<?= $curYear ?>" data-month="<?= $curMonth ?>">
                ↻ Copy from Prev Month
            </button>
            <button type="button" class="att-btn <?= $isLocked ? 'att-btn-warning' : 'att-btn-secondary' ?>" id="att-btn-lock" 
                    data-sheet-id="<?= $sheet['id'] ?>" data-lock="<?= $isLocked ? '0' : '1' ?>">
                <?= $isLocked ? '🔒 Unlock Sheet' : '🔓 Lock Sheet' ?>
            </button>
            <a href="index.php?action=export_excel&export_type=sheet&sheet_id=<?= $sheet['id'] ?>" class="att-btn att-btn-success">
                ⬇ Excel (.xls)
            </a>
            <a href="index.php?action=export_pdf&sheet_id=<?= $sheet['id'] ?>" class="att-btn att-btn-secondary" target="_blank">
                🖨 Print / PDF
            </a>
            <a href="index.php?action=backup_download" class="att-btn att-btn-secondary" title="Download complete SQL database backup">
                🛡️ Backup
            </a>
            <button type="button" class="att-btn att-btn-danger" id="att-btn-delete-site" title="Delete this site from attendance">🗑️</button>
        <?php endif; ?>
    </div>
</div>

<main class="att-container">
    <?php if (!$sheet): ?>
        <div style="background:var(--att-card-bg);padding:60px 40px;text-align:center;border-radius:12px;border:1px solid var(--att-card-border);box-shadow:var(--att-shadow-md);">
            <div style="font-size:48px;margin-bottom:12px;">🏢</div>
            <h2 style="margin:0 0 8px 0;color:var(--att-text);font-weight:800;">Welcome to Attendance Roster</h2>
            <p style="color:var(--att-text-muted);max-width:540px;margin:0 auto 24px;font-size:14px;line-height:1.5;">
                No site has been selected or created for attendance tracking yet. Select an existing site from the dropdown or click below to register a new site.
            </p>
            <button type="button" class="att-btn att-btn-primary" style="padding:10px 22px;font-size:14px;" onclick="document.getElementById('att-modal-new-site').classList.add('att-open')">+ Create First Site</button>
        </div>
    <?php else: ?>
        <div class="att-grid-wrapper">
            <table class="att-roster-outer-table" id="att-roster-table" data-sheet-id="<?= $sheet['id'] ?>" data-locked="<?= $isLocked ? '1' : '0' ?>">
                <thead>
                    <!-- Executive Title Banner -->
                    <tr>
                        <th colspan="<?= $daysInMonth + 4 ?>" class="att-th-banner-darkteal">
                            Ultimate Security Services &bull; Attendance Roster for <?= $monthName ?> - <?= $curYear ?>
                        </th>
                    </tr>

                    <!-- Top Header Row 1: Emp No, Name, Site Name, Day Numbers 1 to 31 -->
                    <tr>
                        <th rowspan="2" class="att-th-top-row att-col-emp">Emp No</th>
                        <th rowspan="2" class="att-th-top-row att-col-name">Guard Name</th>
                        <th rowspan="2" class="att-th-top-row att-col-site">Site Name</th>

                        <!-- Days of Month (1 to 28/29/30/31) -->
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                            $wName = strtoupper(substr(date('D', mktime(0, 0, 0, $curMonth, $d, $curYear)), 0, 3));
                            $isWk = in_array($wName, ['FRI', 'SUN']);
                        ?>
                            <th class="att-th-top-row att-col-day <?= $isWk ? 'att-th-teal-weekend' : '' ?>"><?= $d ?></th>
                        <?php endfor; ?>

                        <!-- Action Column Header -->
                        <th rowspan="2" class="att-th-top-row att-col-action no-print">Action</th>
                    </tr>

                    <!-- Weekday Names Row 2 (SAT, SUN, MON...) -->
                    <tr>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                            $wName = strtoupper(substr(date('D', mktime(0, 0, 0, $curMonth, $d, $curYear)), 0, 3));
                            $isWk = in_array($wName, ['FRI', 'SUN']);
                        ?>
                            <th class="att-th-teal-sub att-col-day <?= $isWk ? 'att-th-teal-weekend' : '' ?>"><?= $wName ?></th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="<?= $daysInMonth + 4 ?>" style="padding:40px;color:var(--att-text-muted);text-align:center;">
                                <div style="font-size:24px;margin-bottom:8px;">👥</div>
                                <div style="font-size:14px;font-weight:700;">No security staff assigned to this site for <?= $monthName ?> <?= $curYear ?></div>
                                <div style="margin-top:6px;font-size:12px;">Click "<strong>+ Add Staff</strong>" or "<strong>↻ Copy from Prev Month</strong>" in the toolbar above to populate this roster.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        foreach ($rows as $r): 
                            $displayName = $r['emp_name_snapshot'] ?? $r['staff_name'] ?? $r['emp_name'] ?? '';
                            $cellVals = $r['days'] ?? $r['cells'] ?? [];
                        ?>
                            <tr class="att-row-staff" data-row-id="<?= $r['id'] ?>" data-emp-no="<?= htmlspecialchars($r['emp_no'] ?? '') ?>">
                                <td class="att-col-emp"><?= htmlspecialchars($r['emp_no'] ?? '') ?></td>
                                <td class="att-col-name"><?= htmlspecialchars($displayName) ?></td>
                                <td class="att-col-site"><?= htmlspecialchars($sheet['site_name']) ?></td>

                                <!-- Day Boxes -->
                                <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                                    $val = $cellVals[$d] ?? '';
                                    $wName = strtoupper(substr(date('D', mktime(0, 0, 0, $curMonth, $d, $curYear)), 0, 3));
                                    $isWk = in_array($wName, ['FRI', 'SUN']);
                                    $codeCls = in_array($val, ['D', 'N', 'OFF', 'S', 'B']) ? 'att-code-' . $val : '';
                                ?>
                                    <td class="att-col-day <?= $isWk ? 'att-td-weekend' : '' ?>">
                                        <input type="text" 
                                               class="att-cell-input <?= $codeCls ?>" 
                                               data-day="<?= $d ?>" 
                                               value="<?= htmlspecialchars($val) ?>" 
                                               maxlength="3"
                                               <?= $isLocked ? 'readonly' : '' ?>
                                               autocomplete="off">
                                    </td>
                                <?php endfor; ?>

                                <!-- Action Buttons: Replace & Delete -->
                                <td class="att-col-action no-print">
                                    <?php if (!$isLocked): ?>
                                        <button type="button" class="att-row-btn-icon att-btn-replace-staff" 
                                                data-row-id="<?= $r['id'] ?>" 
                                                data-emp-no="<?= htmlspecialchars($r['emp_no'] ?? '', ENT_QUOTES) ?>" 
                                                data-name="<?= htmlspecialchars($displayName, ENT_QUOTES) ?>" 
                                                title="Replace Guard">🔁</button>
                                        <button type="button" class="att-row-btn-icon att-btn-remove-staff" 
                                                data-row-id="<?= $r['id'] ?>" 
                                                data-staff-name="<?= htmlspecialchars($displayName, ENT_QUOTES) ?>" 
                                                title="Delete Row">🗑️</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Bottom Summary Row 1: Day Shift (D) -->
                    <tr class="att-tr-day-total">
                        <td colspan="3" class="att-summary-label">DAY SHIFT (D):</td>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                            $stD = $dayDeployStatuses[$d] ?? 'neutral';
                            $clsD = 'att-deploy-' . $stD;
                            $reqD = $dayRequirements[$d] ?? 0;
                            $cntD = $dayShiftCounts[$d] ?: '0';
                            $tipD = "Day {$d} Day Shift: Deployed {$cntD} / Required {$reqD}";
                        ?>
                            <td class="att-col-total-d <?= $clsD ?>" data-day="<?= $d ?>" data-req-d="<?= $reqD ?>" title="<?= htmlspecialchars($tipD) ?>"><?= $cntD ?></td>
                        <?php endfor; ?>
                        <td class="att-col-action no-print"></td>
                    </tr>

                    <!-- Bottom Summary Row 2: Night Shift (N) -->
                    <tr class="att-tr-night-total">
                        <td colspan="3" class="att-summary-label">NIGHT SHIFT (N):</td>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                            $stN = $nightDeployStatuses[$d] ?? 'neutral';
                            $clsN = 'att-deploy-' . $stN;
                            $reqN = $nightRequirements[$d] ?? 0;
                            $cntN = $nightShiftCounts[$d] ?: '0';
                            $tipN = "Day {$d} Night Shift: Deployed {$cntN} / Required {$reqN}";
                        ?>
                            <td class="att-col-total-n <?= $clsN ?>" data-day="<?= $d ?>" data-req-n="<?= $reqN ?>" title="<?= htmlspecialchars($tipN) ?>"><?= $cntN ?></td>
                        <?php endfor; ?>
                        <td class="att-col-action no-print"></td>
                    </tr>

                    <!-- Bottom Summary Row 3: Total on Duty (D + N) -->
                    <tr class="att-tr-grand-total">
                        <td colspan="3" class="att-summary-label">TOTAL ON DUTY (D + N):</td>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                            $stTot = $totalDeployStatuses[$d] ?? 'neutral';
                            $clsTot = 'att-deploy-' . $stTot;
                            $reqTot = $totalRequirements[$d] ?? 0;
                            $cntTot = $totalDutyCounts[$d] ?: '0';
                            $tipTot = "Day {$d} Total Duty: Deployed {$cntTot} / Required {$reqTot}";
                        ?>
                            <td class="att-col-total-grand <?= $clsTot ?>" data-day="<?= $d ?>" data-req-total="<?= $reqTot ?>" title="<?= htmlspecialchars($tipTot) ?>"><?= $cntTot ?></td>
                        <?php endfor; ?>
                        <td class="att-col-action no-print"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- 3. EXECUTIVE MONTH SUMMARY RIBBON -->
        <div class="att-month-total-ribbon">
            <div class="att-metric-chip">
                <div class="att-chip-icon">☀️</div>
                <div class="att-chip-info">
                    <span class="att-chip-lbl">Month Day Shifts</span>
                    <span class="att-chip-val" id="att-month-total-d"><?= $monthTotalDayShifts ?></span>
                </div>
            </div>
            <div class="att-metric-chip">
                <div class="att-chip-icon" style="background:rgba(59,130,246,0.15);color:#3b82f6;">🌙</div>
                <div class="att-chip-info">
                    <span class="att-chip-lbl">Month Night Shifts</span>
                    <span class="att-chip-val" id="att-month-total-n"><?= $monthTotalNightShifts ?></span>
                </div>
            </div>
            <div class="att-metric-chip">
                <div class="att-chip-icon" style="background:rgba(16,185,129,0.15);color:#10b981;">📋</div>
                <div class="att-chip-info">
                    <span class="att-chip-lbl">Total Scheduled Duties</span>
                    <span class="att-chip-val" id="att-month-total-duties"><?= $monthTotalDuties ?></span>
                </div>
            </div>
            <div class="att-metric-chip">
                <div class="att-chip-icon" style="background:rgba(245,158,11,0.15);color:#f59e0b;">🎯</div>
                <div class="att-chip-info">
                    <span class="att-chip-lbl">Contract Delivery Rate</span>
                    <span class="att-chip-val" style="color:#10b981;"><?= $fulfillmentPct ?>%</span>
                </div>
            </div>
        </div>

        <!-- 4. MODERN INTERACTIVE LEGEND -->
        <div class="att-legend-pills">
            <div class="att-legend-group">
                <span style="color:var(--att-text-muted);font-weight:800;text-transform:uppercase;font-size:11px;">Shift Codes:</span>
                <span class="att-pill-badge" style="background:rgba(16,185,129,0.15);color:#059669;">● D = Day Shift</span>
                <span class="att-pill-badge" style="background:rgba(59,130,246,0.15);color:#3b82f6;">● N = Night Shift</span>
                <span class="att-pill-badge" style="background:rgba(239,68,68,0.15);color:#ef4444;">● OFF = Day Off</span>
                <span class="att-pill-badge" style="background:rgba(245,158,11,0.15);color:#f59e0b;">● S = Sick Leave</span>
                <span class="att-pill-badge" style="background:rgba(139,92,246,0.15);color:#8b5cf6;">● B = Basic</span>
            </div>
            <div class="att-legend-group">
                <span style="color:var(--att-text-muted);font-weight:800;text-transform:uppercase;font-size:11px;">Deployment Status:</span>
                <span class="att-pill-badge att-deploy-ok">🟢 OK (Met)</span>
                <span class="att-pill-badge att-deploy-less">🔴 Shortage (Less)</span>
                <span class="att-pill-badge att-deploy-over">🟡 Over Deployed</span>
            </div>
        </div>

        <!-- 5. EXECUTIVE SIGN-OFF CARDS -->
        <div class="att-signatures-grid">
            <div class="att-sig-card">
                <div class="att-sig-role">Prepared By (Site Supervisor)</div>
                <div class="att-sig-line-box">
                    <span>Signature &amp; Stamp</span>
                    <span>Date: ____/____/2026</span>
                </div>
            </div>
            <div class="att-sig-card">
                <div class="att-sig-role">Checked By (Operations Manager)</div>
                <div class="att-sig-line-box">
                    <span>Signature &amp; Stamp</span>
                    <span>Date: ____/____/2026</span>
                </div>
            </div>
            <div class="att-sig-card">
                <div class="att-sig-role">Approved By (Security Director)</div>
                <div class="att-sig-line-box">
                    <span>Signature &amp; Stamp</span>
                    <span>Date: ____/____/2026</span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<!-- Modals -->
<!-- Modal: Searchable Staff Directory & Add to Sheet -->
<div class="att-modal-overlay no-print" id="att-modal-staff-directory">
    <div class="att-modal">
        <div class="att-modal-header">
            <span id="att-dir-modal-title">👥 Saved Staff Directory</span>
            <button type="button" class="att-modal-close" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
        </div>
        <div class="att-modal-body">
            <div style="display:flex;gap:8px;margin-bottom:12px;">
                <input type="text" id="att-search-staff-input" class="att-input" placeholder="Search by ID number or by name..." style="flex:1;">
                <button type="button" class="att-btn att-btn-primary" id="att-btn-show-new-staff-form">+ New Staff</button>
            </div>

            <!-- Inline New Staff Form inside modal -->
            <div id="att-new-staff-inline-form" style="display:none;background:var(--att-bg);padding:14px;border:1px solid var(--att-card-border);border-radius:8px;margin-bottom:14px;">
                <h4 style="margin:0 0 10px 0;font-size:12.5px;color:var(--att-text);font-weight:800;">Register New Guard to Directory</h4>
                <div style="display:flex;gap:10px;">
                    <input type="text" id="att-inline-emp-no" class="att-input" placeholder="Staff ID (e.g. SG1003) *" style="width:140px;">
                    <input type="text" id="att-inline-name" class="att-input" placeholder="Full Guard Name *" style="flex:1;">
                    <button type="button" class="att-btn att-btn-success" id="att-btn-save-inline-staff">Save &amp; Add</button>
                </div>
            </div>

            <div class="att-staff-list-container" id="att-staff-directory-list">
                <div style="padding:20px;text-align:center;color:var(--att-text-muted);">Loading saved staff list...</div>
            </div>
        </div>
        <div class="att-modal-footer">
            <button type="button" class="att-btn att-btn-secondary att-modal-close">Close</button>
        </div>
    </div>
</div>

<!-- Modal: Replace Staff in Row -->
<div class="att-modal-overlay no-print" id="att-modal-replace-staff">
    <div class="att-modal">
        <div class="att-modal-header">
            <span>🔁 Replace Guard on Roster</span>
            <button type="button" class="att-modal-close" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
        </div>
        <div class="att-modal-body">
            <p style="margin-top:0;font-size:13px;color:var(--att-text-muted);">Select another registered guard to take over this position for <strong id="att-replace-target-name" style="color:var(--att-text);"></strong>:</p>
            <input type="hidden" id="att-replace-row-id" value="">
            <input type="text" id="att-replace-search-input" class="att-input" placeholder="Search replacement guard by ID or Name..." style="margin-bottom:10px;width:100%;">

            <div style="margin-bottom:12px;background:var(--att-bg);padding:8px 12px;border-radius:6px;border:1px solid var(--att-card-border);">
                <label style="font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" id="att-replace-clear-codes"> Clear row's day codes (Unchecked keeps existing shift assignments)
                </label>
            </div>

            <div class="att-staff-list-container" id="att-replace-directory-list" style="max-height:220px;">
                <div style="padding:16px;text-align:center;color:var(--att-text-muted);">Type to search saved staff...</div>
            </div>
        </div>
        <div class="att-modal-footer">
            <button type="button" class="att-btn att-btn-secondary att-modal-close">Cancel</button>
        </div>
    </div>
</div>

<!-- Modal: Edit Staff Name / ID -->
<div class="att-modal-overlay no-print" id="att-modal-edit-staff">
    <div class="att-modal" style="width:420px;">
        <div class="att-modal-header">
            <span>✏️ Edit Staff Details</span>
            <button type="button" class="att-modal-close" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
        </div>
        <div class="att-modal-body">
            <input type="hidden" id="att-edit-row-id" value="">
            <div class="att-form-group">
                <label for="att-edit-emp-no">Staff ID / Emp No:</label>
                <input type="text" id="att-edit-emp-no" class="att-input" required>
            </div>
            <div class="att-form-group">
                <label for="att-edit-name">Guard Full Name:</label>
                <input type="text" id="att-edit-name" class="att-input" required>
            </div>
        </div>
        <div class="att-modal-footer">
            <button type="button" class="att-btn att-btn-secondary att-modal-close">Cancel</button>
            <button type="button" class="att-btn att-btn-primary" id="att-btn-submit-edit-staff">Save Changes</button>
        </div>
    </div>
</div>

<!-- Modal: New Site Master -->
<div class="att-modal-overlay no-print" id="att-modal-new-site">
    <div class="att-modal">
        <div class="att-modal-header">
            <span>🏢 Register New Attendance Site</span>
            <button type="button" class="att-modal-close" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
        </div>
        <form method="POST" action="index.php?action=save_site">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(att_get_csrf_token()) ?>">
            <div class="att-modal-body">
                <div class="att-form-group">
                    <label for="att-new-site-name">Site Name: *</label>
                    <input type="text" id="att-new-site-name" name="site_name" class="att-input" required placeholder="e.g. Danat Al Rolla Building">
                </div>
                <div class="att-form-group">
                    <label for="att-new-site-code">Site Code / Acronym: (Optional)</label>
                    <input type="text" id="att-new-site-code" name="site_code" class="att-input" placeholder="e.g. DAR-01">
                </div>
                <div style="display:flex;gap:12px;">
                    <div class="att-form-group" style="flex:1;">
                        <label for="att-new-site-req-day">Required Day Deployment (D): *</label>
                        <input type="number" id="att-new-site-req-day" name="req_day" class="att-input" min="0" value="1" required>
                    </div>
                    <div class="att-form-group" style="flex:1;">
                        <label for="att-new-site-req-night">Required Night Deployment (N): *</label>
                        <input type="number" id="att-new-site-req-night" name="req_night" class="att-input" min="0" value="1" required>
                    </div>
                </div>
                <div style="display:flex;gap:12px;">
                    <div class="att-form-group" style="flex:1;">
                        <label for="att-new-site-contract-start">Contract Start Date: (Optional)</label>
                        <input type="date" id="att-new-site-contract-start" name="contract_start" class="att-input">
                    </div>
                    <div class="att-form-group" style="flex:1;">
                        <label for="att-new-site-contract-end">Contract End Date: (Optional)</label>
                        <input type="date" id="att-new-site-contract-end" name="contract_end" class="att-input">
                    </div>
                </div>
            </div>
            <div class="att-modal-footer">
                <button type="button" class="att-btn att-btn-secondary att-modal-close">Cancel</button>
                <button type="submit" class="att-btn att-btn-primary">Save Site</button>
            </div>
        </form>
    </div>
</div>

<?php if ($sheet): ?>
<!-- Modal: Delete Selected Site -->
<div class="att-modal-overlay no-print" id="att-modal-delete-site">
    <div class="att-modal">
        <div class="att-modal-header" style="background:#dc2626;color:#ffffff;">
            <span>🗑️ Delete Site</span>
            <button type="button" class="att-modal-close" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
        </div>
        <form method="POST" action="index.php?action=delete_site">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(att_get_csrf_token()) ?>">
            <input type="hidden" name="site_id" value="<?= $sheet['site_id'] ?>">
            <div class="att-modal-body">
                <p style="font-size:14px;color:var(--att-text);margin:0 0 12px 0;">
                    Are you sure you want to delete site <strong><?= htmlspecialchars($sheet['site_name']) ?></strong>?
                </p>
                <div style="background:var(--att-danger-bg);border:1px solid rgba(239,68,68,0.3);padding:14px;border-radius:6px;color:var(--att-danger);font-size:12.5px;line-height:1.4;">
                    ⚠️ This site will be removed from the active site roster. Historical records are preserved in the MySQL database.
                </div>
            </div>
            <div class="att-modal-footer">
                <button type="button" class="att-btn att-btn-secondary att-modal-close">Cancel</button>
                <button type="submit" class="att-btn att-btn-danger">Yes, Delete Site</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Additional Deployment Options -->
<div class="att-modal-overlay no-print" id="att-modal-additional-deployment">
    <div class="att-modal" style="width:640px;">
        <div class="att-modal-header">
            <span>⚡ Additional Deployment - <?= htmlspecialchars($sheet['site_name']) ?></span>
            <button type="button" class="att-modal-close" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
        </div>
        <div class="att-modal-body">
            <form method="POST" action="index.php?action=save_additional_deployment" style="background:var(--att-bg);padding:16px;border:1px solid var(--att-card-border);border-radius:8px;margin-bottom:16px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(att_get_csrf_token()) ?>">
                <input type="hidden" name="sheet_id" value="<?= $sheet['id'] ?>">
                <input type="hidden" name="site_id" value="<?= $sheet['site_id'] ?>">
                <input type="hidden" name="month" value="<?= $curMonth ?>">
                <input type="hidden" name="year" value="<?= $curYear ?>">

                <h4 style="margin:0 0 10px 0;font-size:13px;color:var(--att-primary);font-weight:800;">+ Configure Additional Shift Deployment</h4>
                <div style="display:flex;gap:10px;margin-bottom:10px;">
                    <div style="flex:1;">
                        <label style="display:block;font-size:11px;font-weight:bold;margin-bottom:4px;">Apply To Day:</label>
                        <select name="day_number" class="att-select" style="width:100%;">
                            <option value="all">-- All Days in <?= $monthName ?> --</option>
                            <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                                <option value="<?= $d ?>">Day <?= $d ?> (<?= date('D, M j', mktime(0, 0, 0, $curMonth, $d, $curYear)) ?>)</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div style="width:110px;">
                        <label style="display:block;font-size:11px;font-weight:bold;margin-bottom:4px;">Add Day (+D):</label>
                        <input type="number" name="add_day" class="att-input" value="0" min="0" style="width:100%;">
                    </div>
                    <div style="width:110px;">
                        <label style="display:block;font-size:11px;font-weight:bold;margin-bottom:4px;">Add Night (+N):</label>
                        <input type="number" name="add_night" class="att-input" value="0" min="0" style="width:100%;">
                    </div>
                </div>
                <div style="display:flex;gap:10px;align-items:flex-end;">
                    <div style="flex:1;">
                        <label style="display:block;font-size:11px;font-weight:bold;margin-bottom:4px;">Reason / Notes:</label>
                        <input type="text" name="notes" class="att-input" placeholder="e.g. Event Security, VIP Visit, Client Request" style="width:100%;">
                    </div>
                    <button type="submit" class="att-btn att-btn-primary" style="white-space:nowrap;">Save Additional</button>
                </div>
            </form>

            <h4 style="margin:0 0 8px 0;font-size:12px;color:var(--att-text);font-weight:800;">Active Additional Deployments for <?= $monthName ?> <?= $curYear ?>:</h4>
            <?php if (empty($additionalDeployments)): ?>
                <div style="padding:16px;background:var(--att-bg);border-radius:6px;text-align:center;color:var(--att-text-muted);font-size:12px;">
                    No additional deployments active for this period. Standard contract requirements apply.
                </div>
            <?php else: ?>
                <div style="max-height:220px;overflow-y:auto;border:1px solid var(--att-card-border);border-radius:6px;">
                    <table style="width:100%;border-collapse:collapse;font-size:12px;text-align:center;">
                        <thead>
                            <tr style="background:var(--att-bg);font-weight:800;">
                                <th style="padding:8px;border-bottom:1px solid var(--att-card-border);">Day</th>
                                <th style="padding:8px;border-bottom:1px solid var(--att-card-border);">+Day</th>
                                <th style="padding:8px;border-bottom:1px solid var(--att-card-border);">+Night</th>
                                <th style="padding:8px;border-bottom:1px solid var(--att-card-border);">Reason</th>
                                <th style="padding:8px;border-bottom:1px solid var(--att-card-border);">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($additionalDeployments as $ad): ?>
                                <tr style="border-bottom:1px solid var(--att-card-border);">
                                    <td style="padding:8px;font-weight:700;">Day <?= $ad['day_number'] ?></td>
                                    <td style="padding:8px;color:#10b981;font-weight:800;">+<?= $ad['add_day'] ?></td>
                                    <td style="padding:8px;color:#3b82f6;font-weight:800;">+<?= $ad['add_night'] ?></td>
                                    <td style="padding:8px;color:var(--att-text-muted);"><?= htmlspecialchars($ad['notes'] ?: '-') ?></td>
                                    <td style="padding:8px;">
                                        <form method="POST" action="index.php?action=delete_additional_deployment" style="display:inline;" onsubmit="return confirm('Remove additional deployment for Day <?= $ad['day_number'] ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(att_get_csrf_token()) ?>">
                                            <input type="hidden" name="sheet_id" value="<?= $sheet['id'] ?>">
                                            <input type="hidden" name="site_id" value="<?= $sheet['site_id'] ?>">
                                            <input type="hidden" name="day_number" value="<?= $ad['day_number'] ?>">
                                            <input type="hidden" name="month" value="<?= $curMonth ?>">
                                            <input type="hidden" name="year" value="<?= $curYear ?>">
                                            <button type="submit" class="att-row-btn-icon" style="color:#ef4444;" title="Remove">🗑️</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <div class="att-modal-footer">
            <button type="button" class="att-btn att-btn-secondary att-modal-close">Close</button>
        </div>
    </div>
</div>

<!-- Modal: Site Requirements & Contract Dates -->
<div class="att-modal-overlay no-print" id="att-modal-site-reqs">
    <div class="att-modal">
        <div class="att-modal-header">
            <span>⚙️ Site Contract &amp; Shift Requirements</span>
            <button type="button" class="att-modal-close" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
        </div>
        <form method="POST" action="index.php?action=update_site_requirements">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(att_get_csrf_token()) ?>">
            <input type="hidden" name="site_id" value="<?= $sheet['site_id'] ?>">
            <input type="hidden" name="month" value="<?= $curMonth ?>">
            <input type="hidden" name="year" value="<?= $curYear ?>">
            <div class="att-modal-body">
                <div class="att-form-group">
                    <label>Site Name:</label>
                    <input type="text" class="att-input" value="<?= htmlspecialchars($sheet['site_name']) ?>" disabled style="opacity:0.8;">
                </div>
                <div style="display:flex;gap:12px;">
                    <div class="att-form-group" style="flex:1;">
                        <label for="att-edit-site-req-day">Required Day Deployment (D): *</label>
                        <input type="number" id="att-edit-site-req-day" name="req_day" class="att-input" min="0" value="<?= $siteReqDay ?>" required>
                    </div>
                    <div class="att-form-group" style="flex:1;">
                        <label for="att-edit-site-req-night">Required Night Deployment (N): *</label>
                        <input type="number" id="att-edit-site-req-night" name="req_night" class="att-input" min="0" value="<?= $siteReqNight ?>" required>
                    </div>
                </div>
                <div style="display:flex;gap:12px;">
                    <div class="att-form-group" style="flex:1;">
                        <label for="att-edit-site-contract-start">Contract Start Date:</label>
                        <input type="date" id="att-edit-site-contract-start" name="contract_start" class="att-input" value="<?= htmlspecialchars($contractStart) ?>">
                    </div>
                    <div class="att-form-group" style="flex:1;">
                        <label for="att-edit-site-contract-end">Contract End Date:</label>
                        <input type="date" id="att-edit-site-contract-end" name="contract_end" class="att-input" value="<?= htmlspecialchars($contractEnd) ?>">
                    </div>
                </div>
            </div>
            <div class="att-modal-footer">
                <button type="button" class="att-btn att-btn-secondary att-modal-close">Cancel</button>
                <button type="submit" class="att-btn att-btn-primary">Update Requirements</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// Live update of Month Total summary line on cell change
function updateMonthTotalSummary() {
    let totD = 0;
    let totN = 0;
    document.querySelectorAll('.att-cell-input').forEach(inp => {
        const v = inp.value.trim().toUpperCase();
        if (v === 'D') totD++;
        else if (v === 'N') totN++;
    });
    const elD = document.getElementById('att-month-total-d');
    const elN = document.getElementById('att-month-total-n');
    const elDuties = document.getElementById('att-month-total-duties');
    if (elD) elD.textContent = totD;
    if (elN) elN.textContent = totN;
    if (elDuties) elDuties.textContent = totD + totN;
}

document.addEventListener('input', (e) => {
    if (e.target.classList.contains('att-cell-input')) {
        setTimeout(updateMonthTotalSummary, 100);
    }
});
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
