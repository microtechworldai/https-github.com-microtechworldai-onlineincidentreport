<?php
// Attendance & Payroll Module - Clean OpenXML & HTML Spreadsheet/PDF Exporter
// File: hostinger_deployment/attendance/core/ExportEngine.php

if (!defined('ATT_ACCESS')) {
    die('Direct access not permitted.');
}

class ExportEngine {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function exportSheetExcel($sheetId) {
        $sheetData = AttendanceSheet::getSheetFullData($sheetId);
        if (!$sheetData) {
            die('Attendance sheet not found.');
        }

        $sheet = $sheetData['sheet'];
        $rows = $sheetData['rows'] ?? [];
        $daysInMonth = (int)$sheetData['daysInMonth'];
        $monthName = date('F', mktime(0, 0, 0, (int)$sheet['month'], 10));
        $year = (int)$sheet['year'];
        $siteName = $sheet['site_name'];

        $filename = "Attendance_Roster_" . preg_replace('/[^A-Za-z0-9_-]/', '_', $siteName) . "_{$monthName}_{$year}.xls";

        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Cache-Control: max-age=0");

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
        echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Attendance Roster</x:Name><x:WorksheetOptions><x:FitToPage/><x:Print><x:FitWidth>1</x:FitWidth><x:FitHeight>1</x:FitHeight><x:ValidPrinterInfo/><x:Orientation>Landscape</x:Orientation></x:Print></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
        echo '<style>
            table { border-collapse: collapse; font-family: "Franklin Gothic Demi Cond", "Franklin Gothic Demi", Arial, sans-serif; font-size: 12pt; border: 2px solid #000000; }
            th, td { border: 1px solid #000000; text-align: center; vertical-align: middle; height: 20pt; padding: 1px 2px; word-wrap: break-word; white-space: normal; }
            .header-banner { background-color: #005f73; color: #ffffff; font-weight: bold; font-size: 14pt; height: 35pt; }
            .sub-banner { background-color: #0a9396; color: #ffffff; font-weight: bold; font-size: 11pt; height: 20pt; }
            .col-weekend { background-color: #007788; color: #ffffff; }
            .staff-name { text-align: center; font-weight: bold; }
            .site-name { text-align: center; color: #005f73; font-weight: 600; }
            .total-row { background-color: #ee9b00; color: #000000; font-weight: bold; height: 20pt; }
            .grand-total { background-color: #ca6702; color: #ffffff; font-weight: bold; height: 20pt; }
            .month-total { background-color: #e2e8f0; font-weight: bold; font-size: 11pt; text-align: center; padding: 4px; height: 20pt; }
            .legend-row { font-size: 10pt; font-weight: bold; text-align: center; padding: 4px; }
            .sig-row { font-weight: bold; font-size: 10pt; height: 35pt; }
        </style></head><body>';

        echo '<table>';
        $totalHeaderCols = 4 + $daysInMonth;

        echo '<tr><th colspan="' . $totalHeaderCols . '" class="header-banner">Ultimate Security Services Actual Attendance for the month of ' . $monthName . ' - ' . $year . '</th></tr>';
        echo '<tr style="height:10pt;"><th colspan="' . $totalHeaderCols . '" style="background-color:#ffffff;border:1px solid #000000;height:10pt;"></th></tr>';

        echo '<tr>';
        echo '<th rowspan="2" class="sub-banner">NO</th>';
        echo '<th rowspan="2" class="sub-banner">Emp No</th>';
        echo '<th rowspan="2" class="sub-banner">Name</th>';
        echo '<th rowspan="2" class="sub-banner">Site Name</th>';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $wName = strtoupper(substr(date('D', mktime(0, 0, 0, (int)$sheet['month'], $d, $year)), 0, 3));
            $isWk = in_array($wName, ['FRI', 'SUN']);
            $cls = $isWk ? ' class="col-weekend"' : ' class="sub-banner"';
            echo "<th$cls>$d</th>";
        }
        echo '</tr>';

        echo '<tr>';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $wName = strtoupper(substr(date('D', mktime(0, 0, 0, (int)$sheet['month'], $d, $year)), 0, 3));
            $isWk = in_array($wName, ['FRI', 'SUN']);
            $cls = $isWk ? ' class="col-weekend"' : ' class="sub-banner"';
            echo "<th$cls>$wName</th>";
        }
        echo '</tr>';

        $startExcelRow = 4;
        $rowIdx = 1;
        $curExcelRow = $startExcelRow;

        foreach ($rows as $r) {
            $displayName = $r['emp_name_snapshot'] ?? $r['staff_name'] ?? $r['emp_name'] ?? '';
            $cellVals = $r['days'] ?? $r['cells'] ?? [];

            echo '<tr style="height:20pt;">';
            echo "<td>$rowIdx</td>";
            echo "<td style=\"font-weight:bold;\">" . htmlspecialchars($r['emp_no'] ?? '') . "</td>";
            echo "<td class=\"staff-name\">" . htmlspecialchars($displayName) . "</td>";
            echo "<td class=\"site-name\">" . htmlspecialchars($siteName) . "</td>";

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $v = $cellVals[$d] ?? '';
                $style = ($v === 'OFF') ? ' style="color:#c00000;font-weight:bold;"' : '';
                echo "<td$style>" . htmlspecialchars($v) . "</td>";
            }
            echo '</tr>';
            $rowIdx++;
            $curExcelRow++;
        }

        $lastDataRow = $curExcelRow - 1;

        if ($lastDataRow >= $startExcelRow) {
            $firstDayCol = 'E';
            $lastDayCol = $this->getExcelColumnLetter(4 + $daysInMonth);

            echo '<tr class="total-row" style="height:20pt;">';
            echo '<td colspan="4" style="text-align:center;">DAY SHIFT (D):</td>';
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $colLet = $this->getExcelColumnLetter(4 + $d);
                echo '<td>=COUNTIF(' . $colLet . $startExcelRow . ':' . $colLet . $lastDataRow . ',"D")</td>';
            }
            echo '</tr>';

            echo '<tr class="total-row" style="height:20pt;">';
            echo '<td colspan="4" style="text-align:center;">NIGHT SHIFT (N):</td>';
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $colLet = $this->getExcelColumnLetter(4 + $d);
                echo '<td>=COUNTIF(' . $colLet . $startExcelRow . ':' . $colLet . $lastDataRow . ',"N")</td>';
            }
            echo '</tr>';

            $dayShiftRow = $lastDataRow + 1;
            $nightShiftRow = $lastDataRow + 2;
            $totalDutyRow = $lastDataRow + 3;

            echo '<tr class="grand-total" style="height:20pt;">';
            echo '<td colspan="4" style="text-align:center;">TOTAL ON DUTY (D + N):</td>';
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $colLet = $this->getExcelColumnLetter(4 + $d);
                echo '<td>=' . $colLet . $dayShiftRow . '+' . $colLet . $nightShiftRow . '</td>';
            }
            echo '</tr>';

            echo '<tr style="height:20pt;"><td colspan="' . $totalHeaderCols . '" class="month-total">';
            echo 'Month Total Day Shifts: <b>=SUM(' . $firstDayCol . $dayShiftRow . ':' . $lastDayCol . $dayShiftRow . ')</b> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ';
            echo 'Month Total Night Shifts: <b>=SUM(' . $firstDayCol . $nightShiftRow . ':' . $lastDayCol . $nightShiftRow . ')</b> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ';
            echo 'Month Total Duties: <b>=SUM(' . $firstDayCol . $totalDutyRow . ':' . $lastDayCol . $totalDutyRow . ')</b>';
            echo '</td></tr>';
        }

        echo '<tr><td colspan="' . $totalHeaderCols . '" class="legend-row">';
        echo 'Legend: D = Day Shift &nbsp;|&nbsp; N = Night Shift &nbsp;|&nbsp; OFF = Day Off &nbsp;|&nbsp; S = Sick Leave &nbsp;|&nbsp; B = Basic';
        echo '</td></tr>';

        echo '<tr class="sig-row"><td colspan="' . $totalHeaderCols . '" style="text-align:left;vertical-align:bottom;padding:15px 5px 5px 5px;">';
        echo 'Prepared by: ________________________ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ';
        echo 'Checked by: ________________________ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ';
        echo 'Approved by: ________________________';
        echo '</td></tr>';

        echo '</table></body></html>';
        exit;
    }

    public function exportSheetPdf($sheetId) {
        $sheetData = AttendanceSheet::getSheetFullData($sheetId);
        if (!$sheetData) {
            die('Sheet not found.');
        }

        $sheet = $sheetData['sheet'];
        $rows = $sheetData['rows'] ?? [];
        $daysInMonth = (int)$sheetData['daysInMonth'];
        $monthName = date('F', mktime(0, 0, 0, (int)$sheet['month'], 10));
        $year = (int)$sheet['year'];
        $siteName = $sheet['site_name'];

        $dayShiftCounts = $sheetData['dayShiftCounts'] ?? array_fill(1, $daysInMonth, 0);
        $nightShiftCounts = $sheetData['nightShiftCounts'] ?? array_fill(1, $daysInMonth, 0);
        $totalDutyCounts = $sheetData['totalDutyCounts'] ?? array_fill(1, $daysInMonth, 0);

        $monthTotalDayShifts = array_sum($dayShiftCounts);
        $monthTotalNightShifts = array_sum($nightShiftCounts);
        $monthTotalDuties = $monthTotalDayShifts + $monthTotalNightShifts;

        echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
        echo "<title>Ultimate Security Services Actual Attendance for the month of {$monthName} - {$year}</title>";
        echo '<style>
            @page { size: A4 landscape; margin: 5mm 5mm; }
            body { font-family: "Franklin Gothic Demi Cond", "Franklin Gothic Demi", Arial, sans-serif; font-size: 8pt; color: #000; margin: 0; padding: 0; }
            table { width: 100%; border-collapse: collapse; border: 2px solid #000; table-layout: fixed; }
            th, td { border: 1px solid #000; text-align: center; vertical-align: middle; height: 16px; padding: 1px; font-size: 7.5pt; overflow: hidden; white-space: normal; word-wrap: break-word; }
            .header-banner { background-color: #005f73 !important; color: #fff !important; font-size: 11pt !important; font-weight: bold; height: 24px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .sub-banner { background-color: #0a9396 !important; color: #fff !important; font-weight: bold; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .col-weekend { background-color: #007788 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .staff-name { text-align: center; font-weight: bold; }
            .site-name { text-align: center; color: #005f73; }
            .code-OFF { color: #c00000; font-size: 6.5pt; font-weight: 900; }
            .total-row { background-color: #ee9b00 !important; font-weight: bold; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .grand-total { background-color: #ca6702 !important; color: #fff !important; font-weight: bold; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .month-total { margin-top: 5px; padding: 4px; background: #e2e8f0; border: 1px solid #000; font-size: 8pt; font-weight: bold; display: flex; justify-content: space-between; }
            .legend-row { margin-top: 5px; font-size: 7.5pt; font-weight: bold; }
            .sig-row { margin-top: 15px; font-size: 8pt; font-weight: bold; display: flex; justify-content: space-between; }
            .sig-line { display: inline-block; width: 140px; border-bottom: 1px solid #000; }
            @media print {
                .no-print { display: none !important; }
            }
        </style></head><body onload="window.print()">';

        echo '<div class="no-print" style="padding:8px;background:#f8f9fa;border-bottom:1px solid #ddd;margin-bottom:8px;">
            <button onclick="window.print()" style="padding:6px 16px;background:#005f73;color:#fff;border:none;border-radius:4px;cursor:pointer;font-weight:bold;">Print / Save as PDF</button>
            <span style="margin-left:12px;color:#666;">Fits exactly on 1 A4 Landscape page.</span>
        </div>';

        echo '<table>';
        $totalHeaderCols = 4 + $daysInMonth;

        echo '<tr><th colspan="' . $totalHeaderCols . '" class="header-banner">Ultimate Security Services Actual Attendance for the month of ' . $monthName . ' - ' . $year . '</th></tr>';
        echo '<tr style="height:6px;"><th colspan="' . $totalHeaderCols . '" style="background-color:#ffffff;border:1px solid #000000;height:6px;padding:0;"></th></tr>';

        echo '<tr>';
        echo '<th style="width:22px;" rowspan="2" class="sub-banner">NO</th>';
        echo '<th style="width:58px;" rowspan="2" class="sub-banner">Emp No</th>';
        echo '<th style="width:130px;" rowspan="2" class="sub-banner">Name</th>';
        echo '<th style="width:100px;" rowspan="2" class="sub-banner">Site Name</th>';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $wName = strtoupper(substr(date('D', mktime(0, 0, 0, (int)$sheet['month'], $d, $year)), 0, 3));
            $isWk = in_array($wName, ['FRI', 'SUN']);
            $cls = $isWk ? ' class="col-weekend"' : ' class="sub-banner"';
            echo "<th$cls>$d</th>";
        }
        echo '</tr>';

        echo '<tr>';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $wName = strtoupper(substr(date('D', mktime(0, 0, 0, (int)$sheet['month'], $d, $year)), 0, 3));
            $isWk = in_array($wName, ['FRI', 'SUN']);
            $cls = $isWk ? ' class="col-weekend"' : ' class="sub-banner"';
            echo "<th$cls>$wName</th>";
        }
        echo '</tr>';

        $idx = 1;
        foreach ($rows as $r) {
            $displayName = $r['emp_name_snapshot'] ?? $r['staff_name'] ?? $r['emp_name'] ?? '';
            $cellVals = $r['days'] ?? $r['cells'] ?? [];

            echo '<tr>';
            echo "<td>$idx</td>";
            echo "<td style=\"font-weight:bold;\">" . htmlspecialchars($r['emp_no'] ?? '') . "</td>";
            echo "<td class=\"staff-name\">" . htmlspecialchars($displayName) . "</td>";
            echo "<td class=\"site-name\">" . htmlspecialchars($siteName) . "</td>";

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $v = $cellVals[$d] ?? '';
                $cls = ($v === 'OFF') ? ' class="code-OFF"' : '';
                echo "<td$cls>" . htmlspecialchars($v) . "</td>";
            }
            echo '</tr>';
            $idx++;
        }

        echo '<tr class="total-row">';
        echo '<td colspan="4" style="text-align:center;">DAY SHIFT (D):</td>';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            echo '<td>' . ($dayShiftCounts[$d] ?: '0') . '</td>';
        }
        echo '</tr>';

        echo '<tr class="total-row">';
        echo '<td colspan="4" style="text-align:center;">NIGHT SHIFT (N):</td>';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            echo '<td>' . ($nightShiftCounts[$d] ?: '0') . '</td>';
        }
        echo '</tr>';

        echo '<tr class="grand-total">';
        echo '<td colspan="4" style="text-align:center;">TOTAL ON DUTY (D + N):</td>';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            echo '<td>' . ($totalDutyCounts[$d] ?: '0') . '</td>';
        }
        echo '</tr>';

        echo '</table>';

        echo '<div class="month-total">';
        echo "<span>Month Total Day Shifts: <b>$monthTotalDayShifts</b></span>";
        echo "<span>Month Total Night Shifts: <b>$monthTotalNightShifts</b></span>";
        echo "<span>Month Total Duties: <b>$monthTotalDuties</b></span>";
        echo '</div>';

        echo '<div class="legend-row">Legend: D = Day Shift &nbsp;|&nbsp; N = Night Shift &nbsp;|&nbsp; OFF = Day Off &nbsp;|&nbsp; S = Sick Leave &nbsp;|&nbsp; B = Basic</div>';

        echo '<div class="sig-row">';
        echo '<div>Prepared by: <span class="sig-line"></span></div>';
        echo '<div>Checked by: <span class="sig-line"></span></div>';
        echo '<div>Approved by: <span class="sig-line"></span></div>';
        echo '</div>';

        echo '</body></html>';
        exit;
    }

    /**
     * Excel Export for Payroll:
     * - Teal Title and Header
     * - Thin black borders on every cell
     * - Columns: Emp No, Emp Name, Site Name, [Dates 1..N with Weekday], Day, Night, Basic, Sick, Off, Total No. of Days, Remarks
     * - Real formulas for Day, Night, Basic, Sick, Off, and Total No. of Days (=Day+Night+Basic+Sick)
     * - Set to fit on 1 A4 Landscape page
     */
    public function exportPayrollExcel($startDate, $endDate, $siteId = null) {
        $payroll = new PayrollEngine($this->pdo);
        $data = $payroll->getConsolidatedPayroll($startDate, $endDate, $siteId);

        $filename = "Payroll_Attendance_{$startDate}_to_{$endDate}.xls";
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Cache-Control: max-age=0");

        $startFormatted = strtoupper(date('j M Y', strtotime($startDate)));
        $endFormatted = strtoupper(date('j M Y', strtotime($endDate)));
        $datesList = $data['dates_list'];
        $daysCount = count($datesList);

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
        echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Payroll Attendance</x:Name><x:WorksheetOptions><x:FitToPage/><x:Print><x:FitWidth>1</x:FitWidth><x:FitHeight>1</x:FitHeight><x:ValidPrinterInfo/><x:Orientation>Landscape</x:Orientation></x:Print></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
        echo '<style>
            table { border-collapse: collapse; font-family: "Franklin Gothic Demi Cond", "Franklin Gothic Demi", Arial, sans-serif; font-size: 12pt; }
            th, td { border: 1px solid #000000; text-align: center; vertical-align: middle; height: 20pt; padding: 1px 2px; word-wrap: break-word; white-space: normal; }
            .header-banner { background-color: #005f73; color: #ffffff; font-weight: bold; font-size: 14pt; height: 35pt; }
            .teal-header { background-color: #0a9396; color: #ffffff; font-weight: bold; font-size: 11pt; }
            .staff-name { text-align: center; font-weight: bold; }
            .off-cell { color: #c00000; font-weight: bold; }
            .total-row { background-color: #ee9b00; font-weight: bold; }
            .grand-total { background-color: #ca6702; color: #ffffff; font-weight: bold; }
        </style></head><body>';

        echo '<table>';
        $customCols = $data['custom_columns'] ?? [];
        $customColCount = count($customCols);
        $totalHeaderCols = 2 + $daysCount + 6 + $customColCount + 1; // 2 info cols (Emp No, Emp Name) + dates + 6 count cols + custom cols + 1 remarks

        echo '<tr><th colspan="' . $totalHeaderCols . '" class="header-banner">ULTIMATE SECURITY SERVICES PAYROLL ATTENDANCE FROM ' . $startFormatted . ' TO ' . $endFormatted . '</th></tr>';
        echo '<tr style="height:10pt;"><th colspan="' . $totalHeaderCols . '" style="background-color:#ffffff;border:1px solid #000000;height:10pt;"></th></tr>';

        // Header Row 1
        echo '<tr>';
        echo '<th rowspan="2" class="teal-header">Emp No</th>';
        echo '<th rowspan="2" class="teal-header">Emp Name</th>';
        foreach ($datesList as $dInfo) {
            echo '<th class="teal-header">' . $dInfo['day'] . '</th>';
        }
        echo '<th rowspan="2" class="teal-header">Day</th>';
        echo '<th rowspan="2" class="teal-header">Night</th>';
        echo '<th rowspan="2" class="teal-header">Basic</th>';
        echo '<th rowspan="2" class="teal-header">Sick</th>';
        echo '<th rowspan="2" class="teal-header">Off</th>';
        echo '<th rowspan="2" class="teal-header">Total No. of Days</th>';
        foreach ($customCols as $cc) {
            echo '<th rowspan="2" class="teal-header">' . htmlspecialchars($cc['column_name']) . '</th>';
        }
        echo '<th rowspan="2" class="teal-header">Remarks</th>';
        echo '</tr>';

        // Header Row 2 (Weekday Names)
        echo '<tr>';
        foreach ($datesList as $dInfo) {
            echo '<th class="teal-header">' . $dInfo['weekday'] . '</th>';
        }
        echo '</tr>';

        $startRow = 4;
        $curRow = $startRow;

        foreach ($data['staff'] as $st) {
            echo '<tr style="height:20pt;">';
            echo "<td style=\"font-weight:bold;\">" . htmlspecialchars($st['emp_no'] ?? '') . "</td>";
            echo "<td class=\"staff-name\">" . htmlspecialchars(strtoupper($st['staff_name'])) . "</td>";

            foreach ($datesList as $dInfo) {
                $code = $st['daily_codes'][$dInfo['date_str']] ?? '';
                $style = ($code === 'OFF') ? ' class="off-cell"' : '';
                echo "<td$style>" . htmlspecialchars($code) . "</td>";
            }

            $firstDayCol = 'C';
            $lastDayCol = $this->getExcelColumnLetter(2 + $daysCount);

            $dayCol = $this->getExcelColumnLetter(3 + $daysCount);
            $nightCol = $this->getExcelColumnLetter(4 + $daysCount);
            $basicCol = $this->getExcelColumnLetter(5 + $daysCount);
            $sickCol = $this->getExcelColumnLetter(6 + $daysCount);
            $offCol = $this->getExcelColumnLetter(7 + $daysCount);
            $totalDaysCol = $this->getExcelColumnLetter(8 + $daysCount);

            // Real Excel formulas for Day, Night, Basic, Sick, Off
            echo "<td>=COUNTIF($firstDayCol$curRow:$lastDayCol$curRow,\"D\")</td>";
            echo "<td>=COUNTIF($firstDayCol$curRow:$lastDayCol$curRow,\"N\")</td>";
            echo "<td>=COUNTIF($firstDayCol$curRow:$lastDayCol$curRow,\"B\")</td>";
            echo "<td>=COUNTIF($firstDayCol$curRow:$lastDayCol$curRow,\"S\")</td>";
            echo "<td>=COUNTIF($firstDayCol$curRow:$lastDayCol$curRow,\"OFF\")</td>";
            // Total No. of Days = Day + Night + Basic + Sick
            echo "<td style=\"font-weight:bold;\">=SUM($dayCol$curRow:$sickCol$curRow)</td>";

            // Custom columns
            foreach ($customCols as $cc) {
                $cVal = $st['custom_values'][$cc['id']] ?? null;
                if ($cc['col_type'] === 'number') {
                    $num = ($cVal !== null && isset($cVal['num_val']) && $cVal['num_val'] !== null && $cVal['num_val'] !== '') ? (float)$cVal['num_val'] : '';
                    echo "<td>" . ($num !== '' ? $num : 0) . "</td>";
                } else {
                    $txt = ($cVal !== null && isset($cVal['text_val'])) ? $cVal['text_val'] : '';
                    echo "<td>" . htmlspecialchars($txt) . "</td>";
                }
            }

            echo "<td>" . htmlspecialchars($st['remarks'] ?? '') . "</td>";
            echo '</tr>';
            $curRow++;
        }

        $lastRow = $curRow - 1;
        if ($lastRow >= $startRow) {
            $dayCol = $this->getExcelColumnLetter(3 + $daysCount);
            $nightCol = $this->getExcelColumnLetter(4 + $daysCount);
            $basicCol = $this->getExcelColumnLetter(5 + $daysCount);
            $sickCol = $this->getExcelColumnLetter(6 + $daysCount);
            $offCol = $this->getExcelColumnLetter(7 + $daysCount);
            $totalDaysCol = $this->getExcelColumnLetter(8 + $daysCount);

            echo '<tr class="total-row" style="height:20pt;">';
            echo '<td colspan="' . (2 + $daysCount) . '" style="text-align:center;font-weight:bold;">TOTAL</td>';
            echo "<td>=SUM($dayCol$startRow:$dayCol$lastRow)</td>";
            echo "<td>=SUM($nightCol$startRow:$nightCol$lastRow)</td>";
            echo "<td>=SUM($basicCol$startRow:$basicCol$lastRow)</td>";
            echo "<td>=SUM($sickCol$startRow:$sickCol$lastRow)</td>";
            echo "<td>=SUM($offCol$startRow:$offCol$lastRow)</td>";
            echo "<td style=\"font-weight:bold;\">=SUM($totalDaysCol$startRow:$totalDaysCol$lastRow)</td>";

            // Totals for custom columns: SUM for number columns, blank for text
            $curColIdx = 9 + $daysCount;
            foreach ($customCols as $cc) {
                $colLet = $this->getExcelColumnLetter($curColIdx);
                if ($cc['col_type'] === 'number') {
                    echo "<td style=\"font-weight:bold;\">=SUM($colLet$startRow:$colLet$lastRow)</td>";
                } else {
                    echo "<td></td>";
                }
                $curColIdx++;
            }

            echo '<td></td>';
            echo '</tr>';
        }

        echo '</table></body></html>';
        exit;
    }

    private function getExcelColumnLetter($colNumber) {
        $dividend = $colNumber;
        $columnName = '';
        while ($dividend > 0) {
            $modulo = ($dividend - 1) % 26;
            $columnName = chr(65 + $modulo) . $columnName;
            $dividend = (int)(($dividend - $modulo) / 26);
        }
        return $columnName;
    }
}
