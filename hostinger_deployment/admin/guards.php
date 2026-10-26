<?php
/**
 * Security Guards (Patrol Staff) Management
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$pageTitle = "Security Staff (Guards)";
$pageSubtitle = "Create guard accounts and configure site patrol authorizations";
$db = get_db();
$msg = '';
$msgType = 'success';
$newCredsSlip = null;

// Handle Create Guard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_guard'])) {
    $staffId = strtoupper(trim($_POST['staff_id'] ?? ''));
    $fullName = trim($_POST['full_name'] ?? '');
    $designation = trim($_POST['designation'] ?? 'Security Guard');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $selectedSites = isset($_POST['sites']) ? (array)$_POST['sites'] : [];

    if (empty($staffId) || empty($fullName) || empty($username) || empty($password)) {
        $msg = "Staff ID, Full Name, Username, and Password are required.";
        $msgType = 'danger';
    } else {
        // Check uniqueness
        $chk = $db->prepare("SELECT id FROM guards WHERE staff_id = ? OR username = ? LIMIT 1");
        $chk->execute([$staffId, $username]);
        if ($chk->fetch()) {
            $msg = "A guard account with this Staff ID or Username already exists.";
            $msgType = 'danger';
        } else {
            try {
                $passHash = hash_password_argon2id($password);
                $isSupervisor = !empty($_POST['is_supervisor']) ? 1 : 0;
                $assignedCampId = !empty($_POST['assigned_camp_id']) ? (int)$_POST['assigned_camp_id'] : null;
                $insertGuard = $db->prepare("
                    INSERT INTO guards (staff_id, full_name, designation, is_supervisor, assigned_camp_id, username, password_hash, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
                ");
                $insertGuard->execute([$staffId, $fullName, $designation, $isSupervisor, $assignedCampId, $username, $passHash]);
                $guardId = (int)$db->lastInsertId();

                // Insert site authorizations if selected
                if (!empty($selectedSites)) {
                    $stmtAssign = $db->prepare("INSERT INTO guard_site_assignments (guard_id, site_id) VALUES (?, ?)");
                    foreach ($selectedSites as $sId) {
                        $sId = (int)$sId;
                        if ($sId > 0) {
                            $stmtAssign->execute([$guardId, $sId]);
                        }
                    }
                }

                log_audit('CREATE_GUARD', 'GUARD', $guardId, ['staff_id' => $staffId, 'username' => $username]);
                $msg = "Guard account '{$fullName}' ({$staffId}) created successfully!";
                $msgType = 'success';

                // Prepare credential slip for one-time display
                $newCredsSlip = [
                    'staff_id' => $staffId,
                    'name' => $fullName,
                    'designation' => $designation,
                    'username' => $username,
                    'password' => $password
                ];
            } catch (Exception $e) {
                $msg = "Failed to create guard account: " . $e->getMessage();
                $msgType = 'danger';
            }
        }
    }
}

// Handle Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_guard_password'])) {
    $guardId = (int)$_POST['guard_id'];
    $newPassword = (string)$_POST['new_password'];

    if (!empty($newPassword)) {
        $passHash = hash_password_argon2id($newPassword);
        $db->prepare("UPDATE guards SET password_hash = ?, updated_at = NOW() WHERE id = ?")->execute([$passHash, $guardId]);
        log_audit('RESET_GUARD_PASSWORD', 'GUARD', $guardId, 'Admin reset guard password');
        $msg = "Password for guard successfully updated.";
    }
}

// Handle Edit Guard & Assigned Post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_guard'])) {
    $guardId = (int)$_POST['guard_id'];
    $staffId = strtoupper(trim($_POST['staff_id'] ?? ''));
    $fullName = trim($_POST['full_name'] ?? '');
    $designation = trim($_POST['designation'] ?? 'Security Guard');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $selectedSites = isset($_POST['sites']) ? (array)$_POST['sites'] : [];

    if (empty($staffId) || empty($fullName) || empty($username)) {
        $msg = "Staff ID, Full Name, and Username are required.";
        $msgType = 'danger';
    } else {
        // Check uniqueness for other guards
        $chk = $db->prepare("SELECT id FROM guards WHERE (staff_id = ? OR username = ?) AND id != ? LIMIT 1");
        $chk->execute([$staffId, $username, $guardId]);
        if ($chk->fetch()) {
            $msg = "Another guard account with this Staff ID or Username already exists.";
            $msgType = 'danger';
        } else {
            $isSupervisor = !empty($_POST['is_supervisor']) ? 1 : 0;
            $assignedCampId = !empty($_POST['assigned_camp_id']) ? (int)$_POST['assigned_camp_id'] : null;
            $stmtUp = $db->prepare("
                UPDATE guards 
                SET staff_id = ?, full_name = ?, designation = ?, is_supervisor = ?, assigned_camp_id = ?, username = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmtUp->execute([$staffId, $fullName, $designation, $isSupervisor, $assignedCampId, $username, $guardId]);

            // Sync assigned posts
            $db->prepare("DELETE FROM guard_site_assignments WHERE guard_id = ?")->execute([$guardId]);
            $stmtAssign = $db->prepare("INSERT INTO guard_site_assignments (guard_id, site_id) VALUES (?, ?)");
            foreach ($selectedSites as $sId) {
                $stmtAssign->execute([$guardId, (int)$sId]);
            }

            log_audit('UPDATE_GUARD', 'GUARD', $guardId, ['staff_id' => $staffId, 'assigned_sites' => $selectedSites]);
            $msg = "Guard details and assigned post successfully updated!";
            $msgType = 'success';
        }
    }
}

// Handle Toggle Active
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $guardId = (int)$_GET['id'];
    $db->prepare("UPDATE guards SET is_active = IF(is_active=1, 0, 1), updated_at = NOW() WHERE id = ?")->execute([$guardId]);
    $msg = "Guard account status updated.";
}

// Handle Delete Guard Account
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $guardId = (int)$_GET['id'];
    
    $stmtG = $db->prepare("SELECT staff_id, full_name, username FROM guards WHERE id = ? LIMIT 1");
    $stmtG->execute([$guardId]);
    $gData = $stmtG->fetch();

    if ($gData) {
        try {
            // Delete site authorizations
            $db->prepare("DELETE FROM guard_site_assignments WHERE guard_id = ?")->execute([$guardId]);
            
            // Disassociate from filed incidents (preserves historical incident reports)
            $db->prepare("UPDATE incidents SET guard_id = NULL WHERE guard_id = ?")->execute([$guardId]);

            // Disassociate from supervisor duties / visits if supervisor
            $db->prepare("UPDATE supervisor_duties SET supervisor_id = NULL WHERE supervisor_id = ?")->execute([$guardId]);
            $db->prepare("UPDATE supervisor_visits SET supervisor_id = NULL WHERE supervisor_id = ?")->execute([$guardId]);

            // Delete guard record
            $db->prepare("DELETE FROM guards WHERE id = ?")->execute([$guardId]);

            log_audit('DELETE_GUARD', 'GUARD', $guardId, [
                'staff_id' => $gData['staff_id'],
                'full_name' => $gData['full_name'],
                'username' => $gData['username']
            ]);
            $msg = "Guard account '{$gData['full_name']}' ({$gData['staff_id']}) was permanently deleted.";
            $msgType = 'success';
        } catch (Exception $e) {
            $msg = "Failed to delete guard: " . $e->getMessage();
            $msgType = 'danger';
        }
    } else {
        $msg = "Guard account not found or already deleted.";
        $msgType = 'danger';
    }
}

// Search & Pagination for 2,000+ Guards
$search = trim($_GET['search'] ?? '');
$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(g.staff_id LIKE ? OR g.full_name LIKE ? OR g.username LIKE ?)";
    $like = "%{$search}%";
    $params = [$like, $like, $like];
}
$whereSql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$countStmt = $db->prepare("SELECT COUNT(*) FROM guards g {$whereSql}");
$countStmt->execute($params);
$totalGuards = (int)$countStmt->fetchColumn();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 25);
if (!in_array($perPage, [10, 25, 50, 100, 250])) {
    $perPage = 25;
}
$totalPages = max(1, (int)ceil($totalGuards / $perPage));
if ($page > $totalPages && $totalGuards > 0) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Fetch Paginated Guards
$sql = "
    SELECT g.*,
           (SELECT COUNT(*) FROM incidents i WHERE i.guard_id = g.id AND i.deleted_at IS NULL) as reports_filed,
           (SELECT COUNT(*) FROM guard_site_assignments a WHERE a.guard_id = g.id) as assigned_sites_count,
           (SELECT GROUP_CONCAT(s.code SEPARATOR ', ') 
            FROM guard_site_assignments a 
            JOIN sites s ON s.id = a.site_id 
            WHERE a.guard_id = g.id) as assigned_site_codes,
           (SELECT GROUP_CONCAT(s.name SEPARATOR ' • ') 
            FROM guard_site_assignments a 
            JOIN sites s ON s.id = a.site_id 
            WHERE a.guard_id = g.id) as assigned_site_names,
           (SELECT GROUP_CONCAT(s.id SEPARATOR ',') 
            FROM guard_site_assignments a 
            JOIN sites s ON s.id = a.site_id 
            WHERE a.guard_id = g.id) as assigned_site_ids
    FROM guards g
    {$whereSql}
    ORDER BY g.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$guards = $stmt->fetchAll();

// Fetch active sites for modal
$sites = $db->query("SELECT id, name, code FROM sites WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// Fetch active camps for modal
$camps = $db->query("SELECT id, camp_code, camp_name FROM camps WHERE is_active = 1 ORDER BY camp_name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Credential Slip Banner if newly created -->
<?php if ($newCredsSlip): ?>
    <div class="card" style="margin-bottom: 24px; border: 2px solid #10b981; background: rgba(16, 185, 129, 0.08);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
            <div>
                <h3 style="font-size: 16px; font-weight: 800; color: #34d399;">📋 Handover Credentials Slip</h3>
                <p style="font-size: 13px; color: var(--text-muted);">Please note or print these credentials now to hand over to the security officer.</p>
            </div>
            <button class="btn btn-secondary btn-sm" onclick="window.print()">🖨️ Print Slip</button>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; background: rgba(15, 23, 42, 0.8); padding: 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
            <div>
                <div style="font-size: 11px; color: var(--text-dim); text-transform: uppercase;">Officer Name</div>
                <strong style="color: #fff;"><?= htmlspecialchars($newCredsSlip['name']) ?></strong> (<?= htmlspecialchars($newCredsSlip['staff_id']) ?>)
            </div>
            <div>
                <div style="font-size: 11px; color: var(--text-dim); text-transform: uppercase;">Username</div>
                <strong style="color: var(--accent-cyan); font-family: monospace; font-size: 14px;"><?= htmlspecialchars($newCredsSlip['username']) ?></strong>
            </div>
            <div>
                <div style="font-size: 11px; color: var(--text-dim); text-transform: uppercase;">Password</div>
                <strong style="color: #fbbf24; font-family: monospace; font-size: 14px;"><?= htmlspecialchars($newCredsSlip['password']) ?></strong>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Guards Executive Table Card (Perfect Screen Fit & Reference Styling) -->
<div class="dashboard-table-card">
    <div class="table-banner-header">
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
            <div>
                <h3>Security Staff (Guards &amp; Supervisors)</h3>
                <p>On-duty patrol officers, login credentials, and site checkpoint authorizations</p>
            </div>
            <div class="table-badge-live">
                <span class="pulse-dot"></span>
                <?= number_format($totalGuards) ?> OFFICERS
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <form method="GET" action="" style="display: flex; gap: 6px;">
                <input type="text" name="search" class="form-control" placeholder="Search staff ID, name..." value="<?= htmlspecialchars($search) ?>" style="width: 170px; font-size: 12.5px; padding: 6px 10px; background: rgba(0,0,0,0.3); border-color: rgba(255,209,102,0.3); color: #fff;">
                <?php if ($search !== ''): ?>
                    <a href="<?= APP_URL ?>/admin/guards.php" class="btn btn-secondary btn-sm" style="padding: 6px 10px; font-size: 12px;" title="Clear search">✕</a>
                <?php endif; ?>
                <button type="submit" class="btn btn-secondary btn-sm" style="padding: 6px 12px; font-size: 12px; font-weight: 700;">Search</button>
            </form>

            <button class="btn btn-primary btn-sm" onclick="document.getElementById('addGuardModal').style.display='flex'" style="cursor: pointer;">
                + Create Guard Account
            </button>
        </div>
    </div>

    <div class="table-container" style="overflow-x: auto;">
        <table class="fit-screen-table">
            <thead>
                <tr>
                    <th style="width: 105px;">Staff ID</th>
                    <th>Officer Name &amp; Designation</th>
                    <th style="width: 125px;">Username</th>
                    <th style="width: 135px;">Assigned Posts</th>
                    <th style="width: 90px;">Dossiers</th>
                    <th style="width: 90px;">Status</th>
                    <th style="width: 195px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($guards)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-dim);">
                            No guard accounts created yet. Click "+ Create Guard Account" to add officers.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($guards as $g): 
                        $sitesCount = (int)($g['assigned_sites_count'] ?? (!empty($g['assigned_site_ids']) ? count(explode(',', $g['assigned_site_ids'])) : 0));
                    ?>
                        <tr>
                            <td>
                                <span style="font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #38bdf8; background: rgba(56, 189, 248, 0.12); padding: 3px 8px; border-radius: 4px; display: inline-block; font-size: 12.5px;">
                                    <?= htmlspecialchars($g['staff_id']) ?>
                                </span>
                            </td>
                            <td>
                                <strong style="font-size: 13.5px; color: #fff;"><?= htmlspecialchars($g['full_name']) ?></strong>
                                <div style="font-size: 11.5px; color: #94a3b8; margin-top: 2px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                    <span><?= htmlspecialchars($g['designation']) ?></span>
                                    <?php if (!empty($g['is_supervisor'])): ?>
                                        <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #34d399; font-weight: 800; font-size: 10px; padding: 1px 6px;">Supervisor</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <code style="color: #38bdf8; font-size: 12px; font-family: 'JetBrains Mono', monospace;"><?= htmlspecialchars($g['username']) ?></code>
                            </td>
                            <td>
                                <?php if ($sitesCount > 0): ?>
                                    <span style="color: #38bdf8; font-size: 12.5px; font-weight: 700; background: rgba(56, 189, 248, 0.12); padding: 2px 8px; border-radius: 4px; display: inline-block;" title="<?= htmlspecialchars($g['assigned_site_names'] ?? '') ?>">
                                        <?= $sitesCount ?> <?= $sitesCount === 1 ? 'Site' : 'Sites' ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #f87171; font-size: 11.5px; font-weight: 700;">0 Sites Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: #fff; font-size: 13px;"><?= $g['reports_filed'] ?></strong>
                                <span style="font-size: 11px; color: #64748B;">filed</span>
                            </td>
                            <td>
                                <?php if ($g['is_active']): ?>
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.16); color: #34d399; font-weight: 700; border: 1px solid rgba(16, 185, 129, 0.4); font-size: 11px;">Active</span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(244, 63, 94, 0.16); color: #fb7185; font-weight: 700; border: 1px solid rgba(244, 63, 94, 0.4); font-size: 11px;">Suspended</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <div style="display: inline-flex; align-items: center; justify-content: flex-end; gap: 5px;">
                                    <button type="button" class="btn btn-primary btn-sm" style="padding: 5px 9px; font-size: 12px; border-radius: 6px;" onclick='openEditGuardModal(<?= htmlspecialchars(json_encode($g), ENT_QUOTES, "UTF-8") ?>)' title="Assign Sites &amp; Edit Guard">
                                        ✏️ Edit
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm" style="padding: 5px 9px; font-size: 12px; border-radius: 6px;" onclick="openResetModal(<?= $g['id'] ?>, '<?= htmlspecialchars(addslashes($g['full_name'])) ?>')" title="Reset Password">
                                        🔑 Pass
                                    </button>
                                    <a href="<?= APP_URL ?>/admin/guards.php?action=toggle&id=<?= $g['id'] ?>" class="btn btn-secondary btn-sm" style="padding: 5px 8px; font-size: 12px; border-radius: 6px;" title="<?= $g['is_active'] ? 'Suspend Officer Account' : 'Activate Officer Account' ?>">
                                        <?= $g['is_active'] ? '⏸️' : '▶️' ?>
                                    </a>
                                    <a href="<?= APP_URL ?>/admin/guards.php?action=delete&id=<?= $g['id'] ?>" class="btn btn-secondary btn-sm" onclick="return confirm('Permanently delete guard account <?= htmlspecialchars(addslashes($g['full_name'])) ?> (<?= htmlspecialchars(addslashes($g['staff_id'])) ?>)?\n\nThis will remove their login access permanently.');" style="background: rgba(239, 68, 68, 0.15); color: #fb7185; border: 1px solid rgba(239, 68, 68, 0.3); padding: 5px 8px; font-size: 12px; border-radius: 6px;" title="Permanently Delete Officer Account">
                                        🗑️
                                    </a>
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
            Showing <strong style="color: #fff;"><?= $totalGuards > 0 ? ($offset + 1) : 0 ?>–<?= min($totalGuards, $offset + count($guards)) ?></strong> of <strong style="color: #fff;"><?= number_format($totalGuards) ?></strong> security staff
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
                foreach ([10, 25, 50, 100, 250] as $opt): 
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

<!-- Modal: Create Guard Account -->
<div id="addGuardModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(6px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="max-width: 560px; width: 100%; border-color: rgba(14, 165, 233, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 12px;">
            <h3 style="font-size: 18px; font-weight: 700;">+ Register Security Guard Account</h3>
            <button onclick="document.getElementById('addGuardModal').style.display='none'" style="background: none; border: none; color: #94a3b8; font-size: 20px; cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="create_guard" value="1">

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 12px; margin-bottom: 14px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Staff ID</label>
                    <input type="text" name="staff_id" class="form-control" placeholder="e.g. USS-1045" style="text-transform: uppercase;" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Officer Full Name</label>
                    <input type="text" name="full_name" class="form-control" placeholder="e.g. Muhammad Tariq" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Designation / Role</label>
                <input type="text" name="designation" class="form-control" value="Patrol Security Officer" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Login Username</label>
                    <input type="text" id="guardUsername" name="username" class="form-control" placeholder="e.g. tariq.guard" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <div style="display: flex; justify-content: space-between;">
                        <label class="form-label">Password</label>
                        <a href="javascript:void(0)" onclick="generateGuardPass()" style="font-size: 11px; color: var(--accent-cyan); text-decoration: none;">Auto-Generate</a>
                    </div>
                    <input type="text" id="guardPassword" name="password" class="form-control" placeholder="Min 6 chars" required>
                </div>
            </div>

            <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: var(--radius-sm); padding: 12px; margin-bottom: 14px;">
                <label style="display: flex; align-items: center; gap: 8px; font-weight: 700; cursor: pointer; color: var(--accent-emerald); font-size: 13.5px;">
                    <input type="checkbox" name="is_supervisor" value="1">
                    <span>Field Supervisor Account</span>
                </label>
                <div style="font-size: 11.5px; color: var(--text-dim); margin-top: 4px;">Allows officer to scan Camp QR codes, track site visits, and log daily visit dossiers.</div>
                <div style="margin-top: 8px;">
                    <label class="form-label" style="font-size: 11.5px; margin-bottom: 4px;">Assigned Camp Accommodation</label>
                    <select name="assigned_camp_id" class="form-control" style="font-size: 12px;">
                        <option value="">-- No Camp Assigned --</option>
                        <?php foreach ($camps as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['camp_code']) ?> - <?= htmlspecialchars($c['camp_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Assign Post / Checkpoint Site (From Admin Only)</label>
                <div style="font-size: 11.5px; color: var(--text-dim); margin-bottom: 6px;">The guard enters with their username & password; the system automatically loads their assigned post.</div>
                <input type="text" placeholder="🔍 Quick Search 500 checkpoints..." class="form-control" style="margin-bottom: 8px; font-size: 12px; padding: 6px 10px;" onkeyup="filterSiteCheckboxes(this, 'createSiteList')">
                <div id="createSiteList" style="max-height: 140px; overflow-y: auto; background: var(--bg-input); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 10px; display: flex; flex-direction: column; gap: 8px;">
                    <?php foreach ($sites as $s): ?>
                        <label class="site-item-row" style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="sites[]" value="<?= $s['id'] ?>">
                            <span><?= htmlspecialchars($s['name']) ?> (<strong><?= htmlspecialchars($s['code']) ?></strong>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 24px; border-top: 1px solid var(--border-subtle); padding-top: 16px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">Save Guard Account</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addGuardModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Guard & Assign Post -->
<div id="editGuardModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(6px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="max-width: 560px; width: 100%; border-color: rgba(14, 165, 233, 0.4);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 12px;">
            <h3 style="font-size: 18px; font-weight: 700;">👮 Edit Guard & Assign Post</h3>
            <button onclick="document.getElementById('editGuardModal').style.display='none'" style="background: none; border: none; color: #94a3b8; font-size: 20px; cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="edit_guard" value="1">
            <input type="hidden" id="editGuardId" name="guard_id" value="">

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 12px; margin-bottom: 14px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Staff ID</label>
                    <input type="text" id="editStaffId" name="staff_id" class="form-control" style="text-transform: uppercase;" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Officer Full Name</label>
                    <input type="text" id="editFullName" name="full_name" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Designation / Role</label>
                <input type="text" id="editDesignation" name="designation" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Login Username</label>
                <input type="text" id="editUsername" name="username" class="form-control" required>
            </div>

            <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: var(--radius-sm); padding: 12px; margin-bottom: 14px;">
                <label style="display: flex; align-items: center; gap: 8px; font-weight: 700; cursor: pointer; color: var(--accent-emerald); font-size: 13.5px;">
                    <input type="checkbox" name="is_supervisor" value="1" id="editIsSupervisor">
                    <span>Field Supervisor Account</span>
                </label>
                <div style="font-size: 11.5px; color: var(--text-dim); margin-top: 4px;">Allows officer to scan Camp QR codes, track site visits, and log daily visit dossiers.</div>
                <div style="margin-top: 8px;">
                    <label class="form-label" style="font-size: 11.5px; margin-bottom: 4px;">Assigned Camp Accommodation</label>
                    <select name="assigned_camp_id" id="editAssignedCampId" class="form-control" style="font-size: 12px;">
                        <option value="">-- No Camp Assigned --</option>
                        <?php foreach ($camps as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['camp_code']) ?> - <?= htmlspecialchars($c['camp_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Assigned Post / Checkpoint Site (Admin Assignment)</label>
                <div style="font-size: 11.5px; color: var(--text-dim); margin-bottom: 6px;">Select the post this officer patrols. The guard logs in with their credentials and automatically accesses this post without any dropdown selection.</div>
                <input type="text" placeholder="🔍 Quick Search 500 checkpoints..." class="form-control" style="margin-bottom: 8px; font-size: 12px; padding: 6px 10px;" onkeyup="filterSiteCheckboxes(this, 'editSiteList')">
                <div id="editSiteList" style="max-height: 150px; overflow-y: auto; background: var(--bg-input); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 10px; display: flex; flex-direction: column; gap: 8px;">
                    <?php foreach ($sites as $s): ?>
                        <label class="site-item-row" style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="sites[]" value="<?= $s['id'] ?>" class="edit-site-checkbox" id="edit_site_<?= $s['id'] ?>">
                            <span><?= htmlspecialchars($s['name']) ?> (<strong><?= htmlspecialchars($s['code']) ?></strong>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-top: 24px; border-top: 1px solid var(--border-subtle); padding-top: 16px;">
                <a id="editModalDeleteBtn" href="#" class="btn btn-secondary btn-sm" onclick="return confirm('Permanently delete this guard account? This cannot be undone.');" style="background: rgba(239, 68, 68, 0.15); color: #fb7185; border: 1px solid rgba(239, 68, 68, 0.3); padding: 8px 14px;">
                    🗑️ Delete Guard
                </a>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('editGuardModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes & Assign Post</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Reset Guard Password -->
<div id="resetModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(6px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="max-width: 440px; width: 100%;">
        <h3 style="font-size: 17px; font-weight: 700; margin-bottom: 14px;">Reset Guard Password</h3>
        <p style="font-size: 13px; margin-bottom: 16px;">Set a new password for <strong id="resetGuardName"></strong>:</p>

        <form method="POST" action="">
            <input type="hidden" name="reset_guard_password" value="1">
            <input type="hidden" id="resetGuardId" name="guard_id" value="">

            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label class="form-label" style="margin-bottom: 0;">New Password</label>
                    <button type="button" onclick="document.getElementById('resetNewPassword').value='guard123'" style="background: none; border: none; color: var(--accent-cyan); font-size: 11.5px; font-weight: 700; cursor: pointer; text-decoration: underline;">
                        ⚡ Set to Standard: guard123
                    </button>
                </div>
                <input type="text" id="resetNewPassword" name="new_password" class="form-control" placeholder="Enter new strong password" required minlength="6">
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">Update Password</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('resetModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function generateGuardPass() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$';
    let res = '';
    for (let i = 0; i < 10; i++) {
        res += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('guardPassword').value = res;
}

function openResetModal(id, name) {
    document.getElementById('resetGuardId').value = id;
    document.getElementById('resetGuardName').textContent = name;
    document.getElementById('resetModal').style.display = 'flex';
}

function openEditGuardModal(g) {
    document.getElementById('editGuardId').value = g.id;
    document.getElementById('editStaffId').value = g.staff_id;
    document.getElementById('editFullName').value = g.full_name;
    document.getElementById('editDesignation').value = g.designation;
    document.getElementById('editUsername').value = g.username;
    document.getElementById('editIsSupervisor').checked = (g.is_supervisor == 1);
    document.getElementById('editAssignedCampId').value = g.assigned_camp_id || '';
    document.getElementById('editModalDeleteBtn').href = '<?= APP_URL ?>/admin/guards.php?action=delete&id=' + g.id;

    // Clear all site checkboxes
    document.querySelectorAll('.edit-site-checkbox').forEach(cb => cb.checked = false);

    // Check currently assigned sites
    if (g.assigned_site_ids) {
        const ids = g.assigned_site_ids.split(',');
        ids.forEach(id => {
            const cb = document.getElementById('edit_site_' + id.trim());
            if (cb) cb.checked = true;
        });
    }

    document.getElementById('editGuardModal').style.display = 'flex';
}

function filterSiteCheckboxes(input, containerId) {
    const q = input.value.toLowerCase().trim();
    const container = document.getElementById(containerId);
    if (!container) return;
    const labels = container.querySelectorAll('.site-item-row');
    labels.forEach(label => {
        const text = label.textContent.toLowerCase();
        label.style.display = text.includes(q) ? 'flex' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
