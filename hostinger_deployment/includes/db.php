<?php
/**
 * Database Connection Singleton using PDO
 * Supports MySQL / MariaDB on Hostinger
 */

require_once __DIR__ . '/../config.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Check if we are running install.php or need setup
            if (basename($_SERVER['PHP_SELF']) !== 'install.php') {
                $installUrl = APP_URL . '/install.php';
                $errorMsg = htmlspecialchars($e->getMessage());
                die("
                <!DOCTYPE html>
                <html lang='en'>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <title>Database Connection Notice - USS Incidents</title>
                    <style>
                        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
                        .card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 36px; max-width: 580px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
                        h2 { color: #38bdf8; margin-top: 0; font-size: 24px; display: flex; align-items: center; gap: 10px; }
                        p { line-height: 1.6; color: #94a3b8; font-size: 15px; }
                        .error-box { background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #fca5a5; padding: 14px; border-radius: 8px; font-family: monospace; font-size: 13px; word-break: break-all; margin: 20px 0; }
                        .btn { display: inline-block; background: #2563eb; color: #ffffff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px; transition: background 0.2s; }
                        .btn:hover { background: #1d4ed8; }
                        .steps { margin: 20px 0; padding-left: 20px; color: #cbd5e1; }
                        .steps li { margin-bottom: 8px; }
                    </style>
                </head>
                <body>
                    <div class='card'>
                        <h2>⚙️ Database Setup Required</h2>
                        <p>Welcome to <strong>USS Incident Reporting System</strong> for <strong>sprinzo.in</strong>. The application could not connect to MySQL with the current configuration in <code>config.php</code>.</p>
                        
                        <div class='error-box'>Database Error: {$errorMsg}</div>
                        
                        <p><strong>Quick 2-Minute Solution:</strong></p>
                        <ol class='steps'>
                            <li>Log in to your <strong>Hostinger hPanel</strong>.</li>
                            <li>Go to <strong>Databases &rarr; Management</strong> and create a MySQL database & user.</li>
                            <li>Run the automated web installer below to initialize tables in 1-click!</li>
                        </ol>
                        
                        <a href='{$installUrl}' class='btn'>Launch 1-Click Setup Wizard &rarr;</a>
                    </div>
                </body>
                </html>
                ");
            } else {
                throw $e;
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }
}

/**
 * Helper function to retrieve PDO database connection
 * @return PDO
 */
function get_db() {
    return Database::getInstance();
}
