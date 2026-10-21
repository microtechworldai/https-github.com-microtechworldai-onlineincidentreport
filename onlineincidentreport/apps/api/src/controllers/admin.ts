import { Request, Response } from 'express';
import jwt from 'jsonwebtoken';
import QRCode from 'qrcode';
import {
  AdminLoginSchema,
  AdminChangePasswordSchema,
  AdminIncidentUpdateSchema,
  SiteCreateSchema,
  SiteUpdateSchema,
  GuardAccountCreateSchema,
  GuardAccountUpdateSchema,
  SystemSettingsUpdateSchema,
} from '@uss/shared';
import { config, projectRoot, getAllNetworkIps } from '../config/index.js';
import { db } from '../db/index.js';
import {
  hashPassword,
  verifyPassword,
  generateUuid,
  generateSiteQrToken,
} from '../utils/crypto.js';
import { generateIncidentReference } from '../utils/reference.js';
import fs from 'fs';
import path from 'path';

function getLiveTunnelUrl(): { url: string; password?: string } | null {
  try {
    const tunnelFile = path.resolve(projectRoot, 'data', 'active_tunnel.json');
    if (fs.existsSync(tunnelFile)) {
      const tData = JSON.parse(fs.readFileSync(tunnelFile, 'utf8'));
      const isFresh =
        tData?.active !== false &&
        (!tData?.heartbeat || Date.now() - tData.heartbeat < 30000);
      if (isFresh && tData?.url) {
        return {
          url: tData.url.trim().replace(/\/+$/, ''),
          password: tData.ip || '',
        };
      }
    }
  } catch (_) {}
  return null;
}
import { storage } from '../services/storage.js';
import { pdfService } from '../services/pdf.js';
import { excelService } from '../services/excel.js';
import { audit } from '../services/audit.js';
import { realtimeService } from '../services/realtime.js';
import { AdminRequest } from '../middleware/auth.js';
import PDFDocument from 'pdfkit';

export class AdminController {
  /**
   * Super Admin Login
   */
  async login(req: Request, res: Response) {
    try {
      const parsed = AdminLoginSchema.safeParse(req.body);
      if (!parsed.success) {
        return res.status(400).json({
          error: parsed.error.errors[0]?.message || 'Invalid username or password',
        });
      }

      const { username, password } = parsed.data;
      const admin = await db.getAdminByUsername(username);

      if (!admin) {
        await audit.log({
          actorType: 'SUPER_ADMIN',
          action: 'FAILED_LOGIN',
          targetType: 'ADMIN',
          details: { username, reason: 'User not found' },
          req,
        });
        return res.status(401).json({ error: 'Invalid username or password' });
      }

      const validPass = await verifyPassword(password, admin.password_hash);
      if (!validPass) {
        await audit.log({
          actorType: 'SUPER_ADMIN',
          actorId: admin.id,
          actorName: admin.full_name,
          action: 'FAILED_LOGIN',
          targetType: 'ADMIN',
          details: { username, reason: 'Bad password' },
          req,
        });
        return res.status(401).json({ error: 'Invalid username or password' });
      }

      // Issue Admin Realm JWT (aud: uss-admin)
      const token = jwt.sign(
        {
          sub: admin.id,
          username: admin.username,
          aud: 'uss-admin',
        },
        config.jwt.adminSecret,
        { expiresIn: '8h' }
      );

      res.cookie('uss_admin_session', token, {
        httpOnly: true,
        secure: config.env === 'production',
        sameSite: 'lax',
        maxAge: 8 * 60 * 60 * 1000, // 8 hours
      });

      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: admin.id,
        actorName: admin.full_name,
        action: 'LOGIN',
        targetType: 'ADMIN',
        req,
      });

      return res.json({
        token,
        admin: {
          id: admin.id,
          username: admin.username,
          fullName: admin.full_name,
          mustChangePassword: Boolean(admin.must_change_password),
        },
      });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  /**
   * Super Admin Change Password (required on first login)
   */
  async changePassword(req: AdminRequest, res: Response) {
    try {
      if (!req.admin) return res.status(404).json({ error: 'Not Found' });

      const parsed = AdminChangePasswordSchema.safeParse(req.body);
      if (!parsed.success) {
        return res.status(400).json({
          error: parsed.error.errors[0]?.message || 'Invalid password format',
        });
      }

      const { currentPassword, newPassword } = parsed.data;
      const admin = await db.getAdminById(req.admin.id);
      if (!admin) return res.status(404).json({ error: 'Not Found' });

      const validPass = await verifyPassword(currentPassword, admin.password_hash);
      if (!validPass) {
        return res.status(400).json({ error: 'Current password is incorrect' });
      }

      const newHash = await hashPassword(newPassword);
      admin.password_hash = newHash;
      admin.must_change_password = false;
      admin.updated_at = new Date().toISOString();

      await db.saveAdmin(admin);

      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: admin.id,
        actorName: admin.full_name,
        action: 'CHANGE_PASSWORD',
        targetType: 'ADMIN',
        req,
      });

      return res.json({
        success: true,
        message: 'Password updated successfully.',
      });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  /**
   * Get Super Admin Profile
   */
  async getMe(req: AdminRequest, res: Response) {
    if (!req.admin) return res.status(404).json({ error: 'Not Found' });
    return res.json({ admin: req.admin });
  }

  /**
   * Logout Super Admin
   */
  async logout(req: Request, res: Response) {
    res.clearCookie('uss_admin_session');
    return res.json({ success: true, message: 'Logged out successfully' });
  }

  /**
   * Dashboard Statistics & KPI Metrics
   */
  async getDashboardStats(req: AdminRequest, res: Response) {
    try {
      const today = new Date().toISOString().slice(0, 10);
      const { incidents } = await db.listIncidents({ limit: 500 });
      const sites = await db.listSites();

      const todayIncidents = incidents.filter(
        (i) => i.incident_date === today && !i.deleted_at
      );

      // Status breakdown
      const statusCounts: Record<string, number> = {
        Submitted: 0,
        'Under Review': 0,
        'Follow-up Required': 0,
        Resolved: 0,
        'Submitted to Client': 0,
      };

      // Type breakdown
      const typeCounts: Record<string, number> = {};

      // Priority breakdown
      const priorityCounts: Record<string, number> = {
        Critical: 0,
        High: 0,
        Medium: 0,
        Low: 0,
      };

      // Site breakdown
      const siteCounts: Record<string, number> = {};
      sites.forEach((s) => {
        siteCounts[s.name] = 0;
      });

      // 30-day trend
      const trendMap: Record<string, number> = {};
      for (let d = 29; d >= 0; d--) {
        const dt = new Date();
        dt.setDate(dt.getDate() - d);
        const dtStr = dt.toISOString().slice(0, 10);
        trendMap[dtStr] = 0;
      }

      let deletedCount = 0;

      incidents.forEach((inc) => {
        if (inc.deleted_at) {
          deletedCount++;
          return;
        }

        const st = inc.status || 'Submitted';
        statusCounts[st] = (statusCounts[st] || 0) + 1;

        const prio = inc.priority || 'Medium';
        if (priorityCounts[prio] !== undefined) {
          priorityCounts[prio]++;
        } else {
          priorityCounts['Medium']++;
        }

        const siteName = inc.site_name || 'Unknown';
        siteCounts[siteName] = (siteCounts[siteName] || 0) + 1;

        const types = inc.incident_types || [];
        types.forEach((t: string) => {
          typeCounts[t] = (typeCounts[t] || 0) + 1;
        });

        if (trendMap[inc.incident_date] !== undefined) {
          trendMap[inc.incident_date]++;
        }
      });

      return res.json({
        todayCount: todayIncidents.length,
        totalIncidents: incidents.filter((i) => !i.deleted_at).length,
        deletedCount,
        statusCounts,
        priorityCounts,
        typeCounts,
        siteCounts,
        trend30Days: Object.entries(trendMap).map(([date, count]) => ({
          date,
          count,
        })),
        latestSubmissions: incidents.filter((i) => !i.deleted_at).slice(0, 200),
      });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  // ==========================================
  // INCIDENTS MANAGEMENT
  // ==========================================
  async listIncidents(req: AdminRequest, res: Response) {
    try {
      const {
        siteId,
        dateFrom,
        dateTo,
        status,
        cause,
        incidentType,
        guardId,
        search,
        priority,
        includeDeleted,
        onlyDeleted,
        page,
        limit,
        since,
      } = req.query;

      const result = await db.listIncidents({
        siteId: siteId as string,
        dateFrom: dateFrom as string,
        dateTo: dateTo as string,
        status: status as string,
        priority: priority as string,
        cause: cause as string,
        incidentType: incidentType as string,
        guardId: guardId as string,
        search: search as string,
        includeDeleted: includeDeleted === 'true',
        onlyDeleted: onlyDeleted === 'true',
        page: page ? parseInt(page as string, 10) : 1,
        limit: limit ? parseInt(limit as string, 10) : 50,
        since: since as string,
      });

      return res.json(result);
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  /**
   * Real-Time Server-Sent Events (SSE) Stream for Dashboard
   */
  async streamIncidents(req: AdminRequest, res: Response) {
    const clientId = generateUuid();
    realtimeService.registerSseClient(clientId, res, req.admin?.id);
  }

  async getIncident(req: AdminRequest, res: Response) {
    try {
      const { id } = req.params;
      const incident = await db.getIncidentById(id);
      if (!incident) {
        return res.status(404).json({ error: 'Incident not found' });
      }
      return res.json(incident);
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async createIncidentManual(req: AdminRequest, res: Response) {
    try {
      const data = req.body;
      const site = await db.getSiteById(data.siteId);
      if (!site) return res.status(404).json({ error: 'Site not found' });

      const existingCount = await db.getIncidentsCount(site.id, data.incidentDate);
      const refNumber = generateIncidentReference(
        site.code,
        data.incidentDate,
        existingCount + 1
      );

      const incidentId = generateUuid();
      const newIncident = {
        id: incidentId,
        ref_number: refNumber,
        site_id: site.id,
        guard_id: data.guardId || null,
        reported_by_name: data.reportedByName || req.admin!.fullName,
        reported_by_designation: data.reportedByDesignation || 'Operations Manager',
        incident_date: data.incidentDate,
        incident_time: data.incidentTime,
        server_submitted_at: new Date().toISOString(),
        shift: data.shift || 'Day',
        cause_main: data.causeMain || ['Incident'],
        disciplinary_reasons: data.disciplinaryReasons || [],
        disciplinary_other_text: data.disciplinaryOtherText || null,
        incident_types: data.incidentTypes || [],
        incident_other_text: data.incidentOtherText || null,
        summary_line: data.summaryLine || '',
        description: data.description || '',
        who_notified: data.whoNotified || [],
        notified_other_text: data.notifiedOtherText || null,
        status: data.status || 'Submitted',
        priority: data.priority || 'Medium',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      };

      await db.saveIncident(newIncident);

      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'CREATE_INCIDENT_MANUAL',
        targetType: 'INCIDENT',
        targetId: incidentId,
        details: { refNumber, siteCode: site.code },
        req,
      });

      return res.status(201).json(newIncident);
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async updateIncident(req: AdminRequest, res: Response) {
    try {
      const { id } = req.params;
      const incident = await db.getIncidentById(id);
      if (!incident) return res.status(404).json({ error: 'Incident not found' });

      const parsed = AdminIncidentUpdateSchema.safeParse(req.body);
      if (!parsed.success) {
        return res.status(400).json({
          error: parsed.error.errors[0]?.message || 'Validation error',
        });
      }

      const updates: any = parsed.data;

      // Extract values supporting both camelCase and snake_case payloads
      const rec = updates.recommendationActionTaken !== undefined ? updates.recommendationActionTaken : updates.recommendation_action_taken;
      const opName = updates.operationsManagerName !== undefined ? updates.operationsManagerName : updates.operations_manager_name;
      const opDate = updates.operationsManagerDate !== undefined ? updates.operationsManagerDate : updates.operations_manager_date;
      const opSig = updates.operationsManagerSignature !== undefined ? updates.operationsManagerSignature : updates.operations_manager_signature;
      const clAct = updates.clientManagerActionTaken !== undefined ? updates.clientManagerActionTaken : updates.client_manager_action_taken;
      const clName = updates.clientManagerName !== undefined ? updates.clientManagerName : updates.client_manager_name;
      const clDate = updates.clientManagerDate !== undefined ? updates.clientManagerDate : updates.client_manager_date;
      const clSig = updates.clientManagerSignature !== undefined ? updates.clientManagerSignature : updates.client_manager_signature;
      const supName = updates.siteSupervisorName !== undefined ? updates.siteSupervisorName : updates.site_supervisor_name;
      const supId = updates.siteSupervisorId !== undefined ? updates.siteSupervisorId : updates.site_supervisor_id;
      const supDate = updates.siteSupervisorDate !== undefined ? updates.siteSupervisorDate : updates.site_supervisor_date;
      const supSig = updates.siteSupervisorSignature !== undefined ? updates.siteSupervisorSignature : (updates.site_supervisor_signature ?? updates.site_supervisor_signature_url);
      const sumLine = updates.summaryLine !== undefined ? updates.summaryLine : updates.summary_line;
      const notif = updates.whoNotified !== undefined ? updates.whoNotified : updates.who_notified;
      const notifOther = updates.notifiedOtherText !== undefined ? updates.notifiedOtherText : updates.notified_other_text;
      const incDate = updates.incidentDate !== undefined ? updates.incidentDate : updates.incident_date;
      const incTime = updates.incidentTime !== undefined ? updates.incidentTime : updates.incident_time;

      const updated = {
        ...incident,
        ...updates,
        recommendation_action_taken: rec !== undefined ? rec : (incident.recommendation_action_taken ?? incident.recommendationActionTaken ?? null),
        recommendationActionTaken: rec !== undefined ? rec : (incident.recommendationActionTaken ?? incident.recommendation_action_taken ?? null),
        operations_manager_name: opName !== undefined ? opName : (incident.operations_manager_name ?? incident.operationsManagerName ?? null),
        operationsManagerName: opName !== undefined ? opName : (incident.operationsManagerName ?? incident.operations_manager_name ?? null),
        operations_manager_date: opDate !== undefined ? opDate : (incident.operations_manager_date ?? incident.operationsManagerDate ?? null),
        operationsManagerDate: opDate !== undefined ? opDate : (incident.operationsManagerDate ?? incident.operations_manager_date ?? null),
        operations_manager_signature: opSig !== undefined ? opSig : (incident.operations_manager_signature ?? incident.operationsManagerSignature ?? null),
        operationsManagerSignature: opSig !== undefined ? opSig : (incident.operationsManagerSignature ?? incident.operations_manager_signature ?? null),
        operations_manager_signature_url: opSig !== undefined ? opSig : (incident.operations_manager_signature_url ?? incident.operations_manager_signature ?? null),
        client_manager_action_taken: clAct !== undefined ? clAct : (incident.client_manager_action_taken ?? incident.clientManagerActionTaken ?? null),
        clientManagerActionTaken: clAct !== undefined ? clAct : (incident.clientManagerActionTaken ?? incident.client_manager_action_taken ?? null),
        client_manager_name: clName !== undefined ? clName : (incident.client_manager_name ?? incident.clientManagerName ?? null),
        clientManagerName: clName !== undefined ? clName : (incident.clientManagerName ?? incident.client_manager_name ?? null),
        client_manager_date: clDate !== undefined ? clDate : (incident.client_manager_date ?? incident.clientManagerDate ?? null),
        clientManagerDate: clDate !== undefined ? clDate : (incident.clientManagerDate ?? incident.client_manager_date ?? null),
        client_manager_signature: clSig !== undefined ? clSig : (incident.client_manager_signature ?? incident.clientManagerSignature ?? null),
        clientManagerSignature: clSig !== undefined ? clSig : (incident.clientManagerSignature ?? incident.client_manager_signature ?? null),
        client_manager_signature_url: clSig !== undefined ? clSig : (incident.client_manager_signature_url ?? incident.client_manager_signature ?? null),
        site_supervisor_name: supName !== undefined ? supName : (incident.site_supervisor_name ?? incident.siteSupervisorName ?? null),
        siteSupervisorName: supName !== undefined ? supName : (incident.siteSupervisorName ?? incident.site_supervisor_name ?? null),
        site_supervisor_id: supId !== undefined ? supId : (incident.site_supervisor_id ?? incident.siteSupervisorId ?? null),
        siteSupervisorId: supId !== undefined ? supId : (incident.siteSupervisorId ?? incident.site_supervisor_id ?? null),
        site_supervisor_date: supDate !== undefined ? supDate : (incident.site_supervisor_date ?? incident.siteSupervisorDate ?? null),
        siteSupervisorDate: supDate !== undefined ? supDate : (incident.siteSupervisorDate ?? incident.site_supervisor_date ?? null),
        site_supervisor_signature: supSig !== undefined ? supSig : (incident.site_supervisor_signature ?? incident.siteSupervisorSignature ?? null),
        siteSupervisorSignature: supSig !== undefined ? supSig : (incident.siteSupervisorSignature ?? incident.site_supervisor_signature ?? null),
        site_supervisor_signature_url: supSig !== undefined ? supSig : (incident.site_supervisor_signature_url ?? incident.site_supervisor_signature ?? null),
        summary_line: sumLine !== undefined ? sumLine : (incident.summary_line ?? incident.summaryLine ?? null),
        summaryLine: sumLine !== undefined ? sumLine : (incident.summaryLine ?? incident.summary_line ?? null),
        incident_date: incDate !== undefined ? incDate : (incident.incident_date ?? incident.incidentDate),
        incidentDate: incDate !== undefined ? incDate : (incident.incidentDate ?? incident.incident_date),
        incident_time: incTime !== undefined ? incTime : (incident.incident_time ?? incident.incidentTime),
        incidentTime: incTime !== undefined ? incTime : (incident.incidentTime ?? incident.incident_time),
        who_notified: notif !== undefined ? notif : (incident.who_notified ?? incident.whoNotified ?? []),
        whoNotified: notif !== undefined ? notif : (incident.whoNotified ?? incident.who_notified ?? []),
        notified_other_text: notifOther !== undefined ? notifOther : (incident.notified_other_text ?? incident.notifiedOtherText ?? null),
        notifiedOtherText: notifOther !== undefined ? notifOther : (incident.notifiedOtherText ?? incident.notified_other_text ?? null),
        updated_at: new Date().toISOString(),
      };

      await db.saveIncident(updated);

      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'UPDATE_INCIDENT',
        targetType: 'INCIDENT',
        targetId: id,
        details: { refNumber: incident.ref_number, changes: Object.keys(updates) },
        req,
      });

      return res.json(updated);
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async addNote(req: AdminRequest, res: Response) {
    try {
      const { id } = req.params;
      const { note } = req.body;
      if (!note || !note.trim()) {
        return res.status(400).json({ error: 'Note content is required' });
      }

      const incident = await db.getIncidentById(id);
      if (!incident) return res.status(404).json({ error: 'Incident not found' });

      const noteRecord = {
        id: generateUuid(),
        incident_id: id,
        admin_id: req.admin!.id,
        note: note.trim(),
        created_at: new Date().toISOString(),
      };

      await db.addIncidentNote(noteRecord);

      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'ADD_NOTE',
        targetType: 'INCIDENT',
        targetId: id,
        req,
      });

      return res.status(201).json(noteRecord);
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async duplicateIncident(req: AdminRequest, res: Response) {
    try {
      const { id } = req.params;
      const original = await db.getIncidentById(id);
      if (!original) return res.status(404).json({ error: 'Incident not found' });

      const today = new Date().toISOString().slice(0, 10);
      const existingCount = await db.getIncidentsCount(original.site_id, today);
      const refNumber = generateIncidentReference(
        original.site_code,
        today,
        existingCount + 1
      );

      const newId = generateUuid();
      const duplicated = {
        ...original,
        id: newId,
        ref_number: refNumber,
        incident_date: today,
        incident_time: new Date().toTimeString().slice(0, 5),
        server_submitted_at: new Date().toISOString(),
        status: 'Submitted',
        site_supervisor_name: null,
        site_supervisor_id: null,
        site_supervisor_date: null,
        operations_manager_name: null,
        operations_manager_date: null,
        client_manager_name: null,
        client_manager_date: null,
        submitted_to_client_at: null,
        deleted_at: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      };

      await db.saveIncident(duplicated);

      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'DUPLICATE_INCIDENT',
        targetType: 'INCIDENT',
        targetId: newId,
        details: { fromRef: original.ref_number, newRef: refNumber },
        req,
      });

      return res.status(201).json(duplicated);
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async softDeleteIncident(req: AdminRequest, res: Response) {
    try {
      const { id } = req.params;
      await db.softDeleteIncident(id);
      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'SOFT_DELETE_INCIDENT',
        targetType: 'INCIDENT',
        targetId: id,
        req,
      });
      return res.json({ success: true, message: 'Incident moved to trash.' });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async restoreIncident(req: AdminRequest, res: Response) {
    try {
      const { id } = req.params;
      await db.restoreIncident(id);
      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'RESTORE_INCIDENT',
        targetType: 'INCIDENT',
        targetId: id,
        req,
      });
      return res.json({ success: true, message: 'Incident restored.' });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async permanentDeleteIncident(req: AdminRequest, res: Response) {
    try {
      const { id } = req.params;
      await db.permanentDeleteIncident(id);
      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'PERMANENT_DELETE_INCIDENT',
        targetType: 'INCIDENT',
        targetId: id,
        req,
      });
      return res.json({ success: true, message: 'Incident permanently purged.' });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  // ==========================================
  // EXPORTS: PDF & EXCEL
  // ==========================================
  async exportSinglePdf(req: AdminRequest, res: Response) {
    try {
      const { id } = req.params;
      const incident = await db.getIncidentById(id);
      if (!incident) return res.status(404).json({ error: 'Incident not found' });

      const settings = await db.getSettings();
      const pdfBuffer = await pdfService.generateIncidentPdf(incident, settings);

      res.setHeader('Content-Type', 'application/pdf');
      res.setHeader(
        'Content-Disposition',
        `attachment; filename="${incident.ref_number || 'Incident'}.pdf"`
      );
      return res.send(pdfBuffer);
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async exportDailyPdf(req: AdminRequest, res: Response) {
    try {
      const { siteId, date, markSubmitted } = req.body;
      if (!siteId || !date) {
        return res.status(400).json({ error: 'siteId and date are required' });
      }

      const site = await db.getSiteById(siteId);
      if (!site) return res.status(404).json({ error: 'Site not found' });

      const { incidents } = await db.listIncidents({
        siteId,
        dateFrom: date,
        dateTo: date,
        limit: 200,
      });

      const settings = await db.getSettings();
      const pdfBuffer = await pdfService.generateDailyConsolidatedPdf(
        site,
        date,
        incidents,
        settings
      );

      // Record client submission if marked
      if (markSubmitted) {
        const subId = generateUuid();
        await db.addClientSubmission({
          id: subId,
          site_id: site.id,
          report_date: date,
          format: 'PDF',
          file_url: `/exports/daily-${site.code}-${date}.pdf`,
          generated_by: req.admin!.id,
          created_at: new Date().toISOString(),
        });

        // Update incidents status to "Submitted to Client"
        for (const inc of incidents) {
          inc.status = 'Submitted to Client';
          inc.submitted_to_client_at = new Date().toISOString();
          await db.saveIncident(inc);
        }
      }

      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'EXPORT_DAILY_PDF',
        targetType: 'SITE',
        targetId: site.id,
        details: { date, incidentCount: incidents.length, markSubmitted },
        req,
      });

      res.setHeader('Content-Type', 'application/pdf');
      res.setHeader(
        'Content-Disposition',
        `attachment; filename="USS-Daily-Report-${site.code}-${date}.pdf"`
      );
      return res.send(pdfBuffer);
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async exportDailyExcel(req: AdminRequest, res: Response) {
    try {
      const { siteId, date, markSubmitted } = req.body;
      if (!siteId || !date) {
        return res.status(400).json({ error: 'siteId and date are required' });
      }

      const site = await db.getSiteById(siteId);
      if (!site) return res.status(404).json({ error: 'Site not found' });

      const { incidents } = await db.listIncidents({
        siteId,
        dateFrom: date,
        dateTo: date,
        limit: 200,
      });

      const settings = await db.getSettings();
      const excelBuffer = await excelService.generateDailyConsolidatedExcel(
        site,
        date,
        incidents,
        settings
      );

      if (markSubmitted) {
        const subId = generateUuid();
        await db.addClientSubmission({
          id: subId,
          site_id: site.id,
          report_date: date,
          format: 'Excel',
          file_url: `/exports/daily-${site.code}-${date}.xlsx`,
          generated_by: req.admin!.id,
          created_at: new Date().toISOString(),
        });
      }

      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'EXPORT_DAILY_EXCEL',
        targetType: 'SITE',
        targetId: site.id,
        details: { date, incidentCount: incidents.length },
        req,
      });

      res.setHeader(
        'Content-Type',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      );
      res.setHeader(
        'Content-Disposition',
        `attachment; filename="USS-Daily-Report-${site.code}-${date}.xlsx"`
      );
      return res.send(excelBuffer);
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  // ==========================================
  // SITES & QR CODES
  // ==========================================
  async listSites(req: AdminRequest, res: Response) {
    try {
      const sites = await db.listSites();
      return res.json(sites);
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async createSite(req: AdminRequest, res: Response) {
    try {
      const parsed = SiteCreateSchema.safeParse(req.body);
      if (!parsed.success) {
        return res.status(400).json({
          error: parsed.error.errors[0]?.message || 'Validation error',
        });
      }

      const { name, code, type, address, clientName, clientContact, clientEmail, clientMobile, latitude, longitude } = parsed.data;
      const siteId = generateUuid();
      const site = {
        id: siteId,
        name,
        code,
        type,
        address,
        client_name: clientName,
        client_contact: clientContact || clientMobile || '',
        client_email: clientEmail || '',
        client_mobile: clientMobile || '',
        latitude: latitude ? String(latitude).trim() : null,
        longitude: longitude ? String(longitude).trim() : null,
        is_active: true,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      };

      await db.saveSite(site);

      // Automatically generate initial site QR code token
      const qrToken = generateSiteQrToken();
      await db.createSiteQrToken(generateUuid(), siteId, qrToken);

      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'CREATE_SITE',
        targetType: 'SITE',
        targetId: siteId,
        details: { name: site.name, code: site.code, clientEmail: site.client_email, clientMobile: site.client_mobile, latitude: site.latitude, longitude: site.longitude },
        req,
      });

      return res.status(201).json({ ...site, active_qr_token: qrToken });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async updateSite(req: AdminRequest, res: Response) {
    try {
      const { id } = req.params;
      const site = await db.getSiteById(id);
      if (!site) return res.status(404).json({ error: 'Site not found' });

      const parsed = SiteUpdateSchema.safeParse(req.body);
      if (!parsed.success) {
        return res.status(400).json({
          error: parsed.error.errors[0]?.message || 'Validation error',
        });
      }

      const data = parsed.data;
      const updated = {
        ...site,
        name: data.name !== undefined ? data.name : site.name,
        type: data.type !== undefined ? data.type : site.type,
        address: data.address !== undefined ? data.address : site.address,
        client_name: data.clientName !== undefined ? data.clientName : site.client_name,
        client_contact: data.clientContact !== undefined ? data.clientContact : site.client_contact,
        client_email: data.clientEmail !== undefined ? data.clientEmail : site.client_email,
        client_mobile: data.clientMobile !== undefined ? data.clientMobile : site.client_mobile,
        latitude: data.latitude !== undefined ? (data.latitude ? String(data.latitude).trim() : null) : (site.latitude ?? null),
        longitude: data.longitude !== undefined ? (data.longitude ? String(data.longitude).trim() : null) : (site.longitude ?? null),
        is_active: data.isActive !== undefined ? data.isActive : site.is_active,
        updated_at: new Date().toISOString(),
      };

      await db.saveSite(updated);

      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'UPDATE_SITE',
        targetType: 'SITE',
        targetId: id,
        details: { name: updated.name, code: updated.code },
        req,
      });

      return res.json(updated);
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async deleteSite(req: AdminRequest, res: Response) {
    try {
      const { id } = req.params;
      const site = await db.getSiteById(id);
      if (!site) return res.status(404).json({ error: 'Site not found' });

      await db.deleteSite(id);

      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'DELETE_SITE',
        targetType: 'SITE',
        targetId: id,
        details: { siteName: site.name, siteCode: site.code },
        req,
      });

      return res.json({ success: true, message: `Site ${site.name} was successfully deleted.` });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async generateSiteQr(req: AdminRequest, res: Response) {
    try {
      const { id } = req.params;
      const site = await db.getSiteById(id);
      if (!site) return res.status(404).json({ error: 'Site not found' });

      const requestedDomain = req.body?.domain?.trim();
      if (requestedDomain) {
        let checkUrl = requestedDomain;
        if (!checkUrl.startsWith('http://') && !checkUrl.startsWith('https://')) {
          checkUrl = `https://${checkUrl}`;
        }
        try {
          const parsedUrl = new URL(checkUrl);
          const host = parsedUrl.hostname.toLowerCase();
          const isPrivate =
            host === 'localhost' ||
            host === '127.0.0.1' ||
            host.endsWith('.local') ||
            /^10\./.test(host) ||
            /^192\.168\./.test(host) ||
            /^172\.(1[6-9]|2[0-9]|3[0-1])\./.test(host);

          if (isPrivate) {
            return res.status(400).json({
              error: `Cannot generate QR: "${host}" is a local private address. QR codes must contain a public HTTPS URL (e.g. https://yourdomain.ae or live public tunnel) so guards can scan from 4G/5G, any Wi-Fi, or guest networks worldwide.`,
            });
          }

          if (parsedUrl.protocol !== 'https:') {
            return res.status(400).json({
              error: `Cannot generate QR: URL must use HTTPS. Insecure protocol "${parsedUrl.protocol}" is rejected. Must be a public HTTPS URL.`,
            });
          }
        } catch {
          return res.status(400).json({
            error: 'Cannot generate QR: Invalid domain URL. Must be a valid public HTTPS URL.',
          });
        }
      }

      let appDomain = requestedDomain || config.appDomain.trim();
      let activeTunnelUrl = '';
      if (!requestedDomain) {
        const liveTunnel = getLiveTunnelUrl();
        if (liveTunnel) activeTunnelUrl = liveTunnel.url;
      }

      const effectiveUrl = activeTunnelUrl || (appDomain.startsWith('http') ? appDomain : `https://${appDomain}`);

      // Always generate and persist token so QR code is renewed immediately
      const token = generateSiteQrToken();
      await db.createSiteQrToken(generateUuid(), site.id, token);

      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'GENERATE_SITE_QR',
        targetType: 'SITE',
        targetId: site.id,
        req,
      });

      const targetUrl = `${effectiveUrl.replace(/\/+$/, '')}/g/${token}`;

      return res.json({
        success: true,
        token,
        qrUrl: targetUrl,
      });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async revokeSiteQr(req: AdminRequest, res: Response) {
    try {
      const { id } = req.params;
      await db.revokeSiteQrToken(id);
      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'REVOKE_SITE_QR',
        targetType: 'SITE',
        targetId: id,
        req,
      });
      return res.json({ success: true, message: 'QR token revoked.' });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async getSiteQrPoster(req: AdminRequest, res: Response) {
    try {
      const { id } = req.params;
      const site = await db.getSiteById(id);
      if (!site) return res.status(404).json({ error: 'Site not found' });
      if (!site.active_qr_token) {
        return res.status(400).json({ error: 'No active QR token found for this site. Please generate one.' });
      }

      const customBaseUrl = ((req.query.baseUrl || req.query.customUrl) as string)?.trim();
      const mode = (req.query.mode as string)?.trim(); // 'public' | 'lan'
      let targetUrl = '';
      const lanIp = config.appDomain.replace(/:.*$/, '');
      const defaultLanDomain = lanIp !== 'localhost' ? `${lanIp}:5173` : 'localhost:5173';
      const lanTargetUrl = `http://${defaultLanDomain}/g/${site.active_qr_token}`;
      const allIps = getAllNetworkIps();
      const lanTargetUrls = allIps.map((ip) => ({
        ip,
        url: `http://${ip}:5173/g/${site.active_qr_token}`,
      }));

      // Check if an active public tunnel is running
      const liveTunnel = getLiveTunnelUrl();
      const activeTunnelUrl = liveTunnel ? liveTunnel.url : '';
      const tunnelPassword = liveTunnel?.password || '';

      if (mode === 'lan') {
        targetUrl = lanTargetUrl;
      } else if (activeTunnelUrl && (!customBaseUrl || customBaseUrl.includes('trycloudflare.com') || customBaseUrl.includes('loca.lt'))) {
        // Automatically encode the public tunnel URL so QR codes work on ANY network (4G/5G/Wi-Fi)
        targetUrl = `${activeTunnelUrl}/g/${site.active_qr_token}`;
      } else if (customBaseUrl) {
        let cleaned = customBaseUrl.replace(/\/+$/, '');
        if (!cleaned.startsWith('http://') && !cleaned.startsWith('https://')) {
          const isLocal = cleaned.startsWith('localhost') || cleaned.startsWith('127.0.0.1') || cleaned.startsWith('192.168.') || cleaned.startsWith('10.');
          cleaned = `${isLocal ? 'http://' : 'https://'}${cleaned}`;
        }
        targetUrl = `${cleaned}/g/${site.active_qr_token}`;
      } else if (activeTunnelUrl) {
        targetUrl = `${activeTunnelUrl}/g/${site.active_qr_token}`;
      } else {
        targetUrl = lanTargetUrl;
      }

      const qrDataUrl = await QRCode.toDataURL(targetUrl, {
        width: 450,
        margin: 4,
        errorCorrectionLevel: 'H',
        color: {
          dark: '#0F2042',
          light: '#FFFFFF',
        },
      });

      // Printable A4 Poster PDF
      if (req.query.format === 'pdf') {
        const doc = new PDFDocument({ size: 'A4', margin: 40 });
        res.setHeader('Content-Type', 'application/pdf');
        res.setHeader('Content-Disposition', `inline; filename="QR_Poster_${site.code}.pdf"`);
        doc.pipe(res);

        const settings = await db.getSettings();
        const logoBuf = await pdfService.getLogoBuffer(settings);
        doc.rect(40, 40, doc.page.width - 80, 75).strokeColor('#000000').lineWidth(1).stroke();
        if (logoBuf) {
          try {
            doc.image(logoBuf, 50, 48, { fit: [58, 58], valign: 'center' });
          } catch {}
        }
        const textX = logoBuf ? 118 : 50;
        const textW = doc.page.width - 80 - (logoBuf ? 88 : 20);
        doc.fontSize(16).font('Helvetica-Bold').fillColor('#0F2042').text(settings.company_name || 'ULTIMATE SECURITY SERVICES', textX, 58, { width: textW, align: logoBuf ? 'left' : 'center' });
        doc.fontSize(10).font('Helvetica-Bold').fillColor('#475569').text('INCIDENT REPORTING STATION • OFFICIAL QR ACCESS POINT', textX, 84, { width: textW, align: logoBuf ? 'left' : 'center' });

        doc.fontSize(22).font('Helvetica-Bold').fillColor('#000000').text(site.name, 40, 145, { align: 'center' });
        doc.fontSize(12).font('Helvetica-Bold').fillColor('#2563EB').text(`SITE CODE: ${site.code}  •  ${site.type || 'Commercial'}`, 40, 175, { align: 'center' });

        const qrBuffer = await QRCode.toBuffer(targetUrl, {
          width: 320,
          margin: 4,
          errorCorrectionLevel: 'H',
          color: { dark: '#0F2042', light: '#FFFFFF' }
        });
        const qrX = (doc.page.width - 260) / 2;
        doc.image(qrBuffer, qrX, 215, { width: 260, height: 260 });

        doc.fontSize(12).font('Helvetica-Bold').fillColor('#0F2042').text('Scan with your smartphone camera to submit an incident report', 40, 500, { align: 'center' });
        doc.fontSize(9.5).font('Helvetica').fillColor('#475569').text('Or open this address in Chrome / Safari:', 40, 520, { align: 'center' });
        doc.fontSize(11).font('Helvetica-Bold').fillColor('#1D4ED8').text(targetUrl, 40, 538, { align: 'center' });

        doc.rect(40, 580, doc.page.width - 80, 75).fillAndStroke('#F8FAFC', '#CBD5E1');
        doc.fontSize(9.5).font('Helvetica-Bold').fillColor('#1E293B').text('SECURITY INSTRUCTIONS FOR OFFICERS ON DUTY:', 55, 595);
        doc.fontSize(8.5).font('Helvetica').fillColor('#475569').text('1. Open default phone camera or Google Lens. Point at QR code. Tap link to sign in.\n2. Works across any network: Wi-Fi, 4G, or 5G (du, Etisalat, Virgin). Offline drafts are automatically preserved.\n3. In case of emergency, notify site control room immediately.', 55, 615, { lineGap: 3 });

        doc.end();
        return;
      }

      return res.json({
        site: {
          id: site.id,
          name: site.name,
          code: site.code,
          address: site.address,
        },
        token: site.active_qr_token,
        targetUrl,
        qrDataUrl,
        pdfPosterUrl: `/api/admin/sites/${site.id}/qr/poster.pdf`,
        lanTargetUrl,
        lanTargetUrls,
        activeTunnelUrl,
        tunnelActive: Boolean(activeTunnelUrl),
        tunnelPassword,
      });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  // ==========================================
  // SECURITY STAFF MANAGEMENT
  // ==========================================
  async listStaff(req: AdminRequest, res: Response) {
    try {
      const guards = await db.listGuards();
      // Don't expose password hashes
      const sanitized = guards.map((g) => ({
        id: g.id,
        staffId: g.staff_id,
        fullName: g.full_name,
        designation: g.designation,
        username: g.username,
        isActive: Boolean(g.is_active),
        assignedSites: g.assignedSites || [],
        createdAt: g.created_at,
        updatedAt: g.updated_at,
      }));
      return res.json(sanitized);
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async createStaff(req: AdminRequest, res: Response) {
    try {
      const parsed = GuardAccountCreateSchema.safeParse(req.body);
      if (!parsed.success) {
        return res.status(400).json({
          error: parsed.error.errors[0]?.message || 'Validation error',
        });
      }

      const { staffId, fullName, designation, username, password, assignedSites } =
        parsed.data;

      // Check unique username
      const existing = await db.getGuardByUsername(username);
      if (existing) {
        return res.status(400).json({ error: 'Username is already taken' });
      }

      const passHash = await hashPassword(password);
      const guardId = generateUuid();

      const guard = {
        id: guardId,
        staff_id: staffId,
        full_name: fullName,
        designation,
        username,
        password_hash: passHash,
        is_active: true,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      };

      await db.saveGuard(guard, assignedSites);

      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'CREATE_GUARD_STAFF',
        targetType: 'GUARD',
        targetId: guardId,
        details: { staffId, fullName, username, siteCount: assignedSites.length },
        req,
      });

      return res.status(201).json({
        id: guardId,
        staffId,
        fullName,
        designation,
        username,
        isActive: true,
        assignedSites,
      });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async updateStaff(req: AdminRequest, res: Response) {
    try {
      const { id } = req.params;
      const guard = await db.getGuardById(id);
      if (!guard) return res.status(404).json({ error: 'Staff account not found' });

      const parsed = GuardAccountUpdateSchema.safeParse(req.body);
      if (!parsed.success) {
        return res.status(400).json({
          error: parsed.error.errors[0]?.message || 'Validation error',
        });
      }

      const updates = parsed.data;
      const updated = {
        ...guard,
        staff_id: updates.staffId !== undefined ? updates.staffId.trim() : guard.staff_id,
        username: updates.username !== undefined ? updates.username.toLowerCase().trim() : guard.username,
        full_name: updates.fullName !== undefined ? updates.fullName.trim() : guard.full_name,
        designation: updates.designation !== undefined ? updates.designation.trim() : guard.designation,
        is_active: updates.isActive !== undefined ? updates.isActive : guard.is_active,
        updated_at: new Date().toISOString(),
      };

      const sites = updates.assignedSites !== undefined ? updates.assignedSites : guard.assignedSites;
      await db.saveGuard(updated, sites);

      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'UPDATE_GUARD_STAFF',
        targetType: 'GUARD',
        targetId: id,
        details: { staffId: updated.staff_id, fullName: updated.full_name, username: updated.username },
        req,
      });

      return res.json({
        id: updated.id,
        staffId: updated.staff_id,
        fullName: updated.full_name,
        designation: updated.designation,
        username: updated.username,
        isActive: updated.is_active,
        assignedSites: sites,
      });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async resetStaffPassword(req: AdminRequest, res: Response) {
    try {
      const { id } = req.params;
      const { newPassword } = req.body;
      if (!newPassword || newPassword.length < 6) {
        return res.status(400).json({ error: 'Password must be at least 6 characters' });
      }

      const guard = await db.getGuardById(id);
      if (!guard) return res.status(404).json({ error: 'Staff account not found' });

      const hash = await hashPassword(newPassword);
      guard.password_hash = hash;
      guard.updated_at = new Date().toISOString();

      await db.saveGuard(guard, guard.assignedSites || []);

      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'RESET_GUARD_PASSWORD',
        targetType: 'GUARD',
        targetId: id,
        details: { staffId: guard.staff_id },
        req,
      });

      return res.json({ success: true, message: 'Password has been reset successfully.' });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async deleteStaff(req: AdminRequest, res: Response) {
    try {
      const { id } = req.params;
      const guard = await db.getGuardById(id);
      if (!guard) return res.status(404).json({ error: 'Staff account not found' });

      await db.deleteGuard(id);

      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'DELETE_GUARD_STAFF',
        targetType: 'GUARD',
        targetId: id,
        details: { staffId: guard.staff_id, fullName: guard.full_name, username: guard.username },
        req,
      });

      return res.json({
        success: true,
        message: `Staff account ${guard.full_name} (${guard.staff_id}) was successfully deleted.`,
      });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  // ==========================================
  // SETTINGS & AUDIT
  // ==========================================
  async getSettings(req: AdminRequest, res: Response) {
    try {
      const settings = await db.getSettings();
      return res.json(settings);
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async updateSettings(req: AdminRequest, res: Response) {
    try {
      const parsed = SystemSettingsUpdateSchema.safeParse(req.body);
      if (!parsed.success) {
        return res.status(400).json({
          error: parsed.error.errors[0]?.message || 'Validation error',
        });
      }

      const current = await db.getSettings();
      const updated = {
        ...current,
        ...parsed.data,
        updated_at: new Date().toISOString(),
      };

      await db.saveSettings(updated);

      // BROADCAST COMPANY BRANDING UPDATE IMMEDIATELY
      realtimeService.broadcastSettingsUpdate(updated);

      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'UPDATE_SETTINGS',
        targetType: 'SETTINGS',
        req,
      });

      return res.json(updated);
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async uploadLogo(req: AdminRequest, res: Response) {
    try {
      const { dataBase64, filename } = req.body;
      if (!dataBase64) {
        return res.status(400).json({ error: 'Image data is required' });
      }

      const rawBase64 = dataBase64.replace(/^data:image\/\w+;base64,/, '');
      const buffer = Buffer.from(rawBase64, 'base64');
      const saved = await storage.saveFile(buffer, filename || 'uss-logo.png', 'settings');

      // Sync copy to web public assets so Vite can serve it instantly
      const webAssetPath = path.resolve(process.cwd(), '../web/public/assets/uss-logo.png');
      try {
        if (!fs.existsSync(path.dirname(webAssetPath))) {
          fs.mkdirSync(path.dirname(webAssetPath), { recursive: true });
        }
        fs.writeFileSync(webAssetPath, buffer);
      } catch (e: any) {
        console.warn('[LOGO] Failed to copy to web asset path:', e.message);
      }

      const current = await db.getSettings();
      current.logo_url = saved.url;
      await db.saveSettings(current);

      // BROADCAST COMPANY LOGO UPDATE IMMEDIATELY
      realtimeService.broadcastSettingsUpdate(current);

      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'UPLOAD_COMPANY_LOGO',
        targetType: 'SETTINGS',
        details: { url: saved.url, size: saved.fileSize },
        req,
      });

      return res.json({ success: true, logoUrl: saved.url });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async listAuditLogs(req: AdminRequest, res: Response) {
    try {
      const logs = await db.listAuditLogs(100);
      return res.json(logs);
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async exportBackup(req: AdminRequest, res: Response) {
    try {
      const { incidents } = await db.listIncidents({ includeDeleted: true, limit: 10000 });
      const sites = await db.listSites();
      const guards = await db.listGuards();
      const settings = await db.getSettings();
      const auditLogs = await db.listAuditLogs(1000);

      const backupData = {
        system: 'USS Incident Report System',
        exportedAt: new Date().toISOString(),
        exportedBy: req.admin!.username,
        data: {
          sites,
          guards: guards.map((g) => ({ ...g, password_hash: '[PROTECTED]' })),
          incidents,
          settings,
          auditLogs,
        },
      };

      res.setHeader('Content-Type', 'application/json');
      res.setHeader(
        'Content-Disposition',
        `attachment; filename="uss_backup_${new Date().toISOString().slice(0, 10)}.json"`
      );
      return res.send(JSON.stringify(backupData, null, 2));
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  async restoreBackup(req: AdminRequest, res: Response) {
    try {
      const backup = req.body;
      if (!backup || !backup.data) {
        return res.status(400).json({ error: 'Invalid backup file format. Expected a valid USS database backup JSON.' });
      }

      const { sites, guards, incidents, settings } = backup.data;

      let restoredSites = 0;
      let restoredGuards = 0;
      let restoredIncidents = 0;

      // 1. Restore sites
      if (Array.isArray(sites)) {
        for (const s of sites) {
          if (s.id && s.name && s.code) {
            await db.saveSite({
              id: s.id,
              name: s.name,
              code: s.code,
              type: s.type || 'Commercial',
              address: s.address || '',
              client_name: s.client_name || s.name,
              client_contact: s.client_contact || s.client_mobile || '',
              client_email: s.client_email || '',
              client_mobile: s.client_mobile || '',
              is_active: s.is_active ?? true,
              created_at: s.created_at || new Date().toISOString(),
              updated_at: new Date().toISOString(),
            });
            if (s.active_qr_token) {
              await db.createSiteQrToken(generateUuid(), s.id, s.active_qr_token);
            }
            restoredSites++;
          }
        }
      }

      // 2. Restore guards
      if (Array.isArray(guards)) {
        for (const g of guards) {
          if (g.id && g.username) {
            let passHash = g.password_hash;
            if (!passHash || passHash === '[PROTECTED]') {
              const existing = await db.getGuardById(g.id);
              passHash = existing?.password_hash || (await hashPassword('GuardPass123!'));
            }
            await db.saveGuard(
              {
                id: g.id,
                staff_id: g.staff_id || g.staffId || 'USS-000',
                full_name: g.full_name || g.fullName || 'Security Officer',
                designation: g.designation || 'Security Guard',
                username: g.username,
                password_hash: passHash,
                is_active: g.is_active ?? true,
                created_at: g.created_at || new Date().toISOString(),
                updated_at: new Date().toISOString(),
              },
              g.assignedSites || []
            );
            restoredGuards++;
          }
        }
      }

      // 3. Restore incidents
      if (Array.isArray(incidents)) {
        for (const inc of incidents) {
          if (inc.id && inc.ref_number) {
            await db.saveIncident({
              ...inc,
              updated_at: new Date().toISOString(),
            });
            restoredIncidents++;
          }
        }
      }

      // 4. Restore settings if present
      if (settings && typeof settings === 'object') {
        const current = await db.getSettings();
        await db.saveSettings({
          ...current,
          ...settings,
          updated_at: new Date().toISOString(),
        });
      }

      await audit.log({
        actorType: 'SUPER_ADMIN',
        actorId: req.admin!.id,
        actorName: req.admin!.fullName,
        action: 'RESTORE_DATABASE_BACKUP',
        targetType: 'SYSTEM',
        details: {
          restoredSites,
          restoredGuards,
          restoredIncidents,
          exportedAt: backup.exportedAt,
        },
        req,
      });

      return res.json({
        success: true,
        message: `Database restored successfully. Restored ${restoredSites} sites, ${restoredGuards} staff accounts, and ${restoredIncidents} incident reports.`,
        restored: {
          sites: restoredSites,
          guards: restoredGuards,
          incidents: restoredIncidents,
        },
      });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }
}

export const adminController = new AdminController();
