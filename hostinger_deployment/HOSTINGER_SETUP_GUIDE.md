# Hostinger Production Deployment & Setup Guide
## Ultimate Security Services (USS) Dubai - Incident Reporting System
### Configured for Subdomain on `sprinzo.in` (e.g. `https://incidents.sprinzo.in`)

---

## 1. Overview & Architecture

This package has been converted into a **native, pure PHP 8.x + MySQL (MariaDB)** web application designed specifically to run seamlessly on **Hostinger Shared, Premium, Business, or Cloud Web Hosting**.

### Why this architecture will run stably for years:
- **Zero Daemon Overhead:** Requires no Node.js processes, no Docker, and no PM2 daemon management that can be killed by shared hosting memory limits.
- **Pure Native Execution:** Runs directly via Apache / LiteSpeed + FastCGI PHP, the standard industry architecture for long-term website uptime.
- **Bulletproof Data Safety:** Uses relational MySQL tables with strict foreign keys, transactional integrity, and soft-delete protection.
- **Built-in Automated Backups:** Includes both **one-click manual downloads** (SQL dump & full system ZIP) and an **automated nightly cron script** for 10+ years of uninterrupted data retention.

---

## 2. Step-by-Step Hostinger hPanel Setup

### Step 2.1: Create your Subdomain on `sprinzo.in`
1. Log in to your **Hostinger hPanel** ([https://hpanel.hostinger.com](https://hpanel.hostinger.com)).
2. Under your active hosting plan, select your domain **`sprinzo.in`**.
3. In the left menu, go to **Websites** &rarr; **Subdomains** (or search "Subdomains").
4. In the **Subdomain** field, enter:
   - **`incidents`** (this creates `incidents.sprinzo.in`)
5. Check the box **Custom folder for subdomain** and note the directory path:
   - Typically: `public_html/incidents` or `domains/incidents.sprinzo.in/public_html`
6. Click **Create**.

---

### Step 2.2: Enable Free Let's Encrypt SSL (HTTPS)
1. In Hostinger hPanel, go to **Security** &rarr; **SSL**.
2. Locate `incidents.sprinzo.in` in the list.
3. Click **Install SSL** (or **Activate**).
4. Hostinger will automatically issue a free lifetime auto-renewing SSL certificate. Ensure **Force HTTPS** is toggled **ON**.

---

### Step 2.3: Create a MySQL Database in Hostinger
1. In Hostinger hPanel, go to **Databases** &rarr; **Management** (or search "MySQL Databases").
2. Under **Create a New MySQL Database and User**:
   - **Database Name:** e.g., `uss_incidents` (Hostinger will prefix it, e.g. `u123456789_uss_incidents`).
   - **Username:** e.g., `uss_admin` (Hostinger will prefix it, e.g. `u123456789_uss_admin`).
   - **Password:** Enter a strong password (e.g. `YourStrongDbPass2026!`).
3. Click **Create**.
4. **Copy and save** these 3 values:
   - Full Database Name
   - Full Database Username
   - Database Password

---

### Step 2.4: Upload Files to Hostinger
1. In Hostinger hPanel, go to **Files** &rarr; **File Manager** (or connect via FTP using FileZilla).
2. Open the directory for your subdomain:
   - e.g. `/public_html/incidents/`
3. Upload all files and folders located inside the `hostinger_deployment/` folder:
   ```text
   /public_html/incidents/
   ├── .htaccess
   ├── config.php
   ├── install.php
   ├── database.sql
   ├── cron_backup.php
   ├── index.php
   ├── admin/
   ├── g/
   ├── api/
   ├── includes/
   ├── assets/
   ├── uploads/
   └── backups/
   ```
4. Verify folder permissions:
   - `uploads/` should be `755`
   - `backups/` should be `755`

---

### Step 2.5: Run the 1-Click Web Installer
1. Open your browser and navigate to:
   **`https://incidents.sprinzo.in/install.php`**
2. The pre-flight check will verify PHP, PDO, and GD extensions.
3. Enter your Hostinger MySQL Database credentials created in **Step 2.3**:
   - **Host:** `localhost`
   - **Port:** `3306`
   - **Database Name:** (your prefixed DB name)
   - **Database User:** (your prefixed user)
   - **Database Password:** (your DB password)
   - **Subdomain URL:** `https://incidents.sprinzo.in`
4. Set your initial Super Admin credentials:
   - **Username:** `superadmin`
   - **Password:** `Admin@Sprinzo2026!` (or custom)
5. Click **Install Database & Initialize System**.
6. The installer will automatically:
   - Verify connection.
   - Execute all tables with UTF8mb4 charset and relations.
   - Seed default Dubai company configurations.
   - Save your credentials to `config.php`.
   - Create `install.lock` to secure the system.

---

## 3. How to Setup Nightly Automated Backups (Cron Job)

To ensure **100% of your data and photos remain safe for years**, configure an automated cron job in Hostinger:

1. In Hostinger hPanel, navigate to **Advanced** &rarr; **Cron Jobs**.
2. Select **Custom** schedule.
3. In the schedule fields, enter:
   - **Minute:** `0`
   - **Hour:** `3` (runs at 3:00 AM GST every night)
   - **Day of Month:** `*`
   - **Month:** `*`
   - **Day of Week:** `*`
   *(Cron format: `0 3 * * *`)*
4. In the **Command** input field, enter:
   ```bash
   /usr/bin/php /home/u123456789/public_html/incidents/cron_backup.php
   ```
   *(Replace `/home/u123456789/public_html/incidents/` with your exact home directory path shown at the top of your Hostinger File Manager).*
5. Click **Save**.

### What this automated backup does:
- Creates a complete SQL database dump every night into `backups/`.
- Registers each backup in the database registry.
- Retains 30 days of rolling backups automatically, pruning older records to prevent disk space exhaustion.

---

## 4. User Portals & Access

| Portal | URL | Purpose |
| :--- | :--- | :--- |
| **Gateway Router** | `https://incidents.sprinzo.in/` | Clean entry gate to Guard or Admin portals. |
| **Super Admin Portal** | `https://incidents.sprinzo.in/admin/login.php` | KPI Dashboard, Incident Review, PDF/Excel dossiers, Sites, Guards, Settings, Backups. |
| **Security Guard Portal** | `https://incidents.sprinzo.in/g/index.php` | Mobile-first patrol checkpoint submission portal with photo uploads & offline draft. |
| **Direct QR Link** | `https://incidents.sprinzo.in/g/?token=<TOKEN>` | Directly loads verified facility form on guard mobile camera scan. |

---

## 5. Security & Isolation Architecture

1. **Dual-Realm Isolation:**
   - Super Admin and Security Guards use separate session states.
   - Guards cannot access, probe, or view any `/admin/` URL.
   - Guard sessions are **instantly destroyed on the client and server** the moment a report is submitted.
2. **File Upload Hardening:**
   - The `uploads/.htaccess` file strictly disallows execution of any `.php`, `.phtml`, or executable scripts.
   - Images are processed, compressed, and stripped of EXIF GPS metadata on upload.
3. **Protected Backups:**
   - The `backups/.htaccess` file blocks all direct HTTP access to backup archives. Backups can only be downloaded by an authenticated Super Admin inside the admin portal.

---

## 6. Default Seed Credentials

- **Super Admin:**
  - **URL:** `https://incidents.sprinzo.in/admin/login.php`
  - **Username:** `superadmin`
  - **Password:** `Admin@Sprinzo2026!`
- **Sample Demonstration Site:**
  - **Name:** Burj Crown Tower Downtown
  - **Code:** `BUR01`
- **Sample Demonstration Guard:**
  - **Staff ID:** `USS-1001`
  - **Username:** `tariq.guard`
  - **Password:** `Guard@12345`
