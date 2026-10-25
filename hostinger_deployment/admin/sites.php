<?php
/**
 * Sites & Checkpoint QR Posters Management
 * Enterprise Support for 500+ Physical Checkpoints
 */
ini_set('memory_limit', '512M');
set_time_limit(300);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$pageTitle = "Patrol Sites & QR Checkpoints";
$pageSubtitle = "Manage physical buildings, facilities, and cryptographic access QR tokens (500+ Sites Supported)";
$db = get_db();

// Self-healing: Ensure required tables and schema compatibility
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `guard_site_assignments` (
      `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `guard_id` INT UNSIGNED NOT NULL,
      `site_id` INT UNSIGNED NOT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY `uniq_guard_site` (`guard_id`,`site_id`),
      INDEX `idx_assignment_guard` (`guard_id`),
      INDEX `idx_assignment_site` (`site_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (\Throwable $e) {}

$msg = '';
$msgType = 'success';

// Handle Add New Site
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_site'])) {
    $name = trim($_POST['name'] ?? '');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $type = trim($_POST['type'] ?? 'Commercial');
    $address = trim($_POST['address'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');
    $clientName = trim($_POST['client_name'] ?? '');
    $clientContact = trim($_POST['client_contact'] ?? '');

    if (empty($name) || empty($code)) {
        $msg = "Site Name and Site Code are required.";
        $msgType = 'danger';
    } else {
        // Check uniqueness of code
        $checkStmt = $db->prepare("SELECT id FROM sites WHERE code = ? LIMIT 1");
        $checkStmt->execute([$code]);
        if ($checkStmt->fetch()) {
            $msg = "A site with code '{$code}' already exists. Please choose a unique code.";
            $msgType = 'danger';
        } else {
            $tokenSecret = create_qr_token_secret('SITE_');
            $supervisorTokenSecret = create_qr_token_secret('SUP_');
            $insertStmt = $db->prepare("
                INSERT INTO sites (
                    name, code, type, address, latitude, longitude, client_name, client_contact,
                    active_qr_token, active_qr_token_hash, active_qr_token_encrypted,
                    supervisor_qr_token, supervisor_qr_token_hash, supervisor_qr_token_encrypted,
                    is_active, created_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $insertStmt->execute([
                $name, $code, $type, $address, $latitude, $longitude, $clientName, $clientContact,
                $tokenSecret['token'], $tokenSecret['hash'], $tokenSecret['encrypted'],
                $supervisorTokenSecret['token'], $supervisorTokenSecret['hash'], $supervisorTokenSecret['encrypted']
            ]);
            log_audit('CREATE_SITE', 'SITE', $code, ['name' => $name, 'code' => $code, 'lat' => $latitude, 'lng' => $longitude]);
            $msg = "Site '{$name}' created successfully with active cryptographic QR tokens for Guard and Supervisor.";
        }
    }
}

// Handle Update Site (Edit Existing Site & Coordinates)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_site'])) {
    $siteId = (int)($_POST['site_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $type = trim($_POST['type'] ?? 'Commercial');
    $address = trim($_POST['address'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');
    $clientName = trim($_POST['client_name'] ?? '');
    $clientContact = trim($_POST['client_contact'] ?? '');

    if (empty($name) || empty($code) || !$siteId) {
        $msg = "Site Name, Code, and valid ID are required.";
        $msgType = 'danger';
    } else {
        $checkStmt = $db->prepare("SELECT id FROM sites WHERE code = ? AND id != ? LIMIT 1");
        $checkStmt->execute([$code, $siteId]);
        if ($checkStmt->fetch()) {
            $msg = "Another site with code '{$code}' already exists.";
            $msgType = 'danger';
        } else {
            $updateStmt = $db->prepare("
                UPDATE sites 
                SET name = ?, code = ?, type = ?, address = ?, latitude = ?, longitude = ?, client_name = ?, client_contact = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $updateStmt->execute([$name, $code, $type, $address, $latitude, $longitude, $clientName, $clientContact, $siteId]);
            log_audit('UPDATE_SITE', 'SITE', $siteId, ['name' => $name, 'code' => $code, 'lat' => $latitude, 'lng' => $longitude]);
            $msg = "Site '{$name}' updated successfully with updated coordinates.";
            $msgType = 'success';
        }
    }
}

// Handle QR token rotation as an authenticated, CSRF-protected state change.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['regenerate_qr'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        $msg = "The form expired. Refresh the page and try again.";
        $msgType = 'danger';
    } else {
        $siteId = (int)($_POST['site_id'] ?? 0);
        $qrType = ($_POST['qr_type'] ?? 'guard') === 'supervisor' ? 'supervisor' : 'guard';
        $tokenSecret = create_qr_token_secret($qrType === 'supervisor' ? 'SUP_' : 'SITE_');
        if ($qrType === 'supervisor') {
            $db->prepare("UPDATE sites SET supervisor_qr_token = ?, supervisor_qr_token_hash = ?, supervisor_qr_token_encrypted = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$tokenSecret['token'], $tokenSecret['hash'], $tokenSecret['encrypted'], $siteId]);
        } else {
            $db->prepare("UPDATE sites SET active_qr_token = ?, active_qr_token_hash = ?, active_qr_token_encrypted = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$tokenSecret['token'], $tokenSecret['hash'], $tokenSecret['encrypted'], $siteId]);
        }
        log_audit('REGENERATE_SITE_QR', 'SITE', $siteId, ['type' => $qrType, 'token' => 'replaced']);
        $msg = ucfirst($qrType) . " QR token regenerated successfully.";
    }
}

// Handle Toggle Active
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $siteId = (int)$_GET['id'];
    $db->prepare("UPDATE sites SET is_active = IF(is_active=1, 0, 1), updated_at = NOW() WHERE id = ?")->execute([$siteId]);
    $msg = "Site status updated.";
}

// Handle Delete Site
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $siteId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT name, code FROM sites WHERE id = ?");
    $stmt->execute([$siteId]);
    $site = $stmt->fetch();
    if ($site) {
        $db->prepare("DELETE FROM guard_site_assignments WHERE site_id = ?")->execute([$siteId]);
        $db->prepare("DELETE FROM sites WHERE id = ?")->execute([$siteId]);
        log_audit('DELETE_SITE', 'SITE', $siteId, ['name' => $site['name'], 'code' => $site['code']]);
        $msg = "Site '" . htmlspecialchars($site['name']) . "' (" . htmlspecialchars($site['code']) . ") has been deleted.";
        $msgType = 'success';
    }
}

// Search & Pagination for 500+ Sites
$search = trim($_GET['search'] ?? '');
$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(s.name LIKE ? OR s.code LIKE ? OR s.client_name LIKE ? OR s.address LIKE ?)";
    $like = "%{$search}%";
    $params = [$like, $like, $like, $like];
}
$whereSql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$countStmt = $db->prepare("SELECT COUNT(*) FROM sites s {$whereSql}");
$countStmt->execute($params);
$totalSites = (int)$countStmt->fetchColumn();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 25);
if (!in_array($perPage, [10, 25, 50, 100, 250, 500])) {
    $perPage = 25;
}
$totalPages = max(1, (int)ceil($totalSites / $perPage));
if ($page > $totalPages && $totalSites > 0) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Fetch Paginated Sites using B-Tree index scan
$guardCountSql = "0 as guard_count";
try {
    $checkGsa = $db->query("SHOW TABLES LIKE 'guard_site_assignments'")->fetch();
    if ($checkGsa) {
        $guardCountSql = "(SELECT COUNT(*) FROM guard_site_assignments a WHERE a.site_id = s.id) as guard_count";
    }
} catch (\Throwable $e) {}

$sql = "
    SELECT s.*,
           " . $guardCountSql . ",
           (SELECT COUNT(*) FROM incidents i WHERE i.site_id = s.id AND i.deleted_at IS NULL) as incident_count
    FROM sites s
    {$whereSql}
    ORDER BY s.id ASC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$sites = $stmt->fetchAll();

// Check for active high-speed Cloudflare HTTPS tunnel
$publicTunnelUrl = get_active_tunnel_url();

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Sites Executive Table Card (Perfect Screen Fit & Reference Styling) -->
<div class="dashboard-table-card">
    <div class="table-banner-header">
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
            <div>
                <h3>Patrol Sites &amp; QR Checkpoints</h3>
                <p>Manage physical checkpoints, GPS coordinates, and cryptographic access QR posters</p>
            </div>
            <div class="table-badge-live">
                <span class="pulse-dot"></span>
                <?= number_format($totalSites) ?> CHECKPOINTS
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <form method="GET" action="" style="display: flex; gap: 6px;">
                <input type="text" name="search" class="form-control" placeholder="Search site, code, client..." value="<?= htmlspecialchars($search) ?>" style="width: 170px; font-size: 12.5px; padding: 6px 10px; background: rgba(0,0,0,0.3); border-color: rgba(255,209,102,0.3); color: #fff;">
                <?php if ($search !== ''): ?>
                    <a href="<?= APP_URL ?>/admin/sites.php" class="btn btn-secondary btn-sm" style="padding: 6px 10px; font-size: 12px;" title="Clear search">✕</a>
                <?php endif; ?>
                <button type="submit" class="btn btn-secondary btn-sm" style="padding: 6px 12px; font-size: 12px; font-weight: 700;">Search</button>
            </form>

            <a href="<?= APP_URL ?>/admin/bulk_qr_posters.php" class="btn btn-sm" style="background: rgba(255,255,255,0.08); color: #e2e8f0; font-weight: 700; border: 1px solid rgba(255,255,255,0.2); padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12.5px; display: inline-flex; align-items: center; gap: 4px;">
                🖨️ Bulk Posters
            </a>

            <button class="btn btn-sm" onclick="document.getElementById('addSiteModal').style.display='flex'" style="background: #fbbf24; color: #081426; font-weight: 800; border: none; padding: 7px 14px; border-radius: 6px; cursor: pointer; font-size: 12.5px; display: inline-flex; align-items: center; gap: 4px;">
                + Add New Site
            </button>
        </div>
    </div>

    <?php if (!empty($publicTunnelUrl)): ?>
        <div style="margin-bottom: 16px; border-left: 4px solid var(--accent-emerald); padding: 10px 14px; background: rgba(16, 185, 129, 0.08); border-radius: 6px; display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
            <div style="font-size: 12.5px; color: #e2e8f0;">
                <strong style="color: #10b981;">🌐 Mobile QR Scanning Active:</strong> QR posters automatically encode HTTPS link (<strong><?= htmlspecialchars($publicTunnelUrl) ?></strong>).
            </div>
            <a href="<?= htmlspecialchars($publicTunnelUrl) ?>/g/index.php" target="_blank" class="btn btn-primary btn-sm" style="padding: 4px 10px; font-size: 11.5px; white-space: nowrap;">
                📱 Test Phone Link &rarr;
            </a>
        </div>
    <?php endif; ?>

    <div class="table-container" style="overflow-x: auto;">
        <table class="fit-screen-table">
            <thead>
                <tr>
                    <th style="width: 105px;">Site Code</th>
                    <th>Site / Facility Name</th>
                    <th style="width: 120px;">Classification</th>
                    <th style="width: 85px;">Guards</th>
                    <th style="width: 85px;">Reports</th>
                    <th style="width: 90px;">Status</th>
                    <th style="width: 210px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sites)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-dim);">
                            No patrol sites configured. Click "+ Add New Site" to populate your checkpoints.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sites as $s): 
                        $safeSiteData = [
                            'id' => (int)$s['id'],
                            'name' => (string)$s['name'],
                            'code' => (string)$s['code'],
                            'type' => (string)$s['type'],
                            'address' => (string)($s['address'] ?? ''),
                            'latitude' => (string)($s['latitude'] ?? ''),
                            'longitude' => (string)($s['longitude'] ?? ''),
                            'client_name' => (string)($s['client_name'] ?? ''),
                            'client_contact' => (string)($s['client_contact'] ?? '')
                        ];
                        
                        $siteGuardToken = get_stored_qr_token($s, 'active_qr_token_encrypted', 'active_qr_token');
                        $siteGuardUrl = $siteGuardToken !== '' ? APP_URL . '/g/index.php?token=' . urlencode($siteGuardToken) : '';
                        $siteSupToken = get_stored_qr_token($s, 'supervisor_qr_token_encrypted', 'supervisor_qr_token');
                    ?>
                        <tr>
                            <td>
                                <span style="font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #38bdf8; background: rgba(56, 189, 248, 0.12); padding: 3px 8px; border-radius: 4px; display: inline-block; font-size: 12.5px;">
                                    <?= htmlspecialchars($s['code']) ?>
                                </span>
                                <div style="font-size: 11px; color: #64748B; margin-top: 2px; font-family: monospace;">#<?= $s['id'] ?></div>
                            </td>
                            <td>
                                <strong style="font-size: 13.5px; color: #fff;"><?= htmlspecialchars($s['name']) ?></strong>
                                <?php if (!empty($s['address'])): ?>
                                    <div style="font-size: 11.5px; color: #94a3b8; margin-top: 2px;"><?= htmlspecialchars($s['address']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($s['client_name'])): ?>
                                    <div style="font-size: 11px; color: #38bdf8; margin-top: 2px;">👤 <?= htmlspecialchars($s['client_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background: rgba(255, 255, 255, 0.08); color: #e2e8f0; font-size: 11.5px;">
                                    <?= htmlspecialchars($s['type']) ?>
                                </span>
                            </td>
                            <td>
                                <strong style="color: #fff; font-size: 13px;"><?= $s['guard_count'] ?></strong>
                                <span style="font-size: 11px; color: #64748B;">guards</span>
                            </td>
                            <td>
                                <strong style="color: #fff; font-size: 13px;"><?= $s['incident_count'] ?></strong>
                                <span style="font-size: 11px; color: #64748B;">dossiers</span>
                            </td>
                            <td>
                                <?php if ($s['is_active']): ?>
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.16); color: #34d399; font-weight: 700; border: 1px solid rgba(16, 185, 129, 0.4); font-size: 11px;">Active</span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(244, 63, 94, 0.16); color: #fb7185; font-weight: 700; border: 1px solid rgba(244, 63, 94, 0.4); font-size: 11px;">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <div style="display: inline-flex; align-items: center; justify-content: flex-end; gap: 5px;">
                                    <?php if ($siteGuardUrl !== ''): ?>
                                    <button type="button" class="btn btn-sm" style="background: #0284c7; color: #fff; font-weight: 700; padding: 5px 9px; font-size: 12px; border-radius: 6px;" onclick='openQuickQrModal(<?= json_encode([
                                        "id" => $s["id"],
                                        "name" => $s["name"],
                                        "code" => $s["code"],
                                        "latitude" => $s["latitude"] ?? "",
                                        "longitude" => $s["longitude"] ?? "",
                                        "url" => $siteGuardUrl
                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View & Download Site QR Code">
                                        📱 QR
                                    </button>
                                    <a href="<?= APP_URL ?>/admin/qr_poster.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-primary btn-sm" style="padding: 5px 9px; font-size: 12px; border-radius: 6px;" title="Print A4 Guard Checkpoint Poster">
                                        🖨️ Poster
                                    </a>
                                    <?php endif; ?>

                                    <button type="button" class="btn btn-secondary btn-sm" style="padding: 5px 9px; font-size: 12px; border-radius: 6px;" onclick='openEditSiteModal(<?= json_encode($safeSiteData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Site Details">
                                        ✏️
                                    </button>

                                    <div class="action-dropdown-wrapper">
                                        <button type="button" class="btn btn-secondary btn-sm action-dropdown-trigger" style="padding: 5px 8px; font-size: 12px; border-radius: 6px;" title="More Actions">
                                            ⋮
                                        </button>
                                        <div class="action-dropdown-menu">
                                            <?php if (!empty($siteSupToken)): ?>
                                            <a href="<?= APP_URL ?>/admin/qr_poster.php?id=<?= (int)$s['id'] ?>&amp;type=supervisor" target="_blank">
                                                📋 Print Supervisor Poster
                                            </a>
                                            <?php endif; ?>
                                            <a href="<?= APP_URL ?>/admin/daily_report.php?site_id=<?= $s['id'] ?>">
                                                📊 Sitewise Daily Report
                                            </a>
                                            <form method="post" onsubmit="return confirm('Regenerate the guard QR?');" style="margin: 0;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                                                <input type="hidden" name="site_id" value="<?= (int)$s['id'] ?>">
                                                <input type="hidden" name="qr_type" value="guard">
                                                <button type="submit" name="regenerate_qr">🔄 Regenerate Guard QR</button>
                                            </form>
                                            <form method="post" onsubmit="return confirm('Regenerate the supervisor QR?');" style="margin: 0;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                                                <input type="hidden" name="site_id" value="<?= (int)$s['id'] ?>">
                                                <input type="hidden" name="qr_type" value="supervisor">
                                                <button type="submit" name="regenerate_qr">🔄 Regenerate Supervisor QR</button>
                                            </form>
                                            <a href="<?= APP_URL ?>/admin/sites.php?action=toggle&id=<?= $s['id'] ?>">
                                                <?= $s['is_active'] ? '⏸️ Disable Site' : '▶️ Enable Site' ?>
                                            </a>
                                            <a href="<?= APP_URL ?>/admin/sites.php?action=delete&id=<?= $s['id'] ?>" onclick="return confirm('⚠️ Are you sure you want to delete site \'<?= htmlspecialchars(addslashes($s['name'])) ?>\'?');" style="color: #fb7185 !important;">
                                                🗑️ Delete Site
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer Matching Reference -->
    <div class="pagination-container">
        <div style="color: #94a3b8; font-size: 13px;">
            Showing <strong style="color: #fff;"><?= $totalSites > 0 ? ($offset + 1) : 0 ?>–<?= min($totalSites, $offset + count($sites)) ?></strong> of <strong style="color: #fff;"><?= number_format($totalSites) ?></strong> patrol checkpoints
        </div>
        
        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
            <?php
            $paginationParams = $_GET;
            $buildPageUrl = function($p) use ($paginationParams) {
                $paginationParams['page'] = $p;
                return '?' . http_build_query($paginationParams);
            };
            ?>

            <span style="font-size: 12px; color: #64748B; margin-right: 2px;">Rows:</span>
            <select onchange="window.location.href=this.value" class="form-control" style="width: auto; padding: 4px 8px; font-size: 12px; background: rgba(0,0,0,0.3); color: #fff; border-color: rgba(255,209,102,0.25); border-radius: 6px; margin-right: 8px;">
                <?php 
                $currParams = $_GET;
                foreach ([10, 25, 50, 100, 250, 500] as $opt): 
                    $currParams['per_page'] = $opt;
                    $currParams['page'] = 1;
                    $url = '?' . http_build_query($currParams);
                ?>
                    <option value="<?= htmlspecialchars($url) ?>" <?= $perPage == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Prev -->
            <?php if ($page > 1): ?>
                <a href="<?= htmlspecialchars($buildPageUrl($page - 1)) ?>" class="pagination-pill">&lsaquo; Prev</a>
            <?php else: ?>
                <span class="pagination-pill disabled">&lsaquo; Prev</span>
            <?php endif; ?>

            <!-- Page Number Pills -->
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 3);
            if ($endPage - $startPage < 5) {
                if ($startPage === 1) {
                    $endPage = min($totalPages, $startPage + 5);
                } else {
                    $startPage = max(1, $endPage - 5);
                }
            }
            for ($p = $startPage; $p <= $endPage; $p++):
            ?>
                <a href="<?= htmlspecialchars($buildPageUrl($p)) ?>" class="pagination-pill <?= $p === $page ? 'active' : '' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>

            <!-- Next -->
            <?php if ($page < $totalPages): ?>
                <a href="<?= htmlspecialchars($buildPageUrl($page + 1)) ?>" class="pagination-pill">Next &rsaquo;</a>
            <?php else: ?>
                <span class="pagination-pill disabled">Next &rsaquo;</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Universal dropdown listener
document.addEventListener('click', function(e) {
    var isTrigger = e.target.closest('.action-dropdown-trigger');
    var openDropdown = document.querySelector('.action-dropdown-wrapper.open');
    if (isTrigger) {
        var wrapper = isTrigger.closest('.action-dropdown-wrapper');
        if (openDropdown && openDropdown !== wrapper) {
            openDropdown.classList.remove('open');
        }
        wrapper.classList.toggle('open');
        e.stopPropagation();
    } else if (openDropdown && !e.target.closest('.action-dropdown-menu')) {
        openDropdown.classList.remove('open');
    }
});
</script>

<!-- Modal: Add New Patrol Site -->
<div id="addSiteModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(6px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="max-width: 540px; width: 100%; border-color: rgba(14, 165, 233, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 12px;">
            <h3 style="font-size: 18px; font-weight: 700;">+ Register New Patrol Site</h3>
            <button onclick="document.getElementById('addSiteModal').style.display='none'" style="background: none; border: none; color: #94a3b8; font-size: 20px; cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="create_site" value="1">

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Site Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Burj Crown Tower" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Code (Unique)</label>
                    <input type="text" name="code" class="form-control" placeholder="e.g. USS-DXB-001" style="text-transform: uppercase;" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Site Classification</label>
                <select name="type" class="form-control">
                    <option value="Commercial">Commercial Tower</option>
                    <option value="Residential">Residential Community</option>
                    <option value="Hospital">Hospital / Healthcare Facility</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Address / Dubai Location</label>
                <input type="text" name="address" class="form-control" placeholder="e.g. Downtown Dubai, UAE">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Latitude</label>
                    <input type="text" name="latitude" class="form-control" placeholder="e.g. 25.1610° N or 25.1610">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Longitude</label>
                    <input type="text" name="longitude" class="form-control" placeholder="e.g. 55.4120° E or 55.4120">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Client Name</label>
                    <input type="text" name="client_name" class="form-control" placeholder="e.g. Emaar Properties">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Client Contact Info</label>
                    <input type="text" name="client_contact" class="form-control" placeholder="e.g. ops@emaar.ae">
                </div>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 24px; border-top: 1px solid var(--border-subtle); padding-top: 16px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">Create Site & Generate QR</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addSiteModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Patrol Site & Coordinates -->
<div id="editSiteModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(6px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="max-width: 540px; width: 100%; border-color: rgba(14, 165, 233, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 12px;">
            <h3 style="font-size: 18px; font-weight: 700;">✏️ Edit Patrol Site</h3>
            <button onclick="document.getElementById('editSiteModal').style.display='none'" style="background: none; border: none; color: #94a3b8; font-size: 20px; cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="update_site" value="1">
            <input type="hidden" name="site_id" id="edit_site_id" value="">

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Site Name</label>
                    <input type="text" name="name" id="edit_site_name" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Code (Unique)</label>
                    <input type="text" name="code" id="edit_site_code" class="form-control" style="text-transform: uppercase;" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Site Classification</label>
                <select name="type" id="edit_site_type" class="form-control">
                    <option value="Commercial">Commercial Tower</option>
                    <option value="Residential">Residential Community</option>
                    <option value="Hospital">Hospital / Healthcare Facility</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Address / Dubai Location</label>
                <input type="text" name="address" id="edit_site_address" class="form-control">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Latitude</label>
                    <input type="text" name="latitude" id="edit_site_latitude" class="form-control" placeholder="e.g. 25.1610° N or 25.1610">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Longitude</label>
                    <input type="text" name="longitude" id="edit_site_longitude" class="form-control" placeholder="e.g. 55.4120° E or 55.4120">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Client Name</label>
                    <input type="text" name="client_name" id="edit_site_client_name" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Client Contact Info</label>
                    <input type="text" name="client_contact" id="edit_site_client_contact" class="form-control">
                </div>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 24px; border-top: 1px solid var(--border-subtle); padding-top: 16px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">Save Changes</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('editSiteModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Quick Site QR Code Modal -->
<div id="quickQrModal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="max-width: 440px; width: 100%; text-align: center; background: #0f172a; border: 1px solid #334155; border-radius: 16px; padding: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.7);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
            <h3 id="modalQrTitle" style="margin: 0; font-size: 17px; color: #38bdf8; font-weight: 800;">Site Checkpoint QR</h3>
            <button type="button" onclick="document.getElementById('quickQrModal').style.display='none'" style="background: none; border: none; color: #94a3b8; font-size: 24px; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        
        <div style="background: #ffffff; padding: 14px; border-radius: 12px; display: inline-block; box-shadow: 0 8px 24px rgba(0,0,0,0.25); margin-bottom: 14px;">
            <canvas id="modalQrCanvas" width="300" height="300" style="display: block; width: 230px; height: 230px; border-radius: 6px; margin: 0 auto;"></canvas>
            <div id="modalQrUnavailable" style="display:none; width:230px; height:230px; align-items:center; justify-content:center; color:#b91c1c; font-weight:700;">QR could not be rendered.</div>
        </div>
        
        <div id="modalQrCodeBadge" style="font-family: monospace; font-weight: 800; font-size: 13.5px; color: #38bdf8; margin-bottom: 8px; background: rgba(56, 189, 248, 0.1); padding: 4px 12px; border-radius: 6px; display: inline-block;"></div>
        <div id="modalQrGpsBadge" style="font-size: 12px; font-weight: 700; color: #38bdf8; margin-bottom: 12px; display: none;"></div>
        
        <div style="margin-bottom: 16px; text-align: left;">
            <label style="display: block; font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; margin-bottom: 6px;">Direct Guard Patrol Link</label>
            <div style="display: flex; gap: 6px;">
                <input type="text" id="modalQrUrl" class="form-control" readonly style="font-size: 11px; font-family: monospace; background: #1e293b; color: #cbd5e1; border: 1px solid #475569;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="copyModalQrLink()" style="white-space: nowrap;">📋 Copy</button>
            </div>
        </div>
        
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a id="modalPrintPosterLink" href="#" target="_blank" class="btn btn-primary btn-sm" style="flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 10px; font-weight: 700;">
                🖨️ A4 Poster
            </a>
            <button type="button" class="btn btn-sm" onclick="downloadModalQrPng()" style="flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 10px; background: #10b981; color: white; font-weight: 700; border: none; border-radius: 8px; cursor: pointer;">
                ⬇️ Download PNG
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('quickQrModal').style.display='none'">
                Close
            </button>
        </div>
    </div>
</div>

<script src="<?= APP_URL ?>/assets/js/qrcode.min.js"></script>
<script>
let currentModalQr = null;

function openQuickQrModal(data) {
    currentModalQr = data;
    document.getElementById('modalQrTitle').textContent = data.name;
    document.getElementById('modalQrCodeBadge').textContent = 'SITE CODE: ' + data.code;
    const gpsEl = document.getElementById('modalQrGpsBadge');
    if (data.latitude || data.longitude) {
        gpsEl.style.display = 'block';
        gpsEl.textContent = '📍 GPS: ' + (data.latitude || 'N/A') + ' | ' + (data.longitude || 'N/A');
    } else {
        gpsEl.style.display = 'none';
    }
    document.getElementById('modalQrUrl').value = data.url;
    const canvas = document.getElementById('modalQrCanvas');
    const unavailable = document.getElementById('modalQrUnavailable');
    canvas.style.display = 'block';
    unavailable.style.display = 'none';
    if (typeof QRCode !== 'undefined' && typeof QRCode.toCanvas === 'function') {
        QRCode.toCanvas(canvas, data.url, { width: 300, margin: 8 }, function(error) {
            if (error) {
                canvas.style.display = 'none';
                unavailable.style.display = 'flex';
            }
        });
    } else {
        canvas.style.display = 'none';
        unavailable.style.display = 'flex';
    }
    document.getElementById('modalPrintPosterLink').href = '<?= APP_URL ?>/admin/qr_poster.php?id=' + data.id;
    document.getElementById('quickQrModal').style.display = 'flex';
}

function copyModalQrLink() {
    const input = document.getElementById('modalQrUrl');
    if (navigator.clipboard) {
        navigator.clipboard.writeText(input.value).then(function() {
            alert('Copied site patrol URL to clipboard:\n' + input.value);
        });
    } else {
        input.select();
        document.execCommand('copy');
        alert('Copied site patrol URL!');
    }
}

function downloadModalQrPng() {
    if (!currentModalQr) return;
    const canvas = document.getElementById('modalQrCanvas');
    if (canvas.style.display === 'none') {
        alert('QR could not be rendered. Use the direct link above.');
        return;
    }
    const a = document.createElement('a');
    a.href = canvas.toDataURL('image/png');
    a.download = 'QR_Checkpoint_' + currentModalQr.code + '.png';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function openEditSiteModal(site) {
    document.getElementById('edit_site_id').value = site.id || '';
    document.getElementById('edit_site_name').value = site.name || '';
    document.getElementById('edit_site_code').value = site.code || '';
    document.getElementById('edit_site_type').value = site.type || 'Commercial';
    document.getElementById('edit_site_address').value = site.address || '';
    document.getElementById('edit_site_latitude').value = site.latitude || '';
    document.getElementById('edit_site_longitude').value = site.longitude || '';
    document.getElementById('edit_site_client_name').value = site.client_name || '';
    document.getElementById('edit_site_client_contact').value = site.client_contact || '';
    document.getElementById('editSiteModal').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
