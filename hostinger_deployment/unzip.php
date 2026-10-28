<?php
/**
 * Universal 1-Click Server-Side Unzipper for Hostinger
 * Extracts the deployment zip directly into the current directory (public_html root)
 */
header('Content-Type: text/html; charset=utf-8');

$zipNames = [
    'HOSTINGER_UPDATE_PATCH_LATEST.zip',
    'UPLOAD_TO_HOSTINGER.zip',
    'UPLOAD_TO_HOSTINGER_public_html.zip',
    'hostinger_deployment.zip'
];

$candidateZips = [];
foreach ($zipNames as $name) {
    $f = __DIR__ . '/' . $name;
    if (file_exists($f)) {
        $candidateZips[] = [
            'path' => $f,
            'mtime' => filemtime($f),
            'name' => $name,
            'size' => filesize($f)
        ];
    }
}

// Automatically pick the NEWEST zip file by modification timestamp!
usort($candidateZips, function($a, $b) {
    return $b['mtime'] - $a['mtime'];
});

$zipFile = !empty($candidateZips) ? $candidateZips[0]['path'] : null;
$zipName = !empty($candidateZips) ? $candidateZips[0]['name'] : '';
$zipMtime = !empty($candidateZips) ? date('Y-m-d H:i:s', $candidateZips[0]['mtime']) : '';

$targetDir = __DIR__;

if (!$zipFile) {
    die("<div style='font-family:sans-serif; text-align:center; padding:40px; background:#0f172a; color:#fff;'>
        <h2 style='color:#ef4444;'>Error: No Deployment Zip Found</h2>
        <p>Please upload <strong>HOSTINGER_UPDATE_PATCH_LATEST.zip</strong> into: <br><code>" . htmlspecialchars(__DIR__) . "</code></p>
    </div>");
}

$zip = new ZipArchive;
$res = $zip->open($zipFile);

if ($res === TRUE) {
    $numFiles = $zip->numFiles;
    $zip->extractTo($targetDir);
    $zip->close();
    echo "<div style='font-family:sans-serif; text-align:center; padding:40px; background:#0f172a; color:#f8fafc; min-height:100vh;'>";
    echo "<div style='max-width:640px; margin:40px auto; background:#1e293b; padding:32px; border-radius:12px; border:1px solid #334155; box-shadow:0 10px 25px rgba(0,0,0,0.5);'>";
    echo "<h2 style='color:#10b981; margin-top:0;'>✅ SUCCESS! {$numFiles} Files Extracted!</h2>";
    echo "<p style='color:#94a3b8;'>Active Package: <strong style='color:#38bdf8;'>" . htmlspecialchars($zipName) . "</strong></p>";
    echo "<p style='color:#94a3b8;'>Timestamp: <strong style='color:#facc15;'>{$zipMtime}</strong></p>";
    echo "<p style='color:#94a3b8;'>Extracted into: <br><code style='color:#38bdf8; background:#0f172a; padding:6px 12px; border-radius:4px; display:inline-block; margin-top:6px;'>" . htmlspecialchars($targetDir) . "</code></p>";
    echo "<div style='margin-top:24px; display:flex; gap:12px; justify-content:center; flex-wrap:wrap;'>";
    echo "<a href='attendance_roster/index.php' style='display:inline-block; padding:12px 24px; background:#0f766e; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>🏢 New Attendance Roster</a>";
    echo "<a href='attendance/index.php?action=sheet' style='display:inline-block; padding:12px 24px; background:#10b981; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>👉 Attendance Sheet</a>";
    echo "<a href='attendance/index.php?action=payroll' style='display:inline-block; padding:12px 24px; background:#0284c7; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>👉 Consolidated Payroll</a>";
    echo "<a href='admin/index.php' style='display:inline-block; padding:12px 24px; background:#475569; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>🛡️ Admin Panel</a>";
    echo "</div>";
    echo "</div></div>";
} else {
    echo "<h2 style='color:red;'>Extraction failed with ZipArchive error code: " . $res . "</h2>";
}
