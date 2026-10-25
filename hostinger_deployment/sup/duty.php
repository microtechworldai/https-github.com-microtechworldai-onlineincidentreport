<?php

/**
 * Supervisor Daily Patrol Cockpit
 * Shows live shift timer, currently inspected site, today's visit list, total minutes, and QR scanner trigger.
 * Ultimate Security Services (USS)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['uss_supervisor_logged_in']) || empty($_SESSION['supervisor_id'])) {
    header("Location: " . APP_URL . "/sup/login.php");
    exit;
}
require_supervisor_auth();

$db = get_db();
$settings = get_system_settings();
$supervisorId = (int)$_SESSION['supervisor_id'];

// Get supervisor info
$supStmt = $db->prepare("
    SELECT g.*, c.camp_name, c.camp_code 
    FROM guards g 
    LEFT JOIN camps c ON c.id = g.assigned_camp_id 
    WHERE g.id = ? LIMIT 1
");
$supStmt->execute([$supervisorId]);
$supervisor = $supStmt->fetch();

// Find active duty shift
$dutyStmt = $db->prepare("
    SELECT d.*, c.camp_name, c.camp_code 
    FROM supervisor_duties d
    JOIN camps c ON c.id = d.camp_id
    WHERE d.supervisor_id = ? AND d.status = 'Active'
    ORDER BY d.id DESC LIMIT 1
");
$dutyStmt->execute([$supervisorId]);
$activeDuty = $dutyStmt->fetch();

// Find currently open visit
$openVisit = null;
if ($activeDuty) {
    $vStmt = $db->prepare("
        SELECT v.*, s.name AS site_name, s.code AS site_code,
               s.supervisor_qr_token, s.supervisor_qr_token_encrypted
        FROM supervisor_visits v
        JOIN sites s ON s.id = v.site_id
        WHERE v.duty_id = ? AND v.status = 'In Progress'
        ORDER BY v.id DESC LIMIT 1
    ");
    $vStmt->execute([$activeDuty['id']]);
    $openVisit = $vStmt->fetch();
}

// Fetch completed visits for today's active shift (or most recent duty if none active)
$completedVisits = [];
$displayDuty = $activeDuty;

if (!$displayDuty) {
    // Get latest completed duty
    $latestStmt = $db->prepare("
        SELECT d.*, c.camp_name, c.camp_code 
        FROM supervisor_duties d
        JOIN camps c ON c.id = d.camp_id
        WHERE d.supervisor_id = ? 
        ORDER BY d.id DESC LIMIT 1
    ");
    $latestStmt->execute([$supervisorId]);
    $displayDuty = $latestStmt->fetch();
}

if ($displayDuty) {
    $compStmt = $db->prepare("
        SELECT v.*, s.name AS site_name, s.code AS site_code
        FROM supervisor_visits v
        JOIN sites s ON s.id = v.site_id
        WHERE v.duty_id = ? AND v.status = 'Completed'
        ORDER BY v.time_in ASC
    ");
    $compStmt->execute([$displayDuty['id']]);
    $completedVisits = $compStmt->fetchAll();
}

// Compute total minutes and sites
$totalSites = count($completedVisits);
$totalMins = 0;
foreach ($completedVisits as $v) {
    $totalMins += (int)$v['minutes_spent'];
}
$totHours = floor($totalMins / 60);
$totRemMins = $totalMins % 60;

// Fetch Today's Planned Schedule for this Supervisor (Weekly template + Date overrides)
$dayOfWeek = (int)date('N'); // 1 (Mon) to 7 (Sun)
$todayDate = date('Y-m-d');

$schedStmt = $db->prepare("
    SELECT sc.*, s.name AS site_name, s.code AS site_code,
           s.supervisor_qr_token, s.supervisor_qr_token_encrypted
    FROM supervisor_schedules sc
    JOIN sites s ON s.id = sc.site_id
    WHERE sc.supervisor_id = ? 
      AND sc.is_active = 1
      AND (sc.specific_date = ? OR (sc.specific_date IS NULL AND sc.day_of_week = ?))
    ORDER BY sc.expected_time_start ASC
");
$schedStmt->execute([$supervisorId, $todayDate, $dayOfWeek]);
$todaySchedules = $schedStmt->fetchAll();

// Completed site IDs map
$completedSiteIds = array_column($completedVisits, 'site_id');

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Patrol Cockpit | <?= htmlspecialchars($settings['company_name']) ?></title>
    
    <link rel="preload" href="<?= APP_URL ?>/assets/fonts/noto-sans-variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/design-tokens.css?v=<?= time() ?>">
    <?php require_once __DIR__ . '/../includes/theme_bootstrapper.php'; ?>
        
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= time() ?>">
    <script>
        (function() {
            var theme = localStorage.getItem('uss_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <style>
        body {
            background: var(--bg-main);
            color: var(--text-main);
            padding: 12px 14px 80px 14px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        .cockpit-container {
            max-width: 640px;
            margin: 0 auto;
        }

        .sup-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: 14px 16px;
            margin-bottom: 14px;
        }

        .sup-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sup-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0284c7, #0f172a);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 17px;
        }

        .status-hero {
            border-radius: var(--radius-lg);
            padding: 20px 18px;
            margin-bottom: 16px;
            box-shadow: var(--shadow-card);
            position: relative;
            overflow: hidden;
        }

        .status-hero.active-site {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.25));
            border: 2px solid var(--accent-amber);
        }

        .status-hero.in-transit {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.15), rgba(2, 132, 199, 0.25));
            border: 2px solid var(--accent-cyan);
        }

        .status-hero.idle {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            text-align: center;
        }

        .summary-card {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }

        .stat-box {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 14px;
            text-align: center;
        }

        .stat-value {
            font-size: 26px;
            font-weight: 900;
            color: var(--accent-cyan);
            margin-top: 4px;
            font-family: monospace;
        }

        .stat-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .visit-card {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 14px 16px;
            margin-bottom: 10px;
        }

        .visit-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px;
        }

        .visit-times {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 12.5px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .visit-remarks {
            background: var(--bg-card-hover);
            border-left: 3px solid var(--accent-cyan);
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 13px;
            color: var(--text-main);
            margin-top: 8px;
            line-height: 1.4;
        }

        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            border-top: 1px solid var(--border-subtle);
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-around;
            z-index: 100;
        }

        .nav-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
        }

        .nav-btn.primary {
            background: var(--accent-cyan);
            color: #fff;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 13px;
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.4);
        }
    </style>
</head>

<body>

    <div class="cockpit-container">

        <!-- Top Header -->
        <div class="sup-header">
            <div class="sup-info">
                <div class="sup-avatar"><?= strtoupper(substr($supervisor['full_name'], 0, 1)) ?></div>
                <div>
                    <strong style="font-size: 15px; color: var(--text-main);"><?= htmlspecialchars($supervisor['full_name']) ?></strong>
                    <div style="font-size: 12px; color: var(--accent-cyan); font-weight: 600;">
                        <?= htmlspecialchars($supervisor['staff_id']) ?> &bull; Field Supervisor
                    </div>
                </div>
            </div>
            <div style="display: flex; gap: 8px;">
                <a href="<?= APP_URL ?>/sup/history.php" class="btn btn-sm btn-outline" style="font-size: 11.5px; padding: 5px 11px;">
                    📋 History
                </a>
                <a href="<?= APP_URL ?>/sup/logout.php" class="btn btn-sm btn-outline" style="font-size: 11.5px; padding: 5px 11px; color: #f43f5e;" onclick="return confirm('Log out from supervisor session?');">
                    Logout
                </a>
            </div>
        </div>

        <!-- Feedback notifications -->
        <?php if ($msg === 'duty_started'): ?>
            <div class="alert alert-success" style="margin-bottom: 14px;">
                ✓ Shift started! Camp Out timestamp recorded. Proceed to your first site.
            </div>
        <?php elseif ($msg === 'time_in_recorded'): ?>
            <div class="alert alert-success" style="margin-bottom: 14px;">
                ✓ Time In recorded at <?= htmlspecialchars($_GET['site'] ?? 'Site') ?>! Inspection timer running.
            </div>
        <?php elseif ($msg === 'visit_completed'): ?>
            <div class="alert alert-success" style="margin-bottom: 14px;">
                ✓ Time Out recorded! <?= (int)($_GET['mins'] ?? 0) ?> minutes spent on inspection.
            </div>
        <?php elseif ($msg === 'manual_request_submitted'): ?>
            <div class="alert alert-warning" style="margin-bottom: 14px; font-size: 13px;">
                ⚠️ Manual Time Out request submitted to the Super Admin. Official visit time remains unchanged until the request is reviewed.
            </div>
        <?php elseif ($msg === 'duty_completed'): ?>
            <div class="alert alert-info" style="margin-bottom: 14px;">
                ✓ Shift completed! Camp In recorded. All visit records finalized for the daily dossier.
            </div>
        <?php endif; ?>

        <!-- HERO STATUS -->
        <?php if (!$activeDuty): ?>
            <!-- NO ACTIVE DUTY -->
            <div class="status-hero idle">
                <div style="font-size: 38px; margin-bottom: 6px;">🏕️</div>
                <h2 style="font-size: 18px; font-weight: 800; color: var(--text-main);">No Active Patrol Shift</h2>
                <p style="font-size: 13px; color: var(--text-muted); margin: 6px 0 16px 0;">
                    Scan your accommodation Camp QR code to clock Camp Out and begin today's site visits.
                </p>
                <a href="<?= APP_URL ?>/sup/scanner.php" class="btn btn-primary" style="padding: 12px 24px; font-weight: 800; display: inline-flex; align-items: center; gap: 8px;">
                    📷 Scan Camp QR Code
                </a>
            </div>

        <?php elseif ($openVisit): ?>
            <!-- CURRENTLY INSPECTING A SITE -->
            <?php
            $timeInTs = strtotime($openVisit['time_in']);
            $nowTs = time();
            $currMins = max(1, round(($nowTs - $timeInTs) / 60));
            ?>
            <div class="status-hero active-site">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <span class="badge" style="background: var(--accent-amber); color: #000; font-weight: 800;">
                        ● <?= $openVisit['manual_timeout_status'] === 'Pending' ? 'MANUAL TIME OUT PENDING' : 'CURRENTLY INSPECTING SITE' ?>
                    </span>
                    <span style="font-size: 12px; font-weight: 700; color: var(--text-muted);">
                        Time In: <?= date('h:i A', $timeInTs) ?>
                    </span>
                </div>

                <div style="font-size: 22px; font-weight: 900; color: var(--text-main); line-height: 1.2;">
                    <?= htmlspecialchars($openVisit['site_name']) ?>
                </div>
                <div style="font-size: 13px; color: var(--accent-amber); font-weight: 700; margin-top: 2px;">
                    Site Code: <?= htmlspecialchars($openVisit['site_code']) ?> &bull; Ref: <?= htmlspecialchars($openVisit['visit_ref']) ?>
                </div>

                <div style="margin: 14px 0 16px 0; display: flex; align-items: baseline; gap: 8px;">
                    <div style="font-size: 34px; font-weight: 900; color: var(--accent-amber); font-family: monospace;">
                        <span id="liveSiteTimer"><?= $currMins ?></span>m
                    </div>
                    <div style="font-size: 13px; color: var(--text-muted);">spent on site so far</div>
                </div>

                <?php if ($openVisit['manual_timeout_status'] === 'Pending'): ?>
                <div class="alert alert-warning" style="margin-bottom: 12px;">
                    Wait for Super Admin review. Do not start another site visit until this request is resolved.
                </div>
                <?php else: ?>
                    <?php $openVisitToken = get_stored_qr_token($openVisit, 'supervisor_qr_token_encrypted', 'supervisor_qr_token'); ?>
                    <?php if ($openVisitToken !== ''): ?>
                    <a href="<?= APP_URL ?>/sup/index.php?token=<?= urlencode($openVisitToken) ?>" class="btn btn-primary" style="width: 100%; text-align: center; padding: 14px; font-size: 15px; font-weight: 800; background: var(--accent-amber); color: #000;">
                        ⏱️ Clock Out & Write Site Remarks
                    </a>
                    <?php else: ?>
                    <div style="color: var(--accent-rose); font-size: 13px; text-align: center; font-weight: 700;">
                        This site has no active Supervisor QR. Contact the Super Admin to issue one.
                    </div>
                    <?php endif; ?>
                    <div style="text-align: center; margin-top: 10px;">
                        <a href="<?= APP_URL ?>/sup/manual_request.php?visit_id=<?= $openVisit['id'] ?>" style="color: var(--accent-amber); font-size: 12px; text-decoration: underline;">
                            ⚠️ Left site without scanning out? Submit Manual Time Out Request
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- IN TRANSIT / READY FOR NEXT SITE -->
            <?php
            $dutyStartTs = strtotime($activeDuty['camp_out_time']);
            $patrolMins = round((time() - $dutyStartTs) / 60);
            $patrolHours = floor($patrolMins / 60);
            $patrolRem = $patrolMins % 60;
            ?>
            <div class="status-hero in-transit">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <span class="badge" style="background: var(--accent-cyan); color: #fff; font-weight: 800;">
                        🚗 IN TRANSIT &bull; <?= strtoupper($activeDuty['shift_type']) ?> SHIFT
                    </span>
                    <span style="font-size: 12px; font-weight: 700; color: var(--text-muted);">
                        Camp Out: <?= date('h:i A', $dutyStartTs) ?>
                    </span>
                </div>

                <div style="font-size: 20px; font-weight: 900; color: var(--text-main);">
                    Patrol Active (<?= $patrolHours > 0 ? "{$patrolHours}h {$patrolRem}m" : "{$patrolRem}m" ?>)
                </div>
                <div style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                    Base Camp: <?= htmlspecialchars($activeDuty['camp_name']) ?>
                </div>

                <div style="margin-top: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <a href="<?= APP_URL ?>/sup/scanner.php" class="btn btn-primary" style="padding: 12px; text-align: center; font-weight: 800; font-size: 14px;">
                        📷 Scan Site QR
                    </a>
                    <a href="<?= APP_URL ?>/sup/index.php?camp=<?= urlencode($activeDuty['camp_id']) ?>" class="btn btn-outline" style="padding: 12px; text-align: center; font-weight: 700; font-size: 13px;">
                        🏁 Return to Camp
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Summary Stats Bar -->
        <div class="summary-card">
            <div class="stat-box">
                <div class="stat-label">Sites Visited Today</div>
                <div class="stat-value"><?= $totalSites ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total Time on Sites</div>
                <div class="stat-value" style="color: var(--accent-emerald);">
                    <?= $totHours > 0 ? "{$totHours}h {$totRemMins}m" : "{$totRemMins}m" ?>
                </div>
            </div>
        </div>

        <!-- Today's Planned Schedule (Admin-Assigned) -->
        <?php if (!empty($todaySchedules)): ?>
            <div style="margin: 20px 0 16px 0;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <h3 style="font-size: 14.5px; font-weight: 800; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.5px; margin: 0;">
                        📋 Today's Assigned Schedule
                    </h3>
                    <span style="font-size: 11.5px; color: var(--accent-cyan); font-weight: 700;">
                        <?= count(array_intersect($completedSiteIds, array_column($todaySchedules, 'site_id'))) ?> / <?= count($todaySchedules) ?> Done
                    </span>
                </div>

                <div class="card" style="padding: 10px 14px; background: rgba(0,0,0,0.02); border-color: var(--border-subtle);">
                    <?php foreach ($todaySchedules as $sIdx => $sch):
                        $isDone = in_array($sch['site_id'], $completedSiteIds);
                    ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 0; <?= $sIdx > 0 ? 'border-top: 1px solid var(--border-subtle);' : '' ?>">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; background: <?= $isDone ? '#d1fae5' : '#f1f5f9' ?>; color: <?= $isDone ? '#065f46' : '#94a3b8' ?>;">
                                    <?= $isDone ? '✓' : ($sIdx + 1) ?>
                                </div>
                                <div>
                                    <strong style="font-size: 13.5px; color: var(--text-main); display: block; <?= $isDone ? 'text-decoration: line-through; opacity: 0.7;' : '' ?>">
                                        <?= htmlspecialchars($sch['site_name']) ?>
                                    </strong>
                                    <span style="font-size: 11px; color: var(--text-dim);">
                                        Code: <?= htmlspecialchars($sch['site_code']) ?>
                                        <?php if ($sch['expected_time_start']): ?>
                                            &bull; Window: <?= date('h:i A', strtotime($sch['expected_time_start'])) ?> - <?= date('h:i A', strtotime($sch['expected_time_end'])) ?>
                                        <?php endif; ?>
                                        &bull; Min: <?= (int)$sch['min_minutes'] ?>m
                                    </span>
                                </div>
                            </div>
                            <div>
                                <?php if ($isDone): ?>
                                    <span class="badge" style="background: #d1fae5; color: #065f46; font-weight: 800; font-size: 11px;">
                                        Visited ✓
                                    </span>
                                <?php elseif ($openVisit && $openVisit['site_id'] == $sch['site_id']): ?>
                                    <span class="badge" style="background: #fef3c7; color: #92400e; font-weight: 800; font-size: 11px;">
                                        Current ⏱️
                                    </span>
                                <?php else: ?>
                                    <a href="<?= APP_URL ?>/sup/scanner.php" class="badge" style="background: rgba(14, 165, 233, 0.15); color: #0284c7; font-weight: 800; font-size: 11px; text-decoration: none;">
                                        Scan Now →
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Today's Visits Registry -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin: 18px 0 10px 0;">
            <h3 style="font-size: 15px; font-weight: 800; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.5px;">
                Today's Completed Visits
            </h3>
            <span style="font-size: 12px; color: var(--text-dim);"><?= count($completedVisits) ?> Recorded</span>
        </div>

        <?php if (empty($completedVisits)): ?>
            <div class="card" style="text-align: center; padding: 28px 16px; color: var(--text-muted); font-size: 13px;">
                No completed site visits yet for this shift. Scan a site QR code when arriving.
            </div>
        <?php else: ?>
            <?php foreach ($completedVisits as $idx => $v): ?>
                <div class="visit-card">
                    <div class="visit-header">
                        <div>
                            <strong style="font-size: 15px; color: var(--text-main);">
                                #<?= $idx + 1 ?> &bull; <?= htmlspecialchars($v['site_name']) ?>
                            </strong>
                            <span style="font-size: 12px; color: var(--accent-cyan); font-weight: 700; margin-left: 6px;">
                                (<?= htmlspecialchars($v['site_code']) ?>)
                            </span>
                        </div>
                        <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: var(--accent-emerald); font-weight: 800;">
                            ⏱️ <?= (int)$v['minutes_spent'] ?> Mins
                        </span>
                    </div>

                    <div class="visit-times">
                        <div>Time In: <strong><?= date('h:i A', strtotime($v['time_in'])) ?></strong></div>
                        <div>&bull;</div>
                        <div>Time Out: <strong><?= date('h:i A', strtotime($v['time_out'])) ?></strong></div>
                    </div>

                    <?php if (!empty($v['remarks'])): ?>
                        <div class="visit-remarks">
                            <strong>Mandatory Remarks:</strong> <?= htmlspecialchars($v['remarks']) ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    $rep = json_decode($v['site_report'] ?? '', true);
                    if ($rep):
                    ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; font-size: 11px;">
                            <span class="badge" style="background: var(--bg-card-hover); color: var(--text-muted);">
                                Guards: <?= htmlspecialchars($rep['guard_alertness'] ?? 'Satisfactory') ?>
                            </span>
                            <span class="badge" style="background: var(--bg-card-hover); color: var(--text-muted);">
                                Logbook: <?= htmlspecialchars($rep['logbook_check'] ?? 'Verified') ?>
                            </span>
                            <span class="badge" style="background: var(--bg-card-hover); color: var(--text-muted);">
                                Perimeter: <?= htmlspecialchars($rep['perimeter_status'] ?? 'Secure') ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

    <!-- Fixed Bottom Floating Navigation -->
    <div class="bottom-nav">
        <a href="<?= APP_URL ?>/sup/duty.php" class="nav-btn" style="color: var(--accent-cyan);">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
            </svg>
            <span>Cockpit</span>
        </a>

        <a href="<?= APP_URL ?>/sup/scanner.php" class="nav-btn primary">
            📷 Scan QR
        </a>

        <a href="javascript:location.reload();" class="nav-btn">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            <span>Refresh</span>
        </a>
    </div>

    <script>
        // Auto-increment live site timer every minute
        let liveTimerEl = document.getElementById('liveSiteTimer');
        if (liveTimerEl) {
            setInterval(function() {
                let cur = parseInt(liveTimerEl.textContent) || 0;
                liveTimerEl.textContent = (cur + 1);
            }, 60000);
        }
    </script>

</body>

</html>