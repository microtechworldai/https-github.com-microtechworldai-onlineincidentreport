<?php
/**
 * ====================================================================
 * ULTIMATE SECURITY SERVICES (USS) - BULK CHECKPOINT QR POSTERS
 * Batch A4 Poster Generation for 500+ Sites
 * ====================================================================
 */
ini_set('memory_limit', '512M');
set_time_limit(300);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$db = get_db();
$settings = get_system_settings();

$qrType = ($_GET['type'] ?? 'guard') === 'supervisor' ? 'supervisor' : 'guard';
$batchSize = (int)($_GET['batch_size'] ?? 25);
if (!in_array($batchSize, [10, 25, 50, 100])) {
    $batchSize = 25;
}
$page = max(1, (int)($_GET['page'] ?? 1));

// Count total active sites
$totalSites = (int)$db->query("SELECT COUNT(*) FROM sites WHERE is_active = 1")->fetchColumn();
$totalPages = max(1, (int)ceil($totalSites / $batchSize));
if ($page > $totalPages && $totalSites > 0) {
    $page = $totalPages;
}
$offset = ($page - 1) * $batchSize;

// Fetch sites for this batch
$stmt = $db->prepare("
    SELECT * FROM sites 
    WHERE is_active = 1 
    ORDER BY id ASC 
    LIMIT {$batchSize} OFFSET {$offset}
");
$stmt->execute();
$sites = $stmt->fetchAll();

$posterRole = $qrType === 'supervisor' ? 'SUPERVISOR VISIT CHECKPOINT' : 'SECURITY PATROL POST';
$qrPath = $qrType === 'supervisor' ? '/sup/index.php?token=' : '/g/index.php?token=';

$publicTunnelUrl = get_active_tunnel_url();
$qrBaseUrl = !empty($publicTunnelUrl) ? $publicTunnelUrl : APP_URL;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>USS_Bulk_QR_Posters_Batch_<?= $page ?>_<?= ucfirst($qrType) ?></title>
    <script src="<?= APP_URL ?>/assets/js/qrcode.min.js"></script>
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Noto Sans", Arial, sans-serif;
            background: #0b192c;
            color: #0f172a;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Top Action Bar (Screen Only) */
        .no-print-bar {
            width: 100%;
            background: #12263F;
            color: #fff;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 16px rgba(0,0,0,0.4);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .btn-action {
            color: #fff;
            border: none;
            padding: 9px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            transition: transform 0.15s ease, background 0.15s ease;
        }
        .btn-action:hover {
            transform: translateY(-1px);
        }

        /* A4 Poster Canvas */
        .poster-canvas {
            width: 210mm;
            height: 297mm;
            max-height: 297mm;
            background: #ffffff;
            margin: 20px auto;
            padding: 16mm 18mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
            border: 1px solid #cbd5e1;
            position: relative;
            overflow: hidden;
            page-break-after: always;
            break-after: page;
        }

        .poster-header {
            width: 100%;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .poster-logo {
            height: 52px;
            max-width: 260px;
            object-fit: contain;
            margin-bottom: 8px;
        }

        .poster-header h1 {
            font-size: 19pt;
            font-weight: 900;
            color: #0b192c;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 3px 0;
            line-height: 1.15;
        }

        .poster-header h2 {
            font-size: 10.5pt;
            font-weight: 800;
            color: #0284c7;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin: 0;
            line-height: 1.2;
        }

        .header-divider {
            width: 100%;
            height: 3.5px;
            background: #0284c7;
            border-radius: 2px;
            margin-top: 10px;
        }

        .site-card {
            width: 100%;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 12px;
            padding: 14px 18px;
            text-align: center;
            margin-top: 12px;
        }

        .site-card-label {
            font-size: 9.5pt;
            font-weight: 700;
            color: #64748b;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .site-card-name {
            font-size: 22pt;
            font-weight: 900;
            color: #0f172a;
            line-height: 1.2;
            margin-bottom: 4px;
        }

        .site-card-code {
            font-family: "Noto Sans", Arial, sans-serif;
            font-size: 11pt;
            font-weight: 700;
            color: #94a3b8;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .qr-card {
            width: 360px;
            max-width: 100%;
            background: #ffffff;
            border: 2px solid #0f172a;
            border-radius: 16px;
            padding: 18px 20px 14px;
            margin: 12px auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .qr-canvas-item {
            width: 230px !important;
            height: 230px !important;
            display: block;
        }

        .qr-caption {
            margin-top: 10px;
            font-size: 10.5pt;
            font-weight: 900;
            color: #0284c7;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            text-align: center;
            line-height: 1.3;
        }

        .steps-grid {
            width: 100%;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 10px;
        }

        .step-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 8px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .step-badge {
            font-size: 11pt;
            font-weight: 700;
            color: #94a3b8;
            margin-bottom: 3px;
        }

        .step-title {
            font-size: 10pt;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 3px;
        }

        .step-desc {
            font-size: 8pt;
            color: #475569;
            line-height: 1.3;
        }

        .direct-link-box {
            width: 100%;
            text-align: center;
            margin-top: 8px;
        }

        .direct-link-label {
            font-size: 8pt;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 2px;
        }

        .direct-link-url {
            font-family: monospace;
            font-size: 10pt;
            font-weight: 700;
            color: #0B5C66;
            word-break: break-all;
        }

        .poster-footer {
            width: 100%;
            border-top: 1px solid #cbd5e1;
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 8pt;
            color: #334155;
            margin-top: 8px;
        }

        /* Print Media Styles */
        @media print {
            .no-print-bar { display: none !important; }
            body { 
                background: #fff !important; 
                margin: 0 !important; 
                padding: 0 !important;
            }
            .poster-canvas {
                border: none !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 16mm 18mm !important;
                width: 210mm !important;
                height: 297mm !important;
                page-break-after: always !important;
                break-after: page !important;
            }
        }
    </style>
</head>
<body>

<!-- Control Bar -->
<div class="no-print-bar">
    <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
        <span style="font-size: 14px; font-weight: 800; color: #fff;">
            🖨️ Bulk QR Posters: Batch <?= $page ?> of <?= $totalPages ?> (Showing <?= count($sites) ?> of <?= number_format($totalSites) ?> Checkpoints)
        </span>

        <!-- Poster Role Switcher -->
        <div style="display: inline-flex; background: rgba(0,0,0,0.3); padding: 3px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15);">
            <a href="?page=<?= $page ?>&batch_size=<?= $batchSize ?>&type=guard" class="btn-action" style="padding: 5px 12px; font-size: 12px; background: <?= $qrType === 'guard' ? '#0284c7' : 'transparent' ?>;">
                👮 Guard Incident Posts
            </a>
            <a href="?page=<?= $page ?>&batch_size=<?= $batchSize ?>&type=supervisor" class="btn-action" style="padding: 5px 12px; font-size: 12px; background: <?= $qrType === 'supervisor' ? '#0a9396' : 'transparent' ?>;">
                ⏱️ Supervisor Visit Checkpoints
            </a>
        </div>

        <!-- Batch Size Selector -->
        <select onchange="location.href='?page=1&type=<?= $qrType ?>&batch_size=' + this.value;" style="background: #081426; color: #fff; border: 1px solid #0284c7; padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer;">
            <option value="10" <?= $batchSize == 10 ? 'selected' : '' ?>>10 Posters per batch</option>
            <option value="25" <?= $batchSize == 25 ? 'selected' : '' ?>>25 Posters per batch (Recommended)</option>
            <option value="50" <?= $batchSize == 50 ? 'selected' : '' ?>>50 Posters per batch</option>
            <option value="100" <?= $batchSize == 100 ? 'selected' : '' ?>>100 Posters per batch</option>
        </select>
    </div>

    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
        <!-- Pagination Jump -->
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&batch_size=<?= $batchSize ?>&type=<?= $qrType ?>" class="btn-action" style="background: #334155;">&lsaquo; Prev Batch</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>&batch_size=<?= $batchSize ?>&type=<?= $qrType ?>" class="btn-action" style="background: #334155;">Next Batch &rsaquo;</a>
        <?php endif; ?>

        <button class="btn-action" onclick="window.print();" style="background: #10b981; font-weight: 800; padding: 9px 20px;">
            🖨️ Print Batch of <?= count($sites) ?> Posters (A4)
        </button>
        <a href="<?= APP_URL ?>/admin/sites.php" class="btn-action" style="background: transparent; border: 1px solid #64748b;">
            ← Return to Sites
        </a>
    </div>
</div>

<!-- Render Each Poster -->
<?php 
$posterIndex = 0;
foreach ($sites as $site): 
    $posterIndex++;
    if ($qrType === 'supervisor') {
        $rawToken = get_stored_qr_token($site, 'supervisor_qr_token_encrypted', 'supervisor_qr_token');
    } else {
        $rawToken = get_stored_qr_token($site, 'active_qr_token_encrypted', 'active_qr_token');
    }
    $targetUrl = $qrBaseUrl . $qrPath . urlencode($rawToken);
?>
<div class="poster-canvas">

    <div class="poster-header">
        <?php if (!empty($settings['logo_url'])): ?>
            <img class="poster-logo" src="<?= APP_URL ?>/<?= htmlspecialchars($settings['logo_url']) ?>" alt="Logo" onerror="this.style.display='none'">
        <?php endif; ?>
        <h1><?= htmlspecialchars($settings['company_name']) ?></h1>
        <h2>OFFICIAL SECURITY CHECKPOINT POSTER</h2>
        <div class="header-divider"></div>
    </div>

    <div class="site-card">
        <div class="site-card-label"><?= htmlspecialchars($posterRole) ?></div>
        <div class="site-card-name"><?= htmlspecialchars($site['name']) ?></div>
        <div class="site-card-code">CHECKPOINT CODE: <?= htmlspecialchars($site['code']) ?></div>
        <?php if (!empty($site['latitude']) || !empty($site['longitude'])): ?>
            <div style="font-size: 10pt; font-weight: 700; color: #0284c7; margin-top: 4px;">
                📍 GPS: <?= htmlspecialchars($site['latitude']) ?> | <?= htmlspecialchars($site['longitude']) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="qr-card">
        <canvas class="qr-canvas-item" id="qrCanvas_<?= $posterIndex ?>" width="230" height="230" data-url="<?= htmlspecialchars($targetUrl) ?>"></canvas>
        <div class="qr-caption">SCAN WITH MOBILE CAMERA TO <?= $qrType === 'supervisor' ? 'LOG SUPERVISOR VISIT' : 'SUBMIT INCIDENT REPORT' ?></div>
    </div>

    <div class="steps-grid">
        <div class="step-item">
            <div class="step-badge">1</div>
            <div class="step-title">Scan QR Code</div>
            <div class="step-desc">Point smartphone camera at checkpoint code. Tap yellow browser banner.</div>
        </div>
        <div class="step-item">
            <div class="step-badge">2</div>
            <div class="step-title"><?= $qrType === 'supervisor' ? 'Supervisor Auth' : 'Guard Verification' ?></div>
            <div class="step-desc"><?= $qrType === 'supervisor' ? 'Log in with supervisor credentials. System captures GPS arrival.' : 'Enter officer staff credentials to open report template.' ?></div>
        </div>
        <div class="step-item">
            <div class="step-badge">3</div>
            <div class="step-title"><?= $qrType === 'supervisor' ? 'Time In / Out' : 'Submit Incident' ?></div>
            <div class="step-desc"><?= $qrType === 'supervisor' ? 'Confirm Time In arrival or complete inspection checklist for Time Out.' : 'Record narrative, attach photos, and notify command desk.' ?></div>
        </div>
    </div>

    <div class="direct-link-box">
        <div class="direct-link-label">Direct Checkpoint Shortlink:</div>
        <div class="direct-link-url"><?= htmlspecialchars($targetUrl) ?></div>
    </div>

    <div class="poster-footer">
        <div><strong>24/7 Control Room:</strong> <?= htmlspecialchars($settings['phone'] ?: '+971 4 251 0050') ?> | <?= htmlspecialchars($settings['email'] ?: 'ops@ultimatesecurity.ae') ?></div>
        <div style="text-align: right;">Official A4 Checkpoint Poster • Printed: <?= date('Y-m-d') ?></div>
    </div>

</div>
<?php endforeach; ?>

<script>
// Render all canvases in current batch
window.addEventListener('DOMContentLoaded', function() {
    const canvases = document.querySelectorAll('.qr-canvas-item');
    canvases.forEach(function(cvs) {
        const url = cvs.getAttribute('data-url');
        if (typeof QRCode !== 'undefined' && typeof QRCode.toCanvas === 'function') {
            QRCode.toCanvas(cvs, url, {
                width: 230,
                margin: 1,
                color: { dark: '#000000', light: '#ffffff' },
                errorCorrectionLevel: 'H'
            });
        }
    });
});
</script>

</body>
</html>
