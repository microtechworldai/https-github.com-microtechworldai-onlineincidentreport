<?php
/**
 * Universal Security Services - Attendance Roster Subsystem
 * Master Front Controller & Dispatcher
 * File: /attendance_roster/index.php
 */
require_once __DIR__ . '/config/roster_config.php';
require_once __DIR__ . '/src/RosterAudit.php';
require_once __DIR__ . '/src/RosterStaff.php';
require_once __DIR__ . '/src/RosterSite.php';
require_once __DIR__ . '/src/RosterEntry.php';
require_once __DIR__ . '/exports/ExcelExport.php';
require_once __DIR__ . '/exports/PdfExport.php';

// Rule R8: Strictly enforce Super Admin access
roster_require_super_admin();

$action = $_GET['action'] ?? $_POST['action'] ?? 'sheet';

try {
    switch ($action) {
        // Save or update cell attendance code (Rule R1, R3, R4, R5, R6, R7)
        case 'save_cell':
            $staffId = (int)($_POST['staff_id'] ?? 0);
            $siteId = (int)($_POST['site_id'] ?? 0);
            $date = trim($_POST['date'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $forceDouble = !empty($_POST['force_double']);
            $forceReason = trim($_POST['force_reason'] ?? '');

            try {
                $res = RosterEntry::setCellCode($staffId, $siteId, $date, $code, $forceDouble, $forceReason);
                header('Content-Type: application/json');
                echo json_encode($res);
            } catch (RosterConflictException $ce) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'has_conflict' => true,
                    'error' => $ce->getMessage(),
                    'conflict' => $ce->getConflictDetails()
                ]);
            }
            exit;

        // Move attendance between sites (Rule R4 Action 3)
        case 'move_attendance':
            $staffId = (int)($_POST['staff_id'] ?? 0);
            $date = trim($_POST['date'] ?? '');
            $fromSiteId = (int)($_POST['from_site_id'] ?? 0);
            $toSiteId = (int)($_POST['to_site_id'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');

            $res = RosterEntry::moveAttendance($staffId, $date, $fromSiteId, $toSiteId, $reason);
            header('Content-Type: application/json');
            echo json_encode($res);
            exit;

        // Configure Additional Deployment
        case 'add_deployment':
            $siteId = (int)($_POST['site_id'] ?? 0);
            $shift = trim($_POST['shift'] ?? 'D');
            $staffCount = (int)($_POST['staff_count'] ?? 1);
            $from = trim($_POST['date_from'] ?? '');
            $to = trim($_POST['date_to'] ?? '');
            $reason = trim($_POST['reason'] ?? '');

            RosterSite::addAdditionalDeployment($siteId, $shift, $staffCount, $from, $to, $reason, $_SESSION['admin_name'] ?? 'Super Admin');
            header("Location: index.php?site_id=$siteId");
            exit;

        // Delete Additional Deployment
        case 'delete_deployment':
            $deployId = (int)($_POST['deployment_id'] ?? 0);
            RosterSite::deleteAdditionalDeployment($deployId);
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
            exit;

        // Lock / Unlock Month (Rule R7)
        case 'toggle_lock':
            $siteId = (int)($_POST['site_id'] ?? 0);
            $year = (int)($_POST['year'] ?? (int)date('Y'));
            $month = (int)($_POST['month'] ?? (int)date('n'));
            $lockAction = trim($_POST['lock_action'] ?? 'lock');
            $status = ($lockAction === 'unlock') ? 'Approved' : 'Locked';
            $reason = ($lockAction === 'unlock') ? 'Administrative adjustment' : 'Month finalized for payroll';

            RosterEntry::setSheetStatus($siteId, $year, $month, $status, $reason);
            header("Location: index.php?site_id=$siteId&year=$year&month=$month");
            exit;

        // Remove Guard from Site (Rule R9: De-links future dates; historical entries remain untouched)
        case 'remove_staff':
            $staffId = (int)($_POST['staff_id'] ?? 0);
            $siteId = (int)($_POST['site_id'] ?? 0);
            $db = roster_get_db();
            // Delete future assignments
            $stmtDel = $db->prepare("DELETE FROM roster_assignments WHERE staff_id = ? AND site_id = ? AND date_from >= CURDATE()");
            $stmtDel->execute([$staffId, $siteId]);

            RosterAudit::log('DE_LINK_STAFF', $staffId, $siteId, null, null, null, 'De-linked guard from site (past entries retained)');
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
            exit;

        // Find Free Staff (Professional Control 2)
        case 'free_staff':
            $date = trim($_GET['date'] ?? date('Y-m-d'));
            $free = RosterStaff::findFreeStaff($date);
            header('Content-Type: application/json');
            echo json_encode(['staff' => $free]);
            exit;

        // Undo Action (Rule R5)
        case 'undo':
            $token = trim($_POST['undo_token'] ?? '');
            $res = RosterAudit::undo($token);
            header('Content-Type: application/json');
            echo json_encode(['success' => $res]);
            exit;

        // Excel Export (.xls with true COUNTIF formulas)
        case 'export_excel':
            $siteId = (int)($_GET['site_id'] ?? 1);
            $year = (int)($_GET['year'] ?? (int)date('Y'));
            $month = (int)($_GET['month'] ?? (int)date('n'));
            ExcelExport::exportSheet($siteId, $year, $month);
            exit;

        // PDF / Print Export (A4 Landscape)
        case 'export_pdf':
            $siteId = (int)($_GET['site_id'] ?? 1);
            $year = (int)($_GET['year'] ?? (int)date('Y'));
            $month = (int)($_GET['month'] ?? (int)date('n'));
            PdfExport::renderPrintView($siteId, $year, $month);
            exit;

        // Default Master View
        case 'sheet':
        default:
            require __DIR__ . '/templates/roster_sheet.php';
            break;
    }
} catch (Throwable $e) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
    echo '<div style="padding:20px;color:#b91c1c;font-family:sans-serif;"><h3>Roster Error</h3><p>' . htmlspecialchars($e->getMessage()) . '</p></div>';
}

