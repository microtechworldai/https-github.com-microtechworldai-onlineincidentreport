<?php
// Attendance & Payroll Module - Consolidated Payroll View
// Enterprise Executive Redesign
// File: hostinger_deployment/attendance/views/payroll.php

if (!defined('ATT_ACCESS')) {
    die('Direct access not permitted.');
}

$pageTitle = 'Consolidated Payroll';
$activeTab = 'payroll';
require __DIR__ . '/layout/header.php';

$staffList = $payrollData['staff'] ?? [];
$datesList = $payrollData['dates_list'] ?? [];
$startDate = $payrollData['start_date'] ?? date('Y-m-01');
$endDate = $payrollData['end_date'] ?? date('Y-m-t');
$customCols = $payrollData['custom_columns'] ?? [];
$daysCount = count($datesList);
$customColCount = count($customCols);

$startFormatted = strtoupper(date('j M Y', strtotime($startDate)));
$endFormatted = strtoupper(date('j M Y', strtotime($endDate)));

$totalTableCols = 2 + $daysCount + 6 + $customColCount + 1;

// Precalculate totals for Executive KPI Ribbon
$sumDay = 0; $sumNight = 0; $sumBasic = 0; $sumSick = 0; $sumOff = 0; $sumTotalDays = 0;
$customSums = [];
foreach ($customCols as $cc) {
    if ($cc['col_type'] === 'number') {
        $customSums[$cc['id']] = 0;
    }
}

foreach ($staffList as $st) {
    $sumDay += (int)($st['day_shifts'] ?? 0);
    $sumNight += (int)($st['night_shifts'] ?? 0);
    $sumBasic += (int)($st['basic_shifts'] ?? 0);
    $sumSick += (int)($st['sick_leaves'] ?? 0);
    $sumOff += (int)($st['off_days'] ?? 0);
    $sumTotalDays += (int)($st['total_working_days'] ?? 0);
}
$totalBilledDuties = $sumDay + $sumNight;
$totalStaffCount = count($staffList);
?>

<!-- 1. EXECUTIVE PAYROLL KPI RIBBON -->
<div class="att-kpi-ribbon no-print">
    <!-- Card 1: Total Workforce on Payroll -->
    <div class="att-kpi-card att-kpi-primary">
        <div class="att-kpi-top">
            <span class="att-kpi-title">Workforce on Payroll</span>
            <span class="att-kpi-tag att-tag-blue"><?= !empty($_GET['site_id']) ? 'Filtered Site' : 'Consolidated' ?></span>
        </div>
        <div class="att-kpi-value">
            👥 <?= $totalStaffCount ?> <span class="att-kpi-subval">Active Guards</span>
        </div>
        <div class="att-kpi-meta">
            <span>Period: <strong><?= $startFormatted ?> &rarr; <?= $endFormatted ?></strong></span>
        </div>
    </div>

    <!-- Card 2: Total Billed Duties Worked -->
    <div class="att-kpi-card att-kpi-success">
        <div class="att-kpi-top">
            <span class="att-kpi-title">Total Billed Duties</span>
            <span class="att-kpi-tag att-tag-green">Delivered</span>
        </div>
        <div class="att-kpi-value" style="color: #10b981;">
            📋 <?= $totalBilledDuties ?> <span class="att-kpi-subval">Duties Worked</span>
        </div>
        <div class="att-kpi-meta">
            <span>Total Payable Days: <strong><?= $sumTotalDays ?> Days</strong></span>
        </div>
    </div>

    <!-- Card 3: Shift Distribution -->
    <div class="att-kpi-card att-kpi-warning">
        <div class="att-kpi-top">
            <span class="att-kpi-title">Shift Breakdown</span>
            <span class="att-kpi-tag att-tag-amber"><?= $daysCount ?> Calendar Days</span>
        </div>
        <div class="att-kpi-value">
            <span>☀️ <?= $sumDay ?> Day</span>
            <span class="att-kpi-subval">&bull;</span>
            <span>🌙 <?= $sumNight ?> Night</span>
        </div>
        <div class="att-kpi-meta">
            <span>Basic Posts: <strong><?= $sumBasic ?> Duties</strong></span>
        </div>
    </div>

    <!-- Card 4: Rest Days & Sick Leave Welfare -->
    <div class="att-kpi-card att-kpi-purple">
        <div class="att-kpi-top">
            <span class="att-kpi-title">Rest &amp; Medical Welfare</span>
            <span class="att-kpi-tag att-tag-blue">Compliance</span>
        </div>
        <div class="att-kpi-value">
            <span>🏖️ <?= $sumOff ?> Off</span>
            <span class="att-kpi-subval">&bull;</span>
            <span>🏥 <?= $sumSick ?> Sick</span>
        </div>
        <div class="att-kpi-meta">
            <span>All Rosters Consolidated &bull; Real-time Calculations</span>
        </div>
    </div>
</div>

<!-- 2. MODERN CONTROL & FILTER TOOLBAR -->
<div class="att-controlbar no-print">
    <form method="GET" action="index.php" class="att-filters" id="att-payroll-filter-form">
        <input type="hidden" name="action" value="payroll">

        <label for="att-pay-start"><strong>From:</strong></label>
        <input type="date" id="att-pay-start" name="start_date" class="att-input" value="<?= htmlspecialchars($startDate) ?>">

        <label for="att-pay-end"><strong>To:</strong></label>
        <input type="date" id="att-pay-end" name="end_date" class="att-input" value="<?= htmlspecialchars($endDate) ?>">

        <label for="att-pay-site"><strong>Site:</strong></label>
        <select id="att-pay-site" name="site_id" class="att-select" onchange="this.form.submit()">
            <option value="">-- All Sites Consolidated --</option>
            <?php foreach ($sites as $s): ?>
                <option value="<?= $s['id'] ?>" <?= (isset($_GET['site_id']) && $_GET['site_id'] == $s['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['site_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="att-btn att-btn-primary">Apply Range</button>

        <!-- Quick Cycle Presets -->
        <div style="display:flex;gap:4px;margin-left:6px;">
            <button type="button" class="att-btn att-btn-secondary" id="att-preset-cur-month" style="font-size:11.5px;padding:5px 9px;" title="Current Calendar Month">🗓️ This Month</button>
            <button type="button" class="att-btn att-btn-secondary" id="att-preset-payroll-cycle" style="font-size:11.5px;padding:5px 9px;" title="Standard UAE 27th to 26th Payroll Cutoff">🔄 27th - 26th Cycle</button>
            <button type="button" class="att-btn att-btn-secondary" id="att-preset-prev-month" style="font-size:11.5px;padding:5px 9px;" title="Previous Full Month">⏪ Last Month</button>
        </div>

        <!-- Live Search Filter Input -->
        <div style="margin-left:10px;">
            <input type="text" id="att-payroll-search" class="att-input" placeholder="🔍 Filter Guard..." style="width:160px;padding:5px 10px;font-size:12px;">
        </div>
    </form>

    <div class="att-actions">
        <!-- Add Column Button -->
        <button type="button" class="att-btn att-btn-secondary" id="att-btn-add-col">
            + Add column
        </button>

        <!-- Excel Export Only -->
        <a href="index.php?action=export_excel&export_type=payroll&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?><?= !empty($_GET['site_id']) ? '&site_id=' . (int)$_GET['site_id'] : '' ?>" 
           class="att-btn att-btn-success">
            ⬇ Export Excel (.xls)
        </a>

        <!-- Database Backup -->
        <a href="index.php?action=backup_download" class="att-btn att-btn-secondary" title="Download complete SQL database backup">
            🛡️ Backup
        </a>
    </div>
</div>

<main class="att-container">
    
<!-- CLEAN DECENT TABLE STYLING - BACKGROUND REMOVED (Guaranteed Inline) -->
<style>
/* Table Wrapper */
.att-grid-wrapper {
  border: 1px solid #cbd5e1 !important;
  border-radius: 8px !important;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06) !important;
  background: #ffffff !important;
}

.att-roster-outer-table, .att-table {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Arial Narrow", Arial, sans-serif !important;
  border-collapse: collapse !important;
  width: 100% !important;
  background: #ffffff !important;
}

.att-roster-outer-table th, .att-roster-outer-table td,
.att-table th, .att-table td {
  border: 1px solid #cbd5e1 !important;
  vertical-align: middle !important;
  text-align: center !important;
}

/* 1. MAIN TITLE BANNER: Background Removed / Clean Crisp White with Blue Accent Border */
.att-th-banner-darkteal,
.att-payroll-header-banner {
  background: #ffffff !important;
  color: #0f172a !important;
  font-size: 18px !important;
  font-weight: 900 !important;
  height: 48px !important;
  line-height: 48px !important;
  text-align: center !important;
  vertical-align: middle !important;
  letter-spacing: 0.8px !important;
  text-transform: uppercase !important;
  border-bottom: 3px solid #0284c7 !important;
  text-shadow: none !important;
}

/* 2. SUB-HEADERS: Emp No, Guard Name, Site Name, Days 1..31 - Background Removed */
.att-th-top-row,
.att-th-teal-pay {
  background: #f8fafc !important;
  color: #0f172a !important;
  font-weight: 900 !important;
  font-size: 13.5px !important;
  letter-spacing: 0.3px !important;
  height: 32px !important;
  border: 1px solid #cbd5e1 !important;
  text-shadow: none !important;
}

/* Weekday Sub-Header (THU, FRI, SAT...) */
.att-th-teal-sub,
.att-th-teal-pay-sub {
  background: #f1f5f9 !important;
  color: #475569 !important;
  font-weight: 900 !important;
  font-size: 11.5px !important;
  letter-spacing: 0.3px !important;
  height: 26px !important;
  border: 1px solid #cbd5e1 !important;
}

/* Weekend Header (FRI, SUN) - Soft Amber Accent */
.att-th-teal-weekend {
  background: #fef3c7 !important;
  color: #92400e !important;
  font-weight: 900 !important;
}

/* Sticky Left Columns - Clean White */
.att-col-emp {
  background: #ffffff !important;
  color: #0f172a !important;
  font-weight: 900 !important;
  font-size: 13.5px !important;
  letter-spacing: 0.5px !important;
}

.att-col-name {
  background: #ffffff !important;
  color: #0f172a !important;
  font-weight: 900 !important;
  font-size: 14px !important;
  letter-spacing: 0.3px !important;
  text-align: left !important;
  padding-left: 10px !important;
}

.att-col-site {
  background: #ffffff !important;
  color: #0284c7 !important;
  font-weight: 800 !important;
  font-size: 13px !important;
  text-align: left !important;
  padding-left: 10px !important;
}

.att-th-top-row.att-col-emp,
.att-th-top-row.att-col-name,
.att-th-top-row.att-col-site {
  background: #f8fafc !important;
  color: #0f172a !important;
  text-align: center !important;
}

.att-col-day {
  width: 38px !important;
  min-width: 38px !important;
  max-width: 38px !important;
  font-weight: 900 !important;
}

/* 3. CELL INPUTS & SHIFT CODES (Crisp & Bold) */
.att-cell-input {
  width: 100% !important;
  height: 26px !important;
  border: none !important;
  background: transparent !important;
  text-align: center !important;
  font-size: 14px !important;
  font-weight: 900 !important;
  letter-spacing: 0.5px !important;
  text-transform: uppercase !important;
}

.att-code-D {
  color: #059669 !important;
  background: rgba(16, 185, 129, 0.14) !important;
  font-weight: 900 !important;
  border-radius: 4px !important;
}

.att-code-N {
  color: #2563eb !important;
  background: rgba(37, 99, 235, 0.14) !important;
  font-weight: 900 !important;
  border-radius: 4px !important;
}

.att-code-OFF {
  color: #dc2626 !important;
  background: rgba(220, 38, 38, 0.16) !important;
  font-weight: 900 !important;
  border-radius: 4px !important;
}

.att-code-S {
  color: #d97706 !important;
  background: rgba(217, 119, 6, 0.16) !important;
  font-weight: 900 !important;
  border-radius: 4px !important;
}

.att-code-B {
  color: #7c3aed !important;
  background: rgba(124, 58, 237, 0.14) !important;
  font-weight: 900 !important;
  border-radius: 4px !important;
}

/* 4. SUMMARY ROWS: DAY, NIGHT, AND TOTAL ON DUTY - Dark Background Removed */
.att-tr-day-total, .att-tr-night-total, .att-tr-grand-total {
  height: 28px !important;
  font-size: 14px !important;
  border-top: 2px solid #cbd5e1 !important;
}

.att-summary-label {
  background: #f8fafc !important;
  color: #0f172a !important;
  text-align: center !important;
  padding: 0 10px !important;
  font-weight: 900 !important;
  letter-spacing: 0.5px !important;
  position: sticky !important;
  left: 0 !important;
  z-index: 10 !important;
  font-size: 13.5px !important;
  border: 1px solid #cbd5e1 !important;
  text-shadow: none !important;
}

.att-tr-day-total .att-summary-label {
  color: #b45309 !important;
  background: #fffbeb !important;
}

.att-tr-night-total .att-summary-label {
  color: #0369a1 !important;
  background: #f0f9ff !important;
}

.att-tr-grand-total .att-summary-label {
  background: #ecfdf5 !important;
  color: #047857 !important;
  font-size: 14px !important;
}

.att-col-total-d, .att-col-total-n, .att-col-total-grand {
  text-align: center !important;
  vertical-align: middle !important;
  font-weight: 900 !important;
  font-size: 14px !important;
}

/* ====================================================================
 * DEPLOYMENT STATUSES: Clean, Decent, Soft Color Effect
 * 🟢 OK (Met) | 🔴 Shortage (Less) | 🟡 Over Deployed
 * ==================================================================== */
.att-deploy-ok {
  background: #dcfce7 !important;
  color: #15803d !important;
  font-weight: 900 !important;
  font-size: 14px !important;
  border: 1px solid #86efac !important;
  text-shadow: none !important;
  box-shadow: none !important;
}

.att-deploy-less {
  background: #fee2e2 !important;
  color: #b91c1c !important;
  font-weight: 900 !important;
  font-size: 14px !important;
  border: 1px solid #fca5a5 !important;
  text-shadow: none !important;
  box-shadow: none !important;
}

.att-deploy-over {
  background: #fef3c7 !important;
  color: #b45309 !important;
  font-weight: 900 !important;
  font-size: 14px !important;
  border: 1px solid #fcd34d !important;
  text-shadow: none !important;
  box-shadow: none !important;
}

.att-deploy-neutral {
  background-color: #ffffff !important;
  color: #94a3b8 !important;
  font-weight: 800 !important;
}

/* Legend Pills */
.att-legend-pills .att-pill-badge {
  padding: 4px 12px !important;
  border-radius: 20px !important;
  font-size: 12px !important;
  font-weight: 900 !important;
  display: inline-flex !important;
  align-items: center !important;
  gap: 6px !important;
}

.att-legend-pills .att-pill-badge.att-deploy-ok {
  background: #dcfce7 !important;
  color: #15803d !important;
  border: 1px solid #86efac !important;
  box-shadow: none !important;
}

.att-legend-pills .att-pill-badge.att-deploy-less {
  background: #fee2e2 !important;
  color: #b91c1c !important;
  border: 1px solid #fca5a5 !important;
  box-shadow: none !important;
}

.att-legend-pills .att-pill-badge.att-deploy-over {
  background: #fef3c7 !important;
  color: #b45309 !important;
  border: 1px solid #fcd34d !important;
  box-shadow: none !important;
}

/* 5. PAYROLL COUNTS & TOTAL CONSOLIDATED ROW - Background Removed */
.att-col-stat {
  font-weight: 900 !important;
  font-size: 14px !important;
  width: 46px !important;
  min-width: 46px !important;
}

.att-col-total {
  font-weight: 900 !important;
  font-size: 14.5px !important;
  width: 80px !important;
  min-width: 80px !important;
}

.att-payroll-row td {
  font-size: 13.5px !important;
  font-weight: 800 !important;
  background: #ffffff !important;
}

.att-payroll-total-row {
  background: #f8fafc !important;
  color: #0f172a !important;
  border-top: 2px solid #cbd5e1 !important;
}

.att-payroll-total-row td {
  background: #f8fafc !important;
  color: #0f172a !important;
  font-weight: 900 !important;
}
</style>

<!-- EXECUTIVE DECENT HEADER STYLING (Royal Navy & Gold Weekend Highlights) -->
<style>
/* 1. MAIN TITLE BANNER: Deep Executive Navy with Cyan Sheen */
.att-th-banner-darkteal,
.att-payroll-header-banner {
  background: linear-gradient(135deg, #0f2744 0%, #16365c 50%, #1f4575 100%) !important;
  color: #ffffff !important;
  font-size: 17.5px !important;
  font-weight: 800 !important;
  height: 48px !important;
  line-height: 48px !important;
  text-align: center !important;
  vertical-align: middle !important;
  letter-spacing: 1px !important;
  text-transform: uppercase !important;
  border-bottom: 3px solid #38bdf8 !important;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4) !important;
}

/* 2. SUB-HEADERS: Emp No, Guard Name, Site Name, Days 1..31 */
.att-th-top-row,
.att-th-teal-pay {
  background: linear-gradient(180deg, #1a365d 0%, #122844 100%) !important;
  color: #f8fafc !important;
  font-weight: 800 !important;
  font-size: 13.5px !important;
  letter-spacing: 0.3px !important;
  height: 32px !important;
  border: 1px solid #2d4a77 !important;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
}

/* Weekday Sub-Header (THU, FRI, SAT...) */
.att-th-teal-sub,
.att-th-teal-pay-sub {
  background: #0d1e33 !important;
  color: #cbd5e1 !important;
  font-weight: 800 !important;
  font-size: 11px !important;
  letter-spacing: 0.3px !important;
  height: 25px !important;
  border: 1px solid #1c3554 !important;
}

/* Weekend Header (FRI, SUN) - Elegant Gold Standout */
.att-th-teal-weekend {
  background: #091726 !important;
  color: #fbbf24 !important;
  font-weight: 900 !important;
  border: 1px solid #1c3554 !important;
}

/* Sticky Header Left Columns (Emp No, Guard Name, Site Name) */
.att-th-top-row.att-col-emp,
.att-th-top-row.att-col-name,
.att-th-top-row.att-col-site {
  background: linear-gradient(180deg, #1a365d 0%, #122844 100%) !important;
  color: #ffffff !important;
  text-align: center !important;
  border: 1px solid #2d4a77 !important;
}

.att-col-day {
  width: 38px !important;
  min-width: 38px !important;
  max-width: 38px !important;
}
</style>

<div class="att-grid-wrapper">
        <table class="att-table" id="att-payroll-table">
            <thead>
                <!-- Executive Title Banner -->
                <tr>
                    <th colspan="<?= $totalTableCols ?>" class="att-payroll-header-banner">
                        Ultimate Security Services &bull; Consolidated Payroll Attendance (<?= $startFormatted ?> &rarr; <?= $endFormatted ?>)
                    </th>
                </tr>

                <!-- Header Row 1: Emp No, Name, Day Numbers, Stat Columns -->
                <tr>
                    <th rowspan="2" class="att-th-teal-pay att-col-emp">Emp No</th>
                    <th rowspan="2" class="att-th-teal-pay att-col-name">Guard Name</th>

                    <!-- Date Numbers -->
                    <?php foreach ($datesList as $dInfo): 
                        $isWk = in_array(strtoupper($dInfo['weekday'] ?? ''), ['FRI', 'SUN']);
                    ?>
                        <th class="att-th-teal-pay att-col-day <?= $isWk ? 'att-th-teal-weekend' : '' ?>"><?= $dInfo['day'] ?></th>
                    <?php endforeach; ?>

                    <!-- Count Columns: Day, Night, Basic, Sick, Off, Total No. of Days -->
                    <th rowspan="2" class="att-th-teal-pay att-col-stat">Day</th>
                    <th rowspan="2" class="att-th-teal-pay att-col-stat">Night</th>
                    <th rowspan="2" class="att-th-teal-pay att-col-stat">Basic</th>
                    <th rowspan="2" class="att-th-teal-pay att-col-stat">Sick</th>
                    <th rowspan="2" class="att-th-teal-pay att-col-stat">Off</th>
                    <th rowspan="2" class="att-th-teal-pay att-col-total" style="background:linear-gradient(180deg, #0284c7 0%, #0369a1 100%) !important;color:#ffffff !important;font-weight:900 !important;">Total Days</th>

                    <!-- Custom Extra Columns -->
                    <?php foreach ($customCols as $cc): ?>
                        <th rowspan="2" class="att-th-teal-pay att-col-custom-th" data-col-id="<?= $cc['id'] ?>" data-col-name="<?= htmlspecialchars($cc['column_name']) ?>" data-col-type="<?= htmlspecialchars($cc['col_type']) ?>">
                            <span class="att-col-title"><?= htmlspecialchars($cc['column_name']) ?></span>
                            <div class="att-col-actions no-print">
                                <button type="button" class="att-btn-col-action att-btn-col-edit" title="Edit column">✏️</button>
                                <button type="button" class="att-btn-col-action att-btn-col-delete" title="Delete column">✕</button>
                            </div>
                        </th>
                    <?php endforeach; ?>

                    <th rowspan="2" class="att-th-teal-pay" style="min-width:140px;">Remarks</th>
                </tr>

                <!-- Header Row 2 (Weekday Names: TUE, WED, THU...) -->
                <tr>
                    <?php foreach ($datesList as $dInfo): 
                        $isWk = in_array(strtoupper($dInfo['weekday'] ?? ''), ['FRI', 'SUN']);
                    ?>
                        <th class="att-th-teal-pay-sub att-col-day <?= $isWk ? 'att-th-teal-weekend' : '' ?>"><?= $dInfo['weekday'] ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($staffList)): ?>
                    <tr>
                        <td colspan="<?= $totalTableCols ?>" style="padding:40px;color:var(--att-text-muted);text-align:center;">
                            <div style="font-size:24px;margin-bottom:8px;">💼</div>
                            <div style="font-size:14px;font-weight:700;">No attendance records found for this selected period</div>
                            <div style="margin-top:6px;font-size:12px;">Ensure attendance has been entered on the Attendance Rosters for these dates.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    foreach ($staffList as $st): 
                    ?>
                        <tr class="att-payroll-row" data-emp-no="<?= htmlspecialchars(strtolower($st['emp_no'] ?? '')) ?>" data-emp-name="<?= htmlspecialchars(strtolower($st['staff_name'] ?? '')) ?>">
                            <td class="att-col-emp"><?= htmlspecialchars($st['emp_no'] ?? '') ?></td>
                            <td class="att-col-name"><?= htmlspecialchars(strtoupper($st['staff_name'])) ?></td>

                            <!-- Daily Shift Codes -->
                            <?php foreach ($datesList as $dInfo): 
                                $code = $st['daily_codes'][$dInfo['date_str']] ?? '';
                                $isOff = ($code === 'OFF');
                                $isWk = in_array(strtoupper($dInfo['weekday'] ?? ''), ['FRI', 'SUN']);
                                $codeCls = in_array($code, ['D', 'N', 'OFF', 'S', 'B']) ? 'att-code-' . $code : '';
                            ?>
                                <td class="att-col-day <?= $isWk ? 'att-td-weekend' : '' ?>">
                                    <?php if (!empty($code)): ?>
                                        <span class="att-cell-input <?= $codeCls ?>" style="display:inline-block;line-height:24px;">
                                            <?= htmlspecialchars($code) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--att-text-muted);">&bull;</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>

                            <!-- Counts: Day, Night, Basic, Sick, Off, Total No. of Days -->
                            <td class="att-col-stat" style="color:#059669;font-weight:800;"><?= $st['day_shifts'] ?: '0' ?></td>
                            <td class="att-col-stat" style="color:#3b82f6;font-weight:800;"><?= $st['night_shifts'] ?: '0' ?></td>
                            <td class="att-col-stat" style="color:#8b5cf6;font-weight:800;"><?= $st['basic_shifts'] ?: '0' ?></td>
                            <td class="att-col-stat" style="color:#f59e0b;font-weight:800;"><?= $st['sick_leaves'] ?: '0' ?></td>
                            <td class="att-col-stat" style="color:#ef4444;font-weight:800;"><?= $st['off_days'] ?: '0' ?></td>
                            <td class="att-col-total" style="font-weight:900;background:rgba(14,165,233,0.15);color:#0284c7;"><?= $st['total_working_days'] ?></td>

                            <!-- Custom Column Values -->
                            <?php foreach ($customCols as $cc): 
                                $cVal = $st['custom_values'][$cc['id']] ?? null;
                                $isNum = ($cc['col_type'] === 'number');
                                $valDisp = '';
                                if ($isNum) {
                                    $rawNum = ($cVal !== null && isset($cVal['num_val']) && $cVal['num_val'] !== null && $cVal['num_val'] !== '') ? (float)$cVal['num_val'] : null;
                                    if ($rawNum !== null) {
                                        $valDisp = $rawNum;
                                        $customSums[$cc['id']] += $rawNum;
                                    }
                                } else {
                                    $valDisp = ($cVal !== null && isset($cVal['text_val'])) ? $cVal['text_val'] : '';
                                }
                            ?>
                                <td style="padding:0;text-align:center;">
                                    <input type="text" 
                                           class="att-cell-input att-custom-val-input <?= $isNum ? 'att-custom-num' : 'att-custom-txt' ?>" 
                                           data-emp-no="<?= htmlspecialchars($st['emp_no']) ?>"
                                           data-col-id="<?= $cc['id'] ?>"
                                           data-col-type="<?= $cc['col_type'] ?>"
                                           value="<?= htmlspecialchars($valDisp) ?>" 
                                           style="text-align:center;padding:0;font-size:12px;height:24px;"
                                           placeholder="...">
                                </td>
                            <?php endforeach; ?>

                            <!-- Remarks Input with Conflict Note if any -->
                            <td style="text-align:center;padding:0 4px;">
                                <input type="text" class="att-cell-input att-remarks-input" 
                                       data-emp-no="<?= htmlspecialchars($st['emp_no']) ?>" 
                                       value="<?= htmlspecialchars($st['remarks'] ?? '') ?>" 
                                       style="text-align:center;font-weight:normal;font-size:12px;height:24px;" 
                                       placeholder="...">
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- Bottom TOTAL Row -->
                    <tr style="height:28px;font-weight:800;background:#f8fafc;color:#0f172a;border-top:2px solid #cbd5e1;">
                        <td colspan="2" class="att-summary-label" style="text-align:center;font-weight:900;color:#0f172a;letter-spacing:0.8px;background:#f8fafc;">TOTAL CONSOLIDATED</td>
                        <?php foreach ($datesList as $dInfo): ?>
                            <td></td>
                        <?php endforeach; ?>
                        <td style="font-weight:900;text-align:center;color:#4ade80;"><?= $sumDay ?></td>
                        <td style="font-weight:900;text-align:center;color:#60a5fa;"><?= $sumNight ?></td>
                        <td style="font-weight:900;text-align:center;color:#c084fc;"><?= $sumBasic ?></td>
                        <td style="font-weight:900;text-align:center;color:#fde047;"><?= $sumSick ?></td>
                        <td style="font-weight:900;color:#f87171;text-align:center;"><?= $sumOff ?></td>
                        <td style="font-weight:900;background:#e0f2fe;color:#0369a1;text-align:center;font-size:14px;border:1px solid #bae6fd;"><?= $sumTotalDays ?></td>

                        <!-- Totals for Custom Columns -->
                        <?php foreach ($customCols as $cc): ?>
                            <?php if ($cc['col_type'] === 'number'): ?>
                                <td style="font-weight:900;color:#fde047;" class="att-custom-total-cell" data-col-id="<?= $cc['id'] ?>">
                                    <?= $customSums[$cc['id']] ?? 0 ?>
                                </td>
                            <?php else: ?>
                                <td></td>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <td></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Formula Explanation Note -->
    <div style="font-size:11.5px;font-weight:700;color:var(--att-text-muted);margin-top:12px;padding:6px 10px;background:var(--att-card-bg);border:1px solid var(--att-card-border);border-radius:6px;display:inline-flex;align-items:center;gap:6px;">
        <span>ℹ️</span> <strong>Payroll Formula:</strong> Total No. of Days = Day + Night + Basic + Sick (Off days are recorded for statutory compliance and not billed).
    </div>
</main>

<!-- Add Column Modal -->
<div class="att-modal-overlay no-print" id="att-modal-add-col">
    <div class="att-modal" style="max-width: 440px;">
        <div class="att-modal-header">
            <span>+ Add Custom Column to Payroll</span>
            <button type="button" class="att-modal-close" data-close-modal="att-modal-add-col">&times;</button>
        </div>
        <form id="att-form-add-col">
            <div class="att-modal-body">
                <div class="att-form-group">
                    <label for="att-new-col-name">Column Header Name:</label>
                    <input type="text" id="att-new-col-name" name="column_name" class="att-input" placeholder="e.g. Overtime (Hrs), Transport Allowance, Advance Deduction" required>
                </div>
                <div class="att-form-group">
                    <label>Data Entry Format:</label>
                    <label style="margin-right: 16px; font-weight: normal; cursor: pointer; display:flex; align-items:center; gap:6px;">
                        <input type="radio" name="col_type" value="number" checked> <strong>Numeric</strong> (Accepts numbers, automatically computes bottom total)
                    </label>
                    <label style="font-weight: normal; cursor: pointer; display:flex; align-items:center; gap:6px; margin-top: 8px;">
                        <input type="radio" name="col_type" value="text"> <strong>Text</strong> (Accepts remarks, codes, or text)
                    </label>
                </div>
            </div>
            <div class="att-modal-footer">
                <button type="button" class="att-btn att-btn-secondary" data-close-modal="att-modal-add-col">Cancel</button>
                <button type="submit" class="att-btn att-btn-primary">Add Column</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Column Modal -->
<div class="att-modal-overlay no-print" id="att-modal-edit-col">
    <div class="att-modal" style="max-width: 440px;">
        <div class="att-modal-header">
            <span>✏️ Edit Column Settings</span>
            <button type="button" class="att-modal-close" data-close-modal="att-modal-edit-col">&times;</button>
        </div>
        <form id="att-form-edit-col">
            <input type="hidden" id="att-edit-col-id" name="column_id" value="">
            <div class="att-modal-body">
                <div class="att-form-group">
                    <label for="att-edit-col-name">Column Header Name:</label>
                    <input type="text" id="att-edit-col-name" name="column_name" class="att-input" required>
                </div>
                <div class="att-form-group">
                    <label>Data Entry Format:</label>
                    <div style="display:flex;gap:16px;">
                        <label style="cursor: pointer; display:flex; align-items:center; gap:6px;">
                            <input type="radio" id="att-edit-type-number" name="col_type" value="number"> Number
                        </label>
                        <label style="cursor: pointer; display:flex; align-items:center; gap:6px;">
                            <input type="radio" id="att-edit-type-text" name="col_type" value="text"> Text
                        </label>
                    </div>
                </div>
            </div>
            <div class="att-modal-footer">
                <button type="button" class="att-btn att-btn-secondary" data-close-modal="att-modal-edit-col">Cancel</button>
                <button type="submit" class="att-btn att-btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const startDate = "<?= htmlspecialchars($startDate) ?>";
    const endDate = "<?= htmlspecialchars($endDate) ?>";

    // Quick Date Range Presets
    const btnCurMonth = document.getElementById('att-preset-cur-month');
    const btnPayrollCycle = document.getElementById('att-preset-payroll-cycle');
    const btnPrevMonth = document.getElementById('att-preset-prev-month');
    const startInput = document.getElementById('att-pay-start');
    const endInput = document.getElementById('att-pay-end');
    const filterForm = document.getElementById('att-payroll-filter-form');

    function pad2(n) { return n < 10 ? '0' + n : '' + n; }

    if (btnCurMonth) {
        btnCurMonth.addEventListener('click', () => {
            const now = new Date();
            const y = now.getFullYear();
            const m = now.getMonth();
            const first = new Date(y, m, 1);
            const last = new Date(y, m + 1, 0);
            startInput.value = `${y}-${pad2(m + 1)}-01`;
            endInput.value = `${y}-${pad2(m + 1)}-${pad2(last.getDate())}`;
            filterForm.submit();
        });
    }

    if (btnPayrollCycle) {
        btnPayrollCycle.addEventListener('click', () => {
            const now = new Date();
            const curY = now.getFullYear();
            const curM = now.getMonth(); // 0-indexed
            // If current day is >= 27, cycle is 27th curMonth to 26th nextMonth
            // Otherwise cycle is 27th prevMonth to 26th curMonth
            let startY, startM, endY, endM;
            if (now.getDate() >= 27) {
                startY = curY; startM = curM + 1;
                const nextM = new Date(curY, curM + 1, 1);
                endY = nextM.getFullYear(); endM = nextM.getMonth() + 1;
            } else {
                const prevM = new Date(curY, curM - 1, 1);
                startY = prevM.getFullYear(); startM = prevM.getMonth() + 1;
                endY = curY; endM = curM + 1;
            }
            startInput.value = `${startY}-${pad2(startM)}-27`;
            endInput.value = `${endY}-${pad2(endM)}-26`;
            filterForm.submit();
        });
    }

    if (btnPrevMonth) {
        btnPrevMonth.addEventListener('click', () => {
            const now = new Date();
            const y = now.getFullYear();
            const m = now.getMonth() - 1;
            const first = new Date(y, m, 1);
            const last = new Date(y, m + 1, 0);
            const realY = first.getFullYear();
            const realM = first.getMonth() + 1;
            startInput.value = `${realY}-${pad2(realM)}-01`;
            endInput.value = `${realY}-${pad2(realM)}-${pad2(last.getDate())}`;
            filterForm.submit();
        });
    }

    // Real-Time Guard Search / Filter
    const searchInput = document.getElementById('att-payroll-search');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.trim().toLowerCase();
            document.querySelectorAll('.att-payroll-row').forEach(row => {
                const no = row.dataset.empNo || '';
                const name = row.dataset.empName || '';
                if (!q || no.includes(q) || name.includes(q)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // Modal helpers
    function openModal(id) {
        const m = document.getElementById(id);
        if (m) m.classList.add('att-open');
    }
    function closeModal(id) {
        const m = document.getElementById(id);
        if (m) m.classList.remove('att-open');
    }

    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            closeModal(btn.getAttribute('data-close-modal'));
        });
    });

    // 1. Add Column Modal Trigger
    const addColBtn = document.getElementById('att-btn-add-col');
    if (addColBtn) {
        addColBtn.addEventListener('click', () => {
            document.getElementById('att-new-col-name').value = '';
            openModal('att-modal-add-col');
            document.getElementById('att-new-col-name').focus();
        });
    }

    // Submit Add Column
    const addColForm = document.getElementById('att-form-add-col');
    if (addColForm) {
        addColForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const colName = document.getElementById('att-new-col-name').value.trim();
            const colType = addColForm.elements['col_type'].value;

            if (!colName) return;

            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('column_name', colName);
            fd.append('col_type', colType);

            try {
                const res = await fetch('index.php?action=add_column', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Failed to add column');
                }
            } catch (err) {
                location.reload();
            }
        });
    }

    // 2. Edit Column Trigger
    document.querySelectorAll('.att-btn-col-edit').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const th = btn.closest('th');
            const colId = th.dataset.colId;
            const colName = th.dataset.colName;
            const colType = th.dataset.colType;

            document.getElementById('att-edit-col-id').value = colId;
            document.getElementById('att-edit-col-name').value = colName;
            if (colType === 'text') {
                document.getElementById('att-edit-type-text').checked = true;
            } else {
                document.getElementById('att-edit-type-number').checked = true;
            }

            openModal('att-modal-edit-col');
            document.getElementById('att-edit-col-name').focus();
        });
    });

    // Submit Edit Column
    const editColForm = document.getElementById('att-form-edit-col');
    if (editColForm) {
        editColForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const colId = document.getElementById('att-edit-col-id').value;
            const colName = document.getElementById('att-edit-col-name').value.trim();
            const colType = editColForm.elements['col_type'].value;

            if (!colName) return;

            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('column_id', colId);
            fd.append('column_name', colName);
            fd.append('col_type', colType);

            try {
                const res = await fetch('index.php?action=edit_column', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Failed to update column');
                }
            } catch (err) {
                alert('Connection error');
            }
        });
    }

    // 3. Delete Column Trigger
    document.querySelectorAll('.att-btn-col-delete').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.stopPropagation();
            const th = btn.closest('th');
            const colId = th.dataset.colId;
            const colName = th.dataset.colName;

            if (!confirm(`Delete column "${colName}"? Any entries in this column will no longer be shown or exported.`)) {
                return;
            }

            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('column_id', colId);

            try {
                const res = await fetch('index.php?action=delete_column', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Failed to delete column');
                }
            } catch (err) {
                alert('Connection error');
            }
        });
    });

    // 4. Custom Column Value Typing & Auto-Total Update
    function recalculateColumnTotal(colId) {
        let total = 0;
        document.querySelectorAll(`.att-custom-num[data-col-id="${colId}"]`).forEach(inp => {
            const v = parseFloat(inp.value);
            if (!isNaN(v)) {
                total += v;
            }
        });
        const totalCell = document.querySelector(`.att-custom-total-cell[data-col-id="${colId}"]`);
        if (totalCell) {
            totalCell.textContent = Math.round(total * 100) / 100;
        }
    }

    document.querySelectorAll('.att-custom-val-input').forEach(inp => {
        if (inp.dataset.colType === 'number') {
            inp.addEventListener('input', () => {
                inp.value = inp.value.replace(/[^0-9.]/g, '');
                const parts = inp.value.split('.');
                if (parts.length > 2) {
                    inp.value = parts[0] + '.' + parts.slice(1).join('');
                }
                recalculateColumnTotal(inp.dataset.colId);
            });
        }

        inp.addEventListener('change', () => {
            const empNo = inp.dataset.empNo;
            const colId = inp.dataset.colId;
            const colType = inp.dataset.colType;
            const val = inp.value.trim();

            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('emp_no', empNo);
            fd.append('column_id', colId);
            fd.append('start_date', startDate);
            fd.append('end_date', endDate);

            if (colType === 'number') {
                fd.append('num_val', val !== '' ? val : '');
            } else {
                fd.append('text_val', val);
            }

            fetch('index.php?action=save_custom_val', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
        });
    });

    // 5. Remarks Auto-Save
    document.querySelectorAll('.att-remarks-input').forEach(inp => {
        inp.addEventListener('change', () => {
            const empNo = inp.dataset.empNo;
            const remarks = inp.value.trim();

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('emp_no', empNo);
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);
            formData.append('remarks', remarks);

            fetch('index.php?action=save_remarks', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
        });
    });
});
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
