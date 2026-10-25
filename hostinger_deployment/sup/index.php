<?php
/**
 * Supervisor QR Dispatcher & Scan State Machine
 * Handles: Camp Out, Site Time In, Site Time Out (with mandatory remarks, structured report & photos), Drafts, and Camp In.
 * Ultimate Security Services (USS)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Authentication check
if (empty($_SESSION['uss_supervisor_logged_in']) || empty($_SESSION['supervisor_id'])) {
    $campParam = $_GET['camp'] ?? '';
    $tokenParam = $_GET['token'] ?? $_GET['qr'] ?? '';
    $loginUrl = APP_URL . "/sup/login.php";
    if ($campParam) {
        $loginUrl .= "?camp=" . urlencode($campParam);
    } elseif ($tokenParam) {
        $loginUrl .= "?token=" . urlencode($tokenParam);
    }
    header("Location: " . $loginUrl);
    exit;
}

require_supervisor_auth();

if (!empty($_GET['camp']) || !empty($_GET['token']) || !empty($_GET['qr']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/scan.php';
    exit;
}

$db = get_db();
$settings = get_system_settings();
$supervisorId = (int)$_SESSION['supervisor_id'];

// Min remarks length setting (default 20)
$minRemarksLen = max(10, (int)($settings['supervisor_remarks_min_length'] ?? 20));

// Get supervisor info
$supStmt = $db->prepare("SELECT * FROM guards WHERE id = ? LIMIT 1");
$supStmt->execute([$supervisorId]);
$supervisor = $supStmt->fetch();
if (!$supervisor || empty($supervisor['is_supervisor'])) {
    session_destroy();
    die("Access denied. Authorized supervisors only.");
}

// Find current active duty
$dutyStmt = $db->prepare("
    SELECT d.*, c.camp_name, c.camp_code 
    FROM supervisor_duties d
    JOIN camps c ON c.id = d.camp_id
    WHERE d.supervisor_id = ? AND d.status = 'Active'
    ORDER BY d.id DESC LIMIT 1
");
$dutyStmt->execute([$supervisorId]);
$activeDuty = $dutyStmt->fetch();

// Find current open site visit (if any)
$openVisit = null;
if ($activeDuty) {
    $visitStmt = $db->prepare("
        SELECT v.*, s.name AS site_name, s.code AS site_code, s.active_qr_token
        FROM supervisor_visits v
        JOIN sites s ON s.id = v.site_id
        WHERE v.duty_id = ? AND v.status = 'In Progress'
        ORDER BY v.id DESC LIMIT 1
    ");
    $visitStmt->execute([$activeDuty['id']]);
    $openVisit = $visitStmt->fetch();
}

$campToken = trim($_GET['camp'] ?? '');
$siteToken = trim($_GET['token'] ?? $_GET['qr'] ?? '');

// If accessed without parameters, go to duty cockpit
if (empty($campToken) && empty($siteToken) && $_SERVER['REQUEST_METHOD'] !== 'POST' && empty($_GET['confirmed'])) {
    header("Location: " . APP_URL . "/sup/duty.php");
    exit;
}

$pageTitle = "Patrol Checkpoint";
$error = '';
$successMsg = '';
$confirmedVisit = null;
$campData = null;
$siteData = null;

// ====================================================================
// 1. CAMP QR LOGIC
// ====================================================================
if ($campToken) {
    $stmt = $db->prepare("SELECT * FROM camps WHERE qr_token = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$campToken]);
    $campData = $stmt->fetch();

    if (!$campData) {
        $error = "Invalid or inactive Camp QR code. Please contact the Super Admin.";
    }
}

// Handle Camp Out (Start Duty) POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_camp_out'])) {
    $campId = (int)$_POST['camp_id'];
    $shiftType = in_array($_POST['shift_type'] ?? '', ['Day', 'Night']) ? $_POST['shift_type'] : 'Day';

    if ($activeDuty) {
        $error = "You already have an active patrol duty in progress!";
    } else {
        $dateStr = date('Ymd');
        $dutyRef = "DUTY-{$dateStr}-" . strtoupper(bin2hex(random_bytes(2)));

        $insertDuty = $db->prepare("
            INSERT INTO supervisor_duties 
            (duty_ref, supervisor_id, camp_id, duty_date, shift_type, camp_out_time, status, total_sites, total_minutes, created_at)
            VALUES (?, ?, ?, CURDATE(), ?, NOW(), 'Active', 0, 0, NOW())
        ");
        $insertDuty->execute([$dutyRef, $supervisorId, $campId, $shiftType]);
        
        log_audit('CAMP_OUT', "Supervisor {$supervisor['staff_id']} started duty ({$shiftType} Shift) at camp ID {$campId}", null, 'supervisor_duties', $db->lastInsertId());
        
        header("Location: " . APP_URL . "/sup/duty.php?msg=duty_started");
        exit;
    }
}

// Handle Camp In (End Duty) POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_camp_in'])) {
    if (!$activeDuty) {
        $error = "No active patrol duty found to end.";
    } elseif ($openVisit) {
        $error = "Cannot end duty! You are still checked in at {$openVisit['site_name']}. Please record Time Out first.";
    } else {
        $totStmt = $db->prepare("
            SELECT COUNT(*) AS total_count, COALESCE(SUM(minutes_spent), 0) AS total_mins 
            FROM supervisor_visits 
            WHERE duty_id = ? AND status = 'Completed'
        ");
        $totStmt->execute([$activeDuty['id']]);
        $totals = $totStmt->fetch();

        $updateDuty = $db->prepare("
            UPDATE supervisor_duties 
            SET camp_in_time = NOW(), status = 'Completed', total_sites = ?, total_minutes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $updateDuty->execute([$totals['total_count'], $totals['total_mins'], $activeDuty['id']]);

        log_audit('CAMP_IN', "Supervisor {$supervisor['staff_id']} completed duty. Sites visited: {$totals['total_count']}, Minutes spent: {$totals['total_mins']}", null, 'supervisor_duties', $activeDuty['id']);

        header("Location: " . APP_URL . "/sup/duty.php?msg=duty_completed");
        exit;
    }
}

// ====================================================================
// 2. SITE QR LOGIC
// ====================================================================
if ($siteToken) {
    $stmt = $db->prepare("SELECT * FROM sites WHERE (active_qr_token = ? OR code = ?) AND is_active = 1 LIMIT 1");
    $stmt->execute([$siteToken, $siteToken]);
    $siteData = $stmt->fetch();

    if (!$siteData) {
        $error = "Invalid or inactive Site QR code. Checkpoint not recognized.";
    } elseif (!$activeDuty) {
        $error = "No active patrol shift! You must first scan your accommodation Camp QR code (Camp Out) before visiting client sites.";
    }
}

// Handle Confirmation Display
if (!empty($_GET['confirmed'])) {
    $confId = (int)$_GET['confirmed'];
    $cStmt = $db->prepare("
        SELECT v.*, s.name AS site_name, s.code AS site_code
        FROM supervisor_visits v
        JOIN sites s ON s.id = v.site_id
        WHERE v.id = ? AND v.supervisor_id = ?
        LIMIT 1
    ");
    $cStmt->execute([$confId, $supervisorId]);
    $confirmedVisit = $cStmt->fetch();
}

// Handle Site Time Out Form Submission or Save Draft
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action_time_out']) || isset($_POST['action_save_draft']))) {
    $visitId = (int)$_POST['visit_id'];
    $isDraft = isset($_POST['action_save_draft']) ? 1 : 0;
    
    $remarks = trim($_POST['remarks'] ?? '');
    $purposeOfVisit = trim($_POST['purpose_of_visit'] ?? 'Routine');
    $guardsCount = (int)($_POST['guards_count'] ?? 0);
    $guardsAttendance = trim($_POST['guards_attendance'] ?? 'All present');
    $guardsNames = trim($_POST['guards_names'] ?? '');

    $ratingTurnout = trim($_POST['rating_turnout'] ?? 'OK');
    $ratingRecords = trim($_POST['rating_records'] ?? 'OK');
    $ratingCondition = trim($_POST['rating_condition'] ?? 'OK');
    $ratingEquipment = trim($_POST['rating_equipment'] ?? 'OK');

    $hasIssues = ($ratingTurnout === 'Issue' || $ratingRecords === 'Issue' || $ratingCondition === 'Issue' || $ratingEquipment === 'Issue') ? 1 : 0;
    $issuesActionTaken = trim($_POST['issues_action_taken'] ?? '');

    // Fetch visit
    $vStmt = $db->prepare("SELECT * FROM supervisor_visits WHERE id = ? AND supervisor_id = ? LIMIT 1");
    $vStmt->execute([$visitId, $supervisorId]);
    $visit = $vStmt->fetch();

    if (!$visit) {
        $error = "Active visit record not found.";
    } else {
        // Validation when Clocking Out (not draft)
        if (!$isDraft) {
            if (empty($remarks) || strlen($remarks) < $minRemarksLen) {
                $currentLen = strlen($remarks);
                $error = "Remarks too short! Minimum {$minRemarksLen} characters required (current: {$currentLen} characters).";
            } elseif ($hasIssues && empty($issuesActionTaken)) {
                $error = "An inspection 'Issue' was selected. The 'Issues found and action taken' field is mandatory.";
            }
        }

        // Process photos if uploaded
        $existingPhotosCount = (int)$db->query("SELECT COUNT(*) FROM supervisor_visit_photos WHERE visit_id = {$visit['id']}")->fetchColumn();
        $uploadedCount = 0;

        if (empty($error) && !empty($_FILES['photos']['name'][0])) {
            $totalFiles = count($_FILES['photos']['name']);
            for ($i = 0; $i < min(5, $totalFiles); $i++) {
                if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                    $res = process_and_save_photo($_FILES['photos']['tmp_name'][$i], $_FILES['photos']['name'][$i]);
                    if ($res) {
                        $pStmt = $db->prepare("
                            INSERT INTO supervisor_visit_photos (visit_id, file_path, original_filename, file_size, created_at)
                            VALUES (?, ?, ?, ?, NOW())
                        ");
                        $pStmt->execute([$visit['id'], $res['relative_path'], $_FILES['photos']['name'][$i], $res['size']]);
                        $uploadedCount++;
                    }
                }
            }
        }

        // Check if an Issue was selected but no photos exist
        if (!$isDraft && empty($error) && $hasIssues && ($existingPhotosCount + $uploadedCount === 0)) {
            $error = "Inspection has reported issues. Please attach at least 1 photo of the issue.";
        }

        if (empty($error)) {
            $siteReportJson = json_encode([
                'purpose_of_visit' => $purposeOfVisit,
                'guards_count' => $guardsCount,
                'guards_attendance' => $guardsAttendance,
                'guards_names' => $guardsNames,
                'rating_turnout' => $ratingTurnout,
                'rating_records' => $ratingRecords,
                'rating_condition' => $ratingCondition,
                'rating_equipment' => $ratingEquipment,
                'issues_action_taken' => $issuesActionTaken
            ]);

            if ($isDraft) {
                // Save draft
                $upStmt = $db->prepare("
                    UPDATE supervisor_visits 
                    SET remarks = ?, purpose_of_visit = ?, guards_count = ?, guards_attendance = ?, guards_names = ?,
                        rating_turnout = ?, rating_records = ?, rating_condition = ?, rating_equipment = ?,
                        has_issues = ?, issues_action_taken = ?, site_report = ?, is_draft = 1, updated_at = NOW()
                    WHERE id = ?
                ");
                $upStmt->execute([
                    $remarks, $purposeOfVisit, $guardsCount, $guardsAttendance, $guardsNames,
                    $ratingTurnout, $ratingRecords, $ratingCondition, $ratingEquipment,
                    $hasIssues, $issuesActionTaken, $siteReportJson, $visit['id']
                ]);
                $successMsg = "Draft saved on server! You can continue inspection or clock out when ready.";
                
                // Refresh openVisit data
                $visitStmt = $db->prepare("
                    SELECT v.*, s.name AS site_name, s.code AS site_code, s.active_qr_token
                    FROM supervisor_visits v
                    JOIN sites s ON s.id = v.site_id
                    WHERE v.id = ? LIMIT 1
                ");
                $visitStmt->execute([$visit['id']]);
                $openVisit = $visitStmt->fetch();
            } else {
                // Complete Time Out
                $nowTime = date('Y-m-d H:i:s');
                $timeInTs = strtotime($visit['time_in']);
                $minsSpent = max(1, round((strtotime($nowTime) - $timeInTs) / 60));

                $upStmt = $db->prepare("
                    UPDATE supervisor_visits 
                    SET time_out = ?, minutes_spent = ?, remarks = ?, purpose_of_visit = ?, guards_count = ?, guards_attendance = ?, guards_names = ?,
                        rating_turnout = ?, rating_records = ?, rating_condition = ?, rating_equipment = ?,
                        has_issues = ?, issues_action_taken = ?, site_report = ?, is_draft = 0, status = 'Completed', updated_at = NOW()
                    WHERE id = ?
                ");
                $upStmt->execute([
                    $nowTime, $minsSpent, $remarks, $purposeOfVisit, $guardsCount, $guardsAttendance, $guardsNames,
                    $ratingTurnout, $ratingRecords, $ratingCondition, $ratingEquipment,
                    $hasIssues, $issuesActionTaken, $siteReportJson, $visit['id']
                ]);

                // Update running totals on duty
                $db->prepare("
                    UPDATE supervisor_duties 
                    SET total_sites = total_sites + 1, total_minutes = total_minutes + ?, updated_at = NOW()
                    WHERE id = ?
                ")->execute([$minsSpent, $visit['duty_id']]);

                // Check site duration limits for alerts
                $siteRules = $db->prepare("SELECT name, min_minutes_per_visit, max_minutes_per_visit FROM sites WHERE id = ?");
                $siteRules->execute([$visit['site_id']]);
                $sRule = $siteRules->fetch();
                if ($sRule) {
                    if ($minsSpent < $sRule['min_minutes_per_visit']) {
                        $db->prepare("
                            INSERT INTO supervisor_alerts (supervisor_id, duty_id, visit_id, site_id, alert_type, severity, details, created_at)
                            VALUES (?, ?, ?, ?, 'VISIT_TOO_SHORT', 'Warning', ?, NOW())
                        ")->execute([
                            $supervisorId, $visit['duty_id'], $visit['id'], $visit['site_id'],
                            "Visit at {$sRule['name']} was {$minsSpent}m (below minimum requirement of {$sRule['min_minutes_per_visit']}m)"
                        ]);
                    } elseif ($minsSpent > $sRule['max_minutes_per_visit']) {
                        $db->prepare("
                            INSERT INTO supervisor_alerts (supervisor_id, duty_id, visit_id, site_id, alert_type, severity, details, created_at)
                            VALUES (?, ?, ?, ?, 'VISIT_TOO_LONG', 'Warning', ?, NOW())
                        ")->execute([
                            $supervisorId, $visit['duty_id'], $visit['id'], $visit['site_id'],
                            "Visit at {$sRule['name']} exceeded {$minsSpent}m (above maximum expected {$sRule['max_minutes_per_visit']}m)"
                        ]);
                    }
                }

                log_audit('SITE_TIME_OUT', "Supervisor {$supervisor['staff_id']} completed visit {$visit['visit_ref']} at site ID {$visit['site_id']}. Minutes: {$minsSpent}", null, 'supervisor_visits', $visit['id']);

                header("Location: " . APP_URL . "/sup/index.php?confirmed=" . $visit['id']);
                exit;
            }
        }
    }
}

// Handle Auto Time In (when scanned site QR while no open visit exists)
if ($siteData && $activeDuty && !$openVisit && $_SERVER['REQUEST_METHOD'] !== 'POST' && empty($_GET['confirmed'])) {
    $vRef = generate_visit_ref($siteData['code']);
    
    // Calculate travel minutes from previous completed site visit
    $lastVisitStmt = $db->prepare("
        SELECT time_out FROM supervisor_visits 
        WHERE duty_id = ? AND status = 'Completed' 
        ORDER BY id DESC LIMIT 1
    ");
    $lastVisitStmt->execute([$activeDuty['id']]);
    $lastTimeOut = $lastVisitStmt->fetchColumn();

    $travelMins = 0;
    if ($lastTimeOut) {
        $travelMins = max(0, round((time() - strtotime($lastTimeOut)) / 60));
    }

    $insertVisit = $db->prepare("
        INSERT INTO supervisor_visits 
        (visit_ref, duty_id, supervisor_id, site_id, time_in, travel_minutes_from_previous, status, is_draft, created_at)
        VALUES (?, ?, ?, ?, NOW(), ?, 'In Progress', 0, NOW())
    ");
    $insertVisit->execute([$vRef, $activeDuty['id'], $supervisorId, $siteData['id'], $travelMins]);
    $newVisitId = $db->lastInsertId();

    log_audit('SITE_TIME_IN', "Supervisor {$supervisor['staff_id']} checked in at {$siteData['code']} - {$siteData['name']} (Ref: {$vRef}, Travel: {$travelMins}m)", null, 'supervisor_visits', $newVisitId);

    header("Location: " . APP_URL . "/sup/duty.php?msg=time_in_recorded&site=" . urlencode($siteData['name']));
    exit;
}

// Fetch existing photos for open visit
$existingPhotos = [];
if ($openVisit) {
    $pQuery = $db->prepare("SELECT * FROM supervisor_visit_photos WHERE visit_id = ? ORDER BY id ASC");
    $pQuery->execute([$openVisit['id']]);
    $existingPhotos = $pQuery->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= htmlspecialchars($settings['company_name']) ?></title>
    
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
            min-height: 100vh;
            padding: 14px 12px 60px 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .flow-card {
            max-width: 560px;
            width: 100%;
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: 24px 20px;
            box-shadow: var(--shadow-card);
        }
        .flow-header {
            text-align: center;
            margin-bottom: 18px;
        }
        .flow-badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 6px;
        }
        .timer-box {
            background: rgba(14, 165, 233, 0.12);
            border: 1px solid var(--accent-cyan);
            border-radius: var(--radius-md);
            padding: 14px;
            text-align: center;
            margin-bottom: 18px;
        }
        .checklist-group {
            background: var(--bg-card-hover);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 12px 14px;
            margin-bottom: 10px;
        }
        .checklist-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 6px;
        }
        .rating-pills {
            display: flex;
            gap: 8px;
        }
        .rating-pill {
            flex: 1;
            text-align: center;
            padding: 6px 4px;
            font-size: 12px;
            font-weight: 700;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-subtle);
            cursor: pointer;
            background: var(--bg-card);
            color: var(--text-muted);
            transition: all 0.15s ease;
        }
        .rating-pill input {
            display: none;
        }
        .rating-pill.selected-ok {
            background: rgba(16, 185, 129, 0.2);
            border-color: var(--accent-emerald);
            color: var(--accent-emerald);
        }
        .rating-pill.selected-issue {
            background: rgba(239, 68, 68, 0.2);
            border-color: var(--accent-rose);
            color: var(--accent-rose);
        }
        .rating-pill.selected-na {
            background: rgba(148, 163, 184, 0.2);
            border-color: var(--text-dim);
            color: var(--text-muted);
        }
        .char-counter {
            font-size: 11.5px;
            font-weight: 600;
            margin-top: 4px;
            display: flex;
            justify-content: space-between;
        }
    
    /* Mobile Defensive Checklist CSS */
    * { box-sizing: border-box; }
    img, svg { max-width: 100%; height: auto; }
    .rating-pills {
        display: flex;
        gap: 8px;
        width: 100%;
        margin-top: 6px;
    }
    .rating-pill {
        flex: 1 1 0;
        text-align: center;
        padding: 10px 8px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 42px;
        touch-action: manipulation;
    }
    .checklist-group {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        padding: 12px;
        margin-bottom: 12px;
    }
    .checklist-title {
        font-size: 13px;
        font-weight: 700;
        color: var(--text-main);
        margin-bottom: 6px;
    }
    
</style>
</head>
<body>

<div class="flow-card">

    <?php if ($confirmedVisit): ?>
        <!-- ==================== VISIT CONFIRMATION SCREEN ==================== -->
        <div class="flow-header">
            <span class="flow-badge" style="background: rgba(16, 185, 129, 0.2); color: var(--accent-emerald);">Inspection Complete</span>
            <h2 style="font-size: 22px; font-weight: 900; margin-top: 4px;">Time Out Confirmed</h2>
            <div style="font-size: 13px; color: var(--text-muted); margin-top: 2px;">
                Reference: <strong style="color: var(--accent-cyan); font-family: monospace;"><?= htmlspecialchars($confirmedVisit['visit_ref']) ?></strong>
            </div>
        </div>

        <div style="background: var(--bg-card-hover); border: 2px solid var(--accent-emerald); border-radius: var(--radius-md); padding: 18px; text-align: center; margin-bottom: 20px;">
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Site Visited</div>
            <div style="font-size: 19px; font-weight: 900; color: var(--text-main); margin-top: 2px;">
                <?= htmlspecialchars($confirmedVisit['site_name']) ?>
            </div>
            
            <div style="margin: 14px 0; border-top: 1px dashed var(--border-subtle); padding-top: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                <div>
                    <div style="font-size: 11px; color: var(--text-dim); text-transform: uppercase;">Duration</div>
                    <div style="font-size: 24px; font-weight: 900; color: var(--accent-emerald); font-family: monospace;">
                        <?= (int)$confirmedVisit['minutes_spent'] ?> mins
                    </div>
                </div>
                <div>
                    <div style="font-size: 11px; color: var(--text-dim); text-transform: uppercase;">Time Out</div>
                    <div style="font-size: 20px; font-weight: 800; color: var(--text-main); font-family: monospace; margin-top: 3px;">
                        <?= date('h:i A', strtotime($confirmedVisit['time_out'])) ?>
                    </div>
                </div>
            </div>

            <div style="font-size: 12.5px; color: var(--text-muted); text-align: left; background: var(--bg-card); padding: 10px; border-radius: var(--radius-sm); margin-top: 8px;">
                <strong>Remarks Logged:</strong> <?= htmlspecialchars($confirmedVisit['remarks']) ?>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px;">
            <a href="<?= APP_URL ?>/sup/duty.php" class="btn btn-primary" style="padding: 13px; text-align: center; font-weight: 800; font-size: 15px;">
                ✓ Continue Patrol Duty
            </a>
            <a href="<?= APP_URL ?>/g/submit.php?site_id=<?= $confirmedVisit['site_id'] ?>" class="btn btn-outline" style="padding: 11px; text-align: center; font-size: 13px; color: var(--accent-rose); border-color: var(--accent-rose);">
                🚨 Report Specific Incident at This Site
            </a>
        </div>

    <?php elseif ($error): ?>
        <div class="flow-header">
            <span class="flow-badge" style="background: rgba(239, 68, 68, 0.2); color: var(--accent-rose);">Action Blocked</span>
            <h2 style="font-size: 18px; margin-top: 6px;">Patrol Notice</h2>
        </div>
        <div class="alert alert-danger" style="margin-bottom: 20px; font-size: 13.5px; line-height: 1.5;">
            <?= htmlspecialchars($error) ?>
        </div>
        <a href="<?= APP_URL ?>/sup/duty.php" class="btn btn-primary" style="width: 100%; text-align: center; padding: 12px; margin-bottom: 10px;">
            Go to Active Duty Cockpit
        </a>
        <?php if (!empty($siteData)): ?>
        <a href="<?= APP_URL ?>/g/submit.php?site_id=<?= $siteData['id'] ?>" class="btn btn-secondary" style="width: 100%; text-align: center; padding: 12px; font-weight: 700; background: #0284c7; color: #fff; text-decoration: none; border-radius: 8px; display: block;">
            🚨 Open Incident Report Form for <?= htmlspecialchars($siteData['name']) ?>
        </a>
        <?php endif; ?>

    <?php elseif ($campData): ?>
        <!-- ==================== CAMP CHECKPOINT ==================== -->
        <?php if (!$activeDuty): ?>
            <!-- START DUTY (CAMP OUT) -->
            <div class="flow-header">
                <span class="flow-badge" style="background: rgba(16, 185, 129, 0.2); color: var(--accent-emerald);">Camp Checkpoint</span>
                <h2 style="font-size: 20px; font-weight: 800; margin-top: 4px;"><?= htmlspecialchars($campData['camp_name']) ?></h2>
                <div style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">Code: <?= htmlspecialchars($campData['camp_code']) ?></div>
            </div>

            <div class="timer-box">
                <div style="font-size: 12px; color: var(--accent-cyan); font-weight: 700; text-transform: uppercase;">Dubai Server Authority Time</div>
                <div style="font-size: 26px; font-weight: 800; color: var(--text-main); margin-top: 4px; font-family: monospace;">
                    <?= date('h:i:s A') ?>
                </div>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                    <?= date('l, d M Y') ?>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="action_camp_out" value="1">
                <input type="hidden" name="camp_id" value="<?= $campData['id'] ?>">

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 700;">Select Patrol Shift</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; background: var(--bg-card-hover); padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); cursor: pointer;">
                            <input type="radio" name="shift_type" value="Day" checked>
                            <strong>☀️ Day Shift</strong>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; background: var(--bg-card-hover); padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); cursor: pointer;">
                            <input type="radio" name="shift_type" value="Night">
                            <strong>🌙 Night Shift</strong>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 15px; font-weight: 800;">
                    🚪 Confirm Camp Out & Start Patrol
                </button>
            </form>

        <?php else: ?>
            <!-- END DUTY (CAMP IN) -->
            <div class="flow-header">
                <span class="flow-badge" style="background: rgba(99, 102, 241, 0.2); color: var(--accent-indigo);">Camp Checkpoint Return</span>
                <h2 style="font-size: 20px; font-weight: 800; margin-top: 4px;"><?= htmlspecialchars($campData['camp_name']) ?></h2>
                <div style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">Duty Ref: <?= htmlspecialchars($activeDuty['duty_ref']) ?></div>
            </div>

            <?php
            $sStmt = $db->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(minutes_spent), 0) as mins FROM supervisor_visits WHERE duty_id = ? AND status = 'Completed'");
            $sStmt->execute([$activeDuty['id']]);
            $sum = $sStmt->fetch();
            $hours = floor($sum['mins'] / 60);
            $mins = $sum['mins'] % 60;
            ?>

            <div style="background: var(--bg-card-hover); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 16px; margin-bottom: 20px;">
                <div style="font-size: 12px; color: var(--accent-emerald); font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Shift Patrol Summary</div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span style="color: var(--text-muted);">Camp Out Time:</span>
                    <strong><?= date('h:i A', strtotime($activeDuty['camp_out_time'])) ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span style="color: var(--text-muted);">Sites Inspected:</span>
                    <strong style="color: var(--accent-cyan);"><?= (int)$sum['cnt'] ?> Sites</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">Total Time on Sites:</span>
                    <strong style="color: var(--accent-emerald);"><?= $hours > 0 ? "{$hours}h {$mins}m" : "{$mins} mins" ?></strong>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="action_camp_in" value="1">
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 15px; font-weight: 800; background: var(--accent-emerald);">
                    🏁 Confirm Camp In & Complete Duty
                </button>
            </form>
        <?php endif; ?>

    <?php elseif ($openVisit): ?>
        <!-- ==================== MANDATORY REMARKS & SITE VISIT REPORT FORM ==================== -->
        <div class="flow-header">
            <span class="flow-badge" style="background: rgba(245, 158, 11, 0.2); color: var(--accent-amber);">Site Inspection Completion</span>
            <h2 style="font-size: 20px; font-weight: 800; margin-top: 4px;"><?= htmlspecialchars($openVisit['site_name']) ?></h2>
            <div style="font-size: 13px; color: var(--text-muted); margin-top: 2px;">
                Site Code: <strong><?= htmlspecialchars($openVisit['site_code']) ?></strong> &bull; Ref: <code style="color: var(--accent-cyan);"><?= htmlspecialchars($openVisit['visit_ref']) ?></code>
            </div>
        </div>

        <?php if ($successMsg): ?>
            <div class="alert alert-success" style="margin-bottom: 14px; font-size: 13px;">
                <?= htmlspecialchars($successMsg) ?>
            </div>
        <?php endif; ?>

        <?php
        $timeInTs = strtotime($openVisit['time_in']);
        $nowTs = time();
        $liveMins = max(1, round(($nowTs - $timeInTs) / 60));
        ?>

        <div class="timer-box">
            <div style="font-size: 11.5px; color: var(--text-muted);">Checked In at: <strong><?= date('h:i:s A', $timeInTs) ?></strong></div>
            <div style="font-size: 28px; font-weight: 800; color: var(--accent-amber); margin: 4px 0; font-family: monospace;">
                <span id="displayMins"><?= $liveMins ?></span> Minutes
            </div>
            <div style="font-size: 11.5px; color: var(--text-dim);">Time Out will be clocked as of current Dubai timestamp</div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="timeOutForm">
            <input type="hidden" name="visit_id" value="<?= $openVisit['id'] ?>">

            <!-- 1. MANDATORY REMARKS -->
            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" style="display: flex; justify-content: space-between; align-items: baseline;">
                    <span>Inspection Remarks <span style="color: var(--accent-rose);">* (Mandatory)</span></span>
                    <span style="font-size: 11px; color: var(--text-dim);">Plain text</span>
                </label>
                <textarea name="remarks" id="visitRemarks" class="form-control" rows="3" required placeholder="Type your visit remarks, observations, client feedback, actions taken..." style="font-size: 13.5px;"><?= htmlspecialchars($openVisit['remarks'] ?? '') ?></textarea>
                <div class="char-counter">
                    <span id="charCountLabel" style="color: var(--accent-rose);">0 / <?= $minRemarksLen ?> characters required</span>
                    <span id="charStatusMsg" style="color: var(--accent-rose);">Too short</span>
                </div>
            </div>

            <!-- 2. SITE VISIT REPORT (STRUCTURED FIELDS) -->
            <div style="background: var(--bg-card-hover); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 14px; margin-bottom: 16px;">
                <div style="font-size: 12px; font-weight: 800; color: var(--accent-cyan); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; display: flex; justify-content: space-between;">
                    <span>Site Visit Report</span>
                    <span style="color: var(--text-muted); font-size: 11px; font-weight: normal;">Required for Time Out</span>
                </div>

                <!-- Purpose of Visit -->
                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label" style="font-size: 12px;">Purpose of Visit</label>
                    <select name="purpose_of_visit" class="form-control" style="font-size: 13px;">
                        <?php 
                        $purposes = ['Routine', 'Surprise', 'Follow-up', 'Complaint', 'Client meeting', 'Other'];
                        $curPurp = $openVisit['purpose_of_visit'] ?: 'Routine';
                        foreach ($purposes as $p): ?>
                            <option value="<?= $p ?>" <?= $curPurp === $p ? 'selected' : '' ?>><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Guards on duty & attendance -->
                <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 10px; margin-bottom: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 12px;">Guards on Duty</label>
                        <input type="number" name="guards_count" class="form-control" min="0" value="<?= (int)($openVisit['guards_count'] ?? 1) ?>" style="font-size: 13px;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 12px;">Attendance Status</label>
                        <select name="guards_attendance" class="form-control" style="font-size: 13px;">
                            <?php 
                            $attOptions = ['All present', 'Absent', 'Late'];
                            $curAtt = $openVisit['guards_attendance'] ?: 'All present';
                            foreach ($attOptions as $a): ?>
                                <option value="<?= $a ?>" <?= $curAtt === $a ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label" style="font-size: 12px;">Guard Names (Optional)</label>
                    <input type="text" name="guards_names" class="form-control" placeholder="e.g. Tariq, Shiraz" value="<?= htmlspecialchars($openVisit['guards_names'] ?? '') ?>" style="font-size: 12.5px;">
                </div>

                <!-- Ratings: Turnout, Records, Condition, Equipment -->
                <div style="font-size: 11.5px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px;">Inspection Ratings</div>

                <!-- Turnout and Discipline -->
                <div class="checklist-group">
                    <div class="checklist-title">Turnout & Discipline (Uniform, Grooming, Alertness)</div>
                    <div class="rating-pills">
                        <?php $rt = $openVisit['rating_turnout'] ?: 'OK'; ?>
                        <label class="rating-pill <?= $rt === 'OK' ? 'selected-ok' : '' ?>">
                            <input type="radio" name="rating_turnout" value="OK" <?= $rt === 'OK' ? 'checked' : '' ?> onchange="updatePillStyles()"> OK
                        </label>
                        <label class="rating-pill <?= $rt === 'Issue' ? 'selected-issue' : '' ?>">
                            <input type="radio" name="rating_turnout" value="Issue" <?= $rt === 'Issue' ? 'checked' : '' ?> onchange="updatePillStyles()"> Issue
                        </label>
                        <label class="rating-pill <?= $rt === 'N/A' ? 'selected-na' : '' ?>">
                            <input type="radio" name="rating_turnout" value="N/A" <?= $rt === 'N/A' ? 'checked' : '' ?> onchange="updatePillStyles()"> N/A
                        </label>
                    </div>
                </div>

                <!-- Post and records -->
                <div class="checklist-group">
                    <div class="checklist-title">Post & Records (Logbook, Registers, Handover)</div>
                    <div class="rating-pills">
                        <?php $rr = $openVisit['rating_records'] ?: 'OK'; ?>
                        <label class="rating-pill <?= $rr === 'OK' ? 'selected-ok' : '' ?>">
                            <input type="radio" name="rating_records" value="OK" <?= $rr === 'OK' ? 'checked' : '' ?> onchange="updatePillStyles()"> OK
                        </label>
                        <label class="rating-pill <?= $rr === 'Issue' ? 'selected-issue' : '' ?>">
                            <input type="radio" name="rating_records" value="Issue" <?= $rr === 'Issue' ? 'checked' : '' ?> onchange="updatePillStyles()"> Issue
                        </label>
                        <label class="rating-pill <?= $rr === 'N/A' ? 'selected-na' : '' ?>">
                            <input type="radio" name="rating_records" value="N/A" <?= $rr === 'N/A' ? 'checked' : '' ?> onchange="updatePillStyles()"> N/A
                        </label>
                    </div>
                </div>

                <!-- Site condition and safety -->
                <div class="checklist-group">
                    <div class="checklist-title">Site Condition & Safety (Gates, CCTV, Lights, Housekeeping)</div>
                    <div class="rating-pills">
                        <?php $rc = $openVisit['rating_condition'] ?: 'OK'; ?>
                        <label class="rating-pill <?= $rc === 'OK' ? 'selected-ok' : '' ?>">
                            <input type="radio" name="rating_condition" value="OK" <?= $rc === 'OK' ? 'checked' : '' ?> onchange="updatePillStyles()"> OK
                        </label>
                        <label class="rating-pill <?= $rc === 'Issue' ? 'selected-issue' : '' ?>">
                            <input type="radio" name="rating_condition" value="Issue" <?= $rc === 'Issue' ? 'checked' : '' ?> onchange="updatePillStyles()"> Issue
                        </label>
                        <label class="rating-pill <?= $rc === 'N/A' ? 'selected-na' : '' ?>">
                            <input type="radio" name="rating_condition" value="N/A" <?= $rc === 'N/A' ? 'checked' : '' ?> onchange="updatePillStyles()"> N/A
                        </label>
                    </div>
                </div>

                <!-- Equipment -->
                <div class="checklist-group">
                    <div class="checklist-title">Equipment (Radio, Torch, Mobile, Keys)</div>
                    <div class="rating-pills">
                        <?php $re = $openVisit['rating_equipment'] ?: 'OK'; ?>
                        <label class="rating-pill <?= $re === 'OK' ? 'selected-ok' : '' ?>">
                            <input type="radio" name="rating_equipment" value="OK" <?= $re === 'OK' ? 'checked' : '' ?> onchange="updatePillStyles()"> OK
                        </label>
                        <label class="rating-pill <?= $re === 'Issue' ? 'selected-issue' : '' ?>">
                            <input type="radio" name="rating_equipment" value="Issue" <?= $re === 'Issue' ? 'checked' : '' ?> onchange="updatePillStyles()"> Issue
                        </label>
                        <label class="rating-pill <?= $re === 'N/A' ? 'selected-na' : '' ?>">
                            <input type="radio" name="rating_equipment" value="N/A" <?= $re === 'N/A' ? 'checked' : '' ?> onchange="updatePillStyles()"> N/A
                        </label>
                    </div>
                </div>

                <!-- Issues found and action taken (Required if any Issue selected) -->
                <div id="issueActionBox" style="margin-top: 12px; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: var(--radius-sm); padding: 12px; display: <?= $openVisit['has_issues'] ? 'block' : 'none' ?>;">
                    <label class="form-label" style="color: var(--accent-rose); font-size: 12px; font-weight: 700;">
                        Issues Found & Action Taken <span style="color: var(--accent-rose);">* (Required for Issues)</span>
                    </label>
                    <textarea name="issues_action_taken" id="issuesActionField" class="form-control" rows="2" placeholder="Describe the deficiency found and corrective action taken on site..." style="font-size: 12.5px;"><?= htmlspecialchars($openVisit['issues_action_taken'] ?? '') ?></textarea>
                    <span class="form-help" style="color: var(--accent-rose);">Provide specific notes on disciplinary, equipment, or facility defect.</span>
                </div>

                <!-- Photos (Up to 5) -->
                <div style="margin-top: 14px;">
                    <label class="form-label" style="font-size: 12px;">
                        Inspection Photos (Up to 5) <span id="photoRequiredBadge" style="display: <?= $openVisit['has_issues'] ? 'inline' : 'none' ?>; color: var(--accent-rose); font-weight: bold;">* Required for Issues</span>
                    </label>
                    <input type="file" name="photos[]" multiple accept="image/*" capture="environment" class="form-control" style="font-size: 12px;">
                    <span class="form-help">Take or attach photos of post condition, turnout, or noted issues.</span>

                    <?php if (!empty($existingPhotos)): ?>
                        <div style="display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap;">
                            <?php foreach ($existingPhotos as $ph): ?>
                                <img src="<?= APP_URL ?>/<?= htmlspecialchars($ph['file_path']) ?>" alt="Photo" style="width: 55px; height: 55px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-subtle);">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Buttons: Save Draft, Clock Out, Report as Incident -->
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <button type="submit" name="action_time_out" value="1" id="submitTimeOutBtn" class="btn btn-primary" style="padding: 14px; font-size: 15px; font-weight: 800; background: var(--accent-amber); color: #000;">
                    ⏱️ Clock Out & Submit Site Report
                </button>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <button type="submit" name="action_save_draft" value="1" class="btn btn-outline" style="padding: 10px; font-size: 12.5px; font-weight: 700;">
                        💾 Save Draft on Server
                    </button>
                    <a href="<?= APP_URL ?>/g/submit.php?site_id=<?= $openVisit['site_id'] ?>" class="btn btn-outline" style="padding: 10px; font-size: 12.5px; font-weight: 700; color: var(--accent-rose); border-color: var(--accent-rose); text-align: center;">
                        🚨 Report as Incident
                    </a>
                </div>
            </div>
        </form>

    <?php else: ?>
        <div class="flow-header">
            <h2 style="font-size: 18px;">Directing to Patrol Cockpit...</h2>
        </div>
        <script>window.location.href = <?= json_encode(APP_URL . '/sup/duty.php') ?>;</script>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 20px; border-top: 1px solid var(--border-subtle); padding-top: 12px;">
        <a href="<?= APP_URL ?>/sup/duty.php" style="color: var(--text-muted); font-size: 12.5px; text-decoration: none;">
            &larr; Return to Duty Cockpit
        </a>
    </div>

</div>

<script>
const minLen = <?= $minRemarksLen ?>;
const remarksEl = document.getElementById('visitRemarks');
const countLabel = document.getElementById('charCountLabel');
const statusMsg = document.getElementById('charStatusMsg');
const submitBtn = document.getElementById('submitTimeOutBtn');

function validateRemarks() {
    if (!remarksEl) return;
    const len = remarksEl.value.trim().length;
    countLabel.textContent = len + " / " + minLen + " characters required";

    if (len >= minLen) {
        countLabel.style.color = "var(--accent-emerald)";
        statusMsg.style.color = "var(--accent-emerald)";
        statusMsg.textContent = "✓ Minimum length met";
    } else {
        const remaining = minLen - len;
        countLabel.style.color = "var(--accent-rose)";
        statusMsg.style.color = "var(--accent-rose)";
        statusMsg.textContent = remaining + " more character" + (remaining > 1 ? "s" : "") + " needed";
    }
}

if (remarksEl) {
    remarksEl.addEventListener('input', validateRemarks);
    validateRemarks();
}

function updatePillStyles() {
    let hasIssue = false;
    document.querySelectorAll('.rating-pills').forEach(group => {
        group.querySelectorAll('.rating-pill').forEach(pill => {
            const input = pill.querySelector('input');
            pill.classList.remove('selected-ok', 'selected-issue', 'selected-na');
            if (input.checked) {
                if (input.value === 'OK') pill.classList.add('selected-ok');
                if (input.value === 'Issue') {
                    pill.classList.add('selected-issue');
                    hasIssue = true;
                }
                if (input.value === 'N/A') pill.classList.add('selected-na');
            }
        });
    });

    const issueBox = document.getElementById('issueActionBox');
    const photoBadge = document.getElementById('photoRequiredBadge');
    const issuesField = document.getElementById('issuesActionField');

    if (issueBox) {
        issueBox.style.display = hasIssue ? 'block' : 'none';
    }
    if (photoBadge) {
        photoBadge.style.display = hasIssue ? 'inline' : 'none';
    }
    if (issuesField && hasIssue) {
        issuesField.setAttribute('required', 'required');
    } else if (issuesField) {
        issuesField.removeAttribute('required');
    }
}

// Initial pill sync
document.addEventListener('DOMContentLoaded', updatePillStyles);

// Auto-increment display timer every minute
let liveTimerEl = document.getElementById('displayMins');
if (liveTimerEl) {
    setInterval(function() {
        let cur = parseInt(liveTimerEl.textContent) || 0;
        liveTimerEl.textContent = (cur + 1);
    }, 60000);
}
</script>

</body>
</html>
