# Manifest: Universal Security Services Attendance Roster Subsystem

**Subsystem Root Directory:** `/attendance_roster/`  
**Git Branch:** `feature/attendance-roster-isolated`  
**Architecture:** Autonomous, self-contained subsystem with Zero Outside Changes.

---

## 1. Directory Tree & Inventory

```
/attendance_roster/
│
├── config/
│   └── roster_config.php            # Kill-switch, database connector, Super Admin auth check, constants
│
├── docs/
│   ├── ARCHITECTURE.md              # Technical design, strict action rules, data flow
│   └── SCHEMA_ERD.md                # Additive roster_* database schema documentation
│
├── exports/
│   ├── ExcelExport.php              # Real formula-driven Excel (.xls) export with =COUNTIF and SUM
│   └── PdfExport.php                # High-precision A4 Landscape Print & PDF export engine
│
├── migrations/
│   ├── 001_create_roster_tables.sql # Additive DDL creating the 8 isolated roster_* tables
│   ├── 001_rollback_roster_tables.sql# Clean rollback dropping all roster_* tables
│   └── migrate.php                  # CLI migration executor
│
├── public/
│   ├── css/
│   │   └── roster.css               # WCAG AA compliant styles for light and dark themes
│   └── js/
│       └── roster.js                # Live real-time calculations, auto-save, and R4 conflict modal
│
├── src/
│   ├── RosterAudit.php              # Immutable audit trail, before/after JSON snapshots, and undo stack
│   ├── RosterEntry.php              # Strict action rules engine enforcing R1 through R9
│   ├── RosterSite.php               # Site management, requirements, and additional deployment calculator
│   └── RosterStaff.php              # Staff directory, document expiries (SIRA, Visa), free staff finder
│
├── templates/
│   └── roster_sheet.php             # Master interactive roster template, modal dialogs, and summary ribbon
│
├── tests/
│   └── test_rules_r1_to_r9.php      # Automated CLI test suite asserting rules R1 through R9 (18/18 passing)
│
├── index.php                        # Autonomous front controller & action router
└── MANIFEST.md                      # Complete subsystem manifest and file inventory
```

---

## 2. Strict Action Rules Compliance Matrix

| Rule | Description | Implementation & Verification |
|---|---|---|
| **R1** | Past attendance stays where it was worked | Enforced in `RosterEntry::setCellCode`; verified by test `R1.1` & `R1.2`. |
| **R2** | No automatic transfer or back-fill | Assignments never copy past attendance; verified by test `R2.1`. |
| **R3** | One duty per person per date across all sites | Unique composite constraint `uk_roster_staff_date_work` in MySQL + backend pre-check; test `R3.1`. |
| **R4** | Conflict pop-up with 4 mandatory actions | `#modal-roster-conflict` displaying Guard Name, Date, and existing Site Name; test `R4.1`, `R4.Action1-4`. |
| **R5** | Explicit Admin Action & Undo | Immutable `roster_audit` table with before/after state and `undo_token`; test `R5.1` & `R5.2`. |
| **R6** | Live automatic Day/Night recalculation | Instant recalculation on screen, in PDF, and via native Excel `=COUNTIF`; test `R6.1-3`. |
| **R7** | Locked months are read-only | `roster_sheet_status` blocks mutations unless unlocked with reason; test `R7.1` & `R7.2`. |
| **R8** | Super Admin only | `roster_require_super_admin()` protects every route; guests, guards, and supervisors rejected. |
| **R9** | Staff cannot be removed to erase history | `remove_staff` only removes future empty dates; past entries stay strictly intact. |

---

## 3. Kill-Switch Verification

- Controlled in `config/roster_config.php` via `define('ROSTER_ENABLED', true);`.
- Setting to `false` returns HTTP 503 Service Unavailable and halts all execution.
- Executing `migrations/001_rollback_roster_tables.sql` drops all `roster_*` tables, leaving the rest of the USS system completely untouched.
