import { Request, Response, NextFunction } from 'express';
import jwt from 'jsonwebtoken';
import { config } from '../config/index.js';
import { db } from '../db/index.js';

export interface AdminRequest extends Request {
  admin?: {
    id: string;
    username: string;
    fullName: string;
    mustChangePassword: boolean;
  };
}

export interface GuardRequest extends Request {
  guard?: {
    id: string;
    staffId: string;
    fullName: string;
    designation: string;
    username: string;
    assignedSites: string[];
    siteId?: string;
  };
}

/**
 * Super Admin Authentication Middleware (Hard Isolation Realm)
 * CRITICAL DEFENSE: If unauthenticated or token is invalid/guard-realm,
 * returns 404 (Not Found) instead of 401/403 to prevent enumeration of admin surfaces.
 */
export async function adminAuthMiddleware(
  req: AdminRequest,
  res: Response,
  next: NextFunction
) {
  try {
    const token =
      req.cookies?.uss_admin_session ||
      (req.headers.authorization?.startsWith('Bearer ')
        ? req.headers.authorization.slice(7)
        : null);

    if (!token) {
      return res.status(404).json({ error: 'Not Found' });
    }

    const decoded = jwt.verify(token, config.jwt.adminSecret, {
      audience: 'uss-admin',
    }) as any;

    if (!decoded || !decoded.sub) {
      return res.status(404).json({ error: 'Not Found' });
    }

    const admin = await db.getAdminById(decoded.sub);
    if (!admin) {
      return res.status(404).json({ error: 'Not Found' });
    }

    req.admin = {
      id: admin.id,
      username: admin.username,
      fullName: admin.full_name,
      mustChangePassword: Boolean(admin.must_change_password),
    };

    next();
  } catch {
    // Return 404 on any failure (expired, wrong secret, guard token, etc.)
    return res.status(404).json({ error: 'Not Found' });
  }
}

/**
 * Security Guard Authentication Middleware (Hard Isolation Realm)
 */
export async function guardAuthMiddleware(
  req: GuardRequest,
  res: Response,
  next: NextFunction
) {
  try {
    const token =
      req.cookies?.uss_guard_session ||
      (req.headers.authorization?.startsWith('Bearer ')
        ? req.headers.authorization.slice(7)
        : null);

    if (!token) {
      return res.status(401).json({ error: 'Guard session expired or missing' });
    }

    const decoded = jwt.verify(token, config.jwt.guardSecret, {
      audience: 'uss-guard',
    }) as any;

    if (!decoded || !decoded.sub) {
      return res.status(401).json({ error: 'Invalid session' });
    }

    const guard = await db.getGuardById(decoded.sub);
    if (!guard || !guard.is_active) {
      return res.status(401).json({ error: 'Guard account disabled or removed' });
    }

    req.guard = {
      id: guard.id,
      staffId: guard.staff_id,
      fullName: guard.full_name,
      designation: guard.designation,
      username: guard.username,
      assignedSites: guard.assignedSites || [],
      siteId: decoded.siteId,
    };

    next();
  } catch {
    return res.status(401).json({ error: 'Guard session expired. Please scan QR to log in.' });
  }
}
