<?php
/**
 * Export Filtered Incidents to Excel / CSV format
 * Formatted with UTF-8 BOM for Microsoft Excel compatibility
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/excel_tokens.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$db = get_db();

// Filter parameters
$search = trim($_GET['search'] ?? '');
$siteId = trim($_GET['site_id'] ?? '');
$status = trim($_GET['status'] ?? '');
$priority = trim($_GET['priority'] ?? '');
$shift = trim($_GET['shift'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$where = ["i.deleted_at IS NULL"];
$params = [];

if ($search !== '') {
    $where[] = "(i.ref_number LIKE ? OR i.site_name LIKE ? OR i.reported_by_name LIKE ? OR i.summary_line LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}

if ($siteId !== '') {
    $where[] = "i.site_id = ?";
    $params[] = $siteId;
}

if ($status !== '') {
    $where[] = "i.status = ?";
    $params[] = $status;
}

if ($priority !== '') {
    $where[] = "i.priority = ?";
    $params[] = $priority;
}

if ($shift !== '') {
    $where[] = "i.shift = ?";
    $params[] = $shift;
}

if ($dateFrom !== '') {
    $where[] = "i.incident_date >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = "i.incident_date <= ?";
    $params[] = $dateTo;
}

$whereSql = "WHERE " . implode(" AND ", $where);

$sql = "
    SELECT i.*, 
           s.latitude as site_latitude, s.longitude as site_longitude,
           (SELECT COUNT(*) FROM incident_photos p WHERE p.incident_id = i.id) as photo_count
    FROM incidents i
    LEFT JOIN sites s ON s.id = i.site_id
    {$whereSql}
    ORDER BY i.incident_date DESC, i.incident_time DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);

$filename = 'USS_Incident_Reports_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel
fputs($output, "\xEF\xBB\xBF");

// Header row
fputcsv($output, [
    'Reference Number',
    'Site Name',
    'Site Code',
    'Site Latitude',
    'Site Longitude',
    'Reporting Officer',
    'Officer Role',
    'Incident Date (dd/mm/yyyy)',
    'Incident Time (24h GST)',
    'Shift',
    'Priority',
    'Status',
    'Main Causes',
    'Incident Types',
    'Disciplinary Reasons',
    'Authorities Notified',
    'Executive Summary',
    'Occurrence Description',
    'Supervisor Name',
    'Supervisor Action Taken',
    'Operations Manager',
    'Client Manager Name',
    'Photos Attached',
    'Submitted At (dd/mm/yyyy 24h)'
]);

$rowCount = 0;
while ($inc = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $causes = json_decode($inc['cause_main'] ?: '[]', true) ?: [];
    $types = json_decode($inc['incident_types'] ?: '[]', true) ?: [];
    $disc = json_decode($inc['disciplinary_reasons'] ?: '[]', true) ?: [];
    $notified = json_decode($inc['who_notified'] ?: '[]', true) ?: [];

    fputcsv($output, [
        $inc['ref_number'],
        $inc['site_name'],
        $inc['site_code'],
        $inc['site_latitude'] ?? '',
        $inc['site_longitude'] ?? '',
        $inc['reported_by_name'],
        $inc['reported_by_designation'],
        format_date($inc['incident_date']),
        format_time($inc['incident_time']),
        $inc['shift'],
        $inc['priority'],
        $inc['status'],
        implode(', ', $causes),
        implode(', ', $types) . (!empty($inc['incident_other_text']) ? " (Other: {$inc['incident_other_text']})" : ""),
        implode(', ', $disc) . (!empty($inc['disciplinary_other_text']) ? " (Other: {$inc['disciplinary_other_text']})" : ""),
        implode(', ', $notified) . (!empty($inc['notified_other_text']) ? " (Other: {$inc['notified_other_text']})" : ""),
        $inc['summary_line'],
        $inc['description'],
        $inc['site_supervisor_name'] ?? '',
        $inc['recommendation_action_taken'] ?? '',
        $inc['operations_manager_name'] ?? '',
        $inc['client_manager_name'] ?? '',
        $inc['photo_count'],
        format_datetime($inc['created_at'], true)
    ]);

    $rowCount++;
    if ($rowCount % 500 === 0) {
        fflush($output);
    }
}

fclose($output);
exit;
