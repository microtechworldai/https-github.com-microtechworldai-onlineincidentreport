<?php
/**
 * Official Printable A4 Security Checkpoint QR Poster
 * Designed for Physical Mounting at Patrol Desks & Security Posts
 * Ultimate Security Services (USS)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die("Invalid Site ID.");
}

$db = get_db();
$settings = get_system_settings();

$stmt = $db->prepare("SELECT * FROM sites WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$site = $stmt->fetch();
if (!$site) {
    die("Site not found.");
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

$rawToken = !empty($site['active_qr_token']) ? $site['active_qr_token'] : '';
if (empty($rawToken)) {
    $cleanCode = preg_replace('/[^a-z0-9]/', '', strtolower($site['code'] ?: 'site'));
    $rawToken = 'uss_qr_' . ($cleanCode ?: 'site') . '_' . bin2hex(random_bytes(10));
    $db->prepare("UPDATE sites SET active_qr_token = ?, updated_at = NOW() WHERE id = ?")->execute([$rawToken, $site['id']]);
}

$guardUrl = $qrBaseUrl . '/g/index.php?token=' . urlencode($rawToken);
$qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=320x320&margin=8&data=" . urlencode($guardUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QR_Poster_<?= htmlspecialchars($site['code']) ?>_<?= htmlspecialchars($site['name']) ?></title>
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
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #e2e8f0;
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
            background: #0f172a;
            color: #fff;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .btn-action {
            color: #fff;
            border: none;
            padding: 9px 18px;
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
            padding: 18mm 20mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            border: 1px solid #cbd5e1;
            position: relative;
            overflow: hidden;
        }

        /* Header Section */
        .poster-header {
            width: 100%;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .poster-logo-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }

        .poster-logo {
            height: 64px;
            max-width: 280px;
            object-fit: contain;
        }

        .poster-header h1 {
            font-size: 20pt;
            font-weight: 900;
            color: #0b192c;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 4px 0;
            line-height: 1.15;
        }

        .poster-header h2 {
            font-size: 11pt;
            font-weight: 800;
            color: #0284c7;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin: 0;
            line-height: 1.2;
        }

        .header-divider {
            width: 100%;
            height: 4px;
            background: #0284c7;
            border-radius: 2px;
            margin-top: 14px;
        }

        /* Security Patrol Post Card */
        .site-card {
            width: 100%;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 12px;
            padding: 16px 20px;
            text-align: center;
            margin-top: 16px;
        }

        .site-card-label {
            font-size: 10pt;
            font-weight: 700;
            color: #64748b;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .site-card-name {
            font-size: 26pt;
            font-weight: 900;
            color: #0f172a;
            line-height: 1.2;
            margin-bottom: 5px;
        }

        .site-card-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12pt;
            font-weight: 700;
            color: #94a3b8;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        /* QR Frame Box */
        .qr-card {
            width: 380px;
            max-width: 100%;
            background: #ffffff;
            border: 2.5px solid #0f172a;
            border-radius: 18px;
            padding: 22px 24px 18px;
            margin: 16px auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        #qrImage, #qrcodeCanvas {
            width: 250px !important;
            height: 250px !important;
            display: block;
        }

        .qr-caption {
            margin-top: 14px;
            font-size: 11pt;
            font-weight: 900;
            color: #0284c7;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            text-align: center;
            line-height: 1.3;
        }

        /* 3 Steps Grid */
        .steps-grid {
            width: 100%;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 14px;
        }

        .step-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 12px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .step-badge {
            font-size: 12pt;
            font-weight: 700;
            color: #94a3b8;
            margin-bottom: 5px;
        }

        .step-title {
            font-size: 11pt;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 5px;
        }

        .step-desc {
            font-size: 8.5pt;
            color: #475569;
            line-height: 1.35;
        }

        /* Direct Shortlink */
        .direct-link-box {
            width: 100%;
            text-align: center;
            margin-top: 10px;
        }

        .direct-link-label {
            font-size: 8.5pt;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 2px;
        }

        .direct-link-url {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 8.5pt;
            font-weight: 600;
            color: #0284c7;
            text-decoration: none;
            word-break: break-all;
        }

        /* Footer */
        .poster-footer {
            width: 100%;
            border-top: 1px solid #cbd5e1;
            padding-top: 12px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 8.5pt;
            color: #334155;
            line-height: 1.5;
            margin-top: 10px;
        }

        .footer-left strong {
            color: #0f172a;
        }

        .footer-right {
            text-align: right;
        }

        .print-timestamp {
            font-size: 8pt;
            color: #64748b;
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
                page-break-after: avoid !important;
                page-break-inside: avoid !important;
            }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
        <div>
            <strong>Checkpoint Poster:</strong> <?= htmlspecialchars($site['name']) ?> (<?= htmlspecialchars($site['code']) ?>)
        </div>
        <?php if (!empty($publicTunnelUrl) || !empty($lanIps)): ?>
            <div style="background: rgba(14, 165, 233, 0.15); border: 1px solid #0284c7; padding: 4px 12px; border-radius: 6px; font-size: 12.5px; color: #38bdf8; display: flex; align-items: center; gap: 8px;">
                <span>📱 <strong>QR Target Host:</strong></span>
                <select onchange="location.href='?id=<?= $id ?>&host=' + encodeURIComponent(this.value);" style="background: #0f172a; color: #fff; border: 1px solid #0284c7; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer;">
                    <option value="<?= htmlspecialchars(APP_URL) ?>" <?= ($qrBaseUrl === APP_URL) ? 'selected' : '' ?>>
                        🌐 Live Domain (<?= htmlspecialchars(parse_url(APP_URL, PHP_URL_HOST)) ?>)
                    </option>
                    <?php if (!empty($publicTunnelUrl)): ?>
                        <option value="<?= htmlspecialchars($publicTunnelUrl) ?>" <?= ($selectedHost === $publicTunnelUrl) ? 'selected' : '' ?>>
                            ⚡ Cloudflare Tunnel (HTTPS)
                        </option>
                    <?php endif; ?>
                    <?php foreach ($lanIps as $lip): ?>
                        <?php $hVal = $lip . $portSuffix; ?>
                        <option value="<?= htmlspecialchars($hVal) ?>" <?= ($selectedHost === $hVal && $qrBaseUrl !== $publicTunnelUrl) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($hVal) ?> (Local Wi-Fi)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button class="btn-action" onclick="downloadQrCode();" style="background: #10b981;">💾 Download QR</button>
        <button class="btn-action" onclick="copyPatrolLink();" style="background: #6366f1;">📋 Copy Link</button>
        <button class="btn-action" onclick="window.print();" style="background: #0284c7;">🖨️ Print Poster (A4)</button>
        <button style="background: transparent; color: #fff; border: 1px solid #475569; padding: 9px 16px; border-radius: 8px; cursor: pointer;" onclick="window.close();">Close</button>
    </div>
</div>

<div class="poster-canvas">

    <!-- Top Header -->
    <div class="poster-header">
        <div class="poster-logo-wrap">
            <?php if (!empty($settings['logo_url'])): ?>
                <img class="poster-logo" src="<?= APP_URL ?>/<?= htmlspecialchars($settings['logo_url']) ?>" alt="USS Logo" onerror="this.style.display='none'; document.getElementById('svgFallbackLogo').style.display='block';">
            <?php endif; ?>
            <svg id="svgFallbackLogo" width="56" height="56" viewBox="0 0 64 64" fill="none" style="<?= empty($settings['logo_url']) ? '' : 'display:none;' ?>">
                <path d="M32 4L10 14V30C10 44 19 56 32 60C45 56 54 44 54 30V14L32 4Z" fill="#0284C7"/>
                <path d="M32 10L14 18V30C14 41 21 51 32 54C43 51 50 41 50 30V18L32 10Z" fill="#0EA5E9"/>
                <path d="M26 28L31 33L42 22" stroke="#FFFFFF" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h1><?= htmlspecialchars($settings['company_name']) ?></h1>
        <h2>OFFICIAL INCIDENT REPORTING CHECKPOINT</h2>
        <div class="header-divider"></div>
    </div>

    <!-- Security Patrol Post Card -->
    <div class="site-card">
        <div class="site-card-label">SECURITY PATROL POST</div>
        <div class="site-card-name"><?= htmlspecialchars($site['name']) ?></div>
        <div class="site-card-code">SITE CODE: <?= htmlspecialchars($site['code']) ?></div>
        <?php if (!empty($site['latitude']) || !empty($site['longitude'])): ?>
            <div style="font-size: 10.5pt; font-weight: 700; color: #0284c7; margin-top: 6px; letter-spacing: 0.5px;">
                📍 GPS: <?= htmlspecialchars($site['latitude'] ?? '') ?>, <?= htmlspecialchars($site['longitude'] ?? '') ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- QR Code Center Card -->
    <div class="qr-card">
        <canvas id="qrcodeCanvas" width="250" height="250" style="display: none;"></canvas>
        <img id="qrImage" src="<?= $qrApiUrl ?>" alt="QR Code" width="250" height="250" style="display: block;">
        <div class="qr-caption">SCAN WITH PHONE CAMERA TO REPORT INCIDENT</div>
    </div>

    <!-- 3 Steps Instructions Grid -->
    <div class="steps-grid">
        <div class="step-item">
            <div class="step-badge">1</div>
            <div class="step-title">Scan QR Code</div>
            <div class="step-desc">Open your mobile phone camera and point at this code. Tap the link banner.</div>
        </div>
        <div class="step-item">
            <div class="step-badge">2</div>
            <div class="step-title">Guard Sign In</div>
            <div class="step-desc">Enter your Officer Staff ID / Username and Password to access the patrol form.</div>
        </div>
        <div class="step-item">
            <div class="step-badge">3</div>
            <div class="step-title">Submit &amp; Photos</div>
            <div class="step-desc">Fill report narrative, attach live camera photos, and submit for instant review.</div>
        </div>
    </div>

    <!-- Direct Fallback Link -->
    <div class="direct-link-box">
        <div class="direct-link-label">Direct Patrol Link:</div>
        <a href="<?= htmlspecialchars($guardUrl) ?>" class="direct-link-url" target="_blank"><?= htmlspecialchars($guardUrl) ?></a>
    </div>

    <!-- Footer -->
    <div class="poster-footer">
        <div class="footer-left">
            <div><strong>Operations Hotline:</strong> <?= htmlspecialchars($settings['phone'] ?: '+971 4 251 0050') ?></div>
            <div><strong>Support:</strong> <?= htmlspecialchars($settings['email'] ?: 'ops@ultimatesecurity.ae') ?></div>
        </div>
        <div class="footer-right">
            <div>Dubai, United Arab Emirates</div>
            <div class="print-timestamp">Printed: <?= date('Y-m-d H:i') ?> GST</div>
        </div>
    </div>

</div>

<script>
// Client-side Canvas rendering enhancement
window.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('qrcodeCanvas');
    const img = document.getElementById('qrImage');
    const qrData = '<?= addslashes($guardUrl) ?>';
    
    if (typeof QRCode !== 'undefined' && typeof QRCode.toCanvas === 'function') {
        QRCode.toCanvas(canvas, qrData, {
            width: 250,
            margin: 1,
            color: {
                dark: '#000000',
                light: '#ffffff'
            },
            errorCorrectionLevel: 'H'
        }, function (error) {
            if (!error) {
                canvas.style.display = 'block';
                img.style.display = 'none';
            }
        });
    }
});

function downloadQrCode() {
    const canvas = document.getElementById('qrcodeCanvas');
    const img = document.getElementById('qrImage');
    const a = document.createElement('a');
    a.download = 'QR_Checkpoint_<?= htmlspecialchars($site['code']) ?>.png';
    if (canvas && canvas.style.display !== 'none' && canvas.toDataURL) {
        a.href = canvas.toDataURL('image/png');
    } else {
        a.href = img.src;
        a.target = '_blank';
    }
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function copyPatrolLink() {
    const url = '<?= addslashes($guardUrl) ?>';
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() {
            alert('Patrol Checkpoint URL copied to clipboard:\n' + url);
        });
    } else {
        prompt('Copy this patrol link:', url);
    }
}
</script>

</body>
</html>