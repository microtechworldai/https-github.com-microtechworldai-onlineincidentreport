<?php
// Attendance & Payroll Module - Dedicated Payroll Consolidation Engine
// File: hostinger_deployment/attendance/models/PayrollEngine.php

if (!defined('ATT_ACCESS')) {
    die('Direct access not permitted.');
}

class PayrollEngine {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    private function getPeriodKey($startDate, $endDate) {
        return $startDate . '_' . $endDate;
    }

    /**
     * Compute full daily date-range payroll consolidation
     * Every date between $startDate and $endDate appears as a 38px column with Day number and Day of week
     */
    public function getConsolidatedPayroll($startDate, $endDate, $siteId = null) {
        $dStart = new DateTime($startDate);
        $dEnd = new DateTime($endDate);
        
        if ($dStart > $dEnd) {
            throw new InvalidArgumentException("Start date cannot be after end date.");
        }
        
        $interval = $dStart->diff($dEnd)->days + 1;
        if ($interval > 62) {
            throw new InvalidArgumentException("Date range exceeds maximum limit of 62 days.");
        }

        // Build list of all dates in the range
        $datesList = [];
        $dCurr = clone $dStart;
        while ($dCurr <= $dEnd) {
            $datesList[] = [
                'date_str' => $dCurr->format('Y-m-d'),
                'day'      => (int)$dCurr->format('j'),
                'weekday'  => strtoupper(substr($dCurr->format('D'), 0, 3)),
                'year'     => (int)$dCurr->format('Y'),
                'month'    => (int)$dCurr->format('n')
            ];
            $dCurr->modify('+1 day');
        }

        $periodKey = $this->getPeriodKey($startDate, $endDate);
        $customCols = $this->getCustomColumns();

        // 1. Fetch all cells across all sheets in this date range
        $sql = "
            SELECT 
                r.emp_no,
                r.emp_name_snapshot,
                st.id AS staff_id,
                st.emp_name,
                s.site_name,
                s.id AS site_id,
                sh.year,
                sh.month,
                c.day_number,
                c.code
            FROM att_cells c
            JOIN att_rows r ON c.row_id = r.id
            JOIN att_sheets sh ON r.sheet_id = sh.id
            LEFT JOIN att_staff st ON r.emp_no = st.emp_no
            JOIN att_sites s ON sh.site_id = s.id
            WHERE r.is_deleted = 0
        ";
        $params = [];

        if ($siteId) {
            $sql .= " AND s.id = ?";
            $params[] = $siteId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $allCells = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Map cells by emp_no and Y-m-d
        $staffMap = [];
        $staffDailyCells = [];
        $conflictsByStaff = [];

        foreach ($allCells as $cell) {
            $year = (int)$cell['year'];
            $month = (int)$cell['month'];
            $day = (int)$cell['day_number'];
            $cellDate = sprintf('%04d-%02d-%02d', $year, $month, $day);

            if ($cellDate < $startDate || $cellDate > $endDate) {
                continue;
            }

            $empNo = trim($cell['emp_no'] ?? '');
            if ($empNo === '') continue;

            $staffName = $cell['emp_name'] ?: ($cell['emp_name_snapshot'] ?: $empNo);

            if (!isset($staffMap[$empNo])) {
                $staffMap[$empNo] = [
                    'staff_id' => $cell['staff_id'],
                    'emp_no' => $empNo,
                    'staff_name' => $staffName,
                    'sites' => [],
                    'day_shifts' => 0,
                    'night_shifts' => 0,
                    'basic_shifts' => 0,
                    'sick_leaves' => 0,
                    'off_days' => 0,
                    'total_working_days' => 0,
                    'has_conflict' => false,
                    'custom_values' => [],
                    'daily_codes' => []
                ];
            }

            if (!in_array($cell['site_name'], $staffMap[$empNo]['sites'])) {
                $staffMap[$empNo]['sites'][] = $cell['site_name'];
            }

            $v = strtoupper(trim($cell['code'] ?? ''));
            // Save code for this day
            if (isset($staffDailyCells[$empNo][$cellDate]) && !empty($v)) {
                // Conflict
                $staffMap[$empNo]['has_conflict'] = true;
                $conflictsByStaff[$empNo][] = $cellDate;
            }
            if (!empty($v)) {
                $staffDailyCells[$empNo][$cellDate] = $v;
            }
        }

        // Calculate counts for each employee
        foreach ($staffMap as $empNo => &$data) {
            $data['sites_str'] = implode(', ', $data['sites']);
            $data['daily_codes'] = [];

            foreach ($datesList as $dInfo) {
                $dStr = $dInfo['date_str'];
                $v = $staffDailyCells[$empNo][$dStr] ?? '';
                $data['daily_codes'][$dStr] = $v;

                if ($v === 'D') {
                    $data['day_shifts']++;
                } elseif ($v === 'N') {
                    $data['night_shifts']++;
                } elseif ($v === 'B') {
                    $data['basic_shifts']++;
                } elseif ($v === 'S') {
                    $data['sick_leaves']++;
                } elseif ($v === 'OFF') {
                    $data['off_days']++;
                }
            }

            // Total No. of Days = Day + Night + Basic + Sick (OFF is not counted)
            $data['total_working_days'] = $data['day_shifts'] + $data['night_shifts'] + $data['basic_shifts'] + $data['sick_leaves'];

            $data['remarks'] = $this->getStaffRemarks($empNo, $periodKey);
            if ($data['has_conflict'] && isset($conflictsByStaff[$empNo])) {
                $confStr = "Conflict on " . implode(', ', array_unique($conflictsByStaff[$empNo]));
                $data['remarks'] = !empty($data['remarks']) ? $data['remarks'] . " | " . $confStr : $confStr;
            }

            foreach ($customCols as $col) {
                $data['custom_values'][$col['id']] = $this->getCustomValue($empNo, $col['id'], $periodKey);
            }
        }
        unset($data);

        // Sort by Staff Name ascending
        uasort($staffMap, function($a, $b) {
            return strcasecmp($a['staff_name'], $b['staff_name']);
        });

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'dates_list' => $datesList,
            'days_count' => count($datesList),
            'custom_columns' => $customCols,
            'staff' => array_values($staffMap)
        ];
    }

    public function getCustomColumns() {
        $stmt = $this->pdo->query("SELECT id, col_name AS column_name, col_type, sort_order AS position FROM att_payroll_columns WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addCustomColumn($columnName, $colType = 'number', $position = null) {
        if ($position === null) {
            $stmt = $this->pdo->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM att_payroll_columns");
            $position = (int)$stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare("INSERT INTO att_payroll_columns (col_name, col_type, sort_order, is_active) VALUES (?, ?, ?, 1)");
        $stmt->execute([trim($columnName), $colType, $position]);
        return $this->pdo->lastInsertId();
    }

    public function deleteCustomColumn($colId) {
        // Soft deactivate the column so historical payroll values are never destroyed or lost
        $stmt = $this->pdo->prepare("UPDATE att_payroll_columns SET is_active = 0 WHERE id = ?");
        return $stmt->execute([(int)$colId]);
    }

    public function updateCustomColumn($colId, $columnName, $colType) {
        $stmt = $this->pdo->prepare("UPDATE att_payroll_columns SET col_name = ?, col_type = ? WHERE id = ?");
        return $stmt->execute([trim($columnName), $colType, (int)$colId]);
    }

    public function setCustomValue($empNo, $columnId, $startDate, $endDate, $numVal, $textVal = null) {
        $periodKey = $this->getPeriodKey($startDate, $endDate);
        $inTx = $this->pdo->inTransaction();
        if (!$inTx) $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO att_payroll_values (period_key, emp_no, column_id, val_number, val_text)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE val_number = VALUES(val_number), val_text = VALUES(val_text), updated_at = CURRENT_TIMESTAMP
            ");
            $res = $stmt->execute([$periodKey, (string)$empNo, (int)$columnId, ($numVal !== '' && $numVal !== null) ? $numVal : null, $textVal]);
            if (!$inTx) $this->pdo->commit();
            return $res;
        } catch (Throwable $e) {
            if (!$inTx && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getCustomValue($empNo, $columnId, $periodKey) {
        $stmt = $this->pdo->prepare("
            SELECT val_number AS num_val, val_text AS text_val FROM att_payroll_values 
            WHERE period_key = ? AND emp_no = ? AND column_id = ?
        ");
        $stmt->execute([$periodKey, (string)$empNo, (int)$columnId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function setStaffRemarks($empNo, $startDate, $endDate, $remarks) {
        $periodKey = $this->getPeriodKey($startDate, $endDate);
        $inTx = $this->pdo->inTransaction();
        if (!$inTx) $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO att_payroll_remarks (period_key, emp_no, remarks)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE remarks = VALUES(remarks), updated_at = CURRENT_TIMESTAMP
            ");
            $res = $stmt->execute([$periodKey, (string)$empNo, trim($remarks)]);
            if (!$inTx) $this->pdo->commit();
            return $res;
        } catch (Throwable $e) {
            if (!$inTx && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getStaffRemarks($empNo, $periodKey) {
        $stmt = $this->pdo->prepare("
            SELECT remarks FROM att_payroll_remarks 
            WHERE period_key = ? AND emp_no = ?
        ");
        $stmt->execute([$periodKey, (string)$empNo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['remarks'] : '';
    }
}
