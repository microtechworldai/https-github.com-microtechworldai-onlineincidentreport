<?php
/**
 * Official Printable A4 Camp Accommodation QR Checkpoint Poster
 * Ultimate Security Services (USS)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die("Invalid Camp ID.");
}

$db = get_db();
$settings = get_system_settings();

$stmt = $db->prepare("SELECT * FROM camps WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$camp = $stmt->fetch();
if (!$camp) {
    die("Camp accommodation not found.");
}

$publicTunnelUrl = get_active_tunnel_url();
$lanIps = get_lan_ips();
$isLocalDev = !empty($isLocal);

$port = $_SERVER['SERVER_PORT'] ?? '8080';
$portSuffix = ($port && $port != '80' && $port != '443') ? ':' . $port : '';

$selectedHost = $_GET['host'] ?? '';

if (!empty($selectedHost)) {
    if (strpos($selectedHost, 'http://') === 0 || strpos($selectedHost, 'https://') === 0) {
        $qrBaseUrl = rtrim($selectedHost, '/');
    } else {
        $isTunnelHost = (strpos($selectedHost, 'trycloudflare.com') !== false || strpos($selectedHost, 'localtunnel.me') !== false);
        $protocol = ($isTunnelHost || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? "https://" : "http://";
        $qrBaseUrl = $protocol . $selectedHost;
    }
} elseif (!empty($publicTunnelUrl)) {
    $qrBaseUrl = $publicTunnelUrl;
    $selectedHost = $publicTunnelUrl;
} elseif (strpos($_SERVER['HTTP_HOST'] ?? '', 'trycloudflare.com') !== false) {
    $qrBaseUrl = 'https://' . $_SERVER['HTTP_HOST'];
    $selectedHost = $qrBaseUrl;
} elseif ($isLocalDev && !empty($lanIps)) {
    $selectedHost = in_array('10.2.129.2', $lanIps) ? ('10.2.129.2' . $portSuffix) : ($lanIps[0] . $portSuffix);
    $qrBaseUrl = "http://" . $selectedHost;
} else {
    $qrBaseUrl = APP_URL;
}

if (strpos($qrBaseUrl, 'trycloudflare.com') !== false && strpos($qrBaseUrl, 'http://') === 0) {
    $qrBaseUrl = 'https://' . substr($qrBaseUrl, 7);
}

$supervisorUrl = $qrBaseUrl . '/sup/index.php?camp=' . urlencode($camp['qr_token']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Camp_Poster_<?= htmlspecialchars($camp['camp_code']) ?>_<?= htmlspecialchars($camp['camp_name']) ?></title>
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
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #e2e8f0;
            color: #0f172a;
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .no-print-toolbar {
            width: 210mm;
            background: #1e293b;
            color: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .btn-print {
            background: #0284c7;
            color: #fff;
            border: none;
            padding: 8px 16px;
            font-weight: bold;
            font-size: 13px;
            border-radius: 6px;
            cursor: pointer;
        }
        .poster-page {
            width: 210mm;
            height: 297mm;
            background: #ffffff;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 16mm 16mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        .poster-border {
            position: absolute;
            top: 8mm;
            left: 8mm;
            right: 8mm;
            bottom: 8mm;
            border: 3px double #0284c7;
            pointer-events: none;
        }
        .poster-header {
            width: 100%;
            text-align: center;
            border-bottom: 3px solid #0284c7;
            padding-bottom: 12px;
        }
        .poster-logo {
            height: 60px;
            max-width: 260px;
            object-fit: contain;
            margin-bottom: 8px;
        }
        .poster-header h1 {
            font-size: 19pt;
            font-weight: 900;
            color: #0b192c;
            text-transform: uppercase;
        }
        .poster-header h2 {
            font-size: 12.5pt;
            font-weight: 700;
            color: #0284c7;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 4px;
        }
        .camp-box {
            width: 100%;
            background: #f8fafc;
            border: 2px solid #cbd5e1;
            border-radius: 12px;
            padding: 14px;
            text-align: center;
            margin: 10px 0;
        }
        .camp-name {
            font-size: 22pt;
            font-weight: 900;
            color: #0f172a;
            line-height: 1.2;
        }
        .camp-code-badge {
            display: inline-block;
            background: #0284c7;
            color: #fff;
            font-family: monospace;
            font-size: 13pt;
            font-weight: bold;
            padding: 4px 16px;
            border-radius: 6px;
            margin-top: 6px;
        }
        .qr-frame {
            background: #fff;
            padding: 18px;
            border: 4px solid #0f172a;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 6px 0;
        }
        #qrcodeCanvas {
            width: 250px !important;
            height: 250px !important;
            display: block;
        }
        .qr-caption {
            margin-top: 10px;
            font-size: 11pt;
            font-weight: 800;
            color: #0284c7;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .instruction-box {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            margin: 8px 0;
        }
        .step-card {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 10px;
            background: #f8fafc;
            text-align: center;
        }
        .step-num {
            width: 26px;
            height: 26px;
            background: #0284c7;
            color: #fff;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 4px;
        }
        .step-title {
            font-size: 10pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 3px;
        }
        .step-desc {
            font-size: 8pt;
            color: #64748b;
            line-height: 1.3;
        }
        .poster-footer {
            width: 100%;
            border-top: 2px solid #e2e8f0;
            padding-top: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 8.5pt;
            color: #64748b;
        }
        .url-text {
            font-family: monospace;
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            word-break: break-all;
            max-width: 320px;
            font-size: 7.5pt;
        }
        @media print {
            body { background: none; padding: 0; }
            .no-print-toolbar { display: none; }
            .poster-page { box-shadow: none; margin: 0; width: 100%; height: 100%; }
        }
    </style>
</head>
<body>

<div class="no-print-toolbar">
    <div style="font-size: 13px;">
        <strong>Camp Accommodation QR Poster</strong> &bull; <?= htmlspecialchars($camp['camp_name']) ?>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
        <form method="GET" style="margin: 0; display: inline-flex; gap: 6px;">
            <input type="hidden" name="id" value="<?= $camp['id'] ?>">
            <select name="host" class="form-control" style="font-size: 12px; padding: 4px 8px; background: #334155; color: #fff; border: 1px solid #475569;" onchange="this.form.submit()">
                <?php if (!empty($publicTunnelUrl)): ?>
                    <option value="<?= htmlspecialchars($publicTunnelUrl) ?>" <?= ($selectedHost === $publicTunnelUrl) ? 'selected' : '' ?>>
                        Public HTTPS Cloudflare Tunnel
                    </option>
                <?php endif; ?>
                <?php foreach ($lanIps as $ip): ?>
                    <?php $optVal = $ip . $portSuffix; ?>
                    <option value="<?= htmlspecialchars($optVal) ?>" <?= ($selectedHost === $optVal || $selectedHost === ("http://" . $optVal)) ? 'selected' : '' ?>>
                        Wi-Fi LAN IP (<?= htmlspecialchars($optVal) ?>)
                    </option>
                <?php endforeach; ?>
                <option value="<?= htmlspecialchars(APP_URL) ?>" <?= ($selectedHost === APP_URL) ? 'selected' : '' ?>>
                    Default App URL (<?= htmlspecialchars(APP_URL) ?>)
                </option>
            </select>
        </form>
        <button class="btn-print" onclick="window.print()">
            🖨️ Print Checkpoint Poster
        </button>
    </div>
</div>

<div class="poster-page">
    <div class="poster-border"></div>

    <div class="poster-header">
        <img class="poster-logo" src="<?= APP_URL ?>/<?= htmlspecialchars($settings['logo_url']) ?>" alt="Logo" onerror="this.style.display='none'">
        <h1><?= htmlspecialchars($settings['company_name']) ?></h1>
        <h2>Supervisor Camp Patrol Checkpoint</h2>
    </div>

    <div class="camp-box">
        <div style="font-size: 11pt; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Base Accommodation</div>
        <div class="camp-name"><?= htmlspecialchars($camp['camp_name']) ?></div>
        <div class="camp-code-badge"><?= htmlspecialchars($camp['camp_code']) ?></div>
        <?php if (!empty($camp['location'])): ?>
            <div style="font-size: 9.5pt; color: #64748b; margin-top: 6px;"><?= htmlspecialchars($camp['location']) ?></div>
        <?php endif; ?>
    </div>

    <div class="qr-frame">
        <img id="qrImage" src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=8&data=<?= urlencode($supervisorUrl) ?>" alt="Camp Checkpoint QR Code" width="250" height="250" style="display:block; margin:0 auto; background:#fff; border-radius:8px;" crossOrigin="anonymous">
        <canvas id="qrcodeCanvas" style="display: none; width: 250px; height: 250px;"></canvas>
        <div class="qr-caption">Scan to Start Patrol (Camp Out) or End Duty (Camp In)</div>
    </div>

    <div class="instruction-box">
        <div class="step-card">
            <div class="step-num">1</div>
            <div class="step-title">Departing Camp</div>
            <div class="step-desc">Scan this QR when leaving camp to begin patrol shift (Camp Out). Starts official duty timer.</div>
        </div>
        <div class="step-card">
            <div class="step-num">2</div>
            <div class="step-title">Site Inspections</div>
            <div class="step-desc">Travel to assigned sites. Scan Site QR for Time In, conduct audit, and scan for Time Out with mandatory remarks.</div>
        </div>
        <div class="step-card">
            <div class="step-num">3</div>
            <div class="step-title">Return to Camp</div>
            <div class="step-desc">Scan this QR upon returning to camp to complete duty (Camp In). Finalizes daily patrol dossier.</div>
        </div>
    </div>

    <div class="poster-footer">
        <div>
            <strong>Authorized Personnel Only</strong> &bull; Dubai Server Timestamp Authority (Asia/Dubai)
        </div>
        <div style="display: flex; align-items: center; gap: 6px;">
            <span>Target:</span>
            <span class="url-text"><?= htmlspecialchars($supervisorUrl) ?></span>
        </div>
    </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('qrcodeCanvas');
    const img = document.getElementById('qrImage');
    const qrData = '<?= addslashes($supervisorUrl) ?>';
    
    if (typeof QRCode !== 'undefined' && typeof QRCode.toCanvas === 'function') {
        QRCode.toCanvas(canvas, qrData, {
            width: 250,
            margin: 2,
            color: { dark: '#0f172a', light: '#ffffff' },
            errorCorrectionLevel: 'H'
        }, function (error) {
            if (!error) {
                canvas.style.display = 'block';
                img.style.display = 'none';
            }
        });
    }
});
</script>

</body>
</html>

