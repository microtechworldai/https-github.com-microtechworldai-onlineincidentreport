<?php
/**
 * Supervisor Shift & Visit History
 * Displays past 30 days of patrol shifts and site visit reports (Read-only)
 * Ultimate Security Services (USS)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['uss_supervisor_logged_in']) || empty($_SESSION['supervisor_id'])) {
    header("Location: " . APP_URL . "/sup/login.php");
    exit;
}
require_supervisor_auth();

$db = get_db();
$settings = get_system_settings();
$supervisorId = (int)$_SESSION['supervisor_id'];

// Get supervisor info
$supStmt = $db->prepare("SELECT * FROM guards WHERE id = ? LIMIT 1");
$supStmt->execute([$supervisorId]);
$supervisor = $supStmt->fetch();

// Fetch past 30 shifts
$dutiesStmt = $db->prepare("
    SELECT d.*, c.camp_name,
           (SELECT COUNT(*) FROM supervisor_visits v WHERE v.duty_id = d.id AND v.status = 'Completed') AS completed_sites_count
    FROM supervisor_duties d
    JOIN camps c ON c.id = d.camp_id
    WHERE d.supervisor_id = ?
    ORDER BY d.duty_date DESC, d.id DESC
    LIMIT 30
");
$dutiesStmt->execute([$supervisorId]);
$duties = $dutiesStmt->fetchAll();

// If duty selected for breakdown
$selectedDutyId = (int)($_GET['duty_id'] ?? 0);
$dutyVisits = [];
$selectedDuty = null;

if ($selectedDutyId > 0) {
    $selStmt = $db->prepare("SELECT d.*, c.camp_name FROM supervisor_duties d JOIN camps c ON c.id = d.camp_id WHERE d.id = ? AND d.supervisor_id = ?");
    $selStmt->execute([$selectedDutyId, $supervisorId]);
    $selectedDuty = $selStmt->fetch();

    if ($selectedDuty) {
        $vStmt = $db->prepare("
            SELECT v.*, s.name AS site_name, s.code AS site_code
            FROM supervisor_visits v
            JOIN sites s ON s.id = v.site_id
            WHERE v.duty_id = ?
            ORDER BY v.time_in ASC
        ");
        $vStmt->execute([$selectedDutyId]);
        $dutyVisits = $vStmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Patrol History | <?= htmlspecialchars($settings['company_name']) ?></title>
    
    <link rel="preload" href="<?= APP_URL ?>/assets/fonts/noto-sans-variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/design-tokens.css?v=<?= time() ?>">
    <?php require_once __DIR__ . '/../includes/theme_bootstrapper.php'; ?>
        
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= time() ?>">
    <style>
        body { background: var(--bg-main); color: var(--text-main); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 16px; }
        .history-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 12px; padding: 16px; margin-bottom: 12px; }
    </style>
</head>
<body>

<div style="max-width: 600px; margin: 0 auto;">
    <!-- Top Nav -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
        <div>
            <h1 style="font-size: 18px; font-weight: 800; margin: 0;">Patrol Shift History</h1>
            <div style="font-size: 12px; color: var(--text-dim); margin-top: 2px;">
                <?= htmlspecialchars($supervisor['full_name']) ?> (<?= htmlspecialchars($supervisor['staff_id']) ?>)
            </div>
        </div>
        <a href="<?= APP_URL ?>/sup/duty.php" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px;">
            ← Live Cockpit
        </a>
    </div>

    <?php if ($selectedDuty): ?>
        <!-- Specific Duty Breakdown -->
        <div class="history-card" style="border-color: var(--primary);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span class="badge" style="background: rgba(14, 165, 233, 0.2); color: var(--accent-cyan); font-weight: 800;">
                    <?= htmlspecialchars($selectedDuty['duty_ref']) ?> &bull; <?= htmlspecialchars($selectedDuty['shift_type']) ?> Shift
                </span>
                <a href="<?= APP_URL ?>/sup/history.php" style="font-size: 12px; color: var(--text-dim); text-decoration: underline;">
                    Back to all shifts
                </a>
            </div>
            
            <div style="font-size: 16px; font-weight: 800; color: var(--text-main);">
                Date: <?= date('l, d M Y', strtotime($selectedDuty['duty_date'])) ?>
            </div>
            <div style="font-size: 12px; color: var(--text-dim); margin-top: 2px;">
                Camp: <?= htmlspecialchars($selectedDuty['camp_name']) ?> &bull; Out: <?= date('h:i A', strtotime($selectedDuty['camp_out_time'])) ?> 
                <?= $selectedDuty['camp_in_time'] ? ' &bull; In: ' . date('h:i A', strtotime($selectedDuty['camp_in_time'])) : ' (Active)' ?>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--border-subtle); font-size: 13px;">
                <div>Sites: <strong><?= count($dutyVisits) ?></strong></div>
                <div>Total Minutes: <strong style="color: var(--accent-emerald);"><?= (int)$selectedDuty['total_minutes'] ?>m</strong></div>
            </div>
        </div>

        <h3 style="font-size: 14px; font-weight: 800; margin: 16px 0 10px 0; text-transform: uppercase;">
            Sites Visited During This Shift
        </h3>

        <?php if (empty($dutyVisits)): ?>
            <div class="history-card" style="text-align: center; color: var(--text-dim); font-size: 13px;">
                No site visits recorded during this shift.
            </div>
        <?php else: ?>
            <?php foreach ($dutyVisits as $idx => $v): ?>
                <div class="history-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <div>
                            <strong style="font-size: 14px; color: var(--text-main);">
                                #<?= $idx + 1 ?> &bull; <?= htmlspecialchars($v['site_name']) ?>
                            </strong>
                            <span style="font-size: 11px; color: var(--accent-cyan); font-weight: 700; margin-left: 4px;">
                                (<?= htmlspecialchars($v['site_code']) ?>)
                            </span>
                        </div>
                        <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #059669; font-weight: 800;">
                            <?= (int)$v['minutes_spent'] ?> mins
                        </span>
                    </div>

                    <div style="font-size: 12px; color: var(--text-dim); margin-bottom: 8px;">
                        In: <strong><?= date('h:i A', strtotime($v['time_in'])) ?></strong> &bull; 
                        Out: <strong><?= $v['time_out'] ? date('h:i A', strtotime($v['time_out'])) : 'In Progress' ?></strong>
                        <?php if ($v['travel_minutes_from_previous'] > 0): ?>
                            &bull; Travel: <em><?= (int)$v['travel_minutes_from_previous'] ?>m</em>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($v['remarks'])): ?>
                        <div style="font-size: 12px; background: rgba(0,0,0,0.02); padding: 8px 10px; border-radius: 6px; border: 1px solid var(--border-subtle); line-height: 1.4;">
                            <strong>Remarks:</strong> <?= htmlspecialchars($v['remarks']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php else: ?>
        <!-- List of Past Shifts -->
        <?php if (empty($duties)): ?>
            <div class="history-card" style="text-align: center; color: var(--text-dim); padding: 30px;">
                No past patrol shifts recorded yet.
            </div>
        <?php else: ?>
            <?php foreach ($duties as $d): ?>
                <a href="?duty_id=<?= $d['id'] ?>" style="text-decoration: none; color: inherit; display: block;">
                    <div class="history-card" style="transition: border-color 0.2s;" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border-subtle)'">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <span style="font-weight: 800; font-size: 14px; color: var(--text-main);">
                                <?= date('D, d M Y', strtotime($d['duty_date'])) ?>
                            </span>
                            <span class="badge" style="background: <?= $d['shift_type'] === 'Night' ? 'rgba(99, 102, 241, 0.2)' : 'rgba(245, 158, 11, 0.2)' ?>; color: <?= $d['shift_type'] === 'Night' ? 'var(--accent-indigo)' : 'var(--accent-amber)' ?>; font-weight: 700;">
                                <?= htmlspecialchars($d['shift_type']) ?> Shift
                            </span>
                        </div>
                        <div style="font-size: 12px; color: var(--text-dim); margin-bottom: 8px;">
                            Camp Out: <?= date('h:i A', strtotime($d['camp_out_time'])) ?> &bull; 
                            <?= $d['camp_in_time'] ? 'Camp In: ' . date('h:i A', strtotime($d['camp_in_time'])) : '<span style="color:#f59e0b;font-weight:700;">Shift Active</span>' ?>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12.5px; padding-top: 8px; border-top: 1px solid var(--border-subtle);">
                            <span>Sites: <strong><?= (int)$d['completed_sites_count'] ?> Visited</strong></span>
                            <span style="color: var(--accent-emerald); font-weight: 800;"><?= (int)$d['total_minutes'] ?> Mins on Sites &rarr;</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>
