# Ultimate Security Services (USS) Dubai - Incident Report System
## Architecture & Technical Specification Plan

### 1. System Overview
The **USS Incident Report System** is a mobile-first, enterprise-grade web application engineered for security guards patrolling commercial buildings, residential properties, and hospitals in Dubai, UAE, and for the Super Admin who oversees reporting, follow-ups, audit trails, and daily client report exports (PDF & Excel).

---

### 2. Monorepo Project Structure
```
onlineincidentreport/
├── apps/
│   ├── api/                      # Express + TypeScript REST API
│   │   ├── src/
│   │   │   ├── config/           # Environment configuration (Zod validated)
│   │   │   ├── controllers/      # Admin & Guard route controllers
│   │   │   ├── db/               # Database client, schema & migrations
│   │   │   ├── middleware/       # Auth realm guards, rate limiters, error handler
│   │   │   ├── routes/           # /api/admin/* and /api/guard/*
│   │   │   ├── services/         # Storage (S3/local), PDF (Puppeteer/HTML), Excel (ExcelJS), Audit
│   │   │   ├── utils/            # Reference generator, crypto, password hashing
│   │   │   └── app.ts / server.ts
│   │   ├── tests/                # Automated auth isolation, incident CRUD, export tests
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   └── web/                      # React 18 + Vite + TypeScript + Tailwind CSS
│       ├── public/               # PWA manifests, icons
│       ├── src/
│       │   ├── components/       # UI library (Buttons, Dialogs, Inputs, Badges, Table)
│       │   ├── layouts/          # AdminLayout, GuardLayout
│       │   ├── pages/
│       │   │   ├── admin/        # Dashboard, Incidents, DailyReport, Sites, Guards, Settings, Audit, Backups
│       │   │   └── guard/        # QR Entry, GuardLogin, IncidentForm, SubmitSuccess
│       │   ├── hooks/            # useOfflineDraft, useAuth, useIdempotency
│       │   ├── lib/              # API clients (isolated admin vs guard clients), image compressor
│       │   ├── types/            # Frontend interfaces
│       │   ├── App.tsx
│       │   └── main.tsx
│       ├── package.json
│       ├── tailwind.config.js
│       └── vite.config.ts
│
├── packages/
│   └── shared/                   # Shared types, Zod schemas, default checkbox options
│       ├── src/
│       │   ├── schemas/          # Incident, Auth, Site, Staff validation schemas
│       │   ├── constants/        # Default causes, incident types, authorities, shifts
│       │   └── types/            # Shared DTO interfaces
│       ├── package.json
│       └── tsconfig.json
│
├── infra/                        # Production infrastructure & deployment
│   ├── docker/
│   │   ├── Dockerfile.api
│   │   ├── Dockerfile.web
│   │   └── Dockerfile.caddy
│   ├── caddy/
│   │   └── Caddyfile             # Automatic HTTPS via Let's Encrypt for custom APP_DOMAIN
│   ├── nginx/
│   │   └── nginx.conf            # Alternative production reverse proxy
│   ├── scripts/
│   │   ├── backup.sh             # Nightly automated pg_dump with retention
│   │   ├── restore.sh            # Documented database restore
│   │   └── reset-admin-password.ts
│   └── docker-compose.yml        # Orchestration (Web, API, Postgres, Caddy/Nginx)
│
├── .env.example
├── package.json                  # Root npm workspace configuration
└── README.md                     # Production deployment, DNS records, domain config
```

---

### 3. Database Schema (PostgreSQL)

- **`admin_users`**: `id` (UUID), `username` (unique), `password_hash`, `full_name`, `must_change_password` (boolean), `created_at`, `updated_at`.
- **`guard_accounts`**: `id` (UUID), `staff_id` (unique), `full_name`, `designation`, `username` (unique), `password_hash`, `is_active` (boolean), `created_at`, `updated_at`.
- **`sites`**: `id` (UUID), `name`, `code` (e.g. `DXB-TWR`), `type` (`Commercial` | `Residential` | `Hospital`), `address`, `client_name`, `client_contact`, `is_active` (boolean), `created_at`, `updated_at`.
- **`guard_site_assignments`**: `guard_id` (FK), `site_id` (FK) - defines which sites a guard is authorized to report for.
- **`site_qr_tokens`**: `id` (UUID), `site_id` (FK), `token` (128-bit cryptographically secure hex), `is_revoked` (boolean), `created_at`, `revoked_at`.
- **`option_configs`**: `id` (UUID), `category` (`cause_main` | `disciplinary` | `incident_type` | `notified_authority`), `key`, `label`, `is_default` (boolean), `is_active` (boolean), `sort_order` (int).
- **`incidents`**:
  - `id` (UUID), `ref_number` (unique, e.g. `INC-DXB-20261014-0001`), `idempotency_key` (unique).
  - `site_id` (FK), `guard_id` (FK, nullable for admin manual entry), `reported_by_name`, `reported_by_designation`.
  - `incident_date` (Date in Asia/Dubai), `incident_time` (Time in Asia/Dubai), `server_submitted_at` (UTC timestamp), `shift` (`Day` | `Night`).
  - `cause_main` (JSON array: Incident, Disciplinary, Others), `disciplinary_reasons` (JSON array), `disciplinary_other_text` (nullable text).
  - `incident_types` (JSON array), `incident_other_text` (nullable text).
  - `summary_line` (What, Where, When, Who, Why, How action taken).
  - `description` (Detailed narrative, min 2000 chars recommended).
  - `who_notified` (JSON array), `notified_other_text` (nullable text).
  - **Sign-off / Management section:**
    - `site_supervisor_name`, `site_supervisor_id`, `site_supervisor_signature_url`, `site_supervisor_date`.
    - `recommendation_action_taken` (text).
    - `operations_manager_name`, `operations_manager_signature_url`, `operations_manager_date`.
    - `client_manager_action_taken` (text).
    - `client_manager_name`, `client_manager_signature_url`, `client_manager_date`.
  - `status` (`Submitted` | `Under Review` | `Follow-up Required` | `Resolved` | `Submitted to Client`).
  - `submitted_to_client_at` (nullable UTC timestamp).
  - `deleted_at` (nullable timestamp for soft delete), `created_at`, `updated_at`.
- **`incident_photos`**: `id` (UUID), `incident_id` (FK), `storage_key`, `thumbnail_key`, `original_filename`, `mime_type`, `file_size`, `caption`, `captured_at`, `sort_order`, `created_at`.
- **`incident_notes`**: `id` (UUID), `incident_id` (FK), `admin_id` (FK), `note` (text), `created_at`.
- **`incident_versions`**: `id` (UUID), `incident_id` (FK), `admin_id` (FK), `changes` (JSON), `previous_snapshot` (JSON), `created_at`.
- **`client_submissions`**: `id` (UUID), `site_id` (FK), `report_date` (Date), `format` (`PDF` | `Excel`), `file_url`, `generated_by` (FK admin), `created_at`.
- **`audit_logs`**: `id` (UUID), `actor_type` (`SUPER_ADMIN` | `GUARD` | `SYSTEM`), `actor_id` (UUID nullable), `action` (e.g. `LOGIN`, `SUBMIT_INCIDENT`, `EDIT_INCIDENT`, `GENERATE_QR`), `target_type`, `target_id`, `ip_address`, `user_agent`, `details` (JSON), `created_at`.
- **`system_settings`**: `id` (UUID), `company_name`, `address_line`, `phone`, `fax`, `email`, `logo_url`, `timezone` (default `Asia/Dubai`), `updated_at`.

---

### 4. Dual-Realm Security Architecture

| Security Dimension | Guard Submission Realm | Super Admin Realm |
| :--- | :--- | :--- |
| **Target Audience** | Security guards on mobile devices | Super Admin (Management) |
| **URL Prefix** | `/g/*` (Frontend), `/api/guard/*` (API) | `/admin/*` (Frontend), `/api/admin/*` (API) |
| **Authentication** | Username + Password + Valid Site Token | Super Admin Username + Password |
| **Session Cookie** | `uss_guard_session` | `uss_admin_session` |
| **JWT Secrets & Aud** | `JWT_GUARD_SECRET`, `aud: uss-guard` | `JWT_ADMIN_SECRET`, `aud: uss-admin` |
| **Session Lifetime** | 30 minutes idle, max 2h. Immediate destruction on submit | 8 hours rolling with secure cookie |
| **Cross-Realm Enforcement** | Admin endpoints reject guard tokens with **404 Not Found** (anti-probing) | Guard endpoints reject admin tokens |
| **Password Policy** | Strong staff passwords, generated / managed by Super Admin | Seeded securely via env, forced change on first login |

---

### 5. API Route Map

#### Guard Endpoints (`/api/guard/*`)
- `GET  /api/guard/site-by-token/:token`: Validate site QR token, return site info for pre-selection.
- `POST /api/guard/login`: Guard login (site token + username + password) -> returns isolated Guard session.
- `GET  /api/guard/me`: Returns guard profile and assigned site details.
- `POST /api/guard/incidents`: Upload & submit incident with photos (multipart or pre-upload). Atomic transaction, auto-invalidates session upon completion.
- `POST /api/guard/logout`: Guard manual session destruction.

#### Super Admin Endpoints (`/api/admin/*`)
- `POST /api/admin/login`: Super Admin login -> returns isolated Admin session.
- `POST /api/admin/change-password`: Force change password on first login.
- `GET  /api/admin/me`: Super Admin profile info.
- `GET  /api/admin/dashboard/stats`: KPI summaries (today's count, status distribution, 30-day trends, site breakdown).
- `GET  /api/admin/incidents`: Filtered, paginated, sorted incident list.
- `POST /api/admin/incidents`: Create incident manually on guard's behalf.
- `GET  /api/admin/incidents/:id`: Incident details with photos, notes, versions.
- `PATCH/PUT /api/admin/incidents/:id`: Edit fields, complete management sign-offs (4.7), update status.
- `DELETE /api/admin/incidents/:id`: Soft delete / move to Trash.
- `POST /api/admin/incidents/:id/restore`: Restore soft-deleted incident.
- `DELETE /api/admin/incidents/:id/permanent`: Permanent purge.
- `POST /api/admin/incidents/:id/duplicate`: Duplicate incident as new pre-filled draft.
- `POST /api/admin/incidents/:id/notes`: Add follow-up note.
- `GET  /api/admin/export/incident-pdf/:id`: Single report PDF download.
- `POST /api/admin/export/daily-pdf`: Site daily consolidated PDF report.
- `POST /api/admin/export/daily-excel`: Site daily consolidated Excel report.
- `GET  /api/admin/sites`: List sites with QR status.
- `POST /api/admin/sites`: Create site.
- `PUT  /api/admin/sites/:id`: Update site.
- `POST /api/admin/sites/:id/qr/generate`: Generate / regenerate site QR token.
- `POST /api/admin/sites/:id/qr/revoke`: Revoke site QR token.
- `GET  /api/admin/sites/:id/qr/poster`: Printable QR poster PDF / image preview.
- `GET  /api/admin/staff`: List security guards.
- `POST /api/admin/staff`: Create guard account with password and site assignment.
- `PUT  /api/admin/staff/:id`: Update guard info / site assignments / toggle active.
- `POST /api/admin/staff/:id/reset-password`: Set new password for guard.
- `GET  /api/admin/settings`: Read company settings, shifts, option lists.
- `PUT  /api/admin/settings`: Update settings & custom options.
- `POST /api/admin/settings/logo`: Upload USS company logo.
- `GET  /api/admin/audit-logs`: Paginated audit log search.
- `GET  /api/admin/backups/export`: Trigger database SQL/JSON export dump.

---

### 6. PDF and Excel Generation Architecture
1. **Pixel-Perfect PDF Generation**:
   - Replicates exact USS Incident Report form (A4 portrait):
     - Official company header with logo and Dubai contact block.
     - Split header table: Site / Reporter / Designation vs Date / Time / Shift.
     - Section tables with crisp borders, grey headers, checked `[x]` vs unchecked `[ ]` boxes.
     - Description of Incident with dynamic pagination.
     - Complete management sign-offs (Site Supervisor, Recommendation, Operations Manager, Client Manager).
     - Clean append of photographs in a 2x2 grid with captions, reference numbers, and timestamps.
2. **Consolidated Excel Generation**:
   - Built with **ExcelJS**.
   - Summary Sheet: Clean styled columns (Ref No, Site, Date, Time, Shift, Guard, Cause, Incident, Notified, Description, Recommendation, Action, Status, Photo Count).
   - Metrics / Totals sheet with summary counts.
   - Branded color scheme (USS Navy `#1E3A8A` / Slate) with frozen headers and filters.
