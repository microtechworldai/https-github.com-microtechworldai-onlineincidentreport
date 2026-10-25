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
$campToken = get_stored_qr_token($camp, 'qr_token_encrypted', 'qr_token');
if ($campToken === '') {
    http_response_code(410);
    die("This camp QR has been revoked. Generate a new QR from Camp Management.");
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

$supervisorUrl = $qrBaseUrl . '/sup/index.php?camp=' . urlencode($campToken);
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
            transition: transform 0.15s ease;
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

        /* Camp Card */
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
            <strong>Camp Checkpoint Poster:</strong> <?= htmlspecialchars($camp['camp_name']) ?> (<?= htmlspecialchars($camp['camp_code']) ?>)
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
        <h2>OFFICIAL SUPERVISOR SHIFT CHECKPOINT</h2>
        <div class="header-divider"></div>
    </div>

    <!-- Accommodation Camp Card -->
    <div class="site-card">
        <div class="site-card-label">SUPERVISOR ACCOMMODATION BASE</div>
        <div class="site-card-name"><?= htmlspecialchars($camp['camp_name']) ?></div>
        <div class="site-card-code">CAMP CODE: <?= htmlspecialchars($camp['camp_code']) ?></div>
    </div>

    <!-- QR Code Center Card -->
    <div class="qr-card">
        <canvas id="qrcodeCanvas" width="250" height="250" style="display: none;"></canvas>
        <div id="qrUnavailable" style="display:none; width:250px; height:250px; align-items:center; justify-content:center; color:#b91c1c; font-weight:700;">QR could not be rendered. Use the direct link below.</div>
        <div class="qr-caption">SCAN WITH PHONE CAMERA FOR CAMP OUT / CAMP IN</div>
    </div>

    <!-- 3 Steps Instructions Grid -->
    <div class="steps-grid">
        <div class="step-item">
            <div class="step-badge">1</div>
            <div class="step-title">Scan QR Code</div>
            <div class="step-desc">Open phone camera and point at this code to begin or conclude supervisor patrol duty.</div>
        </div>
        <div class="step-item">
            <div class="step-badge">2</div>
            <div class="step-title">Camp Out (Start)</div>
            <div class="step-desc">Register departure from accommodation camp to activate mobile site visit rounds.</div>
        </div>
        <div class="step-item">
            <div class="step-badge">3</div>
            <div class="step-title">Camp In (Finish)</div>
            <div class="step-desc">Return to camp, scan code to submit daily duty dossier and conclude shift.</div>
        </div>
    </div>

    <!-- Direct Fallback Link -->
    <div class="direct-link-box">
        <div class="direct-link-label">Direct Supervisor Link:</div>
        <a href="<?= htmlspecialchars($supervisorUrl) ?>" class="direct-link-url" target="_blank"><?= htmlspecialchars($supervisorUrl) ?></a>
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
window.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('qrcodeCanvas');
    const unavailable = document.getElementById('qrUnavailable');
    const qrData = <?= json_encode($supervisorUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    
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
            } else {
                unavailable.style.display = 'flex';
            }
        });
    } else {
        unavailable.style.display = 'flex';
    }
});

function downloadQrCode() {
    const canvas = document.getElementById('qrcodeCanvas');
    const a = document.createElement('a');
    a.download = <?= json_encode('QR_Camp_' . $camp['camp_code'] . '.png', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    if (canvas && canvas.style.display !== 'none' && canvas.toDataURL) {
        a.href = canvas.toDataURL('image/png');
    } else {
        alert('QR could not be rendered. Use the direct link below.');
        return;
    }
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function copyPatrolLink() {
    const url = <?= json_encode($supervisorUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() {
            alert('Camp Checkpoint URL copied to clipboard:\n' + url);
        });
    } else {
        prompt('Copy this camp link:', url);
    }
}
</script>

</body>
</html>