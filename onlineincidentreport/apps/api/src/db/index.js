"use strict";
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.db = void 0;
const pg_1 = __importDefault(require("pg"));
const fs_1 = __importDefault(require("fs"));
const path_1 = __importDefault(require("path"));
const index_js_1 = require("../config/index");
const shared_1 = require("@uss/shared");
const { Pool } = pg_1.default;
// PostgreSQL Implementation
class PostgresEngine {
    pool;
    constructor(connectionString) {
        this.pool = new Pool({
            connectionString,
            ssl: connectionString.includes('sslmode=require')
                ? { rejectUnauthorized: false }
                : false,
        });
    }
    async query(sql, params = []) {
        const res = await this.pool.query(sql, params);
        return res.rows;
    }
    async close() {
        await this.pool.end();
    }
}
// Lightweight portable SQL engine for zero-config local development and testing
class PortableSqlEngine {
    dataDir;
    tablesFile;
    data = {};
    constructor(filePath) {
        this.dataDir = path_1.default.dirname(filePath);
        this.tablesFile = filePath.endsWith('.json') ? filePath : `${filePath}.json`;
        if (!fs_1.default.existsSync(this.dataDir)) {
            fs_1.default.mkdirSync(this.dataDir, { recursive: true });
        }
        this.load();
    }
    lastMtime = 0;
    load() {
        if (fs_1.default.existsSync(this.tablesFile)) {
            try {
                const stat = fs_1.default.statSync(this.tablesFile);
                if (stat.mtimeMs !== this.lastMtime) {
                    const raw = fs_1.default.readFileSync(this.tablesFile, 'utf-8');
                    this.data = JSON.parse(raw);
                    this.lastMtime = stat.mtimeMs;
                }
            }
            catch {
                this.data = {};
            }
        }
        else {
            this.data = {};
        }
    }
    save() {
        fs_1.default.writeFileSync(this.tablesFile, JSON.stringify(this.data, null, 2), 'utf-8');
        try {
            this.lastMtime = fs_1.default.statSync(this.tablesFile).mtimeMs;
        }
        catch { }
    }
    getTable(name) {
        this.load();
        if (!this.data[name]) {
            this.data[name] = [];
        }
        return this.data[name];
    }
    async query(sql, params = []) {
        // Basic query simulator for portable mode
        return [];
    }
    async close() {
        this.save();
    }
}
class DatabaseManager {
    isPostgres = false;
    pgPool = null;
    portable = null;
    async init() {
        const dbUrl = index_js_1.config.database.url;
        if (dbUrl.startsWith('postgres://') || dbUrl.startsWith('postgresql://')) {
            try {
                const pool = new Pool({ connectionString: dbUrl });
                // Test connection
                await pool.query('SELECT 1');
                this.pgPool = pool;
                this.isPostgres = true;
                console.log('[DB] Connected successfully to PostgreSQL database.');
                await this.createPostgresTables();
                return;
            }
            catch (err) {
                console.warn(`[DB] PostgreSQL connection to ${dbUrl} not available (${err.message}). Falling back to portable zero-config database engine for local environment.`);
            }
        }
        // Fallback portable storage
        const defaultDbFile = process.env.NODE_ENV === 'test'
            ? './data/test_incidents_db.json'
            : './data/uss_incidents_db.json';
        const storagePath = path_1.default.resolve(index_js_1.projectRoot, process.env.DATABASE_FILE || defaultDbFile);
        this.portable = new PortableSqlEngine(storagePath);
        this.isPostgres = false;
        console.log(`[DB] Initialized zero-config local database engine at: ${storagePath}`);
        this.initPortableDefaults();
    }
    initPortableDefaults() {
        if (!this.portable)
            return;
        const settingsTable = this.portable.getTable('system_settings');
        // Migration: ensure fax is dropped from all records
        for (const s of settingsTable) {
            if ('fax' in s) delete s.fax;
        }
        if (settingsTable.length === 0) {
            settingsTable.push({
                id: 'default-settings',
                company_name: shared_1.DEFAULT_COMPANY_SETTINGS.companyName,
                tagline: shared_1.DEFAULT_COMPANY_SETTINGS.tagline,
                address_line: shared_1.DEFAULT_COMPANY_SETTINGS.addressLine,
                phone: shared_1.DEFAULT_COMPANY_SETTINGS.phone,
                email: shared_1.DEFAULT_COMPANY_SETTINGS.email,
                logo_url: '/assets/uss-logo.png',
                timezone: shared_1.DEFAULT_COMPANY_SETTINGS.timezone,
                shift_options: JSON.stringify(shared_1.DEFAULT_SHIFTS),
                cause_options: JSON.stringify(shared_1.DEFAULT_CAUSES),
                disciplinary_options: JSON.stringify(shared_1.DEFAULT_DISCIPLINARY),
                incident_options: JSON.stringify(shared_1.DEFAULT_INCIDENTS),
                notified_options: JSON.stringify(shared_1.DEFAULT_NOTIFIED),
                updated_at: new Date().toISOString(),
            });
            this.portable.save();
        }
    }
    async createPostgresTables() {
        if (!this.pgPool)
            return;
        const schemaSql = `
      CREATE TABLE IF NOT EXISTS admin_users (
        id UUID PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        full_name VARCHAR(150) NOT NULL,
        must_change_password BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMPTZ DEFAULT NOW(),
        updated_at TIMESTAMPTZ DEFAULT NOW()
      );

      CREATE TABLE IF NOT EXISTS sites (
        id UUID PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        code VARCHAR(20) UNIQUE NOT NULL,
        type VARCHAR(50) NOT NULL,
        address TEXT NOT NULL,
        client_name VARCHAR(150) NOT NULL,
        client_contact VARCHAR(150) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMPTZ DEFAULT NOW(),
        updated_at TIMESTAMPTZ DEFAULT NOW()
      );

      CREATE TABLE IF NOT EXISTS site_qr_tokens (
        id UUID PRIMARY KEY,
        site_id UUID REFERENCES sites(id) ON DELETE CASCADE,
        token VARCHAR(64) UNIQUE NOT NULL,
        is_revoked BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMPTZ DEFAULT NOW(),
        revoked_at TIMESTAMPTZ
      );

      CREATE TABLE IF NOT EXISTS guard_accounts (
        id UUID PRIMARY KEY,
        staff_id VARCHAR(50) UNIQUE NOT NULL,
        full_name VARCHAR(150) NOT NULL,
        designation VARCHAR(100) NOT NULL,
        username VARCHAR(100) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMPTZ DEFAULT NOW(),
        updated_at TIMESTAMPTZ DEFAULT NOW()
      );

      CREATE TABLE IF NOT EXISTS guard_site_assignments (
        guard_id UUID REFERENCES guard_accounts(id) ON DELETE CASCADE,
        site_id UUID REFERENCES sites(id) ON DELETE CASCADE,
        PRIMARY KEY (guard_id, site_id)
      );

      CREATE TABLE IF NOT EXISTS incidents (
        id UUID PRIMARY KEY,
        ref_number VARCHAR(50) UNIQUE NOT NULL,
        idempotency_key VARCHAR(100) UNIQUE,
        site_id UUID REFERENCES sites(id),
        guard_id UUID REFERENCES guard_accounts(id),
        reported_by_name VARCHAR(150) NOT NULL,
        reported_by_designation VARCHAR(100) NOT NULL,
        incident_date DATE NOT NULL,
        incident_time VARCHAR(10) NOT NULL,
        server_submitted_at TIMESTAMPTZ DEFAULT NOW(),
        shift VARCHAR(20) NOT NULL,
        cause_main JSONB NOT NULL DEFAULT '[]'::jsonb,
        disciplinary_reasons JSONB NOT NULL DEFAULT '[]'::jsonb,
        disciplinary_other_text TEXT,
        incident_types JSONB NOT NULL DEFAULT '[]'::jsonb,
        incident_other_text TEXT,
        summary_line TEXT NOT NULL,
        description TEXT NOT NULL,
        who_notified JSONB NOT NULL DEFAULT '[]'::jsonb,
        notified_other_text TEXT,

        site_supervisor_name VARCHAR(150),
        site_supervisor_id VARCHAR(50),
        site_supervisor_signature_url TEXT,
        site_supervisor_date VARCHAR(50),

        recommendation_action_taken TEXT,

        operations_manager_name VARCHAR(150),
        operations_manager_signature_url TEXT,
        operations_manager_date VARCHAR(50),

        client_manager_action_taken TEXT,

        client_manager_name VARCHAR(150),
        client_manager_signature_url TEXT,
        client_manager_date VARCHAR(50),

        status VARCHAR(50) DEFAULT 'Submitted',
        submitted_to_client_at TIMESTAMPTZ,
        deleted_at TIMESTAMPTZ,
        created_at TIMESTAMPTZ DEFAULT NOW(),
        updated_at TIMESTAMPTZ DEFAULT NOW()
      );

      CREATE INDEX IF NOT EXISTS idx_incidents_site ON incidents(site_id);
      CREATE INDEX IF NOT EXISTS idx_incidents_date ON incidents(incident_date);
      CREATE INDEX IF NOT EXISTS idx_incidents_status ON incidents(status);
      CREATE INDEX IF NOT EXISTS idx_incidents_deleted ON incidents(deleted_at);

      CREATE TABLE IF NOT EXISTS incident_photos (
        id UUID PRIMARY KEY,
        incident_id UUID REFERENCES incidents(id) ON DELETE CASCADE,
        storage_key TEXT NOT NULL,
        thumbnail_key TEXT,
        original_filename VARCHAR(255) NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        file_size INTEGER NOT NULL,
        caption TEXT,
        captured_at TIMESTAMPTZ,
        sort_order INTEGER DEFAULT 0,
        created_at TIMESTAMPTZ DEFAULT NOW()
      );

      CREATE TABLE IF NOT EXISTS incident_notes (
        id UUID PRIMARY KEY,
        incident_id UUID REFERENCES incidents(id) ON DELETE CASCADE,
        admin_id UUID REFERENCES admin_users(id),
        note TEXT NOT NULL,
        created_at TIMESTAMPTZ DEFAULT NOW()
      );

      CREATE TABLE IF NOT EXISTS incident_versions (
        id UUID PRIMARY KEY,
        incident_id UUID REFERENCES incidents(id) ON DELETE CASCADE,
        admin_id UUID REFERENCES admin_users(id),
        changes_summary TEXT NOT NULL,
        snapshot JSONB NOT NULL,
        created_at TIMESTAMPTZ DEFAULT NOW()
      );

      CREATE TABLE IF NOT EXISTS client_submissions (
        id UUID PRIMARY KEY,
        site_id UUID REFERENCES sites(id),
        report_date DATE NOT NULL,
        format VARCHAR(20) NOT NULL,
        file_url TEXT NOT NULL,
        generated_by UUID REFERENCES admin_users(id),
        created_at TIMESTAMPTZ DEFAULT NOW()
      );

      CREATE TABLE IF NOT EXISTS audit_logs (
        id UUID PRIMARY KEY,
        actor_type VARCHAR(50) NOT NULL,
        actor_id UUID,
        actor_name VARCHAR(150),
        action VARCHAR(100) NOT NULL,
        target_type VARCHAR(50) NOT NULL,
        target_id UUID,
        ip_address VARCHAR(50),
        user_agent TEXT,
        details JSONB,
        created_at TIMESTAMPTZ DEFAULT NOW()
      );

      CREATE TABLE IF NOT EXISTS system_settings (
        id VARCHAR(50) PRIMARY KEY,
        company_name VARCHAR(200) NOT NULL,
        tagline VARCHAR(200),
        address_line TEXT NOT NULL,
        phone VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL,
        website VARCHAR(200),
        report_title VARCHAR(100) DEFAULT 'INCIDENT REPORT',
        primary_color VARCHAR(30) DEFAULT '#0F2042',
        details_version_mode VARCHAR(30) DEFAULT 'current',
        logo_url TEXT,
        timezone VARCHAR(50) DEFAULT 'Asia/Dubai',
        shift_options JSONB NOT NULL,
        cause_options JSONB NOT NULL,
        disciplinary_options JSONB NOT NULL,
        incident_options JSONB NOT NULL,
        notified_options JSONB NOT NULL,
        updated_at TIMESTAMPTZ DEFAULT NOW()
      );
      ALTER TABLE system_settings DROP COLUMN IF EXISTS fax;
    `;
        await this.pgPool.query(schemaSql);
    }
    // --- Super Admin Data Operations ---
    async getAdminByUsername(username) {
        if (this.isPostgres && this.pgPool) {
            const res = await this.pgPool.query('SELECT * FROM admin_users WHERE LOWER(username) = LOWER($1)', [username]);
            return res.rows[0] || null;
        }
        const table = this.portable.getTable('admin_users');
        return (table.find((u) => u.username.toLowerCase() === username.toLowerCase()) ||
            null);
    }
    async getAdminById(id) {
        if (this.isPostgres && this.pgPool) {
            const res = await this.pgPool.query('SELECT * FROM admin_users WHERE id = $1', [id]);
            return res.rows[0] || null;
        }
        const table = this.portable.getTable('admin_users');
        return table.find((u) => u.id === id) || null;
    }
    async saveAdmin(admin) {
        if (this.isPostgres && this.pgPool) {
            await this.pgPool.query(`INSERT INTO admin_users (id, username, password_hash, full_name, must_change_password, created_at, updated_at)
         VALUES ($1, $2, $3, $4, $5, $6, $7)
         ON CONFLICT (id) DO UPDATE SET
          password_hash = EXCLUDED.password_hash,
          full_name = EXCLUDED.full_name,
          must_change_password = EXCLUDED.must_change_password,
          updated_at = EXCLUDED.updated_at`, [
                admin.id,
                admin.username,
                admin.password_hash,
                admin.full_name,
                admin.must_change_password,
                admin.created_at || new Date().toISOString(),
                admin.updated_at || new Date().toISOString(),
            ]);
            return;
        }
        const table = this.portable.getTable('admin_users');
        const idx = table.findIndex((u) => u.id === admin.id ||
            u.username?.toLowerCase() === admin.username?.toLowerCase());
        if (idx >= 0) {
            table[idx] = { ...table[idx], ...admin };
        }
        else {
            table.push(admin);
        }
        // Remove any remaining duplicate rows for the same username
        const unique = table.filter((u, i) => i ===
            table.findIndex((o) => o.username?.toLowerCase() === u.username?.toLowerCase()));
        table.length = 0;
        table.push(...unique);
        this.portable.save();
    }
    // --- Guard Operations ---
    async getGuardByUsername(username) {
        if (this.isPostgres && this.pgPool) {
            const res = await this.pgPool.query('SELECT * FROM guard_accounts WHERE LOWER(username) = LOWER($1)', [username]);
            if (!res.rows[0])
                return null;
            const guard = res.rows[0];
            const sitesRes = await this.pgPool.query('SELECT site_id FROM guard_site_assignments WHERE guard_id = $1', [guard.id]);
            guard.assignedSites = sitesRes.rows.map((r) => r.site_id);
            return guard;
        }
        const table = this.portable.getTable('guard_accounts');
        const guard = table.find((g) => g.username.toLowerCase() === username.toLowerCase());
        if (!guard)
            return null;
        const assignments = this.portable.getTable('guard_site_assignments');
        guard.assignedSites = assignments
            .filter((a) => a.guard_id === guard.id)
            .map((a) => a.site_id);
        return guard;
    }
    async getGuardById(id) {
        if (this.isPostgres && this.pgPool) {
            const res = await this.pgPool.query('SELECT * FROM guard_accounts WHERE id = $1', [id]);
            if (!res.rows[0])
                return null;
            const guard = res.rows[0];
            const sitesRes = await this.pgPool.query('SELECT site_id FROM guard_site_assignments WHERE guard_id = $1', [guard.id]);
            guard.assignedSites = sitesRes.rows.map((r) => r.site_id);
            return guard;
        }
        const table = this.portable.getTable('guard_accounts');
        const guard = table.find((g) => g.id === id);
        if (!guard)
            return null;
        const assignments = this.portable.getTable('guard_site_assignments');
        guard.assignedSites = assignments
            .filter((a) => a.guard_id === guard.id)
            .map((a) => a.site_id);
        return guard;
    }
    async listGuards() {
        if (this.isPostgres && this.pgPool) {
            const res = await this.pgPool.query('SELECT * FROM guard_accounts ORDER BY created_at DESC');
            const guards = res.rows;
            for (const guard of guards) {
                const sitesRes = await this.pgPool.query('SELECT site_id FROM guard_site_assignments WHERE guard_id = $1', [guard.id]);
                guard.assignedSites = sitesRes.rows.map((r) => r.site_id);
            }
            return guards;
        }
        const table = this.portable.getTable('guard_accounts');
        const assignments = this.portable.getTable('guard_site_assignments');
        return table.map((g) => ({
            ...g,
            assignedSites: assignments
                .filter((a) => a.guard_id === g.id)
                .map((a) => a.site_id),
        }));
    }
    async saveGuard(guard, assignedSiteIds = []) {
        if (this.isPostgres && this.pgPool) {
            await this.pgPool.query(`INSERT INTO guard_accounts (id, staff_id, full_name, designation, username, password_hash, is_active, created_at, updated_at)
         VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)
         ON CONFLICT (id) DO UPDATE SET
          full_name = EXCLUDED.full_name,
          designation = EXCLUDED.designation,
          password_hash = EXCLUDED.password_hash,
          is_active = EXCLUDED.is_active,
          updated_at = EXCLUDED.updated_at`, [
                guard.id,
                guard.staff_id,
                guard.full_name,
                guard.designation,
                guard.username,
                guard.password_hash,
                guard.is_active ?? true,
                guard.created_at || new Date().toISOString(),
                guard.updated_at || new Date().toISOString(),
            ]);
            await this.pgPool.query('DELETE FROM guard_site_assignments WHERE guard_id = $1', [guard.id]);
            for (const siteId of assignedSiteIds) {
                await this.pgPool.query('INSERT INTO guard_site_assignments (guard_id, site_id) VALUES ($1, $2) ON CONFLICT DO NOTHING', [guard.id, siteId]);
            }
            return;
        }
        const table = this.portable.getTable('guard_accounts');
        const idx = table.findIndex((g) => g.id === guard.id ||
            g.username?.toLowerCase() === guard.username?.toLowerCase());
        if (idx >= 0) {
            table[idx] = { ...table[idx], ...guard };
        }
        else {
            table.push(guard);
        }
        const uniqueGuards = table.filter((g, i) => i ===
            table.findIndex((o) => o.username?.toLowerCase() === g.username?.toLowerCase()));
        table.length = 0;
        table.push(...uniqueGuards);
        // Update assignments
        let assignments = this.portable.getTable('guard_site_assignments');
        assignments = assignments.filter((a) => a.guard_id !== guard.id);
        for (const siteId of assignedSiteIds) {
            assignments.push({ guard_id: guard.id, site_id: siteId });
        }
        this.portable.getTable('guard_site_assignments').length = 0;
        this.portable.getTable('guard_site_assignments').push(...assignments);
        this.portable.save();
    }
    async deleteGuard(id) {
        if (this.isPostgres && this.pgPool) {
            await this.pgPool.query('DELETE FROM guard_site_assignments WHERE guard_id = $1', [id]);
            await this.pgPool.query('DELETE FROM guard_accounts WHERE id = $1', [id]);
            return;
        }
        const guards = this.portable.getTable('guard_accounts');
        const filteredGuards = guards.filter((g) => g.id !== id);
        guards.length = 0;
        guards.push(...filteredGuards);
        const assignments = this.portable.getTable('guard_site_assignments');
        const filteredAssignments = assignments.filter((a) => a.guard_id !== id);
        assignments.length = 0;
        assignments.push(...filteredAssignments);
        this.portable.save();
    }
    // --- Sites & QR Tokens ---
    async listSites() {
        if (this.isPostgres && this.pgPool) {
            const res = await this.pgPool.query(`SELECT s.*,
          (SELECT token FROM site_qr_tokens WHERE site_id = s.id AND is_revoked = FALSE ORDER BY created_at DESC LIMIT 1) as active_qr_token
         FROM sites s ORDER BY s.name ASC`);
            return res.rows;
        }
        const sites = this.portable.getTable('sites');
        const qrTokens = this.portable.getTable('site_qr_tokens');
        return sites.map((s) => {
            const activeQr = qrTokens
                .filter((t) => t.site_id === s.id && !t.is_revoked)
                .sort((a, b) => b.created_at.localeCompare(a.created_at))[0];
            return {
                ...s,
                active_qr_token: activeQr?.token || null,
            };
        });
    }
    async getSiteById(id) {
        if (this.isPostgres && this.pgPool) {
            const res = await this.pgPool.query(`SELECT s.*,
          (SELECT token FROM site_qr_tokens WHERE site_id = s.id AND is_revoked = FALSE ORDER BY created_at DESC LIMIT 1) as active_qr_token
         FROM sites s WHERE s.id = $1`, [id]);
            return res.rows[0] || null;
        }
        const sites = this.portable.getTable('sites');
        const site = sites.find((s) => s.id === id);
        if (!site)
            return null;
        const qrTokens = this.portable.getTable('site_qr_tokens');
        const activeQr = qrTokens
            .filter((t) => t.site_id === site.id && !t.is_revoked)
            .sort((a, b) => b.created_at.localeCompare(a.created_at))[0];
        return { ...site, active_qr_token: activeQr?.token || null };
    }
    async getSiteByQrToken(rawToken) {
        const token = (0, shared_1.extractSiteToken)(rawToken);
        if (!token)
            return null;
        if (this.isPostgres && this.pgPool) {
            const res = await this.pgPool.query(`SELECT s.*, t.token as active_qr_token
         FROM site_qr_tokens t
         JOIN sites s ON s.id = t.site_id
         WHERE t.token = $1 AND t.is_revoked = FALSE AND s.is_active = TRUE`, [token]);
            return res.rows[0] || null;
        }
        const qrTokens = this.portable.getTable('site_qr_tokens');
        const qr = qrTokens.find((t) => t.token === token && !t.is_revoked);
        if (!qr)
            return null;
        const site = this.portable.getTable('sites').find((s) => s.id === qr.site_id && s.is_active);
        if (!site)
            return null;
        return { ...site, active_qr_token: qr.token };
    }
    async saveSite(site) {
        if (this.isPostgres && this.pgPool) {
            await this.pgPool.query(`INSERT INTO sites (id, name, code, type, address, client_name, client_contact, is_active, created_at, updated_at)
         VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)
         ON CONFLICT (id) DO UPDATE SET
          name = EXCLUDED.name,
          code = EXCLUDED.code,
          type = EXCLUDED.type,
          address = EXCLUDED.address,
          client_name = EXCLUDED.client_name,
          client_contact = EXCLUDED.client_contact,
          is_active = EXCLUDED.is_active,
          updated_at = EXCLUDED.updated_at`, [
                site.id,
                site.name,
                site.code,
                site.type,
                site.address,
                site.client_name,
                site.client_contact,
                site.is_active ?? true,
                site.created_at || new Date().toISOString(),
                site.updated_at || new Date().toISOString(),
            ]);
            return;
        }
        const table = this.portable.getTable('sites');
        const idx = table.findIndex((s) => s.id === site.id || s.code?.toLowerCase() === site.code?.toLowerCase());
        if (idx >= 0) {
            table[idx] = { ...table[idx], ...site };
        }
        else {
            table.push(site);
        }
        const uniqueSites = table.filter((s, i) => i ===
            table.findIndex((o) => o.code?.toLowerCase() === s.code?.toLowerCase()));
        table.length = 0;
        table.push(...uniqueSites);
        this.portable.save();
    }
    async deleteSite(id) {
        if (this.isPostgres && this.pgPool) {
            await this.pgPool.query('DELETE FROM guard_site_assignments WHERE site_id = $1', [id]);
            await this.pgPool.query('DELETE FROM site_qr_tokens WHERE site_id = $1', [id]);
            await this.pgPool.query('DELETE FROM sites WHERE id = $1', [id]);
            return;
        }
        const sites = this.portable.getTable('sites');
        const filteredSites = sites.filter((s) => s.id !== id);
        sites.length = 0;
        sites.push(...filteredSites);
        const qrTokens = this.portable.getTable('site_qr_tokens');
        const filteredTokens = qrTokens.filter((t) => t.site_id !== id);
        qrTokens.length = 0;
        qrTokens.push(...filteredTokens);
        const assignments = this.portable.getTable('guard_site_assignments');
        const filteredAssignments = assignments.filter((a) => a.site_id !== id);
        assignments.length = 0;
        assignments.push(...filteredAssignments);
        this.portable.save();
    }
    async createSite(data) {
        const siteId = data.id || (await import('../utils/crypto.js')).generateUuid();
        const site = {
            id: siteId,
            name: data.name,
            code: data.code,
            type: data.type || 'Commercial',
            address: data.address || '',
            client_name: data.clientName || data.client_name || '',
            client_contact: data.clientContact || data.client_contact || '',
            is_active: data.isActive ?? (data.is_active ?? true),
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
        };
        await this.saveSite(site);
        return site;
    }
    async generateSiteQr(siteId) {
        const qrId = (await import('../utils/crypto.js')).generateUuid();
        const token = (await import('../utils/crypto.js')).generateSiteQrToken();
        await this.createSiteQrToken(qrId, siteId, token);
        return { id: qrId, token, siteId };
    }
    async createSiteQrToken(id, siteId, token) {
        if (this.isPostgres && this.pgPool) {
            // Revoke prior tokens
            await this.pgPool.query('UPDATE site_qr_tokens SET is_revoked = TRUE, revoked_at = NOW() WHERE site_id = $1 AND is_revoked = FALSE', [siteId]);
            await this.pgPool.query('INSERT INTO site_qr_tokens (id, site_id, token, is_revoked, created_at) VALUES ($1, $2, $3, FALSE, NOW())', [id, siteId, token]);
            return;
        }
        const qrTokens = this.portable.getTable('site_qr_tokens');
        qrTokens.forEach((t) => {
            if (t.site_id === siteId && !t.is_revoked) {
                t.is_revoked = true;
                t.revoked_at = new Date().toISOString();
            }
        });
        qrTokens.push({
            id,
            site_id: siteId,
            token,
            is_revoked: false,
            created_at: new Date().toISOString(),
        });
        this.portable.save();
    }
    async revokeSiteQrToken(siteId) {
        if (this.isPostgres && this.pgPool) {
            await this.pgPool.query('UPDATE site_qr_tokens SET is_revoked = TRUE, revoked_at = NOW() WHERE site_id = $1 AND is_revoked = FALSE', [siteId]);
            return;
        }
        const qrTokens = this.portable.getTable('site_qr_tokens');
        qrTokens.forEach((t) => {
            if (t.site_id === siteId && !t.is_revoked) {
                t.is_revoked = true;
                t.revoked_at = new Date().toISOString();
            }
        });
        this.portable.save();
    }
    // --- Incidents CRUD ---
    async getIncidentsCount(siteId, date) {
        if (this.isPostgres && this.pgPool) {
            let query = 'SELECT COUNT(*) FROM incidents WHERE deleted_at IS NULL';
            const params = [];
            if (siteId) {
                params.push(siteId);
                query += ` AND site_id = $${params.length}`;
            }
            if (date) {
                params.push(date);
                query += ` AND incident_date = $${params.length}`;
            }
            const res = await this.pgPool.query(query, params);
            return parseInt(res.rows[0].count, 10);
        }
        let list = this.portable.getTable('incidents').filter((i) => !i.deleted_at);
        if (siteId)
            list = list.filter((i) => i.site_id === siteId);
        if (date)
            list = list.filter((i) => i.incident_date === date);
        return list.length;
    }
    async listIncidents(filters) {
        const page = filters.page || 1;
        const limit = filters.limit || 50;
        let items = [];
        if (this.isPostgres && this.pgPool) {
            let sql = `
        SELECT i.*, s.name as site_name, s.code as site_code, s.type as site_type
        FROM incidents i
        JOIN sites s ON s.id = i.site_id
        WHERE 1=1
      `;
            const params = [];
            if (filters.onlyDeleted) {
                sql += ' AND i.deleted_at IS NOT NULL';
            }
            else if (!filters.includeDeleted) {
                sql += ' AND i.deleted_at IS NULL';
            }
            if (filters.priority) {
                params.push(filters.priority);
                sql += ` AND i.priority = $${params.length}`;
            }
            if (filters.siteId) {
                params.push(filters.siteId);
                sql += ` AND i.site_id = $${params.length}`;
            }
            if (filters.dateFrom) {
                params.push(filters.dateFrom);
                sql += ` AND i.incident_date >= $${params.length}`;
            }
            if (filters.dateTo) {
                params.push(filters.dateTo);
                sql += ` AND i.incident_date <= $${params.length}`;
            }
            if (filters.status) {
                params.push(filters.status);
                sql += ` AND i.status = $${params.length}`;
            }
            if (filters.guardId) {
                params.push(filters.guardId);
                sql += ` AND i.guard_id = $${params.length}`;
            }
            if (filters.search) {
                params.push(`%${filters.search}%`);
                sql += ` AND (i.ref_number ILIKE $${params.length} OR i.summary_line ILIKE $${params.length} OR i.description ILIKE $${params.length} OR i.reported_by_name ILIKE $${params.length})`;
            }
            if (filters.since) {
                params.push(filters.since);
                sql += ` AND (i.created_at > $${params.length} OR i.updated_at > $${params.length})`;
            }
            sql += ' ORDER BY i.incident_date DESC, i.incident_time DESC';
            const allRows = await this.pgPool.query(sql, params);
            const total = allRows.rows.length;
            const paginated = allRows.rows.slice((page - 1) * limit, page * limit);
            // Fetch photos for each
            for (const item of paginated) {
                const photosRes = await this.pgPool.query('SELECT * FROM incident_photos WHERE incident_id = $1 ORDER BY sort_order ASC', [item.id]);
                item.photos = photosRes.rows;
            }
            return { incidents: paginated, total };
        }
        // Portable mode
        const allIncidents = this.portable.getTable('incidents');
        const sites = this.portable.getTable('sites');
        const photos = this.portable.getTable('incident_photos');
        let filtered = allIncidents.filter((i) => {
            if (filters.onlyDeleted) {
                if (!i.deleted_at)
                    return false;
            }
            else if (!filters.includeDeleted && i.deleted_at) {
                return false;
            }
            if (filters.since) {
                const s = filters.since;
                if ((i.created_at || '') <= s && (i.updated_at || '') <= s)
                    return false;
            }
            if (filters.priority && (i.priority || 'Medium') !== filters.priority)
                return false;
            if (filters.siteId && i.site_id !== filters.siteId)
                return false;
            if (filters.dateFrom && i.incident_date < filters.dateFrom)
                return false;
            if (filters.dateTo && i.incident_date > filters.dateTo)
                return false;
            if (filters.status && i.status !== filters.status)
                return false;
            if (filters.guardId && i.guard_id !== filters.guardId)
                return false;
            if (filters.search) {
                const s = filters.search.toLowerCase();
                const ref = (i.ref_number || '').toLowerCase();
                const desc = (i.description || '').toLowerCase();
                const sum = (i.summary_line || '').toLowerCase();
                const rep = (i.reported_by_name || '').toLowerCase();
                if (!ref.includes(s) &&
                    !desc.includes(s) &&
                    !sum.includes(s) &&
                    !rep.includes(s)) {
                    return false;
                }
            }
            return true;
        });
        filtered.sort((a, b) => {
            const dateCmp = (b.incident_date || '').localeCompare(a.incident_date || '');
            if (dateCmp !== 0)
                return dateCmp;
            return (b.incident_time || '').localeCompare(a.incident_time || '');
        });
        const total = filtered.length;
        const paginated = filtered.slice((page - 1) * limit, page * limit);
        const enriched = paginated.map((i) => {
            const site = sites.find((s) => s.id === i.site_id);
            const incidentPhotos = photos.filter((p) => p.incident_id === i.id);
            return {
                ...i,
                site_name: site?.name || 'Unknown Site',
                site_code: site?.code || 'GEN',
                site_type: site?.type || 'Commercial',
                site_latitude: site?.latitude || null,
                site_longitude: site?.longitude || null,
                photos: incidentPhotos,
            };
        });
        return { incidents: enriched, total };
    }
    async getIncidentById(id) {
        if (this.isPostgres && this.pgPool) {
            const res = await this.pgPool.query(`SELECT i.*, s.name as site_name, s.code as site_code, s.type as site_type, s.address as site_address, s.latitude as site_latitude, s.longitude as site_longitude
         FROM incidents i
         JOIN sites s ON s.id = i.site_id
         WHERE i.id = $1`, [id]);
            if (!res.rows[0])
                return null;
            const incident = res.rows[0];
            const photosRes = await this.pgPool.query('SELECT * FROM incident_photos WHERE incident_id = $1 ORDER BY sort_order ASC', [id]);
            incident.photos = photosRes.rows;
            const notesRes = await this.pgPool.query(`SELECT n.*, a.full_name as admin_name
         FROM incident_notes n
         LEFT JOIN admin_users a ON a.id = n.admin_id
         WHERE n.incident_id = $1 ORDER BY n.created_at DESC`, [id]);
            incident.notes = notesRes.rows;
            const versionsRes = await this.pgPool.query(`SELECT v.*, a.full_name as admin_name
         FROM incident_versions v
         LEFT JOIN admin_users a ON a.id = v.admin_id
         WHERE v.incident_id = $1 ORDER BY v.created_at DESC`, [id]);
            incident.versions = versionsRes.rows;
            return incident;
        }
        const incidents = this.portable.getTable('incidents');
        const incident = incidents.find((i) => i.id === id);
        if (!incident)
            return null;
        const sites = this.portable.getTable('sites');
        const site = sites.find((s) => s.id === incident.site_id);
        const photos = this.portable.getTable('incident_photos');
        const notes = this.portable.getTable('incident_notes');
        const versions = this.portable.getTable('incident_versions');
        const admins = this.portable.getTable('admin_users');
        return {
            ...incident,
            site_name: site?.name || 'Unknown Site',
            site_code: site?.code || 'GEN',
            site_type: site?.type || 'Commercial',
            site_address: site?.address || '',
            site_latitude: site?.latitude || null,
            site_longitude: site?.longitude || null,
            photos: photos.filter((p) => p.incident_id === id),
            notes: notes
                .filter((n) => n.incident_id === id)
                .map((n) => ({
                ...n,
                admin_name: admins.find((a) => a.id === n.admin_id)?.full_name || 'Admin',
            })),
            versions: versions
                .filter((v) => v.incident_id === id)
                .map((v) => ({
                ...v,
                admin_name: admins.find((a) => a.id === v.admin_id)?.full_name || 'Admin',
            })),
        };
    }
    async getIncidentByIdempotencyKey(key) {
        if (this.isPostgres && this.pgPool) {
            const res = await this.pgPool.query('SELECT * FROM incidents WHERE idempotency_key = $1', [key]);
            return res.rows[0] || null;
        }
        const table = this.portable.getTable('incidents');
        return table.find((i) => i.idempotency_key === key) || null;
    }
    async saveIncident(incident) {
        if (this.isPostgres && this.pgPool) {
            await this.pgPool.query(`INSERT INTO incidents (
          id, ref_number, idempotency_key, site_id, guard_id,
          reported_by_name, reported_by_designation, incident_date, incident_time,
          server_submitted_at, shift, cause_main, disciplinary_reasons,
          disciplinary_other_text, incident_types, incident_other_text,
          summary_line, description, who_notified, notified_other_text,
          site_supervisor_name, site_supervisor_id, site_supervisor_signature_url, site_supervisor_date,
          recommendation_action_taken, operations_manager_name, operations_manager_signature_url, operations_manager_date,
          client_manager_action_taken, client_manager_name, client_manager_signature_url, client_manager_date,
          status, submitted_to_client_at, deleted_at, created_at, updated_at
        ) VALUES (
          $1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16,
          $17, $18, $19, $20, $21, $22, $23, $24, $25, $26, $27, $28, $29, $30,
          $31, $32, $33, $34, $35, $36, $37
        ) ON CONFLICT (id) DO UPDATE SET
          site_id = EXCLUDED.site_id,
          incident_date = EXCLUDED.incident_date,
          incident_time = EXCLUDED.incident_time,
          shift = EXCLUDED.shift,
          cause_main = EXCLUDED.cause_main,
          disciplinary_reasons = EXCLUDED.disciplinary_reasons,
          disciplinary_other_text = EXCLUDED.disciplinary_other_text,
          incident_types = EXCLUDED.incident_types,
          incident_other_text = EXCLUDED.incident_other_text,
          summary_line = EXCLUDED.summary_line,
          description = EXCLUDED.description,
          who_notified = EXCLUDED.who_notified,
          notified_other_text = EXCLUDED.notified_other_text,
          site_supervisor_name = EXCLUDED.site_supervisor_name,
          site_supervisor_id = EXCLUDED.site_supervisor_id,
          site_supervisor_signature_url = EXCLUDED.site_supervisor_signature_url,
          site_supervisor_date = EXCLUDED.site_supervisor_date,
          recommendation_action_taken = EXCLUDED.recommendation_action_taken,
          operations_manager_name = EXCLUDED.operations_manager_name,
          operations_manager_signature_url = EXCLUDED.operations_manager_signature_url,
          operations_manager_date = EXCLUDED.operations_manager_date,
          client_manager_action_taken = EXCLUDED.client_manager_action_taken,
          client_manager_name = EXCLUDED.client_manager_name,
          client_manager_signature_url = EXCLUDED.client_manager_signature_url,
          client_manager_date = EXCLUDED.client_manager_date,
          status = EXCLUDED.status,
          submitted_to_client_at = EXCLUDED.submitted_to_client_at,
          deleted_at = EXCLUDED.deleted_at,
          updated_at = EXCLUDED.updated_at`, [
                incident.id,
                incident.ref_number,
                incident.idempotency_key,
                incident.site_id,
                incident.guard_id,
                incident.reported_by_name,
                incident.reported_by_designation,
                incident.incident_date,
                incident.incident_time,
                incident.server_submitted_at || new Date().toISOString(),
                incident.shift,
                JSON.stringify(incident.cause_main || []),
                JSON.stringify(incident.disciplinary_reasons || []),
                incident.disciplinary_other_text || null,
                JSON.stringify(incident.incident_types || []),
                incident.incident_other_text || null,
                incident.summary_line,
                incident.description,
                JSON.stringify(incident.who_notified || []),
                incident.notified_other_text || null,
                incident.site_supervisor_name || incident.siteSupervisorName || null,
                incident.site_supervisor_id || incident.siteSupervisorId || null,
                incident.site_supervisor_signature_url || incident.site_supervisor_signature || incident.siteSupervisorSignature || null,
                incident.site_supervisor_date || incident.siteSupervisorDate || null,
                incident.recommendation_action_taken || incident.recommendationActionTaken || null,
                incident.operations_manager_name || incident.operationsManagerName || null,
                incident.operations_manager_signature_url || incident.operations_manager_signature || incident.operationsManagerSignature || null,
                incident.operations_manager_date || incident.operationsManagerDate || null,
                incident.client_manager_action_taken || incident.clientManagerActionTaken || null,
                incident.client_manager_name || incident.clientManagerName || null,
                incident.client_manager_signature_url || incident.client_manager_signature || incident.clientManagerSignature || null,
                incident.client_manager_date || incident.clientManagerDate || null,
                incident.status || 'Submitted',
                incident.submitted_to_client_at || null,
                incident.deleted_at || null,
                incident.created_at || new Date().toISOString(),
                incident.updated_at || new Date().toISOString(),
            ]);
            return;
        }
        const table = this.portable.getTable('incidents');
        const idx = table.findIndex((i) => i.id === incident.id);
        if (idx >= 0) {
            table[idx] = { ...table[idx], ...incident };
        }
        else {
            table.push(incident);
        }
        this.portable.save();
    }
    async saveIncidentPhoto(photo) {
        if (this.isPostgres && this.pgPool) {
            await this.pgPool.query(`INSERT INTO incident_photos (id, incident_id, storage_key, thumbnail_key, original_filename, mime_type, file_size, caption, captured_at, sort_order, created_at)
         VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)`, [
                photo.id,
                photo.incident_id,
                photo.storage_key,
                photo.thumbnail_key || null,
                photo.original_filename,
                photo.mime_type,
                photo.file_size,
                photo.caption || null,
                photo.captured_at || null,
                photo.sort_order || 0,
                photo.created_at || new Date().toISOString(),
            ]);
            return;
        }
        const table = this.portable.getTable('incident_photos');
        table.push(photo);
        this.portable.save();
    }
    async addIncidentNote(note) {
        if (this.isPostgres && this.pgPool) {
            await this.pgPool.query('INSERT INTO incident_notes (id, incident_id, admin_id, note, created_at) VALUES ($1, $2, $3, $4, $5)', [
                note.id,
                note.incident_id,
                note.admin_id,
                note.note,
                note.created_at || new Date().toISOString(),
            ]);
            return;
        }
        const table = this.portable.getTable('incident_notes');
        table.push(note);
        this.portable.save();
    }
    async addIncidentVersion(version) {
        if (this.isPostgres && this.pgPool) {
            await this.pgPool.query('INSERT INTO incident_versions (id, incident_id, admin_id, changes_summary, snapshot, created_at) VALUES ($1, $2, $3, $4, $5, $6)', [
                version.id,
                version.incident_id,
                version.admin_id,
                version.changes_summary,
                JSON.stringify(version.snapshot),
                version.created_at || new Date().toISOString(),
            ]);
            return;
        }
        const table = this.portable.getTable('incident_versions');
        table.push(version);
        this.portable.save();
    }
    async softDeleteIncident(id) {
        if (this.isPostgres && this.pgPool) {
            await this.pgPool.query('UPDATE incidents SET deleted_at = NOW() WHERE id = $1', [id]);
            return;
        }
        const table = this.portable.getTable('incidents');
        const item = table.find((i) => i.id === id);
        if (item) {
            item.deleted_at = new Date().toISOString();
            this.portable.save();
        }
    }
    async restoreIncident(id) {
        if (this.isPostgres && this.pgPool) {
            await this.pgPool.query('UPDATE incidents SET deleted_at = NULL WHERE id = $1', [id]);
            return;
        }
        const table = this.portable.getTable('incidents');
        const item = table.find((i) => i.id === id);
        if (item) {
            item.deleted_at = null;
            this.portable.save();
        }
    }
    async permanentDeleteIncident(id) {
        if (this.isPostgres && this.pgPool) {
            await this.pgPool.query('DELETE FROM incidents WHERE id = $1', [id]);
            return;
        }
        let table = this.portable.getTable('incidents');
        table = table.filter((i) => i.id !== id);
        this.portable.getTable('incidents').length = 0;
        this.portable.getTable('incidents').push(...table);
        this.portable.save();
    }
    // --- Audit Logs ---
    async addAuditLog(log) {
        if (this.isPostgres && this.pgPool) {
            await this.pgPool.query(`INSERT INTO audit_logs (id, actor_type, actor_id, actor_name, action, target_type, target_id, ip_address, user_agent, details, created_at)
         VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)`, [
                log.id,
                log.actor_type,
                log.actor_id || null,
                log.actor_name || null,
                log.action,
                log.target_type,
                log.target_id || null,
                log.ip_address || null,
                log.user_agent || null,
                JSON.stringify(log.details || {}),
                log.created_at || new Date().toISOString(),
            ]);
            return;
        }
        const table = this.portable.getTable('audit_logs');
        table.push(log);
        this.portable.save();
    }
    async listAuditLogs(limit = 100) {
        if (this.isPostgres && this.pgPool) {
            const res = await this.pgPool.query('SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT $1', [limit]);
            return res.rows;
        }
        const table = this.portable.getTable('audit_logs');
        return [...table]
            .sort((a, b) => b.created_at.localeCompare(a.created_at))
            .slice(0, limit);
    }
    // --- System Settings ---
    async getSettings() {
        if (this.isPostgres && this.pgPool) {
            const res = await this.pgPool.query('SELECT * FROM system_settings LIMIT 1');
            if (res.rows[0])
                return res.rows[0];
        }
        else {
            const table = this.portable.getTable('system_settings');
            if (table[0])
                return table[0];
        }
        return {
            id: 'default-settings',
            company_name: shared_1.DEFAULT_COMPANY_SETTINGS.companyName,
            tagline: shared_1.DEFAULT_COMPANY_SETTINGS.tagline,
            address_line: shared_1.DEFAULT_COMPANY_SETTINGS.addressLine,
            phone: shared_1.DEFAULT_COMPANY_SETTINGS.phone,
            email: shared_1.DEFAULT_COMPANY_SETTINGS.email,
            website: null,
            report_title: 'INCIDENT REPORT',
            primary_color: '#0F2042',
            details_version_mode: 'current',
            logo_url: null,
            timezone: shared_1.DEFAULT_COMPANY_SETTINGS.timezone,
            shift_options: shared_1.DEFAULT_SHIFTS,
            cause_options: shared_1.DEFAULT_CAUSES,
            disciplinary_options: shared_1.DEFAULT_DISCIPLINARY,
            incident_options: shared_1.DEFAULT_INCIDENTS,
            notified_options: shared_1.DEFAULT_NOTIFIED,
            updated_at: new Date().toISOString(),
        };
    }
    async saveSettings(settings) {
        const rawShift = settings.shift_options !== undefined ? settings.shift_options : settings.shiftOptions;
        const shiftOpts = Array.isArray(rawShift)
            ? JSON.stringify(rawShift)
            : rawShift;
        const rawCause = settings.cause_options !== undefined ? settings.cause_options : settings.causeOptions;
        const causeOpts = Array.isArray(rawCause)
            ? JSON.stringify(rawCause)
            : rawCause;
        const rawDisc = settings.disciplinary_options !== undefined ? settings.disciplinary_options : settings.disciplinaryOptions;
        const discOpts = Array.isArray(rawDisc)
            ? JSON.stringify(rawDisc)
            : rawDisc;
        const rawInc = settings.incident_options !== undefined ? settings.incident_options : settings.incidentOptions;
        const incOpts = Array.isArray(rawInc)
            ? JSON.stringify(rawInc)
            : rawInc;
        const rawNot = settings.notified_options !== undefined ? settings.notified_options : settings.notifiedOptions;
        const notOpts = Array.isArray(rawNot)
            ? JSON.stringify(rawNot)
            : rawNot;
        const compName = settings.company_name || settings.companyName || 'ULTIMATE SECURITY SERVICES';
        const tag = settings.tagline !== undefined ? settings.tagline : '';
        const addr = settings.address_line || settings.addressLine || '';
        const ph = settings.phone || '';
        const em = settings.email || '';
        const web = settings.website !== undefined ? settings.website : null;
        const repTitle = settings.report_title || settings.reportTitle || 'INCIDENT REPORT';
        const primColor = settings.primary_color || settings.primaryColor || '#0F2042';
        const dvm = settings.details_version_mode || settings.detailsVersionMode || 'current';
        const logo = settings.logo_url !== undefined ? settings.logo_url : (settings.logoUrl !== undefined ? settings.logoUrl : null);
        const tz = settings.timezone || 'Asia/Dubai';
        if (this.isPostgres && this.pgPool) {
            await this.pgPool.query(`INSERT INTO system_settings (id, company_name, tagline, address_line, phone, email, website, report_title, primary_color, details_version_mode, logo_url, timezone, shift_options, cause_options, disciplinary_options, incident_options, notified_options, updated_at)
         VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17, $18)
         ON CONFLICT (id) DO UPDATE SET
          company_name = EXCLUDED.company_name,
          tagline = EXCLUDED.tagline,
          address_line = EXCLUDED.address_line,
          phone = EXCLUDED.phone,
          email = EXCLUDED.email,
          website = EXCLUDED.website,
          report_title = EXCLUDED.report_title,
          primary_color = EXCLUDED.primary_color,
          details_version_mode = EXCLUDED.details_version_mode,
          logo_url = EXCLUDED.logo_url,
          timezone = EXCLUDED.timezone,
          shift_options = EXCLUDED.shift_options,
          cause_options = EXCLUDED.cause_options,
          disciplinary_options = EXCLUDED.disciplinary_options,
          incident_options = EXCLUDED.incident_options,
          notified_options = EXCLUDED.notified_options,
          updated_at = EXCLUDED.updated_at`, [
                settings.id || 'default-settings',
                compName,
                tag,
                addr,
                ph,
                em,
                web,
                repTitle,
                primColor,
                dvm,
                logo,
                tz,
                shiftOpts,
                causeOpts,
                discOpts,
                incOpts,
                notOpts,
                new Date().toISOString(),
            ]);
            return;
        }
        const table = this.portable.getTable('system_settings');
        const prev = table[0] || {};
        if ('fax' in prev) delete prev.fax;
        table[0] = {
            ...prev,
            ...settings,
            id: settings.id || 'default-settings',
            company_name: compName,
            tagline: tag,
            address_line: addr,
            phone: ph,
            email: em,
            website: web,
            report_title: repTitle,
            primary_color: primColor,
            details_version_mode: dvm,
            logo_url: logo,
            timezone: tz,
            shift_options: shiftOpts,
            cause_options: causeOpts,
            disciplinary_options: discOpts,
            incident_options: incOpts,
            notified_options: notOpts,
            updated_at: new Date().toISOString(),
        };
        delete table[0].fax;
        this.portable.save();
    }
    // --- Client Submissions History ---
    async addClientSubmission(sub) {
        if (this.isPostgres && this.pgPool) {
            await this.pgPool.query(`INSERT INTO client_submissions (id, site_id, report_date, format, file_url, generated_by, created_at)
         VALUES ($1, $2, $3, $4, $5, $6, $7)`, [
                sub.id,
                sub.site_id,
                sub.report_date,
                sub.format,
                sub.file_url,
                sub.generated_by,
                sub.created_at || new Date().toISOString(),
            ]);
            return;
        }
        const table = this.portable.getTable('client_submissions');
        table.push(sub);
        this.portable.save();
    }
    async listClientSubmissions(siteId) {
        if (this.isPostgres && this.pgPool) {
            let sql = 'SELECT * FROM client_submissions';
            const params = [];
            if (siteId) {
                params.push(siteId);
                sql += ' WHERE site_id = $1';
            }
            sql += ' ORDER BY created_at DESC';
            const res = await this.pgPool.query(sql, params);
            return res.rows;
        }
        const table = this.portable.getTable('client_submissions');
        let list = [...table];
        if (siteId)
            list = list.filter((s) => s.site_id === siteId);
        return list.sort((a, b) => b.created_at.localeCompare(a.created_at));
    }
}
exports.db = new DatabaseManager();
