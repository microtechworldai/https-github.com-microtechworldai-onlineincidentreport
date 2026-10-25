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

@ini_set('memory_limit', '512M');
@set_time_limit(300);
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
$allIncidents = [];
$incidents = [];
$totalConsolidatedCount = 0;
$criticalCount = 0;
$highCount = 0;
$medCount = 0;
$lowCount = 0;
$dayCount = 0;
$nightCount = 0;
$perPage = 10;
$page = 1;
$totalPages = 1;
$offset = 0;

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
    $allIncidents = $stmtInc->fetchAll();
    $totalConsolidatedCount = count($allIncidents);

    foreach ($allIncidents as $inc) {
        if ($inc['priority'] === 'Critical') $criticalCount++;
        elseif ($inc['priority'] === 'High') $highCount++;
        elseif ($inc['priority'] === 'Medium') $medCount++;
        elseif ($inc['priority'] === 'Low') $lowCount++;

        if (strtolower($inc['shift']) === 'day') $dayCount++;
        else $nightCount++;
    }

    // Consolidated Incident Pagination (10 per page, buttons 1, 2, 3, 4, 5, 6...)
    $totalPages = max(1, (int)ceil($totalConsolidatedCount / $perPage));
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $perPage;
    $incidents = array_slice($allIncidents, $offset, $perPage);
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
                    Total Recorded Incidents: <strong style="color: <?= $totalConsolidatedCount > 0 ? '#38bdf8' : '#34d399' ?>;"><?= $totalConsolidatedCount ?></strong>
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

    <!-- Quick Stats Grid (Vibrant Colorful Metric Cards) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 14px; margin-bottom: 24px;">
        <!-- 1. TOTAL INCIDENTS (Vibrant Cyan / Sapphire Blue) -->
        <div class="card" style="padding: 16px 12px; text-align: center; background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important; border: 1.5px solid rgba(255,255,255,0.25) !important; border-radius: 12px; box-shadow: 0 8px 20px -3px rgba(2, 132, 199, 0.45); transition: transform 0.2s ease, box-shadow 0.2s ease;">
            <div style="font-size: 11px; text-transform: uppercase; color: #e0f2fe; font-weight: 800; letter-spacing: 0.5px;">Total Incidents</div>
            <div style="font-size: 26px; font-weight: 900; margin-top: 4px; color: #ffffff; text-shadow: 0 2px 4px rgba(0,0,0,0.25);"><?= $totalConsolidatedCount ?></div>
        </div>

        <!-- 2. CRITICAL (Vibrant Crimson Red) -->
        <div class="card" style="padding: 16px 12px; text-align: center; background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%) !important; border: 1.5px solid rgba(255,255,255,0.25) !important; border-radius: 12px; box-shadow: 0 8px 20px -3px rgba(239, 68, 68, 0.45); transition: transform 0.2s ease, box-shadow 0.2s ease;">
            <div style="font-size: 11px; text-transform: uppercase; color: #fee2e2; font-weight: 800; letter-spacing: 0.5px;">Critical</div>
            <div style="font-size: 26px; font-weight: 900; margin-top: 4px; color: #ffffff; text-shadow: 0 2px 4px rgba(0,0,0,0.25);"><?= $criticalCount ?></div>
        </div>

        <!-- 3. HIGH (Vibrant Deep Orange) -->
        <div class="card" style="padding: 16px 12px; text-align: center; background: linear-gradient(135deg, #f97316 0%, #c2410c 100%) !important; border: 1.5px solid rgba(255,255,255,0.25) !important; border-radius: 12px; box-shadow: 0 8px 20px -3px rgba(249, 115, 22, 0.45); transition: transform 0.2s ease, box-shadow 0.2s ease;">
            <div style="font-size: 11px; text-transform: uppercase; color: #ffedd5; font-weight: 800; letter-spacing: 0.5px;">High</div>
            <div style="font-size: 26px; font-weight: 900; margin-top: 4px; color: #ffffff; text-shadow: 0 2px 4px rgba(0,0,0,0.25);"><?= $highCount ?></div>
        </div>

        <!-- 4. MEDIUM (Vibrant Amber / Gold) -->
        <div class="card" style="padding: 16px 12px; text-align: center; background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%) !important; border: 1.5px solid rgba(255,255,255,0.25) !important; border-radius: 12px; box-shadow: 0 8px 20px -3px rgba(245, 158, 11, 0.45); transition: transform 0.2s ease, box-shadow 0.2s ease;">
            <div style="font-size: 11px; text-transform: uppercase; color: #fef3c7; font-weight: 800; letter-spacing: 0.5px;">Medium</div>
            <div style="font-size: 26px; font-weight: 900; margin-top: 4px; color: #ffffff; text-shadow: 0 2px 4px rgba(0,0,0,0.25);"><?= $medCount ?></div>
        </div>

        <!-- 5. LOW (Vibrant Emerald Green) -->
        <div class="card" style="padding: 16px 12px; text-align: center; background: linear-gradient(135deg, #10b981 0%, #047857 100%) !important; border: 1.5px solid rgba(255,255,255,0.25) !important; border-radius: 12px; box-shadow: 0 8px 20px -3px rgba(16, 185, 129, 0.45); transition: transform 0.2s ease, box-shadow 0.2s ease;">
            <div style="font-size: 11px; text-transform: uppercase; color: #d1fae5; font-weight: 800; letter-spacing: 0.5px;">Low</div>
            <div style="font-size: 26px; font-weight: 900; margin-top: 4px; color: #ffffff; text-shadow: 0 2px 4px rgba(0,0,0,0.25);"><?= $lowCount ?></div>
        </div>

        <!-- 6. DAY SHIFT (Vibrant Sky Blue / Teal) -->
        <div class="card" style="padding: 16px 12px; text-align: center; background: linear-gradient(135deg, #0284c7 0%, #0891b2 100%) !important; border: 1.5px solid rgba(255,255,255,0.25) !important; border-radius: 12px; box-shadow: 0 8px 20px -3px rgba(8, 145, 178, 0.45); transition: transform 0.2s ease, box-shadow 0.2s ease;">
            <div style="font-size: 11px; text-transform: uppercase; color: #e0f2fe; font-weight: 800; letter-spacing: 0.5px;">Day Shift</div>
            <div style="font-size: 26px; font-weight: 900; margin-top: 4px; color: #ffffff; text-shadow: 0 2px 4px rgba(0,0,0,0.25);"><?= $dayCount ?></div>
        </div>

        <!-- 7. NIGHT SHIFT (Vibrant Royal Indigo / Purple) -->
        <div class="card" style="padding: 16px 12px; text-align: center; background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%) !important; border: 1.5px solid rgba(255,255,255,0.25) !important; border-radius: 12px; box-shadow: 0 8px 20px -3px rgba(99, 102, 241, 0.45); transition: transform 0.2s ease, box-shadow 0.2s ease;">
            <div style="font-size: 11px; text-transform: uppercase; color: #e0e7ff; font-weight: 800; letter-spacing: 0.5px;">Night Shift</div>
            <div style="font-size: 26px; font-weight: 900; margin-top: 4px; color: #ffffff; text-shadow: 0 2px 4px rgba(0,0,0,0.25);"><?= $nightCount ?></div>
        </div>
    </div>

    <!-- Table of Incidents on that Date -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
            <h3 style="font-size: 16px; font-weight: 800;">
                Consolidated Incidents for <?= date('d-M-Y', strtotime($selectedDate)) ?>
            </h3>
            <span style="font-size: 12.5px; color: var(--text-muted);">
                Showing <strong><?= count($incidents) ?></strong> of <strong><?= $totalConsolidatedCount ?></strong> logged incident<?= $totalConsolidatedCount !== 1 ? 's' : '' ?>
                <?php if ($totalPages > 1): ?>
                    &bull; <span style="color: #fbbf24; font-weight: 700;">Page <?= $page ?> of <?= $totalPages ?></span>
                <?php endif; ?>
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
                                <?php if ($totalConsolidatedCount === 0): ?>
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
                                <?php else: ?>
                                    <div style="font-size: 15px; font-weight: 800; color: #fbbf24; margin-bottom: 4px;">
                                        NO INCIDENTS ON PAGE <?= $page ?>
                                    </div>
                                    <div style="font-size: 13px; color: var(--text-dim); margin-top: 8px;">
                                        There are <?= $totalConsolidatedCount ?> total incidents logged for this date.
                                        <a href="?site_id=<?= urlencode($siteInfo['id']) ?>&date=<?= urlencode($selectedDate) ?>&page=1" class="btn btn-primary btn-sm" style="margin-left: 6px;">Go to Page 1</a>
                                    </div>
                                <?php endif; ?>
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
                                        <?= format_ref_2lines($inc['ref_number']) ?>
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

        <!-- Pagination for Consolidated Incidents: Pages 1, 2, 3, 4, 5, 6... -->
        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--border-subtle);">
            <div style="font-size: 13px; color: var(--text-muted);">
                Showing <strong style="color: #fff;"><?= $totalConsolidatedCount > 0 ? ($offset + 1) : 0 ?></strong>–<strong style="color: #fff;"><?= min($totalConsolidatedCount, $offset + count($incidents)) ?></strong> of <strong style="color: #fff;"><?= number_format($totalConsolidatedCount) ?></strong> consolidated incident<?= $totalConsolidatedCount !== 1 ? 's' : '' ?>
                <?php if ($totalPages > 1): ?>
                    <span style="color: #fbbf24; font-weight: 700; margin-left: 6px;">(Page <?= $page ?> of <?= $totalPages ?>)</span>
                <?php endif; ?>
            </div>

            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                <?php
                $paginationParams = $_GET;
                $buildConsolidatedUrl = function($p) use ($paginationParams) {
                    $paginationParams['page'] = $p;
                    return '?' . http_build_query($paginationParams);
                };
                $displayPages = max(6, $totalPages);
                ?>

                <!-- First / Prev -->
                <?php if ($page > 1): ?>
                    <a href="<?= htmlspecialchars($buildConsolidatedUrl(1)) ?>" class="btn btn-secondary btn-sm" title="First Page" style="font-weight: 700;">&laquo;</a>
                    <a href="<?= htmlspecialchars($buildConsolidatedUrl($page - 1)) ?>" class="btn btn-secondary btn-sm" title="Previous Page" style="font-weight: 700;">&lsaquo; Prev</a>
                <?php else: ?>
                    <span class="btn btn-secondary btn-sm" style="opacity: 0.4; pointer-events: none;">&laquo;</span>
                    <span class="btn btn-secondary btn-sm" style="opacity: 0.4; pointer-events: none;">&lsaquo; Prev</span>
                <?php endif; ?>

                <!-- Numbered Pages 1, 2, 3, 4, 5, 6... -->
                <?php for ($p = 1; $p <= $displayPages; $p++): ?>
                    <?php if ($p <= $totalPages || $p <= 6): ?>
                        <a href="<?= htmlspecialchars($buildConsolidatedUrl($p)) ?>" 
                           class="btn <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm" 
                           style="min-width: 34px; text-align: center; font-weight: <?= $p === $page ? '800' : '600' ?>; <?= $p === $page ? 'background: #081426 !important; color: #fbbf24 !important; border: 1.5px solid #fbbf24 !important;' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <!-- Next / Last -->
                <?php if ($page < $totalPages): ?>
                    <a href="<?= htmlspecialchars($buildConsolidatedUrl($page + 1)) ?>" class="btn btn-secondary btn-sm" title="Next Page" style="font-weight: 700;">Next &rsaquo;</a>
                    <a href="<?= htmlspecialchars($buildConsolidatedUrl($totalPages)) ?>" class="btn btn-secondary btn-sm" title="Last Page" style="font-weight: 700;">&raquo;</a>
                <?php else: ?>
                    <span class="btn btn-secondary btn-sm" style="opacity: 0.4; pointer-events: none;">Next &rsaquo;</span>
                    <span class="btn btn-secondary btn-sm" style="opacity: 0.4; pointer-events: none;">&raquo;</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
