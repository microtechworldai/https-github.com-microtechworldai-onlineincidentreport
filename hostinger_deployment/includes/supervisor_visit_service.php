<?php

require_once __DIR__ . '/functions.php';

function get_supervisor_duty_state(PDO $db, $supervisorId)
{
    $stmt = $db->prepare("
        SELECT d.*, c.camp_name, c.camp_code
        FROM supervisor_duties d
        JOIN camps c ON c.id = d.camp_id
        WHERE d.supervisor_id = ? AND d.status = 'Active'
        ORDER BY d.id DESC
        LIMIT 1
    ");
    $stmt->execute([(int)$supervisorId]);
    $duty = $stmt->fetch();
    if (!$duty) {
        return [null, null];
    }

    $visitStmt = $db->prepare("
        SELECT v.*, s.name AS site_name, s.code AS site_code
        FROM supervisor_visits v
        JOIN sites s ON s.id = v.site_id
        WHERE v.duty_id = ? AND v.status = 'In Progress'
        ORDER BY v.id DESC
        LIMIT 1
    ");
    $visitStmt->execute([(int)$duty['id']]);
    return [$duty, $visitStmt->fetch() ?: null];
}

function supervisor_can_visit_site(PDO $db, $supervisorId, $siteId)
{
    // Field supervisors are authorized by super admin to scan & inspect ANY active site
    $stmt = $db->prepare("SELECT id, is_active FROM sites WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$siteId]);
    $site = $stmt->fetch();
    return !empty($site['is_active']);
}

function supervisor_visit_write_scan_event(
    PDO $db,
    $idempotencyKey,
    $supervisorId,
    $dutyId,
    $visitId,
    $token,
    $action,
    $clientTime = null,
    $deviceHash = null,
    $offline = false,
    $location = null,
    array $result = []
) {
    $stmt = $db->prepare("
        INSERT INTO supervisor_scan_events
            (idempotency_key, supervisor_id, duty_id, visit_id, token_hash, scan_action,
             server_time, client_time, device_hash, is_offline_sync, location_encrypted, result_json)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $idempotencyKey,
        (int)$supervisorId,
        $dutyId ? (int)$dutyId : null,
        $visitId ? (int)$visitId : null,
        hash_qr_token($token),
        $action,
        $clientTime ?: null,
        $deviceHash ?: null,
        $offline ? 1 : 0,
        $location ? encrypt_field($location) : null,
        json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ]);
}

function cleanup_supervisor_scan_uploads(array $relativePaths)
{
    $root = realpath(ROOT_PATH);
    if ($root === false) {
        error_log('Supervisor scan upload cleanup skipped: application root is unavailable.');
        return;
    }

    foreach ($relativePaths as $relativePath) {
        if (!is_string($relativePath) || $relativePath === '') {
            continue;
        }
        $candidate = $root . DIRECTORY_SEPARATOR . str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            ltrim($relativePath, '/\\')
        );
        $file = realpath($candidate);
        if ($file === false) {
            continue;
        }
        if (strpos($file, $root . DIRECTORY_SEPARATOR) !== 0) {
            error_log('Supervisor scan upload cleanup refused a path outside the application root.');
            continue;
        }
        if (!unlink($file)) {
            error_log('Supervisor scan upload cleanup failed for a rolled-back scan photo.');
        }
    }
}

function validate_supervisor_visit_report(array $input, $minimumRemarksLength)
{
    $remarks = trim((string)($input['remarks'] ?? ''));
    $characterCount = function_exists('mb_strlen')
        ? mb_strlen($remarks, 'UTF-8')
        : (preg_match_all('/./us', $remarks, $matches) !== false ? count($matches[0]) : strlen($remarks));
    if ($characterCount < (int)$minimumRemarksLength) {
        return "Remarks must be at least {$minimumRemarksLength} characters.";
    }

    // Validate 22-item checklist if submitted
    $chkErrors = [];
    $chkHasNo = false;
    if (isset($input['chk_q_1'])) {
        $chkResult = process_supervisor_checklist_input($input);
        if (!empty($chkResult['errors'])) {
            return implode(' ', $chkResult['errors']);
        }
        if ($chkResult['no'] > 0) {
            $chkHasNo = true;
        }
    }

    $allowedRatings = ['OK', 'Issue', 'N/A'];
    foreach (['rating_turnout', 'rating_records', 'rating_condition', 'rating_equipment'] as $field) {
        if (!empty($input[$field]) && !in_array($input[$field], $allowedRatings, true)) {
            return 'Select a valid value for every site inspection rating.';
        }
    }

    $hasIssue = $chkHasNo;
    foreach (['rating_turnout', 'rating_records', 'rating_condition', 'rating_equipment'] as $field) {
        $hasIssue = $hasIssue || (($input[$field] ?? '') === 'Issue');
    }
    if ($hasIssue && trim((string)($input['issues_action_taken'] ?? '')) === '') {
        return 'Describe the issue/deficiency found and the corrective action taken before completing this visit.';
    }

    if (!empty($input['purpose_of_visit']) && !in_array($input['purpose_of_visit'], ['Routine', 'Surprise', 'Follow-up', 'Complaint', 'Client meeting', 'Other'], true)) {
        return 'Select a valid purpose of visit.';
    }
    if (!empty($input['guards_attendance']) && !in_array($input['guards_attendance'], ['All present', 'Absent', 'Late'], true)) {
        return 'Select a valid guards attendance value.';
    }

    return null;
}

function get_supervisor_checklist_questions() {
    return [
        1 => 'All required security guards are present',
        2 => 'Guards are in correct uniform with valid ID cards',
        3 => 'Guards are alert and at assigned posts',
        4 => 'Reliever arrangements and duty roster are verified',
        5 => 'Security guards follow site instructions',
        6 => 'Patrolling is completed as scheduled',
        7 => 'Visitor and contractor registers are maintained',
        8 => 'Vehicle access and entry/exit points are monitored',
        9 => 'Restricted areas and unattended posts are checked',
        10 => 'Shift handover and occurrence book are updated',
        11 => 'Key register and access cards are properly maintained',
        12 => 'Radios and communication equipment are working',
        13 => 'Emergency contacts and procedures are available',
        14 => 'Emergency exits and escape routes are unobstructed',
        15 => 'Visible safety hazards are reported',
        16 => 'Incidents and client complaints are recorded',
        17 => 'Previous inspection deficiencies are followed up',
        18 => 'Guards are briefed on duties and pending issues',
        19 => 'Client instructions and service standards are followed',
        20 => 'Supervisor QR check-in and check-out are recorded',
        21 => 'Site inspection report is submitted',
        22 => 'Corrective actions are assigned and monitored'
    ];
}

function process_supervisor_checklist_input(array $input) {
    $questions = get_supervisor_checklist_questions();
    $answers = [];
    $yes = 0;
    $no = 0;
    $na = 0;
    $unanswered = 0;
    $errors = [];

    foreach ($questions as $id => $qText) {
        $ans = trim((string)($input["chk_q_{$id}"] ?? ''));
        $rem = trim((string)($input["chk_rem_{$id}"] ?? ''));

        if ($ans === 'Yes') {
            $yes++;
        } elseif ($ans === 'No') {
            $no++;
            if (empty($rem)) {
                $errors[] = "Item {$id} ('{$qText}'): Mandatory remarks required when marked No.";
            }
        } elseif ($ans === 'N/A') {
            $na++;
        } else {
            $unanswered++;
            $ans = 'Unanswered';
        }

        $answers[$id] = [
            'id' => $id,
            'question' => $qText,
            'answer' => $ans,
            'remarks' => $rem
        ];
    }

    $summary = "Yes: {$yes}; No: {$no}; N/A: {$na}; Unanswered: {$unanswered}";

    return [
        'answers' => $answers,
        'yes' => $yes,
        'no' => $no,
        'na' => $na,
        'unanswered' => $unanswered,
        'summary' => $summary,
        'errors' => $errors
    ];
}

