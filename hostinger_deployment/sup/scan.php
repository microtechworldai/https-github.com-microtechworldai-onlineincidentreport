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
        $error = 'No open duty. Scan your Camp QR and start duty before visiting a site.';
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
                    $hasIssue = in_array('Issue', $ratings, true);
                    $issueAction = trim((string)($_POST['issues_action_taken'] ?? ''));
                    $photoFiles = $_FILES['photos'] ?? null;
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
                            : 'Attach at least one photo when an inspection issue is reported.');
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
                        'issues_action_taken' => $issueAction
                    ];
                    $update = $db->prepare("
                        UPDATE supervisor_visits
                        SET time_out = NOW(), minutes_spent = GREATEST(1, TIMESTAMPDIFF(MINUTE, time_in, NOW())),
                            remarks = ?, purpose_of_visit = ?, guards_count = ?, guards_attendance = ?, guards_names = ?,
                            rating_turnout = ?, rating_records = ?, rating_condition = ?, rating_equipment = ?,
                            has_issues = ?, issues_action_taken = ?, site_report = ?, is_draft = 0,
                            is_offline_sync = 0, status = 'Completed', updated_at = NOW()
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
                            $photoInsert = $db->prepare("
                                INSERT INTO supervisor_visit_photos
                                    (visit_id, file_path, original_filename, file_size, created_at)
                                VALUES (?, ?, ?, ?, NOW())
                            ");
                            $photoInsert->execute([
                                $openVisit['id'],
                                $result['relative_path'],
                                basename($photoFiles['name'][$i]),
                                $result['size']
                            ]);
                        }
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
                    log_audit('SITE_TIME_OUT', 'SUPERVISOR_VISIT', (string)$openVisit['id'], ['minutes' => $minutesSpent, 'site' => $site['code']], 'SUPERVISOR', $supervisorId, $_SESSION['supervisor_name'] ?? null);
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
    <?php elseif ($action === 'Time Out' && $openVisit): ?>
        <h2><?= htmlspecialchars($site['name']) ?></h2>
        <p>Time In: <?= htmlspecialchars($openVisit['time_in']) ?>. Enter the required visit report before confirming Time Out.</p>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
            <input type="hidden" name="idempotency_key" value="<?= htmlspecialchars($idempotencyKey) ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <input type="hidden" name="client_time" value="<?= htmlspecialchars($serverTime) ?>">
            <input type="hidden" name="device_hash" value="">
            <label>Remarks (minimum <?= (int)$minimumRemarksLength ?> characters)</label>
            <textarea name="remarks" minlength="<?= (int)$minimumRemarksLength ?>" required><?= htmlspecialchars($openVisit['remarks'] ?? '') ?></textarea>
            <label>Purpose of visit</label>
            <select name="purpose_of_visit"><?php foreach (['Routine','Surprise','Follow-up','Complaint','Client meeting','Other'] as $option): ?><option><?= htmlspecialchars($option) ?></option><?php endforeach; ?></select>
            <label>Guards found on duty</label>
            <input type="number" name="guards_count" min="0" value="<?= (int)($openVisit['guards_count'] ?? 0) ?>">
            <label>Attendance</label>
            <select name="guards_attendance"><?php foreach (['All present','Absent','Late'] as $option): ?><option><?= htmlspecialchars($option) ?></option><?php endforeach; ?></select>
            <label>Guard names (optional)</label>
            <textarea name="guards_names"><?= htmlspecialchars($openVisit['guards_names'] ?? '') ?></textarea>
            <?php foreach (['rating_turnout' => 'Turnout and discipline','rating_records' => 'Post and records','rating_condition' => 'Site condition and safety','rating_equipment' => 'Equipment'] as $field => $label): ?>
                <label><?= htmlspecialchars($label) ?></label>
                <select name="<?= htmlspecialchars($field) ?>"><option>OK</option><option>Issue</option><option>N/A</option></select>
            <?php endforeach; ?>
            <label>Issues found and action taken (required when any rating is Issue)</label>
            <textarea name="issues_action_taken"></textarea>
            <label>Photos (maximum 5)</label>
            <input type="file" name="photos[]" accept="image/*" multiple>
            <button name="confirm_action" value="Time Out">Confirm Time Out and Submit Report</button>
        </form>
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
