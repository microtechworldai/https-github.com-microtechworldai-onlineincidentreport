<?php
/**
 * Monthly Supervisor Site Visit Report (STRICT ONE PAGE A4)
 * Formats: 30/31 Day summary table, Sites Visited per Day, Total Minutes Spent, Remarks, and Monthly Totals.
 * Ultimate Security Services (USS)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/pdf_tokens.php';

require_admin_auth();

$db = get_db();
$settings = get_system_settings();

$monthParam = trim($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthParam = date('Y-m');
}

$supervisorId = (int)($_GET['supervisor_id'] ?? 0);

$year = (int)substr($monthParam, 0, 4);
$month = (int)substr($monthParam, 5, 2);
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$monthLabel = date('F Y', strtotime("{$monthParam}-01"));

// Fetch supervisor details
$supName = "All Field Supervisors";
$supStaffId = "ALL";
if ($supervisorId > 0) {
    $sStmt = $db->prepare("SELECT full_name, staff_id FROM guards WHERE id = ? LIMIT 1");
    $sStmt->execute([$supervisorId]);
    $sup = $sStmt->fetch();
    if ($sup) {
        $supName = $sup['full_name'];
        $supStaffId = $sup['staff_id'];
    }
}

// Fetch all visits in this month grouped by date
$supWhere = $supervisorId > 0 ? "AND v.supervisor_id = {$supervisorId}" : "";
$rawVisits = $db->query("
    SELECT DATE(v.time_in) AS v_date,
           COUNT(v.id) AS sites_count,
           SUM(v.minutes_spent) AS day_minutes,
           GROUP_CONCAT(DISTINCT s.code ORDER BY v.time_in SEPARATOR ', ') AS sites_list,
           GROUP_CONCAT(DISTINCT SUBSTRING(v.remarks, 1, 40) SEPARATOR ' • ') AS day_remarks
    FROM supervisor_visits v
    JOIN sites s ON s.id = v.site_id
    WHERE v.time_in >= '{$monthParam}-01 00:00:00' 
      AND v.time_in <= '{$monthParam}-{$daysInMonth} 23:59:59'
      AND v.status = 'Completed'
      {$supWhere}
    GROUP BY DATE(v.time_in)
    ORDER BY v_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

$visitsByDate = [];
foreach ($rawVisits as $rv) {
    $visitsByDate[$rv['v_date']] = $rv;
}

// Calculate Monthly Totals
$monthTotalSites = 0;
$monthTotalMins = 0;
$activeDutyDays = 0;

foreach ($visitsByDate as $row) {
    $monthTotalSites += (int)$row['sites_count'];
    $monthTotalMins += (int)$row['day_minutes'];
    if ($row['sites_count'] > 0) {
        $activeDutyDays++;
    }
}

$monthHours = floor($monthTotalMins / 60);
$monthRemMins = $monthTotalMins % 60;
$monthFormattedTime = $monthHours > 0 ? "{$monthHours} hrs {$monthRemMins} mins" : "{$monthRemMins} mins";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly_Supervisor_Visit_Report_<?= htmlspecialchars($monthParam) ?><?= $supervisorId ? '_' . htmlspecialchars($supStaffId) : '' ?></title>
    <style>
    @page {
        size: A4 landscape;
        margin: 8mm 10mm;
    }
    body {
        font-family: "Noto Sans", Arial, Helvetica, sans-serif !important;
        font-size: 9pt !important;
        color: #000000 !important;
        background: #FFFFFF !important;
    }
    .report-title, h1 {
        font-size: 16pt !important;
        font-weight: 700 !important;
        color: #12263F !important;
    }
    .section-bar, .section-title {
        font-size: 10pt !important;
        font-weight: 700 !important;
        background-color: #12263F !important;
        color: #FFFFFF !important;
    }
    th {
        font-size: 9pt !important;
        font-weight: 600 !important;
        background-color: #235F63 !important;
        color: #FFFFFF !important;
    }
    td {
        font-size: 9pt !important;
    }
    .kpi-val, .metric-value {
        font-size: 24pt !important;
        font-weight: 700 !important;
    }
    .kpi-lbl {
        font-size: 9pt !important;
    }
    .footer-pt, .signoff-text {
        font-size: 8pt !important;
    }
    
        @page {
            size: A4 landscape;
            margin: 6mm 8mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            height: 100%;
            background: #ffffff;
            color: #0f172a;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 7.5pt;
            line-height: 1.2;
        }

        .no-print-bar {
            background: #0f172a;
            color: #fff;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
        }

        .btn-print {
            background: #0284c7;
            color: #fff;
            border: none;
            padding: 5px 14px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
            cursor: pointer;
        }

        /* Strict Single Page A4 Landscape Container */
        .page-container {
            width: 100%;
            max-width: 281mm;
            height: 196mm;
            max-height: 196mm;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }

        .report-header {
            border-bottom: 2px solid #0284c7;
            padding-bottom: 4px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-logo {
            height: 36px;
            max-width: 140px;
            object-fit: contain;
        }

        .header-center {
            text-align: center;
            flex: 1;
        }

        .company-title {
            font-size: 12pt;
            font-weight: 900;
            color: #0b192c;
            text-transform: uppercase;
        }

        .report-title {
            font-size: 9.5pt;
            font-weight: 800;
            color: #0284c7;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .meta-strip {
            display: flex;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 4px 10px;
            justify-content: space-between;
            font-size: 7.5pt;
            margin-bottom: 4px;
        }

        /* 2-Column Daily Grid for All 31 Days */
        .monthly-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            flex: 1;
            overflow: hidden;
            margin-bottom: 4px;
        }

        table.month-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }

        table.month-table th {
            background: #0f172a;
            color: #fff;
            padding: 3px 5px;
            font-size: 6.5pt;
            text-transform: uppercase;
            border: 1px solid #0f172a;
        }

        table.month-table td {
            padding: 2.2px 5px;
            border: 1px solid #cbd5e1;
            line-height: 1.15;
        }

        table.month-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        .day-active {
            font-weight: bold;
            color: #0284c7;
        }

        /* Footer totals and signoff */
        .summary-row {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 4px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 7.5pt;
            margin-bottom: 4px;
        }

        .summary-row strong {
            font-size: 8.5pt;
            color: #0284c7;
            font-family: monospace;
        }

        .signatures-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }

        .signature-box {
            text-align: center;
        }

        .signature-line {
            width: 70%;
            margin: 14px auto 2px auto;
            border-top: 1px solid #0f172a;
        }

        .sign-title {
            font-size: 6.5pt;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
        }

        .sign-name {
            font-size: 7.5pt;
            font-weight: 800;
            color: #0f172a;
        }

        .report-footer {
            font-size: 6pt;
            color: #94a3b8;
            text-align: center;
            margin-top: 2px;
        }

        @media print {
            body { background: none; }
            .no-print-bar { display: none; }
            .page-container {
                max-width: 100%;
                height: 100%;
                max-height: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <div>
        <strong>Monthly Supervisor Visit Report</strong> &bull; <?= htmlspecialchars($monthLabel) ?> &bull; <?= htmlspecialchars($supName) ?>
    </div>
    <div style="display: flex; gap: 8px;">
        <button class="btn-print" onclick="window.print()">🖨️ Print / Save as PDF</button>
        <button class="btn-print" style="background: #475569;" onclick="window.close()">✕ Close</button>
    </div>
</div>

<div class="page-container">

    <!-- Header Block -->
    <div>
        <div class="report-header">
            <img class="header-logo" src="<?= APP_URL ?>/<?= htmlspecialchars($settings['logo_url']) ?>" alt="Logo" onerror="this.style.display='none'">
            <div class="header-center">
                <div class="company-title"><?= htmlspecialchars($settings['company_name']) ?></div>
                <div class="report-title">Monthly Supervisor Site Visit Report</div>
            </div>
            <div style="text-align: right; font-size: 7pt; color: #64748b;">
                <div>Dubai Server Timestamp Authority</div>
                <div>Generated: <?= date('d M Y, h:i A') ?></div>
            </div>
        </div>

        <div class="meta-strip">
            <div>Supervisor: <strong><?= htmlspecialchars($supName) ?></strong> (<?= htmlspecialchars($supStaffId) ?>)</div>
            <div>Reporting Period: <strong><?= htmlspecialchars($monthLabel) ?></strong> (1 to <?= $daysInMonth ?> <?= date('M Y', strtotime("{$monthParam}-01")) ?>)</div>
            <div>Days on Duty: <strong><?= $activeDutyDays ?> Days</strong></div>
            <div>Total Monthly Sites: <strong><?= $monthTotalSites ?> Sites</strong></div>
        </div>
    </div>

    <!-- 2 Column Split for 31 Days (Days 1-16 on Left, Days 17-31 on Right) -->
    <div class="monthly-grid">
        <!-- Col 1: Days 1 to 16 -->
        <div>
            <table class="month-table">
                <thead>
                    <tr>
                        <th style="width: 28px;">Day</th>
                        <th style="width: 65px;">Date</th>
                        <th style="width: 45px; text-align: center;">Sites</th>
                        <th style="width: 55px; text-align: center;">Time Spent</th>
                        <th>Inspected Checkpoint Sites & Key Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $half = ceil($daysInMonth / 2);
                    for ($d = 1; $d <= $half; $d++): 
                        $curDate = sprintf('%s-%02d', $monthParam, $d);
                        $dayOfWeek = date('D', strtotime($curDate));
                        $row = $visitsByDate[$curDate] ?? null;
                        $cnt = $row ? (int)$row['sites_count'] : 0;
                        $mins = $row ? (int)$row['day_minutes'] : 0;
                        $hrs = floor($mins / 60);
                        $rm = $mins % 60;
                        $timeStr = $cnt > 0 ? ($hrs > 0 ? "{$hrs}h {$rm}m" : "{$rm}m") : "-";
                    ?>
                        <tr>
                            <td style="text-align: center; font-weight: bold;"><?= $d ?></td>
                            <td><?= date('d M', strtotime($curDate)) ?> (<?= $dayOfWeek ?>)</td>
                            <td style="text-align: center; <?= $cnt > 0 ? 'font-weight: bold; color: #0284c7;' : 'color: #94a3b8;' ?>"><?= $cnt > 0 ? $cnt : '-' ?></td>
                            <td style="text-align: center; font-family: monospace; font-size: 6.5pt;"><?= $timeStr ?></td>
                            <td style="font-size: 6.5pt; color: #334155;">
                                <?php if ($cnt > 0): ?>
                                    <strong><?= htmlspecialchars($row['sites_list']) ?></strong>
                                    <?php if (!empty($row['day_remarks'])): ?>
                                        <span style="color: #64748b;"> &bull; <?= htmlspecialchars($row['day_remarks']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #cbd5e1;">No patrol recorded</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <!-- Col 2: Days 17 to End of Month -->
        <div>
            <table class="month-table">
                <thead>
                    <tr>
                        <th style="width: 28px;">Day</th>
                        <th style="width: 65px;">Date</th>
                        <th style="width: 45px; text-align: center;">Sites</th>
                        <th style="width: 55px; text-align: center;">Time Spent</th>
                        <th>Inspected Checkpoint Sites & Key Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    for ($d = $half + 1; $d <= $daysInMonth; $d++): 
                        $curDate = sprintf('%s-%02d', $monthParam, $d);
                        $dayOfWeek = date('D', strtotime($curDate));
                        $row = $visitsByDate[$curDate] ?? null;
                        $cnt = $row ? (int)$row['sites_count'] : 0;
                        $mins = $row ? (int)$row['day_minutes'] : 0;
                        $hrs = floor($mins / 60);
                        $rm = $mins % 60;
                        $timeStr = $cnt > 0 ? ($hrs > 0 ? "{$hrs}h {$rm}m" : "{$rm}m") : "-";
                    ?>
                        <tr>
                            <td style="text-align: center; font-weight: bold;"><?= $d ?></td>
                            <td><?= date('d M', strtotime($curDate)) ?> (<?= $dayOfWeek ?>)</td>
                            <td style="text-align: center; <?= $cnt > 0 ? 'font-weight: bold; color: #0284c7;' : 'color: #94a3b8;' ?>"><?= $cnt > 0 ? $cnt : '-' ?></td>
                            <td style="text-align: center; font-family: monospace; font-size: 6.5pt;"><?= $timeStr ?></td>
                            <td style="font-size: 6.5pt; color: #334155;">
                                <?php if ($cnt > 0): ?>
                                    <strong><?= htmlspecialchars($row['sites_list']) ?></strong>
                                    <?php if (!empty($row['day_remarks'])): ?>
                                        <span style="color: #64748b;"> &bull; <?= htmlspecialchars($row['day_remarks']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #cbd5e1;">No patrol recorded</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Summary & Signatures Section -->
    <div>
        <div class="summary-row">
            <div>Total Monthly Duty Days: <strong><?= $activeDutyDays ?> Days</strong></div>
            <div>Cumulative Sites Visited: <strong><?= $monthTotalSites ?> Site Visits</strong></div>
            <div>Total Hours on Sites: <strong><?= $monthFormattedTime ?></strong></div>
            <div>Daily Inspection Average: <strong><?= $activeDutyDays > 0 ? round($monthTotalSites / $activeDutyDays, 1) : 0 ?> sites/day</strong></div>
        </div>

        <div class="signatures-row">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="sign-name"><?= htmlspecialchars($supName) ?></div>
                <div class="sign-title">Field Supervisor Signature</div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="sign-name">Head of Operations / Security Director</div>
                <div class="sign-title">Monthly Audit Approval & Dossier Sign-Off</div>
            </div>
        </div>

        <div class="report-footer">
            <?= htmlspecialchars($settings['company_name']) ?> &bull; Monthly Supervisor Patrol Dossier &bull; Page 1 of 1 (Strict Single Page A4 Standard)
        </div>
    </div>

</div>

</body>
</html>
