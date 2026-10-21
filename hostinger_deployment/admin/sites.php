<?php
/**
 * Sites & Checkpoint QR Posters Management
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$pageTitle = "Patrol Sites & QR Checkpoints";
$pageSubtitle = "Manage physical buildings, facilities, and access QR tokens";
$db = get_db();
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
            $cleanCode = preg_replace('/[^a-z0-9]/', '', strtolower($code));
            $token = 'uss_qr_' . ($cleanCode ?: 'site') . '_' . bin2hex(random_bytes(10));
            $insertStmt = $db->prepare("
                INSERT INTO sites (name, code, type, address, latitude, longitude, client_name, client_contact, active_qr_token, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $insertStmt->execute([$name, $code, $type, $address, $latitude, $longitude, $clientName, $clientContact, $token]);
            log_audit('CREATE_SITE', 'SITE', $code, ['name' => $name, 'code' => $code, 'lat' => $latitude, 'lng' => $longitude]);
            $msg = "Site '{$name}' created successfully with active cryptographic QR token and GPS coordinates.";
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

// Handle Regenerate QR Token (Manual Super Admin Action Only)
if (isset($_GET['action']) && $_GET['action'] === 'regen_qr' && isset($_GET['id'])) {
    $siteId = (int)$_GET['id'];
    $newToken = 'uss_qr_' . bin2hex(random_bytes(14));
    $db->prepare("UPDATE sites SET active_qr_token = ?, updated_at = NOW() WHERE id = ?")->execute([$newToken, $siteId]);
    log_audit('REGENERATE_SITE_QR', 'SITE', $siteId, 'Cryptographic QR token revoked and replaced by Super Admin');
    $msg = "QR Code token regenerated. Any previous physical poster has been invalidated and revoked.";
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
if (!in_array($perPage, [10, 25, 50, 100, 250])) {
    $perPage = 25;
}
$totalPages = max(1, (int)ceil($totalSites / $perPage));
if ($page > $totalPages && $totalSites > 0) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Fetch Paginated Sites using B-Tree index scan
$sql = "
    SELECT s.*,
           (SELECT COUNT(*) FROM guard_site_assignments a WHERE a.site_id = s.id) as guard_count,
           (SELECT COUNT(*) FROM incidents i WHERE i.site_id = s.id AND i.deleted_at IS NULL) as incident_count
    FROM sites s
    {$whereSql}
    ORDER BY s.name ASC
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

<!-- Top Action -->
<div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px; margin-bottom: 20px;">
    <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
        <span style="color: var(--text-muted); font-size: 14px;">
            Showing <strong><?= $totalSites > 0 ? ($offset + 1) : 0 ?></strong>–<strong><?= min($totalSites, $offset + count($sites)) ?></strong> of <strong><?= number_format($totalSites) ?></strong> registered checkpoints
            <?php if ($totalPages > 1): ?>
                <span style="color: var(--text-dim); margin-left: 6px;">(Page <?= $page ?> of <?= $totalPages ?>)</span>
            <?php endif; ?>
        </span>
    </div>

    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <form method="GET" action="" style="display: flex; gap: 8px;">
            <input type="text" name="search" class="form-control" placeholder="Search site name, code, client..." value="<?= htmlspecialchars($search) ?>" style="width: 250px; font-size: 13px; padding: 6px 12px;">
            <?php if ($search !== ''): ?>
                <a href="<?= APP_URL ?>/admin/sites.php" class="btn btn-secondary btn-sm" title="Clear search">✕</a>
            <?php endif; ?>
            <button type="submit" class="btn btn-secondary btn-sm">Search</button>
        </form>
        <button class="btn btn-primary" onclick="document.getElementById('addSiteModal').style.display='flex'">
            + Add New Patrol Site
        </button>
    </div>
</div>

<?php 
$lanIps = get_lan_ips();
$lanHost = !empty($lanIps) ? $lanIps[0] . ':8080' : 'localhost:8080';
?>

<?php if (!empty($publicTunnelUrl)): ?>
    <div class="card" style="margin-bottom: 20px; border-left: 4px solid var(--accent-emerald); padding: 14px 18px; background: rgba(16, 185, 129, 0.05);">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
            <div>
                <strong style="color: #10b981;">⚡ Public Mobile QR Scanning Active (Cloudflare HTTPS):</strong>
                <span style="font-size: 13px; color: var(--text-main);"> Checkpoint QR posters automatically encode your public HTTPS link (<strong><?= htmlspecialchars($publicTunnelUrl) ?></strong>). Any smartphone on 4G/5G or Wi-Fi connects instantly with camera & GPS support.</span>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="<?= htmlspecialchars($publicTunnelUrl) ?>/g/index.php" target="_blank" class="btn btn-primary btn-sm" style="white-space: nowrap;">
                    📲 Test Phone Link (HTTPS) &rarr;
                </a>
                <a href="http://<?= htmlspecialchars($lanHost) ?>/g/index.php" target="_blank" class="btn btn-secondary btn-sm" style="white-space: nowrap;">
                    Local Wi-Fi Link &rarr;
                </a>
            </div>
        </div>
    </div>
<?php elseif (strpos(APP_URL, 'localhost') !== false || strpos(APP_URL, '127.0.0.1') !== false): ?>
    <div class="card" style="margin-bottom: 20px; border-left: 4px solid var(--accent-cyan); padding: 14px 18px;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
            <div>
                <strong>📱 Mobile Phone QR Scanning:</strong>
                <span style="font-size: 13px;">Ensure your phone is connected to the same Wi-Fi. The QR poster points to your PC's Wi-Fi IP (<strong>http://<?= htmlspecialchars($lanHost) ?></strong>) instead of localhost so your mobile phone connects immediately.</span>
            </div>
            <a href="http://<?= htmlspecialchars($lanHost) ?>/g/index.php" target="_blank" class="btn btn-secondary btn-sm" style="white-space: nowrap;">
                Test Phone Link &rarr;
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- Sites Table -->
<div class="card">
    <div class="table-container">
        <table class="data-table sites-table">
            <thead>
                <tr style="background: #081426 !important;">
                    <th style="background: #081426 !important; color: #FFD166 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px;">Site Code</th>
                    <th style="background: #081426 !important; color: #FFD166 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px;">Site / Facility Name</th>
                    <th style="background: #081426 !important; color: #FFD166 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px;">Facility Type</th>
                    <th style="background: #081426 !important; color: #FFD166 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px;">Assigned Guards</th>
                    <th style="background: #081426 !important; color: #FFD166 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px;">Total Reports</th>
                    <th style="background: #081426 !important; color: #FFD166 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px;">Status</th>
                    <th style="background: #081426 !important; color: #FFD166 !important; font-weight: 800 !important; font-size: 12px; letter-spacing: 0.6px; text-align: right;">QR Poster & Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sites)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-dim);">
                            No patrol sites configured. Click "+ Add New Patrol Site" to create your first checkpoint.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sites as $s): ?>
                        <tr>
                            <td>
                                <span style="font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--accent-cyan); background: rgba(6, 182, 212, 0.1); padding: 3px 8px; border-radius: 4px;">
                                    <?= htmlspecialchars($s['code']) ?>
                                </span>
                            </td>
                            <td>
                                <strong style="font-size: 14px;"><?= htmlspecialchars($s['name']) ?></strong>
                                <?php if (!empty($s['address'])): ?>
                                    <div style="font-size: 11px; color: var(--text-dim); margin-top: 2px;"><?= htmlspecialchars($s['address']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($s['latitude']) || !empty($s['longitude'])): ?>
                                    <div style="font-size: 11px; color: var(--accent-cyan); margin-top: 3px; font-family: monospace;">
                                        📍 Lat: <?= htmlspecialchars($s['latitude']) ?>, Long: <?= htmlspecialchars($s['longitude']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background: rgba(255, 255, 255, 0.05); color: inherit;">
                                    <?= htmlspecialchars($s['type']) ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-weight: 700;"><?= $s['guard_count'] ?></span>
                                <span style="font-size: 11px; color: var(--text-dim);"> guards</span>
                            </td>
                            <td>
                                <span style="font-weight: 700;"><?= $s['incident_count'] ?></span>
                                <span style="font-size: 11px; color: var(--text-dim);"> dossiers</span>
                            </td>
                            <td>
                                <?php if ($s['is_active']): ?>
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">Active</span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(244, 63, 94, 0.15); color: #fb7185;">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; justify-content: flex-end; gap: 6px; flex-wrap: wrap;">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick='openEditSiteModal(<?= json_encode($s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Site Details & Coordinates">
                                        ✏️ Edit
                                    </button>
                                    <a href="<?= APP_URL ?>/admin/daily_report.php?site_id=<?= $s['id'] ?>" class="btn btn-secondary btn-sm" title="Sitewise Daily Security Incident Consolidated Report">
                                        📑 Report
                                    </a>
                                    <?php 
                                        $siteToken = !empty($s['token_encrypted']) ? decrypt_field($s['token_encrypted']) : $s['active_qr_token'];
                                        $siteGuardUrl = APP_URL . '/g/index.php?token=' . urlencode($siteToken);
                                    ?>
                                    <button type="button" class="btn btn-sm" style="background: #0284c7; color: #fff; font-weight: 700;" onclick='openQuickQrModal(<?= json_encode([
                                        "id" => $s["id"],
                                        "name" => $s["name"],
                                        "code" => $s["code"],
                                        "url" => $siteGuardUrl
                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View & Download Site QR Code">
                                        📱 QR Code
                                    </button>
                                    <a href="<?= APP_URL ?>/admin/qr_poster.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-primary btn-sm" title="Print A4 Checkpoint QR Poster">
                                        🖨️ Poster
                                    </a>
                                    <a href="<?= APP_URL ?>/admin/sites.php?action=regen_qr&id=<?= $s['id'] ?>" class="btn btn-secondary btn-sm" onclick="return confirm('Regenerate QR token? This will permanently invalidate the old physical poster.');" title="Regenerate QR">
                                        🔄 Token
                                    </a>
                                    <a href="<?= APP_URL ?>/admin/sites.php?action=toggle&id=<?= $s['id'] ?>" class="btn btn-secondary btn-sm">
                                        <?= $s['is_active'] ? 'Disable' : 'Enable' ?>
                                    </a>
                                    <a href="<?= APP_URL ?>/admin/sites.php?action=delete&id=<?= $s['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('⚠️ Are you sure you want to delete site \'<?= htmlspecialchars(addslashes($s['name'])) ?>\'? This will remove the site and revoke its checkpoint QR tokens.');" title="Delete Site">
                                        Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1 || $totalSites > 25): ?>
        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-subtle);">
            <div style="display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--text-muted);">
                <span>Rows per page:</span>
                <select onchange="window.location.href=this.value" class="form-control" style="width: auto; padding: 4px 10px; font-size: 13px;">
                    <?php 
                    $currParams = $_GET;
                    foreach ([25, 50, 100, 250] as $opt): 
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
                ?>

                <!-- First / Prev -->
                <?php if ($page > 1): ?>
                    <a href="<?= htmlspecialchars($buildPageUrl(1)) ?>" class="btn btn-secondary btn-sm" title="First Page">&laquo;</a>
                    <a href="<?= htmlspecialchars($buildPageUrl($page - 1)) ?>" class="btn btn-secondary btn-sm" title="Previous Page">&lsaquo;</a>
                <?php else: ?>
                    <span class="btn btn-secondary btn-sm" style="opacity: 0.4; pointer-events: none;">&laquo;</span>
                    <span class="btn btn-secondary btn-sm" style="opacity: 0.4; pointer-events: none;">&lsaquo;</span>
                <?php endif; ?>

                <!-- Window of numbered pages -->
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                if ($startPage > 1) {
                    echo '<span style="padding: 0 4px; color: var(--text-dim);">...</span>';
                }
                for ($p = $startPage; $p <= $endPage; $p++):
                ?>
                    <a href="<?= htmlspecialchars($buildPageUrl($p)) ?>" class="btn <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm" style="min-width: 32px; text-align: center;">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
                <?php if ($endPage < $totalPages): ?>
                    <span style="padding: 0 4px; color: var(--text-dim);">...</span>
                <?php endif; ?>

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
    <?php endif; ?>
</div>

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
                    <input type="text" name="code" class="form-control" placeholder="e.g. BUR01" style="text-transform: uppercase;" required>
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
            <img id="modalQrImg" src="" alt="Site QR Code" width="230" height="230" style="display: block; border-radius: 6px; margin: 0 auto;" crossOrigin="anonymous">
        </div>
        
        <div id="modalQrCodeBadge" style="font-family: monospace; font-weight: 800; font-size: 13.5px; color: #38bdf8; margin-bottom: 14px; background: rgba(56, 189, 248, 0.1); padding: 4px 12px; border-radius: 6px; display: inline-block;"></div>
        
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
                💾 Download PNG
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('quickQrModal').style.display='none'">
                Close
            </button>
        </div>
    </div>
</div>

<script>
let currentModalQr = null;

function openQuickQrModal(data) {
    currentModalQr = data;
    document.getElementById('modalQrTitle').textContent = data.name;
    document.getElementById('modalQrCodeBadge').textContent = 'SITE CODE: ' + data.code;
    document.getElementById('modalQrUrl').value = data.url;
    document.getElementById('modalQrImg').src = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=8&data=' + encodeURIComponent(data.url);
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
    const a = document.createElement('a');
    a.href = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&margin=10&data=' + encodeURIComponent(currentModalQr.url);
    a.download = 'QR_Checkpoint_' + currentModalQr.code + '.png';
    a.target = '_blank';
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

