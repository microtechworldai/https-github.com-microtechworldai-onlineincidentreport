<?php
// Attendance & Payroll Module - Attendance Roster Main View
// Features:
// 1. Staff List button next to + Add Staff with searchable directory modal (by Emp No or Name).
// 2. Add individual staff from directory to current sheet.
// 3. "+ New staff" option saves to directory and adds to sheet.
// 4. "Add all on this sheet to list" button to copy existing sheet staff into saved directory in one click.
// 5. In-row actions: Replace (search for another person to take over row, with checkbox to clear day codes or keep them), Edit, and Remove (removes from this site and month only).
// 6. Borders: Black border on every cell, thicker outer border, lines stay locked when scrolling sideways. Light grey in dark mode.
// 7. Top header row with Emp No, Name, Site Name, Day numbers is exactly 45px tall.
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
$contractStart = $sheet['contract_start'] ?? '';
$contractEnd = $sheet['contract_end'] ?? '';

$monthName = date('F', mktime(0, 0, 0, $curMonth, 10));
$prevMonth = $curMonth === 1 ? 12 : $curMonth - 1;
$prevYear = $curMonth === 1 ? $curYear - 1 : $curYear;

$monthTotalDayShifts = array_sum($dayShiftCounts);
$monthTotalNightShifts = array_sum($nightShiftCounts);
$monthTotalDuties = $monthTotalDayShifts + $monthTotalNightShifts;
?>

<style>
/* Dark Teal Title Banner: 55px height and 25px font size */
.att-th-banner-darkteal {
    height: 55px !important;
    font-size: 25px !important;
    line-height: 55px !important;
    background-color: #005f73 !important;
    color: #ffffff !important;
    font-weight: bold !important;
    text-align: center !important;
    vertical-align: middle !important;
    border: 1px solid var(--att-border) !important;
    padding: 0 8px !important;
    letter-spacing: 0.5px;
}

/* Auto-fit to contents, centered, and bold columns */
.att-roster-outer-table {
    border: 2px solid var(--att-border) !important;
    border-collapse: collapse;
    width: 100%;
    font-family: var(--att-font-condensed);
    font-size: 12px;
}

.att-roster-outer-table th, .att-roster-outer-table td {
    border: 1px solid var(--att-border) !important;
    padding: 2px 4px;
    height: 28px;
    text-align: center !important;
    vertical-align: middle !important;
    font-weight: bold !important;
    white-space: nowrap !important;
}

.att-col-emp, .att-col-name, .att-col-site, .att-col-action {
    width: auto !important;
    white-space: nowrap !important;
    text-align: center !important;
    font-weight: bold !important;
    padding: 2px 8px !important;
}

.att-col-day {
    width: 38px !important;
    min-width: 38px !important;
    text-align: center !important;
    font-weight: bold !important;
}

.att-cell-input {
    text-align: center !important;
    font-weight: bold !important;
    font-size: 12px !important;
}

.att-row-btn-icon {
    background: transparent;
    border: 1px solid transparent;
    cursor: pointer;
    font-size: 11px !important;
    line-height: 1 !important;
    padding: 1px 2px !important;
    margin: 0 1px !important;
    border-radius: 3px;
    transition: all 0.15s ease;
    vertical-align: middle;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px !important;
    height: 20px !important;
}
.att-row-btn-icon:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    transform: scale(1.15);
}
.att-row-btn-icon.att-btn-remove-staff:hover {
    background: #fee2e2;
    border-color: #fca5a5;
}
.att-row-btn-icon.att-btn-replace-staff:hover {
    background: #fef3c7;
    border-color: #fcd34d;
}

/* Deployment Status Colors for Summary Rows */
.att-col-total-d, .att-col-total-n, .att-col-total-grand {
    text-align: center !important;
    vertical-align: middle !important;
    font-weight: bold !important;
    font-size: 12px !important;
    transition: background-color 0.15s ease, color 0.15s ease;
}

.att-deploy-ok {
    background-color: #16a34a !important;
    color: #ffffff !important;
}

.att-deploy-less {
    background-color: #dc2626 !important;
    color: #ffffff !important;
}

.att-deploy-over {
    background-color: #facc15 !important;
    color: #000000 !important;
}

.att-deploy-neutral {
    background-color: #f1f5f9 !important;
    color: #475569 !important;
}

.att-month-total-line {
    margin-top: 10px;
    padding: 8px 12px;
    background: #e2e8f0;
    border: 1px solid var(--att-border);
    font-weight: bold;
    font-size: 11px;
    display: flex;
    justify-content: flex-start;
    gap: 28px;
    color: #000000;
}

.att-legend-box {
    margin-top: 10px;
    padding: 6px 4px;
    font-size: 11px;
    font-weight: bold;
    color: #111;
}

.att-signatures-block {
    display: flex;
    justify-content: space-between;
    margin-top: 24px;
    padding: 0 10px 24px 10px;
    font-size: 11px;
    font-weight: bold;
    color: #222;
}

.att-sig-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.att-sig-line {
    display: inline-block;
    width: 170px;
    border-bottom: 1px solid var(--att-border);
}
</style>

<!-- Action & Filter Bar -->
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

        <span id="att-status-indicator" class="att-status-indicator att-status-saved">✓ Stored in Database (MySQL)</span>
    </form>

    <div class="att-actions">
        <button type="button" class="att-btn att-btn-secondary" id="att-btn-add-site">+ New Site</button>
        <?php if ($sheet): ?>
            <button type="button" class="att-btn att-btn-danger" id="att-btn-delete-site" style="background:#dc2626;color:#ffffff;border-color:#dc2626;" title="Delete this site from attendance">🗑️ Delete Site</button>
            <button type="button" class="att-btn att-btn-secondary" id="att-btn-add-deployment" style="background:#0284c7;color:#ffffff;border-color:#0284c7;" title="Manage additional deployment for this month or specific dates">➕ Additional Deployment</button>
            <button type="button" class="att-btn att-btn-secondary" id="att-btn-site-reqs" title="Edit site requirements & contract dates">⚙️ Requirements</button>
            <!-- Staff list button next to + Add staff -->
            <button type="button" class="att-btn att-btn-secondary" id="att-btn-staff-list">👥 Staff List</button>
            <button type="button" class="att-btn att-btn-primary" id="att-btn-add-staff">+ Add Staff</button>
            <button type="button" class="att-btn att-btn-secondary" id="att-btn-copy-prev" 
                    data-site-id="<?= $sheet['site_id'] ?>" data-year="<?= $curYear ?>" data-month="<?= $curMonth ?>">
                ↻ Copy staff from previous month
            </button>
            <button type="button" class="att-btn <?= $isLocked ? 'att-btn-warning' : 'att-btn-secondary' ?>" id="att-btn-lock" 
                    data-sheet-id="<?= $sheet['id'] ?>" data-lock="<?= $isLocked ? '0' : '1' ?>">
                <?= $isLocked ? '🔒 Unlock Sheet' : '🔓 Lock Sheet' ?>
            </button>
            <a href="index.php?action=export_excel&export_type=sheet&sheet_id=<?= $sheet['id'] ?>" class="att-btn att-btn-success">
                ⬇ Download Excel (.xls)
            </a>
            <a href="index.php?action=export_pdf&sheet_id=<?= $sheet['id'] ?>" class="att-btn att-btn-secondary" target="_blank">
                🖨 Print / PDF (A4 Landscape)
            </a>
            <a href="index.php?action=backup_download" class="att-btn att-btn-secondary" style="background:#0f172a;color:#ffffff;border-color:#0f172a;" title="Download complete SQL database backup for long run safety and reuse">
                🛡️ Backup Database (.sql)
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($sheet): ?>
<!-- Site Deployment & Contract Info Strip -->
<div class="att-container no-print" style="margin-top:6px;margin-bottom:6px;">
    <div style="background:#f1f5f9;border:1px solid #cbd5e1;padding:6px 12px;border-radius:4px;display:flex;align-items:center;justify-content:space-between;font-size:12px;font-family:var(--att-font-condensed);font-weight:bold;">
        <div style="display:flex;align-items:center;gap:16px;">
            <span>🏢 Site: <span style="color:#005f73;"><?= htmlspecialchars($sheet['site_name']) ?></span></span>
            <span>☀️ Required Day Shift: <span style="color:#0f766e;"><?= $siteReqDay ?></span></span>
            <span>🌙 Required Night Shift: <span style="color:#0f766e;"><?= $siteReqNight ?></span></span>
            <span>📅 Contract: <span style="color:#334155;"><?= !empty($contractStart) ? htmlspecialchars($contractStart) : 'Open' ?></span> to <span style="color:#334155;"><?= !empty($contractEnd) ? htmlspecialchars($contractEnd) : 'Ongoing' ?></span></span>
            <?php if (!empty($additionalDeployments)): ?>
                <span style="color:#0284c7;background:#e0f2fe;padding:2px 8px;border-radius:3px;">⚡ <?= count($additionalDeployments) ?> day(s) Additional Deployment Active</span>
            <?php endif; ?>
        </div>
        <div style="display:flex;align-items:center;gap:10px;font-size:11px;">
            <span>Status:</span>
            <span style="background:#16a34a;color:#fff;padding:2px 6px;border-radius:3px;">🟢 OK</span>
            <span style="background:#dc2626;color:#fff;padding:2px 6px;border-radius:3px;">🔴 Less</span>
            <span style="background:#facc15;color:#000;padding:2px 6px;border-radius:3px;">🟡 Over</span>
        </div>
    </div>
</div>
<?php endif; ?>

<main class="att-container">
    <?php if (!$sheet): ?>
        <div style="background:#fff;padding:40px;text-align:center;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin-top:0;color:#005f73;">Welcome to Attendance Roster</h3>
            <p style="color:#64748b;max-width:500px;margin:0 auto 20px;">No sites have been added to the dedicated attendance directory yet. Get started by adding your first site.</p>
            <button type="button" class="att-btn att-btn-primary" onclick="document.getElementById('att-modal-new-site').classList.add('att-open')">+ Add Site</button>
        </div>
    <?php else: ?>
        <div class="att-grid-wrapper">
            <table class="att-roster-outer-table" id="att-roster-table" data-sheet-id="<?= $sheet['id'] ?>" data-locked="<?= $isLocked ? '1' : '0' ?>">
                <thead>
                    <!-- Dark Teal Title Bar: height 55px, font size 25px -->
                    <tr style="height: 55px;">
                        <th colspan="<?= $daysInMonth + 4 ?>" class="att-th-banner-darkteal">
                            Ultimate Security Services Actual Attendance for the month of <?= $monthName ?> - <?= $curYear ?>
                        </th>
                    </tr>

                    <!-- Blank White Row In Between -->
                    <tr class="att-tr-blank-spacer" style="height: 12px; background-color: #ffffff;">
                        <th colspan="<?= $daysInMonth + 4 ?>" style="height: 12px; background-color: #ffffff; border: 1px solid #000000; padding: 0;"></th>
                    </tr>

                    <!-- Top Header Row 1: Exactly 28px tall, font size 18px bold -->
                    <tr style="height: 28px;">
                        <th rowspan="2" class="att-th-top-row att-col-emp">Emp No</th>
                        <th rowspan="2" class="att-th-top-row att-col-name">Name</th>
                        <th rowspan="2" class="att-th-top-row att-col-site">Site Name</th>

                        <!-- Days of Month (1 to 28/29/30/31) -->
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                            $wName = strtoupper(substr(date('D', mktime(0, 0, 0, $curMonth, $d, $curYear)), 0, 3));
                            $isWk = in_array($wName, ['FRI', 'SUN']);
                        ?>
                            <th class="att-th-top-row att-col-day <?= $isWk ? 'att-th-teal-weekend' : '' ?>"><?= $d ?></th>
                        <?php endfor; ?>

                        <!-- Action Column Header after 31st date -->
                        <th rowspan="2" class="att-th-top-row att-col-action">Action</th>
                    </tr>

                    <!-- Weekday Names Row 2 (SAT, SUN, MON...): Exactly 28px tall, font size 14px bold -->
                    <tr style="height: 28px;">
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
                            <td colspan="<?= $daysInMonth + 4 ?>" style="padding:24px;color:#64748b;background:#f8fafc;text-align:center;">
                                No staff assigned to this site and month yet. Click "<strong>+ Add Staff</strong>" or "<strong>Copy staff from previous month</strong>" above.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        foreach ($rows as $r): 
                            $displayName = $r['emp_name_snapshot'] ?? $r['staff_name'] ?? $r['emp_name'] ?? '';
                            $cellVals = $r['days'] ?? $r['cells'] ?? [];
                        ?>
                            <tr class="att-row-staff" data-row-id="<?= $r['id'] ?>" data-emp-no="<?= htmlspecialchars($r['emp_no'] ?? '') ?>">
                                <td class="att-col-emp" style="font-weight:700;text-align:center;"><?= htmlspecialchars($r['emp_no'] ?? '') ?></td>
                                <td class="att-col-name" style="font-weight:700;text-align:center;"><?= htmlspecialchars($displayName) ?></td>
                                <td class="att-col-site" style="font-weight:700;text-align:center;"><?= htmlspecialchars($sheet['site_name']) ?></td>

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

                                <!-- Action Buttons Cell: Delete and Replace icons ONLY -->
                                <td class="att-col-action no-print" style="white-space:nowrap;text-align:center;padding:2px 4px;">
                                    <?php if (!$isLocked): ?>
                                        <button type="button" class="att-row-btn-icon att-btn-replace-staff" 
                                                data-row-id="<?= $r['id'] ?>" 
                                                data-emp-no="<?= htmlspecialchars($r['emp_no'] ?? '', ENT_QUOTES) ?>" 
                                                data-name="<?= htmlspecialchars($displayName, ENT_QUOTES) ?>" 
                                                title="Replace" aria-label="Replace">🔁</button>
                                        <button type="button" class="att-row-btn-icon att-btn-remove-staff" 
                                                data-row-id="<?= $r['id'] ?>" 
                                                data-staff-name="<?= htmlspecialchars($displayName, ENT_QUOTES) ?>" 
                                                title="Delete" aria-label="Delete">🗑️</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php 
                        endforeach; 
                        ?>
                    <?php endif; ?>

                    <!-- Bottom Counts Row 1: Day shift (D) -->
                        <tr class="att-tr-day-total">
                            <td colspan="3" class="att-summary-label">DAY SHIFT (D):</td>
                            <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                                $stD = $dayDeployStatuses[$d] ?? 'neutral';
                                $clsD = 'att-deploy-' . $stD;
                                $reqD = $dayRequirements[$d] ?? 0;
                                $cntD = $dayShiftCounts[$d] ?: '0';
                                $tipD = "Day {$d} Day Shift: Deployed {$cntD} / Required {$reqD} (" . ($stD === 'ok' ? 'OK' : ($stD === 'less' ? 'Shortage' : 'Over Deployed')) . ")";
                            ?>
                                <td class="att-col-total-d <?= $clsD ?>" data-day="<?= $d ?>" data-req-d="<?= $reqD ?>" title="<?= htmlspecialchars($tipD) ?>"><?= $cntD ?></td>
                            <?php endfor; ?>
                            <td class="att-col-action"></td>
                        </tr>

                        <!-- Bottom Counts Row 2: Night shift (N) -->
                        <tr class="att-tr-night-total">
                            <td colspan="3" class="att-summary-label">NIGHT SHIFT (N):</td>
                            <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                                $stN = $nightDeployStatuses[$d] ?? 'neutral';
                                $clsN = 'att-deploy-' . $stN;
                                $reqN = $nightRequirements[$d] ?? 0;
                                $cntN = $nightShiftCounts[$d] ?: '0';
                                $tipN = "Day {$d} Night Shift: Deployed {$cntN} / Required {$reqN} (" . ($stN === 'ok' ? 'OK' : ($stN === 'less' ? 'Shortage' : 'Over Deployed')) . ")";
                            ?>
                                <td class="att-col-total-n <?= $clsN ?>" data-day="<?= $d ?>" data-req-n="<?= $reqN ?>" title="<?= htmlspecialchars($tipN) ?>"><?= $cntN ?></td>
                            <?php endfor; ?>
                            <td class="att-col-action"></td>
                        </tr>

                        <!-- Bottom Counts Row 3: Total on duty (D + N) -->
                        <tr class="att-tr-grand-total">
                            <td colspan="3" class="att-summary-label">TOTAL ON DUTY (D + N):</td>
                            <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                                $stTot = $totalDeployStatuses[$d] ?? 'neutral';
                                $clsTot = 'att-deploy-' . $stTot;
                                $reqTot = $totalRequirements[$d] ?? 0;
                                $cntTot = $totalDutyCounts[$d] ?: '0';
                                $tipTot = "Day {$d} Total Duty: Deployed {$cntTot} / Required {$reqTot} (" . ($stTot === 'ok' ? 'OK' : ($stTot === 'less' ? 'Shortage' : 'Over Deployed')) . ")";
                            ?>
                                <td class="att-col-total-grand <?= $clsTot ?>" data-day="<?= $d ?>" data-req-total="<?= $reqTot ?>" title="<?= htmlspecialchars($tipTot) ?>"><?= $cntTot ?></td>
                            <?php endfor; ?>
                            <td class="att-col-action"></td>
                        </tr>
                </tbody>
            </table>
        </div>

        <!-- Month Total Summary Line -->
        <div class="att-month-total-line">
                <span>Month Total Day Shifts: <strong id="att-month-total-d"><?= $monthTotalDayShifts ?></strong></span>
                <span>Month Total Night Shifts: <strong id="att-month-total-n"><?= $monthTotalNightShifts ?></strong></span>
                <span>Month Total Duties: <strong id="att-month-total-duties"><?= $monthTotalDuties ?></strong></span>
            </div>

            <!-- Legend Box -->
            <div class="att-legend-box">
                <strong>Shift Codes:</strong> &nbsp;&nbsp; 
                <span style="color:#005f73;">D = Day Shift</span> &nbsp;&nbsp;|&nbsp;&nbsp; 
                <span style="color:#1e3a8a;">N = Night Shift</span> &nbsp;&nbsp;|&nbsp;&nbsp; 
                <span style="color:#c00000;">OFF = Day Off</span> &nbsp;&nbsp;|&nbsp;&nbsp; 
                <span style="color:#d97706;">S = Sick Leave</span> &nbsp;&nbsp;|&nbsp;&nbsp; 
                <span style="color:#4b5563;">B = Basic</span>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <strong>Deployment Status:</strong> &nbsp;&nbsp;
                <span style="display:inline-block;padding:2px 7px;background:#16a34a;color:#ffffff;border-radius:3px;font-size:11px;font-weight:bold;">🟢 Green: OK (Met)</span> &nbsp;
                <span style="display:inline-block;padding:2px 7px;background:#dc2626;color:#ffffff;border-radius:3px;font-size:11px;font-weight:bold;">🔴 Red: Shortage (Less)</span> &nbsp;
                <span style="display:inline-block;padding:2px 7px;background:#facc15;color:#000000;border-radius:3px;font-size:11px;font-weight:bold;">🟡 Yellow: Over Deployed</span>
            </div>

            <!-- Three Signature Lines -->
            <div class="att-signatures-block">
                <div class="att-sig-item">
                    <span>Prepared by:</span>
                    <span class="att-sig-line"></span>
                </div>
                <div class="att-sig-item">
                    <span>Checked by:</span>
                    <span class="att-sig-line"></span>
                </div>
                <div class="att-sig-item">
                    <span>Approved by:</span>
                    <span class="att-sig-line"></span>
                </div>
            </div>
    <?php endif; ?>
</main>

<!-- Modal: Searchable Staff Directory & Add to Sheet -->
<div class="att-modal-overlay no-print" id="att-modal-staff-directory">
    <div class="att-modal">
        <div class="att-modal-header">
            <span id="att-dir-modal-title">Saved Staff Directory</span>
            <button type="button" class="att-modal-close" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
        </div>
        <div class="att-modal-body">
            <div style="display:flex;gap:8px;margin-bottom:12px;">
                <input type="text" id="att-search-staff-input" class="att-input" placeholder="Search by ID number or by name..." style="flex:1;">
                <button type="button" class="att-btn att-btn-primary" id="att-btn-show-new-staff-form">+ New Staff</button>
            </div>

            <!-- Inline New Staff Form inside modal -->
            <div id="att-new-staff-inline-form" style="display:none;background:#f8fafc;padding:12px;border:1px solid #cbd5e1;border-radius:4px;margin-bottom:12px;">
                <h4 style="margin:0 0 8px 0;font-size:12px;color:#005f73;">Save New Staff Member</h4>
                <div style="display:flex;gap:8px;">
                    <input type="text" id="att-inline-emp-no" class="att-input" placeholder="ID / Emp No *" style="width:120px;">
                    <input type="text" id="att-inline-name" class="att-input" placeholder="Full Name *" style="flex:1;">
                    <button type="button" class="att-btn att-btn-success" id="att-btn-save-inline-staff">Save &amp; Add</button>
                </div>
            </div>

            <div class="att-staff-list-container" id="att-staff-directory-list">
                <!-- Loaded dynamically via JavaScript -->
                <div style="padding:16px;text-align:center;color:#64748b;">Loading saved staff list...</div>
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
            <span>Replace Staff on Row</span>
            <button type="button" class="att-modal-close" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
        </div>
        <div class="att-modal-body">
            <p style="margin-top:0;font-size:12px;">Select another saved person to take over this row for <strong id="att-replace-target-name"></strong>:</p>
            <input type="hidden" id="att-replace-row-id" value="">
            <input type="text" id="att-replace-search-input" class="att-input" placeholder="Search replacement staff by ID or Name..." style="margin-bottom:8px;">

            <div style="margin-bottom:10px;">
                <label style="font-size:11px;font-weight:bold;cursor:pointer;">
                    <input type="checkbox" id="att-replace-clear-codes"> Clear row's day codes (By default the codes stay)
                </label>
            </div>

            <div class="att-staff-list-container" id="att-replace-directory-list" style="max-height:220px;">
                <div style="padding:16px;text-align:center;color:#64748b;">Type to search saved staff...</div>
            </div>
        </div>
        <div class="att-modal-footer">
            <button type="button" class="att-btn att-btn-secondary att-modal-close">Cancel</button>
        </div>
    </div>
</div>

<!-- Modal: Edit Staff Name / ID -->
<div class="att-modal-overlay no-print" id="att-modal-edit-staff">
    <div class="att-modal" style="width:400px;">
        <div class="att-modal-header">
            <span>Edit Staff Details</span>
            <button type="button" class="att-modal-close" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
        </div>
        <div class="att-modal-body">
            <input type="hidden" id="att-edit-row-id" value="">
            <div class="att-form-group">
                <label for="att-edit-emp-no">Emp No:</label>
                <input type="text" id="att-edit-emp-no" class="att-input" required>
            </div>
            <div class="att-form-group">
                <label for="att-edit-name">Staff Name:</label>
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
            <span>Create New Attendance Site</span>
            <button type="button" class="att-modal-close" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
        </div>
        <form method="POST" action="index.php?action=save_site">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(att_get_csrf_token()) ?>">
            <div class="att-modal-body">
                <div class="att-form-group">
                    <label for="att-new-site-name">Site Name: *</label>
                    <input type="text" id="att-new-site-name" name="site_name" class="att-input" required placeholder="e.g. NASA Building">
                </div>
                <div class="att-form-group">
                    <label for="att-new-site-code">Site Code / Acronym: (Optional)</label>
                    <input type="text" id="att-new-site-code" name="site_code" class="att-input" placeholder="e.g. NASABLDG">
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
            <span>Delete Site</span>
            <button type="button" class="att-modal-close" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
        </div>
        <form method="POST" action="index.php?action=delete_site">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(att_get_csrf_token()) ?>">
            <input type="hidden" name="site_id" value="<?= $sheet['site_id'] ?>">
            <div class="att-modal-body">
                <p style="font-size:14px;color:#1e293b;margin:0 0 12px 0;">
                    Are you sure you want to delete site <strong><?= htmlspecialchars($sheet['site_name']) ?></strong>?
                </p>
                <div style="background:#fef2f2;border:1px solid #fecaca;padding:12px;border-radius:4px;color:#991b1b;font-size:12px;line-height:1.4;">
                    ⚠️ This site will be removed from the active attendance site list. All historical roster records are safely preserved in the database.
                </div>
            </div>
            <div class="att-modal-footer">
                <button type="button" class="att-btn att-btn-secondary att-modal-close">Cancel</button>
                <button type="submit" class="att-btn att-btn-danger" style="background:#dc2626;color:#ffffff;border-color:#dc2626;">Yes, Delete Site</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Additional Deployment Options -->
<div class="att-modal-overlay no-print" id="att-modal-additional-deployment">
    <div class="att-modal" style="width:640px;">
        <div class="att-modal-header" style="background:#0284c7;color:#ffffff;">
            <span>⚡ Additional Deployment Options - <?= htmlspecialchars($sheet['site_name']) ?></span>
            <button type="button" class="att-modal-close" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
        </div>
        <div class="att-modal-body">
            <!-- Add New Additional Deployment -->
            <form method="POST" action="index.php?action=save_additional_deployment" style="background:#f8fafc;padding:14px;border:1px solid #cbd5e1;border-radius:6px;margin-bottom:16px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(att_get_csrf_token()) ?>">
                <input type="hidden" name="sheet_id" value="<?= $sheet['id'] ?>">
                <input type="hidden" name="site_id" value="<?= $sheet['site_id'] ?>">
                <input type="hidden" name="month" value="<?= $curMonth ?>">
                <input type="hidden" name="year" value="<?= $curYear ?>">

                <h4 style="margin:0 0 10px 0;font-size:13px;color:#0369a1;">+ Set Additional Deployment</h4>
                <div style="display:flex;gap:10px;margin-bottom:10px;">
                    <div style="flex:1;">
                        <label style="display:block;font-size:11px;font-weight:bold;margin-bottom:3px;">Apply To Day:</label>
                        <select name="day_number" class="att-select" style="width:100%;">
                            <option value="all">-- All Days in <?= $monthName ?> --</option>
                            <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                                <option value="<?= $d ?>">Day <?= $d ?> (<?= date('D, M j', mktime(0, 0, 0, $curMonth, $d, $curYear)) ?>)</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div style="width:110px;">
                        <label style="display:block;font-size:11px;font-weight:bold;margin-bottom:3px;">Add Day (+D):</label>
                        <input type="number" name="add_day" class="att-input" value="0" min="0" style="width:100%;">
                    </div>
                    <div style="width:110px;">
                        <label style="display:block;font-size:11px;font-weight:bold;margin-bottom:3px;">Add Night (+N):</label>
                        <input type="number" name="add_night" class="att-input" value="0" min="0" style="width:100%;">
                    </div>
                </div>
                <div style="display:flex;gap:10px;align-items:flex-end;">
                    <div style="flex:1;">
                        <label style="display:block;font-size:11px;font-weight:bold;margin-bottom:3px;">Reason / Notes: (Optional)</label>
                        <input type="text" name="notes" class="att-input" placeholder="e.g. VIP Visit, Weekend Event, Extra Post" style="width:100%;">
                    </div>
                    <button type="submit" class="att-btn att-btn-primary" style="background:#0284c7;border-color:#0284c7;white-space:nowrap;">Save Additional</button>
                </div>
            </form>

            <!-- Current Additional Deployments on this sheet -->
            <h4 style="margin:0 0 8px 0;font-size:12px;color:#334155;">Active Additional Deployments for <?= $monthName ?> <?= $curYear ?>:</h4>
            <?php if (empty($additionalDeployments)): ?>
                <div style="padding:14px;background:#f1f5f9;border-radius:4px;text-align:center;color:#64748b;font-size:12px;">
                    No additional deployments recorded for this sheet. Base site contract requirements apply (Day: <?= $siteReqDay ?>, Night: <?= $siteReqNight ?>).
                </div>
            <?php else: ?>
                <div style="max-height:220px;overflow-y:auto;border:1px solid #cbd5e1;border-radius:4px;">
                    <table style="width:100%;border-collapse:collapse;font-size:12px;text-align:center;">
                        <thead>
                            <tr style="background:#e2e8f0;font-weight:bold;">
                                <th style="padding:6px;border-bottom:1px solid #cbd5e1;">Day</th>
                                <th style="padding:6px;border-bottom:1px solid #cbd5e1;">+Day</th>
                                <th style="padding:6px;border-bottom:1px solid #cbd5e1;">+Night</th>
                                <th style="padding:6px;border-bottom:1px solid #cbd5e1;">Reason</th>
                                <th style="padding:6px;border-bottom:1px solid #cbd5e1;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($additionalDeployments as $ad): ?>
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:6px;font-weight:bold;">Day <?= $ad['day_number'] ?></td>
                                    <td style="padding:6px;color:#0f766e;font-weight:bold;">+<?= $ad['add_day'] ?></td>
                                    <td style="padding:6px;color:#0f766e;font-weight:bold;">+<?= $ad['add_night'] ?></td>
                                    <td style="padding:6px;color:#475569;"><?= htmlspecialchars($ad['notes'] ?: '-') ?></td>
                                    <td style="padding:6px;">
                                        <form method="POST" action="index.php?action=delete_additional_deployment" style="display:inline;" onsubmit="return confirm('Remove additional deployment for Day <?= $ad['day_number'] ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(att_get_csrf_token()) ?>">
                                            <input type="hidden" name="sheet_id" value="<?= $sheet['id'] ?>">
                                            <input type="hidden" name="site_id" value="<?= $sheet['site_id'] ?>">
                                            <input type="hidden" name="day_number" value="<?= $ad['day_number'] ?>">
                                            <input type="hidden" name="month" value="<?= $curMonth ?>">
                                            <input type="hidden" name="year" value="<?= $curYear ?>">
                                            <button type="submit" class="att-row-btn-icon" style="color:#dc2626;" title="Remove">🗑️</button>
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
            <span>⚙️ Site Contract & Requirements - <?= htmlspecialchars($sheet['site_name']) ?></span>
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
                    <input type="text" class="att-input" value="<?= htmlspecialchars($sheet['site_name']) ?>" disabled style="background:#f1f5f9;">
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
