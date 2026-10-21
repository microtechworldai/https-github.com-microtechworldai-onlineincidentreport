# Ultimate Security Services (USS) - System Manifest

**Version:** 2.1.0-STRICT  
**Security Level:** Production / High-Assurance  
**Policy:** Strict Admin-Only Control, No Automated Alterations, Zero Demo Files

---

## 1. Directory Structure & Responsibilities

```
hostinger_deployment/
├── .htaccess                     # Server hardening, Gzip/Deflate compression, asset caching, Keep-Alive
├── config.php                    # Application configuration, Argon2id parameters, cryptographic pepper/encryption keys
├── cron_backup.php               # Standalone backup execution script (preserves backups, no automatic purging)
├── database.sql                  # Canonical base schema (admins, sites, guards, incidents, photos, audit logs)
├── index.php                     # Root router / portal redirect
├── install.lock                  # Lock file preventing unauthorized installer execution
├── install.php                   # One-time initialization wizard (disabled once installed)
├── admin/                        # Super Admin Command & Operations (Admin Auth Required)
│   ├── api_live_incidents.php    # Low-latency live incident polling endpoint (<2ms)
│   ├── audit_logs.php            # Cryptographically chained, tamper-evident audit trail viewer
│   ├── backups.php               # Database snapshot management (manual downloads and creation)
│   ├── change_password.php       # Super Admin password management
│   ├── daily_report.php          # Official client dossier generator (DD/MM/YYYY, 24-hr time)
│   ├── dashboard.php             # Real-time incident command dashboard with live streaming
│   ├── guards.php                # Security staff registry, Argon2id credentials, site authorizations
│   ├── incident_edit.php         # Super Admin incident review and modification
│   ├── incident_pdf.php          # Official USS PDF dossier rendering engine
│   ├── incident_view.php         # Incident detail and multi-photo evidence viewer
│   ├── incidents.php             # Full incident management, search, and soft-delete/restore
│   ├── login.php                 # Super Admin secure authentication portal
│   ├── logout.php                # Super Admin session termination
│   ├── qr_poster.php             # Checkpoint QR Poster generator (A4 printable with AES-256 decrypted tokens)
│   ├── security.php              # Central Security & Governance Dashboard (Audit chain verifier, policy monitoring)
│   ├── serve_file.php            # Protected private storage gateway (Super Admin authentication required)
│   ├── settings.php              # Company branding, logo, and system parameters
│   └── sites.php                 # Facility registry, HMAC token hashing, and manual QR regeneration
├── api/
│   └── export_excel.php          # Filtered incident report export to Excel (CSV format)
├── assets/
│   ├── css/
│   │   ├── admin.css             # Super Admin responsive stylesheet
│   │   ├── guard.css             # High-contrast mobile patrol stylesheet
│   │   └── style.css             # Shared utilities and tokens
│   └── js/
│       └── qrcode.min.js         # Client-side QR code generator library
├── g/                            # Guard Patrol Portal (Restricted Guard Realm)
│   ├── index.php                 # Checkpoint QR scan handler, instant session binding, guard login
│   ├── logout.php                # Guard session sign-out
│   ├── submit.php                # High-contrast incident report form, canvas async photo compressor
│   └── success.php               # Submission confirmation screen with shift continuation
├── includes/
│   ├── auth.php                  # Authentication middleware (require_admin_auth, require_guard_auth)
│   ├── db.php                    # PDO Singleton database connection
│   ├── footer.php                # Shared administrative footer
│   ├── functions.php             # Cryptographic helpers (Argon2id, AES-256-GCM, HMAC, hash chaining)
│   └── header.php                # Administrative navigation and header layout
├── storage/
│   └── private/                  # Non-web-accessible private evidence store (.htaccess denied)
│       └── photos/               # Stored incident photographs (integrity hashed with SHA-256)
├── uploads/                      # Public media (company logos only)
└── backups/                      # Preserved SQL database snapshots (never purged automatically)
```

---

## 2. Cryptographic Controls

* **Password Hashing:** `PASSWORD_ARGON2ID` with server-side `APP_PEPPER` outside the database.
* **Checkpoint QR Tokens:** Indexed via `token_hmac = hash_hmac('sha256', token, pepper)`. Reversible value stored encrypted via `openssl_encrypt($token, 'aes-256-gcm', key)`.
* **Audit Trail Integrity:** Each row in `audit_logs` includes `previous_hash` and `record_hash` forming an append-only verifiable cryptographic chain.
* **Photo Verification:** Each evidence photo stores a SHA-256 `file_hash` computed upon upload.
