export declare class DatabaseManager {
  init(): Promise<void>;
  close(): Promise<void>;
  listSites(): Promise<any[]>;
  getSiteById(id: string): Promise<any | null>;
  getSiteByCode(code: string): Promise<any | null>;
  saveSite(site: any): Promise<void>;
  deleteSite(id: string): Promise<void>;
  createSiteQrToken(id: string, siteId: string, token: string): Promise<any>;
  getActiveSiteQrToken(siteId: string): Promise<any | null>;
  getSiteByQrToken(token: string): Promise<any | null>;
  listGuards(): Promise<any[]>;
  getGuardById(id: string): Promise<any | null>;
  getGuardByUsername(username: string): Promise<any | null>;
  saveGuard(guard: any, siteIds?: string[]): Promise<void>;
  deleteGuard(id: string): Promise<void>;
  listIncidents(options?: any): Promise<{ incidents: any[]; total: number }>;
  getIncidentById(id: string): Promise<any | null>;
  getIncidentByIdempotencyKey(key: string): Promise<any | null>;
  getIncidentsCount(siteId: string, date: string): Promise<number>;
  saveIncident(incident: any): Promise<void>;
  updateIncident(id: string, updates: any): Promise<void>;
  softDeleteIncident(id: string, deletedBy?: string): Promise<void>;
  saveIncidentPhoto(photo: any): Promise<void>;
  getIncidentPhotos(incidentId: string): Promise<any[]>;
  getSuperAdmin(): Promise<any | null>;
  saveSuperAdmin(admin: any): Promise<void>;
  saveAuditLog(entry: any): Promise<void>;
  listAuditLogs(limit?: number): Promise<any[]>;
  getSettings(): Promise<any>;
  saveSettings(settings: any): Promise<void>;
  addClientSubmission(sub: any): Promise<void>;
  listClientSubmissions(siteId?: string): Promise<any[]>;
  [key: string]: any;
}

export declare const db: DatabaseManager;
