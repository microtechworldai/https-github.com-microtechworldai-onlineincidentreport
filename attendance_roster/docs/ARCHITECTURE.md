# Universal Security Services - Attendance Roster Architecture

## System Principles
1. **Single Source of Truth:** `roster_entries` records all historical attendance facts.
2. **Fact vs. Plan Separation:**
   - **Attendance Entry** = Fact (Staff $X$ worked at Site $S$ on Date $D$).
   - **Assignment** = Plan (Staff $X$ scheduled at Site $S$ from $A$ to $B$). Assignments never alter past facts.
3. **One Folder Rule:** All subsystem code, public assets, templates, migrations, and tests reside in `/attendance_roster/`.

## Data Flow Diagram
```
[Super Admin Input]
        │
        ▼
[index.php (Front Controller)]
        │
        ├──> [RosterEntry::setCellCode]
        │           │
        │           ├──> [RosterConflictDetector] (Rule R3/R4)
        │           │         ├── No Conflict ──> [Save to MySQL roster_entries]
        │           │         └── Collision ────> [Throw RosterConflictException]
        │           │                                       │
        │           │                                       ▼
        │           │                           [Conflict Resolution Modal]
        │           │                           1. Cancel
        │           │                           2. Assign Free Dates Only
        │           │                           3. Move Attendance (R4 Action 3)
        │           │                           4. Force Double Duty (R4 Action 4)
        │           │
        │           └──> [RosterAudit::log] (Rule R5 before/after snapshot)
        │
        ▼
[Real-Time Recalculation Engine (R6)]
   ├── Live Screen: Actual Day, Actual Night, Total Duty
   ├── Export PDF: Formatted A4 Landscape
   └── Export Excel: Native =COUNTIF(col, "D") and =COUNTIF(col, "N")
```

