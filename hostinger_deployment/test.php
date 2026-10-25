<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

echo "<div style='font-family:sans-serif; max-width:650px; margin:40px auto; padding:25px; border-radius:12px; background:#1e293b; color:#f8fafc; box-shadow:0 10px 30px rgba(0,0,0,0.5);'>";
echo "<h2 style='color:#38bdf8; margin-top:0;'>Hostinger Server Diagnostic</h2>";
echo "<p><b>PHP Version:</b> " . PHP_VERSION . "</p>";
echo "<p><b>Current Time:</b> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><b>Directory:</b> " . __DIR__ . "</p>";

echo "<hr style='border:none; border-top:1px solid #334155; margin:20px 0;'>";
echo "<h3 style='color:#fbbf24;'>MySQL Connection Test</h3>";

try {
    require_once __DIR__ . '/config.php';
    echo "<p>config.php loaded. Host: <code>" . DB_HOST . "</code> | DB: <code>" . DB_NAME . "</code> | User: <code>" . DB_USER . "</code></p>";

    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "<p style='color:#4ade80; font-weight:bold;'>MySQL Connected Successfully!</p>";
    
    // Check tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p><b>Total Tables Found:</b> " . count($tables) . "</p>";
    echo "<p style='font-size:12px; color:#94a3b8; word-break:break-all;'>" . implode(', ', $tables) . "</p>";
    
} catch (Throwable $e) {
    echo "<div style='background:rgba(239,68,68,0.2); border:1px solid #ef4444; color:#fca5a5; padding:12px; border-radius:8px;'>";
    echo "<b>Error:</b> " . htmlspecialchars($e->getMessage());
    echo "<br><small style='color:#94a3b8;'>In " . $e->getFile() . " on line " . $e->getLine() . "</small>";
    echo "</div>";
}

echo "</div>";
