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
    $assignment = $db->prepare("
        SELECT 1 FROM guard_site_assignments
        WHERE guard_id = ? AND site_id = ?
        LIMIT 1
    ");
    $assignment->execute([(int)$supervisorId, (int)$siteId]);
    if ($assignment->fetchColumn()) {
        return true;
    }

    $schedule = $db->prepare("
        SELECT 1 FROM supervisor_schedules
        WHERE supervisor_id = ? AND site_id = ? AND is_active = 1
        LIMIT 1
    ");
    $schedule->execute([(int)$supervisorId, (int)$siteId]);
    return (bool)$schedule->fetchColumn();
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

    $allowedRatings = ['OK', 'Issue', 'N/A'];
    foreach (['rating_turnout', 'rating_records', 'rating_condition', 'rating_equipment'] as $field) {
        if (!in_array($input[$field] ?? '', $allowedRatings, true)) {
            return 'Select a valid value for every site inspection rating.';
        }
    }

    $hasIssue = false;
    foreach (['rating_turnout', 'rating_records', 'rating_condition', 'rating_equipment'] as $field) {
        $hasIssue = $hasIssue || $input[$field] === 'Issue';
    }
    if ($hasIssue && trim((string)($input['issues_action_taken'] ?? '')) === '') {
        return 'Describe the issue found and the action taken before completing this visit.';
    }

    if (!in_array($input['purpose_of_visit'] ?? '', ['Routine', 'Surprise', 'Follow-up', 'Complaint', 'Client meeting', 'Other'], true)) {
        return 'Select a valid purpose of visit.';
    }
    if (!in_array($input['guards_attendance'] ?? '', ['All present', 'Absent', 'Late'], true)) {
        return 'Select a valid guards attendance value.';
    }

    return null;
}
