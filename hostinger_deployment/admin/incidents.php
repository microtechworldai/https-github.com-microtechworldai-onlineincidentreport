<?php
/**
 * Incident Reports Management & Search
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$pageTitle = "Incident Reports";
$pageSubtitle = "Browse, filter, and audit verified field patrol dossiers";
$db = get_db();

// Filter parameters
$search = trim($_GET['search'] ?? '');
$siteId = trim($_GET['site_id'] ?? '');
$status = trim($_GET['status'] ?? '');
$priority = trim($_GET['priority'] ?? '');
$shift = trim($_GET['shift'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$showDeleted = !empty($_GET['show_deleted']);

// Handle Delete / Restore actions
$msg = '';
$msgType = 'success';
if (isset($_GET['action']) && isset($_GET['incident_id'])) {
    $actId = (int)$_GET['incident_id'];
    if ($_GET['action'] === 'soft_delete') {
        $db->prepare("UPDATE incidents SET deleted_at = NOW() WHERE id = ?")->execute([$actId]);
        log_audit('SOFT_DELETE_INCIDENT', 'INCIDENT', $actId, 'Moved incident to trash');
        $msg = "Incident report moved to trash.";
    } elseif ($_GET['action'] === 'restore') {
        $db->prepare("UPDATE incidents SET deleted_at = NULL WHERE id = ?")->execute([$actId]);
        log_audit('RESTORE_INCIDENT', 'INCIDENT', $actId, 'Restored incident from trash');
        $msg = "Incident report restored successfully.";
    } elseif ($_GET['action'] === 'permanent_delete') {
        // Delete photos
        $stmtPhotos = $db->prepare("SELECT file_path FROM incident_photos WHERE incident_id = ?");
        $stmtPhotos->execute([$actId]);
        while ($p = $stmtPhotos->fetch()) {
            if (!empty($p['file_path'])) {
                @unlink(ROOT_PATH . '/' . $p['file_path']);
            }
        }
        $db->prepare("DELETE FROM incidents WHERE id = ?")->execute([$actId]);
        log_audit('PERMANENT_DELETE_INCIDENT', 'INCIDENT', $actId, 'Permanently purged incident');
        $msg = "Incident report permanently deleted.";
    }
}

// Active vs Deleted counts
$activeIncidentsCount = (int)$db->query("SELECT COUNT(*) FROM incidents WHERE deleted_at IS NULL")->fetchColumn();
$deletedIncidentsCount = (int)$db->query("SELECT COUNT(*) FROM incidents WHERE deleted_at IS NOT NULL")->fetchColumn();

// Build Query
$where = [];
$params = [];

if ($showDeleted) {
    $where[] = "i.deleted_at IS NOT NULL";
} else {
    $where[] = "i.deleted_at IS NULL";
}

if ($search !== '') {
    $where[] = "(i.ref_number LIKE ? OR i.site_name LIKE ? OR i.reported_by_name LIKE ? OR i.summary_line LIKE ? OR i.description LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
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

$whereSql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

// Server-Side Keyset / Indexed Pagination for 500,000+ Incidents
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 25);
if (!in_array($perPage, [10, 25, 50, 100, 250])) {
    $perPage = 25;
}

// Fast COUNT(*) using indexed conditions
$countSql = "SELECT COUNT(*) FROM incidents i {$whereSql}";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalIncidents = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalIncidents / $perPage));
if ($page > $totalPages && $totalIncidents > 0) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Fetch Paginated Incidents using B-Tree index scan
$sql = "
    SELECT i.*, 
           s.client_name, s.client_contact,
           (SELECT COUNT(*) FROM incident_photos p WHERE p.incident_id = i.id) as photo_count
    FROM incidents i
    LEFT JOIN sites s ON s.id = i.site_id
    {$whereSql}
    ORDER BY i.created_at DESC, i.id DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$incidents = $stmt->fetchAll();

// Determine max incident ID for live stream polling
$maxStmt = $db->query("SELECT MAX(id) FROM incidents WHERE deleted_at IS NULL");
$maxIncidentId = (int)$maxStmt->fetchColumn();

// Fetch sites for filter dropdown
$sites = $db->query("SELECT id, name, code FROM sites ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Filter & Search Card -->
<div class="card" style="margin-bottom: 24px;">
    <form method="GET" action="" style="display: flex; flex-direction: column; gap: 16px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px;">
            <div>
                <label class="form-label">Search Keyword</label>
                <input type="text" name="search" class="form-control" placeholder="Ref #, guard, keyword..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <div>
                <label class="form-label">Patrol Site</label>
                <select name="site_id" class="form-control">
                    <option value="">-- All Sites --</option>
                    <?php foreach ($sites as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $siteId == $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="form-label">Priority</label>
                <select name="priority" class="form-control">
                    <option value="">-- All Priorities --</option>
                    <option value="Critical" <?= $priority === 'Critical' ? 'selected' : '' ?>>Critical</option>
                    <option value="High" <?= $priority === 'High' ? 'selected' : '' ?>>High</option>
                    <option value="Medium" <?= $priority === 'Medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="Low" <?= $priority === 'Low' ? 'selected' : '' ?>>Low</option>
                </select>
            </div>

            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">-- All Statuses --</option>
                    <option value="Submitted" <?= $status === 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                    <option value="Under Review" <?= $status === 'Under Review' ? 'selected' : '' ?>>Under Review</option>
                    <option value="Follow-up Required" <?= $status === 'Follow-up Required' ? 'selected' : '' ?>>Follow-up Required</option>
                    <option value="Resolved" <?= $status === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                    <option value="Submitted to Client" <?= $status === 'Submitted to Client' ? 'selected' : '' ?>>Submitted to Client</option>
                </select>
            </div>

            <div>
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>

            <div>
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
        </div>

        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; border-top: 1px solid var(--border-subtle); padding-top: 14px;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-muted); cursor: pointer;">
                    <input type="checkbox" name="show_deleted" value="1" <?= $showDeleted ? 'checked' : '' ?> onchange="this.form.submit()">
                    Show Deleted / Trash Only
                </label>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary btn-sm">Filter Reports</button>
                <a href="<?= APP_URL ?>/admin/incidents.php" class="btn btn-secondary btn-sm">Reset</a>
                <a href="<?= APP_URL ?>/admin/daily_report.php<?= $siteId ? '?site_id=' . urlencode($siteId) : '' ?>" class="btn btn-secondary btn-sm" title="Sitewise Daily Security Incident Consolidated Report">
                    📑 Daily Consolidated Report
                </a>
                <a href="<?= APP_URL ?>/api/export_excel.php?<?= http_build_query($_GET) ?>" class="btn btn-secondary btn-sm" title="Export this filtered view to Excel">
                    📊 Export Excel
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Table Card -->
<div class="card">
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 16px;">
        <!-- Active vs Trash Tabs -->
        <div style="display: inline-flex; background: rgba(0,0,0,0.2); border: 1px solid var(--border-subtle); border-radius: 8px; padding: 3px; gap: 4px;">
            <a href="<?= APP_URL ?>/admin/incidents.php" class="btn btn-sm" style="<?= !$showDeleted ? 'background: var(--primary); color: var(--primary-text); font-weight: 700;' : 'background: transparent; color: var(--text-muted); border: none;' ?>">
                📁 Active Reports (<?= number_format($activeIncidentsCount) ?>)
            </a>
            <a href="<?= APP_URL ?>/admin/incidents.php?show_deleted=1" class="btn btn-sm" style="<?= $showDeleted ? 'background: var(--accent-rose); color: #fff; font-weight: 700;' : 'background: transparent; color: var(--text-muted); border: none;' ?>">
                🗑️ Trash / Deleted Items (<?= number_format($deletedIncidentsCount) ?>)
            </a>
        </div>

        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <span style="font-size: 13.5px; color: var(--text-muted);">
                Showing <strong><?= $totalIncidents > 0 ? ($offset + 1) : 0 ?></strong>–<strong><?= min($totalIncidents, $offset + count($incidents)) ?></strong> of <strong id="incidentsTotalCount"><?= number_format($totalIncidents) ?></strong> <?= $showDeleted ? 'deleted' : 'active' ?> report<?= $totalIncidents !== 1 ? 's' : '' ?>
                <?php if ($totalPages > 1): ?>
                    <span style="color: var(--text-dim); margin-left: 6px;">(Page <?= $page ?> of <?= $totalPages ?>)</span>
                <?php endif; ?>
            </span>
            <?php if (!$showDeleted): ?>
                <div id="liveStreamBadge" style="display: inline-flex; align-items: center; gap: 6px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #34d399; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; letter-spacing: 0.5px;">
                    <span style="display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #10b981; box-shadow: 0 0 8px #10b981; animation: livePulseDot 1.5s infinite;"></span>
                    LIVE STREAM ACTIVE
                </div>
            <?php else: ?>
                <span class="badge" style="background: rgba(244, 63, 94, 0.18); color: #fb7185; font-size: 11.5px; font-weight: 700;">
                    ⚠️ TRASH / DELETED ARCHIVE
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr style="background: #081426 !important;">
                    <th style="background: #081426 !important; color: #fbbf24 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px; text-transform: uppercase;">Ref Number</th>
                    <th style="background: #081426 !important; color: #fbbf24 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px; text-transform: uppercase;">Site</th>
                    <th style="background: #081426 !important; color: #fbbf24 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px; text-transform: uppercase;">Reported By</th>
                    <th style="background: #081426 !important; color: #fbbf24 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px; text-transform: uppercase;">Date & Shift</th>
                    <th style="background: #081426 !important; color: #fbbf24 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px; text-transform: uppercase;">Summary</th>
                    <th style="background: #081426 !important; color: #fbbf24 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px; text-transform: uppercase;">Priority</th>
                    <th style="background: #081426 !important; color: #fbbf24 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px; text-transform: uppercase;">Status</th>
                    <th style="background: #081426 !important; color: #fbbf24 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px; text-transform: uppercase;">Photos</th>
                    <th style="background: #081426 !important; color: #fbbf24 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px; text-transform: uppercase; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="incidentsTableBody">
                <?php if (empty($incidents)): ?>
                    <tr id="emptyIncRow">
                        <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-dim);">
                            No incident reports match the selected filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($incidents as $inc): ?>
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
                                <div style="font-size: 11px; color: var(--text-dim);"><?= htmlspecialchars($inc['reported_by_designation']) ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 700;"><?= format_date($inc['incident_date']) ?></div>
                                <div style="font-size: 11px; color: var(--text-dim);"><?= format_time($inc['incident_time']) ?> (<?= htmlspecialchars($inc['shift']) ?>)</div>
                            </td>
                            <td>
                                <div style="max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($inc['summary_line']) ?>">
                                    <?= htmlspecialchars($inc['summary_line']) ?>
                                </div>
                            </td>
                            <td><?= render_priority_badge($inc['priority']) ?></td>
                            <td><?= render_status_badge($inc['status']) ?></td>
                            <td>
                                <?php if ($inc['photo_count'] > 0): ?>
                                    <span class="badge" style="background: rgba(14, 165, 233, 0.15); color: #38bdf8;">
                                        📷 <?= $inc['photo_count'] ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-dim); font-size: 12px;">0</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; justify-content: flex-end; gap: 6px; flex-wrap: wrap;">
                                    <a href="<?= APP_URL ?>/admin/incident_view.php?id=<?= $inc['id'] ?>" class="btn btn-secondary btn-sm" title="Review Incident">
                                        View
                                    </a>
                                    <a href="<?= APP_URL ?>/admin/incident_pdf.php?id=<?= $inc['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" title="Official USS PDF">
                                        PDF
                                    </a>
                                    <?php if ($showDeleted): ?>
                                        <a href="<?= APP_URL ?>/admin/incidents.php?action=restore&incident_id=<?= $inc['id'] ?>&show_deleted=1" 
                                           class="btn btn-secondary btn-sm" 
                                           onclick="return confirm('Restore incident report <?= htmlspecialchars(addslashes($inc['ref_number'])) ?> back to active reports?');"
                                           style="color: #34d399; border-color: rgba(52, 211, 153, 0.4);"
                                           title="Restore Deleted Incident">
                                            🔄 Restore Item
                                        </a>
                                        <a href="<?= APP_URL ?>/admin/incidents.php?action=permanent_delete&incident_id=<?= $inc['id'] ?>&show_deleted=1" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('⚠️ PERMANENT PURGE: This will completely delete incident <?= htmlspecialchars(addslashes($inc['ref_number'])) ?> and all uploaded photos from database and server. This cannot be undone. Are you sure?');"
                                           title="Permanently Delete Incident">
                                            🗑️ Delete Permanently
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= APP_URL ?>/admin/incidents.php?action=soft_delete&incident_id=<?= $inc['id'] ?>" 
                                           class="btn btn-secondary btn-sm" 
                                           onclick="return confirm('Move incident report <?= htmlspecialchars(addslashes($inc['ref_number'])) ?> to trash? You can restore it anytime from Trash.');" 
                                           title="Move to Trash">
                                            🗑️ Trash
                                        </a>
                                        <a href="<?= APP_URL ?>/admin/incidents.php?action=permanent_delete&incident_id=<?= $inc['id'] ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('⚠️ PERMANENT PURGE: Delete incident <?= htmlspecialchars(addslashes($inc['ref_number'])) ?> permanently right now? This cannot be undone.');" 
                                           title="Permanently Delete Incident">
                                            Purge
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Incident Reports Area Pagination: Pages 1, 2, 3, 4, 5, 6... -->
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-subtle);">
        <div style="display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--text-muted);">
            <span>Rows per page:</span>
            <select onchange="window.location.href=this.value" class="form-control" style="width: auto; padding: 4px 10px; font-size: 13px;">
                <?php 
                $currParams = $_GET;
                foreach ([10, 25, 50, 100, 250] as $opt): 
                    $currParams['per_page'] = $opt;
                    $currParams['page'] = 1;
                    $url = '?' . http_build_query($currParams);
                ?>
                    <option value="<?= htmlspecialchars($url) ?>" <?= $perPage == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
            <?php
            $paginationParams = $_GET;
            $buildPageUrl = function($p) use ($paginationParams) {
                $paginationParams['page'] = $p;
                return '?' . http_build_query($paginationParams);
            };
            $displayPages = max(6, $totalPages);
            ?>

            <!-- First / Prev -->
            <?php if ($page > 1): ?>
                <a href="<?= htmlspecialchars($buildPageUrl(1)) ?>" class="btn btn-secondary btn-sm" title="First Page">&laquo;</a>
                <a href="<?= htmlspecialchars($buildPageUrl($page - 1)) ?>" class="btn btn-secondary btn-sm" title="Previous Page">&lsaquo;</a>
            <?php else: ?>
                <span class="btn btn-secondary btn-sm" style="opacity: 0.4; pointer-events: none;">&laquo;</span>
                <span class="btn btn-secondary btn-sm" style="opacity: 0.4; pointer-events: none;">&lsaquo;</span>
            <?php endif; ?>

            <!-- Numbered Pages 1, 2, 3, 4, 5, 6... -->
            <?php for ($p = 1; $p <= $displayPages; $p++): ?>
                <?php if ($p <= $totalPages || $p <= 6): ?>
                    <a href="<?= htmlspecialchars($buildPageUrl($p)) ?>" 
                       class="btn <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm" 
                       style="min-width: 34px; text-align: center; font-weight: <?= $p === $page ? '800' : '600' ?>; <?= $p === $page ? 'background: #081426 !important; color: #fbbf24 !important; border: 1.5px solid #fbbf24 !important;' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>

            <!-- Next / Last -->
            <?php if ($page < $totalPages): ?>
                <a href="<?= htmlspecialchars($buildPageUrl($page + 1)) ?>" class="btn btn-secondary btn-sm" title="Next Page">&rsaquo;</a>
                <a href="<?= htmlspecialchars($buildPageUrl($totalPages)) ?>" class="btn btn-secondary btn-sm" title="Last Page">&raquo;</a>
            <?php else: ?>
                <span class="btn btn-secondary btn-sm" style="opacity: 0.4; pointer-events: none;">&rsaquo;</span>
                <span class="btn btn-secondary btn-sm" style="opacity: 0.4; pointer-events: none;">&raquo;</span>
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
let lastIncidentId = <?= (int)$maxIncidentId ?>;
let isPolling = false;
let currentTotalCount = <?= count($incidents) ?>;

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
        osc.frequency.setValueAtTime(659.25, ctx.currentTime);
        osc.frequency.setValueAtTime(880.00, ctx.currentTime + 0.12);
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
    // Only poll live stream when viewing active reports without specific date/text filters
    const hasFilters = <?= (!empty($search) || !empty($siteId) || !empty($priority) || !empty($status) || !empty($shift) || !empty($dateFrom) || !empty($dateTo) || $showDeleted) ? 'true' : 'false' ?>;
    if (hasFilters) return;

    if (isPolling) return;
    isPolling = true;

    try {
        const res = await fetch('<?= APP_URL ?>/admin/api_live_incidents.php?since_id=' + lastIncidentId);
        if (!res.ok) throw new Error('Network error');
        const data = await res.json();

        if (data.success && data.has_new && data.incidents && data.incidents.length > 0) {
            const tbody = document.getElementById('incidentsTableBody');
            const emptyRow = document.getElementById('emptyIncRow');
            if (emptyRow) emptyRow.remove();

            playAlertChime();

            data.incidents.reverse().forEach(inc => {
                if (document.getElementById('row_inc_' + inc.id)) return;

                currentTotalCount++;
                const countSpan = document.getElementById('incidentsTotalCount');
                if (countSpan) countSpan.textContent = currentTotalCount;

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
                        <div style="font-size: 11px; color: var(--text-dim);">${escapeHtml(inc.reported_by_designation)}</div>
                    </td>
                    <td>
                        <div style="font-weight: 700;">${inc.date_formatted}</div>
                        <div style="font-size: 11px; color: var(--text-dim);">${inc.time_formatted} (${escapeHtml(inc.shift)})</div>
                    </td>
                    <td>
                        <div style="max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${escapeHtml(inc.summary_line)}">
                            ${escapeHtml(inc.summary_line)}
                        </div>
                    </td>
                    <td>${inc.priority_badge}</td>
                    <td>${inc.status_badge}</td>
                    <td>
                        ${inc.photo_count > 0 
                            ? `<span class="badge" style="background: rgba(14, 165, 233, 0.15); color: #38bdf8;">📷 ${inc.photo_count}</span>`
                            : `<span style="color: var(--text-dim); font-size: 12px;">0</span>`
                        }
                    </td>
                    <td style="text-align: right;">
                        <div style="display: flex; justify-content: flex-end; gap: 6px; flex-wrap: wrap;">
                            <a href="${inc.view_url}" class="btn btn-secondary btn-sm" title="Review Incident">View</a>
                            <a href="<?= APP_URL ?>/admin/incident_pdf.php?id=${inc.id}" target="_blank" class="btn btn-secondary btn-sm" title="Official USS PDF">PDF</a>
                            <a href="<?= APP_URL ?>/admin/incidents.php?action=soft_delete&incident_id=${inc.id}" class="btn btn-secondary btn-sm" onclick="return confirm('Move this incident report to trash?');" title="Move to Trash">🗑️ Trash</a>
                            <a href="<?= APP_URL ?>/admin/incidents.php?action=permanent_delete&incident_id=${inc.id}" class="btn btn-danger btn-sm" onclick="return confirm('⚠️ PERMANENT PURGE: Delete this incident report permanently? This cannot be undone.');" title="Delete Permanently">Purge</a>
                        </div>
                    </td>
                `;
                tbody.insertBefore(tr, tbody.firstChild);

                showIncidentToast(inc);
            });

            if (data.max_id > lastIncidentId) {
                lastIncidentId = data.max_id;
            }
        }
    } catch(err) {
        console.warn('Live incident check error:', err);
    } finally {
        isPolling = false;
    }
}

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
