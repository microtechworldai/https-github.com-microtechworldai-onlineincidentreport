<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/supervisor_visit_service.php';

require_supervisor_auth();
$db = get_db();
$supervisorId = (int)$_SESSION['supervisor_id'];
$settings = get_system_settings();
$policy = json_decode($settings['supervisor_visit_config'] ?? '', true);
if (!is_array($policy)) {
    $policy = [];
}
$minimumRemarksLength = max(1, (int)($settings['supervisor_remarks_min_length'] ?? 20));
$token = trim((string)($_GET['camp'] ?? $_GET['token'] ?? $_GET['qr'] ?? $_POST['token'] ?? ''));
$error = '';
$message = '';
$duplicate = null;
$camp = null;
$site = null;
$duty = null;
$openVisit = null;
$action = '';

if ($token === '') {
    header('Location: ' . APP_URL . '/sup/duty.php');
    exit;
}

$camp = find_camp_by_qr_token($db, $token);
if (!$camp) {
    $site = find_site_by_qr_token($db, $token, true);
}

if (!$camp && !$site) {
    log_audit('SUPERVISOR_SCAN_REJECTED', 'SUPERVISOR', (string)$supervisorId, 'Invalid or revoked QR token.', 'SUPERVISOR', $supervisorId, $_SESSION['supervisor_name'] ?? null);
    http_response_code(404);
    $error = 'This QR code is invalid, revoked, or inactive. Contact the Super Admin.';
} else {
    list($duty, $openVisit) = get_supervisor_duty_state($db, $supervisorId);

    $duplicateStmt = $db->prepare("
        SELECT scan_action, server_time, result_json
        FROM supervisor_scan_events
        WHERE supervisor_id = ? AND token_hash = ?
          AND server_time >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
          AND scan_action <> 'Rejected'
        ORDER BY id DESC
        LIMIT 1
    ");
    $duplicateStmt->execute([$supervisorId, hash_qr_token($token)]);
    $duplicate = $duplicateStmt->fetch() ?: null;

    if ($duplicate && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $message = 'This QR was already recorded within the last 60 seconds. Showing the existing scan; no duplicate record was created.';
    } elseif ($camp) {
        if (!$duty) {
            $action = 'Camp Out';
        } elseif ((int)$duty['camp_id'] !== (int)$camp['id']) {
            $error = 'This is not the camp where your current duty started. Return to your assigned camp to end duty.';
        } elseif ($openVisit) {
            $error = 'You are still checked in at ' . $openVisit['site_name'] . '. Time out there first.';
        } else {
            $action = 'Camp In';
        }
    } elseif (!$duty) {
        // Automatically start field supervisor duty from central camp so they can immediately begin site inspection
        $defaultCamp = $db->query("SELECT id FROM camps WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetch();
        $campId = $defaultCamp ? (int)$defaultCamp['id'] : 1;
        $dutyRef = 'DUTY-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $insDuty = $db->prepare("
            INSERT INTO supervisor_duties
                (duty_ref, supervisor_id, camp_id, duty_date, shift_type, camp_out_time, status, total_sites, total_minutes, created_at)
            VALUES (?, ?, ?, CURDATE(), 'Day', NOW(), 'Active', 0, 0, NOW())
        ");
        $insDuty->execute([$dutyRef, $supervisorId, $campId]);
        list($duty, $openVisit) = get_supervisor_duty_state($db, $supervisorId);
        $action = 'Time In';
    } elseif ($openVisit && $openVisit['manual_timeout_status'] === 'Pending') {
        $error = 'A manual Time Out request is pending Super Admin review. Do not start another site visit until it is resolved.';
    } elseif ($openVisit && (int)$openVisit['site_id'] !== (int)$site['id']) {
        $error = 'You are still checked in at ' . $openVisit['site_name'] . '. Time out there first.';
    } elseif ($openVisit) {
        $action = 'Time Out';
    } elseif (!supervisor_can_visit_site($db, $supervisorId, $site['id'])) {
        $error = 'This site is not assigned to your supervisor account. Contact the Super Admin.';
        log_audit('SUPERVISOR_SCAN_UNASSIGNED_SITE', 'SITE', (string)$site['id'], 'Supervisor attempted to scan an unassigned site.', 'SUPERVISOR', $supervisorId, $_SESSION['supervisor_name'] ?? null);
    } else {
        $action = 'Time In';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_action']) && $action !== '') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        $error = 'Your session verification expired. Reload this scan and try again.';
    } else {
        $idempotencyKey = trim((string)($_POST['idempotency_key'] ?? ''));
        if (!preg_match('/^[a-f0-9-]{36}$/i', $idempotencyKey)) {
            $error = 'The scan request could not be verified. Please scan the QR again.';
        } else {
            $receiptStmt = $db->prepare("
                SELECT scan_action, visit_id
                FROM supervisor_scan_events
                WHERE idempotency_key = ? AND supervisor_id = ?
                LIMIT 1
            ");
            $receiptStmt->execute([$idempotencyKey, $supervisorId]);
            $receipt = $receiptStmt->fetch();
            if ($receipt) {
                if ($receipt['scan_action'] === 'Camp In') {
                    header('Location: ' . APP_URL . '/sup/login.php?msg=duty_completed');
                } elseif ($receipt['scan_action'] === 'Time Out' && !empty($receipt['visit_id'])) {
                    header('Location: ' . APP_URL . '/sup/index.php?confirmed=' . (int)$receipt['visit_id']);
                } else {
                    header('Location: ' . APP_URL . '/sup/duty.php?msg=scan_recorded');
                }
                exit;
            }

            $uploaded = [];
            $db->beginTransaction();
            try {
                $lockStmt = $db->prepare('SELECT id FROM guards WHERE id = ? FOR UPDATE');
                $lockStmt->execute([$supervisorId]);
                list($duty, $openVisit) = get_supervisor_duty_state($db, $supervisorId);

                $postedAction = (string)($_POST['confirm_action'] ?? '');
                if ($postedAction !== $action) {
                    throw new DomainException('Your duty state changed. Reload the QR scan before confirming.');
                }

                $clientTime = trim((string)($_POST['client_time'] ?? ''));
                if ($clientTime !== '' && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $clientTime)) {
                    $clientTime = null;
                }
                $deviceHash = trim((string)($_POST['device_hash'] ?? ''));
                if ($deviceHash !== '' && !preg_match('/^[a-f0-9]{64}$/i', $deviceHash)) {
                    $deviceHash = null;
                }

                if ($action === 'Camp Out') {
                    if ($duty) {
                        throw new DomainException('You already have an active duty.');
                    }
                    $shiftType = in_array($_POST['shift_type'] ?? '', ['Day', 'Night'], true) ? $_POST['shift_type'] : 'Day';
                    $dutyRef = 'DUTY-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
                    $insert = $db->prepare("
                        INSERT INTO supervisor_duties
                            (duty_ref, supervisor_id, camp_id, duty_date, shift_type, camp_out_time,
                             client_camp_out_time, status, total_sites, total_minutes, created_at)
                        VALUES (?, ?, ?, CURDATE(), ?, NOW(), ?, 'Active', 0, 0, NOW())
                    ");
                    $insert->execute([$dutyRef, $supervisorId, $camp['id'], $shiftType, $clientTime]);
                    $dutyId = (int)$db->lastInsertId();
                    supervisor_visit_write_scan_event($db, $idempotencyKey, $supervisorId, $dutyId, null, $token, $action, $clientTime, $deviceHash);
                    log_audit('CAMP_OUT', 'SUPERVISOR_DUTY', (string)$dutyId, ['shift' => $shiftType, 'camp' => $camp['camp_code']], 'SUPERVISOR', $supervisorId, $_SESSION['supervisor_name'] ?? null);
                    $db->commit();
                    header('Location: ' . APP_URL . '/sup/duty.php?msg=duty_started');
                    exit;
                }

                if ($action === 'Camp In') {
                    if (!$duty || $openVisit) {
                        throw new DomainException('Duty cannot end while there is no active duty or an open site visit.');
                    }
                    $totals = $db->prepare("
                        SELECT COUNT(*) AS visit_count, COALESCE(SUM(minutes_spent), 0) AS minutes
                        FROM supervisor_visits
                        WHERE duty_id = ? AND status = 'Completed'
                    ");
                    $totals->execute([$duty['id']]);
                    $sum = $totals->fetch();
                    $update = $db->prepare("
                        UPDATE supervisor_duties
                        SET camp_in_time = NOW(), client_camp_in_time = ?, status = 'Completed',
                            total_sites = ?, total_minutes = ?, updated_at = NOW()
                        WHERE id = ? AND status = 'Active'
                    ");
                    $update->execute([$clientTime, $sum['visit_count'], $sum['minutes'], $duty['id']]);
                    supervisor_visit_write_scan_event($db, $idempotencyKey, $supervisorId, $duty['id'], null, $token, $action, $clientTime, $deviceHash);
                    log_audit('CAMP_IN', 'SUPERVISOR_DUTY', (string)$duty['id'], ['sites' => (int)$sum['visit_count'], 'minutes' => (int)$sum['minutes']], 'SUPERVISOR', $supervisorId, $_SESSION['supervisor_name'] ?? null);
                    $db->commit();
                    destroy_supervisor_session();
                    header('Location: ' . APP_URL . '/sup/login.php?msg=duty_completed');
                    exit;
                }

                if (!$duty || !$site || !supervisor_can_visit_site($db, $supervisorId, $site['id'])) {
                    throw new DomainException('The site is not currently available to your supervisor account.');
                }

                if ($action === 'Time In') {
                    if ($openVisit) {
                        throw new DomainException('You are still checked in at ' . $openVisit['site_name'] . '. Time out there first.');
                    }
                    $last = $db->prepare("
                        SELECT time_out FROM supervisor_visits
                        WHERE duty_id = ? AND status = 'Completed'
                        ORDER BY time_out DESC LIMIT 1
                    ");
                    $last->execute([$duty['id']]);
                    $lastTimeOut = $last->fetchColumn();
                    $travelMinutes = $lastTimeOut
                        ? max(0, (int)floor((time() - strtotime($lastTimeOut)) / 60))
                        : 0;
                    $visitRef = generate_visit_ref($site['code']);
                    $insert = $db->prepare("
                        INSERT INTO supervisor_visits
                            (visit_ref, duty_id, supervisor_id, site_id, time_in, client_scan_time,
                             travel_minutes_from_previous, status, is_draft, created_at)
                        VALUES (?, ?, ?, ?, NOW(), ?, ?, 'In Progress', 0, NOW())
                    ");
                    $insert->execute([$visitRef, $duty['id'], $supervisorId, $site['id'], $clientTime, $travelMinutes]);
                    $visitId = (int)$db->lastInsertId();
                    supervisor_visit_write_scan_event($db, $idempotencyKey, $supervisorId, $duty['id'], $visitId, $token, $action, $clientTime, $deviceHash);
                    log_audit('SITE_TIME_IN', 'SUPERVISOR_VISIT', (string)$visitId, ['site' => $site['code'], 'ref' => $visitRef], 'SUPERVISOR', $supervisorId, $_SESSION['supervisor_name'] ?? null);
                    $db->commit();
                    header('Location: ' . APP_URL . '/sup/duty.php?msg=time_in_recorded&site=' . urlencode($site['name']));
                    exit;
                }

                if ($action === 'Time Out') {
                    // Process 22-item checklist
                    $checklistResult = process_supervisor_checklist_input($_POST);
                    if (!empty($checklistResult['errors'])) {
                        throw new DomainException(implode(' ', $checklistResult['errors']));
                    }

                    // Auto-populate legacy ratings if not explicitly set
                    if (empty($_POST['rating_turnout'])) {
                        $_POST['rating_turnout'] = in_array('No', [$_POST['chk_q_1']??'', $_POST['chk_q_2']??'', $_POST['chk_q_3']??'', $_POST['chk_q_5']??'', $_POST['chk_q_18']??'', $_POST['chk_q_19']??''], true) ? 'Issue' : 'OK';
                    }
                    if (empty($_POST['rating_records'])) {
                        $_POST['rating_records'] = in_array('No', [$_POST['chk_q_4']??'', $_POST['chk_q_6']??'', $_POST['chk_q_7']??'', $_POST['chk_q_10']??'', $_POST['chk_q_11']??'', $_POST['chk_q_16']??'', $_POST['chk_q_17']??'', $_POST['chk_q_20']??'', $_POST['chk_q_21']??'', $_POST['chk_q_22']??''], true) ? 'Issue' : 'OK';
                    }
                    if (empty($_POST['rating_condition'])) {
                        $_POST['rating_condition'] = in_array('No', [$_POST['chk_q_8']??'', $_POST['chk_q_9']??'', $_POST['chk_q_13']??'', $_POST['chk_q_14']??'', $_POST['chk_q_15']??''], true) ? 'Issue' : 'OK';
                    }
                    if (empty($_POST['rating_equipment'])) {
                        $_POST['rating_equipment'] = (($_POST['chk_q_12'] ?? '') === 'No') ? 'Issue' : 'OK';
                    }
                    if (empty($_POST['purpose_of_visit'])) {
                        $_POST['purpose_of_visit'] = 'Routine';
                    }
                    if (empty($_POST['guards_attendance'])) {
                        $_POST['guards_attendance'] = 'All present';
                    }

                    $validationError = validate_supervisor_visit_report($_POST, $minimumRemarksLength);
                    if ($validationError !== null) {
                        throw new DomainException($validationError);
                    }
                    if (!$openVisit || (int)$openVisit['site_id'] !== (int)$site['id']) {
                        throw new DomainException('The open visit no longer matches this site. Reload the scan.');
                    }
                    $ratings = [
                        $_POST['rating_turnout'], $_POST['rating_records'],
                        $_POST['rating_condition'], $_POST['rating_equipment']
                    ];
                    $hasIssue = in_array('Issue', $ratings, true) || ($checklistResult['no'] > 0);
                    $issueAction = trim((string)($_POST['issues_action_taken'] ?? ''));
                    $assignee = trim((string)($_POST['corrective_action_assignee'] ?? ''));
                    $dueDate = !empty($_POST['corrective_action_due_date']) ? $_POST['corrective_action_due_date'] : null;
                    $actionStatus = in_array($_POST['corrective_action_status'] ?? '', ['Pending', 'In Progress', 'Resolved'], true) ? $_POST['corrective_action_status'] : 'Pending';
                    $clientRepName = trim((string)($_POST['client_rep_name'] ?? ''));

                    $photoFiles = $_FILES['photos'] ?? null;
                    $photoCaptions = $_POST['photo_captions'] ?? $_POST['photo_comments'] ?? [];
                    $photoCount = is_array($photoFiles['name'] ?? null)
                        ? count(array_filter($photoFiles['name']))
                        : 0;
                    $existingPhotos = $db->prepare('SELECT COUNT(*) FROM supervisor_visit_photos WHERE visit_id = ?');
                    $existingPhotos->execute([$openVisit['id']]);
                    $photoTotal = (int)$existingPhotos->fetchColumn() + $photoCount;
                    $photosRequiredOnIssue = (bool)($policy['photos_required_on_issue'] ?? true);
                    if ($photoCount > 5 || ($hasIssue && $photosRequiredOnIssue && $photoTotal === 0)) {
                        throw new DomainException($photoCount > 5
                            ? 'A maximum of 5 photos may be attached to one visit.'
                            : 'Attach at least one photo when an inspection deficiency or issue is reported.');
                    }

                    $siteReport = [
                        'purpose_of_visit' => $_POST['purpose_of_visit'],
                        'guards_count' => max(0, (int)($_POST['guards_count'] ?? 0)),
                        'guards_attendance' => $_POST['guards_attendance'],
                        'guards_names' => trim((string)($_POST['guards_names'] ?? '')),
                        'rating_turnout' => $_POST['rating_turnout'],
                        'rating_records' => $_POST['rating_records'],
                        'rating_condition' => $_POST['rating_condition'],
                        'rating_equipment' => $_POST['rating_equipment'],
                        'issues_action_taken' => $issueAction,
                        'client_rep_name' => $clientRepName,
                        'checklist_summary' => $checklistResult['summary'],
                        'corrective_action_assignee' => $assignee,
                        'corrective_action_due_date' => $dueDate,
                        'corrective_action_status' => $actionStatus
                    ];
                    $update = $db->prepare("
                        UPDATE supervisor_visits
                        SET time_out = NOW(), minutes_spent = GREATEST(1, TIMESTAMPDIFF(MINUTE, time_in, NOW())),
                            remarks = ?, purpose_of_visit = ?, guards_count = ?, guards_attendance = ?, guards_names = ?,
                            rating_turnout = ?, rating_records = ?, rating_condition = ?, rating_equipment = ?,
                            has_issues = ?, issues_action_taken = ?, site_report = ?, is_draft = 0,
                            is_offline_sync = 0, status = 'Completed', updated_at = NOW(),
                            client_rep_name = ?,
                            checklist_answers = ?,
                            checklist_yes_count = ?,
                            checklist_no_count = ?,
                            checklist_na_count = ?,
                            checklist_unanswered_count = ?,
                            checklist_summary = ?,
                            corrective_action_assignee = ?,
                            corrective_action_due_date = ?,
                            corrective_action_status = ?
                        WHERE id = ? AND status = 'In Progress'
                    ");
                    $update->execute([
                        trim((string)$_POST['remarks']),
                        $siteReport['purpose_of_visit'],
                        $siteReport['guards_count'],
                        $siteReport['guards_attendance'],
                        $siteReport['guards_names'],
                        $siteReport['rating_turnout'],
                        $siteReport['rating_records'],
                        $siteReport['rating_condition'],
                        $siteReport['rating_equipment'],
                        $hasIssue ? 1 : 0,
                        $issueAction,
                        json_encode($siteReport, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        $clientRepName,
                        json_encode($checklistResult['answers'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        $checklistResult['yes'],
                        $checklistResult['no'],
                        $checklistResult['na'],
                        $checklistResult['unanswered'],
                        $checklistResult['summary'],
                        $assignee,
                        $dueDate,
                        $actionStatus,
                        $openVisit['id']
                    ]);
                    if ($update->rowCount() !== 1) {
                        throw new DomainException('This visit was already completed or changed. Refresh the page.');
                    }

                    if ($photoCount > 0) {
                        for ($i = 0; $i < count($photoFiles['name']); $i++) {
                            if ($photoFiles['error'][$i] === UPLOAD_ERR_NO_FILE) {
                                continue;
                            }
                            if ($photoFiles['error'][$i] !== UPLOAD_ERR_OK) {
                                throw new RuntimeException('A visit photo could not be uploaded. Please retry.');
                            }
                            $result = process_and_save_photo($photoFiles['tmp_name'][$i], $photoFiles['name'][$i]);
                            if (!$result) {
                                throw new RuntimeException('A visit photo was rejected. Use a supported image under the upload limit.');
                            }
                            $uploaded[] = $result['relative_path'];
                            $caption = trim((string)($photoCaptions[$i] ?? ''));
                            $photoInsert = $db->prepare("
                                INSERT INTO supervisor_visit_photos
                                    (visit_id, file_path, original_filename, file_size, caption, created_at)
                                VALUES (?, ?, ?, ?, ?, NOW())
                            ");
                            $photoInsert->execute([
                                $openVisit['id'],
                                $result['relative_path'],
                                basename($photoFiles['name'][$i]),
                                $result['size'],
                                $caption
                            ]);
                        }
                    }

                    // Alerts to Operations Manager for critical findings
                    if ($checklistResult['no'] > 0 || $hasIssue) {
                        $deficiencies = [];
                        foreach ($checklistResult['answers'] as $ans) {
                            if ($ans['answer'] === 'No') {
                                $deficiencies[] = "#{$ans['id']} {$ans['question']}" . ($ans['remarks'] ? ": {$ans['remarks']}" : '');
                            }
                        }
                        $supLabel = ($_SESSION['supervisor_name'] ?? 'Supervisor') . " (" . ($_SESSION['supervisor_staff_id'] ?? 'SUP') . ")";
                        $alertDetails = "Critical deficiency logged during site inspection at {$site['name']} by {$supLabel}:
"
                            . implode("
", $deficiencies)
                            . ($issueAction ? "
Corrective Action: {$issueAction}" : "")
                            . ($assignee ? "
Assignee: {$assignee} (Due: " . ($dueDate ?: 'Immediate') . ")" : "");

                        $alertStmt = $db->prepare("
                            INSERT INTO supervisor_alerts
                                (supervisor_id, duty_id, visit_id, site_id, alert_type, severity, details, is_acknowledged, created_at)
                            VALUES (?, ?, ?, ?, 'CHECKLIST_DEFICIENCY', 'Critical', ?, 0, NOW())
                        ");
                        $alertStmt->execute([
                            $supervisorId,
                            $duty['id'],
                            $openVisit['id'],
                            $site['id'],
                            $alertDetails
                        ]);
                    }

                    $duration = $db->prepare('SELECT minutes_spent FROM supervisor_visits WHERE id = ?');
                    $duration->execute([$openVisit['id']]);
                    $minutesSpent = (int)$duration->fetchColumn();
                    $db->prepare("
                        UPDATE supervisor_duties
                        SET total_sites = total_sites + 1, total_minutes = total_minutes + ?, updated_at = NOW()
                        WHERE id = ?
                    ")->execute([$minutesSpent, $duty['id']]);
                    supervisor_visit_write_scan_event($db, $idempotencyKey, $supervisorId, $duty['id'], $openVisit['id'], $token, $action, $clientTime, $deviceHash);
                    log_audit('SITE_TIME_OUT', 'SUPERVISOR_VISIT', (string)$openVisit['id'], [
                        'minutes' => $minutesSpent,
                        'site' => $site['code'],
                        'checklist' => $checklistResult['summary'],
                        'client_rep' => $clientRepName
                    ], 'SUPERVISOR', $supervisorId, $_SESSION['supervisor_name'] ?? null);
                    $db->commit();
                    header('Location: ' . APP_URL . '/sup/index.php?confirmed=' . (int)$openVisit['id']);
                    exit;
                }
                throw new DomainException('Unsupported supervisor scan action.');
            } catch (DomainException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                cleanup_supervisor_scan_uploads($uploaded);
                $error = $e->getMessage();
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                cleanup_supervisor_scan_uploads($uploaded);
                error_log('Supervisor scan failed: ' . $e->getMessage());
                $error = 'The scan could not be saved. No action was confirmed. Please retry or contact the Super Admin.';
            }
        }
    }
}

$serverTime = date('Y-m-d H:i:s');
$idempotencyKey = bin2hex(random_bytes(16));
$idempotencyKey = substr($idempotencyKey, 0, 8) . '-' . substr($idempotencyKey, 8, 4) . '-' . substr($idempotencyKey, 12, 4) . '-' . substr($idempotencyKey, 16, 4) . '-' . substr($idempotencyKey, 20, 12);
$photos = [];
if ($openVisit) {
    $photoStmt = $db->prepare('SELECT * FROM supervisor_visit_photos WHERE visit_id = ? ORDER BY id');
    $photoStmt->execute([$openVisit['id']]);
    $photos = $photoStmt->fetchAll();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirm Supervisor Scan</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <style>
        .site-card {
            background: linear-gradient(135deg, #0b192c 0%, #1e3a5f 100%);
            color: #ffffff;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.15);
        }
        .site-card h3 { margin: 0 0 10px 0; font-size: 16px; color: #38bdf8; font-weight: 800; }
        .site-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 10px 18px;
            font-size: 13px;
        }
        .site-grid div strong { color: #94a3b8; font-size: 11px; text-transform: uppercase; display: block; }
        .site-grid div span { color: #f8fafc; font-weight: 700; font-size: 13.5px; }
        .badge-verified {
            display: inline-block;
            background: #059669;
            color: #ffffff;
            font-size: 10.5px;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 4px;
            margin-left: 4px;
        }
        .sticky-summary {
            position: sticky;
            top: 10px;
            z-index: 99;
            background: #0b192c;
            color: #ffffff;
            border-radius: 10px;
            padding: 12px 18px;
            margin-bottom: 20px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .chk-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 12px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .chk-card.has-error {
            border-color: #ef4444;
            background: #fff5f5;
        }
        .chk-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 10px;
        }
        .chk-title {
            font-size: 13.5px;
            font-weight: 750;
            color: #0f172a;
            line-height: 1.35;
        }
        .chk-pill {
            font-size: 11px;
            font-weight: 800;
            padding: 3px 9px;
            border-radius: 12px;
            white-space: nowrap;
        }
        .pill-unanswered { background: #f1f5f9; color: #64748b; }
        .pill-yes-active { background: #dcfce7; color: #166534; }
        .pill-no-active { background: #fee2e2; color: #991b1b; }
        .pill-na-active { background: #f1f5f9; color: #334155; }
        .opt-group {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        .opt-label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 9px 8px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s ease;
            user-select: none;
            text-align: center;
            color: #334155;
            background: #f8fafc;
        }
        .opt-label input[type="radio"] { display: none; }
        .opt-label.active-yes {
            background: #10b981;
            border-color: #059669;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(16,185,129,0.3);
        }
        .opt-label.active-no {
            background: #ef4444;
            border-color: #dc2626;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(239,68,68,0.3);
        }
        .opt-label.active-na {
            background: #64748b;
            border-color: #475569;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(100,116,139,0.3);
        }
        .section-header-box {
            background: #0b192c;
            color: #ffffff !important;
            font-weight: 800 !important;
            padding: 10px 14px;
            border-radius: 8px;
            margin: 22px 0 14px 0;
            font-size: 13.5px;
            letter-spacing: 0.4px;
        }
        .btn-submit-inspection {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 15px 20px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            width: 100%;
            margin-top: 24px;
            box-shadow: 0 4px 14px rgba(2,132,199,0.35);
            transition: transform 0.1s, box-shadow 0.1s;
        }
        .btn-submit-inspection:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(2,132,199,0.45);
        }
        .photo-slot {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 10px;
        }

        body { margin: 0; padding: 18px; background: #f1f5f9; color: #0f172a; font: 16px/1.45 system-ui, sans-serif; }
        main { max-width: 620px; margin: 0 auto; background: #fff; border-radius: 14px; padding: 22px; box-shadow: 0 8px 32px #0f172a1c; }
        label { display: block; margin: 14px 0 6px; font-weight: 650; }
        input, select, textarea { box-sizing: border-box; width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; }
        button { border: 0; border-radius: 8px; padding: 12px 16px; background: #0284c7; color: white; font: inherit; font-weight: 700; width: 100%; margin-top: 16px; }
        .notice { padding: 12px; margin: 12px 0; border-radius: 8px; background: #e0f2fe; }
        .error { background: #fee2e2; color: #991b1b; }
        .ratings { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
        @media (max-width: 520px) { .ratings { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    </style>
</head>
<body>
<main>
    <h1><?= htmlspecialchars($action ?: 'Supervisor scan') ?></h1>
    <div class="notice">Dubai server time: <strong><?= htmlspecialchars($serverTime) ?></strong></div>
    <?php if ($error): ?><div class="notice error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($message): ?><div class="notice"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($duplicate): ?>
        <div class="notice">Existing action: <strong><?= htmlspecialchars($duplicate['scan_action']) ?></strong> at <?= htmlspecialchars($duplicate['server_time']) ?>. No duplicate was saved.</div>
        <a href="<?= APP_URL ?>/sup/duty.php">Return to duty</a>
    <?php elseif ($action === 'Camp Out' || $action === 'Camp In' || $action === 'Time In'): ?>
        <h2><?= htmlspecialchars($camp['camp_name'] ?? $site['name']) ?></h2>
        <p>Proposed action: <strong><?= htmlspecialchars($action) ?></strong></p>
        <?php if ($action === 'Camp Out'): ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                <input type="hidden" name="idempotency_key" value="<?= htmlspecialchars($idempotencyKey) ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <label for="shift_type">Duty type</label>
                <select id="shift_type" name="shift_type"><option>Day</option><option>Night</option></select>
                <input type="hidden" name="client_time" value="<?= htmlspecialchars($serverTime) ?>">
                    <input type="hidden" name="device_hash" value="">
                <button name="confirm_action" value="Camp Out">Confirm Start Duty (Camp Out)</button>
            </form>
        <?php elseif ($action === 'Camp In'): ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                <input type="hidden" name="idempotency_key" value="<?= htmlspecialchars($idempotencyKey) ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="client_time" value="<?= htmlspecialchars($serverTime) ?>">
                <input type="hidden" name="device_hash" value="">
                <button name="confirm_action" value="Camp In">Confirm End Duty (Camp In)</button>
            </form>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                <input type="hidden" name="idempotency_key" value="<?= htmlspecialchars($idempotencyKey) ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="client_time" value="<?= htmlspecialchars($serverTime) ?>">
                <input type="hidden" name="device_hash" value="">
                <button name="confirm_action" value="Time In">Confirm Time In</button>
            </form>
        <?php endif; ?>
    <?php elseif ($action === 'Time Out' && $openVisit): 
        $questions = get_supervisor_checklist_questions();
        $siteClient = $site['client_name'] ?? 'Danube Properties';
        $siteLoc = $site['address'] ?? 'Dubai, UAE';
        $supName = $_SESSION['supervisor_name'] ?? 'Rashid Al Nuaimi';
        $supStaffId = $_SESSION['supervisor_staff_id'] ?? 'SUP-401';
        $shiftType = $duty['shift_type'] ?? 'Day';
        $dutyDate = $duty['duty_date'] ?? date('Y-m-d');
    ?>
        <div style="text-align: center; margin-bottom: 18px;">
            <div style="font-size: 12px; font-weight: 800; color: #0284c7; text-transform: uppercase; letter-spacing: 1px;">Ultimate Security Services</div>
            <h2 style="margin: 4px 0 0 0; font-size: 20px; font-weight: 850; color: #0f172a;">Field Supervisor – Site Inspection Checklist</h2>
            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">Official UAE Site Patrol &amp; Compliance Audit Record</div>
        </div>

        <!-- 1. Site Visit & Attendance Details Card -->
        <div class="site-card">
            <h3 style="color: #ffffff; font-weight: 800;">1. Site Visit &amp; Attendance Details</h3>
            <div class="site-grid">
                <div>
                    <strong>Site Name &amp; Code</strong>
                    <span><?= htmlspecialchars($site['name']) ?> (<?= htmlspecialchars($site['code']) ?>)</span>
                </div>
                <div>
                    <strong>Client Name &amp; Location</strong>
                    <span><?= htmlspecialchars($siteClient) ?> &bull; <?= htmlspecialchars($siteLoc) ?></span>
                </div>
                <div>
                    <strong>Supervisor Name &amp; ID</strong>
                    <span><?= htmlspecialchars($supName) ?> (<?= htmlspecialchars($supStaffId) ?>)</span>
                </div>
                <div>
                    <strong>Date &amp; Shift</strong>
                    <span><?= date('d/m/Y', strtotime($dutyDate)) ?> &bull; <?= htmlspecialchars($shiftType) ?> Shift</span>
                </div>
                <div>
                    <strong>Site QR Scan — Check-in</strong>
                    <span><?= date('h:i A', strtotime($openVisit['time_in'])) ?> <span class="badge-verified">✓ Arrival Verified</span></span>
                </div>
                <div>
                    <strong>Site QR Scan — Check-out</strong>
                    <span><span id="liveDepClock"><?= date('h:i A') ?></span> <span class="badge-verified">✓ Departure Verified</span></span>
                </div>
                <div>
                    <strong>Total Time Spent at Site</strong>
                    <span id="liveMinutesSpent" style="color: #38bdf8; font-weight: 800;">Calculating...</span>
                </div>
                <div>
                    <strong>Client Representative</strong>
                    <span id="clientRepDisplay" style="color: #cbd5e1; font-style: italic;">Optional on site</span>
                </div>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data" id="inspectionForm" onsubmit="return validateInspectionForm(event)">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
            <input type="hidden" name="idempotency_key" value="<?= htmlspecialchars($idempotencyKey) ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <input type="hidden" name="client_time" value="<?= htmlspecialchars($serverTime) ?>">
            <input type="hidden" name="device_hash" value="">

            <!-- Client Representative Input -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; margin-bottom: 16px;">
                <label for="client_rep_name" style="margin-top: 0; font-size: 13px; font-weight: 750; color: #0f172a;">
                    Client Representative Name (if present on site)
                </label>
                <input type="text" id="client_rep_name" name="client_rep_name" placeholder="Enter client representative name, e.g. Mr. Ahmed (Facility Manager)" value="<?= htmlspecialchars($openVisit['client_rep_name'] ?? '') ?>" oninput="document.getElementById('clientRepDisplay').innerText = this.value.trim() || 'Optional on site';">
            </div>

            <!-- Sticky Live Summary Bar -->
            <div class="sticky-summary" id="checklistStickySummary">
                <span style="font-weight: 850; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #ffffff;">Checklist Summary:</span>
                <div style="display: flex; gap: 12px; font-weight: 800; font-size: 13px;">
                    <span style="color: #4ade80;">Yes <span id="sumYes">0</span></span>
                    <span style="color: #f87171;">No <span id="sumNo">0</span></span>
                    <span style="color: #94a3b8;">N/A <span id="sumNA">0</span></span>
                    <span style="color: #fde047;">Unanswered <span id="sumUnanswered">22</span></span>
                </div>
            </div>

            <!-- 2. Field Supervisor – Site Inspection Checklist (22 Items) -->
            <div class="section-header-box">
                2. Field Supervisor – Site Inspection Checklist (22 Items)
            </div>

            <div id="checklistContainer">
                <?php foreach ($questions as $qId => $qText): ?>
                    <div class="chk-card" id="chk_card_<?= $qId ?>">
                        <div class="chk-header">
                            <div class="chk-title">
                                <span style="color: #0284c7; font-weight: 800;"><?= $qId ?>.</span> <?= htmlspecialchars($qText) ?>:
                            </div>
                            <span class="chk-pill pill-unanswered" id="chk_pill_<?= $qId ?>">Not answered</span>
                        </div>
                        <div class="opt-group">
                            <label class="opt-label" id="lbl_<?= $qId ?>_Yes">
                                <input type="radio" name="chk_q_<?= $qId ?>" value="Yes" onchange="onChkAnswer(<?= $qId ?>, 'Yes')">
                                <span>✓ Yes</span>
                            </label>
                            <label class="opt-label" id="lbl_<?= $qId ?>_No">
                                <input type="radio" name="chk_q_<?= $qId ?>" value="No" onchange="onChkAnswer(<?= $qId ?>, 'No')">
                                <span>✕ No</span>
                            </label>
                            <label class="opt-label" id="lbl_<?= $qId ?>_NA">
                                <input type="radio" name="chk_q_<?= $qId ?>" value="N/A" onchange="onChkAnswer(<?= $qId ?>, 'N/A')">
                                <span>— N/A</span>
                            </label>
                        </div>
                        <div id="rem_box_<?= $qId ?>" style="display: none; margin-top: 10px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 10px;">
                            <label for="chk_rem_<?= $qId ?>" style="color: #dc2626; font-size: 12px; font-weight: 800; margin: 0 0 4px 0;">
                                Mandatory Deficiency Remarks (Required for 'No') *
                            </label>
                            <textarea name="chk_rem_<?= $qId ?>" id="chk_rem_<?= $qId ?>" rows="2" placeholder="Specify deficiency observed, guard actions, and immediate instruction given..."></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- 3. Guard Attendance & Turnout Verification -->
            <div class="section-header-box">
                3. Guard Attendance &amp; Turnout Verification
            </div>

            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 16px;">
                <label>Purpose of visit</label>
                <select name="purpose_of_visit">
                    <?php foreach (['Routine','Surprise','Follow-up','Complaint','Client meeting','Other'] as $option): ?>
                        <option><?= htmlspecialchars($option) ?></option>
                    <?php endforeach; ?>
                </select>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <label>Guards found on duty</label>
                        <input type="number" name="guards_count" min="0" value="<?= (int)($openVisit['guards_count'] ?? 0) ?>">
                    </div>
                    <div>
                        <label>Attendance Status</label>
                        <select name="guards_attendance">
                            <?php foreach (['All present','Absent','Late'] as $option): ?>
                                <option><?= htmlspecialchars($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <label>Guard names on post (optional)</label>
                <textarea name="guards_names" rows="2" placeholder="e.g. Guard John Doe (G-102), Guard Ali Khan (G-108)"><?= htmlspecialchars($openVisit['guards_names'] ?? '') ?></textarea>

                <label>Mandatory Supervisor Overall Inspection Remarks (minimum <?= (int)$minimumRemarksLength ?> characters) *</label>
                <textarea name="remarks" minlength="<?= (int)$minimumRemarksLength ?>" required rows="3" placeholder="Enter comprehensive inspection remarks and observation details..."><?= htmlspecialchars($openVisit['remarks'] ?? '') ?></textarea>
            </div>

            <!-- 4. Corrective-Action Tracking -->
            <div class="section-header-box">
                4. Corrective-Action Tracking
            </div>

            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 16px;">
                <label>Corrective Actions Assigned / Issues Found &amp; Action Taken</label>
                <textarea name="issues_action_taken" id="issues_action_taken" rows="2" placeholder="Describe corrective actions assigned, remedial post instructions, or operations follow-up..."><?= htmlspecialchars($openVisit['issues_action_taken'] ?? '') ?></textarea>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                    <div>
                        <label>Responsible Person / Assignee</label>
                        <input type="text" name="corrective_action_assignee" placeholder="e.g. Lead Guard / Site In-Charge" value="<?= htmlspecialchars($openVisit['corrective_action_assignee'] ?? '') ?>">
                    </div>
                    <div>
                        <label>Due Date</label>
                        <input type="date" name="corrective_action_due_date" value="<?= htmlspecialchars($openVisit['corrective_action_due_date'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div>
                        <label>Action Status</label>
                        <select name="corrective_action_status">
                            <option value="Pending" <?= ($openVisit['corrective_action_status'] ?? '') === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="In Progress" <?= ($openVisit['corrective_action_status'] ?? '') === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="Resolved" <?= ($openVisit['corrective_action_status'] ?? '') === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 5. Photographic Evidence (Up to 5 Photos with Comments) -->
            <div class="section-header-box">
                5. Photographic Evidence (Deficiencies &amp; Site Observations)
            </div>

            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 20px;">
                <p style="margin: 0 0 12px 0; font-size: 12.5px; color: #64748b;">
                    Attach up to 5 photos showing deficiencies, guard turnout, or site checkpoints. Comments can be added to each photo.
                </p>
                <?php for ($pIdx = 1; $pIdx <= 5; $pIdx++): ?>
                    <div class="photo-slot">
                        <label style="margin: 0 0 4px 0; font-size: 12px; color: #334155; font-weight: 700;">
                            Photo #<?= $pIdx ?> (Evidence Image)
                        </label>
                        <input type="file" name="photos[]" accept="image/*" style="padding: 6px; font-size: 12px; margin-bottom: 6px;">
                        <input type="text" name="photo_captions[]" placeholder="Photo #<?= $pIdx ?> comment / deficiency description (optional)" style="font-size: 12.5px; padding: 7px 10px;">
                    </div>
                <?php endfor; ?>
            </div>

            <button type="submit" name="confirm_action" value="Time Out" class="btn-submit-inspection">
                ✓ Confirm Time Out &amp; Submit Inspection Report
            </button>
        </form>

        <script>
        const checkinTimestamp = new Date("<?= date('c', strtotime($openVisit['time_in'])) ?>").getTime();
        function updateDurationClock() {
            const now = new Date();
            const diffMs = Math.max(0, now.getTime() - checkinTimestamp);
            const diffMins = Math.max(1, Math.round(diffMs / 60000));
            const hours = Math.floor(diffMins / 60);
            const remMins = diffMins % 60;
            const str = hours > 0 ? (hours + "h " + remMins + "m (" + diffMins + " mins)") : (diffMins + " mins");
            const minEl = document.getElementById("liveMinutesSpent");
            if (minEl) minEl.innerText = str;

            const pad = n => String(n).padStart(2, "0");
            let h = now.getHours();
            const ampm = h >= 12 ? "PM" : "AM";
            h = h % 12 || 12;
            const clockEl = document.getElementById("liveDepClock");
            if (clockEl) clockEl.innerText = pad(h) + ":" + pad(now.getMinutes()) + " " + ampm;
        }
        setInterval(updateDurationClock, 10000);
        updateDurationClock();

        const answersState = {};
        for (let i = 1; i <= 22; i++) {
            answersState[i] = "Unanswered";
        }

        function onChkAnswer(id, val) {
            answersState[id] = val;
            const card = document.getElementById("chk_card_" + id);
            const pill = document.getElementById("chk_pill_" + id);
            const remBox = document.getElementById("rem_box_" + id);
            const remInput = document.getElementById("chk_rem_" + id);

            if (card) card.classList.remove("has-error");

            // Update label styles
            ["Yes", "No", "NA"].forEach(k => {
                const lbl = document.getElementById("lbl_" + id + "_" + k);
                if (lbl) {
                    lbl.classList.remove("active-yes", "active-no", "active-na");
                }
            });

            if (val === "Yes") {
                const lbl = document.getElementById("lbl_" + id + "_Yes");
                if (lbl) lbl.classList.add("active-yes");
                if (pill) {
                    pill.className = "chk-pill pill-yes-active";
                    pill.innerText = "Yes";
                }
                if (remBox) remBox.style.display = "none";
            } else if (val === "No") {
                const lbl = document.getElementById("lbl_" + id + "_No");
                if (lbl) lbl.classList.add("active-no");
                if (pill) {
                    pill.className = "chk-pill pill-no-active";
                    pill.innerText = "No";
                }
                if (remBox) {
                    remBox.style.display = "block";
                    if (remInput && !remInput.value.trim()) {
                        remInput.focus();
                    }
                }
            } else if (val === "N/A") {
                const lbl = document.getElementById("lbl_" + id + "_NA");
                if (lbl) lbl.classList.add("active-na");
                if (pill) {
                    pill.className = "chk-pill pill-na-active";
                    pill.innerText = "N/A";
                }
                if (remBox) remBox.style.display = "none";
            }

            recalcTally();
        }

        function recalcTally() {
            let yes = 0, no = 0, na = 0, un = 0;
            for (let i = 1; i <= 22; i++) {
                const v = answersState[i];
                if (v === "Yes") yes++;
                else if (v === "No") no++;
                else if (v === "N/A") na++;
                else un++;
            }
            document.getElementById("sumYes").innerText = yes;
            document.getElementById("sumNo").innerText = no;
            document.getElementById("sumNA").innerText = na;
            document.getElementById("sumUnanswered").innerText = un;
        }

        function validateInspectionForm(e) {
            let firstErr = null;
            for (let i = 1; i <= 22; i++) {
                if (answersState[i] === "No") {
                    const remInput = document.getElementById("chk_rem_" + i);
                    if (!remInput || !remInput.value.trim()) {
                        const card = document.getElementById("chk_card_" + i);
                        if (card) card.classList.add("has-error");
                        if (!firstErr) firstErr = { id: i, el: remInput, card: card };
                    }
                }
            }
            if (firstErr) {
                if (e) e.preventDefault();
                alert("Checklist Item " + firstErr.id + ": Mandatory deficiency remarks are required when marked No.");
                if (firstErr.card) {
                    firstErr.card.scrollIntoView({ behavior: "smooth", block: "center" });
                }
                if (firstErr.el) firstErr.el.focus();
                return false;
            }
            return true;
        }
        </script>
    <?php endif; ?>
    <p><a href="<?= APP_URL ?>/sup/duty.php">Return to duty cockpit</a></p>
</main>
<script>
(() => {
    const pad = value => String(value).padStart(2, '0');
    const updateClientTime = form => {
        const now = new Date();
        const localTime = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
        const input = form.querySelector('input[name="client_time"]');
        if (input) input.value = localTime;
    };
    const setDeviceHash = async form => {
        if (!window.crypto || !crypto.subtle || !window.TextEncoder) return;
        let deviceId = '';
        try {
            deviceId = localStorage.getItem('uss_supervisor_device_id') || '';
            if (!deviceId) {
                const bytes = new Uint8Array(16);
                crypto.getRandomValues(bytes);
                deviceId = Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
                localStorage.setItem('uss_supervisor_device_id', deviceId);
            }
        } catch (error) {
            console.warn('Supervisor device metadata is unavailable in this browser.', error);
            return;
        }
        const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(deviceId));
        const bytes = Array.from(new Uint8Array(digest));
        const input = form.querySelector('input[name="device_hash"]');
        if (input) input.value = bytes.map(value => value.toString(16).padStart(2, '0')).join('');
    };
    document.querySelectorAll('form[method="post"]').forEach(form => {
        updateClientTime(form);
        form.addEventListener('submit', async event => {
            event.preventDefault();
            updateClientTime(form);
            try {
                await setDeviceHash(form);
            } catch (error) {
                console.warn('Supervisor device metadata could not be hashed.', error);
            }
            HTMLFormElement.prototype.submit.call(form);
        }, { once: true });
    });
})();
</script>
</body>
</html>
