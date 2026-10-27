<?php
/**
 * Universal 1-Click Server-Side Unzipper for Hostinger
 * Extracts the deployment zip directly into the current directory (public_html root)
 */
header('Content-Type: text/html; charset=utf-8');

$zipNames = [
    'UPLOAD_TO_HOSTINGER_public_html.zip',
    'hostinger_deployment.zip',
    'HOSTINGER_UPDATE_PATCH_LATEST.zip'
];

$zipFile = null;
foreach ($zipNames as $name) {
    if (file_exists(__DIR__ . '/' . $name)) {
        $zipFile = __DIR__ . '/' . $name;
        break;
    }
}

// Target is the EXACT current root folder where unzip.php is placed!
$targetDir = __DIR__;

if (!$zipFile) {
    die("<div style='font-family:sans-serif; text-align:center; padding:40px; background:#0f172a; color:#fff;'>
        <h2 style='color:#ef4444;'>Error: No Deployment Zip Found</h2>
        <p>Please upload <strong>UPLOAD_TO_HOSTINGER_public_html.zip</strong> into: <br><code>" . htmlspecialchars(__DIR__) . "</code></p>
    </div>");
}

$zip = new ZipArchive;
$res = $zip->open($zipFile);

if ($res === TRUE) {
    $zip->extractTo($targetDir);
    $zip->close();
    echo "<div style='font-family:sans-serif; text-align:center; padding:40px; background:#0f172a; color:#f8fafc; min-height:100vh;'>";
    echo "<div style='max-width:600px; margin:40px auto; background:#1e293b; padding:32px; border-radius:12px; border:1px solid #334155;'>";
    echo "<h2 style='color:#10b981; margin-top:0;'> SUCCESS! Files Extracted to Root Folder!</h2>";
    echo "<p style='color:#94a3b8;'>Source: <strong style='color:#fff;'>" . basename($zipFile) . "</strong></p>";
    echo "<p style='color:#94a3b8;'>Extracted directly into root directory: <br><code style='color:#38bdf8; background:#0f172a; padding:4px 8px; border-radius:4px;'>" . htmlspecialchars($targetDir) . "</code></p>";
    echo "<div style='margin-top:24px; display:flex; gap:12px; justify-content:center;'>";
    echo "<a href='fix.php' style='display:inline-block; padding:12px 24px; background:#2563eb; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>👉 Run Auto-Repair (fix.php)</a>";
    echo "<a href='admin/dashboard.php' style='display:inline-block; padding:12px 24px; background:#10b981; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>👉 Open Dashboard</a>";
    echo "</div>";
    echo "</div></div>";
} else {
    echo "<h2 style='color:red;'>Extraction failed with ZipArchive error code: " . $res . "</h2>";
}
