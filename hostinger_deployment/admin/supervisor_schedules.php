<?php
/**
 * Supervisor Patrol Schedules & Site Coverage Management
 * Admin-managed weekly patrol templates, date overrides, and site visit rules.
 * Ultimate Security Services (USS)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$db = get_db();
$pageTitle = "Supervisor Schedules & Site Coverage Rules";
$pageSubtitle = "Manage weekly templates, shift windows, site coverage quotas, and date overrides";

$msg = '';
$msgType = 'success';

// Handle Add / Edit Schedule Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_schedule'])) {
    $schedId = (int)($_POST['schedule_id'] ?? 0);
    $supId = (int)$_POST['supervisor_id'];
    $siteId = (int)$_POST['site_id'];
    $schedType = $_POST['sched_type']; // 'weekly' or 'override'
    $dayOfWeek = ($schedType === 'weekly') ? (int)$_POST['day_of_week'] : null;
    $specificDate = ($schedType === 'override' && !empty($_POST['specific_date'])) ? $_POST['specific_date'] : null;
    $shiftType = $_POST['shift_type'] ?? 'Day';
    $timeStart = !empty($_POST['expected_time_start']) ? $_POST['expected_time_start'] : null;
    $timeEnd = !empty($_POST['expected_time_end']) ? $_POST['expected_time_end'] : null;
    $minMinutes = max(5, (int)($_POST['min_minutes'] ?? 30));

    if (empty($supId) || empty($siteId)) {
        $msg = "Please select both a supervisor and a patrol site.";
        $msgType = "danger";
    } else {
        if ($schedId > 0) {
            $stmt = $db->prepare("
                UPDATE supervisor_schedules SET
                    supervisor_id = ?, site_id = ?, day_of_week = ?, specific_date = ?,
                    shift_type = ?, expected_time_start = ?, expected_time_end = ?, min_minutes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$supId, $siteId, $dayOfWeek, $specificDate, $shiftType, $timeStart, $timeEnd, $minMinutes, $schedId]);
            log_audit('UPDATE_SCHEDULE', 'SCHEDULE', (string)$schedId, 'Updated supervisor schedule entry');
            $msg = "Schedule entry successfully updated.";
        } else {
            $stmt = $db->prepare("
                INSERT INTO supervisor_schedules 
                (supervisor_id, site_id, day_of_week, specific_date, shift_type, expected_time_start, expected_time_end, min_minutes, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$supId, $siteId, $dayOfWeek, $specificDate, $shiftType, $timeStart, $timeEnd, $minMinutes]);
            log_audit('CREATE_SCHEDULE', 'SCHEDULE', (string)$db->lastInsertId(), 'Created new supervisor schedule entry');
            $msg = "New schedule entry successfully assigned.";
        }
    }
}

// Handle Delete Schedule Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete_schedule'])) {
    $delId = (int)$_POST['delete_id'];
    $db->prepare("DELETE FROM supervisor_schedules WHERE id = ?")->execute([$delId]);
    log_audit('DELETE_SCHEDULE', 'SCHEDULE', (string)$delId, 'Deleted supervisor schedule entry');
    $msg = "Schedule entry removed.";
}

// Handle Update Site Coverage Quota
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_site_coverage'])) {
    $targetSiteId = (int)$_POST['coverage_site_id'];
    $minVisitsWeek = max(1, (int)$_POST['min_visits_per_week']);
    $minNightWeek = max(0, (int)$_POST['min_night_visits_per_week']);
    $minMinsVisit = max(5, (int)$_POST['min_minutes_per_visit']);
    $maxMinsVisit = max($minMinsVisit, (int)$_POST['max_minutes_per_visit']);

    $db->prepare("
        UPDATE sites SET 
            min_visits_per_week = ?,
            min_night_visits_per_week = ?,
            min_minutes_per_visit = ?,
            max_minutes_per_visit = ?,
            updated_at = NOW()
        WHERE id = ?
    ")->execute([$minVisitsWeek, $minNightWeek, $minMinsVisit, $maxMinsVisit, $targetSiteId]);

    log_audit('UPDATE_SITE_COVERAGE', 'SITE', (string)$targetSiteId, "Updated coverage quota: {$minVisitsWeek} visits/wk, {$minNightWeek} nights");
    $msg = "Site coverage rules successfully updated.";
}

// Filter parameters
$filterSup = (int)($_GET['supervisor_id'] ?? 0);
$filterDay = trim($_GET['day'] ?? '');

$where = ["1=1"];
$params = [];
if ($filterSup > 0) {
    $where[] = "sc.supervisor_id = ?";
    $params[] = $filterSup;
}
if ($filterDay !== '') {
    $where[] = "sc.day_of_week = ?";
    $params[] = (int)$filterDay;
}

$whereSql = implode(' AND ', $where);

// Fetch schedules
$schedules = $db->prepare("
    SELECT sc.*, g.full_name as supervisor_name, g.staff_id, s.name as site_name, s.code as site_code
    FROM supervisor_schedules sc
    JOIN guards g ON g.id = sc.supervisor_id
    JOIN sites s ON s.id = sc.site_id
    WHERE {$whereSql}
    ORDER BY sc.day_of_week ASC, sc.specific_date ASC, sc.expected_time_start ASC
");
$schedules->execute($params);
$scheduleList = $schedules->fetchAll();

// Fetch supervisors and sites for dropdowns
$supervisors = $db->query("SELECT id, staff_id, full_name FROM guards WHERE is_supervisor = 1 ORDER BY full_name ASC")->fetchAll();
$sites = $db->query("SELECT * FROM sites WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

$daysMap = [
    1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'
];

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" style="margin-bottom: 20px; font-weight: 600;">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<!-- Tabs Navigation -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <div style="display: flex; gap: 8px;">
        <button type="button" class="btn btn-primary" onclick="showTab('schedulesTab')">
            📅 Weekly Patrol Schedules (<?= count($scheduleList) ?>)
        </button>
        <button type="button" class="btn btn-secondary" onclick="showTab('coverageTab')">
            🏢 Site Coverage Quotas (<?= count($sites) ?> Sites)
        </button>
    </div>

    <div style="display: flex; gap: 8px;">
        <button type="button" class="btn btn-primary" onclick="openScheduleModal()">
            + Assign New Schedule
        </button>
        <a href="<?= APP_URL ?>/admin/supervisor_board.php" class="btn btn-secondary">
            ← Live Board
        </a>
    </div>
</div>

<!-- Tab 1: Schedules List -->
<div id="schedulesTab">
    <!-- Filter Bar -->
    <div class="card" style="margin-bottom: 18px; padding: 14px 18px;">
        <form method="GET" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
            <div class="form-group" style="margin: 0; min-width: 220px;">
                <label class="form-label" style="font-size: 11.5px;">Filter by Supervisor</label>
                <select name="supervisor_id" class="form-control" style="font-size: 13px;" onchange="this.form.submit()">
                    <option value="0">All Field Supervisors</option>
                    <?php foreach ($supervisors as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $filterSup == $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['staff_id']) ?> - <?= htmlspecialchars($s['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin: 0;">
                <label class="form-label" style="font-size: 11.5px;">Day of Week</label>
                <select name="day" class="form-control" style="font-size: 13px;" onchange="this.form.submit()">
                    <option value="">All Days</option>
                    <?php foreach ($daysMap as $dNum => $dName): ?>
                        <option value="<?= $dNum ?>" <?= $filterDay === (string)$dNum ? 'selected' : '' ?>>
                            <?= $dName ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="padding: 8px 16px; font-size: 12.5px;">Filter</button>
            <a href="<?= APP_URL ?>/admin/supervisor_schedules.php" class="btn btn-outline" style="padding: 8px 12px; font-size: 12.5px;">Reset</a>
        </form>
    </div>

    <!-- Schedules Table Card -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="overflow-x: auto;">
            <table class="table" style="margin: 0;">
                <thead>
                    <tr>
                        <th style="padding-left: 20px;">Supervisor</th>
                        <th>Day / Date</th>
                        <th>Site Assigned</th>
                        <th>Shift</th>
                        <th>Expected Window</th>
                        <th>Min Time</th>
                        <th style="text-align: right; padding-right: 20px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($scheduleList)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-dim);">
                                No patrol schedules assigned matching the selected filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($scheduleList as $sc): ?>
                            <tr>
                                <td style="padding-left: 20px;">
                                    <strong style="color: var(--text-main); font-size: 13.5px;"><?= htmlspecialchars($sc['supervisor_name']) ?></strong>
                                    <div style="font-size: 11px; color: var(--accent-cyan); font-weight: 700;"><?= htmlspecialchars($sc['staff_id']) ?></div>
                                </td>
                                <td>
                                    <?php if ($sc['specific_date']): ?>
                                        <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #d97706; font-weight: 800;">
                                            📅 <?= date('d M Y', strtotime($sc['specific_date'])) ?> (Override)
                                        </span>
                                    <?php else: ?>
                                        <span style="font-weight: 700; color: var(--text-main);">
                                            <?= $daysMap[$sc['day_of_week']] ?? 'Weekly' ?>
                                        </span>
                                        <div style="font-size: 10.5px; color: var(--text-dim);">Recurring Weekly</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="font-size: 13px;"><?= htmlspecialchars($sc['site_name']) ?></strong>
                                    <div style="font-size: 11px; color: var(--text-dim);"><?= htmlspecialchars($sc['site_code']) ?></div>
                                </td>
                                <td>
                                    <span class="badge" style="background: <?= $sc['shift_type'] === 'Night' ? 'rgba(99, 102, 241, 0.2)' : 'rgba(245, 158, 11, 0.2)' ?>; color: <?= $sc['shift_type'] === 'Night' ? 'var(--accent-indigo)' : 'var(--accent-amber)' ?>; font-weight: 700;">
                                        <?= htmlspecialchars($sc['shift_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($sc['expected_time_start']): ?>
                                        <span style="font-family: monospace; font-size: 12.5px; font-weight: 700;">
                                            <?= date('h:i A', strtotime($sc['expected_time_start'])) ?> - <?= date('h:i A', strtotime($sc['expected_time_end'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-dim); font-size: 12px;">Any time during shift</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #059669; font-weight: 800;">
                                        <?= (int)$sc['min_minutes'] ?> mins
                                    </span>
                                </td>
                                <td style="text-align: right; padding-right: 20px;">
                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Remove this schedule assignment?');">
                                        <input type="hidden" name="action_delete_schedule" value="1">
                                        <input type="hidden" name="delete_id" value="<?= $sc['id'] ?>">
                                        <button type="submit" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px; color: #ef4444; border-color: rgba(239,68,68,0.3);">
                                            ✕ Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Tab 2: Site Coverage Quotas -->
<div id="coverageTab" style="display: none;">
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 14px 20px; border-bottom: 1px solid var(--border-subtle); background: rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 14.5px; font-weight: 700;">Site Coverage Rules & Duration Quotas</h3>
            <span style="font-size: 12px; color: var(--text-dim);">Automated Compliance Triggers</span>
        </div>

        <div style="overflow-x: auto;">
            <table class="table" style="margin: 0;">
                <thead>
                    <tr>
                        <th style="padding-left: 20px;">Site Name & Code</th>
                        <th>Type / Client</th>
                        <th>Required Visits / Wk</th>
                        <th>Night Visits / Wk</th>
                        <th>Min Time / Visit</th>
                        <th>Max Time / Visit</th>
                        <th style="text-align: right; padding-right: 20px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sites as $st): ?>
                        <tr>
                            <td style="padding-left: 20px;">
                                <strong style="font-size: 13.5px;"><?= htmlspecialchars($st['name']) ?></strong>
                                <div style="font-size: 11px; color: var(--accent-cyan); font-weight: 700;"><?= htmlspecialchars($st['code']) ?></div>
                            </td>
                            <td>
                                <span style="font-size: 12px;"><?= htmlspecialchars($st['type']) ?></span>
                                <div style="font-size: 11px; color: var(--text-dim);"><?= htmlspecialchars($st['client_name'] ?: 'USS Client') ?></div>
                            </td>
                            <td>
                                <strong style="color: var(--primary);"><?= (int)$st['min_visits_per_week'] ?></strong> visits/wk
                            </td>
                            <td>
                                <strong style="color: var(--accent-indigo);"><?= (int)$st['min_night_visits_per_week'] ?></strong> nights/wk
                            </td>
                            <td>
                                <?= (int)$st['min_minutes_per_visit'] ?> mins
                            </td>
                            <td>
                                <?= (int)$st['max_minutes_per_visit'] ?> mins
                            </td>
                            <td style="text-align: right; padding-right: 20px;">
                                <button type="button" class="btn btn-secondary" style="padding: 4px 10px; font-size: 11.5px;" onclick='openCoverageModal(<?= json_encode($st) ?>)'>
                                    ✏️ Edit Quota
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Assign Schedule -->
<div id="scheduleModal" class="modal" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="modal-dialog" style="background: var(--bg-card); width: 100%; max-width: 520px; border-radius: 12px; border: 1px solid var(--border-subtle); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3); overflow: hidden;">
        <div style="padding: 16px 22px; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 800;">Assign Patrol Schedule</h3>
            <button type="button" onclick="closeScheduleModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-dim);">&times;</button>
        </div>

        <form method="POST" action="" style="margin: 0; padding: 22px;">
            <input type="hidden" name="action_save_schedule" value="1">
            <input type="hidden" name="schedule_id" id="modalSchedId" value="0">

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label">Supervisor <span style="color:#ef4444;">*</span></label>
                <select name="supervisor_id" class="form-control" required>
                    <option value="">Select Supervisor...</option>
                    <?php foreach ($supervisors as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['staff_id']) ?> - <?= htmlspecialchars($s['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label">Patrol Site <span style="color:#ef4444;">*</span></label>
                <select name="site_id" class="form-control" required>
                    <option value="">Select Site...</option>
                    <?php foreach ($sites as $st): ?>
                        <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?> (<?= htmlspecialchars($st['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Schedule Type</label>
                    <select name="sched_type" id="modalSchedType" class="form-control" onchange="toggleSchedType(this.value)">
                        <option value="weekly">Recurring Weekly</option>
                        <option value="override">Date Override</option>
                    </select>
                </div>

                <div class="form-group" style="margin: 0;" id="dayOfWeekGroup">
                    <label class="form-label">Day of Week</label>
                    <select name="day_of_week" class="form-control">
                        <?php foreach ($daysMap as $dNum => $dName): ?>
                            <option value="<?= $dNum ?>"><?= $dName ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin: 0; display: none;" id="overrideDateGroup">
                    <label class="form-label">Specific Date</label>
                    <input type="date" name="specific_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Duty Shift</label>
                    <select name="shift_type" class="form-control">
                        <option value="Day">Day Shift</option>
                        <option value="Night">Night Shift</option>
                    </select>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Min Time on Site</label>
                    <input type="number" name="min_minutes" class="form-control" value="30" min="5" max="360">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Window Start (Optional)</label>
                    <input type="time" name="expected_time_start" class="form-control">
                </div>

                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Window End (Optional)</label>
                    <input type="time" name="expected_time_end" class="form-control">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="closeScheduleModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Assignment</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Site Coverage Quota -->
<div id="coverageModal" class="modal" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="modal-dialog" style="background: var(--bg-card); width: 100%; max-width: 480px; border-radius: 12px; border: 1px solid var(--border-subtle); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3); overflow: hidden;">
        <div style="padding: 16px 22px; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 800;" id="covModalSiteName">Site Coverage Quota</h3>
            <button type="button" onclick="closeCoverageModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-dim);">&times;</button>
        </div>

        <form method="POST" action="" style="margin: 0; padding: 22px;">
            <input type="hidden" name="action_update_site_coverage" value="1">
            <input type="hidden" name="coverage_site_id" id="covSiteId">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Min Visits / Week</label>
                    <input type="number" name="min_visits_per_week" id="covMinVisits" class="form-control" min="1" max="100" required>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Min Night Visits / Wk</label>
                    <input type="number" name="min_night_visits_per_week" id="covMinNight" class="form-control" min="0" max="100" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Min Minutes / Visit</label>
                    <input type="number" name="min_minutes_per_visit" id="covMinMins" class="form-control" min="5" max="300" required>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Max Minutes / Visit</label>
                    <input type="number" name="max_minutes_per_visit" id="covMaxMins" class="form-control" min="5" max="600" required>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="closeCoverageModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Quotas</button>
            </div>
        </form>
    </div>
</div>

<script>
function showTab(tabId) {
    document.getElementById('schedulesTab').style.display = (tabId === 'schedulesTab') ? 'block' : 'none';
    document.getElementById('coverageTab').style.display = (tabId === 'coverageTab') ? 'block' : 'none';
}

function openScheduleModal() {
    document.getElementById('modalSchedId').value = 0;
    document.getElementById('scheduleModal').style.display = 'flex';
}
function closeScheduleModal() {
    document.getElementById('scheduleModal').style.display = 'none';
}

function toggleSchedType(val) {
    if (val === 'override') {
        document.getElementById('dayOfWeekGroup').style.display = 'none';
        document.getElementById('overrideDateGroup').style.display = 'block';
    } else {
        document.getElementById('dayOfWeekGroup').style.display = 'block';
        document.getElementById('overrideDateGroup').style.display = 'none';
    }
}

function openCoverageModal(site) {
    document.getElementById('covSiteId').value = site.id;
    document.getElementById('covModalSiteName').innerText = site.name + ' (' + site.code + ')';
    document.getElementById('covMinVisits').value = site.min_visits_per_week || 7;
    document.getElementById('covMinNight').value = site.min_night_visits_per_week || 2;
    document.getElementById('covMinMins').value = site.min_minutes_per_visit || 20;
    document.getElementById('covMaxMins').value = site.max_minutes_per_visit || 120;
    document.getElementById('coverageModal').style.display = 'flex';
}
function closeCoverageModal() {
    document.getElementById('coverageModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
