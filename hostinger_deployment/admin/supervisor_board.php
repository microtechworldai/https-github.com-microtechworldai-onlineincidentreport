<?php
/**
 * Live Supervisor Operations Board
 * Real-time board monitoring active supervisors, current location/site, elapsed minutes,
 * planned vs actual progress, and live field exception alerts.
 * Ultimate Security Services (USS)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$db = get_db();
$todayDate = date('Y-m-d');
$dayOfWeek = (int)date('N');

// AJAX Polling Endpoint (returns JSON for live 2s updates)
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');

    // 1. Fetch all supervisors marked as active
    $supervisors = $db->query("
        SELECT id, staff_id, full_name 
        FROM guards 
        WHERE is_supervisor = 1 AND is_active = 1 
        ORDER BY full_name ASC
    ")->fetchAll();

    $boardData = [];
    $activeOnDutyCount = 0;
    $currentlyAtSiteCount = 0;
    $inTransitCount = 0;

    foreach ($supervisors as $sup) {
        $supId = (int)$sup['id'];

        // Find active duty
        $dutyStmt = $db->prepare("
            SELECT d.*, c.camp_name 
            FROM supervisor_duties d
            JOIN camps c ON c.id = d.camp_id
            WHERE d.supervisor_id = ? AND d.status = 'Active'
            ORDER BY d.id DESC LIMIT 1
        ");
        $dutyStmt->execute([$supId]);
        $activeDuty = $dutyStmt->fetch();

        // Find today's planned schedule count
        $schStmt = $db->prepare("
            SELECT COUNT(*) FROM supervisor_schedules 
            WHERE supervisor_id = ? AND is_active = 1
              AND (specific_date = ? OR (specific_date IS NULL AND day_of_week = ?))
        ");
        $schStmt->execute([$supId, $todayDate, $dayOfWeek]);
        $plannedCount = (int)$schStmt->fetchColumn();

        // Find completed visits today
        $completedVisits = [];
        $openVisit = null;
        $totalMinutesOnSites = 0;

        if ($activeDuty) {
            $activeOnDutyCount++;

            // Open visit
            $vStmt = $db->prepare("
                SELECT v.*, s.name as site_name, s.code as site_code 
                FROM supervisor_visits v
                JOIN sites s ON s.id = v.site_id
                WHERE v.duty_id = ? AND v.status = 'In Progress'
                LIMIT 1
            ");
            $vStmt->execute([$activeDuty['id']]);
            $openVisit = $vStmt->fetch();

            if ($openVisit) {
                $currentlyAtSiteCount++;
            } else {
                $inTransitCount++;
            }

            // Completed visits for active duty
            $cStmt = $db->prepare("
                SELECT v.*, s.name as site_name, s.code as site_code 
                FROM supervisor_visits v
                JOIN sites s ON s.id = v.site_id
                WHERE v.duty_id = ? AND v.status = 'Completed'
                ORDER BY v.time_in ASC
            ");
            $cStmt->execute([$activeDuty['id']]);
            $completedVisits = $cStmt->fetchAll();

            foreach ($completedVisits as $cv) {
                $totalMinutesOnSites += (int)$cv['minutes_spent'];
            }
        }

        // Live Alerts for this supervisor today
        $alertStmt = $db->prepare("
            SELECT a.*, s.name as site_name 
            FROM supervisor_alerts a
            LEFT JOIN sites s ON s.id = a.site_id
            WHERE a.supervisor_id = ? AND DATE(a.created_at) = CURDATE()
            ORDER BY a.id DESC LIMIT 5
        ");
        $alertStmt->execute([$supId]);
        $alerts = $alertStmt->fetchAll();

        // Determine current status string and color
        $statusLabel = 'Off Duty';
        $statusType = 'off_duty';
        $currentLocation = 'Off Duty';
        $elapsedSiteMins = 0;

        if ($activeDuty) {
            if ($openVisit) {
                $statusType = 'at_site';
                $elapsedSiteMins = max(1, round((time() - strtotime($openVisit['time_in'])) / 60));
                $statusLabel = "At {$openVisit['site_name']} ({$elapsedSiteMins}m)";
                $currentLocation = $openVisit['site_name'] . ' (' . $openVisit['site_code'] . ')';
            } else {
                $statusType = 'travelling';
                $statusLabel = "Travelling / In Transit";
                $currentLocation = "In Transit (Base: {$activeDuty['camp_name']})";
            }
        }

        $completedCount = count($completedVisits);
        $progressPct = ($plannedCount > 0) ? min(100, round(($completedCount / $plannedCount) * 100)) : ($completedCount > 0 ? 100 : 0);

        $boardData[] = [
            'supervisor_id' => $supId,
            'staff_id' => $sup['staff_id'],
            'full_name' => $sup['full_name'],
            'phone' => $sup['phone'] ?? '',
            'is_on_duty' => !empty($activeDuty),
            'status_type' => $statusType,
            'status_label' => $statusLabel,
            'current_location' => $currentLocation,
            'elapsed_site_mins' => $elapsedSiteMins,
            'camp_out_time' => $activeDuty ? date('h:i A', strtotime($activeDuty['camp_out_time'])) : null,
            'shift_type' => $activeDuty['shift_type'] ?? 'Day',
            'planned_count' => $plannedCount,
            'completed_count' => $completedCount,
            'progress_pct' => $progressPct,
            'total_minutes_on_sites' => $totalMinutesOnSites,
            'open_visit' => $openVisit ? [
                'id' => $openVisit['id'],
                'ref' => $openVisit['visit_ref'],
                'site_name' => $openVisit['site_name'],
                'time_in' => date('h:i A', strtotime($openVisit['time_in'])),
                'elapsed_mins' => $elapsedSiteMins
            ] : null,
            'alerts_count' => count($alerts),
            'alerts' => $alerts
        ];
    }

    echo json_encode([
        'server_time' => date('h:i:s A'),
        'total_supervisors' => count($supervisors),
        'active_on_duty' => $activeOnDutyCount,
        'currently_at_site' => $currentlyAtSiteCount,
        'in_transit' => $inTransitCount,
        'supervisors' => $boardData
    ]);
    exit;
}

$pageTitle = "Live Field Supervisor Board";
$pageSubtitle = "Real-time command center: Active patrol locations, inspection duration, and schedule compliance";

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Board Top Metrics -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 22px;">
    <div class="card" style="padding: 16px; text-align: center; border-left: 4px solid var(--primary);">
        <div style="font-size: 11.5px; color: var(--text-dim); text-transform: uppercase; font-weight: 700;">Supervisors On Duty</div>
        <div style="font-size: 28px; font-weight: 900; color: var(--primary); margin-top: 4px;" id="metricOnDuty">
            --
        </div>
        <div style="font-size: 11px; color: var(--text-dim);">Active shifts running</div>
    </div>

    <div class="card" style="padding: 16px; text-align: center; border-left: 4px solid #f59e0b;">
        <div style="font-size: 11.5px; color: var(--text-dim); text-transform: uppercase; font-weight: 700;">Currently At Sites</div>
        <div style="font-size: 28px; font-weight: 900; color: #f59e0b; margin-top: 4px;" id="metricAtSite">
            --
        </div>
        <div style="font-size: 11px; color: var(--text-dim);">Conducting inspections now</div>
    </div>

    <div class="card" style="padding: 16px; text-align: center; border-left: 4px solid #0284c7;">
        <div style="font-size: 11.5px; color: var(--text-dim); text-transform: uppercase; font-weight: 700;">In Transit / Travelling</div>
        <div style="font-size: 28px; font-weight: 900; color: #0284c7; margin-top: 4px;" id="metricInTransit">
            --
        </div>
        <div style="font-size: 11px; color: var(--text-dim);">Moving between sites</div>
    </div>

    <div class="card" style="padding: 16px; text-align: center; border-left: 4px solid #10b981;">
        <div style="font-size: 11.5px; color: var(--text-dim); text-transform: uppercase; font-weight: 700;">Server Sync Heartbeat</div>
        <div style="font-size: 20px; font-weight: 800; color: #10b981; margin-top: 8px; font-family: monospace;" id="metricSyncTime">
            Live GST
        </div>
        <div style="font-size: 11px; color: var(--text-dim);">2-Second Live Catch-up</div>
    </div>
</div>

<!-- Header Actions & Controls -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
    <div style="display: flex; gap: 8px; align-items: center;">
        <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #10b981; box-shadow: 0 0 10px #10b981; animation: pulse 1.5s infinite;"></span>
        <span style="font-size: 13.5px; font-weight: 700; color: var(--text-main);">Live Field Patrol Operations</span>
        <span id="boardUpdateIndicator" style="font-size: 11px; color: var(--text-dim); margin-left: 8px;">Connecting...</span>
    </div>

    <div style="display: flex; gap: 10px;">
        <a href="<?= APP_URL ?>/admin/supervisor_visits.php" class="btn btn-secondary" style="font-size: 12.5px;">
            📋 Visits Registry
        </a>
        <a href="<?= APP_URL ?>/admin/supervisor_corrections.php" class="btn btn-secondary" style="font-size: 12.5px;">
            📝 Manual Corrections
        </a>
        <a href="<?= APP_URL ?>/admin/supervisor_schedules.php" class="btn btn-primary" style="font-size: 12.5px;">
            📅 Schedules & Plans
        </a>
    </div>
</div>

<!-- Live Supervisors Grid -->
<div id="supervisorsGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 16px;">
    <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--text-dim);">
        Connecting to real-time patrol stream...
    </div>
</div>

<style>
@keyframes pulse {
    0% { transform: scale(0.95); opacity: 0.8; }
    50% { transform: scale(1.15); opacity: 1; }
    100% { transform: scale(0.95); opacity: 0.8; }
}
.sup-card {
    background: var(--bg-card);
    border: 1px solid var(--border-subtle);
    border-radius: 12px;
    padding: 18px;
    transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
    position: relative;
    overflow: hidden;
}
.sup-card.at-site {
    border-left: 5px solid #f59e0b;
    background: linear-gradient(to right, rgba(245, 158, 11, 0.04), transparent);
}
.sup-card.travelling {
    border-left: 5px solid #0284c7;
    background: linear-gradient(to right, rgba(2, 132, 199, 0.04), transparent);
}
.sup-card.off-duty {
    border-left: 5px solid #64748b;
    opacity: 0.75;
}
</style>

<script>
let lastPollData = null;

function renderBoard(data) {
    document.getElementById('metricOnDuty').innerText = data.active_on_duty;
    document.getElementById('metricAtSite').innerText = data.currently_at_site;
    document.getElementById('metricInTransit').innerText = data.in_transit;
    document.getElementById('metricSyncTime').innerText = data.server_time;
    document.getElementById('boardUpdateIndicator').innerText = 'Live Synced (' + data.server_time + ')';

    let grid = document.getElementById('supervisorsGrid');
    if (!data.supervisors || data.supervisors.length === 0) {
        grid.innerHTML = '<div class="card" style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--text-dim);">No field supervisors configured in system.</div>';
        return;
    }

    let html = '';
    data.supervisors.forEach(s => {
        let cardClass = s.status_type === 'at_site' ? 'at-site' : (s.status_type === 'travelling' ? 'travelling' : 'off-duty');
        let statusBadge = '';

        if (s.status_type === 'at_site') {
            statusBadge = `<span class="badge" style="background:#fef3c7; color:#92400e; font-weight:800; border:1px solid #fde68a;">
                ⏱️ AT SITE (${s.elapsed_site_mins}m)
            </span>`;
        } else if (s.status_type === 'travelling') {
            statusBadge = `<span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:800; border:1px solid #bae6fd;">
                🚗 TRAVELLING
            </span>`;
        } else {
            statusBadge = `<span class="badge" style="background:#f1f5f9; color:#64748b; font-weight:700;">
                OFF DUTY
            </span>`;
        }

        html += `
        <div class="sup-card ${cardClass}">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                <div>
                    <strong style="font-size: 15.5px; color: var(--text-main); display: block;">${escapeHtml(s.full_name)}</strong>
                    <span style="font-size: 11.5px; color: var(--accent-cyan); font-weight: 700;">
                        ${escapeHtml(s.staff_id)} &bull; ${escapeHtml(s.shift_type)} Shift
                    </span>
                </div>
                ${statusBadge}
            </div>

            <!-- Current Location / Activity Box -->
            <div style="background: rgba(0,0,0,0.02); border: 1px solid var(--border-subtle); border-radius: 8px; padding: 10px 12px; margin-bottom: 12px;">
                <div style="font-size: 10.5px; color: var(--text-dim); text-transform: uppercase; font-weight: 700;">Current Status & Location:</div>
                <div style="font-size: 13.5px; font-weight: 800; color: ${s.status_type === 'at_site' ? '#d97706' : (s.status_type === 'travelling' ? '#0284c7' : 'var(--text-dim)')}; margin-top: 2px;">
                    ${escapeHtml(s.current_location)}
                </div>
                ${s.camp_out_time ? `<div style="font-size: 11px; color: var(--text-dim); margin-top: 4px;">Camp Out: <strong>${s.camp_out_time}</strong> &bull; Total Site Time: <strong>${s.total_minutes_on_sites}m</strong></div>` : ''}
            </div>

            <!-- Planned vs Completed Progress Bar -->
            <div style="margin-bottom: 12px;">
                <div style="display: flex; justify-content: space-between; font-size: 11.5px; font-weight: 700; margin-bottom: 5px;">
                    <span style="color: var(--text-dim);">Schedule Progress:</span>
                    <span style="color: var(--primary);">${s.completed_count} / ${s.planned_count || s.completed_count} Sites (${s.progress_pct}%)</span>
                </div>
                <div style="height: 6px; background: rgba(0,0,0,0.06); border-radius: 4px; overflow: hidden;">
                    <div style="height: 100%; width: ${s.progress_pct}%; background: ${s.progress_pct >= 100 ? '#10b981' : 'var(--primary)'}; border-radius: 4px; transition: width 0.3s;"></div>
                </div>
            </div>

            <!-- Alerts Banner if any -->
            ${s.alerts_count > 0 ? `
                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 8px 10px; margin-bottom: 10px; font-size: 11.5px; color: #dc2626; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                    <span>⚠️</span>
                    <span>${s.alerts_count} Field Exception Alert(s) Today</span>
                </div>
            ` : ''}

            <!-- Card Footer -->
            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 10px; border-top: 1px solid var(--border-subtle); font-size: 11.5px;">
                <span style="color: var(--text-dim);">${s.phone ? '📞 ' + escapeHtml(s.phone) : 'No direct line'}</span>
                <a href="<?= APP_URL ?>/admin/supervisor_visits.php?supervisor_id=${s.supervisor_id}" style="color: var(--primary); font-weight: 700; text-decoration: none;">
                    View Today's Log &rarr;
                </a>
            </div>
        </div>
        `;
    });

    grid.innerHTML = html;
}

function escapeHtml(str) {
    if (!str) return '';
    return str.toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function pollBoard() {
    fetch('<?= APP_URL ?>/admin/supervisor_board.php?ajax=1')
        .then(r => r.json())
        .then(data => {
            renderBoard(data);
        })
        .catch(err => {
            console.error('Polling error:', err);
            document.getElementById('boardUpdateIndicator').innerText = 'Reconnecting...';
        });
}

// Initial poll + repeat every 2 seconds
pollBoard();
setInterval(pollBoard, 2000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
