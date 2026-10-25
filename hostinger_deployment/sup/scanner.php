<?php
/**
 * In-App Camera QR Code Scanner for Supervisors
 * Scans Camp and Site QR Checkpoints directly from mobile phone browser
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

$settings = get_system_settings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Scan Patrol QR Code | <?= htmlspecialchars($settings['company_name']) ?></title>
    
    <link rel="preload" href="<?= APP_URL ?>/assets/fonts/noto-sans-variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/design-tokens.css?v=<?= time() ?>">
    <?php require_once __DIR__ . '/../includes/theme_bootstrapper.php'; ?>
        
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= time() ?>">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body {
            background: #000;
            color: #fff;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }
        .scanner-header {
            padding: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(15, 23, 42, 0.9);
            z-index: 10;
        }
        .scanner-viewport {
            flex: 1;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #000;
        }
        #reader {
            width: 100% !important;
            height: 100% !important;
            border: none !important;
        }
        #reader video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
        }
        .scanner-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 260px;
            height: 260px;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 20px;
            pointer-events: none;
            box-shadow: 0 0 0 4000px rgba(0, 0, 0, 0.5);
        }
        .scanner-overlay::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 3px;
            background: #38bdf8;
            box-shadow: 0 0 12px #38bdf8;
            animation: scanLine 2s linear infinite alternate;
        }
        @keyframes scanLine {
            0% { top: 0; }
            100% { top: calc(100% - 3px); }
        }
        .scanner-footer {
            padding: 16px;
            background: rgba(15, 23, 42, 0.95);
            text-align: center;
            z-index: 10;
        }
    </style>
</head>
<body>

<div class="scanner-header">
    <div style="font-size: 15px; font-weight: 800; color: #fff;">
        Point Camera at QR Code
    </div>
    <a href="<?= APP_URL ?>/sup/duty.php" style="color: #94a3b8; text-decoration: none; font-size: 14px; font-weight: 600;">
        ✕ Cancel
    </a>
</div>

<div class="scanner-viewport">
    <div id="reader"></div>
    <div class="scanner-overlay"></div>
</div>

<div class="scanner-footer">
    <div style="font-size: 12px; color: #94a3b8; margin-bottom: 12px;">
        Scan Camp Checkpoint or Client Site QR Poster
    </div>
    <div>
        <label for="qrFileInput" class="btn btn-outline" style="cursor: pointer; padding: 10px 18px; font-size: 13px; color: #38bdf8; border-color: #38bdf8;">
            📁 Pick Photo from Gallery / Camera
        </label>
        <input type="file" id="qrFileInput" accept="image/*" capture="environment" style="display: none;">
    </div>
</div>

<script>
function onScanSuccess(decodedText, decodedResult) {
    if (window.hasHandledScan) return;
    window.hasHandledScan = true;

    // Beep sound
    try {
        let ctx = new (window.AudioContext || window.webkitAudioContext)();
        let osc = ctx.createOscillator();
        let gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = 800;
        gain.gain.value = 0.2;
        osc.start();
        setTimeout(function() { osc.stop(); ctx.close(); }, 150);
    } catch(e) {}

    // Offline queue check
    if (!navigator.onLine) {
        let queue = JSON.parse(localStorage.getItem('uss_offline_scans') || '[]');
        queue.push({
            token: decodedText,
            client_time: new Date().toISOString(),
            id: 'scan_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6)
        });
        localStorage.setItem('uss_offline_scans', JSON.stringify(queue));
        alert("⚡ Offline scan recorded! Saved locally on device. Will synchronize when network is restored.");
        window.location.href = <?= json_encode(APP_URL . '/sup/duty.php?msg=offline_queued') ?>;
        return;
    }

    // Check if the decoded string is a full URL or token
    let target = decodedText;
    if (target.indexOf('http://') === 0 || target.indexOf('https://') === 0) {
        // Full URL
        window.location.href = target;
    } else if (target.indexOf('CMP_') === 0 || target.indexOf('CAMP') !== -1) {
        // Camp token
        window.location.href = <?= json_encode(APP_URL . '/sup/index.php?camp=') ?> + encodeURIComponent(target);
    } else {
        // Site token
        window.location.href = <?= json_encode(APP_URL . '/sup/index.php?token=') ?> + encodeURIComponent(target);
    }
}

// Start HTML5 QR Code scanner
let html5QrCode = new Html5Qrcode("reader");
let config = { fps: 15, qrbox: { width: 250, height: 250 }, aspectRatio: 1.0 };

Html5Qrcode.getCameras().then(devices => {
    if (devices && devices.length) {
        // Prefer back camera
        let backCam = devices.find(d => d.label.toLowerCase().includes('back') || d.label.toLowerCase().includes('environment'));
        let cameraId = backCam ? backCam.id : devices[devices.length - 1].id;
        
        html5QrCode.start(
            cameraId,
            config,
            onScanSuccess
        ).catch(err => {
            console.error("Camera error:", err);
            // Fallback to facingMode environment
            html5QrCode.start({ facingMode: "environment" }, config, onScanSuccess);
        });
    } else {
        html5QrCode.start({ facingMode: "environment" }, config, onScanSuccess);
    }
}).catch(err => {
    html5QrCode.start({ facingMode: "environment" }, config, onScanSuccess);
});

// File picker fallback
document.getElementById('qrFileInput').addEventListener('change', e => {
    if (e.target.files.length == 0) return;
    const imageFile = e.target.files[0];
    html5QrCode.scanFile(imageFile, true)
        .then(decodedText => {
            onScanSuccess(decodedText, null);
        })
        .catch(err => {
            alert("No QR code detected in the selected image. Please try again with clear lighting.");
        });
});
</script>

</body>
</html>
