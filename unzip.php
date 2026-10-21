<?php
/**
 * 1-Click Server-Side Unzipper for Hostinger
 * Extracts hostinger_deployment.zip into /public_html/incidents/
 */
header('Content-Type: text/html; charset=utf-8');

$zipFile = __DIR__ . '/hostinger_deployment.zip';
$targetDir = __DIR__ . '/incidents/';

if (!file_exists($zipFile)) {
    if (file_exists(__DIR__ . '/incidents/hostinger_deployment.zip')) {
        $zipFile = __DIR__ . '/incidents/hostinger_deployment.zip';
    } else {
        die("<h2 style='color:red;'>Error: hostinger_deployment.zip not found in " . htmlspecialchars(__DIR__) . "</h2>");
    }
}

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

$zip = new ZipArchive;
$res = $zip->open($zipFile);

if ($res === TRUE) {
    $zip->extractTo($targetDir);
    $zip->close();
    echo "<div style='font-family:sans-serif; text-align:center; padding:40px;'>";
    echo "<h2 style='color:green;'>SUCCESS! All Files Extracted Successfully!</h2>";
    echo "<p>Files extracted into: <strong>/public_html/incidents/</strong></p>";
    echo "<p><a href='https://incidents.sprinzo.in/install.php' style='display:inline-block; padding:12px 24px; background:#2563eb; color:white; text-decoration:none; border-radius:6px; font-weight:bold;'>Click Here to Open Installer (install.php) &rarr;</a></p>";
    echo "</div>";
} else {
    echo "<h2 style='color:red;'>Extraction failed with ZipArchive error code: " . $res . "</h2>";
}
