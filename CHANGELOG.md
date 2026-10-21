# Changelog - USS Incident Report System

All notable updates and security enhancements are recorded here.

---

## [2.2.0-ENTERPRISE-SCALE] - 2026-10-20
### Added
- **Composite B-Tree Indexes**: Added multi-column performance indexes on `incidents (deleted_at, created_at, id)`, `incidents (guard_id, deleted_at, created_at)`, `incidents (site_id, deleted_at, incident_date)`, `incident_photos (incident_id, sort_order, id)`, and `audit_logs (id, created_at)`.
- **Hierarchical Date-Partitioned Storage**: Sharded photo uploads into `/storage/private/photos/{YYYY}/{MM}/` subdirectories to prevent filesystem inode lookup saturation and directory lockups when storing 500,000+ files.
- **Server-Side Pagination & Instant Search for Guards**: Added paginated navigation (25/50/100/250 rows) and instant search by Staff ID, Name, and Username in `admin/guards.php` to handle 2,000+ security staff without DOM lag.
- **Quick Checkpoint Filter in Guard Assignment Modals**: Added real-time text filter to easily search and assign guard checkpoints across 500 patrol facilities.
- **Server-Side Pagination & Search for Sites**: Added paginated navigation and live filter in `admin/sites.php` to handle 500 patrol checkpoints smoothly.
- **Zero-Memory Streaming Excel Export**: Replaced buffered array queries in `api/export_excel.php` with direct unbuffered cursor streaming (`fetch(PDO::FETCH_ASSOC)`) and periodic output buffer flushing, enabling continuous export of 500,000+ incident rows using under 4MB server RAM.

## [2.1.0-STRICT] - 2026-10-20
### Added
- **Argon2id Password Hashing**: Integrated modern memory-hard Argon2id hashing with server-side `APP_PEPPER` outside the database.
- **Cryptographic QR Token Vault**: Replaced raw token queries with keyed `HMAC-SHA256` indexing and `AES-256-GCM` field-level vault encryption.
- **Append-Only Tamper-Evident Audit Chain**: Chained all audit log entries with SHA-256 payload signatures, enabling instant detection of direct database modifications or row deletions.
- **Private Evidence Store (`storage/private/`)**: Isolated evidence photographs behind web-denied permissions, served exclusively via authenticated gateway [`admin/serve_file.php`](file:///c:/Users/micro/OneDrive/Desktop/onlineincidentreport/hostinger_deployment/admin/serve_file.php).
- **Security & Governance Dashboard (`admin/security.php`)**: Real-time cryptographic chain integrity verifier, backup retention status, and credential protection overview.
- **Audit Verification Banner (`admin/audit_logs.php`)**: Live status indicator displaying number of cryptographically verified audit records.
- **Photo Integrity Checksums**: Added SHA-256 `file_hash` tracking to `incident_photos` upon ingestion.

### Changed
- **Zero-Purge Trash Policy**: Enforced immutable retention in Trash; automated deletion scripts removed.
- **Automated Email Dispatch**: Set to disabled by default.
- **Site QR Posters**: Decrypted directly from the secure vault in `admin/qr_poster.php` with manual Super Admin revocation controls.

### Removed
- **Demo & Test Files**: All mock generators, sample accounts, and test fixtures purged from production.
