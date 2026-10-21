import { Request, Response } from 'express';
import jwt from 'jsonwebtoken';
import {
  GuardLoginSchema,
  GuardIncidentSubmitSchema,
  extractSiteToken,
  sanitizeRichText,
} from '@uss/shared';
import { config } from '../config/index.js';
import { db } from '../db/index.js';
import { verifyPassword, generateUuid } from '../utils/crypto.js';
import { generateIncidentReference } from '../utils/reference.js';
import { storage } from '../services/storage.js';
import { audit } from '../services/audit.js';
import { realtimeService } from '../services/realtime.js';
import { GuardRequest } from '../middleware/auth.js';

export class GuardController {
  /**
   * Validate site QR token and retrieve site details for the login screen
   */
  async getSiteByToken(req: Request, res: Response) {
    try {
      const rawToken = req.params.token || (req.query.token as string) || '';
      const token = extractSiteToken(rawToken);
      if (!token) {
        return res.status(400).json({ error: 'Token is required' });
      }

      const site = await db.getSiteByQrToken(token);
      if (!site) {
        return res.status(404).json({ error: 'Invalid or revoked site QR code. Please contact your supervisor.' });
      }

      const rawSettings = await db.getSettings();
      const parseOpt = (val: any, fallback: any = []) => {
        if (!val) return fallback;
        if (Array.isArray(val)) return val;
        try { return JSON.parse(val); } catch { return fallback; }
      };

      return res.json({
        valid: true,
        site: {
          id: site.id,
          name: site.name,
          code: site.code,
          type: site.type,
          address: site.address,
        },
        settings: {
          companyName: rawSettings.company_name || 'ULTIMATE SECURITY SERVICES',
          logoUrl: rawSettings.logo_url || '/assets/uss-logo.png',
          incidentOptions: parseOpt(rawSettings.incident_options),
          disciplinaryOptions: parseOpt(rawSettings.disciplinary_options),
          notifiedOptions: parseOpt(rawSettings.notified_options),
          causeOptions: parseOpt(rawSettings.cause_options),
          shiftOptions: parseOpt(rawSettings.shift_options),
        },
      });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  /**
   * Guard Login with site token + username + password
   */
  async login(req: Request, res: Response) {
    try {
      const parsed = GuardLoginSchema.safeParse(req.body);
      if (!parsed.success) {
        return res.status(400).json({
          error: parsed.error.errors[0]?.message || 'Invalid login details',
        });
      }

      const { siteToken: rawSiteToken, username, password } = parsed.data;
      const siteToken = extractSiteToken(rawSiteToken);

      // 1. Verify Site QR Token
      const site = await db.getSiteByQrToken(siteToken);
      if (!site) {
        return res.status(400).json({
          error: 'Site QR token is expired or revoked. Please scan an active site QR code.',
        });
      }

      // 2. Lookup Guard Account
      const guard = await db.getGuardByUsername(username);
      if (!guard || !guard.is_active) {
        await audit.log({
          actorType: 'GUARD',
          action: 'FAILED_LOGIN',
          targetType: 'GUARD',
          details: { username, reason: 'Account not found or disabled', siteId: site.id },
          req,
        });
        return res.status(401).json({ error: 'Invalid username or password' });
      }

      // 3. Verify Password
      const validPass = await verifyPassword(password, guard.password_hash);
      if (!validPass) {
        await audit.log({
          actorType: 'GUARD',
          actorId: guard.id,
          actorName: guard.full_name,
          action: 'FAILED_LOGIN',
          targetType: 'GUARD',
          details: { username, reason: 'Bad credentials', siteId: site.id },
          req,
        });
        return res.status(401).json({ error: 'Invalid username or password' });
      }

      // 4. Verify Site Assignment (guard can only report for assigned sites)
      if (!guard.assignedSites || !guard.assignedSites.includes(site.id)) {
        await audit.log({
          actorType: 'GUARD',
          actorId: guard.id,
          actorName: guard.full_name,
          action: 'UNAUTHORIZED_SITE_ACCESS_ATTEMPT',
          targetType: 'SITE',
          targetId: site.id,
          details: { username, siteCode: site.code },
          req,
        });
        return res.status(403).json({
          error: `You are not authorized to submit incident reports for ${site.name}. Please contact operations.`,
        });
      }

      // 5. Issue Guard Realm Session JWT (aud: uss-guard)
      const token = jwt.sign(
        {
          sub: guard.id,
          siteId: site.id,
          staffId: guard.staff_id,
          aud: 'uss-guard',
        },
        config.jwt.guardSecret,
        { expiresIn: '2h' }
      );

      res.cookie('uss_guard_session', token, {
        httpOnly: true,
        secure: config.env === 'production',
        sameSite: 'lax',
        maxAge: 2 * 60 * 60 * 1000, // 2 hours
      });

      await audit.log({
        actorType: 'GUARD',
        actorId: guard.id,
        actorName: guard.full_name,
        action: 'LOGIN',
        targetType: 'GUARD',
        details: { siteId: site.id, siteName: site.name },
        req,
      });

      return res.json({
        token,
        guard: {
          id: guard.id,
          staffId: guard.staff_id,
          username: guard.username,
          fullName: guard.full_name,
          designation: guard.designation,
        },
        site: {
          id: site.id,
          name: site.name,
          code: site.code,
          type: site.type,
          address: site.address,
        },
      });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  /**
   * Get current guard session profile
   */
  async getMe(req: GuardRequest, res: Response) {
    try {
      if (!req.guard) return res.status(401).json({ error: 'Unauthorized' });
      const site = req.guard.siteId
        ? await db.getSiteById(req.guard.siteId)
        : null;

      const rawSettings = await db.getSettings();
      const parseOpt = (val: any, fallback: any = []) => {
        if (!val) return fallback;
        if (Array.isArray(val)) return val;
        try { return JSON.parse(val); } catch { return fallback; }
      };

      const settings = {
        companyName: rawSettings.company_name || 'ULTIMATE SECURITY SERVICES',
        logoUrl: rawSettings.logo_url || '/assets/uss-logo.png',
        incidentOptions: parseOpt(rawSettings.incident_options),
        disciplinaryOptions: parseOpt(rawSettings.disciplinary_options),
        notifiedOptions: parseOpt(rawSettings.notified_options),
        causeOptions: parseOpt(rawSettings.cause_options),
        shiftOptions: parseOpt(rawSettings.shift_options),
      };

      return res.json({
        guard: {
          id: req.guard.id,
          staffId: req.guard.staffId,
          fullName: req.guard.fullName,
          designation: req.guard.designation,
          username: req.guard.username,
        },
        site: site
          ? {
              id: site.id,
              name: site.name,
              code: site.code,
              type: site.type,
              address: site.address,
            }
          : null,
        settings,
      });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  /**
   * Get public or guard configuration options
   */
  async getSettings(req: Request, res: Response) {
    try {
      const rawSettings = await db.getSettings();
      const parseOpt = (val: any, fallback: any = []) => {
        if (!val) return fallback;
        if (Array.isArray(val)) return val;
        try { return JSON.parse(val); } catch { return fallback; }
      };

      return res.json({
        companyName: rawSettings.company_name || 'ULTIMATE SECURITY SERVICES',
        logoUrl: rawSettings.logo_url || '/assets/uss-logo.png',
        incidentOptions: parseOpt(rawSettings.incident_options),
        disciplinaryOptions: parseOpt(rawSettings.disciplinary_options),
        notifiedOptions: parseOpt(rawSettings.notified_options),
        causeOptions: parseOpt(rawSettings.cause_options),
        shiftOptions: parseOpt(rawSettings.shift_options),
      });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  /**
   * Submit Incident Report with up to 10 photos
   * Automatically terminates guard session upon success (server-side session destruction)
   */
  async submitIncident(req: GuardRequest, res: Response) {
    try {
      if (!req.guard) {
        return res.status(401).json({ error: 'Guard session expired' });
      }

      const parsed = GuardIncidentSubmitSchema.safeParse(req.body);
      if (!parsed.success) {
        return res.status(400).json({
          error: parsed.error.errors[0]?.message || 'Validation error',
          details: parsed.error.errors,
        });
      }

      const data = parsed.data;

      let effectiveIdempotencyKey = data.idempotencyKey;

      // Idempotency check: prevent duplicate submission from rapid network retries (< 90 seconds)
      const existing = await db.getIncidentByIdempotencyKey(effectiveIdempotencyKey);
      if (existing) {
        const existingTime = new Date(existing.created_at || existing.server_submitted_at).getTime();
        const timeDiffMs = Math.abs(Date.now() - existingTime);

        // Only treat as a duplicate network retry if within 90 seconds AND identical summary
        if (timeDiffMs < 90000 && existing.summary_line === data.summaryLine) {
          res.clearCookie('uss_guard_session');
          return res.json({
            success: true,
            refNumber: existing.ref_number,
            incidentId: existing.id,
            message: 'Incident was previously recorded.',
          });
        }

        // If the key collided from an old browser draft/cache or is a distinct report, do NOT swallow it!
        // Assign a new guaranteed unique key so this new incident is saved and visible on admin dashboard
        console.warn(`[GUARD_SUBMIT] Idempotency collision on key '${effectiveIdempotencyKey}'. Re-keying to ensure report is not lost.`);
        effectiveIdempotencyKey = `${effectiveIdempotencyKey}_new_${Date.now()}_${Math.random().toString(36).substring(2, 6)}`;
      }

      // Check site assignment
      if (!req.guard.assignedSites.includes(data.siteId)) {
        return res.status(403).json({
          error: 'You are not authorized to submit reports for this site.',
        });
      }

      const site = await db.getSiteById(data.siteId);
      if (!site) {
        return res.status(404).json({ error: 'Site not found' });
      }

      // Generate sequence and reference number
      const existingCountForDate = await db.getIncidentsCount(
        site.id,
        data.incidentDate
      );
      const refNumber = generateIncidentReference(
        site.code,
        data.incidentDate,
        existingCountForDate + 1
      );

      const incidentId = generateUuid();

      // Process uploaded photos (up to 10 photos)
      const savedPhotos: any[] = [];
      if (data.photos && Array.isArray(data.photos)) {
        let order = 0;
        for (const p of data.photos.slice(0, 10)) {
          if (p.dataBase64) {
            try {
              // Strip data URI prefix if present
              const base64Data = p.dataBase64.replace(
                /^data:image\/\w+;base64,/,
                ''
              );
              const buffer = Buffer.from(base64Data, 'base64');
              const saved = await storage.saveFile(
                buffer,
                p.originalFilename || `evidence_${order + 1}.jpg`,
                'incidents'
              );

              const photoRecord = {
                id: generateUuid(),
                incident_id: incidentId,
                storage_key: saved.storageKey,
                thumbnail_key: saved.storageKey,
                original_filename: saved.originalFilename,
                mime_type: saved.mimeType,
                file_size: saved.fileSize,
                caption: p.caption || null,
                captured_at: new Date().toISOString(),
                sort_order: order++,
                created_at: new Date().toISOString(),
              };

              await db.saveIncidentPhoto(photoRecord);
              savedPhotos.push(photoRecord);
            } catch (err: any) {
              console.warn('[STORAGE] Failed to save photo:', err.message);
            }
          }
        }
      }

      // Sanitize rich text description (strips dangerous tags, preserves styling & plain text)
      const sanitizedDesc = sanitizeRichText(data.description);

      // Create Incident Record
      const incidentRecord = {
        id: incidentId,
        ref_number: refNumber,
        idempotency_key: effectiveIdempotencyKey,
        site_id: site.id,
        guard_id: req.guard.id,
        reported_by_name: req.guard.fullName,
        reported_by_designation: req.guard.designation,
        incident_date: data.incidentDate,
        incident_time: data.incidentTime,
        server_submitted_at: new Date().toISOString(),
        shift: data.shift,
        cause_main: data.causeMain,
        disciplinary_reasons: data.disciplinaryReasons,
        disciplinary_other_text: data.disciplinaryOtherText || null,
        incident_types: data.incidentTypes,
        incident_other_text: data.incidentOtherText || null,
        summary_line: data.summaryLine,
        description: sanitizedDesc.html || data.description,
        description_plain: sanitizedDesc.text,
        who_notified: data.whoNotified,
        notified_other_text: data.notifiedOtherText || null,
        site_supervisor_name: data.siteSupervisorName || null,
        site_supervisor_id: data.siteSupervisorId || null,
        site_supervisor_date: data.siteSupervisorDate || data.incidentDate,
        site_supervisor_signature: data.siteSupervisorSignature || null,
        status: 'Submitted',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      };

      await db.saveIncident(incidentRecord);

      // REAL-TIME BROADCAST: push to Super Admin dashboard within ~2 seconds
      realtimeService.broadcastIncident({
        ...incidentRecord,
        site_name: site.name,
        site_code: site.code,
        photos: savedPhotos,
      });

      // Audit Log
      await audit.log({
        actorType: 'GUARD',
        actorId: req.guard.id,
        actorName: req.guard.fullName,
        action: 'SUBMIT_INCIDENT',
        targetType: 'INCIDENT',
        targetId: incidentId,
        details: {
          refNumber,
          siteCode: site.code,
          causes: data.causeMain,
          incidentTypes: data.incidentTypes,
          photoCount: savedPhotos.length,
        },
        req,
      });

      // AUTO-LOGOUT ON SUCCESS: Destroy guard session cookie immediately
      res.clearCookie('uss_guard_session');

      return res.status(201).json({
        success: true,
        refNumber,
        incidentId,
        message:
          'Incident report submitted successfully. You have been securely logged out.',
      });
    } catch (err: any) {
      return res.status(500).json({ error: err.message });
    }
  }

  /**
   * Manual Guard Logout
   */
  async logout(req: Request, res: Response) {
    res.clearCookie('uss_guard_session');
    return res.json({ success: true, message: 'Logged out successfully' });
  }
}

export const guardController = new GuardController();
