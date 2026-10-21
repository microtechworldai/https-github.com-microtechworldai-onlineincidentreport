import { Response } from 'express';
import http from 'http';

interface SseClient {
  id: string;
  res: Response;
  adminId?: string;
  connectedAt: Date;
}

export class RealtimeService {
  private sseClients: Map<string, SseClient> = new Map();
  private wsClients: Set<any> = new Set();
  private heartbeatInterval: NodeJS.Timeout | null = null;

  constructor() {
    // Send periodic heartbeats every 15 seconds to keep connections alive through proxies
    this.heartbeatInterval = setInterval(() => {
      this.sendHeartbeat();
    }, 15000);
    this.heartbeatInterval.unref();
  }

  close(): void {
    if (this.heartbeatInterval) {
      clearInterval(this.heartbeatInterval);
      this.heartbeatInterval = null;
    }
    for (const client of this.sseClients.values()) {
      try { client.res.end(); } catch (_) {}
    }
    this.sseClients.clear();
  }

  /**
   * Register a new SSE connection from Super Admin dashboard
   */
  registerSseClient(id: string, res: Response, adminId?: string): void {
    res.setHeader('Content-Type', 'text/event-stream');
    res.setHeader('Cache-Control', 'no-cache, no-transform');
    res.setHeader('Connection', 'keep-alive');
    res.setHeader('X-Accel-Buffering', 'no'); // Disable proxy buffering (Nginx, Caddy, Cloudflare)
    res.flushHeaders?.();

    // Initial greeting and connection acknowledgment
    res.write(`event: connected\ndata: ${JSON.stringify({ clientId: id, timestamp: new Date().toISOString() })}\n\n`);

    const client: SseClient = { id, res, adminId, connectedAt: new Date() };
    this.sseClients.set(id, client);

    res.on('close', () => {
      this.sseClients.delete(id);
    });

    res.on('error', () => {
      this.sseClients.delete(id);
    });
  }

  /**
   * Broadcast a newly created incident report to all connected dashboards in real time (<2 seconds)
   */
  broadcastIncident(incident: any): void {
    const payload = JSON.stringify({
      type: 'NEW_INCIDENT',
      incident: {
        id: incident.id,
        refNumber: incident.ref_number || incident.refNumber,
        siteId: incident.site_id || incident.siteId,
        siteName: incident.site_name || incident.siteName,
        siteCode: incident.site_code || incident.siteCode,
        incidentDate: incident.incident_date || incident.incidentDate,
        incidentTime: incident.incident_time || incident.incidentTime,
        shift: incident.shift,
        reportedByName: incident.reported_by_name || incident.reportedByName,
        reportedByDesignation: incident.reported_by_designation || incident.reportedByDesignation,
        causeMain: incident.cause_main || incident.causeMain,
        incidentTypes: incident.incident_types || incident.incidentTypes,
        summaryLine: incident.summary_line || incident.summaryLine,
        description: incident.description,
        status: incident.status || 'Submitted',
        priority: incident.priority || 'Medium',
        photoCount: Array.isArray(incident.photos) ? incident.photos.length : 0,
        createdAt: incident.created_at || incident.createdAt || new Date().toISOString(),
      },
      timestamp: new Date().toISOString(),
    });

    this.sendSseEvent('incident_new', payload);
  }

  /**
   * Broadcast incident update or status change
   */
  broadcastIncidentUpdate(incidentId: string, updates: any): void {
    const payload = JSON.stringify({
      type: 'UPDATE_INCIDENT',
      incidentId,
      updates,
      timestamp: new Date().toISOString(),
    });

    this.sendSseEvent('incident_updated', payload);
  }

  /**
   * Broadcast company settings update so all active sessions update branding immediately
   */
  broadcastSettingsUpdate(settings: any): void {
    const payload = JSON.stringify({
      type: 'SETTINGS_UPDATED',
      settings,
      timestamp: new Date().toISOString(),
    });

    this.sendSseEvent('settings_updated', payload);
  }

  private sendSseEvent(event: string, dataString: string): void {
    const deadClientIds: string[] = [];

    for (const [id, client] of this.sseClients.entries()) {
      try {
        client.res.write(`event: ${event}\ndata: ${dataString}\n\n`);
      } catch (_) {
        deadClientIds.push(id);
      }
    }

    for (const deadId of deadClientIds) {
      this.sseClients.delete(deadId);
    }
  }

  private sendHeartbeat(): void {
    const now = new Date().toISOString();
    const deadClientIds: string[] = [];

    for (const [id, client] of this.sseClients.entries()) {
      try {
        client.res.write(`: heartbeat ${now}\n\n`);
      } catch (_) {
        deadClientIds.push(id);
      }
    }

    for (const deadId of deadClientIds) {
      this.sseClients.delete(deadId);
    }
  }

  getActiveClientCount(): number {
    return this.sseClients.size;
  }
}

export const realtimeService = new RealtimeService();
