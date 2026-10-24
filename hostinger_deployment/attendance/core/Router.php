<?php
// Attendance & Payroll Module - Front Controller & Action Router
// File: hostinger_deployment/attendance/core/Router.php

if (!defined('ATT_ACCESS')) {
    die('Direct access not permitted.');
}

class Router {
    private $pdo;
    private $payroll;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->payroll = new PayrollEngine($pdo);
    }

    public function dispatch() {
        require_once __DIR__ . '/BackupEngine.php';
        // Automated silent daily snapshot of database
        try {
            BackupEngine::autoDailyBackup($this->pdo);
        } catch (Throwable $ignore) {}

        $action = $_GET['action'] ?? 'sheet';
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'POST') {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['att_csrf_token'] ?? '';
            if (!att_verify_csrf_token($token)) {
                $this->jsonError('Security verification failed. Invalid or expired CSRF token.', 403);
                return;
            }
        }

        try {
            switch ($action) {
                // Attendance Roster view & actions
                case 'sheet':
                    $this->handleSheetView();
                    break;
                case 'save_cell':
                    $this->handleSaveCell();
                    break;
                case 'copy_staff':
                    $this->handleCopyStaff();
                    break;
                case 'lock_sheet':
                    $this->handleLockSheet();
                    break;

                // Site & Staff management actions
                case 'sites':
                    $this->handleSites();
                    break;
                case 'save_site':
                    $this->handleSaveSite();
                    break;
                case 'rename_site':
                    $this->handleRenameSite();
                    break;
                case 'delete_site':
                    $this->handleDeleteSite();
                    break;
                case 'save_additional_deployment':
                    $this->handleSaveAdditionalDeployment();
                    break;
                case 'delete_additional_deployment':
                    $this->handleDeleteAdditionalDeployment();
                    break;
                case 'update_site_requirements':
                    $this->handleUpdateSiteRequirements();
                    break;
                case 'staff':
                    $this->handleStaff();
                    break;
                case 'save_staff':
                    $this->handleSaveStaff();
                    break;
                case 'edit_sheet_staff':
                    $this->handleEditSheetStaff();
                    break;
                case 'replace_staff_row':
                    $this->handleReplaceStaffRow();
                    break;
                case 'delete_directory_staff':
                    $this->handleDeleteDirectoryStaff();
                    break;
                case 'save_sheet_staff_to_directory':
                    $this->handleSaveSheetStaffToDirectory();
                    break;
                case 'remove_staff_row':
                    $this->handleRemoveStaffRow();
                    break;
                case 'assign_site':
                    $this->handleAssignSite();
                    break;

                // Payroll view & actions
                case 'payroll':
                    $this->handlePayrollView();
                    break;
                case 'save_custom_val':
                    $this->handleSaveCustomVal();
                    break;
                case 'save_remarks':
                    $this->handleSaveRemarks();
                    break;
                case 'add_column':
                    $this->handleAddColumn();
                    break;
                case 'edit_column':
                    $this->handleEditColumn();
                    break;
                case 'delete_column':
                    $this->handleDeleteColumn();
                    break;

                // Exports
                case 'export_excel':
                    $this->handleExportExcel();
                    break;
                case 'export_pdf':
                    $this->handleExportPdf();
                    break;

                // Database Protection & Backup
                case 'backup_download':
                    $this->handleBackupDownload();
                    break;
                case 'backup_snapshot':
                    $this->handleBackupSnapshot();
                    break;

                default:
                    $this->handleSheetView();
                    break;
            }
        } catch (Throwable $e) {
            @error_log("[" . date('Y-m-d H:i:s') . "] Uncaught Error in Router: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", 3, ATT_LOGS_PATH . '/app_errors.log');
            if ($this->isAjax()) {
                $this->jsonError($e->getMessage(), 500);
            } else {
                echo '<div style="padding:20px;color:#c00;font-family:sans-serif;">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }

    private function isAjax() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
            || !empty($_POST['ajax']) || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);
    }

    private function jsonSuccess($data = []) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => true], $data));
        exit;
    }

    private function jsonError($message, $code = 400) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }

    private function handleSheetView() {
        $sites = SiteDirectory::getAllSites(true);
        $selectedSiteId = isset($_GET['site_id']) ? (int)$_GET['site_id'] : ($sites[0]['id'] ?? 0);
        $selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

        $sheetData = null;
        if ($selectedSiteId > 0) {
            $sheet = AttendanceSheet::getOrCreateSheet($selectedSiteId, $selectedYear, $selectedMonth);
            if ($sheet) {
                $sheetData = AttendanceSheet::getSheetFullData($sheet['id']);
            }
        }

        require __DIR__ . '/../views/sheet.php';
    }

    private function handleSaveCell() {
        $sheetId = (int)($_POST['sheet_id'] ?? 0);
        $rowId = (int)($_POST['row_id'] ?? 0);
        $day = (int)($_POST['day'] ?? 0);
        $val = strtoupper(trim($_POST['val'] ?? ''));

        $validVals = ['', 'D', 'N', 'B', 'S', 'OFF'];
        if (!in_array($val, $validVals, true)) {
            $this->jsonError("Invalid attendance code '$val'. Allowed: D, N, S, B, OFF or empty.");
        }

        $res = AttendanceSheet::updateCellCode($sheetId, $rowId, $day, $val, 1, $_SESSION['admin_name'] ?? 'Super Admin');
        $this->jsonSuccess($res);
    }

    private function handleCopyStaff() {
        $targetSiteId = (int)($_POST['site_id'] ?? 0);
        $targetYear = (int)($_POST['year'] ?? 0);
        $targetMonth = (int)($_POST['month'] ?? 0);

        $result = AttendanceSheet::copyStaffFromPreviousMonth($targetSiteId, $targetYear, $targetMonth, $_SESSION['admin_name'] ?? 'Super Admin');
        $this->jsonSuccess($result);
    }

    private function handleLockSheet() {
        $sheetId = (int)($_POST['sheet_id'] ?? 0);
        $lock = !empty($_POST['lock']);
        AttendanceSheet::toggleLock($sheetId, $lock, 'Admin toggle lock', $_SESSION['admin_name'] ?? 'Super Admin');
        $this->jsonSuccess(['locked' => $lock]);
    }

    private function handleSites() {
        $sites = SiteDirectory::getAllSites(true);
        $this->jsonSuccess(['sites' => $sites]);
    }

    private function handleSaveSite() {
        $name = trim($_POST['site_name'] ?? '');
        $code = trim($_POST['site_code'] ?? '');
        $client = trim($_POST['client_name'] ?? $_POST['description'] ?? '');
        $reqDay = isset($_POST['req_day']) ? max(0, (int)$_POST['req_day']) : 1;
        $reqNight = isset($_POST['req_night']) ? max(0, (int)$_POST['req_night']) : 1;
        $contractStart = !empty($_POST['contract_start']) ? trim($_POST['contract_start']) : null;
        $contractEnd = !empty($_POST['contract_end']) ? trim($_POST['contract_end']) : null;

        if (empty($name)) {
            $this->jsonError('Site name is required.');
        }

        if (empty($code)) {
            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 10));
        }

        $newId = SiteDirectory::addSite($name, $code, $client, $reqDay, $reqNight, $contractStart, $contractEnd, $_SESSION['admin_name'] ?? 'Super Admin');
        
        header("Location: index.php?action=sheet&site_id=" . $newId);
        exit;
    }

    private function handleRenameSite() {
        $siteId = (int)($_POST['site_id'] ?? 0);
        $name = trim($_POST['site_name'] ?? '');
        if (!$siteId || empty($name)) {
            $this->jsonError('Site ID and updated site name are required.');
        }

        $site = SiteDirectory::getSiteById($siteId);
        if (!$site) {
            $this->jsonError('Site not found.');
        }

        SiteDirectory::updateSite($siteId, $name, $site['site_code'], $site['client_name'] ?? '', $site['req_day'] ?? 1, $site['req_night'] ?? 1, $site['contract_start'] ?? null, $site['contract_end'] ?? null, 1, $_SESSION['admin_name'] ?? 'Super Admin');
        header("Location: index.php?action=sheet&site_id=" . $siteId);
        exit;
    }

    private function handleDeleteSite() {
        $siteId = (int)($_POST['site_id'] ?? $_GET['site_id'] ?? 0);
        if (!$siteId) {
            $this->jsonError('Site ID is required.');
        }
        SiteDirectory::deleteSite($siteId, $_SESSION['admin_name'] ?? 'Super Admin');
        header("Location: index.php?action=sheet");
        exit;
    }

    private function handleSaveAdditionalDeployment() {
        $sheetId = (int)($_POST['sheet_id'] ?? 0);
        $dayNumber = $_POST['day_number'] ?? 'all';
        $addDay = (int)($_POST['add_day'] ?? 0);
        $addNight = (int)($_POST['add_night'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if (!$sheetId) {
            $this->jsonError('Sheet ID is required.');
        }

        AttendanceSheet::saveAdditionalDeployment($sheetId, $dayNumber, $addDay, $addNight, $notes, $_SESSION['admin_name'] ?? 'Super Admin');
        
        $siteId = (int)($_POST['site_id'] ?? 0);
        $month = (int)($_POST['month'] ?? 0);
        $year = (int)($_POST['year'] ?? 0);
        $redirect = "index.php?action=sheet";
        if ($siteId) $redirect .= "&site_id={$siteId}";
        if ($month) $redirect .= "&month={$month}";
        if ($year) $redirect .= "&year={$year}";
        header("Location: " . $redirect);
        exit;
    }

    private function handleDeleteAdditionalDeployment() {
        $sheetId = (int)($_POST['sheet_id'] ?? $_GET['sheet_id'] ?? 0);
        $dayNumber = $_POST['day_number'] ?? $_GET['day_number'] ?? 'all';

        if (!$sheetId) {
            $this->jsonError('Sheet ID is required.');
        }

        AttendanceSheet::deleteAdditionalDeployment($sheetId, $dayNumber, $_SESSION['admin_name'] ?? 'Super Admin');

        $siteId = (int)($_POST['site_id'] ?? $_GET['site_id'] ?? 0);
        $month = (int)($_POST['month'] ?? $_GET['month'] ?? 0);
        $year = (int)($_POST['year'] ?? $_GET['year'] ?? 0);
        $redirect = "index.php?action=sheet";
        if ($siteId) $redirect .= "&site_id={$siteId}";
        if ($month) $redirect .= "&month={$month}";
        if ($year) $redirect .= "&year={$year}";
        header("Location: " . $redirect);
        exit;
    }

    private function handleUpdateSiteRequirements() {
        $siteId = (int)($_POST['site_id'] ?? 0);
        $reqDay = max(0, (int)($_POST['req_day'] ?? 1));
        $reqNight = max(0, (int)($_POST['req_night'] ?? 1));
        $contractStart = !empty($_POST['contract_start']) ? trim($_POST['contract_start']) : null;
        $contractEnd = !empty($_POST['contract_end']) ? trim($_POST['contract_end']) : null;

        if (!$siteId) {
            $this->jsonError('Site ID is required.');
        }

        AttendanceSheet::updateSiteRequirements($siteId, $reqDay, $reqNight, $contractStart, $contractEnd, $_SESSION['admin_name'] ?? 'Super Admin');

        $month = (int)($_POST['month'] ?? 0);
        $year = (int)($_POST['year'] ?? 0);
        $redirect = "index.php?action=sheet&site_id={$siteId}";
        if ($month) $redirect .= "&month={$month}";
        if ($year) $redirect .= "&year={$year}";
        header("Location: " . $redirect);
        exit;
    }

    private function handleStaff() {
        $query = $_GET['q'] ?? '';
        $staff = StaffDirectory::searchStaff($query);
        $this->jsonSuccess(['staff' => $staff]);
    }

    private function handleSaveStaff() {
        $empNo = strtoupper(trim($_POST['emp_no'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $sheetId = isset($_POST['sheet_id']) ? (int)$_POST['sheet_id'] : 0;
        $siteId = isset($_POST['site_id']) ? (int)$_POST['site_id'] : 0;

        if (empty($name) || empty($empNo)) {
            $this->jsonError('Employee Number and Staff Name are required.');
        }

        if ($sheetId > 0) {
            $stmtCheck = $this->pdo->prepare("SELECT id FROM att_rows WHERE sheet_id = ? AND emp_no = ? AND is_deleted = 0 LIMIT 1");
            $stmtCheck->execute([$sheetId, $empNo]);
            if ($stmtCheck->fetch()) {
                throw new InvalidArgumentException("Employee Number '{$empNo}' is already on this sheet. Emp No cannot repeat on one sheet.");
            }
        }

        StaffDirectory::addStaff($empNo, $name, 'Security Officer', '', $_SESSION['admin_name'] ?? 'Super Admin');

        if ($sheetId > 0) {
            AttendanceSheet::ensureRowExists($sheetId, $empNo, $name);
        }

        if ($this->isAjax()) {
            $this->jsonSuccess(['emp_no' => $empNo, 'name' => $name]);
        } else {
            header("Location: index.php?action=sheet" . ($siteId ? "&site_id=" . $siteId : ""));
            exit;
        }
    }

    private function handleEditSheetStaff() {
        $sheetId = (int)($_POST['sheet_id'] ?? 0);
        $rowId = (int)($_POST['row_id'] ?? 0);
        $empNo = strtoupper(trim($_POST['emp_no'] ?? ''));
        $name = trim($_POST['name'] ?? '');

        if (!$sheetId || !$rowId || empty($empNo) || empty($name)) {
            $this->jsonError('All fields are required.');
        }

        // Update row on sheet
        $stmt = $this->pdo->prepare("UPDATE att_rows SET emp_no = ?, emp_name_snapshot = ? WHERE id = ? AND sheet_id = ?");
        $stmt->execute([$empNo, $name, $rowId, $sheetId]);

        // Update in directory
        StaffDirectory::addStaff($empNo, $name, 'Security Officer', '', $_SESSION['admin_name'] ?? 'Super Admin');

        $this->jsonSuccess();
    }

    private function handleReplaceStaffRow() {
        $sheetId = (int)($_POST['sheet_id'] ?? 0);
        $rowId = (int)($_POST['row_id'] ?? 0);
        $newEmpNo = strtoupper(trim($_POST['new_emp_no'] ?? ''));
        $newName = trim($_POST['new_name'] ?? '');
        $clearCodes = !empty($_POST['clear_codes']);

        if (!$sheetId || !$rowId || empty($newEmpNo) || empty($newName)) {
            $this->jsonError('Replacement staff details are required.');
        }

        $res = AttendanceSheet::replaceStaffInRow($sheetId, $rowId, $newEmpNo, $newName, $clearCodes, $_SESSION['admin_name'] ?? 'Super Admin');
        $this->jsonSuccess(['replaced' => $res]);
    }

    private function handleDeleteDirectoryStaff() {
        $empNo = trim($_POST['emp_no'] ?? '');
        if (empty($empNo)) {
            $this->jsonError('Employee Number is required.');
        }

        // Deleting from directory deactivates from saved master list, existing sheet records stay intact
        $res = StaffDirectory::deleteStaff($empNo, $_SESSION['admin_name'] ?? 'Super Admin');
        $this->jsonSuccess(['deleted' => $res]);
    }

    private function handleSaveSheetStaffToDirectory() {
        $sheetId = (int)($_POST['sheet_id'] ?? 0);
        if (!$sheetId) {
            $this->jsonError('Sheet ID is required.');
        }

        $stmt = $this->pdo->prepare("SELECT emp_no, emp_name_snapshot FROM att_rows WHERE sheet_id = ? AND is_deleted = 0");
        $stmt->execute([$sheetId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $count = 0;
        foreach ($rows as $r) {
            if (!empty($r['emp_no']) && !empty($r['emp_name_snapshot'])) {
                StaffDirectory::addStaff($r['emp_no'], $r['emp_name_snapshot'], 'Security Officer', '', $_SESSION['admin_name'] ?? 'Super Admin');
                $count++;
            }
        }

        $this->jsonSuccess(['count' => $count]);
    }

    private function handleRemoveStaffRow() {
        $sheetId = (int)($_POST['sheet_id'] ?? 0);
        $rowId = (int)($_POST['row_id'] ?? 0);

        if (!$sheetId || !$rowId) {
            $this->jsonError('Sheet ID and Row ID are required.');
        }

        $res = AttendanceSheet::removeRow($sheetId, $rowId, $_SESSION['admin_name'] ?? 'Super Admin');
        $this->jsonSuccess(['removed' => $res]);
    }

    private function handleAssignSite() {
        $empNo = trim($_POST['emp_no'] ?? '');
        $siteId = (int)($_POST['site_id'] ?? 0);
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $defaultCode = $_POST['default_code'] ?? 'D';
        $isOverride = !empty($_POST['is_override']);
        $reason = $_POST['override_reason'] ?? '';

        if (!$empNo || !$siteId || !$startDate || !$endDate) {
            $this->jsonError('Staff, site, start date, and end date are required.');
        }

        $res = StaffDirectory::assignToSite($empNo, $siteId, $startDate, $endDate, $defaultCode, $isOverride, $reason, $_SESSION['admin_name'] ?? 'Super Admin');
        $this->jsonSuccess(['assigned' => $res]);
    }

    private function handlePayrollView() {
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');
        $siteId = !empty($_GET['site_id']) ? (int)$_GET['site_id'] : null;

        $sites = SiteDirectory::getAllSites(true);
        $payrollData = $this->payroll->getConsolidatedPayroll($startDate, $endDate, $siteId);

        require __DIR__ . '/../views/payroll.php';
    }

    private function handleSaveCustomVal() {
        $empNo = trim($_POST['emp_no'] ?? '');
        $colId = (int)($_POST['column_id'] ?? 0);
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $numVal = isset($_POST['num_val']) ? (float)$_POST['num_val'] : null;
        $textVal = $_POST['text_val'] ?? null;

        $this->payroll->setCustomValue($empNo, $colId, $startDate, $endDate, $numVal, $textVal);
        $this->jsonSuccess();
    }

    private function handleSaveRemarks() {
        $empNo = trim($_POST['emp_no'] ?? '');
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $remarks = $_POST['remarks'] ?? '';

        $this->payroll->setStaffRemarks($empNo, $startDate, $endDate, $remarks);
        $this->jsonSuccess();
    }

    private function handleAddColumn() {
        $colName = trim($_POST['column_name'] ?? '');
        $colType = in_array($_POST['col_type'] ?? '', ['number', 'text']) ? $_POST['col_type'] : 'number';
        if (empty($colName)) {
            if ($this->isAjax()) {
                $this->jsonError('Column name is required.');
            } else {
                header("Location: index.php?action=payroll");
                exit;
            }
        }

        $colId = $this->payroll->addCustomColumn($colName, $colType);
        if ($this->isAjax()) {
            $this->jsonSuccess(['column_id' => $colId, 'col_name' => $colName, 'col_type' => $colType]);
        } else {
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            header("Location: index.php?action=payroll");
            exit;
        }
    }

    private function handleEditColumn() {
        $colId = (int)($_POST['column_id'] ?? 0);
        $colName = trim($_POST['column_name'] ?? '');
        $colType = in_array($_POST['col_type'] ?? '', ['number', 'text']) ? $_POST['col_type'] : 'number';
        if ($colId <= 0 || empty($colName)) {
            $this->jsonError('Valid column ID and name are required.');
        }

        $this->payroll->updateCustomColumn($colId, $colName, $colType);
        $this->jsonSuccess(['column_id' => $colId, 'col_name' => $colName, 'col_type' => $colType]);
    }

    private function handleDeleteColumn() {
        $colId = (int)($_POST['column_id'] ?? 0);
        if ($colId <= 0) {
            $this->jsonError('Valid column ID is required.');
        }
        $this->payroll->deleteCustomColumn($colId);
        $this->jsonSuccess();
    }

    private function handleExportExcel() {
        $type = $_GET['export_type'] ?? 'sheet';
        require_once __DIR__ . '/ExportEngine.php';
        $exporter = new ExportEngine($this->pdo);

        if ($type === 'payroll') {
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-t');
            $siteId = !empty($_GET['site_id']) ? (int)$_GET['site_id'] : null;
            $exporter->exportPayrollExcel($startDate, $endDate, $siteId);
        } else {
            $sheetId = (int)($_GET['sheet_id'] ?? 0);
            $exporter->exportSheetExcel($sheetId);
        }
    }

    private function handleExportPdf() {
        $sheetId = (int)($_GET['sheet_id'] ?? 0);
        require_once __DIR__ . '/ExportEngine.php';
        $exporter = new ExportEngine($this->pdo);
        $exporter->exportSheetPdf($sheetId);
    }

    private function handleBackupDownload() {
        require_once __DIR__ . '/BackupEngine.php';
        BackupEngine::downloadBackup($this->pdo);
    }

    private function handleBackupSnapshot() {
        require_once __DIR__ . '/BackupEngine.php';
        $res = BackupEngine::createSnapshot($this->pdo, 'manual');
        $this->jsonSuccess($res);
    }
}
