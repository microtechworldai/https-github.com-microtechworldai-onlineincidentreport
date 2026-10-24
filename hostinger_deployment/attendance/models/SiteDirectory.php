<?php
/**
 * Site Directory Model for Attendance Subsystem
 * Independent from incident sites
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Audit.php';

class SiteDirectory {
    public static function getAllSites($onlyActive = true) {
        $db = att_get_db();
        $sql = "SELECT * FROM att_sites";
        if ($onlyActive) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY site_name ASC";
        return $db->query($sql)->fetchAll();
    }

    public static function getSiteById($id) {
        $db = att_get_db();
        $stmt = $db->prepare("SELECT * FROM att_sites WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$id]);
        return $stmt->fetch();
    }

    public static function addSite($name, $code, $clientName = '', $reqDay = 1, $reqNight = 1, $contractStart = null, $contractEnd = null, $actorName = 'Super Admin') {
        $db = att_get_db();
        $cleanName = trim($name);
        $cleanCode = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', trim($code)));
        if (empty($cleanName) || empty($cleanCode)) {
            throw new InvalidArgumentException("Site name and code are required.");
        }

        $reqDay = max(0, (int)$reqDay);
        $reqNight = max(0, (int)$reqNight);
        $contractStart = !empty($contractStart) ? trim($contractStart) : null;
        $contractEnd = !empty($contractEnd) ? trim($contractEnd) : null;

        $stmt = $db->prepare("INSERT INTO att_sites (site_name, site_code, client_name, req_day, req_night, contract_start, contract_end, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$cleanName, $cleanCode, trim($clientName), $reqDay, $reqNight, $contractStart, $contractEnd]);
        $id = (int)$db->lastInsertId();

        att_log_audit('SITE_CREATE', 'att_sites', $id, [
            'name' => $cleanName,
            'code' => $cleanCode,
            'req_day' => $reqDay,
            'req_night' => $reqNight,
            'contract_start' => $contractStart,
            'contract_end' => $contractEnd
        ], $actorName);
        return $id;
    }

    public static function updateSite($id, $name, $code, $clientName = '', $reqDay = 1, $reqNight = 1, $contractStart = null, $contractEnd = null, $isActive = 1, $actorName = 'Super Admin') {
        $db = att_get_db();
        $id = (int)$id;
        $reqDay = max(0, (int)$reqDay);
        $reqNight = max(0, (int)$reqNight);
        $contractStart = !empty($contractStart) ? trim($contractStart) : null;
        $contractEnd = !empty($contractEnd) ? trim($contractEnd) : null;

        $stmt = $db->prepare("UPDATE att_sites SET site_name = ?, site_code = ?, client_name = ?, req_day = ?, req_night = ?, contract_start = ?, contract_end = ?, is_active = ? WHERE id = ?");
        $stmt->execute([trim($name), strtoupper(trim($code)), trim($clientName), $reqDay, $reqNight, $contractStart, $contractEnd, $isActive ? 1 : 0, $id]);

        att_log_audit('SITE_UPDATE', 'att_sites', $id, [
            'name' => $name,
            'code' => $code,
            'req_day' => $reqDay,
            'req_night' => $reqNight,
            'contract_start' => $contractStart,
            'contract_end' => $contractEnd
        ], $actorName);
        return true;
    }

    public static function deleteSite($id, $actorName = 'Super Admin') {
        $db = att_get_db();
        $id = (int)$id;
        $site = self::getSiteById($id);
        if (!$site) {
            return false;
        }

        // Soft delete: deactivate site so historic sheets and audit records are safe
        $stmt = $db->prepare("UPDATE att_sites SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);

        att_log_audit('SITE_DELETE', 'att_sites', $id, ['name' => $site['site_name'], 'code' => $site['site_code']], $actorName);
        return true;
    }
}
