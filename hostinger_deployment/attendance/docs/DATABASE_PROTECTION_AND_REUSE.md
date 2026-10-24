# Ultimate Security Services - Attendance & Payroll Database Protection & Long-Run Reusability Guide

This document explains the enterprise data protection, crash prevention, tamper-proofing, and backup mechanisms implemented for the **Attendance & Payroll Subsystem** (`uss_attendance`).

---

## 1. Zero-Crash & Transactional Integrity (ACID)
All database write operations are wrapped in strict atomic transactions (`beginTransaction()`, `commit()`, and `rollBack()` on any caught `Throwable`).
- **Atomic Operations**: When copying staff between months, updating day codes, or saving custom payroll values, operations succeed completely or roll back entirely. No partial or corrupted state can ever exist.
- **Concurrency & Optimistic Locking**: Every sheet has a `version` counter. Modifying any cell or sheet property increments the version.
- **Sheet Finalization (Lock / Unlock)**: Completed attendance months can be locked with a single click. When a sheet is locked (`status = 'Locked'`), any attempt to edit cells or remove staff is immediately blocked.

---

## 2. Anti-Override & Anti-Destruction Architecture
- **Non-Destructive Soft Deletes**:
  - `att_rows` uses `is_deleted = 1`. If a staff member is removed from a monthly roster, their historical cell values (`att_cells`) are **never deleted**. If the staff member is re-added, their row is reactivated and their records reappear intact.
  - `att_payroll_columns` uses `is_active = 0`. If a custom column (e.g. Basic Salary, Overtime) is deleted, all past values stored in `att_payroll_values` are preserved for historical audit and re-use.
- **Collision-Proof Writes**:
  - Cell updates utilize `INSERT INTO att_cells ... ON DUPLICATE KEY UPDATE code = VALUES(code)` so that concurrent or repeated writes update existing cells safely without failing or generating duplicate rows.
  - Payroll custom values and remarks utilize unique keys per `(period_key, emp_no, column_id)` with `ON DUPLICATE KEY UPDATE`.

---

## 3. Automated & One-Click Backup Engine
The system includes a dedicated `BackupEngine` (`hostinger_deployment/attendance/core/BackupEngine.php`):
1. **Automated Daily Silent Snapshots**:
   - Each day the module is accessed, a timestamped snapshot is automatically created in `hostinger_deployment/attendance/backups/uss_attendance_backup_YYYYMMDD_HHmmss_auto_daily.sql`.
   - The backup engine automatically rotates backups, keeping the 30 most recent snapshots.
2. **One-Click Live SQL Download**:
   - Both the **Attendance** and **Payroll** control bars now include a dedicated `🛡️ Backup Database (.sql)` button.
   - Clicking this button instantly downloads the complete, current SQL database dump directly to your computer.
3. **Protected Storage**:
   - The `backups/` and `database/` folders are shielded with `.htaccess` (`Require all denied`), `web.config` (IIS block), and `index.html` (403 Forbidden) so raw SQL dumps can never be browsed or accessed via public URL.

---

## 4. Master Database File for the Long Run
A complete, standalone SQL file with all table structures, constraints, and current live data has been generated:
- **File Location**: `hostinger_deployment/attendance/database/uss_attendance_master.sql`

### How to Restore or Migrate Anytime:

#### Option A: Localhost (Command Line / PowerShell)
```powershell
# 1. Create database if it does not exist
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS uss_attendance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Restore all tables and data
C:\xampp\mysql\bin\mysql.exe -u root uss_attendance < "C:\Users\micro\OneDrive\Desktop\onlineincidentreport\hostinger_deployment\attendance\database\uss_attendance_master.sql"
```

#### Option B: phpMyAdmin / Hostinger cPanel
1. Open **phpMyAdmin**.
2. Select your database (e.g. `uss_attendance` or `u123456789_uss_attendance`).
3. Click the **Import** tab.
4. Select `uss_attendance_master.sql` (or any timestamped `.sql` from the `backups/` folder).
5. Click **Go / Import**. All tables, constraints, settings, sites, staff, rosters, cells, and custom payroll columns will be restored immediately.
