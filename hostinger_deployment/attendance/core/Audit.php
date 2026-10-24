<?php
/**
 * Append-Only Tamper-Evident Audit Logging for Attendance Subsystem
 * Cryptographic SHA-256 hash chaining (independent from incident audit logs)
 */
require_once __DIR__ . '/../config/database.php';

function att_log_audit($action, $entityType, $entityId = null, $details = null, $actorName = 'Super Admin') {
    try {
        $db = att_get_db();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);
        $detailsStr = is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : (string)($details ?? '');
        $timestamp = date('Y-m-d H:i:s');

        // Fetch last hash
        $lastRow = $db->query("SELECT record_hash FROM att_audit ORDER BY id DESC LIMIT 1")->fetch();
        $prevHash = (!empty($lastRow) && !empty($lastRow['record_hash'])) ? $lastRow['record_hash'] : 'GENESIS_ATTENDANCE_HASH';

        // Calculate chained hash
        $recordHash = hash('sha256', implode('|', [
            $prevHash,
            $timestamp,
            $action,
            $entityType,
            (string)$entityId,
            $detailsStr,
            $actorName,
            $ip
        ]));

        $stmt = $db->prepare("
            INSERT INTO att_audit 
            (prev_hash, record_hash, action, entity_type, entity_id, details, actor_name, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $prevHash,
            $recordHash,
            $action,
            $entityType,
            (string)$entityId,
            $detailsStr,
            $actorName,
            $ip,
            $ua,
            $timestamp
        ]);
    } catch (Throwable $e) {
        @error_log("[" . date('Y-m-d H:i:s') . "] Attendance Audit Warning: " . $e->getMessage() . "\n", 3, ATT_LOGS_PATH . '/audit_errors.log');
    }
}
