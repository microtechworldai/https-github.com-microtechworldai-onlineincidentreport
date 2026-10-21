<?php
/**
 * Daily Supervisor Site Visit Report (STRICT ONE PAGE A4)
 * Formats: Date, Time In, Time Out, Site Name, Time Spent, Mandatory Remarks, Day/Night Summary.
 * Ultimate Security Services (USS)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$db = get_db();
$settings = get_system_settings();

$date = trim($_GET['date'] ?? date('Y-m-d'));
$supervisorId = (int)($_GET['supervisor_id'] ?? 0);

// Build query
$where = ["DATE(v.time_in) = ?"];
$params = [$date];

if ($supervisorId > 0) {
    $where[] = "v.supervisor_id = ?";
    $params[] = $supervisorId;
}

$whereSql = "WHERE " . implode(" AND ", $where);

$query = "
    SELECT v.*, 
           s.name AS site_name, s.code AS site_code,
           g.full_name AS supervisor_name, g.staff_id AS supervisor_staff_id,
           d.duty_ref, d.shift_type, d.camp_out_time, d.camp_in_time,
           c.camp_name
    FROM supervisor_visits v
    JOIN sites s ON s.id = v.site_id
    JOIN guards g ON g.id = v.supervisor_id
    JOIN supervisor_duties d ON d.id = v.duty_id
    JOIN camps c ON c.id = d.camp_id
    {$whereSql}
    ORDER BY v.time_in ASC
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$visits = $stmt->fetchAll();

// Get supervisor info for header
$supName = "All Supervisors";
$supStaffId = "-";
$campName = "-";
$shiftType = "Day / Night";

if (!empty($visits)) {
    $supName = $visits[0]['supervisor_name'];
    $supStaffId = $visits[0]['supervisor_staff_id'];
    $campName = $visits[0]['camp_name'];
    $shiftType = $visits[0]['shift_type'] . " Shift";
} elseif ($supervisorId > 0) {
    $sInfo = $db->prepare("SELECT full_name, staff_id FROM guards WHERE id = ? LIMIT 1");
    $sInfo->execute([$supervisorId]);
    $sup = $sInfo->fetch();
    if ($sup) {
        $supName = $sup['full_name'];
        $supStaffId = $sup['staff_id'];
    }
}

// Totals
$totalSites = count($visits);
$totalMins = 0;
foreach ($visits as $v) {
    $totalMins += (int)$v['minutes_spent'];
}
$totHours = floor($totalMins / 60);
$totRemMins = $totalMins % 60;
$timeFormatted = $totHours > 0 ? "{$totHours} hrs {$totRemMins} mins" : "{$totRemMins} mins";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Daily_Supervisor_Visit_Report_<?= htmlspecialchars($date) ?><?= $supervisorId ? '_' . htmlspecialchars($supStaffId) : '' ?></title>
    <style>
        @page {
            size: A4 portrait;
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
            font-size: 8.5pt;
            line-height: 1.25;
        }

        .no-print-bar {
            background: #0f172a;
            color: #fff;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
        }

        .btn-print {
            background: #0284c7;
            color: #fff;
            border: none;
            padding: 6px 16px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
            cursor: pointer;
        }

        /* Strict Single Page A4 Container */
        .page-container {
            width: 100%;
            max-width: 194mm;
            height: 283mm;
            max-height: 283mm;
            margin: 0 auto;
            padding: 2mm 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }

        /* Header block */
        .report-header {
            border-bottom: 2px solid #0284c7;
            padding-bottom: 5px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-logo {
            height: 42px;
            max-width: 160px;
            object-fit: contain;
        }

        .header-center {
            text-align: center;
            flex: 1;
        }

        .company-title {
            font-size: 13pt;
            font-weight: 900;
            color: #0b192c;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .report-title {
            font-size: 10.5pt;
            font-weight: 800;
            color: #0284c7;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 2px;
        }

        /* Meta Grid */
        .meta-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1fr;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            border-radius: 4px;
            padding: 6px 10px;
            margin-bottom: 8px;
            font-size: 8pt;
            gap: 4px 8px;
        }

        .meta-item strong {
            color: #475569;
            text-transform: uppercase;
            font-size: 7pt;
            display: block;
        }

        .meta-item span {
            color: #0f172a;
            font-weight: 700;
            font-size: 8.5pt;
        }

        /* Table */
        .table-wrap {
            flex: 1;
            overflow: hidden;
            margin-bottom: 6px;
        }

        table.dossier-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }

        table.dossier-table th {
            background: #0f172a;
            color: #ffffff;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 7pt;
            letter-spacing: 0.5px;
            padding: 5px 6px;
            border: 1px solid #0f172a;
            text-align: left;
        }

        table.dossier-table td {
            padding: 4.5px 6px;
            border: 1px solid #cbd5e1;
            vertical-align: top;
            line-height: 1.25;
        }

        table.dossier-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        .col-no { width: 22px; text-align: center; }
        .col-site { width: 155px; font-weight: 700; color: #0f172a; }
        .col-time { width: 55px; text-align: center; font-family: monospace; font-size: 7.5pt; font-weight: 600; }
        .col-spent { width: 62px; text-align: center; font-weight: 800; color: #0284c7; }
        .col-remarks { font-size: 7.5pt; color: #1e293b; }

        /* Summary Banner */
        .summary-banner {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 6px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 8pt;
            margin-bottom: 8px;
        }

        .summary-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .summary-item strong {
            font-size: 10pt;
            color: #0284c7;
            font-family: monospace;
        }

        /* Signatures Block */
        .signatures-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            padding-top: 6px;
            border-top: 1px solid #e2e8f0;
        }

        .signature-box {
            text-align: center;
        }

        .signature-line {
            width: 80%;
            margin: 24px auto 4px auto;
            border-top: 1px solid #0f172a;
        }

        .sign-title {
            font-size: 7.5pt;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
        }

        .sign-name {
            font-size: 8.5pt;
            font-weight: 800;
            color: #0f172a;
        }

        .report-footer {
            font-size: 6.5pt;
            color: #94a3b8;
            text-align: center;
            margin-top: 4px;
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
        <strong>Daily Supervisor Visit Report</strong> &bull; <?= htmlspecialchars($date) ?> &bull; <?= htmlspecialchars($supName) ?>
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
                <div class="report-title">Daily Supervisor Site Visit Report</div>
            </div>
            <div style="text-align: right; font-size: 7.5pt; color: #64748b;">
                <div>Dubai Server Timestamp Authority</div>
                <div>Generated: <?= date('d M Y, h:i A') ?></div>
            </div>
        </div>

        <!-- Metadata Bar -->
        <div class="meta-grid">
            <div class="meta-item">
                <strong>Field Supervisor</strong>
                <span><?= htmlspecialchars($supName) ?> (<?= htmlspecialchars($supStaffId) ?>)</span>
            </div>
            <div class="meta-item">
                <strong>Inspection Date</strong>
                <span><?= date('l, d M Y', strtotime($date)) ?></span>
            </div>
            <div class="meta-item">
                <strong>Shift Type</strong>
                <span><?= htmlspecialchars($shiftType) ?></span>
            </div>
            <div class="meta-item">
                <strong>Base Camp Accommodation</strong>
                <span><?= htmlspecialchars($campName) ?></span>
            </div>
        </div>
    </div>

    <!-- Table of Visits -->
    <div class="table-wrap">
        <table class="dossier-table">
            <thead>
                <tr>
                    <th class="col-no">#</th>
                    <th class="col-site">Site Name & Code</th>
                    <th class="col-time">Time In</th>
                    <th class="col-time">Time Out</th>
                    <th class="col-spent">Time Spent</th>
                    <th class="col-remarks">Mandatory Inspection Remarks & Site Findings</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($visits)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 28px; color: #94a3b8;">
                            No supervisor site visits recorded for this shift date.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($visits as $idx => $v): ?>
                        <tr>
                            <td class="col-no"><?= $idx + 1 ?></td>
                            <td class="col-site">
                                <?= htmlspecialchars($v['site_name']) ?>
                                <div style="font-size: 7pt; color: #64748b; font-weight: normal;"><?= htmlspecialchars($v['site_code']) ?></div>
                            </td>
                            <td class="col-time"><?= date('h:i A', strtotime($v['time_in'])) ?></td>
                            <td class="col-time"><?= $v['time_out'] ? date('h:i A', strtotime($v['time_out'])) : 'Active' ?></td>
                            <td class="col-spent"><?= (int)$v['minutes_spent'] ?> mins</td>
                            <td class="col-remarks">
                                <div><?= htmlspecialchars($v['remarks'] ?: 'Inspection complete. No major irregularities observed.') ?></div>
                                <?php
                                $rep = json_decode($v['site_report'] ?? '', true);
                                if ($rep):
                                ?>
                                    <div style="font-size: 6.5pt; color: #64748b; margin-top: 2px;">
                                        Guards: <strong><?= htmlspecialchars($rep['guard_alertness'] ?? '') ?></strong> &bull;
                                        Logbook: <strong><?= htmlspecialchars($rep['logbook_check'] ?? '') ?></strong> &bull;
                                        Perimeter: <strong><?= htmlspecialchars($rep['perimeter_status'] ?? '') ?></strong>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Summary & Signatures Section -->
    <div>
        <!-- Day Totals Summary Banner -->
        <div class="summary-banner">
            <div class="summary-item">
                <span>Total Sites Visited:</span>
                <strong><?= $totalSites ?> Sites</strong>
            </div>
            <div class="summary-item">
                <span>Total Time Actually Spent on Sites:</span>
                <strong><?= $timeFormatted ?></strong>
            </div>
            <div class="summary-item">
                <span>Average Time per Site:</span>
                <strong><?= $totalSites > 0 ? round($totalMins / $totalSites) : 0 ?> mins</strong>
            </div>
        </div>

        <!-- Official Sign-Off Block -->
        <div class="signatures-row">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="sign-name"><?= htmlspecialchars($supName) ?></div>
                <div class="sign-title">Field Supervisor Signature</div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="sign-name">Operations Manager / In-Charge</div>
                <div class="sign-title">Security Command Review & Approval</div>
            </div>
        </div>

        <div class="report-footer">
            <?= htmlspecialchars($settings['company_name']) ?> &bull; Daily Site Patrol Dossier &bull; Page 1 of 1 (Strict Single Page A4 Standard)
        </div>
    </div>

</div>

</body>
</html>
