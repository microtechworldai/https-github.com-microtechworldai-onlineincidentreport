import { Request } from 'express';
import { db } from '../db/index.js';
import { generateUuid } from '../utils/crypto.js';

export class AuditService {
  async log(params: {
    actorType: 'SUPER_ADMIN' | 'GUARD' | 'SYSTEM';
    actorId?: string | null;
    actorName?: string | null;
    action: string;
    targetType: string;
    targetId?: string | null;
    details?: Record<string, any>;
    req?: Request;
  }): Promise<void> {
    try {
      let ipAddress: string | null = null;
      let userAgent: string | null = null;

      if (params.req) {
        let rawIp =
          (params.req.headers['x-forwarded-for'] as string)?.split(',')[0]?.trim() ||
          (params.req.headers['x-real-ip'] as string)?.trim() ||
          params.req.socket.remoteAddress ||
          '127.0.0.1';
        rawIp = rawIp.replace(/^::ffff:/, '').trim();
        if (rawIp === '::1') rawIp = '127.0.0.1';
        ipAddress = rawIp;
        userAgent = params.req.headers['user-agent'] || null;
      }

      await db.addAuditLog({
        id: generateUuid(),
        actor_type: params.actorType,
        actor_id: params.actorId || null,
        actor_name: params.actorName || null,
        action: params.action,
        target_type: params.targetType,
        target_id: params.targetId || null,
        ip_address: ipAddress,
        user_agent: userAgent,
        details: params.details || {},
        created_at: new Date().toISOString(),
      });
    } catch (err) {
      console.error('[AUDIT] Failed to persist audit log:', err);
    }
  }
}

export const audit = new AuditService();
