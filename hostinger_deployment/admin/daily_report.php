<?php
/**
 * Sitewise Daily Security Incident Consolidated Report
 * Ultimate Security Services (USS)
 * Select site and date -> generates consolidated incident dossier (Web / PDF / Excel)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$pageTitle = "DAILY SECURITY INCIDENT CONSOLIDATED REPORT";
$pageSubtitle = "Sitewise Daily Security Incident Consolidated Report & Dossier Generator";
$db = get_db();

$selectedSiteId = trim($_GET['site_id'] ?? '');
$selectedDate = trim($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

// Fetch active sites
$sites = $db->query("SELECT id, name, code, type, address, client_name, client_contact FROM sites WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

if (!$selectedSiteId && !empty($sites)) {
    $selectedSiteId = $sites[0]['id'];
}

$siteInfo = null;
$incidents = [];
$criticalCount = 0;
$highCount = 0;
$medCount = 0;
$lowCount = 0;
$dayCount = 0;
$nightCount = 0;

if ($selectedSiteId) {
    $stmtSite = $db->prepare("SELECT * FROM sites WHERE id = ? LIMIT 1");
    $stmtSite->execute([$selectedSiteId]);
    $siteInfo = $stmtSite->fetch();

    $stmtInc = $db->prepare("
        SELECT i.*,
               (SELECT COUNT(*) FROM incident_photos p WHERE p.incident_id = i.id) as photo_count
        FROM incidents i
        WHERE i.site_id = ? AND i.incident_date = ? AND i.deleted_at IS NULL
        ORDER BY i.incident_time ASC
    ");
    $stmtInc->execute([$selectedSiteId, $selectedDate]);
    $incidents = $stmtInc->fetchAll();

    foreach ($incidents as $inc) {
        if ($inc['priority'] === 'Critical') $criticalCount++;
        elseif ($inc['priority'] === 'High') $highCount++;
        elseif ($inc['priority'] === 'Medium') $medCount++;
        elseif ($inc['priority'] === 'Low') $lowCount++;

        if (strtolower($inc['shift']) === 'day') $dayCount++;
        else $nightCount++;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Selector Card -->
<div class="card" style="margin-bottom: 24px;">
    <form method="GET" action="" style="display: flex; flex-wrap: wrap; align-items: flex-end; gap: 16px;">
        <div style="flex: 2; min-width: 220px;">
            <label class="form-label" style="font-weight: 700;">Select Patrol Site / Facility</label>
            <select name="site_id" class="form-control" onchange="this.form.submit()">
                <?php foreach ($sites as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $selectedSiteId == $s['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['code']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 1; min-width: 180px;">
            <label class="form-label" style="font-weight: 700;">Report Date</label>
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()">
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">Load Consolidated Report</button>
        </div>
    </form>
</div>

<?php if ($siteInfo): ?>
    <!-- Dossier Header & Action Banner -->
    <div class="card" style="margin-bottom: 24px; border-left: 4px solid var(--accent-cyan);">
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px;">
            <div>
                <span style="font-size: 11px; text-transform: uppercase; color: var(--accent-cyan); font-weight: 800; letter-spacing: 1px;">
                    Sitewise Consolidated Dossier
                </span>
                <h2 style="font-size: 20px; font-weight: 800; margin-top: 4px; color: #fff;">
                    <?= htmlspecialchars($siteInfo['name']) ?> &bull; <?= format_date($selectedDate) ?>
                </h2>
                <p style="font-size: 13px; color: var(--text-muted); margin-top: 2px;">
                    Client: <strong><?= htmlspecialchars($siteInfo['client_name'] ?: 'Corporate Client') ?></strong> &bull; 
                    Contact: <?= htmlspecialchars($siteInfo['client_contact'] ?: 'ops@' . strtolower($siteInfo['code']) . '.ae') ?> &bull; 
                    Total Recorded Incidents: <strong style="color: <?= count($incidents) > 0 ? '#38bdf8' : '#34d399' ?>;"><?= count($incidents) ?></strong>
                    <?php if (!empty($siteInfo['latitude']) || !empty($siteInfo['longitude'])): ?>
                        &bull; <span style="color: #38bdf8; font-weight: 600;">📍 GPS: <?= htmlspecialchars($siteInfo['latitude'] ?? '') ?>, <?= htmlspecialchars($siteInfo['longitude'] ?? '') ?></span>
                    <?php endif; ?>
                </p>
            </div>

            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                <a href="<?= APP_URL ?>/admin/report_site_daily_incident_pdf.php?site_id=<?= $siteInfo['id'] ?>&date=<?= $selectedDate ?>" target="_blank" class="btn btn-primary btn-sm" style="font-weight: 800; background: #0284c7; border-color: #0284c7;">
                    📄 Generate Consolidated PDF
                </a>
                <a href="<?= APP_URL ?>/api/export_excel.php?site_id=<?= $siteInfo['id'] ?>&date_from=<?= $selectedDate ?>&date_to=<?= $selectedDate ?>" class="btn btn-secondary btn-sm">
                    📊 Export Excel (.csv)
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 14px; margin-bottom: 24px;">
        <div class="card" style="padding: 14px; text-align: center; border-left: 3px solid #38bdf8;">
            <div style="font-size: 11px; text-transform: uppercase; color: var(--text-dim); font-weight: 700;">Total Incidents</div>
            <div style="font-size: 24px; font-weight: 900; margin-top: 2px;"><?= count($incidents) ?></div>
        </div>
        <div class="card" style="padding: 14px; text-align: center; border-left: 3px solid #f87171;">
            <div style="font-size: 11px; text-transform: uppercase; color: #f87171; font-weight: 700;">Critical</div>
            <div style="font-size: 24px; font-weight: 900; margin-top: 2px; color: #f87171;"><?= $criticalCount ?></div>
        </div>
        <div class="card" style="padding: 14px; text-align: center; border-left: 3px solid #fb923c;">
            <div style="font-size: 11px; text-transform: uppercase; color: #fb923c; font-weight: 700;">High</div>
            <div style="font-size: 24px; font-weight: 900; margin-top: 2px; color: #fb923c;"><?= $highCount ?></div>
        </div>
        <div class="card" style="padding: 14px; text-align: center; border-left: 3px solid #facc15;">
            <div style="font-size: 11px; text-transform: uppercase; color: #facc15; font-weight: 700;">Medium</div>
            <div style="font-size: 24px; font-weight: 900; margin-top: 2px; color: #facc15;"><?= $medCount ?></div>
        </div>
        <div class="card" style="padding: 14px; text-align: center; border-left: 3px solid #4ade80;">
            <div style="font-size: 11px; text-transform: uppercase; color: #4ade80; font-weight: 700;">Low</div>
            <div style="font-size: 24px; font-weight: 900; margin-top: 2px; color: #4ade80;"><?= $lowCount ?></div>
        </div>
        <div class="card" style="padding: 14px; text-align: center; border-left: 3px solid #94a3b8;">
            <div style="font-size: 11px; text-transform: uppercase; color: var(--text-dim); font-weight: 700;">Day Shift</div>
            <div style="font-size: 24px; font-weight: 900; margin-top: 2px;"><?= $dayCount ?></div>
        </div>
        <div class="card" style="padding: 14px; text-align: center; border-left: 3px solid #818cf8;">
            <div style="font-size: 11px; text-transform: uppercase; color: #818cf8; font-weight: 700;">Night Shift</div>
            <div style="font-size: 24px; font-weight: 900; margin-top: 2px; color: #818cf8;"><?= $nightCount ?></div>
        </div>
    </div>

    <!-- Table of Incidents on that Date -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="font-size: 16px; font-weight: 800;">
                Consolidated Incidents for <?= date('d-M-Y', strtotime($selectedDate)) ?>
            </h3>
            <span style="font-size: 12.5px; color: var(--text-muted);">
                Showing all <strong><?= count($incidents) ?></strong> logged incident<?= count($incidents) !== 1 ? 's' : '' ?>
            </span>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Time (24h)</th>
                        <th>Ref Number</th>
                        <th>Shift</th>
                        <th>Reporting Officer</th>
                        <th>Priority</th>
                        <th>Incident Summary</th>
                        <th>Status</th>
                        <th>Photos</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($incidents)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 45px 20px;">
                                <div style="font-size: 28px; margin-bottom: 8px;">🛡️</div>
                                <div style="font-size: 15px; font-weight: 800; color: #34d399; margin-bottom: 4px;">
                                    NIL INCIDENTS LOGGED &bull; ALL PATROLS CLEAR
                                </div>
                                <div style="font-size: 13px; color: var(--text-dim); max-width: 500px; margin: 0 auto;">
                                    No security incidents or disciplinary infractions were logged for <strong><?= htmlspecialchars($siteInfo['name']) ?></strong> on <strong><?= format_date($selectedDate) ?></strong>.
                                </div>
                                <div style="margin-top: 14px;">
                                    <a href="<?= APP_URL ?>/admin/report_site_daily_incident_pdf.php?site_id=<?= $siteInfo['id'] ?>&date=<?= $selectedDate ?>" target="_blank" class="btn btn-secondary btn-sm">
                                        📄 Print NIL Incidents Certificate (PDF)
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($incidents as $inc): ?>
                            <tr>
                                <td>
                                    <strong style="font-family: monospace; font-size: 13px;"><?= format_time($inc['incident_time']) ?></strong>
                                </td>
                                <td>
                                    <a href="<?= APP_URL ?>/admin/incident_view.php?id=<?= $inc['id'] ?>" style="color: var(--accent-cyan); font-family: monospace; font-weight: 700; text-decoration: none;">
                                        <?= htmlspecialchars($inc['ref_number']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge" style="background: rgba(255,255,255,0.06); color: inherit;">
                                        <?= htmlspecialchars($inc['shift']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($inc['reported_by_name']) ?></td>
                                <td><?= render_priority_badge($inc['priority']) ?></td>
                                <td>
                                    <div style="max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600;" title="<?= htmlspecialchars($inc['summary_line']) ?>">
                                        <?= htmlspecialchars($inc['summary_line'] ?: 'No summary provided') ?>
                                    </div>
                                </td>
                                <td><?= render_status_badge($inc['status']) ?></td>
                                <td>
                                    <?php if ($inc['photo_count'] > 0): ?>
                                        <span class="badge" style="background: rgba(14, 165, 233, 0.15); color: #38bdf8;">📷 <?= $inc['photo_count'] ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--text-dim); font-size: 11px;">0</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; justify-content: flex-end; gap: 6px;">
                                        <a href="<?= APP_URL ?>/admin/incident_view.php?id=<?= $inc['id'] ?>" class="btn btn-secondary btn-sm" title="Inspect Incident">
                                            Inspect
                                        </a>
                                        <a href="<?= APP_URL ?>/admin/incident_pdf.php?id=<?= $inc['id'] ?>" target="_blank" class="btn btn-primary btn-sm" title="Print Official Incident Report PDF" style="padding: 5px 9px;">
                                            📄 PDF
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
