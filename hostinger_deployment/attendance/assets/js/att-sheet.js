// Universal Security Services - Attendance Roster Interactive Controller
// File: hostinger_deployment/attendance/assets/js/att-sheet.js

document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('att-roster-table');
    const statusIndicator = document.getElementById('att-status-indicator');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const isLocked = table?.dataset.locked === '1';
    const sheetId = table?.dataset.sheetId || '';

    let saveQueue = [];
    let isSaving = false;
    let saveTimeout = null;

    function setStatus(state, msg) {
        if (!statusIndicator) return;
        statusIndicator.className = 'att-status-indicator att-status-' + state;
        statusIndicator.textContent = msg;
    }

    function showDuplicateWarning(msg) {
        const modal = document.getElementById('att-modal-duplicate-warning');
        const text = document.getElementById('att-dup-warning-msg');
        if (modal && text) {
            text.textContent = msg;
            modal.classList.add('att-open');
        } else {
            alert(msg);
        }
    }

    // Process queued cell updates to server
    function flushSaveQueue() {
        if (saveQueue.length === 0) {
            isSaving = false;
            setStatus('saved', '● Stored in Database (MySQL)');
            return;
        }

        isSaving = true;
        setStatus('saving', 'Saving changes...');
        const item = saveQueue.shift();

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('sheet_id', sheetId);
        formData.append('row_id', item.rowId);
        formData.append('day', item.day);
        formData.append('val', item.val);

        fetch('index.php?action=save_cell', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const input = item.cellEl?.classList?.contains('att-cell-input') ? item.cellEl : item.cellEl?.querySelector?.('.att-cell-input');
                if (input) {
                    input.dataset.prevVal = item.val;
                    input.classList.remove('att-cell-conflict');
                }
                recalculateColumn(item.day);
                recalculateMonthTotals();
            } else {
                setStatus('error', 'Error: ' + (data.error || 'Failed to save'));

                if (data.is_duplicate) {
                    // Revert the conflicting cell back to previous valid value
                    const input = item.cellEl?.classList?.contains('att-cell-input') ? item.cellEl : item.cellEl?.querySelector?.('.att-cell-input');
                    const prev = input?.dataset?.prevVal || '';
                    if (input) {
                        input.value = prev;
                        applyCellColor(input, prev);
                        input.classList.add('att-cell-conflict');
                    }

                    // Highlight the conflicting employee row
                    const row = item.rowEl || input?.closest('tr');
                    if (row) {
                        row.classList.add('att-row-conflict');
                        row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        setTimeout(() => {
                            row.classList.remove('att-row-conflict');
                            input?.classList.remove('att-cell-conflict');
                        }, 6000);
                    }

                    // Display clear warning modal with exact duplicate details
                    showDuplicateWarning(data.error);

                    recalculateColumn(item.day);
                    recalculateMonthTotals();
                } else {
                    alert(data.error || 'Failed to save');
                }
            }
        })
        .catch(err => {
            console.error(err);
            setStatus('error', 'Connection error');
        })
        .finally(() => {
            setTimeout(flushSaveQueue, 40);
        });
    }

    function queueSave(cellEl, rowEl, rowId, day, val) {
        saveQueue = saveQueue.filter(i => !(i.rowId === rowId && i.day === day));
        saveQueue.push({ cellEl, rowEl, rowId, day, val });

        if (saveTimeout) clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            if (!isSaving) {
                flushSaveQueue();
            }
        }, 50);
    }

    // Dynamic Recalculation of Column (Per-Day) Totals
    function updateDeploymentClass(cell, actual, required) {
        if (!cell) return;
        cell.classList.remove('att-deploy-ok', 'att-deploy-less', 'att-deploy-over', 'att-deploy-neutral');
        if (required === 0) {
            if (actual === 0) cell.classList.add('att-deploy-neutral');
            else cell.classList.add('att-deploy-over');
        } else {
            if (actual === required) cell.classList.add('att-deploy-ok');
            else if (actual < required) cell.classList.add('att-deploy-less');
            else cell.classList.add('att-deploy-over');
        }
    }

    function recalculateColumn(day) {
        const dayInputs = document.querySelectorAll(`.att-row-staff .att-cell-input[data-day="${day}"]`);
        let dayCount = 0;
        let nightCount = 0;

        dayInputs.forEach(inp => {
            const v = (inp.value || '').trim().toUpperCase();
            if (v === 'D') dayCount++;
            else if (v === 'N') nightCount++;
        });

        const dayTotalCell = document.querySelector(`.att-col-total-d[data-day="${day}"]`);
        const nightTotalCell = document.querySelector(`.att-col-total-n[data-day="${day}"]`);
        const grandTotalCell = document.querySelector(`.att-col-total-grand[data-day="${day}"]`);

        if (dayTotalCell) {
            dayTotalCell.textContent = dayCount;
            const reqD = parseInt(dayTotalCell.dataset.reqD || '0', 10);
            updateDeploymentClass(dayTotalCell, dayCount, reqD);
            dayTotalCell.title = `Day ${day} Day Shift: Deployed ${dayCount} / Required ${reqD}`;
        }
        if (nightTotalCell) {
            nightTotalCell.textContent = nightCount;
            const reqN = parseInt(nightTotalCell.dataset.reqN || '0', 10);
            updateDeploymentClass(nightTotalCell, nightCount, reqN);
            nightTotalCell.title = `Day ${day} Night Shift: Deployed ${nightCount} / Required ${reqN}`;
        }
        if (grandTotalCell) {
            const grandCount = dayCount + nightCount;
            grandTotalCell.textContent = grandCount;
            const reqTotal = parseInt(grandTotalCell.dataset.reqTotal || '0', 10);
            updateDeploymentClass(grandTotalCell, grandCount, reqTotal);
            grandTotalCell.title = `Day ${day} Total Duty: Deployed ${grandCount} / Required ${reqTotal}`;
        }
    }

    // Dynamic Recalculation of Month Total Ribbon
    function recalculateMonthTotals() {
        let totalDay = 0;
        let totalNight = 0;
        let totalReq = 0;

        document.querySelectorAll('.att-col-total-d').forEach(td => {
            totalDay += parseInt(td.textContent || '0', 10) || 0;
        });
        document.querySelectorAll('.att-col-total-n').forEach(tn => {
            totalNight += parseInt(tn.textContent || '0', 10) || 0;
        });
        document.querySelectorAll('.att-col-total-grand').forEach(tg => {
            totalReq += parseInt(tg.dataset.reqTotal || '0', 10) || 0;
        });

        const totalDuties = totalDay + totalNight;
        const elD = document.getElementById('att-month-total-d');
        const elN = document.getElementById('att-month-total-n');
        const elDuties = document.getElementById('att-month-total-duties');
        const elRate = document.getElementById('att-month-delivery-rate');

        if (elD) elD.textContent = totalDay;
        if (elN) elN.textContent = totalNight;
        if (elDuties) elDuties.textContent = totalDuties;
        if (elRate) {
            const pct = totalReq > 0 ? Math.round((totalDuties / totalReq) * 100) : 0;
            elRate.textContent = pct + '%';
        }
    }

    function applyCellColor(input, val) {
        input.classList.remove('att-code-D', 'att-code-N', 'att-code-OFF', 'att-code-S', 'att-code-B');
        if (['D', 'N', 'OFF', 'S', 'B'].includes(val)) {
            input.classList.add('att-code-' + val);
        }
    }

    function moveToNextDay(currentInput) {
        const currentRow = currentInput.closest('tr');
        const day = parseInt(currentInput.dataset.day, 10);
        const nextInput = currentRow?.querySelector(`.att-cell-input[data-day="${day + 1}"]`);
        if (nextInput) {
            nextInput.focus();
            nextInput.select();
        }
    }

    function moveToPrevDay(currentInput) {
        const currentRow = currentInput.closest('tr');
        const day = parseInt(currentInput.dataset.day, 10);
        const prevInput = currentRow?.querySelector(`.att-cell-input[data-day="${day - 1}"]`);
        if (prevInput) {
            prevInput.focus();
            prevInput.select();
        }
    }

    // Unified Cell Value Handler: Instant Real-time Calculation & Instant Auto-Save
    function handleCellUpdate(input, requestedVal) {
        let val = '';
        const raw = (requestedVal !== undefined ? requestedVal : input.value).trim().toUpperCase();

        if (raw === '' || raw === ' ' || raw === '-' || raw === '0') {
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
        applyCellColor(input, val);

        const rowEl = input.closest('tr');
        const rowId = rowEl ? rowEl.dataset.rowId : null;
        const day = input.dataset.day;

        // 1. Instant calculation & update at the exact same time
        if (day) {
            recalculateColumn(day);
            recalculateMonthTotals();
        }

        // 2. Instant auto-save at the exact same time (both for adding shift and removing shift)
        if (rowId && day) {
            queueSave(input.parentElement, rowEl, rowId, day, val);
        }

        return val;
    }

    if (table && !isLocked) {
        table.querySelectorAll('.att-cell-input').forEach(inp => {
            inp.dataset.prevVal = inp.value.trim().toUpperCase();
            applyCellColor(inp, inp.value.trim().toUpperCase());
        });

        // Initial live sync of all column counts & month summary totals
        for (let d = 1; d <= 31; d++) {
            recalculateColumn(d);
        }
        recalculateMonthTotals();

        table.addEventListener('focusin', (e) => {
            if (e.target.classList.contains('att-cell-input')) {
                e.target.dataset.prevVal = e.target.value.trim().toUpperCase();
                e.target.select();
            }
        });

        table.addEventListener('input', (e) => {
            if (!e.target.classList.contains('att-cell-input')) return;
            const input = e.target;
            const val = handleCellUpdate(input);

            if (['D', 'N', 'B', 'S', 'OFF'].includes(val)) {
                moveToNextDay(input);
            }
        });

        table.addEventListener('keydown', (e) => {
            if (!e.target.classList.contains('att-cell-input')) return;
            const current = e.target;
            const currentRow = current.closest('tr');
            const day = parseInt(current.dataset.day, 10);

            if (e.key === 'Backspace' || e.key === 'Delete') {
                if (current.value !== '') {
                    e.preventDefault();
                    handleCellUpdate(current, '');
                } else if (e.key === 'Backspace') {
                    // Already empty: move to previous day cell
                    e.preventDefault();
                    moveToPrevDay(current);
                }
                return;
            }

            let target = null;
            if (e.key === 'ArrowRight') {
                target = currentRow?.querySelector(`.att-cell-input[data-day="${day + 1}"]`);
                if (target) { e.preventDefault(); target.focus(); target.select(); }
            } else if (e.key === 'ArrowLeft') {
                target = currentRow?.querySelector(`.att-cell-input[data-day="${day - 1}"]`);
                if (target) { e.preventDefault(); target.focus(); target.select(); }
            } else if (e.key === 'ArrowDown' || e.key === 'Enter') {
                const nextRow = currentRow?.nextElementSibling;
                if (nextRow && nextRow.classList.contains('att-row-staff')) {
                    target = nextRow.querySelector(`.att-cell-input[data-day="${day}"]`);
                    if (target) { e.preventDefault(); target.focus(); target.select(); }
                }
            } else if (e.key === 'ArrowUp') {
                const prevRow = currentRow?.previousElementSibling;
                if (prevRow && prevRow.classList.contains('att-row-staff')) {
                    target = prevRow.querySelector(`.att-cell-input[data-day="${day}"]`);
                    if (target) { e.preventDefault(); target.focus(); target.select(); }
                }
            }
        });

        table.addEventListener('paste', (e) => {
            if (!e.target.classList.contains('att-cell-input')) return;
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text');
            const val = handleCellUpdate(e.target, text);
            if (['D', 'N', 'B', 'S', 'OFF'].includes(val)) {
                moveToNextDay(e.target);
            }
        });
    }

    // Modal Management
    const staffDirModal = document.getElementById('att-modal-staff-directory');
    const replaceModal = document.getElementById('att-modal-replace-staff');
    const editModal = document.getElementById('att-modal-edit-staff');
    const siteModal = document.getElementById('att-modal-new-site');
    const renameModal = document.getElementById('att-modal-rename-site');
    const closeModals = document.querySelectorAll('.att-modal-close');

    closeModals.forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.att-modal-overlay').forEach(m => m.classList.remove('att-open'));
        });
    });

    // 1. Staff List & Search
    const btnStaffList = document.getElementById('att-btn-staff-list');
    const btnAddStaff = document.getElementById('att-btn-add-staff');
    const searchInput = document.getElementById('att-search-staff-input');
    const staffListContainer = document.getElementById('att-staff-directory-list');

    function loadStaffDirectory(query = '') {
        if (!staffListContainer) return;
        staffListContainer.innerHTML = '<div style="padding:16px;text-align:center;color:#64748b;">Loading staff...</div>';

        fetch(`index.php?action=staff&q=${encodeURIComponent(query)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            const list = data.staff || [];
            if (list.length === 0) {
                staffListContainer.innerHTML = '<div style="padding:16px;text-align:center;color:#64748b;">No saved staff found matching search.</div>';
                return;
            }

            let html = '';
            list.forEach(st => {
                html += `
                    <div class="att-staff-item">
                        <div>
                            <strong>${escapeHtml(st.emp_name)}</strong> 
                            <span style="color:#64748b;font-size:11px;">(ID: ${escapeHtml(st.emp_no)})</span>
                        </div>
                        <div style="display:flex;gap:6px;">
                            ${sheetId ? `<button type="button" class="att-btn att-btn-primary att-btn-add-to-sheet" data-emp-no="${escapeHtml(st.emp_no)}" data-name="${escapeHtml(st.emp_name)}">+ Add</button>` : ''}
                            <button type="button" class="att-btn att-btn-secondary att-btn-edit-dir-staff" data-emp-no="${escapeHtml(st.emp_no)}" data-name="${escapeHtml(st.emp_name)}">Edit</button>
                            <button type="button" class="att-btn att-btn-danger att-btn-delete-dir-staff" data-emp-no="${escapeHtml(st.emp_no)}" data-name="${escapeHtml(st.emp_name)}">Delete</button>
                        </div>
                    </div>
                `;
            });
            staffListContainer.innerHTML = html;
            attachDirectoryActions();
        });
    }

    function attachDirectoryActions() {
        // Add to sheet
        document.querySelectorAll('.att-btn-add-to-sheet').forEach(btn => {
            btn.addEventListener('click', () => {
                const empNo = btn.dataset.empNo;
                const name = btn.dataset.name;

                const formData = new FormData();
                formData.append('csrf_token', csrfToken);
                formData.append('sheet_id', sheetId);
                formData.append('emp_no', empNo);
                formData.append('name', name);

                fetch('index.php?action=save_staff', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => {
                    if (res.redirected) {
                        window.location.reload();
                    } else {
                        return res.json().then(d => {
                            if (d.success) window.location.reload();
                            else alert(d.error || 'Failed to add staff');
                        });
                    }
                });
            });
        });

        // Edit staff in directory
        document.querySelectorAll('.att-btn-edit-dir-staff').forEach(btn => {
            btn.addEventListener('click', () => {
                const empNo = btn.dataset.empNo;
                const currentName = btn.dataset.name;
                const newName = prompt(`Edit staff name for ID ${empNo}:`, currentName);
                if (newName && newName.trim() !== '') {
                    const formData = new FormData();
                    formData.append('csrf_token', csrfToken);
                    formData.append('emp_no', empNo);
                    formData.append('name', newName.trim());

                    fetch('index.php?action=save_staff', {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(() => loadStaffDirectory(searchInput.value.trim()));
                }
            });
        });

        // Delete from directory (keeps them on existing sheets)
        document.querySelectorAll('.att-btn-delete-dir-staff').forEach(btn => {
            btn.addEventListener('click', () => {
                const empNo = btn.dataset.empNo;
                const name = btn.dataset.name;
                if (!confirm(`Delete "${name}" (${empNo}) from the saved staff directory? (Anyone already on attendance sheets will stay there).`)) {
                    return;
                }

                const formData = new FormData();
                formData.append('csrf_token', csrfToken);
                formData.append('emp_no', empNo);

                fetch('index.php?action=delete_directory_staff', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.json())
                .then(d => {
                    if (d.success) loadStaffDirectory(searchInput.value.trim());
                    else alert(d.error || 'Failed to delete');
                });
            });
        });
    }

    if (btnStaffList) {
        btnStaffList.addEventListener('click', () => {
            document.getElementById('att-dir-modal-title').textContent = 'Saved Staff Directory';
            staffDirModal.classList.add('att-open');
            loadStaffDirectory('');
        });
    }

    if (btnAddStaff) {
        btnAddStaff.addEventListener('click', () => {
            document.getElementById('att-dir-modal-title').textContent = '+ Add Staff to Current Sheet';
            staffDirModal.classList.add('att-open');
            loadStaffDirectory('');
        });
    }

    if (searchInput) {
        let searchTimer = null;
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                loadStaffDirectory(searchInput.value.trim());
            }, 200);
        });
    }

    // Toggle inline new staff form
    const btnShowNewForm = document.getElementById('att-btn-show-new-staff-form');
    const inlineForm = document.getElementById('att-new-staff-inline-form');
    if (btnShowNewForm && inlineForm) {
        btnShowNewForm.addEventListener('click', () => {
            inlineForm.style.display = (inlineForm.style.display === 'none') ? 'block' : 'none';
        });
    }

    // Save inline new staff and add to sheet
    const btnSaveInline = document.getElementById('att-btn-save-inline-staff');
    if (btnSaveInline) {
        btnSaveInline.addEventListener('click', () => {
            const empNo = document.getElementById('att-inline-emp-no').value.trim();
            const name = document.getElementById('att-inline-name').value.trim();

            if (!empNo || !name) {
                alert('Please provide both Employee ID and Full Name.');
                return;
            }

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('sheet_id', sheetId);
            formData.append('emp_no', empNo);
            formData.append('name', name);

            fetch('index.php?action=save_staff', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(() => {
                window.location.reload();
            });
        });
    }

    // Add all on this sheet to list button
    const btnSaveAllSheet = document.getElementById('att-btn-save-sheet-to-list');
    if (btnSaveAllSheet) {
        btnSaveAllSheet.addEventListener('click', () => {
            if (!confirm('Copy all staff members currently on this sheet into the saved staff directory?')) {
                return;
            }

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('sheet_id', sheetId);

            fetch('index.php?action=save_sheet_staff_to_directory', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(d => {
                if (d.success) {
                    alert(`Successfully copied ${d.count} staff members to the saved directory.`);
                } else {
                    alert(d.error || 'Failed to copy staff.');
                }
            });
        });
    }

    // In-row Replace Staff
    table?.querySelectorAll('.att-btn-replace-staff').forEach(btn => {
        btn.addEventListener('click', () => {
            const rowId = btn.dataset.rowId;
            const staffName = btn.dataset.name;
            document.getElementById('att-replace-row-id').value = rowId;
            document.getElementById('att-replace-target-name').textContent = staffName;
            replaceModal.classList.add('att-open');
            loadReplacementDirectory('');
        });
    });

    const replaceSearch = document.getElementById('att-replace-search-input');
    const replaceContainer = document.getElementById('att-replace-directory-list');

    function loadReplacementDirectory(query = '') {
        if (!replaceContainer) return;
        replaceContainer.innerHTML = '<div style="padding:16px;text-align:center;color:#64748b;">Loading staff...</div>';

        fetch(`index.php?action=staff&q=${encodeURIComponent(query)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            const list = data.staff || [];
            if (list.length === 0) {
                replaceContainer.innerHTML = '<div style="padding:16px;text-align:center;color:#64748b;">No saved staff found.</div>';
                return;
            }

            let html = '';
            list.forEach(st => {
                html += `
                    <div class="att-staff-item">
                        <div>
                            <strong>${escapeHtml(st.emp_name)}</strong> 
                            <span style="color:#64748b;font-size:11px;">(ID: ${escapeHtml(st.emp_no)})</span>
                        </div>
                        <button type="button" class="att-btn att-btn-warning att-btn-confirm-replace" data-emp-no="${escapeHtml(st.emp_no)}" data-name="${escapeHtml(st.emp_name)}">Choose</button>
                    </div>
                `;
            });
            replaceContainer.innerHTML = html;

            document.querySelectorAll('.att-btn-confirm-replace').forEach(cBtn => {
                cBtn.addEventListener('click', () => {
                    const rowId = document.getElementById('att-replace-row-id').value;
                    const newEmpNo = cBtn.dataset.empNo;
                    const newName = cBtn.dataset.name;
                    const clearCodes = document.getElementById('att-replace-clear-codes').checked ? 1 : 0;

                    const formData = new FormData();
                    formData.append('csrf_token', csrfToken);
                    formData.append('sheet_id', sheetId);
                    formData.append('row_id', rowId);
                    formData.append('new_emp_no', newEmpNo);
                    formData.append('new_name', newName);
                    formData.append('clear_codes', clearCodes);

                    fetch('index.php?action=replace_staff_row', {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(res => res.json())
                    .then(d => {
                        if (d.success) window.location.reload();
                        else alert(d.error || 'Replacement failed.');
                    });
                });
            });
        });
    }

    if (replaceSearch) {
        let replaceTimer = null;
        replaceSearch.addEventListener('input', () => {
            clearTimeout(replaceTimer);
            replaceTimer = setTimeout(() => {
                loadReplacementDirectory(replaceSearch.value.trim());
            }, 200);
        });
    }

    // In-row Edit Staff
    table?.querySelectorAll('.att-btn-edit-staff').forEach(btn => {
        btn.addEventListener('click', () => {
            const rowId = btn.dataset.rowId;
            const empNo = btn.dataset.empNo;
            const name = btn.dataset.name;

            document.getElementById('att-edit-row-id').value = rowId;
            document.getElementById('att-edit-emp-no').value = empNo;
            document.getElementById('att-edit-name').value = name;
            editModal.classList.add('att-open');
        });
    });

    const btnSubmitEdit = document.getElementById('att-btn-submit-edit-staff');
    if (btnSubmitEdit) {
        btnSubmitEdit.addEventListener('click', () => {
            const rowId = document.getElementById('att-edit-row-id').value;
            const empNo = document.getElementById('att-edit-emp-no').value.trim();
            const name = document.getElementById('att-edit-name').value.trim();

            if (!empNo || !name) {
                alert('Both Emp No and Name are required.');
                return;
            }

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('sheet_id', sheetId);
            formData.append('row_id', rowId);
            formData.append('emp_no', empNo);
            formData.append('name', name);

            fetch('index.php?action=edit_sheet_staff', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(d => {
                if (d.success) window.location.reload();
                else alert(d.error || 'Failed to update');
            });
        });
    }

    // In-row Remove Staff (site and month only with Undo note)
    
    // Clear Row Attendance Cells (Keep Guard ID & Name)
    table?.querySelectorAll('.att-btn-clear-row-cells').forEach(btn => {
        btn.addEventListener('click', () => {
            const rowId = btn.dataset.rowId;
            const staffName = btn.dataset.staffName || 'this guard';

            if (!confirm(`Clear all shift codes for ${staffName} on this site?\n\n(Guard ID & Name will remain on this site. Previous attendance at other sites will NOT be changed).`)) {
                return;
            }

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('sheet_id', sheetId);
            formData.append('row_id', rowId);

            fetch('index.php?action=clear_row_cells', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const row = document.querySelector(`.att-row-staff[data-row-id="${rowId}"]`);
                    if (row) {
                        row.querySelectorAll('.att-cell-input').forEach(inp => {
                            inp.value = '';
                            inp.className = 'att-cell-input';
                        });
                        if (typeof updateMonthTotalSummary === 'function') updateMonthTotalSummary();
                    }
                } else {
                    alert('Error: ' + (data.error || 'Failed to clear shift codes'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('Network or server error while clearing row cells.');
            });
        });
    });

    table?.querySelectorAll('.att-btn-remove-staff').forEach(btn => {
        btn.addEventListener('click', () => {
            const rowId = btn.dataset.rowId;
            const staffName = btn.dataset.staffName;

            if (!confirm(`Remove "${staffName}" from this site and month only?\n(They will stay intact on other sites and months).`)) {
                return;
            }

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('sheet_id', sheetId);
            formData.append('row_id', rowId);

            fetch('index.php?action=remove_staff_row', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        });
    });

    // Site Modals
    const openSiteBtn = document.getElementById('att-btn-add-site');
    if (openSiteBtn && siteModal) {
        openSiteBtn.addEventListener('click', () => siteModal.classList.add('att-open'));
    }

    const deleteSiteBtn = document.getElementById('att-btn-delete-site');
    const deleteSiteModal = document.getElementById('att-modal-delete-site');
    if (deleteSiteBtn && deleteSiteModal) {
        deleteSiteBtn.addEventListener('click', () => deleteSiteModal.classList.add('att-open'));
    }

    const addDeployBtn = document.getElementById('att-btn-add-deployment');
    const addDeployModal = document.getElementById('att-modal-additional-deployment');
    if (addDeployBtn && addDeployModal) {
        addDeployBtn.addEventListener('click', () => addDeployModal.classList.add('att-open'));
    }

    const siteReqsBtn = document.getElementById('att-btn-site-reqs');
    const siteReqsModal = document.getElementById('att-modal-site-reqs');
    if (siteReqsBtn && siteReqsModal) {
        siteReqsBtn.addEventListener('click', () => siteReqsModal.classList.add('att-open'));
    }

    const renameSiteBtn = document.getElementById('att-btn-rename-site');
    if (renameSiteBtn && renameModal) {
        renameSiteBtn.addEventListener('click', () => renameModal.classList.add('att-open'));
    }

    // Copy from previous month
    const btnCopyPrev = document.getElementById('att-btn-copy-prev');
    if (btnCopyPrev) {
        btnCopyPrev.addEventListener('click', () => {
            const siteId = btnCopyPrev.dataset.siteId;
            const curYear = btnCopyPrev.dataset.year;
            const curMonth = btnCopyPrev.dataset.month;

            if (!confirm(`Copy staff roster structure from previous month? Names will be brought across with blank days.`)) {
                return;
            }

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('site_id', siteId);
            formData.append('year', curYear);
            formData.append('month', curMonth);

            fetch('index.php?action=copy_staff', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || `Successfully copied ${data.copied} staff members.`);
                    window.location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        });
    }

    // Lock Sheet
    const btnLock = document.getElementById('att-btn-lock');
    if (btnLock) {
        btnLock.addEventListener('click', () => {
            const sheetId = btnLock.dataset.sheetId;
            const targetLock = btnLock.dataset.lock === '1' ? 1 : 0;
            const actionWord = targetLock ? 'LOCK' : 'UNLOCK';

            if (!confirm(`Are you sure you want to ${actionWord} this attendance sheet?`)) {
                return;
            }

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('sheet_id', sheetId);
            formData.append('lock', targetLock);

            fetch('index.php?action=lock_sheet', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
