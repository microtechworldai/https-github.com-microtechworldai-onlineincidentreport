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
</div>

<!-- Quick Actions Banner -->
<div class="card quick-actions-banner" style="margin-bottom: 28px;">
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 800;">Operational Quick Actions</h3>
            <p style="font-size: 13px;">Generate official client dossiers or manage field checkpoints</p>
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
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

<!-- Recent Incidents Table -->
<div class="card">
    <div class="live-patrol-banner" style="background: #081426 !important; border: 1px solid rgba(255, 209, 102, 0.35) !important; padding: 16px 20px; border-radius: 10px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
            <div>
                <h3 style="font-size: 18px; font-weight: 900; margin: 0; color: #fbbf24 !important;">Live Patrol Submissions</h3>
                <p style="font-size: 13px; font-weight: 700; margin: 3px 0 0 0; color: #ffe894 !important; opacity: 0.95;">Latest incidents filed by on-duty guards across all checkpoints</p>
            </div>
            <div id="liveStreamBadge" style="display: inline-flex; align-items: center; gap: 6px; background: rgba(255, 209, 102, 0.18); border: 1px solid rgba(255, 209, 102, 0.45); color: #fbbf24; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; letter-spacing: 0.5px;">
                <span style="display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #fbbf24; box-shadow: 0 0 8px #fbbf24; animation: livePulseDot 1.5s infinite;"></span>
                LIVE CONNECTED
            </div>
        </div>
        <a href="<?= APP_URL ?>/admin/incidents.php" class="btn btn-sm" style="background: #fbbf24; color: #081426; font-weight: 800; border: none; padding: 7px 14px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
            View All Reports &rarr;
        </a>
    </div>

    <div class="table-container">
        <table class="data-table dashboard-table">
            <thead>
                <tr style="background: #081426 !important;">
                    <th style="background: #081426 !important; color: #fbbf24 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px;">Ref Number</th>
                    <th style="background: #081426 !important; color: #fbbf24 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px;">Site / Facility</th>
                    <th style="background: #081426 !important; color: #fbbf24 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px;">Reported By</th>
                    <th style="background: #081426 !important; color: #fbbf24 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px;">Date & Time</th>
                    <th style="background: #081426 !important; color: #fbbf24 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px;">Priority</th>
                    <th style="background: #081426 !important; color: #fbbf24 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px;">Status</th>
                    <th style="background: #081426 !important; color: #fbbf24 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px;">Photos</th>
                    <th style="background: #081426 !important; color: #fbbf24 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px; text-align: right;">Action</th>
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
            Showing <strong style="color: #fff;"><?= $totalIncidentsCount > 0 ? ($offset + 1) : 0 ?></strong>–<strong style="color: #fff;"><?= min($totalIncidentsCount, $offset + count($recentIncidents)) ?></strong> of <strong style="color: #fff;"><?= number_format($totalIncidentsCount) ?></strong> incident reports
            <?php if ($totalPages > 1): ?>
                <span style="color: #fbbf24; font-weight: 700; margin-left: 6px;">(Page <?= $page ?> of <?= $totalPages ?>)</span>
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
                       style="min-width: 34px; text-align: center; font-weight: <?= $p === $page ? '800' : '600' ?>; <?= $p === $page ? 'background: #081426 !important; color: #fbbf24 !important; border: 1.5px solid #fbbf24 !important;' : '' ?>">
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
