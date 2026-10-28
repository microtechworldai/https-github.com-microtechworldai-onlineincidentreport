<?php
/**
 * Migration Runner for Attendance Roster Subsystem
 * File: /attendance_roster/migrations/migrate.php
 */
require_once dirname(__DIR__) . '/config/roster_config.php';

function roster_run_migration($action = 'up') {
    $db = roster_get_db();
    $dir = __DIR__;

    if ($action === 'down') {
        $file = $dir . '/001_rollback_roster_tables.sql';
        echo "Rolling back roster_* tables...\n";
    } else {
        $file = $dir . '/001_create_roster_tables.sql';
        echo "Applying roster_* migration...\n";
    }

    if (!file_exists($file)) {
        throw new RuntimeException("Migration file not found: $file");
    }

    $sql = file_get_contents($file);
    // Split and execute individual queries
    $queries = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($queries as $q) {
        if (!empty($q)) {
            $db->exec($q);
        }
    }

    echo "Migration completed successfully (" . count($queries) . " statements executed).\n";
}

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $action = $argv[1] ?? 'up';
    roster_run_migration($action);
}

