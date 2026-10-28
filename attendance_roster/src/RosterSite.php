<?php
/**
 * Universal Security Services - Attendance Roster Subsystem
 * Site Directory, Requirements & Additional Deployment Model
 * File: /attendance_roster/src/RosterSite.php
 */
require_once dirname(__DIR__) . '/config/roster_config.php';

class RosterSite {
    /**
     * Get or create site configuration in roster_sites_config
     */
    public static function getOrCreate(int $siteId, string $siteName, ?string $siteCode = null, ?string $clientName = null, int $reqDay = 1, int $reqNight = 1): array {
        $db = roster_get_db();
        $stmt = $db->prepare("SELECT * FROM roster_sites_config WHERE site_id = ? LIMIT 1");
        $stmt->execute([$siteId]);
        $site = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($site) {
            return $site;
        }

        $stmtIns = $db->prepare("
            INSERT INTO roster_sites_config (site_id, site_name, site_code, client_name, req_day_base, req_night_base, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmtIns->execute([$siteId, $siteName, $siteCode, $clientName, $reqDay, $reqNight]);
        $id = (int)$db->lastInsertId();

        return [
            'id' => $id,
            'site_id' => $siteId,
            'site_name' => $siteName,
            'site_code' => $siteCode,
            'client_name' => $clientName,
            'req_day_base' => $reqDay,
            'req_night_base' => $reqNight,
            'is_active' => 1
        ];
    }

    /**
     * Get all active sites
     */
    public static function getAll(): array {
        $db = roster_get_db();
        return $db->query("SELECT * FROM roster_sites_config WHERE is_active = 1 ORDER BY site_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get site config by site_id
     */
    public static function getById(int $siteId): ?array {
        $db = roster_get_db();
        $stmt = $db->prepare("SELECT * FROM roster_sites_config WHERE site_id = ? LIMIT 1");
        $stmt->execute([$siteId]);
        $site = $stmt->fetch(PDO::FETCH_ASSOC);
        return $site ?: null;
    }

    /**
     * Update site requirements
     */
    public static function updateRequirements(int $siteId, int $reqDay, int $reqNight, ?string $start = null, ?string $end = null): bool {
        $db = roster_get_db();
        $stmt = $db->prepare("
            UPDATE roster_sites_config 
            SET req_day_base = ?, req_night_base = ?, effective_from = ?, effective_to = ?
            WHERE site_id = ?
        ");
        return $stmt->execute([max(0, $reqDay), max(0, $reqNight), $start ?: null, $end ?: null, $siteId]);
    }

    /**
     * Sync sites from main system `sites` table (strictly read-only)
     */
    public static function syncFromSites(): int {
        $db = roster_get_db();
        try {
            $sysSites = $db->query("SELECT id, name, code, client_name FROM sites WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return 0;
        }

        $count = 0;
        foreach ($sysSites as $s) {
            self::getOrCreate((int)$s['id'], $s['name'], $s['code'], $s['client_name'] ?? null);
            $count++;
        }
        return $count;
    }

    /**
     * Add Additional Deployment for a site and date range
     */
    public static function addAdditionalDeployment(int $siteId, string $shift, int $staffCount, string $from, string $to, string $reason, string $approvedBy = 'Super Admin'): int {
        $db = roster_get_db();
        $shift = strtoupper(trim($shift));
        if (!in_array($shift, ['D', 'N'])) {
            throw new InvalidArgumentException("Shift must be 'D' or 'N'");
        }

        $stmt = $db->prepare("
            INSERT INTO roster_additional_deployments (site_id, shift, staff_count, date_from, date_to, reason, approved_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$siteId, $shift, max(1, $staffCount), $from, $to, $reason, $approvedBy]);
        return (int)$db->lastInsertId();
    }

    /**
     * Delete Additional Deployment
     */
    public static function deleteAdditionalDeployment(int $id): bool {
        $db = roster_get_db();
        $stmt = $db->prepare("DELETE FROM roster_additional_deployments WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Get all additional deployments active for a site in a specific month
     */
    public static function getAdditionalDeploymentsForMonth(int $siteId, int $year, int $month): array {
        $db = roster_get_db();
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $stmt = $db->prepare("
            SELECT * FROM roster_additional_deployments 
            WHERE site_id = ? 
              AND date_from <= ? 
              AND date_to >= ?
            ORDER BY date_from ASC, shift ASC
        ");
        $stmt->execute([$siteId, $monthEnd, $monthStart]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compute date-by-date requirements (Contract + Additional) for an entire month
     */
    public static function getMonthlyRequirements(int $siteId, int $year, int $month): array {
        $site = self::getById($siteId);
        $baseDay = $site ? (int)$site['req_day_base'] : 1;
        $baseNight = $site ? (int)$site['req_night_base'] : 1;
        $contractStart = $site['effective_from'] ?? null;
        $contractEnd = $site['effective_to'] ?? null;

        $daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
        $additional = self::getAdditionalDeploymentsForMonth($siteId, $year, $month);

        $requirements = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            
            // Check contract date limits
            $inContract = true;
            if (!empty($contractStart) && $dateStr < $contractStart) $inContract = false;
            if (!empty($contractEnd) && $dateStr > $contractEnd) $inContract = false;

            $contractD = $inContract ? $baseDay : 0;
            $contractN = $inContract ? $baseNight : 0;

            $extraD = 0;
            $extraN = 0;
            foreach ($additional as $add) {
                if ($dateStr >= $add['date_from'] && $dateStr <= $add['date_to']) {
                    if ($add['shift'] === 'D') $extraD += (int)$add['staff_count'];
                    if ($add['shift'] === 'N') $extraN += (int)$add['staff_count'];
                }
            }

            $reqD = $contractD + $extraD;
            $reqN = $contractN + $extraN;

            $requirements[$d] = [
                'date' => $dateStr,
                'contract_day' => $contractD,
                'contract_night' => $contractN,
                'additional_day' => $extraD,
                'additional_night' => $extraN,
                'required_day' => $reqD,
                'required_night' => $reqN,
                'total_required' => $reqD + $reqN
            ];
        }

        return [
            'site' => $site,
            'daysInMonth' => $daysInMonth,
            'days' => $requirements,
            'additional_list' => $additional
        ];
    }
}

