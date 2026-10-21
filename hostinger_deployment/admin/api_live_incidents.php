<?php
/**
 * Real-Time Admin Live Incident Stream API
 * Pings for new field incident submissions across all active sites with zero delay
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Ensure valid admin session
require_admin_auth();

// Close session immediately so long-polling or repeated requests never block other admin tabs
session_write_close();

$db = get_db();
$sinceId = (int)($_GET['since_id'] ?? 0);
$today = date('Y-m-d');

// 1. Fetch updated KPI Stats
$stmtToday = $db->prepare("SELECT COUNT(*) FROM incidents WHERE incident_date = ? AND deleted_at IS NULL");
$stmtToday->execute([$today]);
$todayIncidents = (int)$stmtToday->fetchColumn();

$stmtUrgent = $db->query("SELECT COUNT(*) FROM incidents WHERE priority IN ('Critical', 'High') AND status != 'Resolved' AND deleted_at IS NULL");
$urgentAlerts = (int)$stmtUrgent->fetchColumn();

$stmtFollowup = $db->query("SELECT COUNT(*) FROM incidents WHERE status = 'Follow-up Required' AND deleted_at IS NULL");
$openFollowups = (int)$stmtFollowup->fetchColumn();

$stmtSites = $db->query("SELECT COUNT(*) FROM sites WHERE is_active = 1");
$activeSites = (int)$stmtSites->fetchColumn();

// 2. Fetch new incidents since last known ID
$newIncidents = [];
if ($sinceId > 0) {
    $stmtNew = $db->prepare("
        SELECT i.*, 
               s.client_name, s.client_contact,
               (SELECT COUNT(*) FROM incident_photos p WHERE p.incident_id = i.id) as photo_count
        FROM incidents i 
        LEFT JOIN sites s ON s.id = i.site_id
        WHERE i.id > ? AND i.deleted_at IS NULL 
        ORDER BY i.id ASC
    ");
    $stmtNew->execute([$sinceId]);
    $newIncidents = $stmtNew->fetchAll();
}

$formattedIncidents = [];
foreach ($newIncidents as $inc) {
    $formattedIncidents[] = [
        'id' => (int)$inc['id'],
        'ref_number' => $inc['ref_number'],
        'site_name' => $inc['site_name'],
        'site_code' => $inc['site_code'],
        'reported_by_name' => $inc['reported_by_name'],
        'reported_by_designation' => $inc['reported_by_designation'],
        'shift' => $inc['shift'],
        'date_formatted' => format_date($inc['incident_date']),
        'time_formatted' => format_time($inc['incident_time']) . ' GST',
        'priority' => $inc['priority'],
        'priority_badge' => render_priority_badge($inc['priority']),
        'status' => $inc['status'],
        'status_badge' => render_status_badge($inc['status']),
        'photo_count' => (int)$inc['photo_count'],
        'summary_line' => $inc['summary_line'] ?? '',
        'client_name' => $inc['client_name'] ?? '',
        'client_contact' => $inc['client_contact'] ?? '',
        'pdf_url' => APP_URL . '/admin/incident_pdf.php?id=' . $inc['id'],
        'view_url' => APP_URL . '/admin/incident_view.php?id=' . $inc['id']
    ];
}

// Highest ID currently in database
$maxStmt = $db->query("SELECT MAX(id) FROM incidents WHERE deleted_at IS NULL");
$maxId = (int)$maxStmt->fetchColumn();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'server_time' => date('H:i:s'),
    'max_id' => $maxId,
    'kpis' => [
        'today' => $todayIncidents,
        'urgent' => $urgentAlerts,
        'followup' => $openFollowups,
        'sites' => $activeSites
    ],
    'has_new' => count($formattedIncidents) > 0,
    'count' => count($formattedIncidents),
    'incidents' => $formattedIncidents
], JSON_UNESCAPED_UNICODE);
exit;
