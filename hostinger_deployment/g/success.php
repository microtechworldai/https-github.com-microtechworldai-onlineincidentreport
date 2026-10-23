<?php
/**
 * Incident Submission Success Screen
 * Guard session has already been destroyed on submit
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

$settings = get_system_settings();
$ref = htmlspecialchars($_GET['ref'] ?? 'INC-SUBMITTED');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Submitted | <?= htmlspecialchars($settings['company_name']) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/guard.css">
    <style>
        .success-box {
            max-width: 460px;
            margin: 50px auto;
            padding: 32px 24px;
            text-align: center;
            background: var(--g-card);
            border: 1px solid var(--g-border);
            border-radius: var(--g-radius);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
        .check-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.15);
            border: 2px solid #10b981;
            color: #10b981;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        .ref-card {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--g-border);
            border-radius: 10px;
            padding: 16px;
            margin: 20px 0;
        }
    </style>
</head>
<body class="guard-body">

<div class="guard-container">
    <div class="success-box">
        <div class="check-icon">
            <svg style="width:36px;height:36px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
        </div>

        <h2 style="font-size: 22px; font-weight: 800; color: #fff;">Report Successfully Filed</h2>
        <p style="font-size: 13.5px; color: var(--g-text-muted); margin-top: 6px;">
            The patrol incident has been permanently recorded and sent to the Super Admin command desk.
        </p>

        <div class="ref-card">
            <div style="font-size: 11px; text-transform: uppercase; color: var(--g-accent); font-weight: 700; letter-spacing: 1px;">
                Official Reference Code
            </div>
            <div style="font-family: 'JetBrains Mono', monospace; font-size: 19px; font-weight: 800; color: #fff; margin-top: 6px;">
                <?= $ref ?>
            </div>
        </div>

        <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; padding: 12px; font-size: 12px; color: #34d399; text-align: left; line-height: 1.5; margin-bottom: 24px;">
            ⚡ <strong>Shift Active:</strong> You remain logged in. Scan any checkpoint QR poster or tap below to file another report immediately without delay.
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px;">
            <?php if (!empty($_SESSION['uss_supervisor_logged_in'])): ?>
                <a href="<?= APP_URL ?>/sup/duty.php" class="btn-guard-submit" style="text-decoration: none; padding: 12px 20px; font-size: 15px; background: #0284c7;">
                    🛡️ Return to Supervisor Duty Portal &rarr;
                </a>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/g/submit.php" class="btn-guard-submit" style="text-decoration: none; padding: 12px 20px; font-size: 15px;">
                + File Another Report for this Site &rarr;
            </a>
            <a href="<?= APP_URL ?>/g/logout.php" style="color: var(--g-text-muted); font-size: 12.5px; text-decoration: none; padding: 6px;">
                Sign Out Officer (End Shift)
            </a>
        </div>
    </div>
</div>

</body>
</html>
