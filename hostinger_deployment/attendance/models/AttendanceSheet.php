<?php
/**
 * Monthly Attendance Sheet Roster Model
 * Strict calendar calculations, cell management, D/N/Duty bottom row tallies
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Audit.php';

class AttendanceDuplicateException extends RuntimeException {
    protected $conflictData;
    public function __construct($message, array $conflictData = [], $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->conflictData = $conflictData;
    }
    public function getConflictData() {
        return $this->conflictData;
    }
}

class AttendanceSheet {
    public static function getOrCreateSheet($siteId, $year, $month, $actorName = 'Super Admin') {
        $db = att_get_db();
        $siteId = (int)$siteId;
        $year = (int)$year;
        $month = (int)$month;

        $stmt = $db->prepare("SELECT * FROM att_sheets WHERE site_id = ? AND year = ? AND month = ? LIMIT 1");
        $stmt->execute([$siteId, $year, $month]);
        $sheet = $stmt->fetch();
        if ($sheet) {
            return $sheet;
        }

        // Create new Open sheet
        $stmtInsert = $db->prepare("
            INSERT INTO att_sheets (site_id, year, month, status, version)
            VALUES (?, ?, ?, 'Open', 1)
        ");
        $stmtInsert->execute([$siteId, $year, $month]);
        $sheetId = (int)$db->lastInsertId();

        att_log_audit('SHEET_CREATE', 'att_sheets', $sheetId, ['site_id' => $siteId, 'year' => $year, 'month' => $month], $actorName);

        $stmt->execute([$siteId, $year, $month]);
        return $stmt->fetch();
    }

    public static function getSheetFullData($sheetId) {
        $db = att_get_db();
        $sheetId = (int)$sheetId;

        $stmtSheet = $db->prepare("
            SELECT s.*, st.site_name, st.site_code, st.req_day, st.req_night, st.contract_start, st.contract_end 
            FROM att_sheets s
            JOIN att_sites st ON st.id = s.site_id
            WHERE s.id = ? 
            LIMIT 1
        ");
        $stmtSheet->execute([$sheetId]);
        $sheet = $stmtSheet->fetch();
        if (!$sheet) {
            return null;
        }

        $year = (int)$sheet['year'];
        $month = (int)$sheet['month'];
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        // Fetch non-deleted rows
        $stmtRows = $db->prepare("
            SELECT * FROM att_rows 
            WHERE sheet_id = ? AND is_deleted = 0 
            ORDER BY sort_order ASC, id ASC
        ");
        $stmtRows->execute([$sheetId]);
        $rows = $stmtRows->fetchAll();

        // Fetch all cells for this sheet
        $stmtCells = $db->prepare("
            SELECT c.row_id, c.day_number, c.code 
            FROM att_cells c
            JOIN att_rows r ON r.id = c.row_id
            WHERE r.sheet_id = ? AND r.is_deleted = 0
        ");
        $stmtCells->execute([$sheetId]);
        $allCells = $stmtCells->fetchAll();

        $cellsMap = [];
        foreach ($allCells as $c) {
            $cellsMap[$c['row_id']][$c['day_number']] = $c['code'];
        }

        // Fetch additional deployments for this sheet
        $stmtAdd = $db->prepare("SELECT * FROM att_additional_deployments WHERE sheet_id = ? ORDER BY day_number ASC");
        $stmtAdd->execute([$sheetId]);
        $additionalRows = $stmtAdd->fetchAll();
        $addMap = [];
        foreach ($additionalRows as $ar) {
            $addMap[(int)$ar['day_number']] = [
                'add_day' => (int)$ar['add_day'],
                'add_night' => (int)$ar['add_night'],
                'notes' => $ar['notes'] ?? ''
            ];
        }

        // Compute bottom daily tallies
        // DAY SHIFT (D), NIGHT SHIFT (N), TOTAL ON DUTY (D + N), BASIC (B)
        $dayShiftCounts = array_fill(1, $daysInMonth, 0);
        $nightShiftCounts = array_fill(1, $daysInMonth, 0);
        $basicShiftCounts = array_fill(1, $daysInMonth, 0);
        $totalDutyCounts = array_fill(1, $daysInMonth, 0);

        foreach ($rows as &$r) {
            $rowId = $r['id'];
            $r['days'] = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $code = $cellsMap[$rowId][$d] ?? '';
                $r['days'][$d] = $code;

                if ($code === 'D') {
                    $dayShiftCounts[$d]++;
                    $totalDutyCounts[$d]++;
                } elseif ($code === 'N') {
                    $nightShiftCounts[$d]++;
                    $totalDutyCounts[$d]++;
                } elseif ($code === 'B') {
                    $basicShiftCounts[$d]++;
                }
            }
        }
        unset($r);

        // Daily requirements and deployment comparison
        $siteReqDay = isset($sheet['req_day']) ? (int)$sheet['req_day'] : 1;
        $siteReqNight = isset($sheet['req_night']) ? (int)$sheet['req_night'] : 1;
        $contractStart = !empty($sheet['contract_start']) ? $sheet['contract_start'] : null;
        $contractEnd = !empty($sheet['contract_end']) ? $sheet['contract_end'] : null;

        $dayRequirements = [];
        $nightRequirements = [];
        $totalRequirements = [];
        $dayDeployStatuses = [];
        $nightDeployStatuses = [];
        $totalDeployStatuses = [];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dayDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $inContract = true;
            if (!empty($contractStart) && $dayDate < $contractStart) {
                $inContract = false;
            }
            if (!empty($contractEnd) && $dayDate > $contractEnd) {
                $inContract = false;
            }

            $baseD = $inContract ? $siteReqDay : 0;
            $baseN = $inContract ? $siteReqNight : 0;
            $extraD = (int)($addMap[$d]['add_day'] ?? 0);
            $extraN = (int)($addMap[$d]['add_night'] ?? 0);

            $reqD = max(0, $baseD + $extraD);
            $reqN = max(0, $baseN + $extraN);
            $reqTot = $reqD + $reqN;

            $dayRequirements[$d] = $reqD;
            $nightRequirements[$d] = $reqN;
            $totalRequirements[$d] = $reqTot;

            // Status comparison: 'ok' (green), 'less' (red), 'over' (yellow)
            $actualD = $dayShiftCounts[$d];
            if ($reqD == 0) {
                $dayDeployStatuses[$d] = ($actualD == 0) ? 'neutral' : 'over';
            } else {
                if ($actualD == $reqD) $dayDeployStatuses[$d] = 'ok';
                elseif ($actualD < $reqD) $dayDeployStatuses[$d] = 'less';
                else $dayDeployStatuses[$d] = 'over';
            }

            $actualN = $nightShiftCounts[$d];
            if ($reqN == 0) {
                $nightDeployStatuses[$d] = ($actualN == 0) ? 'neutral' : 'over';
            } else {
                if ($actualN == $reqN) $nightDeployStatuses[$d] = 'ok';
                elseif ($actualN < $reqN) $nightDeployStatuses[$d] = 'less';
                else $nightDeployStatuses[$d] = 'over';
            }

            $actualTot = $totalDutyCounts[$d];
            if ($reqTot == 0) {
                $totalDeployStatuses[$d] = ($actualTot == 0) ? 'neutral' : 'over';
            } else {
                if ($actualTot == $reqTot) $totalDeployStatuses[$d] = 'ok';
                elseif ($actualTot < $reqTot) $totalDeployStatuses[$d] = 'less';
                else $totalDeployStatuses[$d] = 'over';
            }
        }

        return [
            'sheet'                 => $sheet,
            'daysInMonth'           => $daysInMonth,
            'rows'                  => $rows,
            'dayShiftCounts'        => $dayShiftCounts,
            'nightShiftCounts'      => $nightShiftCounts,
            'basicShiftCounts'      => $basicShiftCounts,
            'totalDutyCounts'       => $totalDutyCounts,
            'monthTotalDuty'        => array_sum($totalDutyCounts),
            'additionalDeployments' => $additionalRows,
            'additionalMap'         => $addMap,
            'dayRequirements'       => $dayRequirements,
            'nightRequirements'     => $nightRequirements,
            'totalRequirements'     => $totalRequirements,
            'dayDeployStatuses'     => $dayDeployStatuses,
            'nightDeployStatuses'   => $nightDeployStatuses,
            'totalDeployStatuses'   => $totalDeployStatuses
        ];
    }

    public static function ensureRowExists($sheetId, $empNo, $empName) {
        $db = att_get_db();
        $sheetId = (int)$sheetId;
        $cleanNo = strtoupper(trim($empNo));
        $cleanName = trim($empName);

        $stmt = $db->prepare("SELECT id FROM att_rows WHERE sheet_id = ? AND emp_no = ? LIMIT 1");
        $stmt->execute([$sheetId, $cleanNo]);
        $existing = $stmt->fetch();
        if ($existing) {
            // Un-delete if soft-deleted
            $stmtUndel = $db->prepare("UPDATE att_rows SET is_deleted = 0, emp_name_snapshot = ? WHERE id = ?");
            $stmtUndel->execute([$cleanName, $existing['id']]);
            return (int)$existing['id'];
        }

        // Get max sort_order
        $stmtMax = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM att_rows WHERE sheet_id = ?");
        $stmtMax->execute([$sheetId]);
        $nextSort = (int)$stmtMax->fetchColumn();

        $stmtIns = $db->prepare("
            INSERT INTO att_rows (sheet_id, emp_no, emp_name_snapshot, sort_order, is_deleted)
            VALUES (?, ?, ?, ?, 0)
        ");
        $stmtIns->execute([$sheetId, $cleanNo, $cleanName, $nextSort]);
        return (int)$db->lastInsertId();
    }

    public static function ensureCentralAttendanceTableExists() {
        $db = att_get_db();
        $sql = "
            CREATE TABLE IF NOT EXISTS `att_central_attendance` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `emp_no` VARCHAR(60) NOT NULL,
                `attendance_date` DATE NOT NULL,
                `site_id` INT UNSIGNED NOT NULL,
                `sheet_id` INT UNSIGNED NOT NULL,
                `row_id` INT UNSIGNED NOT NULL,
                `cell_id` INT UNSIGNED NOT NULL,
                `code` VARCHAR(5) NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_central_emp_date` (`emp_no`, `attendance_date`),
                INDEX `idx_central_cell` (`cell_id`),
                INDEX `idx_central_site_date` (`site_id`, `attendance_date`),
                INDEX `idx_central_sheet` (`sheet_id`),
                INDEX `idx_central_row` (`row_id`),
                CONSTRAINT `fk_central_cell` FOREIGN KEY (`cell_id`) REFERENCES `att_cells` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_central_site` FOREIGN KEY (`site_id`) REFERENCES `att_sites` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $db->exec($sql);

        // Populate central attendance table with existing records if table is currently empty
        try {
            $stmtCount = $db->query("SELECT COUNT(*) FROM att_central_attendance");
            if ($stmtCount && (int)$stmtCount->fetchColumn() === 0) {
                $db->exec("
                    INSERT IGNORE INTO att_central_attendance (emp_no, attendance_date, site_id, sheet_id, row_id, cell_id, code)
                    SELECT r.emp_no, 
                           STR_TO_DATE(CONCAT(s.year, '-', LPAD(s.month, 2, '00'), '-', LPAD(c.day_number, 2, '00')), '%Y-%m-%d') as attendance_date,
                           s.site_id, s.id as sheet_id, r.id as row_id, c.id as cell_id, c.code
                    FROM att_cells c
                    JOIN att_rows r ON r.id = c.row_id AND r.is_deleted = 0
                    JOIN att_sheets s ON s.id = r.sheet_id
                    WHERE c.code != '' AND c.code IS NOT NULL
                ");
            }
        } catch (Throwable $ignore) {}
    }

    public static function checkAttendanceConflict($empNo, $year, $month, $dayNumber, $excludeRowId = 0, $excludeCellId = 0) {
        $db = att_get_db();
        $empNo = strtoupper(trim($empNo));
        $year = (int)$year;
        $month = (int)$month;
        $dayNumber = (int)$dayNumber;
        $excludeRowId = (int)$excludeRowId;
        $excludeCellId = (int)$excludeCellId;

        $attendanceDate = sprintf('%04d-%02d-%02d', $year, $month, $dayNumber);
        $formattedDate = date('j F Y', strtotime($attendanceDate));

        // 1. Check in active sheets, rows, and cells
        $sql = "
            SELECT c.id as cell_id, c.code, r.id as row_id, r.emp_no, r.emp_name_snapshot,
                   s.id as sheet_id, s.site_id, st.site_name, st.site_code
            FROM att_cells c
            JOIN att_rows r ON r.id = c.row_id AND r.is_deleted = 0
            JOIN att_sheets s ON s.id = r.sheet_id
            JOIN att_sites st ON st.id = s.site_id
            WHERE r.emp_no = ?
              AND s.year = ?
              AND s.month = ?
              AND c.day_number = ?
              AND c.code != ''
              AND c.code IS NOT NULL
        ";
        $params = [$empNo, $year, $month, $dayNumber];

        if ($excludeCellId > 0) {
            $sql .= " AND c.id != ?";
            $params[] = $excludeCellId;
        }
        if ($excludeRowId > 0) {
            $sql .= " AND r.id != ?";
            $params[] = $excludeRowId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Also check central attendance registry if available
        try {
            $sqlCa = "
                SELECT ca.cell_id, ca.code, ca.row_id, ca.emp_no, ca.site_id,
                       st.site_name, st.site_code
                FROM att_central_attendance ca
                JOIN att_sites st ON st.id = ca.site_id
                WHERE ca.emp_no = ?
                  AND ca.attendance_date = ?
            ";
            $paramsCa = [$empNo, $attendanceDate];
            if ($excludeCellId > 0) {
                $sqlCa .= " AND ca.cell_id != ?";
                $paramsCa[] = $excludeCellId;
            }
            if ($excludeRowId > 0) {
                $sqlCa .= " AND ca.row_id != ?";
                $paramsCa[] = $excludeRowId;
            }
            $stmtCa = $db->prepare($sqlCa);
            $stmtCa->execute($paramsCa);
            $caRows = $stmtCa->fetchAll(PDO::FETCH_ASSOC);
            foreach ($caRows as $ca) {
                $found = false;
                foreach ($conflicts as $cf) {
                    if ((int)$cf['cell_id'] === (int)$ca['cell_id']) { $found = true; break; }
                }
                if (!$found) {
                    $conflicts[] = $ca;
                }
            }
        } catch (Throwable $ignore) {}

        if (empty($conflicts)) {
            return null;
        }

        // Determine employee name
        $empName = !empty($conflicts[0]['emp_name_snapshot']) ? $conflicts[0]['emp_name_snapshot'] : '';
        if (empty($empName)) {
            $stmtName = $db->prepare("SELECT emp_name FROM att_staff WHERE emp_no = ? LIMIT 1");
            $stmtName->execute([$empNo]);
            $empName = $stmtName->fetchColumn() ?: $empNo;
        }

        // Distinct conflicting site names
        $siteNames = [];
        foreach ($conflicts as $c) {
            $name = !empty($c['site_name']) ? $c['site_name'] : ('Site #' . ($c['site_id'] ?? ''));
            if (!in_array($name, $siteNames, true)) {
                $siteNames[] = $name;
            }
        }

        if (count($siteNames) > 1) {
            $siteList = implode(', ', $siteNames);
            $warning = "Attendance already exists for {$empName} at {$siteList} on {$formattedDate}. This employee cannot be assigned attendance at another site on the same date. Please resolve the duplicates.";
        } else {
            $siteList = $siteNames[0] ?? 'another site';
            $warning = "Attendance already exists for {$empName} at {$siteList} on {$formattedDate}. This employee cannot be assigned attendance at another site on the same date.";
        }

        return [
            'has_conflict'   => true,
            'emp_no'         => $empNo,
            'emp_name'       => $empName,
            'date'           => $attendanceDate,
            'formatted_date' => $formattedDate,
            'existing_sites' => $siteList,
            'conflicts'      => $conflicts,
            'message'        => $warning
        ];
    }

    public static function setCellCodeIfEmpty($rowId, $dayNumber, $code) {
        $db = att_get_db();
        $code = strtoupper(trim($code));
        if (!in_array($code, ATT_VALID_CODES) || empty($code)) return false;

        $stmtCheck = $db->prepare("SELECT id, code FROM att_cells WHERE row_id = ? AND day_number = ? LIMIT 1");
        $stmtCheck->execute([(int)$rowId, (int)$dayNumber]);
        $rowCell = $stmtCheck->fetch();
        if ($rowCell && !empty($rowCell['code'])) {
            return false; // Already has code, preserve existing
        }

        // Fetch row and sheet metadata
        $stmtMeta = $db->prepare("
            SELECT r.emp_no, s.id as sheet_id, s.site_id, s.year, s.month 
            FROM att_rows r 
            JOIN att_sheets s ON s.id = r.sheet_id 
            WHERE r.id = ? 
            LIMIT 1
        ");
        $stmtMeta->execute([(int)$rowId]);
        $meta = $stmtMeta->fetch();
        if (!$meta) return false;

        // Check company-wide conflict
        $conflict = self::checkAttendanceConflict($meta['emp_no'], $meta['year'], $meta['month'], $dayNumber, (int)$rowId);
        if ($conflict && !empty($conflict['has_conflict'])) {
            return false; // Disallow duplicate attendance
        }

        self::ensureCentralAttendanceTableExists();

        $stmt = $db->prepare("
            INSERT INTO att_cells (row_id, day_number, code)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE code = IF(code = '' OR code IS NULL, VALUES(code), code)
        ");
        $stmt->execute([(int)$rowId, (int)$dayNumber, $code]);
        $cellId = (int)$db->lastInsertId();
        if (!$cellId && $rowCell) {
            $cellId = (int)$rowCell['id'];
        }

        if ($cellId > 0) {
            $attDate = sprintf('%04d-%02d-%02d', $meta['year'], $meta['month'], $dayNumber);
            try {
                $stmtCa = $db->prepare("
                    INSERT INTO att_central_attendance (emp_no, attendance_date, site_id, sheet_id, row_id, cell_id, code)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE site_id = VALUES(site_id), sheet_id = VALUES(sheet_id), row_id = VALUES(row_id), cell_id = VALUES(cell_id), code = VALUES(code)
                ");
                $stmtCa->execute([$meta['emp_no'], $attDate, (int)$meta['site_id'], (int)$meta['sheet_id'], (int)$rowId, $cellId, $code]);
            } catch (Throwable $e) {}
        }

        return true;
    }

    public static function updateCellCode($sheetId, $rowId, $dayNumber, $code, $expectedVersion, $actorName = 'Super Admin') {
        $db = att_get_db();
        $code = strtoupper(trim($code));
        if ($code === 'O') $code = 'OFF';
        if (!empty($code) && !in_array($code, ATT_VALID_CODES)) {
            throw new InvalidArgumentException("Invalid attendance code: {$code}. Valid: D, N, OFF, S, B");
        }

        self::ensureCentralAttendanceTableExists();

        $inTx = $db->inTransaction();
        if (!$inTx) $db->beginTransaction();

        try {
            // Check lock & version concurrency
            $stmtSheet = $db->prepare("SELECT id, site_id, year, month, status, version FROM att_sheets WHERE id = ? LIMIT 1 FOR UPDATE");
            $stmtSheet->execute([(int)$sheetId]);
            $sheet = $stmtSheet->fetch();
            if (!$sheet) {
                throw new RuntimeException("Sheet not found.");
            }
            if ($sheet['status'] === 'Locked') {
                throw new RuntimeException("This attendance sheet is Locked and cannot be modified.");
            }

            // Fetch row info with row lock
            $stmtRow = $db->prepare("SELECT id, emp_no, emp_name_snapshot FROM att_rows WHERE id = ? AND sheet_id = ? LIMIT 1 FOR UPDATE");
            $stmtRow->execute([(int)$rowId, (int)$sheetId]);
            $row = $stmtRow->fetch();
            if (!$row) {
                throw new RuntimeException("Roster row not found.");
            }
            $empNo = strtoupper(trim($row['emp_no']));
            $empName = trim($row['emp_name_snapshot']);
            $siteId = (int)$sheet['site_id'];
            $year = (int)$sheet['year'];
            $month = (int)$sheet['month'];
            $dayNumber = (int)$dayNumber;
            $attendanceDate = sprintf('%04d-%02d-%02d', $year, $month, $dayNumber);

            // Fetch existing cell for this row & day
            $stmtExistCell = $db->prepare("SELECT id, code FROM att_cells WHERE row_id = ? AND day_number = ? LIMIT 1 FOR UPDATE");
            $stmtExistCell->execute([(int)$rowId, $dayNumber]);
            $existingCell = $stmtExistCell->fetch();
            $existingCellId = $existingCell ? (int)$existingCell['id'] : 0;

            if (empty($code)) {
                // User is clearing attendance for this day
                if ($existingCellId > 0) {
                    $stmtDelCell = $db->prepare("DELETE FROM att_cells WHERE id = ?");
                    $stmtDelCell->execute([$existingCellId]);

                    $stmtDelCentral = $db->prepare("DELETE FROM att_central_attendance WHERE cell_id = ?");
                    $stmtDelCentral->execute([$existingCellId]);
                }
            } else {
                // User is recording an attendance code (D, N, B, S, OFF)
                // Check if employee already has attendance on this calendar date across ANY other row or site
                $conflict = self::checkAttendanceConflict($empNo, $year, $month, $dayNumber, (int)$rowId, $existingCellId);
                if ($conflict && !empty($conflict['has_conflict'])) {
                    if (!$inTx && $db->inTransaction()) $db->rollBack();
                    throw new AttendanceDuplicateException($conflict['message'], $conflict);
                }

                // Insert or update cell in att_cells
                $stmtCell = $db->prepare("
                    INSERT INTO att_cells (row_id, day_number, code)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE code = VALUES(code)
                ");
                $stmtCell->execute([(int)$rowId, $dayNumber, $code]);

                if (!$existingCellId) {
                    $existingCellId = (int)$db->lastInsertId();
                }

                // Register into att_central_attendance table
                // Database-level UNIQUE constraint uk_central_emp_date will guarantee no race conditions
                try {
                    $stmtCentral = $db->prepare("
                        INSERT INTO att_central_attendance (emp_no, attendance_date, site_id, sheet_id, row_id, cell_id, code)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                            site_id = VALUES(site_id),
                            sheet_id = VALUES(sheet_id),
                            row_id = VALUES(row_id),
                            cell_id = VALUES(cell_id),
                            code = VALUES(code)
                    ");
                    $stmtCentral->execute([$empNo, $attendanceDate, $siteId, (int)$sheetId, (int)$rowId, $existingCellId, $code]);
                } catch (PDOException $pe) {
                    if ($pe->getCode() == 23000 || strpos($pe->getMessage(), '1062') !== false || strpos($pe->getMessage(), 'uk_central_emp_date') !== false) {
                        if (!$inTx && $db->inTransaction()) $db->rollBack();
                        $conflictRecheck = self::checkAttendanceConflict($empNo, $year, $month, $dayNumber, (int)$rowId, $existingCellId);
                        $msg = $conflictRecheck['message'] ?? "Attendance already exists for {$empName} on {$attendanceDate}. This employee cannot be assigned attendance at another site on the same date.";
                        throw new AttendanceDuplicateException($msg, $conflictRecheck ?: ['emp_no' => $empNo, 'date' => $attendanceDate]);
                    }
                    throw $pe;
                }
            }

            // Increment sheet version
            $stmtVer = $db->prepare("UPDATE att_sheets SET version = version + 1 WHERE id = ?");
            $stmtVer->execute([(int)$sheetId]);

            if (!$inTx) $db->commit();

            $newVer = (int)$sheet['version'] + 1;
            return ['success' => true, 'version' => $newVer];
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function removeRow($sheetId, $rowId, $actorName = 'Super Admin') {
        $db = att_get_db();
        $sheetId = (int)$sheetId;
        $rowId = (int)$rowId;

        $inTx = $db->inTransaction();
        if (!$inTx) $db->beginTransaction();

        try {
            // Check if locked
            $stmtSheet = $db->prepare("SELECT status FROM att_sheets WHERE id = ? LIMIT 1 FOR UPDATE");
            $stmtSheet->execute([$sheetId]);
            if ($stmtSheet->fetchColumn() === 'Locked') {
                throw new RuntimeException("Cannot alter locked sheet.");
            }

            // Soft delete row so Undo is available and history is preserved
            $stmt = $db->prepare("UPDATE att_rows SET is_deleted = 1 WHERE id = ? AND sheet_id = ?");
            $stmt->execute([$rowId, $sheetId]);

            // Release central attendance slots for soft-deleted row
            try {
                $db->prepare("DELETE FROM att_central_attendance WHERE row_id = ?")->execute([$rowId]);
            } catch (Throwable $e) {}

            att_log_audit('SHEET_ROW_REMOVE', 'att_rows', $rowId, ['sheet_id' => $sheetId], $actorName);

            if (!$inTx) $db->commit();
            return true;
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function replaceStaffInRow($sheetId, $rowId, $newEmpNo, $newEmpName, $clearCodes = false, $actorName = 'Super Admin') {
        $db = att_get_db();
        $sheetId = (int)$sheetId;
        $rowId = (int)$rowId;
        $newEmpNo = strtoupper(trim($newEmpNo));
        $newEmpName = trim($newEmpName);

        self::ensureCentralAttendanceTableExists();

        $inTx = $db->inTransaction();
        if (!$inTx) $db->beginTransaction();

        try {
            $stmtSheet = $db->prepare("SELECT id, site_id, year, month, status FROM att_sheets WHERE id = ? LIMIT 1 FOR UPDATE");
            $stmtSheet->execute([$sheetId]);
            $sheet = $stmtSheet->fetch();
            if (!$sheet) {
                throw new RuntimeException("Sheet not found.");
            }
            if ($sheet['status'] === 'Locked') {
                throw new RuntimeException("Cannot alter locked sheet.");
            }

            // If not clearing codes, verify that new staff has no conflicts on any of the active days in this row
            if (!$clearCodes) {
                $stmtCells = $db->prepare("SELECT id, day_number, code FROM att_cells WHERE row_id = ? AND code != '' AND code IS NOT NULL");
                $stmtCells->execute([$rowId]);
                $existingCells = $stmtCells->fetchAll();

                foreach ($existingCells as $ec) {
                    $conflict = self::checkAttendanceConflict($newEmpNo, $sheet['year'], $sheet['month'], $ec['day_number'], $rowId, (int)$ec['id']);
                    if ($conflict && !empty($conflict['has_conflict'])) {
                        if (!$inTx && $db->inTransaction()) $db->rollBack();
                        throw new AttendanceDuplicateException($conflict['message'], $conflict);
                    }
                }
            }

            // Update row
            $stmtRow = $db->prepare("UPDATE att_rows SET emp_no = ?, emp_name_snapshot = ? WHERE id = ? AND sheet_id = ?");
            $stmtRow->execute([$newEmpNo, $newEmpName, $rowId, $sheetId]);

            if ($clearCodes) {
                $stmtClr = $db->prepare("DELETE FROM att_cells WHERE row_id = ?");
                $stmtClr->execute([$rowId]);
                $db->prepare("DELETE FROM att_central_attendance WHERE row_id = ?")->execute([$rowId]);
            } else {
                $db->prepare("UPDATE att_central_attendance SET emp_no = ? WHERE row_id = ?")->execute([$newEmpNo, $rowId]);
            }

            att_log_audit('SHEET_ROW_REPLACE', 'att_rows', $rowId, [
                'sheet_id' => $sheetId,
                'new_emp_no' => $newEmpNo,
                'clear_codes' => $clearCodes
            ], $actorName);

            if (!$inTx) $db->commit();
            return true;
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function copyStaffFromPreviousMonth($siteId, $year, $month, $actorName = 'Super Admin') {
        $db = att_get_db();
        $siteId = (int)$siteId;
        $year = (int)$year;
        $month = (int)$month;

        // Calculate previous month
        $prevMonth = $month - 1;
        $prevYear = $year;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }

        $inTx = $db->inTransaction();
        if (!$inTx) $db->beginTransaction();

        try {
            // Current target sheet
            $targetSheet = self::getOrCreateSheet($siteId, $year, $month, $actorName);
            if ($targetSheet['status'] === 'Locked') {
                throw new RuntimeException("Target sheet is Locked.");
            }

            // Fetch previous sheet
            $stmtPrev = $db->prepare("SELECT id FROM att_sheets WHERE site_id = ? AND year = ? AND month = ? LIMIT 1");
            $stmtPrev->execute([$siteId, $prevYear, $prevMonth]);
            $prevSheetId = $stmtPrev->fetchColumn();
            if (!$prevSheetId) {
                if (!$inTx) $db->commit();
                return ['copied' => 0, 'message' => "No previous sheet found for {$prevMonth}/{$prevYear}."];
            }

            // Fetch staff from previous month
            $stmtRows = $db->prepare("SELECT emp_no, emp_name_snapshot, sort_order FROM att_rows WHERE sheet_id = ? AND is_deleted = 0 ORDER BY sort_order ASC");
            $stmtRows->execute([$prevSheetId]);
            $prevRows = $stmtRows->fetchAll();

            $copiedCount = 0;
            foreach ($prevRows as $pr) {
                // Check if already in target sheet
                $stmtCheck = $db->prepare("SELECT id FROM att_rows WHERE sheet_id = ? AND emp_no = ? LIMIT 1");
                $stmtCheck->execute([$targetSheet['id'], $pr['emp_no']]);
                $exists = $stmtCheck->fetch();
                if ($exists) {
                    // Ensure non-deleted
                    $db->prepare("UPDATE att_rows SET is_deleted = 0 WHERE id = ?")->execute([$exists['id']]);
                } else {
                    $stmtIns = $db->prepare("
                        INSERT INTO att_rows (sheet_id, emp_no, emp_name_snapshot, sort_order, is_deleted)
                        VALUES (?, ?, ?, ?, 0)
                    ");
                    $stmtIns->execute([$targetSheet['id'], $pr['emp_no'], $pr['emp_name_snapshot'], $pr['sort_order']]);
                    $copiedCount++;
                }
            }

            att_log_audit('SHEET_COPY_STAFF', 'att_sheets', $targetSheet['id'], [
                'from_period' => "{$prevYear}-{$prevMonth}",
                'copied_count' => $copiedCount
            ], $actorName);

            if (!$inTx) $db->commit();
            return ['copied' => $copiedCount, 'message' => "Copied {$copiedCount} staff from {$prevMonth}/{$prevYear} with empty codes."];
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function toggleLock($sheetId, $lock, $reason = '', $actorName = 'Super Admin') {
        $db = att_get_db();
        $sheetId = (int)$sheetId;
        $status = $lock ? 'Locked' : 'Open';
        $lockedAt = $lock ? date('Y-m-d H:i:s') : null;
        $lockedBy = $lock ? $actorName : null;

        $inTx = $db->inTransaction();
        if (!$inTx) $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                UPDATE att_sheets 
                SET status = ?, locked_by = ?, lock_reason = ?, locked_at = ?, version = version + 1
                WHERE id = ?
            ");
            $stmt->execute([$status, $lockedBy, trim($reason), $lockedAt, $sheetId]);

            att_log_audit($lock ? 'SHEET_LOCK' : 'SHEET_UNLOCK', 'att_sheets', $sheetId, ['reason' => $reason], $actorName);

            if (!$inTx) $db->commit();
            return true;
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function saveAdditionalDeployment($sheetId, $dayNumber, $addDay, $addNight, $notes = '', $actorName = 'Super Admin') {
        $db = att_get_db();
        $sheetId = (int)$sheetId;
        $addDay = (int)$addDay;
        $addNight = (int)$addNight;
        $notes = trim($notes);

        $stmtSheet = $db->prepare("SELECT year, month FROM att_sheets WHERE id = ? LIMIT 1");
        $stmtSheet->execute([$sheetId]);
        $sheet = $stmtSheet->fetch();
        if (!$sheet) {
            throw new InvalidArgumentException("Sheet not found.");
        }
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int)$sheet['month'], (int)$sheet['year']);

        $inTx = $db->inTransaction();
        if (!$inTx) $db->beginTransaction();

        try {
            $stmtUpsert = $db->prepare("
                INSERT INTO att_additional_deployments (sheet_id, day_number, add_day, add_night, notes)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE add_day = VALUES(add_day), add_night = VALUES(add_night), notes = VALUES(notes)
            ");

            if ($dayNumber === 'all' || (string)$dayNumber === '0') {
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $stmtUpsert->execute([$sheetId, $d, $addDay, $addNight, $notes]);
                }
            } else {
                $d = max(1, min($daysInMonth, (int)$dayNumber));
                $stmtUpsert->execute([$sheetId, $d, $addDay, $addNight, $notes]);
            }

            att_log_audit('SHEET_ADD_DEPLOYMENT', 'att_additional_deployments', $sheetId, [
                'day' => $dayNumber,
                'add_day' => $addDay,
                'add_night' => $addNight,
                'notes' => $notes
            ], $actorName);

            if (!$inTx) $db->commit();
            return true;
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function saveAdditionalDeploymentRange($sheetId, $fromDate, $endDate, $addDay, $addNight, $notes = '', $actorName = 'Super Admin') {
        $db = att_get_db();
        $sheetId = (int)$sheetId;
        $addDay = (int)$addDay;
        $addNight = (int)$addNight;
        $notes = trim($notes);

        $stmtSheet = $db->prepare("SELECT site_id, year, month FROM att_sheets WHERE id = ? LIMIT 1");
        $stmtSheet->execute([$sheetId]);
        $sheet = $stmtSheet->fetch();
        if (!$sheet) {
            throw new InvalidArgumentException("Sheet not found.");
        }

        $siteId = (int)$sheet['site_id'];
        $fromTime = !empty($fromDate) ? strtotime($fromDate) : null;
        $endTime = !empty($endDate) ? strtotime($endDate) : null;
        if (!$fromTime && !$endTime) {
            return self::saveAdditionalDeployment($sheetId, 'all', $addDay, $addNight, $notes, $actorName);
        }
        if (!$fromTime) $fromTime = $endTime;
        if (!$endTime) $endTime = $fromTime;
        if ($fromTime > $endTime) {
            $tmp = $fromTime;
            $fromTime = $endTime;
            $endTime = $tmp;
        }

        $inTx = $db->inTransaction();
        if (!$inTx) $db->beginTransaction();

        try {
            $stmtUpsert = $db->prepare("
                INSERT INTO att_additional_deployments (sheet_id, day_number, add_day, add_night, notes)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE add_day = VALUES(add_day), add_night = VALUES(add_night), notes = VALUES(notes)
            ");

            $curr = $fromTime;
            while ($curr <= $endTime) {
                $y = (int)date('Y', $curr);
                $m = (int)date('n', $curr);
                $d = (int)date('j', $curr);

                if ($y === (int)$sheet['year'] && $m === (int)$sheet['month']) {
                    $targetSheetId = $sheetId;
                } else {
                    $targetSheet = self::getOrCreateSheet($siteId, $y, $m, $actorName);
                    $targetSheetId = (int)$targetSheet['id'];
                }

                $stmtUpsert->execute([$targetSheetId, $d, $addDay, $addNight, $notes]);
                $curr = strtotime('+1 day', $curr);
            }

            att_log_audit('SHEET_ADD_DEPLOYMENT_RANGE', 'att_additional_deployments', $sheetId, [
                'from_date' => date('Y-m-d', $fromTime),
                'end_date' => date('Y-m-d', $endTime),
                'add_day' => $addDay,
                'add_night' => $addNight,
                'notes' => $notes
            ], $actorName);

            if (!$inTx) $db->commit();
            return true;
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function deleteAdditionalDeploymentById($deploymentId, $actorName = 'Super Admin') {
        $db = att_get_db();
        $deploymentId = (int)$deploymentId;
        $stmt = $db->prepare("DELETE FROM att_additional_deployments WHERE id = ?");
        $stmt->execute([$deploymentId]);
        att_log_audit('SHEET_DEL_DEPLOYMENT_ID', 'att_additional_deployments', $deploymentId, [], $actorName);
        return true;
    }

    public static function deleteAdditionalDeployment($sheetId, $dayNumber, $actorName = 'Super Admin') {
        $db = att_get_db();
        $sheetId = (int)$sheetId;

        $inTx = $db->inTransaction();
        if (!$inTx) $db->beginTransaction();

        try {
            if ($dayNumber === 'all' || (string)$dayNumber === '0') {
                $stmt = $db->prepare("DELETE FROM att_additional_deployments WHERE sheet_id = ?");
                $stmt->execute([$sheetId]);
            } else {
                $stmt = $db->prepare("DELETE FROM att_additional_deployments WHERE sheet_id = ? AND day_number = ?");
                $stmt->execute([$sheetId, (int)$dayNumber]);
            }

            att_log_audit('SHEET_DEL_DEPLOYMENT', 'att_additional_deployments', $sheetId, ['day' => $dayNumber], $actorName);

            if (!$inTx) $db->commit();
            return true;
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function updateSiteRequirements($siteId, $reqDay, $reqNight, $contractStart = null, $contractEnd = null, $actorName = 'Super Admin') {
        $db = att_get_db();
        $siteId = (int)$siteId;
        $reqDay = max(0, (int)$reqDay);
        $reqNight = max(0, (int)$reqNight);
        $contractStart = !empty($contractStart) ? trim($contractStart) : null;
        $contractEnd = !empty($contractEnd) ? trim($contractEnd) : null;

        $stmt = $db->prepare("UPDATE att_sites SET req_day = ?, req_night = ?, contract_start = ?, contract_end = ? WHERE id = ?");
        $stmt->execute([$reqDay, $reqNight, $contractStart, $contractEnd, $siteId]);

        att_log_audit('SITE_REQUIREMENTS_UPDATE', 'att_sites', $siteId, [
            'req_day' => $reqDay,
            'req_night' => $reqNight,
            'contract_start' => $contractStart,
            'contract_end' => $contractEnd
        ], $actorName);
        return true;
    }
}
