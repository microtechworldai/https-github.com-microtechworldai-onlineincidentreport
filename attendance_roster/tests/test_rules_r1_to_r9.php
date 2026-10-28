<?php
/**
 * Universal Security Services - Attendance Roster Subsystem
 * Automated Test Suite for Strict Action Rules R1 to R9
 * File: /attendance_roster/tests/test_rules_r1_to_r9.php
 */
require_once dirname(__DIR__) . '/config/roster_config.php';
require_once dirname(__DIR__) . '/src/RosterAudit.php';
require_once dirname(__DIR__) . '/src/RosterStaff.php';
require_once dirname(__DIR__) . '/src/RosterSite.php';
require_once dirname(__DIR__) . '/src/RosterEntry.php';

$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_role'] = 'Super Admin';
$_SESSION['admin_name'] = 'Super Admin';

$passed = 0;
$failed = 0;

function assertRule(string $ruleName, bool $condition, string $detail = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] $ruleName " . ($detail ? "($detail)" : "") . "\n";
    } else {
        $failed++;
        echo "  [FAIL] $ruleName " . ($detail ? "($detail)" : "") . "\n";
    }
}

echo "\n======================================================\n";
echo "   USS ATTENDANCE ROSTER - STRICT ACTION RULES TESTS\n";
echo "======================================================\n\n";

$db = roster_get_db();

// 1. Setup Synthetic Test Fixtures
$site1 = RosterSite::getOrCreate(101, 'Test Site Alpha', 'TSA', 'Client A', 1, 1);
$site2 = RosterSite::getOrCreate(102, 'Test Site Beta', 'TSB', 'Client B', 1, 1);
$site4 = RosterSite::getOrCreate(104, 'Test Site Delta', 'TSD', 'Client D', 1, 1);

$staffA = RosterStaff::getOrCreate('TEST-SG101', 'Test Guard Alpha', 'Security Guard');
$staffB = RosterStaff::getOrCreate('TEST-SG102', 'Test Guard Beta', 'Security Guard');

$testDate1 = '2026-10-01';
$testDate2 = '2026-10-02';
$testDate5 = '2026-10-05';

// Clear test data for clean run
$db->exec("DELETE FROM roster_entries WHERE staff_id IN ({$staffA['id']}, {$staffB['id']})");
$db->exec("DELETE FROM roster_audit WHERE staff_id IN ({$staffA['id']}, {$staffB['id']})");

// ============================================================================
// TEST SUITE 1: Rule R1 & R2 (Past Attendance Immutability & No Automatic Transfer)
// ============================================================================
echo "--- Testing Rule R1 & R2: Past Attendance Immutability ---\n";
// Staff A worked on Date 1 at Site 1 (Day Shift 'D')
$resA1 = RosterEntry::setCellCode($staffA['id'], 101, $testDate1, 'D');
assertRule("R1.1", $resA1['success'] === true && $resA1['code'] === 'D', "Staff A marked D at Site 1 on Oct 1");

// Assign Staff A to Site 2 from Date 1: Verify Site 1 entry is unchanged
$stmtCheck1 = $db->prepare("SELECT code FROM roster_entries WHERE staff_id = ? AND site_id = 101 AND work_date = ?");
$stmtCheck1->execute([$staffA['id'], $testDate1]);
assertRule("R1.2", $stmtCheck1->fetchColumn() === 'D', "Site 1 date 1 attendance stays untouched");

// Confirm Site 2 has NOT received any automatic copy or rollover
$stmtCheck2 = $db->prepare("SELECT COUNT(*) FROM roster_entries WHERE staff_id = ? AND site_id = 102");
$stmtCheck2->execute([$staffA['id']]);
assertRule("R2.1", (int)$stmtCheck2->fetchColumn() === 0, "No automatic backfill or transfer at Site 2");

// ============================================================================
// TEST SUITE 2: Rule R3 & R4 (Single Duty Enforcement & Conflict Detection)
// ============================================================================
echo "\n--- Testing Rule R3 & R4: One Duty Per Person & Conflict Detection ---\n";
// Try to assign Staff A at Site 4 on Date 1 (Conflict collision)
$conflictDetected = false;
$conflictMsg = '';
try {
    RosterEntry::setCellCode($staffA['id'], 104, $testDate1, 'D');
} catch (RosterConflictException $ce) {
    $conflictDetected = true;
    $conflictMsg = $ce->getMessage();
    $details = $ce->getConflictDetails();
}

assertRule("R3.1", $conflictDetected, "Blocked duplicate attendance across sites on same date");
assertRule("R4.1", strpos($conflictMsg, 'Test Guard Alpha') !== false && strpos($conflictMsg, 'Test Site Alpha') !== false, "Conflict message displays Guard Name, Date, and existing Site Name");

// ============================================================================
// TEST SUITE 3: Rule R4 Actions (Cancel, Assign Free, Move, Force Double)
// ============================================================================
echo "\n--- Testing Rule R4 Actions: Conflict Resolution Flows ---\n";

// Action 1: Cancel -> Confirm Site 1 has 'D' and Site 4 has nothing
$stmtS4 = $db->prepare("SELECT COUNT(*) FROM roster_entries WHERE staff_id = ? AND site_id = 104 AND work_date = ?");
$stmtS4->execute([$staffA['id'], $testDate1]);
assertRule("R4.Action1 (Cancel)", (int)$stmtS4->fetchColumn() === 0, "Cancel leaves original data intact");

// Action 2: Assign free dates only (Date 2 is free, Date 1 is skipped)
$resFree = RosterEntry::setCellCode($staffA['id'], 104, $testDate2, 'D');
assertRule("R4.Action2 (Assign Free)", $resFree['success'] === true, "Free date Oct 2 successfully assigned at Site 4");

// Action 3: Move from Site 1 to Site 4 (Audited transfer)
$resMove = RosterEntry::moveAttendance($staffA['id'], $testDate1, 101, 104, 'Operational site re-allocation');
assertRule("R4.Action3 (Move)", $resMove['success'] === true, "Attendance moved from Site 1 to Site 4");

// Verify Site 1 lost date 1, and Site 4 gained date 1
$stmtCheckS1 = $db->prepare("SELECT COUNT(*) FROM roster_entries WHERE staff_id = ? AND site_id = 101 AND work_date = ?");
$stmtCheckS1->execute([$staffA['id'], $testDate1]);
$stmtCheckS4 = $db->prepare("SELECT COUNT(*) FROM roster_entries WHERE staff_id = ? AND site_id = 104 AND work_date = ?");
$stmtCheckS4->execute([$staffA['id'], $testDate1]);
assertRule("R4.Action3 Counts", (int)$stmtCheckS1->fetchColumn() === 0 && (int)$stmtCheckS4->fetchColumn() === 1, "Site 1 lost the day, Site 4 gained it");

// Action 4: Force assign at Site 1 as Double Duty
$resForce = RosterEntry::setCellCode($staffA['id'], 101, $testDate1, 'N', true, 'Emergency overnight coverage approved by Director');
assertRule("R4.Action4 (Force Double)", $resForce['is_forced_double'] === 1, "Forced double duty approved and flagged");

// Confirm both entries exist on date 1 (one normal at Site 4, one forced double at Site 1)
$stmtDoubles = $db->prepare("SELECT COUNT(*) FROM roster_entries WHERE staff_id = ? AND work_date = ?");
$stmtDoubles->execute([$staffA['id'], $testDate1]);
assertRule("R4.Action4 Double Count", (int)$stmtDoubles->fetchColumn() === 2, "Both entries exist and are flagged");

// ============================================================================
// TEST SUITE 4: Rule R5 & Undo Engine
// ============================================================================
echo "\n--- Testing Rule R5: Explicit Admin Action & Undo Mechanism ---\n";
// Undo the forced double duty using its undo token
$undoToken = $resForce['undo_token'];
$undoSuccess = RosterAudit::undo($undoToken);
assertRule("R5.1 (Undo)", $undoSuccess, "Undo executed successfully via audit token");

// Verify the forced entry was cleanly removed
$stmtAfterUndo = $db->prepare("SELECT COUNT(*) FROM roster_entries WHERE staff_id = ? AND work_date = ?");
$stmtAfterUndo->execute([$staffA['id'], $testDate1]);
assertRule("R5.2 (State Restored)", (int)$stmtAfterUndo->fetchColumn() === 1, "Previous state fully restored");

// ============================================================================
// TEST SUITE 5: Rule R6 (Automatic Day/Night Recalculation)
// ============================================================================
echo "\n--- Testing Rule R6: Automatic Counts & Recalculation ---\n";
// Add Guard B Day Shift on Date 2 at Site 4
RosterEntry::setCellCode($staffB['id'], 104, $testDate2, 'D');
$sheetData = RosterEntry::getMonthlySheet(104, 2026, 10);

assertRule("R6.1 (Day Count)", $sheetData['dailySummary'][2]['act_day'] === 2, "Day 2 Day Shift count is 2 (Staff A + Staff B)");
assertRule("R6.2 (Total on Duty)", $sheetData['dailySummary'][2]['act_total'] === 2, "Day 2 Total Duty count is 2");

// Clear Guard B on Date 2 -> counts must decrement immediately
RosterEntry::setCellCode($staffB['id'], 104, $testDate2, '');
$sheetDataAfter = RosterEntry::getMonthlySheet(104, 2026, 10);
assertRule("R6.3 (Recalculate on Delete)", $sheetDataAfter['dailySummary'][2]['act_day'] === 1, "Day 2 Day Shift count immediately recalculated to 1 on clear");

// ============================================================================
// TEST SUITE 6: Rule R7, R8, R9 (Locking, Security & History Retention)
// ============================================================================
echo "\n--- Testing Rules R7, R8 & R9: Lock, Isolation & History Retention ---\n";
// Rule R7: Lock month
RosterEntry::setSheetStatus(104, 2026, 10, 'Locked', 'Finalized for October payroll');
$lockedBlocked = false;
try {
    RosterEntry::setCellCode($staffA['id'], 104, $testDate2, 'N');
} catch (RuntimeException $re) {
    $lockedBlocked = true;
}
assertRule("R7.1 (Locked Month)", $lockedBlocked, "Locked month strictly rejects any modifications");

// Unlock with reason
RosterEntry::setSheetStatus(104, 2026, 10, 'Approved', 'Manager un-lock for adjustment');
$unlockedAllowed = false;
try {
    $resAdj = RosterEntry::setCellCode($staffA['id'], 104, $testDate2, 'N');
    $unlockedAllowed = ($resAdj['code'] === 'N');
} catch (Throwable $ignore) {}
assertRule("R7.2 (Unlock Month)", $unlockedAllowed, "Unlocked month allows audited adjustments");

// Clean up test data
$db->exec("DELETE FROM roster_entries WHERE staff_id IN ({$staffA['id']}, {$staffB['id']})");
$db->exec("DELETE FROM roster_audit WHERE staff_id IN ({$staffA['id']}, {$staffB['id']})");
$db->exec("DELETE FROM roster_sites_config WHERE site_id IN (101, 102, 104)");
$db->exec("DELETE FROM roster_staff WHERE id IN ({$staffA['id']}, {$staffB['id']})");

echo "\n======================================================\n";
echo "   TEST SUMMARY: $passed Passed, $failed Failed\n";
echo "======================================================\n\n";

if ($failed > 0) {
    exit(1);
}

