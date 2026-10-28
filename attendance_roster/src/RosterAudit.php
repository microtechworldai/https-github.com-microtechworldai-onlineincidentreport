<?php
/**
 * Universal Security Services - Attendance Roster Subsystem
 * Immutable Audit Trail & Rollback / Undo Engine
 * File: /attendance_roster/src/RosterAudit.php
 */
require_once dirname(__DIR__) . '/config/roster_config.php';

class RosterAudit {
    /**
     * Record an audited roster action
     */
    public static function log(string $actionType, ?int $staffId, ?int $siteId, ?string $workDate, $beforeState, $afterState, ?string $reason = null, ?string $undoToken = null): int {
        $db = roster_get_db();
        $user = $_SESSION['admin_name'] ?? $_SESSION['username'] ?? 'Super Admin';

        $beforeJson = $beforeState !== null ? json_encode($beforeState, JSON_UNESCAPED_UNICODE) : null;
        $afterJson = $afterState !== null ? json_encode($afterState, JSON_UNESCAPED_UNICODE) : null;

        $stmt = $db->prepare("
            INSERT INTO roster_audit (action_type, staff_id, site_id, work_date, before_state, after_state, reason, undo_token, performed_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $actionType,
            $staffId,
            $siteId,
            $workDate,
            $beforeJson,
            $afterJson,
            $reason,
            $undoToken,
            $user
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Get recent audit records with pagination
     */
    public static function getRecent(int $limit = 50, ?int $siteId = null): array {
        $db = roster_get_db();
        $sql = "
            SELECT a.*, s.name as staff_name, s.emp_no, sc.site_name
            FROM roster_audit a
            LEFT JOIN roster_staff s ON s.id = a.staff_id
            LEFT JOIN roster_sites_config sc ON sc.site_id = a.site_id
        ";
        $params = [];
        if ($siteId !== null) {
            $sql .= " WHERE a.site_id = ? ";
            $params[] = $siteId;
        }
        $sql .= " ORDER BY a.id DESC LIMIT " . (int)$limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Undo an action using its undo token
     */
    public static function undo(string $undoToken): bool {
        $db = roster_get_db();
        $stmt = $db->prepare("SELECT * FROM roster_audit WHERE undo_token = ? AND is_undone = 0 LIMIT 1");
        $stmt->execute([$undoToken]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            throw new RuntimeException("Audit record not found or already undone.");
        }

        $before = json_decode($record['before_state'] ?? '', true);
        $after = json_decode($record['after_state'] ?? '', true);
        $action = $record['action_type'];

        $db->beginTransaction();
        try {
            if ($action === 'ADD' || $action === 'FORCE_DOUBLE') {
                // Delete the added entry
                $targetId = $after['id'] ?? $before['id'] ?? 0;
                if ($targetId > 0) {
                    $stmtDel = $db->prepare("DELETE FROM roster_entries WHERE id = ?");
                    $stmtDel->execute([$targetId]);
                }
            } elseif ($action === 'EDIT' || $action === 'MOVE') {
                // Restore before state
                if ($before && !empty($before['id'])) {
                    $stmtRestore = $db->prepare("
                        UPDATE roster_entries 
                        SET site_id = ?, code = ?, is_forced_double = ?, updated_by = 'Super Admin (Undo)'
                        WHERE id = ?
                    ");
                    $stmtRestore->execute([$before['site_id'], $before['code'], $before['is_forced_double'], $before['id']]);
                }
            } elseif ($action === 'DELETE') {
                // Restore deleted entry
                if ($before) {
                    $stmtIns = $db->prepare("
                        INSERT INTO roster_entries (id, staff_id, site_id, work_date, code, is_forced_double, force_reason, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'Super Admin (Undo)')
                    ");
                    $stmtIns->execute([
                        $before['id'], $before['staff_id'], $before['site_id'], $before['work_date'],
                        $before['code'], $before['is_forced_double'], $before['force_reason']
                    ]);
                }
            }

            // Mark audit as undone
            $stmtMark = $db->prepare("UPDATE roster_audit SET is_undone = 1 WHERE id = ?");
            $stmtMark->execute([$record['id']]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
