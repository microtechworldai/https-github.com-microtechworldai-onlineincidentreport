# Security Policy & Cryptographic Key Management

## 1. Cryptographic Secrets & Environment Variables

The following secrets must be provided via server environment variables or `.env` outside web-accessible roots:

| Secret Name | Description | Default / Fallback |
| :--- | :--- | :--- |
| `USS_APP_KEY` | Session signature and token generation secret | Internal production key |
| `USS_APP_PEPPER` | Salt pepper for Argon2id and QR HMAC hashing | Server-side key vault |
| `USS_ENCRYPTION_KEY` | 256-bit AES-GCM vault encryption key | Private application key |

---

## 2. Access Control Model

| Role | Access Scope |
| :--- | :--- |
| **Super Admin** | Full control over sites, security officers, report auditing, PDF export, system settings, database backups, and cryptographic integrity verification. |
| **Security Guard** | Restricted strictly to scanning their authorized checkpoint QR poster, filling the mobile patrol report, and attaching scene photos. No access to other posts, files, or admin functions. |

---

## 3. Data Protection In Transit & At Rest

* **In Transit:** HTTPS / TLS 1.3 enforced.
* **At Rest:** Database backups stored in `backups/` are preserved. Incident evidence photographs reside in `storage/private/` with HTTP direct access denied.
* **Audit Logs:** Chained append-only signatures prevent silent tampering.
