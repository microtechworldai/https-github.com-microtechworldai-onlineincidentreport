<?php
/**
 * Super Admin Operations Dashboard
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$pageTitle = "Operations Dashboard";
$pageSubtitle = "Real-time Patrol Monitoring & Incident Command";
$db = get_db();

// 1. Calculate KPIs
$today = date('Y-m-d');

// Today's Incidents
$stmt = $db->prepare("SELECT COUNT(*) FROM incidents WHERE incident_date = ? AND deleted_at IS NULL");
$stmt->execute([$today]);
$todayIncidents = (int)$stmt->fetchColumn();

// Urgent / Critical Alerts (Critical or High priority unresolved)
$stmt = $db->prepare("SELECT COUNT(*) FROM incidents WHERE priority IN ('Critical', 'High') AND status != 'Resolved' AND deleted_at IS NULL");
$stmt->execute();
$urgentAlerts = (int)$stmt->fetchColumn();

// Open Follow-ups Required
$stmt = $db->prepare("SELECT COUNT(*) FROM incidents WHERE status = 'Follow-up Required' AND deleted_at IS NULL");
$stmt->execute();
$openFollowups = (int)$stmt->fetchColumn();

// Total Active Sites
$stmt = $db->query("SELECT COUNT(*) FROM sites WHERE is_active = 1");
$activeSites = (int)$stmt->fetchColumn();

// 2. Fetch Latest 10 Incidents
$stmt = $db->query("
    SELECT i.*, 
           s.client_name, s.client_contact,
           (SELECT COUNT(*) FROM incident_photos p WHERE p.incident_id = i.id) as photo_count
    FROM incidents i 
    LEFT JOIN sites s ON s.id = i.site_id
    WHERE i.deleted_at IS NULL 
    ORDER BY i.created_at DESC 
    LIMIT 10
");
$recentIncidents = $stmt->fetchAll();
$maxIncidentId = !empty($recentIncidents) ? (int)$recentIncidents[0]['id'] : 0;

// 4. Fetch Field Supervisor Site Visits (Today & Recent)
$stmtSupVisits = $db->query("
    SELECT v.*, 
           s.name AS site_name, s.code AS site_code,
           g.full_name AS supervisor_name, g.staff_id AS supervisor_staff_id,
           d.duty_ref, d.shift_type
    FROM supervisor_visits v
    JOIN sites s ON s.id = v.site_id
    JOIN guards g ON g.id = v.supervisor_id
    LEFT JOIN supervisor_duties d ON d.id = v.duty_id
    ORDER BY v.id DESC 
    LIMIT 10
");
$recentSupervisorVisits = $stmtSupVisits->fetchAll();

// Supervisor visits KPI today
$stmtTodayVisits = $db->prepare("SELECT COUNT(*) FROM supervisor_visits WHERE DATE(time_in) = ?");
$stmtTodayVisits->execute([$today]);
$todaySupervisorVisitsCount = (int)$stmtTodayVisits->fetchColumn();

// Active Supervisors on duty
$stmtActiveSup = $db->query("SELECT COUNT(DISTINCT supervisor_id) FROM supervisor_duties WHERE status = 'Active'");
$activeSupervisorsOnDuty = (int)$stmtActiveSup->fetchColumn();

// 3. Fetch Sites Summary
$stmt = $db->query("SELECT * FROM sites WHERE is_active = 1 ORDER BY name ASC LIMIT 6");
$sitesList = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- KPI Counters -->
<div class="kpi-grid">
    <div class="kpi-card blue">
        <div class="kpi-icon blue">
            <svg style="width:24px;height:24px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
        </div>
        <div class="kpi-data">
            <div class="kpi-val" id="kpiToday"><?= $todayIncidents ?></div>
            <div class="kpi-label">Today's Incidents</div>
        </div>
    </div>

    <div class="kpi-card rose">
        <div class="kpi-icon rose">
            <svg style="width:24px;height:24px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        </div>
        <div class="kpi-data">
            <div class="kpi-val" id="kpiUrgent"><?= $urgentAlerts ?></div>
            <div class="kpi-label">Urgent / Critical Alerts</div>
        </div>
    </div>

    <div class="kpi-card amber">
        <div class="kpi-icon amber">
            <svg style="width:24px;height:24px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <div class="kpi-data">
            <div class="kpi-val" id="kpiFollowup"><?= $openFollowups ?></div>
            <div class="kpi-label">Follow-up Required</div>
        </div>
    </div>

    <div class="kpi-card emerald">
        <div class="kpi-icon emerald">
            <svg style="width:24px;height:24px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
        </div>
        <div class="kpi-data">
            <div class="kpi-val" id="kpiSites"><?= $activeSites ?></div>
            <div class="kpi-label">Active Patrol Sites</div>
        </div>
    </div>
    <div class="kpi-card" style="background: linear-gradient(135deg, rgba(8, 145, 178, 0.12), rgba(6, 182, 212, 0.05)); border: 1px solid rgba(8, 145, 178, 0.35);">
        <div class="kpi-icon" style="background: rgba(8, 145, 178, 0.25); color: #06b6d4;">
            <svg style="width:24px;height:24px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
        </div>
        <div class="kpi-data">
            <div class="kpi-val" id="kpiSupVisits" style="color: #38bdf8;"><?= $todaySupervisorVisitsCount ?></div>
            <div class="kpi-label">Supervisor Visits Today (<?= $activeSupervisorsOnDuty ?> On Duty)</div>
        </div>
    </div>
</div>

<!-- Quick Actions Banner -->
<div class="card quick-actions-banner" style="margin-bottom: 28px;">
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 800;">Operational Quick Actions</h3>
            <p style="font-size: 13px;">Generate official client dossiers or manage field checkpoints</p>
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
            <a href="<?= APP_URL ?>/attendance/" class="btn btn-sm" style="background: linear-gradient(135deg, #005f73 0%, #0a9396 100%); color: #ffffff; font-weight: 700; border: none; box-shadow: 0 4px 10px rgba(0,95,115,0.25); text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <svg style="width:16px;height:16px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Attendance &amp; Payroll
            </a>
            <a href="<?= APP_URL ?>/admin/daily_report.php" class="btn btn-primary btn-sm">
                📑 Daily Consolidated Report
            </a>
            <a href="<?= APP_URL ?>/admin/sites.php" class="btn btn-secondary btn-sm">
                📍 Checkpoint QR Posters
            </a>
            <a href="<?= APP_URL ?>/admin/guards.php" class="btn btn-secondary btn-sm">
                👮 Security Staff
            </a>
            <a href="<?= APP_URL ?>/admin/backups.php" class="btn btn-secondary btn-sm">
                💾 Backup Database
            </a>
        </div>
    </div>
</div>

<!-- Attendance & Payroll Operations Card -->
<div class="card" style="margin-bottom: 28px; background: linear-gradient(135deg, #051b2c 0%, #082d3b 100%); border: 1px solid rgba(10, 147, 150, 0.4); border-radius: 12px; padding: 20px 24px;">
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 18px;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #005f73, #0a9396); color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 4px 14px rgba(10,147,150,0.4);">
                <svg style="width:26px;height:26px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
            <div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <h3 style="font-size: 17px; font-weight: 800; margin: 0; color: #e0f2fe;">Attendance &amp; Payroll Management</h3>
                    <span style="background: rgba(10,147,150,0.25); color: #38bdf8; border: 1px solid rgba(56,189,248,0.3); font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 6px; letter-spacing: 0.5px;">STANDALONE SYSTEM</span>
                </div>
                <p style="font-size: 13px; color: #94a3b8; margin: 4px 0 0 0;">
                    Manage monthly security guard rosters, record daily shifts (Day, Night, Off, Leave), and calculate payroll salaries &amp; overtime.
                </p>
            </div>
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
            <a href="<?= APP_URL ?>/attendance/" class="btn btn-sm" style="background: #0a9396; color: #ffffff; font-weight: 700; border: none; padding: 8px 16px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <svg style="width:15px;height:15px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                Monthly Attendance Roster &rarr;
            </a>
            <a href="<?= APP_URL ?>/attendance/?action=payroll" class="btn btn-sm" style="background: rgba(255,255,255,0.08); color: #e2e8f0; border: 1px solid rgba(255,255,255,0.2); font-weight: 700; padding: 8px 16px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <svg style="width:15px;height:15px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Payroll &amp; Salary Sheet &rarr;
            </a>
        </div>
    </div>
</div>

<!-- Recent Incidents Table -->
<div class="card">
    <div class="live-patrol-banner">
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
            <div>
                <h3>Live Patrol Submissions</h3>
                <p>Latest incidents filed by on-duty guards across all checkpoints</p>
            </div>
            <div id="liveStreamBadge" class="live-stream-badge">
                <span class="live-pulse-dot"></span>
                LIVE CONNECTED
            </div>
        </div>
        <a href="<?= APP_URL ?>/admin/incidents.php" class="btn btn-primary btn-sm">
            View All Reports &rarr;
        </a>
    </div>

    <div class="table-container">
        <table class="data-table dashboard-table">
            <thead>
                <tr>
                    <th>Ref Number</th>
                    <th>Site / Facility</th>
                    <th>Reported By</th>
                    <th>Date & Time</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Photos</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody id="liveTableBody">
                <?php if (empty($recentIncidents)): ?>
                    <tr id="emptyTableRow">
                        <td colspan="8" style="text-align: center; padding: 40px; font-weight: 600;">
                            No incident reports filed yet. Guards will submit reports by scanning site QR codes.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentIncidents as $inc): ?>
                        <tr id="row_inc_<?= $inc['id'] ?>">
                            <td>
                                <a href="<?= APP_URL ?>/admin/incident_view.php?id=<?= $inc['id'] ?>" style="color: var(--accent-cyan); font-family: 'JetBrains Mono', monospace; font-weight: 600; text-decoration: none; display: block; line-height: 1.3;">
                                    <?= format_ref_2lines($inc['ref_number']) ?>
                                </a>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($inc['site_name']) ?></strong>
                                <div style="font-size: 11px; color: var(--text-dim);"><?= htmlspecialchars($inc['site_code']) ?></div>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($inc['reported_by_name']) ?></div>
                                <div style="font-size: 11px; color: var(--text-dim);"><?= htmlspecialchars($inc['reported_by_designation']) ?> (Shift: <?= htmlspecialchars($inc['shift']) ?>)</div>
                            </td>
                            <td>
                                <div><?= format_date($inc['incident_date']) ?></div>
                                <div style="font-size: 11px; color: var(--text-dim);"><?= format_time($inc['incident_time']) ?> GST</div>
                            </td>
                            <td><?= render_priority_badge($inc['priority']) ?></td>
                            <td><?= render_status_badge($inc['status']) ?></td>
                            <td>
                                <?php if ($inc['photo_count'] > 0): ?>
                                    <span class="badge" style="background: rgba(14, 165, 233, 0.15); color: #38bdf8;">
                                        📷 <?= $inc['photo_count'] ?> photo<?= $inc['photo_count'] > 1 ? 's' : '' ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-dim); font-size: 12px;">None</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <a href="<?= APP_URL ?>/admin/incident_view.php?id=<?= $inc['id'] ?>" class="btn btn-secondary btn-sm">
                                    Review &rarr;
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination for Dashboard Incidents: Pages 1, 2, 3, 4, 5, 6... -->
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--border-subtle);">
        <div style="font-size: 13px; color: var(--text-muted);">
            Showing <strong style="color: var(--header-title);"><?= $totalIncidentsCount > 0 ? ($offset + 1) : 0 ?></strong>–<strong style="color: var(--header-title);"><?= min($totalIncidentsCount, $offset + count($recentIncidents)) ?></strong> of <strong style="color: var(--header-title);"><?= number_format($totalIncidentsCount) ?></strong> incident reports
            <?php if ($totalPages > 1): ?>
                <span style="color: var(--brand-primary); font-weight: 700; margin-left: 6px;">(Page <?= $page ?> of <?= $totalPages ?>)</span>
            <?php endif; ?>
        </div>

        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
            <?php
            $dashParams = $_GET;
            $buildDashUrl = function($p) use ($dashParams) {
                $dashParams['page'] = $p;
                return '?' . http_build_query($dashParams);
            };
            $displayPages = max(6, $totalPages);
            ?>

            <!-- Previous Button -->
            <?php if ($page > 1): ?>
                <a href="<?= htmlspecialchars($buildDashUrl($page - 1)) ?>" class="btn btn-secondary btn-sm" title="Previous Page" style="font-weight: 700;">&lsaquo; Prev</a>
            <?php else: ?>
                <span class="btn btn-secondary btn-sm" style="opacity: 0.4; pointer-events: none;">&lsaquo; Prev</span>
            <?php endif; ?>

            <!-- Numbered Page Buttons 1, 2, 3, 4, 5, 6... -->
            <?php for ($p = 1; $p <= $displayPages; $p++): ?>
                <?php if ($p <= $totalPages || $p <= 6): ?>
                    <a href="<?= htmlspecialchars($buildDashUrl($p)) ?>" 
                       class="btn <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm" 
                       style="min-width: 34px; text-align: center; font-weight: <?= $p === $page ? '800' : '600' ?>;">
                        <?= $p ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>

            <!-- Next Button -->
            <?php if ($page < $totalPages): ?>
                <a href="<?= htmlspecialchars($buildDashUrl($page + 1)) ?>" class="btn btn-secondary btn-sm" title="Next Page" style="font-weight: 700;">Next &rsaquo;</a>
            <?php else: ?>
                <span class="btn btn-secondary btn-sm" style="opacity: 0.4; pointer-events: none;">Next &rsaquo;</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Field Supervisor Live Site Visits & Inspections Table Card -->
<div class="card" style="margin-top: 24px;">
    <div class="live-patrol-banner" style="background: linear-gradient(135deg, #0e2a47 0%, #0d3b66 100%) !important; border-color: rgba(56, 189, 248, 0.25) !important;">
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
            <div>
                <h3 style="color: #f8fafc; margin: 0; font-size: 17px; font-weight: 800;">Field Supervisor Live Site Inspections</h3>
                <p style="color: #94a3b8; margin: 3px 0 0 0; font-size: 13px;">Official checkpoint tracking: Time In, Time Out, Spending Time &amp; Online Site Reports</p>
            </div>
            <div class="live-stream-badge" style="background: rgba(14, 116, 144, 0.25); border-color: rgba(14, 165, 233, 0.5); color: #38bdf8;">
                <span class="live-pulse-dot" style="background: #38bdf8;"></span>
                LIVE AUDIT
            </div>
        </div>
        <a href="<?= APP_URL ?>/admin/supervisor_visits.php" class="btn btn-primary btn-sm" style="background: #0891b2; border-color: #0891b2;">
            View Full Visits Dossier &rarr;
        </a>
    </div>

    <div class="table-container">
        <table class="data-table dashboard-table">
            <thead>
                <tr>
                    <th>Ref / Supervisor</th>
                    <th>Site Visited</th>
                    <th>Shift</th>
                    <th style="text-align: center;">Time In</th>
                    <th style="text-align: center;">Time Out</th>
                    <th style="text-align: center;">Spending Time</th>
                    <th>Visit Assessment &amp; Remarks</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentSupervisorVisits)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; font-weight: 600; color: var(--text-muted);">
                            No field supervisor site visits recorded yet. Visits will appear here live when supervisors scan site QR codes.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentSupervisorVisits as $v): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 800; font-size: 12.5px; color: #38bdf8; font-family: monospace;">
                                    <?= htmlspecialchars($v['visit_ref'] ?: 'VIS-' . str_pad($v['id'], 5, '0', STR_PAD_LEFT)) ?>
                                </div>
                                <strong style="color: var(--text-main); font-size: 13.5px; display: block; margin-top: 2px;">
                                    <?= htmlspecialchars($v['supervisor_name']) ?>
                                </strong>
                                <div style="font-size: 11px; color: var(--text-dim);"><?= htmlspecialchars($v['supervisor_staff_id']) ?> &bull; Field Supervisor</div>
                            </td>
                            <td>
                                <strong style="color: var(--text-main); font-size: 13.5px;"><?= htmlspecialchars($v['site_name']) ?></strong>
                                <div style="font-size: 11.5px; color: var(--text-muted); font-family: monospace;"><?= htmlspecialchars($v['site_code']) ?></div>
                            </td>
                            <td>
                                <span class="badge" style="background: <?= $v['shift_type'] === 'Night' ? 'rgba(99, 102, 241, 0.2)' : 'rgba(245, 158, 11, 0.2)' ?>; color: <?= $v['shift_type'] === 'Night' ? '#818cf8' : '#f59e0b' ?>; font-weight: 700;">
                                    <?= htmlspecialchars($v['shift_type'] ?: 'Day') ?>
                                </span>
                            </td>
                            <td style="text-align: center; font-family: monospace; font-size: 13px; color: #10b981; font-weight: 700;">
                                <?= !empty($v['time_in']) && $v['time_in'] !== '0000-00-00 00:00:00' ? date('h:i A', strtotime($v['time_in'])) : '—' ?>
                            </td>
                            <td style="text-align: center; font-family: monospace; font-size: 13px;">
                                <?php if (!empty($v['time_out']) && $v['time_out'] !== '0000-00-00 00:00:00' && strpos($v['time_out'], '1970') === false): ?>
                                    <span style="color: #f8fafc; font-weight: 700;"><?= date('h:i A', strtotime($v['time_out'])) ?></span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b; font-weight: 700;">● In Progress</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($v['status'] === 'Completed'): ?>
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 800; font-family: monospace; font-size: 12.5px; padding: 4px 10px;">
                                        ⏱️ <?= (int)$v['minutes_spent'] ?> mins
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #d97706; font-weight: 700; font-size: 12px; padding: 4px 8px;">
                                        ⏱️ Ticking...
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="max-width: 260px;">
                                <?php if (!empty($v['purpose_of_visit'])): ?>
                                    <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #38bdf8; display: block; margin-bottom: 2px;">
                                        <?= htmlspecialchars($v['purpose_of_visit']) ?>
                                    </span>
                                <?php endif; ?>
                                <div style="font-size: 12px; color: var(--text-muted); line-height: 1.35;">
                                    <?= htmlspecialchars(mb_strimwidth($v['remarks'] ?: ($v['site_report'] ?: 'Site inspection in progress'), 0, 75, '...')) ?>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($v['status'] === 'Completed'): ?>
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700;">Completed</span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b; font-weight: 700;">In Progress</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <a href="<?= APP_URL ?>/admin/supervisor_visits.php?date=<?= !empty($v['time_in']) ? date('Y-m-d', strtotime($v['time_in'])) : date('Y-m-d') ?>&supervisor_id=<?= $v['supervisor_id'] ?>" 
                                   class="btn btn-outline btn-sm" style="font-size: 11.5px; padding: 4px 10px; text-decoration: none;">
                                    Inspect &rarr;
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Floating Real-Time Incident Toast Notification -->
<div id="toastContainer" style="position: fixed; bottom: 24px; right: 24px; z-index: 99999; display: flex; flex-direction: column; gap: 12px; pointer-events: none;"></div>

<style>
@keyframes livePulseDot {
    0% { transform: scale(0.9); opacity: 0.8; }
    50% { transform: scale(1.3); opacity: 1; box-shadow: 0 0 12px #34d399; }
    100% { transform: scale(0.9); opacity: 0.8; }
}

@keyframes highlightNewRow {
    0% { background: rgba(56, 189, 248, 0.35); }
    100% { background: transparent; }
}

.new-incident-row {
    animation: highlightNewRow 3.5s ease-out;
}

.incident-toast {
    pointer-events: auto;
    background: #0f172a;
    border: 1px solid rgba(56, 189, 248, 0.5);
    border-left: 4px solid #38bdf8;
    color: #f8fafc;
    padding: 14px 18px;
    border-radius: 12px;
    box-shadow: 0 20px 35px -8px rgba(0,0,0,0.7);
    display: flex;
    align-items: center;
    gap: 14px;
    max-width: 420px;
    transform: translateX(120%);
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

.incident-toast.show {
    transform: translateX(0);
}
</style>

<script>
// Zero-delay Live Patrol Stream
let lastIncidentId = <?= (int)$maxIncidentId ?>;
let isPolling = false;

function playAlertChime() {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.setValueAtTime(659.25, ctx.currentTime); // E5
        osc.frequency.setValueAtTime(880.00, ctx.currentTime + 0.12); // A5
        gain.gain.setValueAtTime(0.08, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
        osc.start();
        osc.stop(ctx.currentTime + 0.35);
    } catch(e) {}
}

function showIncidentToast(inc) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'incident-toast';
    toast.innerHTML = `
        <div style="font-size: 24px;">🚨</div>
        <div style="flex: 1;">
            <div style="font-size: 11px; text-transform: uppercase; color: #38bdf8; font-weight: 800; letter-spacing: 0.5px;">New Field Report • ${inc.time_formatted}</div>
            <div style="font-size: 14px; font-weight: 800; color: #fff; margin-top: 2px;">${escapeHtml(inc.site_name)}</div>
            <div style="font-size: 12.5px; color: #94a3b8; font-family: monospace;">${escapeHtml(inc.ref_number)} &bull; ${inc.priority_badge}</div>
        </div>
        <a href="${inc.view_url}" class="btn btn-primary btn-sm" style="padding: 6px 12px; font-size: 12px; font-weight: 700; white-space: nowrap;">View &rarr;</a>
    `;
    container.appendChild(toast);

    setTimeout(() => toast.classList.add('show'), 20);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 400);
    }, 6000);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

async function checkLiveIncidents() {
    if (isPolling) return;
    isPolling = true;

    try {
        const res = await fetch('<?= APP_URL ?>/admin/api_live_incidents.php?since_id=' + lastIncidentId);
        if (!res.ok) throw new Error('Network error');
        const data = await res.json();

        if (data.success) {
            // 1. Update KPI Counters live without page reload
            if (data.kpis) {
                const elToday = document.getElementById('kpiToday');
                const elUrgent = document.getElementById('kpiUrgent');
                const elFollow = document.getElementById('kpiFollowup');
                const elSites = document.getElementById('kpiSites');
                if (elToday) elToday.textContent = data.kpis.today;
                if (elUrgent) elUrgent.textContent = data.kpis.urgent;
                if (elFollow) elFollow.textContent = data.kpis.followup;
                if (elSites) elSites.textContent = data.kpis.sites;
            }

            // 2. Prepend any new incident reports immediately to the table
            if (data.has_new && data.incidents && data.incidents.length > 0) {
                const tbody = document.getElementById('liveTableBody');
                const emptyRow = document.getElementById('emptyTableRow');
                if (emptyRow) emptyRow.remove();

                // Play soft notification sound
                playAlertChime();

                // Insert newest first
                data.incidents.reverse().forEach(inc => {
                    if (document.getElementById('row_inc_' + inc.id)) return; // Avoid duplicates

                    const tr = document.createElement('tr');
                    tr.id = 'row_inc_' + inc.id;
                    tr.className = 'new-incident-row';
                    tr.innerHTML = `
                        <td>
                            <a href="${inc.view_url}" style="color: var(--accent-cyan); font-family: 'JetBrains Mono', monospace; font-weight: 600; text-decoration: none; display: block; line-height: 1.3;">
                                ${formatRef2LinesJs(inc.ref_number)}
                            </a>
                        </td>
                        <td>
                            <strong>${escapeHtml(inc.site_name)}</strong>
                            <div style="font-size: 11px; color: var(--text-dim);">${escapeHtml(inc.site_code)}</div>
                        </td>
                        <td>
                            <div>${escapeHtml(inc.reported_by_name)}</div>
                            <div style="font-size: 11px; color: var(--text-dim);">${escapeHtml(inc.reported_by_designation)} (Shift: ${escapeHtml(inc.shift)})</div>
                        </td>
                        <td>
                            <div>${inc.date_formatted}</div>
                            <div style="font-size: 11px; color: var(--text-dim);">${inc.time_formatted}</div>
                        </td>
                        <td>${inc.priority_badge}</td>
                        <td>${inc.status_badge}</td>
                        <td>
                            ${inc.photo_count > 0 
                                ? `<span class="badge" style="background: rgba(14, 165, 233, 0.15); color: #38bdf8;">📷 ${inc.photo_count} photo${inc.photo_count > 1 ? 's' : ''}</span>`
                                : `<span style="color: var(--text-dim); font-size: 12px;">None</span>`
                            }
                        </td>
                        <td style="text-align: right;">
                            <a href="${inc.view_url}" class="btn btn-secondary btn-sm">
                                Review &rarr;
                            </a>
                        </td>
                    `;
                    tbody.insertBefore(tr, tbody.firstChild);

                    // Show toast notification
                    showIncidentToast(inc);
                });

                if (data.max_id > lastIncidentId) {
                    lastIncidentId = data.max_id;
                }
            }
        }
    } catch(err) {
        console.warn('Live stream ping error:', err);
    } finally {
        isPolling = false;
    }
}

// Poll every 3.5 seconds for instant real-time synchronization from all sites
setInterval(checkLiveIncidents, 3500);

    function formatRef2LinesJs(ref) {
        if (!ref) return '';
        ref = String(ref).trim();
        const m = ref.match(/^([A-Za-z0-9]+-[A-Za-z0-9_]+)-(\d{8}-\d+)$/);
        if (m) {
            return escapeHtml(m[1]) + '<br><span style="color: var(--text-dim); font-size: 11px; font-weight: 500; display: block; line-height: 1.25; margin-top: 2px;">' + escapeHtml(m[2]) + '</span>';
        }
        const lastIdx = ref.lastIndexOf('-');
        if (lastIdx > 0) {
            return escapeHtml(ref.substring(0, lastIdx)) + '<br><span style="color: var(--text-dim); font-size: 11px; font-weight: 500; display: block; line-height: 1.25; margin-top: 2px;">' + escapeHtml(ref.substring(lastIdx + 1)) + '</span>';
        }
        return escapeHtml(ref);
    }

</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
