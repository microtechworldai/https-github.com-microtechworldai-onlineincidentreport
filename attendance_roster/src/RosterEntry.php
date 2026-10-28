<?php
/**
 * Universal Security Services - Attendance Roster Subsystem
 * Core Domain Logic & Strict Action Rules Engine (R1 to R9)
 * File: /attendance_roster/src/RosterEntry.php
 */
require_once dirname(__DIR__) . '/config/roster_config.php';
require_once __DIR__ . '/RosterAudit.php';
require_once __DIR__ . '/RosterStaff.php';
require_once __DIR__ . '/RosterSite.php';

class RosterConflictException extends Exception {
    protected array $conflictDetails;

    public function __construct(string $message, array $details = [], int $code = 409, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->conflictDetails = $details;
    }

    public function getConflictDetails(): array {
        return $this->conflictDetails;
    }
}

class RosterEntry {
    /**
     * Check if a site and month is locked (Rule R7)
     */
    public static function isLocked(int $siteId, int $year, int $month): bool {
        $db = roster_get_db();
        $stmt = $db->prepare("SELECT status FROM roster_sheet_status WHERE site_id = ? AND year = ? AND month = ? LIMIT 1");
        $stmt->execute([$siteId, $year, $month]);
        $status = $stmt->fetchColumn();
        return ($status === 'Locked');
    }

    /**
     * Set sheet status: 'Draft', 'Checked', 'Approved', 'Locked' (Rule R7 & Approval Workflow)
     */
    public static function setSheetStatus(int $siteId, int $year, int $month, string $status, ?string $reason = null): bool {
        $db = roster_get_db();
        $admin = $_SESSION['admin_name'] ?? 'Super Admin';
        $valid = ['Draft', 'Checked', 'Approved', 'Locked'];
        if (!in_array($status, $valid, true)) {
            throw new InvalidArgumentException("Invalid status: $status");
        }

        $stmt = $db->prepare("
            INSERT INTO roster_sheet_status (site_id, year, month, status, locked_by, locked_at, lock_reason)
            VALUES (?, ?, ?, ?, ?, IF(? = 'Locked', NOW(), NULL), ?)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                locked_by = VALUES(locked_by),
                locked_at = VALUES(locked_at),
                lock_reason = VALUES(lock_reason)
        ");
        $res = $stmt->execute([$siteId, $year, $month, $status, $admin, $status, $reason]);

        RosterAudit::log('SET_STATUS', null, $siteId, null, null, ['status' => $status], $reason);
        return $res;
    }

    /**
     * Check if a staff member already has attendance anywhere on a given date (Rule R3)
     */
    public static function checkConflict(int $staffId, string $date, ?int $excludeEntryId = null): ?array {
        $db = roster_get_db();
        $sql = "
            SELECT e.*, s.site_name, st.emp_no, st.name as staff_name
            FROM roster_entries e
            JOIN roster_sites_config s ON s.site_id = e.site_id
            JOIN roster_staff st ON st.id = e.staff_id
            WHERE e.staff_id = ? 
              AND e.work_date = ? 
              AND e.code IN ('D', 'N', 'B')
              AND e.deleted_at IS NULL
        ";
        $params = [$staffId, $date];
        if ($excludeEntryId !== null) {
            $sql .= " AND e.id != ? ";
            $params[] = $excludeEntryId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($conflicts)) {
            return null;
        }

        $first = $conflicts[0];
        $formattedDate = date('d M Y', strtotime($date));
        
        // Formulate clear, strict message per Rule R4
        $msg = "{$first['staff_name']} ({$first['emp_no']}) on {$formattedDate} worked at {$first['site_name']} ({$first['code']}).";

        return [
            'has_conflict' => true,
            'message' => $msg,
            'staff_id' => $staffId,
            'staff_name' => $first['staff_name'],
            'emp_no' => $first['emp_no'],
            'date' => $date,
            'formatted_date' => $formattedDate,
            'existing_site_id' => $first['site_id'],
            'existing_site_name' => $first['site_name'],
            'existing_code' => $first['code'],
            'existing_entry_id' => $first['id'],
            'all_conflicts' => $conflicts
        ];
    }

    /**
     * Save/Update a single cell attendance code (Rules R1, R3, R4, R5, R6, R7)
     */
    public static function setCellCode(int $staffId, int $siteId, string $date, string $code, bool $forceDouble = false, ?string $forceReason = null): array {
        $db = roster_get_db();
        $code = strtoupper(trim($code));
        $dateObj = new DateTime($date);
        $year = (int)$dateObj->format('Y');
        $month = (int)$dateObj->format('n');

        // Rule R7: Check locked month
        if (self::isLocked($siteId, $year, $month)) {
            throw new RuntimeException("Roster for this site and month is Locked and cannot be modified.");
        }

        // Validate code or empty (clearing cell)
        if ($code !== '' && !in_array($code, ROSTER_VALID_CODES, true)) {
            throw new InvalidArgumentException("Invalid code '$code'. Allowed: " . implode(', ', ROSTER_VALID_CODES));
        }

        $db->beginTransaction();
        try {
            // Check existing cell for this staff, site, and date
            $stmtExist = $db->prepare("
                SELECT * FROM roster_entries 
                WHERE staff_id = ? AND site_id = ? AND work_date = ? AND deleted_at IS NULL
                FOR UPDATE
            ");
            $stmtExist->execute([$staffId, $siteId, $date]);
            $existing = $stmtExist->fetch(PDO::FETCH_ASSOC);

            // CASE 1: Clearing cell
            if ($code === '') {
                if ($existing) {
                    $stmtDel = $db->prepare("DELETE FROM roster_entries WHERE id = ?");
                    $stmtDel->execute([$existing['id']]);

                    RosterAudit::log('DELETE', $staffId, $siteId, $date, $existing, null, 'Cleared cell');
                }
                $db->commit();
                return ['success' => true, 'action' => 'cleared', 'date' => $date];
            }

            // CASE 2: Assigning working code (D, N, B)
            if (in_array($code, ['D', 'N', 'B'], true)) {
                $conflict = self::checkConflict($staffId, $date, $existing['id'] ?? null);

                if ($conflict && !$forceDouble) {
                    // Rule R4: Reject and throw structured conflict details for UI modal
                    $db->rollBack();
                    throw new RosterConflictException($conflict['message'], $conflict);
                }
            }

            // Insert or Update entry
            $admin = $_SESSION['admin_name'] ?? 'Super Admin';
            $isDouble = $forceDouble ? 1 : 0;
            $undoToken = bin2hex(random_bytes(16));

            if ($existing) {
                $stmtUp = $db->prepare("
                    UPDATE roster_entries 
                    SET code = ?, is_forced_double = ?, force_reason = ?, updated_by = ?, version = version + 1
                    WHERE id = ?
                ");
                $stmtUp->execute([$code, $isDouble, $forceReason, $admin, $existing['id']]);
                $entryId = $existing['id'];

                RosterAudit::log($isDouble ? 'FORCE_DOUBLE' : 'EDIT', $staffId, $siteId, $date, $existing, [
                    'id' => $entryId, 'code' => $code, 'is_forced_double' => $isDouble
                ], $forceReason, $undoToken);
            } else {
                $stmtIns = $db->prepare("
                    INSERT INTO roster_entries (staff_id, site_id, work_date, code, is_forced_double, force_reason, created_by, updated_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtIns->execute([$staffId, $siteId, $date, $code, $isDouble, $forceReason, $admin, $admin]);
                $entryId = (int)$db->lastInsertId();

                RosterAudit::log($isDouble ? 'FORCE_DOUBLE' : 'ADD', $staffId, $siteId, $date, null, [
                    'id' => $entryId, 'code' => $code, 'is_forced_double' => $isDouble
                ], $forceReason, $undoToken);
            }

            $db->commit();
            return [
                'success' => true,
                'action' => $existing ? 'updated' : 'inserted',
                'entry_id' => $entryId,
                'code' => $code,
                'is_forced_double' => $isDouble,
                'undo_token' => $undoToken
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * R4 Action 3: Move attendance from previous site to new site (audited, atomic, updates counts at both sites)
     */
    public static function moveAttendance(int $staffId, string $date, int $fromSiteId, int $toSiteId, string $reason): array {
        $db = roster_get_db();
        $admin = $_SESSION['admin_name'] ?? 'Super Admin';

        if (empty($reason)) {
            throw new InvalidArgumentException("A reason is mandatory when moving attendance between sites.");
        }

        $dateObj = new DateTime($date);
        $year = (int)$dateObj->format('Y');
        $month = (int)$dateObj->format('n');

        if (self::isLocked($fromSiteId, $year, $month) || self::isLocked($toSiteId, $year, $month)) {
            throw new RuntimeException("Cannot move attendance: one of the affected site months is Locked.");
        }

        $db->beginTransaction();
        try {
            $stmtFind = $db->prepare("
                SELECT * FROM roster_entries 
                WHERE staff_id = ? AND site_id = ? AND work_date = ? AND deleted_at IS NULL
                FOR UPDATE
            ");
            $stmtFind->execute([$staffId, $fromSiteId, $date]);
            $existing = $stmtFind->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                throw new RuntimeException("Original attendance record not found at source site.");
            }

            // Transfer site_id
            $stmtMove = $db->prepare("
                UPDATE roster_entries 
                SET site_id = ?, moved_from_site_id = ?, moved_from_entry_id = ?, updated_by = ?, version = version + 1
                WHERE id = ?
            ");
            $stmtMove->execute([$toSiteId, $fromSiteId, $existing['id'], $admin, $existing['id']]);

            $undoToken = bin2hex(random_bytes(16));
            RosterAudit::log('MOVE', $staffId, $toSiteId, $date, $existing, [
                'id' => $existing['id'], 'site_id' => $toSiteId, 'from_site_id' => $fromSiteId
            ], $reason, $undoToken);

            $db->commit();
            return [
                'success' => true,
                'message' => "Attendance moved from Site #$fromSiteId to Site #$toSiteId successfully.",
                'undo_token' => $undoToken
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Compute entire site roster sheet for a month with counts, requirements, and WCAG AA statuses (Rule R6)
     */
    public static function getMonthlySheet(int $siteId, int $year, int $month): array {
        $db = roster_get_db();
        $reqData = RosterSite::getMonthlyRequirements($siteId, $year, $month);
        $daysInMonth = $reqData['daysInMonth'];
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        // 1. Fetch all staff who have assignments OR entries at this site in this month
        $stmtStaff = $db->prepare("
            SELECT DISTINCT s.*
            FROM roster_staff s
            WHERE s.id IN (
                SELECT staff_id FROM roster_entries 
                WHERE site_id = ? AND work_date BETWEEN ? AND ? AND deleted_at IS NULL
                UNION
                SELECT staff_id FROM roster_assignments 
                WHERE site_id = ? AND date_from <= ? AND (date_to IS NULL OR date_to >= ?)
            )
            ORDER BY s.name ASC
        ");
        $stmtStaff->execute([$siteId, $monthStart, $monthEnd, $siteId, $monthEnd, $monthStart]);
        $staffList = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);

        // 2. Fetch all entries for this site & month
        $stmtEntries = $db->prepare("
            SELECT * FROM roster_entries 
            WHERE site_id = ? AND work_date BETWEEN ? AND ? AND deleted_at IS NULL
            ORDER BY staff_id ASC, work_date ASC
        ");
        $stmtEntries->execute([$siteId, $monthStart, $monthEnd]);
        $allEntries = $stmtEntries->fetchAll(PDO::FETCH_ASSOC);

        // Map entries by [staff_id][day]
        $entryMap = [];
        foreach ($allEntries as $e) {
            $day = (int)date('j', strtotime($e['work_date']));
            $entryMap[$e['staff_id']][$day] = [
                'id' => (int)$e['id'],
                'code' => $e['code'],
                'is_forced_double' => (int)$e['is_forced_double'],
                'force_reason' => $e['force_reason']
            ];
        }

        // 3. Compute live daily counts (Rule R6)
        $actualDay = array_fill(1, $daysInMonth, 0);
        $actualNight = array_fill(1, $daysInMonth, 0);
        $actualBasic = array_fill(1, $daysInMonth, 0);
        $totalDuty = array_fill(1, $daysInMonth, 0);

        foreach ($staffList as $st) {
            $sid = $st['id'];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $code = $entryMap[$sid][$d]['code'] ?? '';
                if ($code === 'D') {
                    $actualDay[$d]++;
                    $totalDuty[$d]++;
                } elseif ($code === 'N') {
                    $actualNight[$d]++;
                    $totalDuty[$d]++;
                } elseif ($code === 'B') {
                    $actualBasic[$d]++;
                }
            }
        }

        // 4. Compute daily variances and WCAG AA deployment status
        $dailySummary = [];
        $totalAdditionalDayStaffDays = 0;
        $totalAdditionalNightStaffDays = 0;
        $totalShortageStaffDays = 0;
        $totalSurplusStaffDays = 0;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $reqD = $reqData['days'][$d]['required_day'];
            $reqN = $reqData['days'][$d]['required_night'];
            $actD = $actualDay[$d];
            $actN = $actualNight[$d];
            $actTot = $totalDuty[$d];

            $totalAdditionalDayStaffDays += $reqData['days'][$d]['additional_day'];
            $totalAdditionalNightStaffDays += $reqData['days'][$d]['additional_night'];

            // Variance: Actual - Required
            $varD = $actD - $reqD;
            $varN = $actN - $reqN;

            // Track shortage / surplus staff days
            if ($varD < 0) $totalShortageStaffDays += abs($varD);
            if ($varD > 0) $totalSurplusStaffDays += $varD;
            if ($varN < 0) $totalShortageStaffDays += abs($varN);
            if ($varN > 0) $totalSurplusStaffDays += $varN;

            // Status: 'ok', 'less', 'over', 'unset'
            $statusD = ($reqD === 0 && $actD === 0) ? 'neutral' : ($actD === $reqD ? 'ok' : ($actD < $reqD ? 'less' : 'over'));
            $statusN = ($reqN === 0 && $actN === 0) ? 'neutral' : ($actN === $reqN ? 'ok' : ($actN < $reqN ? 'less' : 'over'));

            $dailySummary[$d] = [
                'day' => $d,
                'req_day' => $reqD,
                'req_night' => $reqN,
                'act_day' => $actD,
                'act_night' => $actN,
                'act_total' => $actTot,
                'var_day' => $varD,
                'var_night' => $varN,
                'status_day' => $statusD,
                'status_night' => $statusN
            ];
        }

        // Check sheet approval/lock status
        $stmtStatus = $db->prepare("SELECT * FROM roster_sheet_status WHERE site_id = ? AND year = ? AND month = ? LIMIT 1");
        $stmtStatus->execute([$siteId, $year, $month]);
        $sheetStatus = $stmtStatus->fetch(PDO::FETCH_ASSOC) ?: [
            'status' => 'Draft', 'locked_by' => null, 'locked_at' => null
        ];

        return [
            'site' => $reqData['site'],
            'year' => $year,
            'month' => $month,
            'daysInMonth' => $daysInMonth,
            'staffList' => $staffList,
            'entries' => $entryMap,
            'dailySummary' => $dailySummary,
            'requirements' => $reqData['days'],
            'additionalDeployments' => $reqData['additional_list'],
            'monthTotalDay' => array_sum($actualDay),
            'monthTotalNight' => array_sum($actualNight),
            'monthTotalDuty' => array_sum($totalDuty),
            'totalAdditionalDayStaffDays' => $totalAdditionalDayStaffDays,
            'totalAdditionalNightStaffDays' => $totalAdditionalNightStaffDays,
            'totalShortageStaffDays' => $totalShortageStaffDays,
            'totalSurplusStaffDays' => $totalSurplusStaffDays,
            'sheetStatus' => $sheetStatus,
            'isLocked' => ($sheetStatus['status'] === 'Locked')
        ];
    }
}
