/**
 * Universal Security Services - Attendance Roster Subsystem
 * Interactive Controller (Real-Time Calculation & Auto-Save)
 * File: /attendance_roster/public/js/roster.js
 */

document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('roster-grid-table');
    const isLocked = table?.dataset.locked === '1';
    const siteId = parseInt(table?.dataset.siteId || '0', 10);
    const year = parseInt(table?.dataset.year || '0', 10);
    const month = parseInt(table?.dataset.month || '0', 10);
    const statusEl = document.getElementById('roster-status-badge');

    let saveQueue = [];
    let isSaving = false;
    let saveTimer = null;

    function setStatus(msg, isError = false) {
        if (!statusEl) return;
        statusEl.textContent = msg;
        statusEl.style.color = isError ? '#ef4444' : '#10b981';
    }

    // Modal Manager
    window.openModal = function(id) {
        document.getElementById(id)?.classList.add('roster-open');
    };
    window.closeModal = function(id) {
        document.getElementById(id)?.classList.remove('roster-open');
    };

    // Recalculate Column Totals and WCAG AA Colors in Real Time
    function recalculateColumn(day) {
        const inputs = document.querySelectorAll(`.roster-row-staff .roster-cell-input[data-day="${day}"]`);
        let actualD = 0;
        let actualN = 0;

        inputs.forEach(inp => {
            const v = (inp.value || '').trim().toUpperCase();
            if (v === 'D') actualD++;
            else if (v === 'N') actualN++;
        });

        const actDCell = document.querySelector(`.roster-act-day[data-day="${day}"]`);
        const actNCell = document.querySelector(`.roster-act-night[data-day="${day}"]`);
        const totCell = document.querySelector(`.roster-act-total[data-day="${day}"]`);
        const varDCell = document.querySelector(`.roster-var-day[data-day="${day}"]`);
        const varNCell = document.querySelector(`.roster-var-night[data-day="${day}"]`);

        const reqD = parseInt(actDCell?.dataset.reqD || '0', 10);
        const reqN = parseInt(actNCell?.dataset.reqN || '0', 10);

        // Update counts
        if (actDCell) actDCell.textContent = actualD;
        if (actNCell) actNCell.textContent = actualN;
        if (totCell) totCell.textContent = (actualD + actualN);

        // Update Day Variance & WCAG Color
        if (varDCell && actDCell) {
            const varD = actualD - reqD;
            varDCell.textContent = varD > 0 ? `+${varD}` : varD;

            actDCell.className = 'roster-act-day ' + getDeployClass(actualD, reqD);
            actDCell.innerHTML = formatDeployCell(actualD, reqD);
        }

        // Update Night Variance & WCAG Color
        if (varNCell && actNCell) {
            const varN = actualN - reqN;
            varNCell.textContent = varN > 0 ? `+${varN}` : varN;

            actNCell.className = 'roster-act-night ' + getDeployClass(actualN, reqN);
            actNCell.innerHTML = formatDeployCell(actualN, reqN);
        }

        recalculateMonthTotals();
    }

    function getDeployClass(actual, required) {
        if (required === 0) return 'roster-deploy-total';
        if (actual === required) return 'roster-deploy-ok';
        if (actual < required) return 'roster-deploy-less';
        return 'roster-deploy-over';
    }

    function formatDeployCell(actual, required) {
        if (required === 0) return actual;
        if (actual === required) return `${actual} <span style="font-size:10px;">=</span>`;
        if (actual < required) return `${actual} <span style="font-size:10px;">▼${required - actual}</span>`;
        return `${actual} <span style="font-size:10px;">▲${actual - required}</span>`;
    }

    function recalculateMonthTotals() {
        let totDay = 0;
        let totNight = 0;

        document.querySelectorAll('.roster-act-day').forEach(c => {
            totDay += parseInt(c.textContent || '0', 10) || 0;
        });
        document.querySelectorAll('.roster-act-night').forEach(c => {
            totNight += parseInt(c.textContent || '0', 10) || 0;
        });

        const elDay = document.getElementById('roster-month-tot-day');
        const elNight = document.getElementById('roster-month-tot-night');
        const elGrand = document.getElementById('roster-month-tot-grand');

        if (elDay) elDay.textContent = totDay;
        if (elNight) elNight.textContent = totNight;
        if (elGrand) elGrand.textContent = (totDay + totNight);
    }

    function applyCellCodeStyle(input, code) {
        input.classList.remove('roster-code-D', 'roster-code-N', 'roster-code-OFF', 'roster-code-S', 'roster-code-B');
        if (['D', 'N', 'OFF', 'S', 'B'].includes(code)) {
            input.classList.add('roster-code-' + code);
        }
    }

    // Auto-Save Queue Processor
    function flushSaveQueue() {
        if (saveQueue.length === 0) {
            isSaving = false;
            setStatus('● Stored in Database (MySQL)');
            return;
        }

        isSaving = true;
        setStatus('Saving changes...');
        const item = saveQueue.shift();

        const formData = new FormData();
        formData.append('action', 'save_cell');
        formData.append('staff_id', item.staffId);
        formData.append('site_id', siteId);
        formData.append('date', item.date);
        formData.append('code', item.code);
        if (item.forceDouble) {
            formData.append('force_double', '1');
            formData.append('force_reason', item.forceReason || '');
        }

        fetch('index.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                item.input.dataset.prevVal = item.code;
                recalculateColumn(item.day);
            } else if (data.has_conflict) {
                // Rule R4 Conflict Pop-up
                handleConflictEncountered(data.conflict, item);
            } else {
                alert(data.error || 'Failed to save');
                item.input.value = item.input.dataset.prevVal || '';
                applyCellCodeStyle(item.input, item.input.value);
                recalculateColumn(item.day);
            }
        })
        .catch(err => {
            console.error(err);
            setStatus('Connection error', true);
        })
        .finally(() => {
            setTimeout(flushSaveQueue, 40);
        });
    }

    function queueSave(input, staffId, date, day, code, forceDouble = false, forceReason = '') {
        saveQueue = saveQueue.filter(i => !(i.staffId === staffId && i.date === date));
        saveQueue.push({ input, staffId, date, day, code, forceDouble, forceReason });

        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            if (!isSaving) flushSaveQueue();
        }, 50);
    }

    // Rule R4 Conflict Modal Manager
    let activeConflict = null;
    function handleConflictEncountered(conflict, pendingItem) {
        activeConflict = { conflict, pendingItem };

        const modal = document.getElementById('modal-roster-conflict');
        const msgEl = document.getElementById('roster-conflict-message');
        const reasonInput = document.getElementById('roster-conflict-reason');

        if (msgEl) msgEl.textContent = conflict.message;
        if (reasonInput) reasonInput.value = '';

        openModal('modal-roster-conflict');
    }

    // Bind R4 Conflict Modal Action Buttons
    document.getElementById('btn-conflict-cancel')?.addEventListener('click', () => {
        if (activeConflict) {
            const input = activeConflict.pendingItem.input;
            input.value = input.dataset.prevVal || '';
            applyCellCodeStyle(input, input.value);
            recalculateColumn(activeConflict.pendingItem.day);
        }
        closeModal('modal-roster-conflict');
        activeConflict = null;
    });

    document.getElementById('btn-conflict-skip')?.addEventListener('click', () => {
        // Skip assigning this conflicting date
        if (activeConflict) {
            const input = activeConflict.pendingItem.input;
            input.value = '';
            applyCellCodeStyle(input, '');
            recalculateColumn(activeConflict.pendingItem.day);
        }
        closeModal('modal-roster-conflict');
        activeConflict = null;
    });

    document.getElementById('btn-conflict-move')?.addEventListener('click', () => {
        if (!activeConflict) return;
        const reason = document.getElementById('roster-conflict-reason')?.value.trim();
        if (!reason) {
            alert('A reason is required to move attendance between sites.');
            return;
        }

        const cf = activeConflict.conflict;
        const formData = new FormData();
        formData.append('action', 'move_attendance');
        formData.append('staff_id', cf.staff_id);
        formData.append('date', cf.date);
        formData.append('from_site_id', cf.existing_site_id);
        formData.append('to_site_id', siteId);
        formData.append('reason', reason);

        fetch('index.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(d => {
            if (d.success) {
                closeModal('modal-roster-conflict');
                window.location.reload();
            } else {
                alert(d.error || 'Failed to move attendance');
            }
        });
    });

    document.getElementById('btn-conflict-force')?.addEventListener('click', () => {
        if (!activeConflict) return;
        const reason = document.getElementById('roster-conflict-reason')?.value.trim();
        if (!reason) {
            alert('A reason is mandatory when forcing double duty.');
            return;
        }

        const item = activeConflict.pendingItem;
        queueSave(item.input, item.staffId, item.date, item.day, item.code, true, reason);
        closeModal('modal-roster-conflict');
        activeConflict = null;
    });

    // Cell Event Handlers
    function handleCellInput(input, customVal) {
        let val = '';
        const raw = (customVal !== undefined ? customVal : input.value).trim().toUpperCase();

        if (raw === '' || raw === '-' || raw === '0') {
            val = '';
        } else if (raw === 'O' || raw.endsWith('O') || raw === 'OFF') {
            val = 'OFF';
        } else {
            const lastChar = raw.slice(-1);
            if (['D', 'N', 'S', 'B'].includes(lastChar)) {
                val = lastChar;
            } else {
                val = '';
            }
        }

        input.value = val;
        applyCellCodeStyle(input, val);

        const row = input.closest('tr');
        const staffId = parseInt(row?.dataset.staffId || '0', 10);
        const day = parseInt(input.dataset.day || '0', 10);
        const date = input.dataset.date;

        // Instant simultaneous calculation
        recalculateColumn(day);

        // Instant simultaneous auto-save
        if (staffId && date) {
            queueSave(input, staffId, date, day, val);
        }

        return val;
    }

    if (table && !isLocked) {
        table.querySelectorAll('.roster-cell-input').forEach(inp => {
            inp.dataset.prevVal = inp.value.trim().toUpperCase();
            applyCellCodeStyle(inp, inp.value.trim().toUpperCase());
        });

        // Live initial calculation
        for (let d = 1; d <= 31; d++) recalculateColumn(d);

        table.addEventListener('focusin', (e) => {
            if (e.target.classList.contains('roster-cell-input')) {
                e.target.dataset.prevVal = e.target.value.trim().toUpperCase();
                e.target.select();
            }
        });

        table.addEventListener('input', (e) => {
            if (!e.target.classList.contains('roster-cell-input')) return;
            const val = handleCellInput(e.target);
            if (['D', 'N', 'B', 'S', 'OFF'].includes(val)) {
                const day = parseInt(e.target.dataset.day, 10);
                const next = e.target.closest('tr')?.querySelector(`.roster-cell-input[data-day="${day + 1}"]`);
                if (next) { next.focus(); next.select(); }
            }
        });

        table.addEventListener('keydown', (e) => {
            if (!e.target.classList.contains('roster-cell-input')) return;
            const current = e.target;
            const day = parseInt(current.dataset.day, 10);
            const row = current.closest('tr');

            if (e.key === 'Backspace' || e.key === 'Delete') {
                if (current.value !== '') {
                    e.preventDefault();
                    handleCellInput(current, '');
                } else if (e.key === 'Backspace') {
                    // Step back to previous day
                    e.preventDefault();
                    const prev = row?.querySelector(`.roster-cell-input[data-day="${day - 1}"]`);
                    if (prev) { prev.focus(); prev.select(); }
                }
                return;
            }

            // Arrow keys & Enter
            if (e.key === 'ArrowRight') {
                const next = row?.querySelector(`.roster-cell-input[data-day="${day + 1}"]`);
                if (next) { e.preventDefault(); next.focus(); next.select(); }
            } else if (e.key === 'ArrowLeft') {
                const prev = row?.querySelector(`.roster-cell-input[data-day="${day - 1}"]`);
                if (prev) { e.preventDefault(); prev.focus(); prev.select(); }
            } else if (e.key === 'ArrowDown' || e.key === 'Enter') {
                const nextRow = row?.nextElementSibling;
                const target = nextRow?.querySelector(`.roster-cell-input[data-day="${day}"]`);
                if (target) { e.preventDefault(); target.focus(); target.select(); }
            } else if (e.key === 'ArrowUp') {
                const prevRow = row?.previousElementSibling;
                const target = prevRow?.querySelector(`.roster-cell-input[data-day="${day}"]`);
                if (target) { e.preventDefault(); target.focus(); target.select(); }
            }
        });
    }
});

