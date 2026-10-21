<?php
/**
 * Super Admin Security & Tamper-Evidence Oversight Command
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$pageTitle = "Security & Governance";
$pageSubtitle = "Cryptographic integrity, credential protection, and tamper evidence controls";
$db = get_db();

// Verify Audit Log Cryptographic Chain
$chainStatus = verify_audit_log_chain();

// Check Backup Status
$backupFiles = [];
if (is_dir(BACKUPS_PATH)) {
    $files = scandir(BACKUPS_PATH);
    foreach ($files as $f) {
        if (strpos($f, '.sql') !== false) {
            $backupFiles[] = [
                'name' => $f,
                'size' => filesize(BACKUPS_PATH . '/' . $f),
                'time' => filemtime(BACKUPS_PATH . '/' . $f)
            ];
        }
    }
}
usort($backupFiles, fn($a, $b) => $b['time'] <=> $a['time']);
$latestBackup = !empty($backupFiles) ? $backupFiles[0] : null;

// Fetch sensitive audit events
$stmtSens = $db->query("
    SELECT * FROM audit_logs 
    WHERE action IN ('CREATE_GUARD', 'RESET_GUARD_PASSWORD', 'REGENERATE_SITE_QR', 'PERMANENT_DELETE_INCIDENT', 'ADMIN_LOGIN')
    ORDER BY id DESC LIMIT 8
");
$sensitiveEvents = $stmtSens->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <!-- Card 1: Audit Chain Integrity -->
    <div class="card" style="border-left: 4px solid <?= $chainStatus['valid'] ? '#10b981' : '#ef4444' ?>;">
        <div style="font-size: 11px; text-transform: uppercase; color: var(--text-dim); font-weight: 700; letter-spacing: 0.5px;">Cryptographic Audit Chain</div>
        <div style="display: flex; align-items: center; gap: 8px; margin-top: 6px;">
            <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?= $chainStatus['valid'] ? '#10b981' : '#ef4444' ?>; box-shadow: 0 0 10px <?= $chainStatus['valid'] ? '#10b981' : '#ef4444' ?>;"></span>
            <strong style="font-size: 16px; color: #fff;"><?= $chainStatus['valid'] ? 'Chain Verified & Intact' : 'TAMPER DETECTED' ?></strong>
        </div>
        <p style="font-size: 12.5px; color: var(--text-muted); margin-top: 6px;">
            <?= $chainStatus['total'] ?> chained records cryptographically linked via SHA-256 HMAC payload signatures.
        </p>
    </div>

    <!-- Card 2: Password & Token Protection -->
    <div class="card" style="border-left: 4px solid #38bdf8;">
        <div style="font-size: 11px; text-transform: uppercase; color: var(--text-dim); font-weight: 700; letter-spacing: 0.5px;">Credential Storage</div>
        <div style="display: flex; align-items: center; gap: 8px; margin-top: 6px;">
            <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #38bdf8; box-shadow: 0 0 10px #38bdf8;"></span>
            <strong style="font-size: 16px; color: #fff;">Argon2id + Cryptographic Pepper</strong>
        </div>
        <p style="font-size: 12.5px; color: var(--text-muted); margin-top: 6px;">
            Passwords salted and peppered outside database. Checkpoint QR tokens indexed via HMAC-SHA256 and encrypted with AES-256-GCM.
        </p>
    </div>

    <!-- Card 3: Storage & Access Control -->
    <div class="card" style="border-left: 4px solid #a855f7;">
        <div style="font-size: 11px; text-transform: uppercase; color: var(--text-dim); font-weight: 700; letter-spacing: 0.5px;">Private Storage</div>
        <div style="display: flex; align-items: center; gap: 8px; margin-top: 6px;">
            <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #a855f7; box-shadow: 0 0 10px #a855f7;"></span>
            <strong style="font-size: 16px; color: #fff;">Private Evidence Store Active</strong>
        </div>
        <p style="font-size: 12.5px; color: var(--text-muted); margin-top: 6px;">
            Direct web browsing denied on evidence files. Documents served strictly via authenticated Super Admin gateway.
        </p>
    </div>
</div>

<!-- Strict System Policies Overview -->
<div class="card" style="margin-bottom: 24px;">
    <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 12px; color: #fff;">🛡️ Active Security Policies (Strict Admin-Only Control)</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px;">
        <div style="background: rgba(15, 23, 42, 0.6); padding: 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
            <div style="font-weight: 700; color: #38bdf8; font-size: 13px;">🔒 Automatic Trash Purge</div>
            <div style="font-size: 12px; color: #34d399; font-weight: 700; margin-top: 4px;">● DISABLED (Strict Policy)</div>
            <div style="font-size: 12px; color: var(--text-dim); margin-top: 4px;">Incidents in trash are never auto-deleted. Only manual Super Admin purge is permitted.</div>
        </div>

        <div style="background: rgba(15, 23, 42, 0.6); padding: 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
            <div style="font-weight: 700; color: #38bdf8; font-size: 13px;">📧 Scheduled Email Dispatch</div>
            <div style="font-size: 12px; color: #fbbf24; font-weight: 700; margin-top: 4px;">● OFF BY DEFAULT</div>
            <div style="font-size: 12px; color: var(--text-dim); margin-top: 4px;">Client dossiers are sent only upon explicit manual Super Admin command.</div>
        </div>

        <div style="background: rgba(15, 23, 42, 0.6); padding: 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
            <div style="font-weight: 700; color: #38bdf8; font-size: 13px;">💾 Disaster Recovery & Backups</div>
            <div style="font-size: 12px; color: #34d399; font-weight: 700; margin-top: 4px;">● PRESERVATION ENFORCED</div>
            <div style="font-size: 12px; color: var(--text-dim); margin-top: 4px;">
                Latest snapshot: <?= $latestBackup ? htmlspecialchars($latestBackup['name']) . ' (' . format_datetime(date('Y-m-d H:i:s', $latestBackup['time'])) . ')' : 'No manual backups yet' ?>
            </div>
        </div>
    </div>
</div>

<!-- Sensitive Audit Events -->
<div class="card">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 800; color: #fff; margin: 0;">Recent Privileged Administrative Actions</h3>
            <p style="font-size: 12.5px; color: var(--text-muted); margin: 2px 0 0 0;">Cryptographically chained audit trail of sensitive operations</p>
        </div>
        <a href="<?= APP_URL ?>/admin/audit_logs.php" class="btn btn-secondary btn-sm">Full Audit Trail &rarr;</a>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Target</th>
                    <th>Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sensitiveEvents)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 24px; color: var(--text-dim);">No sensitive actions recorded yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sensitiveEvents as $ev): ?>
                        <tr>
                            <td style="font-family: monospace; font-size: 12px; color: var(--text-muted);">
                                <?= format_datetime($ev['created_at'], true) ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($ev['actor_name'] ?: 'Super Admin') ?></strong>
                                <span class="badge" style="background: rgba(14, 165, 233, 0.15); color: #38bdf8; font-size: 10px;"><?= htmlspecialchars($ev['actor_type']) ?></span>
                            </td>
                            <td>
                                <code style="color: var(--accent-cyan); font-weight: 700;"><?= htmlspecialchars($ev['action']) ?></code>
                            </td>
                            <td><?= htmlspecialchars($ev['target_type'] . ($ev['target_id'] ? " #{$ev['target_id']}" : '')) ?></td>
                            <td style="font-size: 12px; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= htmlspecialchars($ev['details'] ?? '-') ?>
                            </td>
                            <td style="font-family: monospace; font-size: 11.5px; color: var(--text-dim);"><?= htmlspecialchars($ev['ip_address']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
