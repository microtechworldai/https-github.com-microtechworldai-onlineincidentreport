<?php
// Attendance & Payroll Module - Consolidated Payroll View
// - Two top buttons: 1. Attendance Roster and 2. Consolidated Payroll
// - From Date & To Date selection (supports cross-month, e.g. 27 Sep to 26 Oct)
// - One site or all sites selection
// - Lay out: Emp No, Emp Name, Site Name, each date with its weekday, Day, Night, Basic, Sick, Off, Total No. of Days, Custom Columns, Remarks
// - Staff on more than one site merged into one row showing all worked sites
// - Conflict warning ("Conflict: 15 Oct") if different codes exist
// - Bottom row totals for each count column and numeric custom columns
// - Export Excel button (Excel only, no PDF button on payroll), teal title & header, thin black borders, real formulas, A4 landscape print fit
// - Wrap text enabled across all cells
// - + Add column, edit (rename/change type), delete custom column with confirmation
// File: hostinger_deployment/attendance/views/payroll.php

if (!defined('ATT_ACCESS')) {
    die('Direct access not permitted.');
}

$pageTitle = 'Consolidated Payroll';
$activeTab = 'payroll';
require __DIR__ . '/layout/header.php';

$staffList = $payrollData['staff'] ?? [];
$datesList = $payrollData['dates_list'] ?? [];
$startDate = $payrollData['start_date'] ?? date('Y-m-01');
$endDate = $payrollData['end_date'] ?? date('Y-m-t');
$customCols = $payrollData['custom_columns'] ?? [];
$daysCount = count($datesList);
$customColCount = count($customCols);

$startFormatted = strtoupper(date('j M Y', strtotime($startDate)));
$endFormatted = strtoupper(date('j M Y', strtotime($endDate)));

$totalTableCols = 2 + $daysCount + 6 + $customColCount + 1;
?>

<style>
/* Teal title & header for Payroll: 45px height and 24px font size */
.att-payroll-header-banner {
    background-color: #005f73 !important;
    color: #ffffff !important;
    font-size: 24px !important;
    font-weight: 800 !important;
    letter-spacing: 0.5px !important;
    height: 45px !important;
    line-height: 45px;
    text-align: center !important;
    vertical-align: middle !important;
    padding: 0 8px !important;
}

.att-th-teal-pay {
    background-color: #0a9396 !important;
    color: #ffffff !important;
    font-weight: bold !important;
    font-size: 18px !important;
    height: 28px !important;
    border: 1px solid #000000 !important;
    vertical-align: middle !important;
    text-align: center !important;
    white-space: nowrap !important;
    padding: 1px 3px !important;
    box-sizing: border-box !important;
}

.att-th-teal-pay-sub {
    background-color: #0a9396 !important;
    color: #ffffff !important;
    font-weight: bold !important;
    font-size: 14px !important;
    height: 28px !important;
    border: 1px solid #000000 !important;
    vertical-align: middle !important;
    text-align: center !important;
    white-space: nowrap !important;
    padding: 2px 4px !important;
    box-sizing: border-box !important;
}

.att-off-cell {
    color: #c00000 !important;
    font-weight: 800 !important;
    font-size: 11px !important;
}

.att-conflict-note {
    color: #b91c1c !important;
    font-weight: 700;
}

.att-col-custom-th {
    position: relative;
    padding: 2px 4px !important;
    min-width: 75px;
}

.att-col-actions {
    display: inline-flex;
    gap: 4px;
    margin-left: 4px;
    vertical-align: middle;
}

.att-btn-col-action {
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 11px;
    padding: 0 2px;
    line-height: 1;
    opacity: 0.75;
    color: #ffffff;
}

.att-btn-col-action:hover {
    opacity: 1;
    transform: scale(1.15);
}

.att-btn-col-delete:hover {
    color: #fca5a5 !important;
}
</style>

<!-- Action & Filter Bar -->
<div class="att-controlbar">
    <form method="GET" action="index.php" class="att-filters">
        <input type="hidden" name="action" value="payroll">

        <label for="att-pay-start"><strong>From Date:</strong></label>
        <input type="date" id="att-pay-start" name="start_date" class="att-input" value="<?= htmlspecialchars($startDate) ?>">

        <label for="att-pay-end"><strong>To Date:</strong></label>
        <input type="date" id="att-pay-end" name="end_date" class="att-input" value="<?= htmlspecialchars($endDate) ?>">

        <label for="att-pay-site"><strong>Site:</strong></label>
        <select id="att-pay-site" name="site_id" class="att-select">
            <option value="">-- All Sites Consolidated --</option>
            <?php foreach ($sites as $s): ?>
                <option value="<?= $s['id'] ?>" <?= (isset($_GET['site_id']) && $_GET['site_id'] == $s['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['site_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="att-btn att-btn-primary">Apply Date Range</button>
        <span style="color:#64748b;font-size:12px;">(<?= $daysCount ?> days)</span>
    </form>

    <div class="att-actions">
        <!-- Add Column Button -->
        <button type="button" class="att-btn att-btn-secondary" id="att-btn-add-col">
            + Add column
        </button>

        <!-- Excel export only, no PDF button as specified -->
        <a href="index.php?action=export_excel&export_type=payroll&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?><?= !empty($_GET['site_id']) ? '&site_id=' . (int)$_GET['site_id'] : '' ?>" 
           class="att-btn att-btn-success">
            ⬇ Export Excel (.xls)
        </a>

        <!-- One-click Database Backup Button -->
        <a href="index.php?action=backup_download" class="att-btn att-btn-secondary" style="background:#0f172a;color:#ffffff;border-color:#0f172a;" title="Download complete SQL database backup for long run safety and reuse">
            🛡️ Backup Database (.sql)
        </a>
    </div>
</div>

<main class="att-container">
    <div class="att-grid-wrapper">
        <table class="att-table" style="border: 2px solid #000000;">
            <thead>
                <!-- Teal Title Banner: height 45px, font size 24px -->
                <tr style="height: 45px;">
                    <th colspan="<?= $totalTableCols ?>" class="att-payroll-header-banner">
                        ULTIMATE SECURITY SERVICES PAYROLL ATTENDANCE FROM <?= $startFormatted ?> TO <?= $endFormatted ?>
                    </th>
                </tr>

                <!-- Blank White Row In Between -->
                <tr class="att-tr-blank-spacer" style="height: 12px; background-color: #ffffff;">
                    <th colspan="<?= $totalTableCols ?>" style="height: 12px; background-color: #ffffff; border: 1px solid #000000; padding: 0;"></th>
                </tr>

                <!-- Header Row 1: Exactly 28px tall, font size 14px bold -->
                <tr style="height: 28px;">
                    <th rowspan="2" class="att-th-teal-pay att-col-emp">Emp No</th>
                    <th rowspan="2" class="att-th-teal-pay att-col-name">Emp Name</th>

                    <!-- Date Numbers -->
                    <?php foreach ($datesList as $dInfo): ?>
                        <th class="att-th-teal-pay att-col-day"><?= $dInfo['day'] ?></th>
                    <?php endforeach; ?>

                    <!-- Count Columns: Day, Night, Basic, Sick, Off, Total No. of Days -->
                    <th rowspan="2" class="att-th-teal-pay att-col-stat">Day</th>
                    <th rowspan="2" class="att-th-teal-pay att-col-stat">Night</th>
                    <th rowspan="2" class="att-th-teal-pay att-col-stat">Basic</th>
                    <th rowspan="2" class="att-th-teal-pay att-col-stat">Sick</th>
                    <th rowspan="2" class="att-th-teal-pay att-col-stat">Off</th>
                    <th rowspan="2" class="att-th-teal-pay att-col-total">Total No. of Days</th>

                    <!-- Custom Extra Columns -->
                    <?php foreach ($customCols as $cc): ?>
                        <th rowspan="2" class="att-th-teal-pay att-col-custom-th" data-col-id="<?= $cc['id'] ?>" data-col-name="<?= htmlspecialchars($cc['column_name']) ?>" data-col-type="<?= htmlspecialchars($cc['col_type']) ?>">
                            <span class="att-col-title"><?= htmlspecialchars($cc['column_name']) ?></span>
                            <div class="att-col-actions">
                                <button type="button" class="att-btn-col-action att-btn-col-edit" title="Edit column">✏️</button>
                                <button type="button" class="att-btn-col-action att-btn-col-delete" title="Delete column">✕</button>
                            </div>
                        </th>
                    <?php endforeach; ?>

                    <th rowspan="2" class="att-th-teal-pay" style="min-width:140px;">Remarks</th>
                </tr>

                <!-- Header Row 2 (Weekday Names: TUE, WED, THU...): Exactly 28px tall, font size 14px bold -->
                <tr style="height: 28px;">
                    <?php foreach ($datesList as $dInfo): ?>
                        <th class="att-th-teal-pay-sub att-col-day"><?= $dInfo['weekday'] ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($staffList)): ?>
                    <tr>
                        <td colspan="<?= $totalTableCols ?>" style="padding:24px;color:#64748b;background:#f8fafc;text-align:center;">
                            No attendance records found for this period. Mark attendance on rosters first.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $sumDay = 0; $sumNight = 0; $sumBasic = 0; $sumSick = 0; $sumOff = 0; $sumTotalDays = 0;
                    $customSums = [];
                    foreach ($customCols as $cc) {
                        if ($cc['col_type'] === 'number') {
                            $customSums[$cc['id']] = 0;
                        }
                    }

                    foreach ($staffList as $st): 
                        $sumDay += $st['day_shifts'];
                        $sumNight += $st['night_shifts'];
                        $sumBasic += $st['basic_shifts'];
                        $sumSick += $st['sick_leaves'];
                        $sumOff += $st['off_days'];
                        $sumTotalDays += $st['total_working_days'];
                    ?>
                        <tr style="height:20px;">
                            <td style="font-weight:700;text-align:center;"><?= htmlspecialchars($st['emp_no'] ?? '') ?></td>
                            <td class="att-col-name" style="text-align:center;"><?= htmlspecialchars(strtoupper($st['staff_name'])) ?></td>

                            <!-- Daily Shift Codes -->
                            <?php foreach ($datesList as $dInfo): 
                                $code = $st['daily_codes'][$dInfo['date_str']] ?? '';
                                $isOff = ($code === 'OFF');
                            ?>
                                <td class="att-col-day <?= $isOff ? 'att-off-cell' : '' ?>" style="text-align:center;">
                                    <?= htmlspecialchars($code) ?>
                                </td>
                            <?php endforeach; ?>

                            <!-- Counts: Day, Night, Basic, Sick, Off, Total No. of Days -->
                            <td class="att-col-stat" style="text-align:center;"><?= $st['day_shifts'] ?: '0' ?></td>
                            <td class="att-col-stat" style="text-align:center;"><?= $st['night_shifts'] ?: '0' ?></td>
                            <td class="att-col-stat" style="text-align:center;"><?= $st['basic_shifts'] ?: '0' ?></td>
                            <td class="att-col-stat" style="text-align:center;"><?= $st['sick_leaves'] ?: '0' ?></td>
                            <td class="att-col-stat" style="color:#c00000;text-align:center;"><?= $st['off_days'] ?: '0' ?></td>
                            <td class="att-col-total" style="font-weight:700;text-align:center;"><?= $st['total_working_days'] ?></td>

                            <!-- Custom Column Values -->
                            <?php foreach ($customCols as $cc): 
                                $cVal = $st['custom_values'][$cc['id']] ?? null;
                                $isNum = ($cc['col_type'] === 'number');
                                $valDisp = '';
                                if ($isNum) {
                                    $rawNum = ($cVal !== null && isset($cVal['num_val']) && $cVal['num_val'] !== null && $cVal['num_val'] !== '') ? (float)$cVal['num_val'] : null;
                                    if ($rawNum !== null) {
                                        $valDisp = $rawNum;
                                        $customSums[$cc['id']] += $rawNum;
                                    }
                                } else {
                                    $valDisp = ($cVal !== null && isset($cVal['text_val'])) ? $cVal['text_val'] : '';
                                }
                            ?>
                                <td style="padding:0;text-align:center;">
                                    <input type="text" 
                                           class="att-cell-input att-custom-val-input <?= $isNum ? 'att-custom-num' : 'att-custom-txt' ?>" 
                                           data-emp-no="<?= htmlspecialchars($st['emp_no']) ?>"
                                           data-col-id="<?= $cc['id'] ?>"
                                           data-col-type="<?= $cc['col_type'] ?>"
                                           value="<?= htmlspecialchars($valDisp) ?>" 
                                           style="text-align:center;padding:0;font-size:12px;height:19px;"
                                           placeholder="...">
                                </td>
                            <?php endforeach; ?>

                            <!-- Remarks Input with Conflict Note if any -->
                            <td style="text-align:center;padding:0 2px;">
                                <input type="text" class="att-cell-input att-remarks-input" 
                                       data-emp-no="<?= htmlspecialchars($st['emp_no']) ?>" 
                                       value="<?= htmlspecialchars($st['remarks'] ?? '') ?>" 
                                       style="text-align:center;font-weight:normal;font-size:12px;height:19px;" 
                                       placeholder="...">
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- Bottom TOTAL Row: totals each count column for the whole period -->
                    <tr style="height:20px;font-weight:800;background:#f8fafc;border-top:2px solid #000000;">
                        <td colspan="2" style="text-align:center;font-weight:800;">TOTAL</td>
                        <?php foreach ($datesList as $dInfo): ?>
                            <td></td>
                        <?php endforeach; ?>
                        <td style="font-weight:800;text-align:center;"><?= $sumDay ?></td>
                        <td style="font-weight:800;text-align:center;"><?= $sumNight ?></td>
                        <td style="font-weight:800;text-align:center;"><?= $sumBasic ?></td>
                        <td style="font-weight:800;text-align:center;"><?= $sumSick ?></td>
                        <td style="font-weight:800;color:#c00000;text-align:center;"><?= $sumOff ?></td>
                        <td style="font-weight:800;background:#e2e8f0;text-align:center;"><?= $sumTotalDays ?></td>

                        <!-- Totals for Custom Columns -->
                        <?php foreach ($customCols as $cc): ?>
                            <?php if ($cc['col_type'] === 'number'): ?>
                                <td style="font-weight:800;" class="att-custom-total-cell" data-col-id="<?= $cc['id'] ?>">
                                    <?= $customSums[$cc['id']] ?? 0 ?>
                                </td>
                            <?php else: ?>
                                <td></td>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <td></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Formula Explanation Legend -->
        <div style="font-size:11px;font-weight:bold;color:#111;margin-top:10px;padding:4px;">
            Total No. of Days = Day + Night + Basic + Sick (Off is shown but not counted).
        </div>
    </div>
</main>

<!-- Add Column Modal -->
<div class="att-modal-overlay" id="att-modal-add-col">
    <div class="att-modal" style="max-width: 420px;">
        <div class="att-modal-header">
            <h3>+ Add Column to Payroll</h3>
            <button type="button" class="att-modal-close" data-close-modal="att-modal-add-col">&times;</button>
        </div>
        <form id="att-form-add-col">
            <div class="att-modal-body">
                <div class="att-form-group" style="margin-bottom: 12px;">
                    <label for="att-new-col-name" style="display:block;margin-bottom:4px;font-weight:bold;">Column Name:</label>
                    <input type="text" id="att-new-col-name" name="column_name" class="att-input" style="width:100%;" placeholder="e.g. Basic Salary, Overtime, Deduction" required>
                </div>
                <div class="att-form-group">
                    <label style="display:block;margin-bottom:4px;font-weight:bold;">Column Type:</label>
                    <label style="margin-right: 16px; font-weight: normal; cursor: pointer;">
                        <input type="radio" name="col_type" value="number" checked> Number (accepts digits, shows total)
                    </label>
                    <label style="font-weight: normal; cursor: pointer; display: block; margin-top: 6px;">
                        <input type="radio" name="col_type" value="text"> Text (accepts anything, no total)
                    </label>
                </div>
            </div>
            <div class="att-modal-footer">
                <button type="button" class="att-btn att-btn-secondary" data-close-modal="att-modal-add-col">Cancel</button>
                <button type="submit" class="att-btn att-btn-primary">Add Column</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Column Modal -->
<div class="att-modal-overlay" id="att-modal-edit-col">
    <div class="att-modal" style="max-width: 420px;">
        <div class="att-modal-header">
            <h3>Edit Column</h3>
            <button type="button" class="att-modal-close" data-close-modal="att-modal-edit-col">&times;</button>
        </div>
        <form id="att-form-edit-col">
            <input type="hidden" id="att-edit-col-id" name="column_id" value="">
            <div class="att-modal-body">
                <div class="att-form-group" style="margin-bottom: 12px;">
                    <label for="att-edit-col-name" style="display:block;margin-bottom:4px;font-weight:bold;">Column Name:</label>
                    <input type="text" id="att-edit-col-name" name="column_name" class="att-input" style="width:100%;" required>
                </div>
                <div class="att-form-group">
                    <label style="display:block;margin-bottom:4px;font-weight:bold;">Column Type:</label>
                    <label style="margin-right: 16px; font-weight: normal; cursor: pointer;">
                        <input type="radio" id="att-edit-type-number" name="col_type" value="number"> Number
                    </label>
                    <label style="font-weight: normal; cursor: pointer;">
                        <input type="radio" id="att-edit-type-text" name="col_type" value="text"> Text
                    </label>
                </div>
            </div>
            <div class="att-modal-footer">
                <button type="button" class="att-btn att-btn-secondary" data-close-modal="att-modal-edit-col">Cancel</button>
                <button type="submit" class="att-btn att-btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const startDate = "<?= htmlspecialchars($startDate) ?>";
    const endDate = "<?= htmlspecialchars($endDate) ?>";

    // Modal helpers
    function openModal(id) {
        const m = document.getElementById(id);
        if (m) m.classList.add('att-open');
    }
    function closeModal(id) {
        const m = document.getElementById(id);
        if (m) m.classList.remove('att-open');
    }

    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            closeModal(btn.getAttribute('data-close-modal'));
        });
    });

    // 1. Add Column Modal Trigger
    const addColBtn = document.getElementById('att-btn-add-col');
    if (addColBtn) {
        addColBtn.addEventListener('click', () => {
            document.getElementById('att-new-col-name').value = '';
            openModal('att-modal-add-col');
            document.getElementById('att-new-col-name').focus();
        });
    }

    // Submit Add Column
    const addColForm = document.getElementById('att-form-add-col');
    if (addColForm) {
        addColForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const colName = document.getElementById('att-new-col-name').value.trim();
            const colType = addColForm.elements['col_type'].value;

            if (!colName) return;

            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('column_name', colName);
            fd.append('col_type', colType);

            try {
                const res = await fetch('index.php?action=add_column', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Failed to add column');
                }
            } catch (err) {
                location.reload();
            }
        });
    }

    // 2. Edit Column Trigger
    document.querySelectorAll('.att-btn-col-edit').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const th = btn.closest('th');
            const colId = th.dataset.colId;
            const colName = th.dataset.colName;
            const colType = th.dataset.colType;

            document.getElementById('att-edit-col-id').value = colId;
            document.getElementById('att-edit-col-name').value = colName;
            if (colType === 'text') {
                document.getElementById('att-edit-type-text').checked = true;
            } else {
                document.getElementById('att-edit-type-number').checked = true;
            }

            openModal('att-modal-edit-col');
            document.getElementById('att-edit-col-name').focus();
        });
    });

    // Submit Edit Column
    const editColForm = document.getElementById('att-form-edit-col');
    if (editColForm) {
        editColForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const colId = document.getElementById('att-edit-col-id').value;
            const colName = document.getElementById('att-edit-col-name').value.trim();
            const colType = editColForm.elements['col_type'].value;

            if (!colName) return;

            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('column_id', colId);
            fd.append('column_name', colName);
            fd.append('col_type', colType);

            try {
                const res = await fetch('index.php?action=edit_column', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Failed to update column');
                }
            } catch (err) {
                alert('Connection error');
            }
        });
    }

    // 3. Delete Column Trigger
    document.querySelectorAll('.att-btn-col-delete').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.stopPropagation();
            const th = btn.closest('th');
            const colId = th.dataset.colId;
            const colName = th.dataset.colName;

            if (!confirm(`Delete column "${colName}"? Any entries in this column will no longer be shown or exported.`)) {
                return;
            }

            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('column_id', colId);

            try {
                const res = await fetch('index.php?action=delete_column', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Failed to delete column');
                }
            } catch (err) {
                alert('Connection error');
            }
        });
    });

    // 4. Custom Column Value Typing & Auto-Total Update
    function recalculateColumnTotal(colId) {
        let total = 0;
        document.querySelectorAll(`.att-custom-num[data-col-id="${colId}"]`).forEach(inp => {
            const v = parseFloat(inp.value);
            if (!isNaN(v)) {
                total += v;
            }
        });
        const totalCell = document.querySelector(`.att-custom-total-cell[data-col-id="${colId}"]`);
        if (totalCell) {
            totalCell.textContent = Math.round(total * 100) / 100;
        }
    }

    document.querySelectorAll('.att-custom-val-input').forEach(inp => {
        // Enforce digits only for number columns
        if (inp.dataset.colType === 'number') {
            inp.addEventListener('input', () => {
                // allow digits and optional single decimal point
                inp.value = inp.value.replace(/[^0-9.]/g, '');
                const parts = inp.value.split('.');
                if (parts.length > 2) {
                    inp.value = parts[0] + '.' + parts.slice(1).join('');
                }
                recalculateColumnTotal(inp.dataset.colId);
            });
        }

        // Save value on change
        inp.addEventListener('change', () => {
            const empNo = inp.dataset.empNo;
            const colId = inp.dataset.colId;
            const colType = inp.dataset.colType;
            const val = inp.value.trim();

            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('emp_no', empNo);
            fd.append('column_id', colId);
            fd.append('start_date', startDate);
            fd.append('end_date', endDate);

            if (colType === 'number') {
                fd.append('num_val', val !== '' ? val : '');
            } else {
                fd.append('text_val', val);
            }

            fetch('index.php?action=save_custom_val', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
        });
    });

    // 5. Remarks Auto-Save
    document.querySelectorAll('.att-remarks-input').forEach(inp => {
        inp.addEventListener('change', () => {
            const empNo = inp.dataset.empNo;
            const remarks = inp.value.trim();

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('emp_no', empNo);
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);
            formData.append('remarks', remarks);

            fetch('index.php?action=save_remarks', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
        });
    });
});
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
