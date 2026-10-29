<?php
/**
 * Universal Security Services - Attendance Roster Subsystem
 * Staff Directory Model & Document Expiry Management
 * File: /attendance_roster/src/RosterStaff.php
 */
require_once dirname(__DIR__) . '/config/roster_config.php';

class RosterStaff {
    /**
     * Get or create a staff member in roster_staff
     */
    public static function getOrCreate(string $empNo, string $name, string $designation = 'Security Guard', ?int $userId = null): array {
        $db = roster_get_db();
        $empNo = strtoupper(trim($empNo));
        $name = trim($name);

        $stmt = $db->prepare("SELECT * FROM roster_staff WHERE emp_no = ? LIMIT 1");
        $stmt->execute([$empNo]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($staff) {
            if ($staff['name'] !== $name || $staff['designation'] !== $designation) {
                $stmtUp = $db->prepare("UPDATE roster_staff SET name = ?, designation = ? WHERE id = ?");
                $stmtUp->execute([$name, $designation, $staff['id']]);
                $staff['name'] = $name;
                $staff['designation'] = $designation;
            }
            return $staff;
        }

        $stmtIns = $db->prepare("
            INSERT INTO roster_staff (emp_no, name, designation, user_id, is_active)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmtIns->execute([$empNo, $name, $designation, $userId]);
        $id = (int)$db->lastInsertId();

        return [
            'id' => $id,
            'emp_no' => $empNo,
            'name' => $name,
            'designation' => $designation,
            'user_id' => $userId,
            'is_active' => 1
        ];
    }

    /**
     * Search staff by ID or Name
     */
    public static function search(string $query = '', int $limit = 100): array {
        $db = roster_get_db();
        $q = trim($query);

        if (empty($q)) {
            $stmt = $db->query("SELECT * FROM roster_staff WHERE is_active = 1 ORDER BY name ASC LIMIT $limit");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $param = '%' . $q . '%';
        $stmt = $db->prepare("
            SELECT * FROM roster_staff 
            WHERE (emp_no LIKE ? OR name LIKE ? OR designation LIKE ?) AND is_active = 1
            ORDER BY name ASC LIMIT $limit
        ");
        $stmt->execute([$param, $param, $param]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sync staff from existing `guards` table (strictly read-only on `guards`)
     */
    public static function syncFromGuards(): int {
        $db = roster_get_db();
        try {
            $guards = $db->query("SELECT id, staff_id, full_name, designation, is_active FROM guards")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return 0; // guards table might not be present or accessible
        }

        $count = 0;
        foreach ($guards as $g) {
            $empNo = !empty($g['staff_id']) ? $g['staff_id'] : 'EMP-' . $g['id'];
            $name = $g['full_name'] ?: 'Guard ' . $empNo;
            $desig = $g['designation'] ?: 'Security Guard';
            self::getOrCreate($empNo, $name, $desig, (int)$g['id']);
            $count++;
        }
        return $count;
    }

    /**
     * Check document expiries (warnings at 30 days and 7 days)
     */
    public static function checkDocumentExpiries(?int $staffId = null): array {
        $db = roster_get_db();
        $sql = "SELECT id, emp_no, name, sira_expiry, visa_expiry, passport_expiry FROM roster_staff WHERE is_active = 1";
        if ($staffId !== null) {
            $sql .= " AND id = " . (int)$staffId;
        }

        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $today = new DateTime();
        $warnings = [];

        foreach ($rows as $r) {
            $checks = [
                'SIRA Card' => $r['sira_expiry'],
                'Residence Visa' => $r['visa_expiry'],
                'Passport' => $r['passport_expiry']
            ];

            foreach ($checks as $doc => $expStr) {
                if (!empty($expStr)) {
                    $expDate = new DateTime($expStr);
                    $diff = (int)$today->diff($expDate)->format('%r%a');

                    if ($diff < 0) {
                        $warnings[] = [
                            'staff_id' => $r['id'],
                            'emp_no' => $r['emp_no'],
                            'name' => $r['name'],
                            'document' => $doc,
                            'status' => 'EXPIRED',
                            'days_remaining' => $diff,
                            'expiry_date' => $expStr
                        ];
                    } elseif ($diff <= 30) {
                        $warnings[] = [
                            'staff_id' => $r['id'],
                            'emp_no' => $r['emp_no'],
                            'name' => $r['name'],
                            'document' => $doc,
                            'status' => $diff <= 7 ? 'CRITICAL_EXPIRY' : 'WARNING_EXPIRY',
                            'days_remaining' => $diff,
                            'expiry_date' => $expStr
                        ];
                    }
                }
            }
        }

        return $warnings;
    }

    /**
     * Find staff members free (no attendance anywhere) on a given date
     */
    public static function findFreeStaff(string $date, ?string $designation = null): array {
        $db = roster_get_db();
        $sql = "
            SELECT s.* 
            FROM roster_staff s
            WHERE s.is_active = 1
              AND s.id NOT IN (
                  SELECT e.staff_id 
                  FROM roster_entries e 
                  WHERE e.work_date = ? 
                    AND e.code IN ('D', 'N', 'B')
                    AND e.deleted_at IS NULL
              )
        ";
        $params = [$date];

        if (!empty($designation)) {
            $sql .= " AND s.designation = ? ";
            $params[] = $designation;
        }

        $sql .= " ORDER BY s.name ASC ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

