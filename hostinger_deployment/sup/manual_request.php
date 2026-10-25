<?php
/**
 * Supervisor Manual Time-Out Request
 * Allows supervisor to submit an unclocked visit for Super Admin correction and approval.
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
$minRemarksLen = max(10, (int)($settings['supervisor_remarks_min_length'] ?? 20));

$visitId = (int)($_GET['visit_id'] ?? $_POST['visit_id'] ?? 0);
if (!$visitId) {
    header("Location: " . APP_URL . "/sup/duty.php");
    exit;
}

$vStmt = $db->prepare("
    SELECT v.*, s.name AS site_name, s.code AS site_code 
    FROM supervisor_visits v
    JOIN sites s ON s.id = v.site_id
    WHERE v.id = ? AND v.supervisor_id = ?
    LIMIT 1
");
$vStmt->execute([$visitId, $supervisorId]);
$visit = $vStmt->fetch();

if (!$visit) {
    header("Location: " . APP_URL . "/sup/duty.php?error=visit_not_found");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_manual_request'])) {
    $manualTime = trim($_POST['manual_timeout_time'] ?? '');
    $reason = trim($_POST['manual_timeout_reason'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        $error = "The form expired. Refresh the page and try again.";
    } elseif ($visit['status'] !== 'In Progress' || $visit['manual_timeout_status'] === 'Pending') {
        $error = "This visit cannot accept another manual time-out request.";
    } elseif (empty($manualTime)) {
        $error = "Please specify the approximate time you left the site.";
    } elseif (empty($reason) || strlen($reason) < 8) {
        $error = "Please provide a valid explanation for why you could not scan out at the site.";
    } elseif (empty($remarks) || strlen($remarks) < $minRemarksLen) {
        $error = "Remarks too short! Minimum {$minRemarksLen} characters required (current: " . strlen($remarks) . ").";
    } else {
        $parsedTime = DateTime::createFromFormat('Y-m-d\TH:i', $manualTime);
        $dateErrors = DateTime::getLastErrors();
        $timeInTs = strtotime($visit['time_in']);
        $timeOutTs = $parsedTime ? $parsedTime->getTimestamp() : false;

        if (
            !$parsedTime
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $timeOutTs === false
            || $timeOutTs <= $timeInTs
        ) {
            $error = "Departure time must be after the Time In timestamp (" . date('h:i A', $timeInTs) . ").";
        } else {
            $up = $db->prepare("
                UPDATE supervisor_visits 
                SET manual_timeout_requested = 1, manual_timeout_time = ?, manual_timeout_reason = ?,
                    manual_timeout_remarks = ?, manual_timeout_status = 'Pending', updated_at = NOW()
                WHERE id = ? AND status = 'In Progress' AND manual_timeout_status <> 'Pending'
            ");
            $up->execute([
                $parsedTime->format('Y-m-d H:i:s'), $reason, $remarks,
                $visit['id']
            ]);
            if ($up->rowCount() !== 1) {
                $error = "This visit changed while the request was being submitted. Refresh and review its status.";
            } else {

            log_audit('MANUAL_TIMEOUT_REQUESTED', 'SUPERVISOR_VISIT', (string)$visit['id'], ['reason' => $reason], 'SUPERVISOR', $supervisorId, $_SESSION['supervisor_name'] ?? null);

            header("Location: " . APP_URL . "/sup/duty.php?msg=manual_request_submitted");
            exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manual Time Out Request | <?= htmlspecialchars($settings['company_name']) ?></title>
    
    <link rel="preload" href="<?= APP_URL ?>/assets/fonts/noto-sans-variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/design-tokens.css?v=<?= time() ?>">
    <?php require_once __DIR__ . '/../includes/theme_bootstrapper.php'; ?>
        
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= time() ?>">
    <script>
        (function() {
            var theme = localStorage.getItem('uss_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <style>
        body {
            background: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            padding: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .request-card {
            max-width: 500px;
            width: 100%;
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-card);
        }
    </style>
</head>
<body>

<div class="request-card">
    <div style="text-align: center; margin-bottom: 20px;">
        <span class="badge" style="background: rgba(245, 158, 11, 0.2); color: var(--accent-amber); font-weight: 800; text-transform: uppercase;">
            Admin Correction Request
        </span>
        <h2 style="font-size: 19px; font-weight: 800; margin-top: 6px;">Manual Time Out Submission</h2>
        <div style="font-size: 13px; color: var(--text-muted); margin-top: 2px;">
            <?= htmlspecialchars($visit['site_name']) ?> (<?= htmlspecialchars($visit['site_code']) ?>)
        </div>
    </div>

    <div style="background: var(--bg-card-hover); border-left: 3px solid var(--accent-amber); padding: 12px; border-radius: 4px; font-size: 12.5px; color: var(--text-muted); line-height: 1.4; margin-bottom: 18px;">
        Use this form only if you could not scan the site QR code before leaving. Your request will be reviewed by the Super Admin; official visit time and remarks remain unchanged until approval.
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom: 16px; font-size: 13px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="submit_manual_request" value="1">
        <input type="hidden" name="visit_id" value="<?= $visit['id'] ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">

        <div class="form-group" style="margin-bottom: 14px;">
            <label class="form-label" style="font-size: 12px;">Official Time In</label>
            <input type="text" class="form-control" value="<?= date('d M Y, h:i:s A', strtotime($visit['time_in'])) ?>" readonly style="opacity: 0.7; font-family: monospace;">
        </div>

        <div class="form-group" style="margin-bottom: 14px;">
            <label class="form-label" style="font-size: 12px;">Estimated Departure Time <span style="color: var(--accent-rose);">*</span></label>
            <input type="datetime-local" name="manual_timeout_time" class="form-control" required value="<?= date('Y-m-d\TH:i') ?>">
        </div>

        <div class="form-group" style="margin-bottom: 14px;">
            <label class="form-label" style="font-size: 12px;">Reason for Manual Correction <span style="color: var(--accent-rose);">*</span></label>
            <textarea name="manual_timeout_reason" class="form-control" rows="2" required placeholder="e.g. Left post in hurry due to client emergency; phone battery died before leaving..."></textarea>
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
            <label class="form-label" style="font-size: 12px;">Mandatory Visit Remarks <span style="color: var(--accent-rose);">*</span></label>
            <textarea name="remarks" id="manualRemarks" class="form-control" rows="3" required placeholder="Observations, guard alertness, post condition..."><?= htmlspecialchars($visit['manual_timeout_remarks'] ?? $visit['remarks'] ?? '') ?></textarea>
            <div style="font-size: 11.5px; color: var(--text-dim); margin-top: 4px;">
                Minimum <?= $minRemarksLen ?> characters required.
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 13px; font-size: 14.5px; font-weight: 800; background: var(--accent-amber); color: #000;">
            📨 Submit for Super Admin Approval
        </button>
    </form>

    <div style="text-align: center; margin-top: 16px; border-top: 1px solid var(--border-subtle); padding-top: 12px;">
        <a href="<?= APP_URL ?>/sup/duty.php" style="color: var(--text-muted); font-size: 12px; text-decoration: none;">
            &larr; Cancel and return to Duty Cockpit
        </a>
    </div>
</div>

</body>
</html>
