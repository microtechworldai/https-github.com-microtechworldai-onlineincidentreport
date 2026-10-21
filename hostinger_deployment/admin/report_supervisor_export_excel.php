<?php
/**
 * Multi-Sheet Supervisor Audit Excel Export
 * Format: Microsoft XML Spreadsheet 2003 (SpreadsheetML)
 * Multi-Tab Workbook:
 *   1. Daily Visits
 *   2. Monthly Summary
 *   3. Site Summary
 *   4. Planned vs Actual
 *   5. Flags & Corrections
 * Ultimate Security Services (USS)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$db = get_db();
$settings = get_system_settings();

// Date range filters
$monthParam = trim($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthParam = date('Y-m');
}

$year = (int)substr($monthParam, 0, 4);
$month = (int)substr($monthParam, 5, 2);
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$monthLabel = date('F Y', strtotime("{$monthParam}-01"));

$fromDate = trim($_GET['from_date'] ?? "{$monthParam}-01");
$toDate = trim($_GET['to_date'] ?? "{$monthParam}-{$daysInMonth}");

$startDt = "{$fromDate} 00:00:00";
$endDt = "{$toDate} 23:59:59";

// Helper XML entity escape
function xmlEscape($str) {
    if ($str === null) return '';
    return htmlspecialchars((string)$str, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// 1. Fetch Visits
$visitsStmt = $db->prepare("
    SELECT v.*,
           s.name AS site_name, s.code AS site_code,
           g.full_name AS supervisor_name, g.staff_id AS supervisor_staff_id,
           d.duty_ref, d.shift_type,
           c.camp_name
    FROM supervisor_visits v
    JOIN sites s ON s.id = v.site_id
    JOIN guards g ON g.id = v.supervisor_id
    JOIN supervisor_duties d ON d.id = v.duty_id
    JOIN camps c ON c.id = d.camp_id
    WHERE v.time_in >= ? AND v.time_in <= ?
    ORDER BY v.time_in ASC
");
$visitsStmt->execute([$startDt, $endDt]);
$visits = $visitsStmt->fetchAll();

// 2. Fetch Monthly Summary grouped by Supervisor
$supSumStmt = $db->prepare("
    SELECT g.staff_id, g.full_name,
           COUNT(DISTINCT d.id) AS total_shifts,
           SUM(CASE WHEN d.shift_type = 'Day' THEN 1 ELSE 0 END) AS day_shifts,
           SUM(CASE WHEN d.shift_type = 'Night' THEN 1 ELSE 0 END) AS night_shifts,
           COUNT(v.id) AS total_visits,
           COALESCE(SUM(v.minutes_spent), 0) AS total_minutes,
           ROUND(COALESCE(AVG(v.minutes_spent), 0), 1) AS avg_minutes_per_visit
    FROM guards g
    JOIN supervisor_duties d ON d.supervisor_id = g.id AND d.duty_date >= ? AND d.duty_date <= ?
    LEFT JOIN supervisor_visits v ON v.duty_id = d.id AND v.status = 'Completed'
    WHERE g.is_supervisor = 1
    GROUP BY g.id, g.staff_id, g.full_name
    ORDER BY total_visits DESC
");
$supSumStmt->execute([$fromDate, $toDate]);
$supervisorSummary = $supSumStmt->fetchAll();

// 3. Fetch Site Summary
$siteSumStmt = $db->prepare("
    SELECT s.code AS site_code, s.name AS site_name,
           s.min_visits_per_week, s.min_night_visits_per_week,
           COUNT(v.id) AS total_visits,
           SUM(CASE WHEN d.shift_type = 'Night' OR HOUR(v.time_in) >= 20 OR HOUR(v.time_in) < 6 THEN 1 ELSE 0 END) AS night_visits,
           COALESCE(SUM(v.minutes_spent), 0) AS total_minutes,
           ROUND(COALESCE(AVG(v.minutes_spent), 0), 1) AS avg_minutes
    FROM sites s
    LEFT JOIN supervisor_visits v ON v.site_id = s.id AND v.time_in >= ? AND v.time_in <= ? AND v.status = 'Completed'
    LEFT JOIN supervisor_duties d ON d.id = v.duty_id
    GROUP BY s.id, s.code, s.name, s.min_visits_per_week, s.min_night_visits_per_week
    ORDER BY s.name ASC
");
$siteSumStmt->execute([$startDt, $endDt]);
$siteSummary = $siteSumStmt->fetchAll();

// 4. Fetch Planned vs Actual Schedules
$schedStmt = $db->prepare("
    SELECT sch.*,
           s.name AS site_name, s.code AS site_code,
           g.full_name AS supervisor_name, g.staff_id AS supervisor_staff_id,
           v.id AS actual_visit_id, v.time_in AS actual_time_in, v.time_out AS actual_time_out,
           v.minutes_spent AS actual_minutes_spent
    FROM supervisor_schedules sch
    JOIN sites s ON s.id = sch.site_id
    JOIN guards g ON g.id = sch.supervisor_id
    LEFT JOIN supervisor_visits v ON v.supervisor_id = sch.supervisor_id 
                                 AND v.site_id = sch.site_id 
                                 AND (
                                     (sch.specific_date IS NOT NULL AND DATE(v.time_in) = sch.specific_date)
                                     OR (sch.specific_date IS NULL AND sch.day_of_week IS NOT NULL AND DAYOFWEEK(v.time_in) = sch.day_of_week)
                                 )
                                 AND v.status = 'Completed'
    WHERE (sch.specific_date IS NOT NULL AND sch.specific_date >= ? AND sch.specific_date <= ?)
       OR (sch.specific_date IS NULL AND sch.is_active = 1)
    ORDER BY sch.expected_time_start ASC
");
$schedStmt->execute([$fromDate, $toDate]);
$plannedSchedules = $schedStmt->fetchAll();

// 5. Fetch Flags and Corrections
$alertsStmt = $db->prepare("
    SELECT a.*, 
           g.full_name AS supervisor_name, g.staff_id AS supervisor_staff_id,
           s.name AS site_name, s.code AS site_code
    FROM supervisor_alerts a
    LEFT JOIN guards g ON g.id = a.supervisor_id
    LEFT JOIN sites s ON s.id = a.site_id
    WHERE a.created_at >= ? AND a.created_at <= ?
    ORDER BY a.created_at DESC
");
$alertsStmt->execute([$startDt, $endDt]);
$alerts = $alertsStmt->fetchAll();

$correctionsStmt = $db->prepare("
    SELECT v.visit_ref, v.time_in, v.manual_timeout_time, v.manual_timeout_reason,
           v.manual_timeout_status, v.correction_notes, v.minutes_spent,
           g.full_name AS supervisor_name, g.staff_id AS supervisor_staff_id,
           s.name AS site_name, s.code AS site_code,
           adm.username AS approved_by_admin
    FROM supervisor_visits v
    JOIN guards g ON g.id = v.supervisor_id
    JOIN sites s ON s.id = v.site_id
    LEFT JOIN admins adm ON adm.id = v.corrected_by_admin_id
    WHERE v.manual_timeout_requested = 1
      AND v.time_in >= ? AND v.time_in <= ?
    ORDER BY v.time_in DESC
");
$correctionsStmt->execute([$startDt, $endDt]);
$corrections = $correctionsStmt->fetchAll();

// Prepare Download Headers
$filename = "USS_Supervisor_Audit_" . date('Ymd_His') . ".xls";
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <Author><?= xmlEscape($settings['company_name']) ?></Author>
  <Created><?= date('Y-m-d\TH:i:s\Z') ?></Created>
  <Company><?= xmlEscape($settings['company_name']) ?></Company>
 </DocumentProperties>
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Center"/>
   <Borders/>
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="10" ss:Color="#1E293B"/>
   <Interior/>
   <NumberFormat/>
   <Protection/>
  </Style>
  <!-- Main Header Title -->
  <Style ss:ID="HeaderTitle">
   <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="14" ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#0F172A" ss:Pattern="Solid"/>
  </Style>
  <!-- Table Header Columns -->
  <Style ss:ID="ColHeader">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#334155"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#334155"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#334155"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#334155"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#0284C7" ss:Pattern="Solid"/>
  </Style>
  <!-- Table Row Regular -->
  <Style ss:ID="RowRegular">
   <Alignment ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
   </Borders>
  </Style>
  <!-- Table Row Center -->
  <Style ss:ID="RowCenter">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
   </Borders>
  </Style>
  <!-- Table Row Number -->
  <Style ss:ID="RowNumber">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
   </Borders>
   <NumberFormat ss:Format="#,##0"/>
  </Style>
  <!-- Rating OK -->
  <Style ss:ID="RatingOK">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:Bold="1" ss:Color="#15803D"/>
   <Interior ss:Color="#DCFCE7" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
   </Borders>
  </Style>
  <!-- Rating Issue -->
  <Style ss:ID="RatingIssue">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:Bold="1" ss:Color="#B91C1C"/>
   <Interior ss:Color="#FEE2E2" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
   </Borders>
  </Style>
 </Styles>

 <!-- ==================== SHEET 1: DAILY VISITS ==================== -->
 <Worksheet ss:Name="Daily Visits">
  <Table ss:DefaultColumnWidth="90" ss:DefaultRowHeight="20">
   <Column ss:Width="70"/>
   <Column ss:Width="90"/>
   <Column ss:Width="50"/>
   <Column ss:Width="80"/>
   <Column ss:Width="130"/>
   <Column ss:Width="110"/>
   <Column ss:Width="70"/>
   <Column ss:Width="140"/>
   <Column ss:Width="60"/>
   <Column ss:Width="60"/>
   <Column ss:Width="60"/>
   <Column ss:Width="60"/>
   <Column ss:Width="55"/>
   <Column ss:Width="55"/>
   <Column ss:Width="55"/>
   <Column ss:Width="55"/>
   <Column ss:Width="90"/>
   <Column ss:Width="50"/>
   <Column ss:Width="75"/>
   <Column ss:Width="200"/>
   <Column ss:Width="180"/>

   <Row ss:Height="30">
    <Cell ss:MergeAcross="20" ss:StyleID="HeaderTitle">
     <Data ss:Type="String"> <?= xmlEscape($settings['company_name']) ?> - Daily Supervisor Patrol &amp; Inspection Visits (<?= $fromDate ?> to <?= $toDate ?>)</Data>
    </Cell>
   </Row>

   <Row ss:Height="24">
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Date</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Visit Ref</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Shift</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Sup Staff ID</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Supervisor Name</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Base Camp</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Site Code</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Site Name</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Time In</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Time Out</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Mins Spent</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Travel Mins</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Turnout</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Records</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Safety</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Equip</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Purpose</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Guards</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Attendance</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Remarks</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Issues / Action Taken</Data></Cell>
   </Row>

   <?php foreach ($visits as $v): 
       $tStyle = ($v['rating_turnout'] === 'Issue') ? 'RatingIssue' : (($v['rating_turnout'] === 'OK') ? 'RatingOK' : 'RowCenter');
       $rStyle = ($v['rating_records'] === 'Issue') ? 'RatingIssue' : (($v['rating_records'] === 'OK') ? 'RatingOK' : 'RowCenter');
       $cStyle = ($v['rating_condition'] === 'Issue') ? 'RatingIssue' : (($v['rating_condition'] === 'OK') ? 'RatingOK' : 'RowCenter');
       $eStyle = ($v['rating_equipment'] === 'Issue') ? 'RatingIssue' : (($v['rating_equipment'] === 'OK') ? 'RatingOK' : 'RowCenter');
   ?>
   <Row ss:Height="19">
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= date('Y-m-d', strtotime($v['time_in'])) ?></Data></Cell>
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= xmlEscape($v['visit_ref']) ?></Data></Cell>
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= xmlEscape($v['shift_type']) ?></Data></Cell>
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= xmlEscape($v['supervisor_staff_id']) ?></Data></Cell>
    <Cell ss:StyleID="RowRegular"><Data ss:Type="String"><?= xmlEscape($v['supervisor_name']) ?></Data></Cell>
    <Cell ss:StyleID="RowRegular"><Data ss:Type="String"><?= xmlEscape($v['camp_name']) ?></Data></Cell>
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= xmlEscape($v['site_code']) ?></Data></Cell>
    <Cell ss:StyleID="RowRegular"><Data ss:Type="String"><?= xmlEscape($v['site_name']) ?></Data></Cell>
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= date('H:i', strtotime($v['time_in'])) ?></Data></Cell>
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= !empty($v['time_out']) ? date('H:i', strtotime($v['time_out'])) : '--:--' ?></Data></Cell>
    <Cell ss:StyleID="RowNumber"><Data ss:Type="Number"><?= (int)$v['minutes_spent'] ?></Data></Cell>
    <Cell ss:StyleID="RowNumber"><Data ss:Type="Number"><?= (int)$v['travel_minutes_from_previous'] ?></Data></Cell>
    <Cell ss:StyleID="<?= $tStyle ?>"><Data ss:Type="String"><?= xmlEscape($v['rating_turnout']) ?></Data></Cell>
    <Cell ss:StyleID="<?= $rStyle ?>"><Data ss:Type="String"><?= xmlEscape($v['rating_records']) ?></Data></Cell>
    <Cell ss:StyleID="<?= $cStyle ?>"><Data ss:Type="String"><?= xmlEscape($v['rating_condition']) ?></Data></Cell>
    <Cell ss:StyleID="<?= $eStyle ?>"><Data ss:Type="String"><?= xmlEscape($v['rating_equipment']) ?></Data></Cell>
    <Cell ss:StyleID="RowRegular"><Data ss:Type="String"><?= xmlEscape($v['purpose_of_visit']) ?></Data></Cell>
    <Cell ss:StyleID="RowNumber"><Data ss:Type="Number"><?= (int)$v['guards_count'] ?></Data></Cell>
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= xmlEscape($v['guards_attendance']) ?></Data></Cell>
    <Cell ss:StyleID="RowRegular"><Data ss:Type="String"><?= xmlEscape($v['remarks']) ?></Data></Cell>
    <Cell ss:StyleID="RowRegular"><Data ss:Type="String"><?= xmlEscape($v['issues_action_taken']) ?></Data></Cell>
   </Row>
   <?php endforeach; ?>
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <Selected/>
   <FreezePanes/>
   <FrozenNoSplit/>
   <SplitHorizontal>2</SplitHorizontal>
   <TopRowBottomPane>2</TopRowBottomPane>
  </WorksheetOptions>
 </Worksheet>

 <!-- ==================== SHEET 2: MONTHLY SUMMARY ==================== -->
 <Worksheet ss:Name="Monthly Summary">
  <Table ss:DefaultColumnWidth="100" ss:DefaultRowHeight="20">
   <Column ss:Width="80"/>
   <Column ss:Width="160"/>
   <Column ss:Width="85"/>
   <Column ss:Width="85"/>
   <Column ss:Width="85"/>
   <Column ss:Width="100"/>
   <Column ss:Width="100"/>
   <Column ss:Width="100"/>

   <Row ss:Height="30">
    <Cell ss:MergeAcross="7" ss:StyleID="HeaderTitle">
     <Data ss:Type="String"> Supervisor Patrol Monthly Performance Overview (<?= $monthLabel ?>)</Data>
    </Cell>
   </Row>

   <Row ss:Height="24">
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Staff ID</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Supervisor Name</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Total Shifts</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Day Shifts</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Night Shifts</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Completed Visits</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Total Site Mins</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Avg Mins / Visit</Data></Cell>
   </Row>

   <?php foreach ($supervisorSummary as $sup): ?>
   <Row ss:Height="20">
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= xmlEscape($sup['staff_id']) ?></Data></Cell>
    <Cell ss:StyleID="RowRegular"><Data ss:Type="String"><?= xmlEscape($sup['full_name']) ?></Data></Cell>
    <Cell ss:StyleID="RowNumber"><Data ss:Type="Number"><?= (int)$sup['total_shifts'] ?></Data></Cell>
    <Cell ss:StyleID="RowNumber"><Data ss:Type="Number"><?= (int)$sup['day_shifts'] ?></Data></Cell>
    <Cell ss:StyleID="RowNumber"><Data ss:Type="Number"><?= (int)$sup['night_shifts'] ?></Data></Cell>
    <Cell ss:StyleID="RowNumber"><Data ss:Type="Number"><?= (int)$sup['total_visits'] ?></Data></Cell>
    <Cell ss:StyleID="RowNumber"><Data ss:Type="Number"><?= (int)$sup['total_minutes'] ?></Data></Cell>
    <Cell ss:StyleID="RowCenter"><Data ss:Type="Number"><?= (float)$sup['avg_minutes_per_visit'] ?></Data></Cell>
   </Row>
   <?php endforeach; ?>
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <FreezePanes/>
   <FrozenNoSplit/>
   <SplitHorizontal>2</SplitHorizontal>
   <TopRowBottomPane>2</TopRowBottomPane>
  </WorksheetOptions>
 </Worksheet>

 <!-- ==================== SHEET 3: SITE SUMMARY ==================== -->
 <Worksheet ss:Name="Site Summary">
  <Table ss:DefaultColumnWidth="100" ss:DefaultRowHeight="20">
   <Column ss:Width="80"/>
   <Column ss:Width="160"/>
   <Column ss:Width="90"/>
   <Column ss:Width="90"/>
   <Column ss:Width="90"/>
   <Column ss:Width="90"/>
   <Column ss:Width="90"/>
   <Column ss:Width="100"/>
   <Column ss:Width="100"/>
   <Column ss:Width="120"/>

   <Row ss:Height="30">
    <Cell ss:MergeAcross="9" ss:StyleID="HeaderTitle">
     <Data ss:Type="String"> Site Supervision Coverage &amp; Contract Quota Compliance (<?= $monthLabel ?>)</Data>
    </Cell>
   </Row>

   <Row ss:Height="24">
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Site Code</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Site Name</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Target Visits/Wk</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Target Night/Wk</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Actual Visits</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Actual Night</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Total Time (Mins)</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Total Time (Hours)</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Avg Mins / Visit</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Compliance Status</Data></Cell>
   </Row>

   <?php 
   $weeksInMonth = $daysInMonth / 7;
   foreach ($siteSummary as $site): 
       $minWeek = (int)($site['min_visits_per_week'] ?: 7);
       $targetMonth = (int)ceil($minWeek * $weeksInMonth);
       $actual = (int)$site['total_visits'];
       $isMet = ($actual >= $targetMonth);
       $statusStr = $isMet ? "COMPLIANT (MET)" : ("SHORT BY " . ($targetMonth - $actual));
       $statusStyle = $isMet ? 'RatingOK' : 'RatingIssue';
       $hrs = round((int)$site['total_minutes'] / 60, 1);
   ?>
   <Row ss:Height="20">
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= xmlEscape($site['site_code']) ?></Data></Cell>
    <Cell ss:StyleID="RowRegular"><Data ss:Type="String"><?= xmlEscape($site['site_name']) ?></Data></Cell>
    <Cell ss:StyleID="RowNumber"><Data ss:Type="Number"><?= $minWeek ?></Data></Cell>
    <Cell ss:StyleID="RowNumber"><Data ss:Type="Number"><?= (int)$site['min_night_visits_per_week'] ?></Data></Cell>
    <Cell ss:StyleID="RowNumber"><Data ss:Type="Number"><?= $actual ?></Data></Cell>
    <Cell ss:StyleID="RowNumber"><Data ss:Type="Number"><?= (int)$site['night_visits'] ?></Data></Cell>
    <Cell ss:StyleID="RowNumber"><Data ss:Type="Number"><?= (int)$site['total_minutes'] ?></Data></Cell>
    <Cell ss:StyleID="RowNumber"><Data ss:Type="Number"><?= $hrs ?></Data></Cell>
    <Cell ss:StyleID="RowCenter"><Data ss:Type="Number"><?= (float)$site['avg_minutes'] ?></Data></Cell>
    <Cell ss:StyleID="<?= $statusStyle ?>"><Data ss:Type="String"><?= $statusStr ?></Data></Cell>
   </Row>
   <?php endforeach; ?>
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <FreezePanes/>
   <FrozenNoSplit/>
   <SplitHorizontal>2</SplitHorizontal>
   <TopRowBottomPane>2</TopRowBottomPane>
  </WorksheetOptions>
 </Worksheet>

 <!-- ==================== SHEET 4: PLANNED VS ACTUAL ==================== -->
 <Worksheet ss:Name="Planned vs Actual">
  <Table ss:DefaultColumnWidth="90" ss:DefaultRowHeight="20">
   <Column ss:Width="75"/>
   <Column ss:Width="70"/>
   <Column ss:Width="140"/>
   <Column ss:Width="80"/>
   <Column ss:Width="130"/>
   <Column ss:Width="50"/>
   <Column ss:Width="50"/>
   <Column ss:Width="90"/>
   <Column ss:Width="65"/>
   <Column ss:Width="65"/>
   <Column ss:Width="70"/>
   <Column ss:Width="110"/>

   <Row ss:Height="30">
    <Cell ss:MergeAcross="11" ss:StyleID="HeaderTitle">
     <Data ss:Type="String"> Supervisor Planned Patrol Schedules vs Field Execution (<?= $fromDate ?> to <?= $toDate ?>)</Data>
    </Cell>
   </Row>

   <Row ss:Height="24">
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Date</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Site Code</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Site Name</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Sup Staff ID</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Assigned Supervisor</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Shift</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Seq #</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Execution Status</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Time In</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Time Out</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Actual Mins</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Compliance</Data></Cell>
   </Row>

   <?php 
   if (empty($plannedSchedules)): ?>
   <Row ss:Height="20">
    <Cell ss:MergeAcross="11" ss:StyleID="RowCenter"><Data ss:Type="String">No planned schedules found for the selected period.</Data></Cell>
   </Row>
   <?php else:
       foreach ($plannedSchedules as $sch): 
           $isVisited = !empty($sch['actual_visit_id']);
           $vStatus = $isVisited ? 'VISITED' : 'MISSED';
           $vStyle = $isVisited ? 'RatingOK' : 'RatingIssue';
           $inStr = $isVisited ? date('H:i', strtotime($sch['actual_time_in'])) : '--:--';
           $outStr = ($isVisited && !empty($sch['actual_time_out'])) ? date('H:i', strtotime($sch['actual_time_out'])) : '--:--';
           $actMins = $isVisited ? (int)$sch['actual_minutes_spent'] : 0;
           $compNotes = $isVisited ? 'Completed on Schedule' : 'Missed Visit Exception';
   ?>
   <Row ss:Height="19">
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= $sch['specific_date'] ?: ('Weekly (Day ' . $sch['day_of_week'] . ')') ?></Data></Cell>
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= xmlEscape($sch['site_code']) ?></Data></Cell>
    <Cell ss:StyleID="RowRegular"><Data ss:Type="String"><?= xmlEscape($sch['site_name']) ?></Data></Cell>
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= xmlEscape($sch['supervisor_staff_id']) ?></Data></Cell>
    <Cell ss:StyleID="RowRegular"><Data ss:Type="String"><?= xmlEscape($sch['supervisor_name']) ?></Data></Cell>
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= xmlEscape($sch['shift_type']) ?></Data></Cell>
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= $sch['expected_time_start'] ? substr($sch['expected_time_start'], 0, 5) : '-' ?></Data></Cell>
    <Cell ss:StyleID="<?= $vStyle ?>"><Data ss:Type="String"><?= $vStatus ?></Data></Cell>
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= $inStr ?></Data></Cell>
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= $outStr ?></Data></Cell>
    <Cell ss:StyleID="RowNumber"><Data ss:Type="Number"><?= $actMins ?></Data></Cell>
    <Cell ss:StyleID="RowRegular"><Data ss:Type="String"><?= $compNotes ?></Data></Cell>
   </Row>
   <?php endforeach; endif; ?>
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <FreezePanes/>
   <FrozenNoSplit/>
   <SplitHorizontal>2</SplitHorizontal>
   <TopRowBottomPane>2</TopRowBottomPane>
  </WorksheetOptions>
 </Worksheet>

 <!-- ==================== SHEET 5: FLAGS & CORRECTIONS ==================== -->
 <Worksheet ss:Name="Flags and Corrections">
  <Table ss:DefaultColumnWidth="90" ss:DefaultRowHeight="20">
   <Column ss:Width="110"/>
   <Column ss:Width="95"/>
   <Column ss:Width="65"/>
   <Column ss:Width="120"/>
   <Column ss:Width="120"/>
   <Column ss:Width="250"/>
   <Column ss:Width="80"/>

   <Row ss:Height="30">
    <Cell ss:MergeAcross="6" ss:StyleID="HeaderTitle">
     <Data ss:Type="String"> Operational Alerts, Time Exceptions &amp; Manual Corrections Audit Trail</Data>
    </Cell>
   </Row>

   <!-- SECTION 1: SYSTEM ALERTS -->
   <Row ss:Height="22">
    <Cell ss:MergeAcross="6" ss:StyleID="ColHeader">
     <Data ss:Type="String">--- SYSTEM AUDIT ALERTS &amp; PROTOCOL EXCEPTIONS ---</Data>
    </Cell>
   </Row>
   <Row ss:Height="22">
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Timestamp</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Alert Type</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Severity</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Supervisor</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Site</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Alert Message / Reason</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Status</Data></Cell>
   </Row>

   <?php 
   if (empty($alerts)): ?>
   <Row ss:Height="19">
    <Cell ss:MergeAcross="6" ss:StyleID="RowCenter"><Data ss:Type="String">No system alerts recorded for this period.</Data></Cell>
   </Row>
   <?php else:
       foreach ($alerts as $a): 
           $sevStyle = ($a['severity'] === 'CRITICAL' || $a['severity'] === 'HIGH') ? 'RatingIssue' : 'RowCenter';
   ?>
   <Row ss:Height="19">
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= $a['created_at'] ?></Data></Cell>
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= xmlEscape($a['alert_type']) ?></Data></Cell>
    <Cell ss:StyleID="<?= $sevStyle ?>"><Data ss:Type="String"><?= xmlEscape($a['severity']) ?></Data></Cell>
    <Cell ss:StyleID="RowRegular"><Data ss:Type="String"><?= xmlEscape($a['supervisor_staff_id'] ? "{$a['supervisor_staff_id']} - {$a['supervisor_name']}" : '-') ?></Data></Cell>
    <Cell ss:StyleID="RowRegular"><Data ss:Type="String"><?= xmlEscape($a['site_code'] ? "{$a['site_code']} - {$a['site_name']}" : '-') ?></Data></Cell>
    <Cell ss:StyleID="RowRegular"><Data ss:Type="String"><?= xmlEscape($a['details']) ?></Data></Cell>
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= $a['is_acknowledged'] ? 'Acknowledged' : 'Active' ?></Data></Cell>
   </Row>
   <?php endforeach; endif; ?>

   <!-- SECTION 2: MANUAL CORRECTIONS -->
   <Row ss:Height="10"><Cell ss:MergeAcross="6"><Data ss:Type="String"></Data></Cell></Row>
   <Row ss:Height="22">
    <Cell ss:MergeAcross="6" ss:StyleID="ColHeader">
     <Data ss:Type="String">--- SUPERVISOR MANUAL TIME-OUT CORRECTION REQUESTS ---</Data>
    </Cell>
   </Row>
   <Row ss:Height="22">
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Visit Ref</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Supervisor</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Site</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Time In</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Requested Out</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Supervisor Justification &amp; Admin Decision</Data></Cell>
    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Decision</Data></Cell>
   </Row>

   <?php 
   if (empty($corrections)): ?>
   <Row ss:Height="19">
    <Cell ss:MergeAcross="6" ss:StyleID="RowCenter"><Data ss:Type="String">No manual time-out correction requests recorded.</Data></Cell>
   </Row>
   <?php else:
       foreach ($corrections as $c): 
           $cStyle = ($c['manual_timeout_status'] === 'Approved') ? 'RatingOK' : (($c['manual_timeout_status'] === 'Rejected') ? 'RatingIssue' : 'RowCenter');
           $notes = "Reason: " . ($c['manual_timeout_reason'] ?: '-');
           if (!empty($c['correction_notes'])) {
               $notes .= " | Admin: " . $c['correction_notes'] . " (" . ($c['approved_by_admin'] ?: 'admin') . ")";
           }
   ?>
   <Row ss:Height="19">
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= xmlEscape($c['visit_ref']) ?></Data></Cell>
    <Cell ss:StyleID="RowRegular"><Data ss:Type="String"><?= xmlEscape("{$c['supervisor_staff_id']} - {$c['supervisor_name']}") ?></Data></Cell>
    <Cell ss:StyleID="RowRegular"><Data ss:Type="String"><?= xmlEscape("{$c['site_code']} - {$c['site_name']}") ?></Data></Cell>
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= $c['time_in'] ?></Data></Cell>
    <Cell ss:StyleID="RowCenter"><Data ss:Type="String"><?= $c['manual_timeout_time'] ?></Data></Cell>
    <Cell ss:StyleID="RowRegular"><Data ss:Type="String"><?= xmlEscape($notes) ?></Data></Cell>
    <Cell ss:StyleID="<?= $cStyle ?>"><Data ss:Type="String"><?= xmlEscape($c['manual_timeout_status']) ?></Data></Cell>
   </Row>
   <?php endforeach; endif; ?>

  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <FreezePanes/>
   <FrozenNoSplit/>
   <SplitHorizontal>2</SplitHorizontal>
   <TopRowBottomPane>2</TopRowBottomPane>
  </WorksheetOptions>
 </Worksheet>

</Workbook>
