<?php
/**
 * Monthly Site Visit & Security Supervision Audit Report (STRICT ONE PAGE A4)
 * Site-Centric Monthly Dossier with Quota Compliance, Checklist Ratings, and Sign-Off.
 * Ultimate Security Services (USS)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$db = get_db();
$settings = get_system_settings();

// Parameters
$siteId = (int)($_GET['site_id'] ?? 0);
$monthParam = trim($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthParam = date('Y-m');
}

$year = (int)substr($monthParam, 0, 4);
$month = (int)substr($monthParam, 5, 2);
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$monthLabel = date('F Y', strtotime("{$monthParam}-01"));

// Fetch all active sites for the selector
$allSites = $db->query("SELECT id, name, code, address, client_name, min_visits_per_week, min_night_visits_per_week FROM sites ORDER BY name ASC")->fetchAll();

if ($siteId <= 0 && !empty($allSites)) {
    $siteId = (int)$allSites[0]['id'];
}

// Fetch current site details
$siteStmt = $db->prepare("SELECT * FROM sites WHERE id = ? LIMIT 1");
$siteStmt->execute([$siteId]);
$site = $siteStmt->fetch();

if (!$site) {
    die("Site not found. <a href='supervisor_visits.php'>Return to Visits</a>");
}

$weeklyQuota = (int)($site['min_visits_per_week'] ?: 7);
$weeklyNightQuota = (int)($site['min_night_visits_per_week'] ?: 2);
$weeksInMonth = $daysInMonth / 7;
$monthlyTargetVisits = (int)ceil($weeklyQuota * $weeksInMonth);
$monthlyTargetNight = (int)ceil($weeklyNightQuota * $weeksInMonth);

// Fetch all completed visits for this site in the selected month
$startDate = "{$monthParam}-01 00:00:00";
$endDate = "{$monthParam}-{$daysInMonth} 23:59:59";

$visitsStmt = $db->prepare("
    SELECT v.*,
           g.full_name AS supervisor_name, g.staff_id AS supervisor_staff_id,
           d.shift_type, d.duty_ref
    FROM supervisor_visits v
    JOIN guards g ON g.id = v.supervisor_id
    JOIN supervisor_duties d ON d.id = v.duty_id
    WHERE v.site_id = ?
      AND v.time_in >= ?
      AND v.time_in <= ?
      AND v.status = 'Completed'
    ORDER BY v.time_in ASC
");
$visitsStmt->execute([$siteId, $startDate, $endDate]);
$visits = $visitsStmt->fetchAll();

// Metrics
$totalVisits = count($visits);
$totalMins = 0;
$nightVisitsCount = 0;
$issuesCount = 0;

foreach ($visits as $v) {
    $totalMins += (int)$v['minutes_spent'];
    $hour = (int)date('H', strtotime($v['time_in']));
    if ($v['shift_type'] === 'Night' || $hour >= 20 || $hour < 6) {
        $nightVisitsCount++;
    }
    if (!empty($v['has_issues']) && $v['has_issues'] == 1) {
        $issuesCount++;
    }
}

$totHours = floor($totalMins / 60);
$totRemMins = $totalMins % 60;
$totalTimeFormatted = $totHours > 0 ? "{$totHours}h {$totRemMins}m" : "{$totRemMins}m";
$avgMinsPerVisit = $totalVisits > 0 ? round($totalMins / $totalVisits, 1) : 0;

// Incidents count in same period
$incStmt = $db->prepare("SELECT COUNT(*) FROM incidents WHERE site_id = ? AND incident_date >= ? AND incident_date <= ?");
$incStmt->execute([$siteId, "{$monthParam}-01", "{$monthParam}-{$daysInMonth}"]);
$incidentsCount = (int)$incStmt->fetchColumn();

// Quota compliance check
$quotaMet = ($totalVisits >= $monthlyTargetVisits);
$nightQuotaMet = ($nightVisitsCount >= $monthlyTargetNight);
$complianceStatus = ($quotaMet && $nightQuotaMet) ? 'COMPLIANT' : 'SHORTFALL';

// Dynamic sizing class
$densityClass = 'density-normal';
if ($totalVisits > 22 && $totalVisits <= 30) {
    $densityClass = 'density-compact';
} elseif ($totalVisits > 30) {
    $densityClass = 'density-ultra';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly_Site_Report_<?= htmlspecialchars($site['code']) ?>_<?= htmlspecialchars($monthParam) ?></title>
    <style>
        @page {
            size: A4 landscape;
            margin: 5mm 7mm;
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
            padding: 10px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .no-print-bar select, .no-print-bar input {
            background: #1e293b;
            color: #fff;
            border: 1px solid #475569;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
        }

        .btn-print {
            background: #0284c7;
            color: #fff;
            border: none;
            padding: 7px 18px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
        }
        .btn-print:hover { background: #0369a1; }

        .btn-back {
            color: #94a3b8;
            text-decoration: none;
            margin-right: 15px;
        }
        .btn-back:hover { color: #fff; }

        @media print {
            .no-print-bar { display: none !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .a4-page-container {
                max-height: 198mm !important;
                overflow: hidden !important;
            }
        }

        .a4-page-container {
            width: 100%;
            height: calc(100vh - 50px);
            max-height: 198mm;
            padding: 2mm 3mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: #fff;
        }

        /* Header block */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 5px;
            margin-bottom: 5px;
        }

        .company-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .company-logo {
            max-height: 34px;
            max-width: 130px;
            object-fit: contain;
        }
        .company-title {
            font-size: 11pt;
            font-weight: 900;
            letter-spacing: 0.5px;
            color: #0f172a;
            text-transform: uppercase;
        }
        .company-sub {
            font-size: 6.5pt;
            color: #64748b;
        }

        .doc-title-box {
            text-align: right;
        }
        .doc-main-title {
            font-size: 10.5pt;
            font-weight: 800;
            color: #0284c7;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        .doc-meta {
            font-size: 6.5pt;
            color: #475569;
            margin-top: 2px;
        }

        /* Site Meta & Quota Summary Cards */
        .site-meta-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1.3fr;
            gap: 6px;
            margin-bottom: 5px;
        }

        .meta-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 4px 7px;
        }
        .meta-card-label {
            font-size: 6pt;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 2px;
        }
        .meta-card-value {
            font-size: 8pt;
            font-weight: 800;
            color: #0f172a;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 6.5pt;
            font-weight: 800;
            text-transform: uppercase;
        }
        .badge-success { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .badge-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        /* Table */
        .table-wrap {
            flex: 1;
            overflow: hidden;
            margin-bottom: 5px;
        }

        .site-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }

        .site-table th {
            background: #0f172a;
            color: #ffffff;
            font-weight: 700;
            text-align: left;
            padding: 4px 5px;
            font-size: 6.5pt;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: 1px solid #334155;
            white-space: nowrap;
        }

        .site-table td {
            padding: 3px 5px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
            color: #1e293b;
        }

        .site-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        /* Density variations to guarantee 1 page */
        .density-compact .site-table th { padding: 3px 4px; font-size: 6pt; }
        .density-compact .site-table td { padding: 2px 4px; font-size: 6.5pt; }

        .density-ultra .site-table th { padding: 2px 3px; font-size: 5.5pt; }
        .density-ultra .site-table td { padding: 1.5px 3px; font-size: 5.8pt; line-height: 1.1; }

        .badge-rating {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 5.5pt;
            font-weight: 700;
            text-align: center;
        }
        .rating-ok { background: #dcfce7; color: #166534; }
        .rating-issue { background: #fee2e2; color: #991b1b; font-weight: 800; }
        .rating-na { background: #f1f5f9; color: #64748b; }

        /* Summary & Sign-off */
        .summary-bar {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 4px 10px;
            display: flex;
            justify-content: space-between;
            font-size: 6.8pt;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }
        .summary-bar strong { color: #0f172a; }

        .signatures-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-bottom: 4px;
        }

        .sig-box {
            border: 1px dashed #cbd5e1;
            border-radius: 4px;
            padding: 4px 8px;
            text-align: center;
            background: #fff;
        }
        .sig-line {
            height: 18px;
            border-bottom: 1px solid #475569;
            margin-bottom: 3px;
        }
        .sig-title {
            font-size: 6.5pt;
            font-weight: 700;
            color: #0f172a;
        }
        .sig-sub {
            font-size: 5.5pt;
            color: #64748b;
        }

        .report-footer {
            text-align: center;
            font-size: 5.5pt;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 3px;
        }
    </style>
</head>
<body>

<!-- Interactive Control Bar (Screen Only) -->
<div class="no-print-bar">
    <div style="display: flex; align-items: center; gap: 12px;">
        <a href="supervisor_visits.php" class="btn-back">&larr; Return to Supervisor Visits</a>
        <strong style="color: #38bdf8;">Monthly Site Patrol Dossier</strong>
    </div>

    <form method="GET" style="display: flex; align-items: center; gap: 10px; margin: 0;">
        <label style="color: #94a3b8; font-size: 12px;">Site:</label>
        <select name="site_id" onchange="this.form.submit()">
            <?php foreach ($allSites as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $siteId == $s['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['code']) ?> - <?= htmlspecialchars($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label style="color: #94a3b8; font-size: 12px; margin-left: 8px;">Month:</label>
        <input type="month" name="month" value="<?= htmlspecialchars($monthParam) ?>" onchange="this.form.submit()">

        <button type="submit" style="background: #334155; color: #fff; border: 1px solid #475569; padding: 6px 12px; border-radius: 6px; cursor: pointer;">
            Apply
        </button>
    </form>

    <div>
        <button class="btn-print" onclick="window.print()">🖨️ Print Single A4 Page</button>
    </div>
</div>

<div class="a4-page-container <?= $densityClass ?>">

    <!-- Report Header -->
    <div class="report-header">
        <div class="company-brand">
            <?php if (!empty($settings['logo_url']) && file_exists(__DIR__ . '/../' . $settings['logo_url'])): ?>
                <img src="../<?= htmlspecialchars($settings['logo_url']) ?>" alt="Logo" class="company-logo">
            <?php endif; ?>
            <div>
                <div class="company-title"><?= htmlspecialchars($settings['company_name']) ?></div>
                <div class="company-sub"><?= htmlspecialchars($settings['tagline'] ?? 'Total Security Solutions') ?> &bull; UAE PSBD / SIRA Operations Division</div>
            </div>
        </div>

        <div class="doc-title-box">
            <div class="doc-main-title">Monthly Site Supervision & Patrol Dossier</div>
            <div class="doc-meta">
                Period: <strong><?= $monthLabel ?></strong> &bull; Generated: <?= date('d M Y, H:i') ?> GST &bull; <strong>Strict Single-Page A4 Standard</strong>
            </div>
        </div>
    </div>

    <!-- Site Metadata & Quota Evaluation -->
    <div class="site-meta-grid">
        <div class="meta-card">
            <div class="meta-card-label">Client & Facility Location</div>
            <div class="meta-card-value"><?= htmlspecialchars($site['name']) ?> (<?= htmlspecialchars($site['code']) ?>)</div>
            <div style="font-size: 6.5pt; color: #475569; margin-top: 1px;">
                Client: <strong><?= htmlspecialchars($site['client_name'] ?? 'Contract Client') ?></strong> &bull; Contact: <?= htmlspecialchars($site['client_contact'] ?? '-') ?> &bull; Loc: <?= htmlspecialchars($site['address'] ?? 'UAE') ?>
            </div>
        </div>

        <div class="meta-card">
            <div class="meta-card-label">Patrol Quotas & Target</div>
            <div class="meta-card-value">
                <?= $weeklyQuota ?>/wk (<span style="color: #0284c7;"><?= $monthlyTargetVisits ?>/mo</span>)
            </div>
            <div style="font-size: 6.5pt; color: #475569; margin-top: 1px;">
                Night Quota: <?= $weeklyNightQuota ?>/wk (<?= $monthlyTargetNight ?>/mo)
            </div>
        </div>

        <div class="meta-card">
            <div class="meta-card-label">Actual Executed Visits</div>
            <div class="meta-card-value" style="color: <?= $quotaMet ? '#15803d' : '#b91c1c' ?>;">
                <?= $totalVisits ?> Visits
            </div>
            <div style="font-size: 6.5pt; color: #475569; margin-top: 1px;">
                Night Visits: <strong><?= $nightVisitsCount ?></strong> &bull; Time: <strong><?= $totalTimeFormatted ?></strong>
            </div>
        </div>

        <div class="meta-card" style="text-align: right; display: flex; flex-direction: column; justify-content: center; align-items: flex-end;">
            <div class="meta-card-label">Compliance Status</div>
            <?php if ($complianceStatus === 'COMPLIANT'): ?>
                <span class="status-badge badge-success">✓ QUOTA COMPLIANT (100% MET)</span>
            <?php else: ?>
                <span class="status-badge badge-danger">⚠️ SHORT BY <?= max(0, $monthlyTargetVisits - $totalVisits) ?> VISITS</span>
            <?php endif; ?>
            <div style="font-size: 6pt; color: #64748b; margin-top: 2px;">
                Avg Visit Duration: <strong><?= $avgMinsPerVisit ?> mins</strong>
            </div>
        </div>
    </div>

    <!-- Table of Visits -->
    <div class="table-wrap">
        <table class="site-table">
            <thead>
                <tr>
                    <th style="width: 22px; text-align: center;">#</th>
                    <th style="width: 70px;">Date & Day</th>
                    <th style="width: 48px; text-align: center;">Shift</th>
                    <th style="width: 120px;">Field Supervisor</th>
                    <th style="width: 50px; text-align: center;">Time In</th>
                    <th style="width: 50px; text-align: center;">Time Out</th>
                    <th style="width: 45px; text-align: center;">Mins</th>
                    <th style="width: 42px; text-align: center;">Turnout</th>
                    <th style="width: 42px; text-align: center;">Records</th>
                    <th style="width: 42px; text-align: center;">Safety</th>
                    <th style="width: 42px; text-align: center;">Equip</th>
                    <th>Supervisor Remarks & Observations</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($visits)): ?>
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 25px; color: #94a3b8; font-style: italic;">
                            No completed supervisor inspections recorded for this site during <?= htmlspecialchars($monthLabel) ?>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $idx = 1;
                    foreach ($visits as $v): 
                        $inTime = date('H:i', strtotime($v['time_in']));
                        $outTime = !empty($v['time_out']) ? date('H:i', strtotime($v['time_out'])) : '--:--';
                        $dateFormatted = date('d M', strtotime($v['time_in']));
                        $dayOfWeek = date('D', strtotime($v['time_in']));

                        $tRating = $v['rating_turnout'] ?? 'OK';
                        $rRating = $v['rating_records'] ?? 'OK';
                        $cRating = $v['rating_condition'] ?? 'OK';
                        $eRating = $v['rating_equipment'] ?? 'OK';

                        $remarkDisplay = htmlspecialchars($v['remarks'] ?? '');
                        if (!empty($v['issues_action_taken'])) {
                            $remarkDisplay = '<strong style="color: #b91c1c;">[ISSUE: ' . htmlspecialchars(substr($v['issues_action_taken'], 0, 45)) . ']</strong> ' . $remarkDisplay;
                        }
                    ?>
                        <tr>
                            <td style="text-align: center; font-weight: bold; color: #64748b;"><?= $idx++ ?></td>
                            <td style="white-space: nowrap;"><strong><?= $dateFormatted ?></strong> <span style="color: #64748b; font-size: 6pt;">(<?= $dayOfWeek ?>)</span></td>
                            <td style="text-align: center;">
                                <span style="font-weight: 700; color: <?= $v['shift_type'] === 'Night' ? '#6366f1' : '#d97706' ?>;">
                                    <?= htmlspecialchars($v['shift_type']) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($v['supervisor_staff_id']) ?></strong> - <?= htmlspecialchars($v['supervisor_name']) ?>
                            </td>
                            <td style="text-align: center; font-family: monospace; font-weight: 700;"><?= $inTime ?></td>
                            <td style="text-align: center; font-family: monospace; font-weight: 700;"><?= $outTime ?></td>
                            <td style="text-align: center; font-weight: 800; color: #0284c7;"><?= (int)$v['minutes_spent'] ?>m</td>
                            <td style="text-align: center;"><span class="badge-rating rating-<?= strtolower($tRating) ?>"><?= $tRating ?></span></td>
                            <td style="text-align: center;"><span class="badge-rating rating-<?= strtolower($rRating) ?>"><?= $rRating ?></span></td>
                            <td style="text-align: center;"><span class="badge-rating rating-<?= strtolower($cRating) ?>"><?= $cRating ?></span></td>
                            <td style="text-align: center;"><span class="badge-rating rating-<?= strtolower($eRating) ?>"><?= $eRating ?></span></td>
                            <td style="font-size: 6.5pt; color: #334155;">
                                <?= $remarkDisplay ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Summary & Signatures Section -->
    <div>
        <div class="summary-bar">
            <div>Monthly Quota Target: <strong><?= $monthlyTargetVisits ?> Visits</strong> (Night: <strong><?= $monthlyTargetNight ?></strong>)</div>
            <div>Actual Completed Visits: <strong><?= $totalVisits ?></strong> (Night: <strong><?= $nightVisitsCount ?></strong>)</div>
            <div>Cumulative Site Presence: <strong><?= $totalTimeFormatted ?></strong></div>
            <div>Average Time per Visit: <strong><?= $avgMinsPerVisit ?> Minutes</strong></div>
            <div>Incidents Logged at Site: <strong><?= $incidentsCount ?></strong></div>
            <div>Compliance Status: <strong><?= $complianceStatus === 'COMPLIANT' ? '100% MET (PASS)' : 'SHORTFALL AUDITED' ?></strong></div>
        </div>

        <div class="signatures-grid">
            <div class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-title">Head of Security Operations / Super Admin</div>
                <div class="sig-sub">Ultimate Security Services UAE &bull; Operations Review</div>
            </div>

            <div class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-title">Area Patrol Supervisor / Field Inspector</div>
                <div class="sig-sub">Field Verification & Shift Handovers Certified</div>
            </div>

            <div class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-title">Client Representative Acknowledgement</div>
                <div class="sig-sub"><?= htmlspecialchars($site['client_name'] ?? 'Authorized Site Representative') ?> &bull; Facility Audit Receipt</div>
            </div>
        </div>

        <div class="report-footer">
            <?= htmlspecialchars($settings['company_name']) ?> &bull; Monthly Site Patrol & Field Supervision Audit &bull; Page 1 of 1 (Strict Single-Page A4 Guarantee)
        </div>
    </div>

</div>

</body>
</html>
