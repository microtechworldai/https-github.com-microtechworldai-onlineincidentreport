<?php
/**
 * Universal Security Services - Attendance Roster Subsystem
 * Native Excel Generator with True COUNTIF Formulas & Conditional Formatting
 * File: /attendance_roster/exports/ExcelExport.php
 */
require_once dirname(__DIR__) . '/config/roster_config.php';
require_once dirname(__DIR__) . '/src/RosterEntry.php';

class ExcelExport {
    public static function exportSheet(int $siteId, int $year, int $month) {
        $data = RosterEntry::getMonthlySheet($siteId, $year, $month);
        $site = $data['site'];
        $daysInMonth = $data['daysInMonth'];
        $staffList = $data['staffList'];
        $entries = $data['entries'];
        $monthName = strtoupper(date('F', mktime(0, 0, 0, $month, 1, $year)));

        $filename = "USS_Attendance_Roster_" . preg_replace('/[^A-Za-z0-9_]/', '_', $site['site_name']) . "_{$monthName}_{$year}.xls";

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
        echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Roster</x:Name><x:WorksheetOptions><x:FitToPage/><x:Print><x:FitWidth>1</x:FitWidth><x:FitHeight>0</x:FitHeight><x:ValidPrinterInfo/><x:Orientation>Landscape</x:Orientation></x:Print></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
        echo '<style>
            table { border-collapse: collapse; font-family: "Segoe UI", Arial, sans-serif; font-size: 11px; }
            th, td { border: 0.5pt solid #000000; text-align: center; vertical-align: middle; height: 20px; }
            .th-banner { background-color: #0f766e; color: #ffffff; font-size: 14px; font-weight: bold; height: 35px; }
            .th-sub { background-color: #f1f5f9; color: #0f172a; font-weight: bold; }
            .th-weekend { background-color: #fef3c7; color: #92400e; font-weight: bold; }
            .col-name { text-align: left; padding-left: 5px; font-weight: bold; }
            .tag-D { background-color: #d1fae5; color: #047857; font-weight: bold; }
            .tag-N { background-color: #dbeafe; color: #1d4ed8; font-weight: bold; }
            .tag-OFF { background-color: #fee2e2; color: #b91c1c; font-weight: bold; }
            .tag-S { background-color: #fef3c7; color: #b45309; font-weight: bold; }
            .tag-B { background-color: #ede9fe; color: #6d28d9; font-weight: bold; }
            .sum-req { background-color: #f8fafc; font-weight: bold; }
            .sum-act { background-color: #ffffff; font-weight: bold; }
            .sum-tot { background-color: #e5e7eb; font-weight: bold; }
            .lbl-summary { text-align: right; padding-right: 8px; font-weight: bold; background-color: #f8fafc; }
        </style></head><body>';

        echo '<table>';

        // 1. Executive Banner
        $totalCols = $daysInMonth + 3;
        echo '<tr>';
        echo '<th colspan="' . $totalCols . '" class="th-banner">';
        echo 'ULTIMATE SECURITY SERVICES &bull; ATTENDANCE ROSTER FOR ' . htmlspecialchars($site['site_name']) . ' &bull; ' . $monthName . ' ' . $year;
        echo '</th></tr>';

        // 2. Day Numbers (Row 2 in Excel)
        echo '<tr>';
        echo '<th rowspan="2" class="th-sub" style="width:70px;">Emp No</th>';
        echo '<th rowspan="2" class="th-sub" style="width:160px;">Guard Name</th>';
        echo '<th rowspan="2" class="th-sub" style="width:130px;">Site Name</th>';

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $w = strtoupper(substr(date('D', mktime(0, 0, 0, $month, $d, $year)), 0, 3));
            $isWk = in_array($w, ['FRI', 'SUN']);
            echo '<th class="' . ($isWk ? 'th-weekend' : 'th-sub') . '" style="width:32px;">' . $d . '</th>';
        }
        echo '</tr>';

        // 3. Weekday Names (Row 3 in Excel)
        echo '<tr>';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $w = strtoupper(substr(date('D', mktime(0, 0, 0, $month, $d, $year)), 0, 3));
            $isWk = in_array($w, ['FRI', 'SUN']);
            echo '<th class="' . ($isWk ? 'th-weekend' : 'th-sub') . '">' . $w . '</th>';
        }
        echo '</tr>';

        // 4. Staff Data Rows
        $startDataRow = 4;
        $currentRow = $startDataRow;

        foreach ($staffList as $st) {
            $sid = $st['id'];
            echo '<tr>';
            echo '<td>' . htmlspecialchars($st['emp_no']) . '</td>';
            echo '<td class="col-name">' . htmlspecialchars($st['name']) . '</td>';
            echo '<td class="col-name">' . htmlspecialchars($site['site_name']) . '</td>';

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $code = $entries[$sid][$d]['code'] ?? '';
                $cls = $code ? 'tag-' . $code : '';
                echo '<td class="' . $cls . '">' . htmlspecialchars($code) . '</td>';
            }
            echo '</tr>';
            $currentRow++;
        }

        $endDataRow = max($startDataRow, $currentRow - 1);

        // Helper to convert column number to Excel letter (D=4, E=5, etc.)
        $colLetter = function($colIdx) {
            $letter = '';
            while ($colIdx > 0) {
                $mod = ($colIdx - 1) % 26;
                $letter = chr(65 + $mod) . $letter;
                $colIdx = (int)(($colIdx - $mod) / 26);
            }
            return $letter;
        };

        // 5. REQUIRED DAY ROW
        echo '<tr>';
        echo '<td colspan="3" class="lbl-summary">REQUIRED DAY:</td>';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $reqD = $data['requirements'][$d]['required_day'];
            echo '<td class="sum-req">' . $reqD . '</td>';
        }
        echo '</tr>';
        $rowReqDay = $currentRow++;

        // 6. REQUIRED NIGHT ROW
        echo '<tr>';
        echo '<td colspan="3" class="lbl-summary">REQUIRED NIGHT:</td>';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $reqN = $data['requirements'][$d]['required_night'];
            echo '<td class="sum-req">' . $reqN . '</td>';
        }
        echo '</tr>';
        $rowReqNight = $currentRow++;

        // 7. ACTUAL DAY ROW with REAL =COUNTIF FORMULAS
        echo '<tr>';
        echo '<td colspan="3" class="lbl-summary">ACTUAL DAY SHIFT (D):</td>';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $colLet = $colLetter($d + 3);
            $formula = "=COUNTIF({$colLet}{$startDataRow}:{$colLet}{$endDataRow},\"D\")";
            echo '<td class="sum-act" x:fmla="' . $formula . '">' . $data['dailySummary'][$d]['act_day'] . '</td>';
        }
        echo '</tr>';
        $rowActDay = $currentRow++;

        // 8. ACTUAL NIGHT ROW with REAL =COUNTIF FORMULAS
        echo '<tr>';
        echo '<td colspan="3" class="lbl-summary">ACTUAL NIGHT SHIFT (N):</td>';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $colLet = $colLetter($d + 3);
            $formula = "=COUNTIF({$colLet}{$startDataRow}:{$colLet}{$endDataRow},\"N\")";
            echo '<td class="sum-act" x:fmla="' . $formula . '">' . $data['dailySummary'][$d]['act_night'] . '</td>';
        }
        echo '</tr>';
        $rowActNight = $currentRow++;

        // 9. TOTAL ON DUTY ROW with REAL FORMULA (=Day + Night)
        echo '<tr>';
        echo '<td colspan="3" class="lbl-summary" style="background-color:#e5e7eb;">TOTAL ON DUTY (D + N):</td>';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $colLet = $colLetter($d + 3);
            $formula = "={$colLet}{$rowActDay}+{$colLet}{$rowActNight}";
            echo '<td class="sum-tot" x:fmla="' . $formula . '">' . $data['dailySummary'][$d]['act_total'] . '</td>';
        }
        echo '</tr>';
        $rowTotalDuty = $currentRow++;

        // 10. VARIANCE ROW
        echo '<tr>';
        echo '<td colspan="3" class="lbl-summary">DEPLOYMENT VARIANCE:</td>';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $colLet = $colLetter($d + 3);
            $formula = "={$colLet}{$rowTotalDuty}-({$colLet}{$rowReqDay}+{$colLet}{$rowReqNight})";
            $varTot = ($data['dailySummary'][$d]['act_day'] - $data['requirements'][$d]['required_day']) + ($data['dailySummary'][$d]['act_night'] - $data['requirements'][$d]['required_night']);
            echo '<td class="sum-req" x:fmla="' . $formula . '">' . ($varTot > 0 ? "+$varTot" : $varTot) . '</td>';
        }
        echo '</tr>';

        echo '</table>';
        echo '</body></html>';
        exit;
    }
}
