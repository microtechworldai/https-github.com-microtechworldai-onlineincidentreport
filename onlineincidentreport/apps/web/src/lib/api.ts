import { extractSiteToken } from '@uss/shared';

const BASE_URL = '';

export class ApiError extends Error {
  status: number;
  data: any;

  constructor(message: string, status: number, data?: any) {
    super(message);
    this.status = status;
    this.data = data;
  }
}

// ==========================================
// GUARD API CLIENT (ISOLATED REALM)
// ==========================================
export const guardApi = {
  getToken(): string | null {
    return sessionStorage.getItem('uss_guard_token');
  },

  setToken(token: string | null) {
    if (token) {
      sessionStorage.setItem('uss_guard_token', token);
    } else {
      sessionStorage.removeItem('uss_guard_token');
    }
  },

  async request(path: string, options: RequestInit = {}) {
    const token = this.getToken();
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Cache-Control': 'no-cache, no-store, must-revalidate',
      'Pragma': 'no-cache',
      ...(options.headers as any),
    };
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    const res = await fetch(`${BASE_URL}/api/guard${path}`, {
      cache: 'no-store',
      ...options,
      headers,
      credentials: 'include',
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      throw new ApiError(data.error || 'Request failed', res.status, data);
    }
    return data;
  },

  async getSiteByToken(token: string) {
    const cleanToken = extractSiteToken(token);
    return this.request(`/site-by-token/${encodeURIComponent(cleanToken || token)}`);
  },

  async login(payload: { siteToken: string; username: string; password: string }) {
    const cleanToken = extractSiteToken(payload.siteToken);
    const res = await this.request('/login', {
      method: 'POST',
      body: JSON.stringify({ ...payload, siteToken: cleanToken || payload.siteToken }),
    });
    if (res.token) {
      this.setToken(res.token);
    }
    return res;
  },

  async getMe() {
    return this.request('/me');
  },

  async getSettings() {
    return this.request('/settings');
  },

  async submitIncident(payload: any) {
    const res = await this.request('/incidents', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    // Auto logout on client side and purge drafts
    this.setToken(null);
    try {
      sessionStorage.removeItem('uss_guard_token');
      for (let i = localStorage.length - 1; i >= 0; i--) {
        const k = localStorage.key(i);
        if (k && k.startsWith('uss_draft_')) {
          localStorage.removeItem(k);
        }
      }
    } catch (_) {}
    return res;
  },

  async logout() {
    try {
      await this.request('/logout', { method: 'POST' });
    } finally {
      this.setToken(null);
      try {
        sessionStorage.removeItem('uss_guard_token');
        for (let i = localStorage.length - 1; i >= 0; i--) {
          const k = localStorage.key(i);
          if (k && k.startsWith('uss_draft_')) {
            localStorage.removeItem(k);
          }
        }
      } catch (_) {}
    }
  },
};

// ==========================================
// SUPER ADMIN API CLIENT (ISOLATED REALM)
// ==========================================
export const adminApi = {
  getToken(): string | null {
    return localStorage.getItem('uss_admin_token');
  },

  setToken(token: string | null) {
    if (token) {
      localStorage.setItem('uss_admin_token', token);
    } else {
      localStorage.removeItem('uss_admin_token');
    }
  },

  async request(path: string, options: RequestInit = {}) {
    const token = this.getToken();
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      ...(options.headers as any),
    };
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    const res = await fetch(`${BASE_URL}/api/admin${path}`, {
      ...options,
      headers,
      credentials: 'include',
    });

    if (res.status === 404) {
      throw new ApiError('Not Found / Unauthorized', 404);
    }

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      throw new ApiError(data.error || 'Request failed', res.status, data);
    }
    return data;
  },

  async login(payload: { username: string; password: string }) {
    const res = await this.request('/login', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    if (res.token) {
      this.setToken(res.token);
    }
    return res;
  },

  async changePassword(currentPassword: string, newPassword: string) {
    return this.request('/change-password', {
      method: 'POST',
      body: JSON.stringify({ currentPassword, newPassword }),
    });
  },

  async getMe() {
    return this.request('/me');
  },

  async logout() {
    try {
      await this.request('/logout', { method: 'POST' });
    } finally {
      this.setToken(null);
    }
  },

  async getDashboardStats() {
    return this.request('/dashboard/stats');
  },

  async listIncidents(params: Record<string, any> = {}) {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') {
        query.append(k, String(v));
      }
    });
    return this.request(`/incidents?${query.toString()}`);
  },

  async getIncident(id: string) {
    return this.request(`/incidents/${id}`);
  },

  async createIncident(payload: any) {
    return this.request('/incidents', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  async updateIncident(id: string, payload: any) {
    return this.request(`/incidents/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  async addNote(id: string, note: string) {
    return this.request(`/incidents/${id}/notes`, {
      method: 'POST',
      body: JSON.stringify({ note }),
    });
  },

  async duplicateIncident(id: string) {
    return this.request(`/incidents/${id}/duplicate`, {
      method: 'POST',
    });
  },

  async softDeleteIncident(id: string) {
    return this.request(`/incidents/${id}`, {
      method: 'DELETE',
    });
  },

  async restoreIncident(id: string) {
    return this.request(`/incidents/${id}/restore`, {
      method: 'POST',
    });
  },

  async permanentDeleteIncident(id: string) {
    return this.request(`/incidents/${id}/permanent`, {
      method: 'DELETE',
    });
  },

  async exportSinglePdf(id: string) {
    const token = this.getToken();
    const headers: Record<string, string> = {};
    if (token) headers['Authorization'] = `Bearer ${token}`;

    const res = await fetch(`${BASE_URL}/api/admin/export/incident-pdf/${id}`, {
      headers,
      credentials: 'include',
    });
    if (!res.ok) throw new Error('Failed to download PDF');
    return res.blob();
  },

  async exportDailyPdf(siteId: string, date: string, markSubmitted = false) {
    const token = this.getToken();
    const headers: Record<string, string> = { 'Content-Type': 'application/json' };
    if (token) headers['Authorization'] = `Bearer ${token}`;

    const res = await fetch(`${BASE_URL}/api/admin/export/daily-pdf`, {
      method: 'POST',
      headers,
      credentials: 'include',
      body: JSON.stringify({ siteId, date, markSubmitted }),
    });
    if (!res.ok) throw new Error('Failed to export daily PDF');
    return res.blob();
  },

  async exportDailyExcel(siteId: string, date: string, markSubmitted = false) {
    const token = this.getToken();
    const headers: Record<string, string> = { 'Content-Type': 'application/json' };
    if (token) headers['Authorization'] = `Bearer ${token}`;

    const res = await fetch(`${BASE_URL}/api/admin/export/daily-excel`, {
      method: 'POST',
      headers,
      credentials: 'include',
      body: JSON.stringify({ siteId, date, markSubmitted }),
    });
    if (!res.ok) throw new Error('Failed to export daily Excel');
    return res.blob();
  },

  async listSites() {
    return this.request('/sites');
  },

  async createSite(payload: any) {
    return this.request('/sites', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  async updateSite(id: string, payload: any) {
    return this.request(`/sites/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  async deleteSite(id: string) {
    return this.request(`/sites/${id}`, {
      method: 'DELETE',
    });
  },

  async generateSiteQr(id: string) {
    return this.request(`/sites/${id}/qr/generate`, {
      method: 'POST',
    });
  },

  async revokeSiteQr(id: string) {
    return this.request(`/sites/${id}/qr/revoke`, {
      method: 'POST',
    });
  },

  async getSiteQrPoster(id: string, baseUrl?: string, mode?: string) {
    const params = new URLSearchParams();
    if (baseUrl) params.set('baseUrl', baseUrl);
    if (mode) params.set('mode', mode);
    const query = params.toString() ? `?${params.toString()}` : '';
    return this.request(`/sites/${id}/qr/poster${query}`);
  },

  async listStaff() {
    return this.request('/staff');
  },

  async createStaff(payload: any) {
    return this.request('/staff', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  async updateStaff(id: string, payload: any) {
    return this.request(`/staff/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  async resetStaffPassword(id: string, newPassword: string) {
    return this.request(`/staff/${id}/reset-password`, {
      method: 'POST',
      body: JSON.stringify({ newPassword }),
    });
  },

  async deleteStaff(id: string) {
    return this.request(`/staff/${id}`, {
      method: 'DELETE',
    });
  },

  async getSettings() {
    return this.request('/settings');
  },

  async updateSettings(payload: any) {
    return this.request('/settings', {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  async uploadLogo(dataBase64: string, filename?: string) {
    return this.request('/settings/logo', {
      method: 'POST',
      body: JSON.stringify({ dataBase64, filename }),
    });
  },

  async listAuditLogs() {
    return this.request('/audit-logs');
  },

  async exportBackup() {
    const token = this.getToken();
    const headers: Record<string, string> = {};
    if (token) headers['Authorization'] = `Bearer ${token}`;

    const res = await fetch(`${BASE_URL}/api/admin/backups/export`, {
      headers,
      credentials: 'include',
    });
    if (!res.ok) throw new Error('Failed to export backup');
    return res.blob();
  },

  async restoreBackup(backupData: any) {
    return this.request('/backups/restore', {
      method: 'POST',
      body: JSON.stringify(backupData),
    });
  },
};
