<?php
/**
 * Staff Directory Model for Attendance Subsystem
 * Independent from incident guards or supervisors
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Audit.php';

class StaffDirectory {
    public static function searchStaff($query = '', $limit = 50) {
        $db = att_get_db();
        $query = trim($query);
        if ($query === '') {
            $stmt = $db->prepare("SELECT * FROM att_staff WHERE is_active = 1 ORDER BY emp_name ASC LIMIT ?");
            $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $term = '%' . $query . '%';
        $stmt = $db->prepare("
            SELECT * FROM att_staff 
            WHERE (emp_no LIKE ? OR emp_name LIKE ? OR designation LIKE ?)
            ORDER BY emp_name ASC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $term);
        $stmt->bindValue(2, $term);
        $stmt->bindValue(3, $term);
        $stmt->bindValue(4, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getByEmpNo($empNo) {
        $db = att_get_db();
        $stmt = $db->prepare("SELECT * FROM att_staff WHERE emp_no = ? LIMIT 1");
        $stmt->execute([trim($empNo)]);
        return $stmt->fetch();
    }

    public static function addStaff($empNo, $empName, $designation = 'Security Officer', $phone = '', $actorName = 'Super Admin') {
        $db = att_get_db();
        $cleanNo = strtoupper(trim($empNo));
        $cleanName = trim($empName);
        if (empty($cleanNo) || empty($cleanName)) {
            throw new InvalidArgumentException("Employee Number and Name are required.");
        }

        $stmt = $db->prepare("
            INSERT INTO att_staff (emp_no, emp_name, designation, mobile_phone, is_active)
            VALUES (?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE emp_name = VALUES(emp_name), designation = VALUES(designation), mobile_phone = VALUES(mobile_phone), is_active = 1
        ");
        $stmt->execute([$cleanNo, $cleanName, trim($designation), trim($phone)]);
        $id = (int)$db->lastInsertId();

        att_log_audit('STAFF_UPSERT', 'att_staff', $cleanNo, ['name' => $cleanName, 'designation' => $designation], $actorName);
        return $id;
    }

    public static function deleteStaff($empNo, $actorName = 'Super Admin') {
        $db = att_get_db();
        // Soft delete from staff master so existing sheets are never broken
        $stmt = $db->prepare("UPDATE att_staff SET is_active = 0 WHERE emp_no = ?");
        $stmt->execute([trim($empNo)]);
        att_log_audit('STAFF_DEACTIVATE', 'att_staff', $empNo, ['status' => 'inactive'], $actorName);
        return true;
    }

    /**
     * Check across all other sites for date conflicts (D, N, B)
     */
    public static function checkSiteAssignmentConflicts($empNo, $targetSiteId, $dateFrom, $dateTo) {
        $db = att_get_db();
        $conflicts = [];

        $dStart = new DateTime($dateFrom);
        $dEnd = new DateTime($dateTo);
        $dEnd->modify('+1 day'); // inclusive
        $period = new DatePeriod($dStart, new DateInterval('P1D'), $dEnd);

        foreach ($period as $dt) {
            $year = (int)$dt->format('Y');
            $month = (int)$dt->format('n');
            $day = (int)$dt->format('j');
            $dateStr = $dt->format('Y-m-d');

            // Find cell codes in other sites where staff is recorded with any attendance code
            $stmt = $db->prepare("
                SELECT s.id as sheet_id, st.site_name, st.site_code, c.code
                FROM att_sheets s
                JOIN att_sites st ON st.id = s.site_id
                JOIN att_rows r ON r.sheet_id = s.id AND r.is_deleted = 0
                JOIN att_cells c ON c.row_id = r.id AND c.day_number = ?
                WHERE r.emp_no = ?
                  AND s.year = ?
                  AND s.month = ?
                  AND s.site_id != ?
                  AND c.code != '' AND c.code IS NOT NULL
            ");
            $stmt->execute([$day, $empNo, $year, $month, (int)$targetSiteId]);
            $matches = $stmt->fetchAll();

            foreach ($matches as $m) {
                $conflicts[] = [
                    'date'      => $dateStr,
                    'day'       => $day,
                    'site_id'   => $m['sheet_id'],
                    'site_name' => $m['site_name'],
                    'site_code' => $m['site_code'],
                    'code'      => $m['code']
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Assign staff to target site for date range
     */
    public static function assignToSite($empNo, $siteId, $dateFrom, $dateTo, $defaultCode = 'D', $isOverride = false, $overrideReason = '', $actorName = 'Super Admin') {
        $db = att_get_db();
        $staff = self::getByEmpNo($empNo);
        if (!$staff) {
            throw new RuntimeException("Staff member not found: {$empNo}");
        }

        require_once __DIR__ . '/AttendanceSheet.php';

        // Check company-wide duplicate attendance conflicts
        $conflicts = self::checkSiteAssignmentConflicts($empNo, $siteId, $dateFrom, $dateTo);
        if (!empty($conflicts)) {
            $first = $conflicts[0];
            $siteNames = array_unique(array_column($conflicts, 'site_name'));
            $siteList = count($siteNames) > 1 ? implode(', ', $siteNames) : $siteNames[0];
            $formattedDate = date('j F Y', strtotime($first['date']));
            $msg = "Attendance already exists for {$staff['emp_name']} at {$siteList} on {$formattedDate}. This employee cannot be assigned attendance at another site on the same date.";
            throw new AttendanceDuplicateException($msg, [
                'emp_no' => $empNo,
                'emp_name' => $staff['emp_name'],
                'date' => $first['date'],
                'formatted_date' => $formattedDate,
                'existing_sites' => $siteList,
                'conflicts' => $conflicts
            ]);
        }

        // Record assignment
        $stmtAssign = $db->prepare("
            INSERT INTO att_site_assignments (emp_no, site_id, date_from, date_to, default_code, is_conflict_override, override_reason)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtAssign->execute([
            $empNo,
            (int)$siteId,
            $dateFrom,
            $dateTo,
            $defaultCode,
            $isOverride ? 1 : 0,
            $overrideReason
        ]);

        $dStart = new DateTime($dateFrom);
        $dEnd = new DateTime($dateTo);
        $dEnd->modify('+1 day');
        $period = new DatePeriod($dStart, new DateInterval('P1D'), $dEnd);

        // Group dates by year and month
        $monthsMap = [];
        foreach ($period as $dt) {
            $y = (int)$dt->format('Y');
            $m = (int)$dt->format('n');
            $d = (int)$dt->format('j');
            $key = "{$y}-{$m}";
            if (!isset($monthsMap[$key])) {
                $monthsMap[$key] = ['year' => $y, 'month' => $m, 'days' => []];
            }
            $monthsMap[$key]['days'][] = $d;
        }

        require_once __DIR__ . '/AttendanceSheet.php';

        foreach ($monthsMap as $info) {
            $sheet = AttendanceSheet::getOrCreateSheet((int)$siteId, $info['year'], $info['month'], $actorName);
            if ($sheet['status'] === 'Locked') {
                continue; // Cannot alter locked sheets
            }

            // Ensure row exists for this employee on this sheet
            $rowId = AttendanceSheet::ensureRowExists($sheet['id'], $empNo, $staff['emp_name']);

            // Keep day cells empty for this site so only ID & Name appear at the new site.
            // Previous attendance at other sites remains strictly unchanged.
        }

        att_log_audit('STAFF_ASSIGN_SITE', 'att_site_assignments', $empNo, [
            'site_id' => $siteId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'code' => $defaultCode,
            'override' => $isOverride
        ], $actorName);

        return true;
    }
}
