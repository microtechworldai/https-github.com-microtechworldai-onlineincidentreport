# Ultimate Security Services (USS) Dubai - Incident Report System

Production-ready, mobile-first web-based Incident Report System for **Ultimate Security Services (USS), Dubai, UAE**.

Designed specifically for security guards patrolling commercial buildings, residential properties, and hospitals to submit verified incident reports with photographic evidence from their mobile devices, and for the Super Admin to oversee operations, sign off, and generate official daily client dossiers in **PDF** and **Excel (.xlsx)**.

---

## 1. System Architecture & Tech Stack

- **Monorepo Architecture:**
  - `apps/web`: React 18, Vite, TypeScript, Tailwind CSS, Lucide Icons, Mobile-first responsive UX (optimized for 360px+ mobile phones).
  - `apps/api`: Node.js, Express, TypeScript, Zod validation, Scrypt/Argon2id password hashing, Dual-Realm JWT authentication.
  - `packages/shared`: Shared types, Zod schemas, default checkbox configurations.
  - `infra`: Dockerfiles, Docker Compose, Caddy (automatic Let's Encrypt SSL), Nginx, backup & restore shell scripts.
- **Database & Storage:**
  - PostgreSQL relational database with foreign keys, indexes, and soft-delete capabilities.
  - S3-compatible object storage (Cloudflare R2 / AWS S3 / MinIO) with automatic secure local-disk fallback driver.
  - Server-side image sanitization: Magic-byte MIME sniffing, EXIF metadata stripping, randomized storage keys.
- **Document Generation:**
  - **PDF:** Pixel-perfect replication of official USS Incident Report form (A4, checked/unchecked boxes, grey section headers, management sign-off blocks, 2x2 attached photo appendix).
  - **Excel:** Formatted multi-sheet workbook (.xlsx) via `exceljs` with styled header banner, frozen panes, filters, and totals metrics.

---

## 2. Hard Security Isolation Model

The system enforces **two completely isolated authentication realms**:

| Security Dimension | Guard Realm (`/g/*`) | Super Admin Realm (`/admin/*`) |
| :--- | :--- | :--- |
| **User Role** | Guard staff submission account (No administrative role) | Single Super Admin |
| **Authentication** | Username + Password + Valid Site QR Token | Super Admin Username + Password |
| **Cookie Name** | `uss_guard_session` | `uss_admin_session` |
| **JWT Audience** | `aud: uss-guard` | `aud: uss-admin` |
| **Session Lifetime** | 30m idle, max 2h. **Instant destruction on submit** | 8h rolling with secure cookie |
| **Cross-Realm Defense** | Admin endpoints return **404 Not Found** (anti-probing) | Guard endpoints reject admin tokens |

---

## 3. Local Development Setup

### Prerequisites
- Node.js (v20+ recommended)
- npm (v10+)

### Quick Start (Zero-Setup Local Dev)
1. Clone repository and install dependencies:
   ```bash
   git clone <repo-url>
   cd onlineincidentreport
   npm install
   ```

2. Build the shared types package:
   ```bash
   npm run build --workspace=packages/shared
   ```

3. Start backend API and frontend Vite server concurrently:
   ```bash
   npm run dev
   ```
   - **Frontend (Web):** `http://localhost:5173`
   - **Backend (API):** `http://localhost:4000`

4. Run automated test suite:
   ```bash
   npm test
   ```
   *(Runs 28 automated tests verifying dual-realm 404 isolation, PDF/Excel generation, and upload security).*

---

## 4. Production Deployment on a VPS

### 4.1 How to Point Your Own Custom Domain

1. **Obtain your domain name** (e.g. `incidents.mydomain.com`).
2. **Configure DNS Records** in your DNS provider (Cloudflare, GoDaddy, Namecheap, Route53, etc.):
   - **Record Type:** `A`
   - **Host / Name:** `incidents` (or `@` if using root apex domain)
   - **Value / Target:** `<YOUR_VPS_PUBLIC_IPV4_ADDRESS>`
   - **TTL:** Auto or 300 seconds
3. **Configure Environment Variables:**
   Copy `.env.example` to `.env` on your VPS:
   ```bash
   cp .env.example .env
   ```
   Edit `.env` and configure your custom domain:
   ```env
   NODE_ENV=production
   APP_DOMAIN=incidents.mydomain.com
   CORS_ORIGIN=https://incidents.mydomain.com
   SUPERADMIN_USERNAME=superadmin
   SUPERADMIN_PASSWORD=YourStrongPasswordHere123!
   JWT_ADMIN_SECRET=generate_a_random_64_character_hex_secret
   JWT_GUARD_SECRET=generate_another_random_64_character_hex_secret
   ```

### 4.2 Starting Production Containers with Docker Compose

Run the production Docker Compose stack:
```bash
docker compose -f infra/docker-compose.yml up -d --build
```

**Automatic HTTPS:**
Caddy automatically contacts Let's Encrypt, completes the ACME challenge for `APP_DOMAIN`, provisions a valid TLS certificate, and enables HTTP/2 and HTTP/3 with automatic renewal. Zero manual certificate management required.

---

## 5. Super Admin Initialization & Operations

### 5.1 First Login & Password Change
- The initial Super Admin account is seeded from `SUPERADMIN_USERNAME` and `SUPERADMIN_PASSWORD` in `.env`.
- Upon initial login at `https://<APP_DOMAIN>/admin/login`, the system forces an immediate password change.
- There is no public registration and no email forgot-password flow that could be exploited.

### 5.2 Resetting Admin Password via CLI
If the Super Admin password is lost, run the interactive CLI reset command on the server:
```bash
npm run reset-admin-password
```

### 5.3 Creating Sites & Generating QR Posters
1. Log in to the Super Admin Dashboard at `https://<APP_DOMAIN>/admin`.
2. Navigate to **Sites & QR Codes**.
3. Click **Add New Site** (enter Site Name, Code e.g. `MARINA`, Type, Address, Client Contact).
4. An active 128-bit cryptographically secure QR code is automatically generated.
5. Click **View QR Poster** and click **Print Poster**. The site name and code are clearly printed beneath the QR code, ready to be posted at physical security checkpoints.
6. If a QR code is compromised, click **Regenerate QR** or **Revoke QR** to invalidate the old token immediately.

### 5.4 Creating Security Guard Accounts
1. In the Super Admin Dashboard, navigate to **Security Staff**.
2. Click **Create Guard Account**.
3. Enter Staff ID (e.g. `USS-8805`), Officer Name, Designation.
4. Click **Generate Strong Password**.
5. Select the **Authorized Sites** for this guard (the server rejects any attempt by a guard to report for an unassigned site).
6. Note the one-time display of the credentials to hand over to the guard.

---

## 6. Security Guard Patrol Submission Workflow

1. The security guard arrives at their patrol checkpoint (commercial tower, residential community, or hospital).
2. The guard scans the physical QR code poster with their phone's native camera.
3. The phone browser opens `https://<APP_DOMAIN>/g/<site-token>` with the site pre-verified.
4. The guard enters their username and password.
5. The mobile-optimized Incident Form loads with the Site Name, Officer Name, Date, and Dubai Time pre-populated.
6. The guard selects Causes, Incident Types, summary, and types the narrative.
7. The guard taps **Take Camera Photo** to capture live photos (or picks from gallery). Photos are compressed automatically to &lt;1600px long edge.
8. If network coverage drops in basements, the form saves the draft locally in `localStorage`.
9. The guard taps **Submit Incident Report**.
10. The system assigns a unique reference code: `INC-<SITECODE>-YYYYMMDD-0001`.
11. **Auto-Logout:** The guard's session cookie and token are immediately destroyed on both client and server, preventing unauthorized access.

---

## 7. Daily Client Dossier Exports (PDF & Excel)

1. Navigate to **Daily Client Reports** in the Super Admin panel.
2. Select the site and date.
3. Click **Generate Consolidated PDF Report** for a pixel-perfect, A4 multi-page dossier matching official USS forms, complete with checked boxes, grey section bars, management sign-off lines, and 2x2 attached photo grids.
4. Click **Generate Consolidated Excel Workbook (.xlsx)** for an ExcelJS spreadsheet with styled navy headers, frozen panes, filters, and metrics breakdown.

---

## 8. Database Backups & Restore

### Automated Nightly Backups
The automated cron script runs nightly at 03:00 GST:
```bash
infra/scripts/backup.sh
```
Backups are compressed with `gzip`, saved to `/var/backups/uss_incidents`, and retained for 30 days.

### Documented Restore Procedure
To restore a backup:
```bash
chmod +x infra/scripts/restore.sh
infra/scripts/restore.sh /var/backups/uss_incidents/uss_incidents_backup_YYYYMMDD_HHMMSS.sql.gz
```

### On-Demand JSON Dump
Super Admins can also download an on-demand database dump anytime from `https://<APP_DOMAIN>/admin/backups`.

---

## 9. Acceptance Criteria Verification Checklist

- [x] **Only one role exists: Super Admin**; no other role or public signup anywhere.
- [x] **Super Admin creates guard username/password and site QR codes**.
- [x] **Guard scans QR with phone camera**, logs in with username/password, sees only the incident form.
- [x] **Guard attaches photos (camera or gallery)**, submits, and is automatically logged out immediately.
- [x] **Guard can never open any admin page or call any admin API** (28 automated tests prove 404 anti-probing on all admin routes).
- [x] **Super Admin can view, filter, edit, modify and delete incidents site-wise**.
- [x] **All reports are stored permanently** and can be duplicated, re-submitted and followed up.
- [x] **PDF output matches the attached USS format** and includes photos in a 2x2 grid.
- [x] **Daily consolidated site report generates in PDF and Excel**, and only those formats exist.
- [x] **App runs on custom domain over HTTPS** via Docker Compose and Caddy.
