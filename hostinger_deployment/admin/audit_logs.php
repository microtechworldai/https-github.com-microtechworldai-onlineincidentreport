<?php
/**
 * Security & Operational Audit Trail
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$pageTitle = "Audit Trail & Security Logs";
$pageSubtitle = "Immutable record of all administrative actions, logins, and patrol submissions";
$db = get_db();

$search = trim($_GET['search'] ?? '');
$action = trim($_GET['action_filter'] ?? '');

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(actor_name LIKE ? OR ip_address LIKE ? OR details LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like]);
}

if ($action !== '') {
    $where[] = "action = ?";
    $params[] = $action;
}

$whereSql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$sql = "SELECT * FROM audit_logs {$whereSql} ORDER BY created_at DESC LIMIT 150";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Distinct actions for filter
$actionsList = $db->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

// Verify Cryptographic Hash Chain Integrity
$chainCheck = verify_audit_log_chain();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Cryptographic Audit Chain Status Banner -->
<div class="card" style="margin-bottom: 20px; border-left: 4px solid <?= $chainCheck['valid'] ? '#10b981' : '#ef4444' ?>; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
    <div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?= $chainCheck['valid'] ? '#10b981' : '#ef4444' ?>; box-shadow: 0 0 10px <?= $chainCheck['valid'] ? '#10b981' : '#ef4444' ?>;"></span>
            <strong style="color: #fff; font-size: 15px;"><?= $chainCheck['valid'] ? 'Cryptographic Hash Chain: VERIFIED & INTACT' : 'TAMPER WARNING: Chain Broken at Record #' . $chainCheck['broken_at'] ?></strong>
        </div>
        <div style="font-size: 12.5px; color: var(--text-muted); margin-top: 4px;">
            Each audit entry incorporates the cryptographic SHA-256 payload of the preceding entry. Unauthorized database alterations or deletions are automatically flagged.
        </div>
    </div>
    <span class="badge" style="background: <?= $chainCheck['valid'] ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; color: <?= $chainCheck['valid'] ? '#34d399' : '#f87171' ?>; font-size: 12px; font-weight: 700;">
        <?= $chainCheck['total'] ?> Chained Records Verified
    </span>
</div>

<!-- Filter Bar -->
<div class="card" style="margin-bottom: 24px;">
    <form method="GET" action="" style="display: flex; flex-wrap: wrap; align-items: flex-end; gap: 14px;">
        <div style="flex: 2; min-width: 220px;">
            <label class="form-label">Search Actor / IP / Detail</label>
            <input type="text" name="search" class="form-control" placeholder="Search by name, IP address, keyword..." value="<?= htmlspecialchars($search) ?>">
        </div>

        <div style="flex: 1; min-width: 180px;">
            <label class="form-label">Action Type</label>
            <select name="action_filter" class="form-control">
                <option value="">-- All Actions --</option>
                <?php foreach ($actionsList as $act): ?>
                    <option value="<?= htmlspecialchars($act) ?>" <?= $action === $act ? 'selected' : '' ?>><?= htmlspecialchars($act) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="<?= APP_URL ?>/admin/audit_logs.php" class="btn btn-secondary btn-sm">Reset</a>
        </div>
    </form>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Timestamp (GST)</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Target</th>
                    <th>Event Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-dim);">
                            No security audit logs recorded yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td style="font-family: 'JetBrains Mono', monospace; font-size: 11.5px; color: var(--text-dim); white-space: nowrap;">
                                <?= format_datetime($l['created_at'], true) ?>
                            </td>
                            <td>
                                <strong style="font-size: 13px;"><?= htmlspecialchars($l['actor_name'] ?: 'System') ?></strong>
                                <div style="font-size: 10.5px; color: var(--accent-cyan);"><?= htmlspecialchars($l['actor_type']) ?></div>
                            </td>
                            <td>
                                <span class="badge" style="background: rgba(255, 255, 255, 0.06); font-family: monospace; font-size: 11px; border: 1px solid var(--border-subtle);">
                                    <?= htmlspecialchars($l['action']) ?>
                                </span>
                            </td>
                            <td style="font-size: 12px; color: var(--text-muted);">
                                <?= htmlspecialchars($l['target_type'] ?? '-') ?> 
                                <?php if (!empty($l['target_id'])): ?>
                                    <span style="font-family: monospace;">#<?= htmlspecialchars($l['target_id']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="max-width: 320px; font-size: 12px; word-break: break-all;">
                                <?= htmlspecialchars($l['details'] ?? '') ?>
                            </td>
                            <td style="font-family: monospace; font-size: 11.5px; color: var(--text-dim); white-space: nowrap;">
                                <?= htmlspecialchars($l['ip_address'] ?? '127.0.0.1') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
