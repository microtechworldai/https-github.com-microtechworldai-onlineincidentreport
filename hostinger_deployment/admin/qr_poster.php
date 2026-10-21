<?php
/**
 * Official Printable A4 Security Checkpoint QR Poster
 * Designed for Physical Mounting at Patrol Desks & Security Posts
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

$rawToken = '';
if (!empty($site['token_encrypted'])) {
    $rawToken = decrypt_field($site['token_encrypted']);
}
if (empty($rawToken)) {
    $rawToken = $site['active_qr_token'] ?: ('uss_qr_' . preg_replace('/[^a-z0-9]/', '', strtolower($site['code'])) . '_' . bin2hex(random_bytes(10)));
    $db->prepare("UPDATE sites SET token_hmac = ?, token_encrypted = ?, active_qr_token = ? WHERE id = ?")->execute([
        hash_qr_token($rawToken),
        encrypt_field($rawToken),
        $rawToken,
        $site['id']
    ]);
}

$guardUrl = $qrBaseUrl . '/g/index.php?token=' . urlencode($rawToken);
$qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=8&data=" . urlencode($guardUrl);
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
            font-family: 'Arial', 'Helvetica Neue', Helvetica, sans-serif;
            background: #e2e8f0;
            color: #0f172a;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        .no-print-bar {
            width: 100%;
            background: #0f172a;
            color: #fff;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .btn-action {
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            transition: transform 0.15s ease;
        }
        .btn-action:hover {
            transform: translateY(-1px);
        }

        /* A4 Page Canvas */
        .poster-canvas {
            width: 210mm;
            min-height: 297mm;
            background: #fff;
            margin: 20px auto;
            padding: 16mm 18mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            border: 1px solid #cbd5e1;
            position: relative;
        }

        .poster-header {
            width: 100%;
            text-align: center;
            border-bottom: 3px solid #0284c7;
            padding-bottom: 16px;
        }

        .poster-logo {
            height: 64px;
            max-width: 260px;
            object-fit: contain;
            margin-bottom: 8px;
        }

        .poster-header h1 {
            font-size: 20pt;
            font-weight: 900;
            color: #0b192c;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .poster-header h2 {
            font-size: 13pt;
            font-weight: 700;
            color: #0284c7;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-top: 4px;
        }

        .site-box {
            width: 100%;
            background: #f1f5f9;
            border: 2px solid #cbd5e1;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            margin: 16px 0;
        }

        .site-name {
            font-size: 24pt;
            font-weight: 900;
            color: #0f172a;
            line-height: 1.2;
        }

        .site-code-badge {
            display: inline-block;
            background: #0284c7;
            color: #fff;
            font-family: monospace;
            font-size: 14pt;
            font-weight: bold;
            padding: 4px 16px;
            border-radius: 6px;
            margin-top: 8px;
            letter-spacing: 1px;
        }

        .qr-frame {
            background: #fff;
            padding: 20px;
            border: 4px solid #0f172a;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 10px 0;
        }

        #qrImage, #qrcodeCanvas {
            width: 260px !important;
            height: 260px !important;
            display: block;
            border-radius: 8px;
        }

        .qr-caption {
            margin-top: 12px;
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
            gap: 14px;
            margin: 14px 0;
        }

        .step-card {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 12px;
            background: #f8fafc;
            text-align: center;
        }

        .step-num {
            width: 28px;
            height: 28px;
            background: #0284c7;
            color: #fff;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12pt;
            margin-bottom: 6px;
        }

        .step-title {
            font-size: 10.5pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .step-desc {
            font-size: 8.5pt;
            color: #64748b;
            line-height: 1.3;
        }

        .poster-footer {
            width: 100%;
            border-top: 2px solid #e2e8f0;
            padding-top: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 9pt;
            color: #64748b;
        }

        .url-text {
            font-family: monospace;
            font-size: 8.5pt;
            color: #0284c7;
            word-break: break-all;
        }

        @media print {
            .no-print-bar { display: none !important; }
            body { background: #fff !important; }
            .poster-canvas {
                border: none !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 14mm 16mm !important;
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
        <button style="background: transparent; color: #fff; border: 1px solid #475569; padding: 10px 18px; border-radius: 8px; cursor: pointer;" onclick="window.close();">Close</button>
    </div>
</div>

<div class="poster-canvas">

    <!-- Header -->
    <div class="poster-header">
        <img class="poster-logo" src="<?= APP_URL ?>/<?= htmlspecialchars($settings['logo_url']) ?>" alt="Logo" onerror="this.style.display='none'">
        <h1><?= htmlspecialchars($settings['company_name']) ?></h1>
        <h2>Official Incident Reporting Checkpoint</h2>
    </div>

    <!-- Facility Highlight -->
    <div class="site-box">
        <div style="font-size: 11pt; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px;">Security Patrol Post</div>
        <div class="site-name"><?= htmlspecialchars($site['name']) ?></div>
        <div class="site-code-badge">SITE CODE: <?= htmlspecialchars($site['code']) ?></div>
    </div>

    <!-- QR Code Centerpiece: Dual-mode Guaranteed Rendering -->
    <div class="qr-frame">
        <img id="qrImage" src="<?= htmlspecialchars($qrApiUrl) ?>" alt="Security Checkpoint QR Code" crossOrigin="anonymous">
        <canvas id="qrcodeCanvas" style="display: none;"></canvas>
        <div class="qr-caption">Scan With Phone Camera To Report Incident</div>
    </div>

    <!-- Step by Step Instructions -->
    <div class="instruction-box">
        <div class="step-card">
            <div class="step-num">1</div>
            <div class="step-title">Scan QR Code</div>
            <div class="step-desc">Open your mobile phone camera and point at this code. Tap the link banner.</div>
        </div>
        <div class="step-card">
            <div class="step-num">2</div>
            <div class="step-title">Guard Sign In</div>
            <div class="step-desc">Enter your Officer Staff ID / Username and Password to access the patrol form.</div>
        </div>
        <div class="step-card">
            <div class="step-num">3</div>
            <div class="step-title">Submit &amp; Photos</div>
            <div class="step-desc">Fill report narrative, attach live camera photos, and submit for instant review.</div>
        </div>
    </div>

    <!-- Direct Fallback Shortlink -->
    <div style="text-align: center; margin: 4px 0;">
        <span style="font-size: 9pt; color: #64748b;">Direct Patrol Link:</span>
        <div class="url-text"><?= htmlspecialchars($guardUrl) ?></div>
    </div>

    <!-- Footer -->
    <div class="poster-footer">
        <div>
            <strong>Operations Hotline:</strong> <?= htmlspecialchars($settings['phone']) ?><br>
            <strong>Support:</strong> <?= htmlspecialchars($settings['email']) ?>
        </div>
        <div style="text-align: right;">
            <div>Dubai, United Arab Emirates</div>
            <div style="font-size: 8pt; color: #94a3b8;">Printed: <?= date('Y-m-d H:i') ?> GST</div>
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
            width: 260,
            margin: 2,
            color: {
                dark: '#000000',
                light: '#ffffff'
            },
            errorCorrectionLevel: 'H'
        }, function (error) {
            if (!error) {
                // Smoothly replace external image with local canvas render
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
