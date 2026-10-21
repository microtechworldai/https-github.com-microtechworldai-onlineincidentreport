import { Router } from 'express';
import rateLimit from 'express-rate-limit';
import { adminController } from '../controllers/admin.js';
import { adminAuthMiddleware } from '../middleware/auth.js';

const router = Router();

// Rate limiting on admin login (max 5 attempts per 15 minutes per IP)
const adminLoginLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 10,
  message: { error: 'Too many admin login attempts. Please wait 15 minutes.' },
  standardHeaders: true,
  legacyHeaders: false,
});

// Admin Login (public within admin realm)
router.post('/login', adminLoginLimiter, (req, res) =>
  adminController.login(req, res)
);

// ALL OTHER ADMIN ROUTES REQUIRE `adminAuthMiddleware`
// Unauthenticated or Guard sessions will receive 404 (anti-probing)
router.use(adminAuthMiddleware);

// Profile & Password
router.get('/me', (req, res) => adminController.getMe(req as any, res));
router.post('/change-password', (req, res) =>
  adminController.changePassword(req as any, res)
);
router.post('/logout', (req, res) => adminController.logout(req, res));

// Dashboard Stats
router.get('/dashboard/stats', (req, res) =>
  adminController.getDashboardStats(req as any, res)
);

// Incident Reports & Real-Time Stream
router.get('/incidents/stream', (req, res) =>
  adminController.streamIncidents(req as any, res)
);
router.get('/incidents', (req, res) =>
  adminController.listIncidents(req as any, res)
);
router.post('/incidents', (req, res) =>
  adminController.createIncidentManual(req as any, res)
);
router.get('/incidents/:id', (req, res) =>
  adminController.getIncident(req as any, res)
);
router.put('/incidents/:id', (req, res) =>
  adminController.updateIncident(req as any, res)
);
router.post('/incidents/:id/notes', (req, res) =>
  adminController.addNote(req as any, res)
);
router.post('/incidents/:id/duplicate', (req, res) =>
  adminController.duplicateIncident(req as any, res)
);
router.delete('/incidents/:id', (req, res) =>
  adminController.softDeleteIncident(req as any, res)
);
router.post('/incidents/:id/restore', (req, res) =>
  adminController.restoreIncident(req as any, res)
);
router.delete('/incidents/:id/permanent', (req, res) =>
  adminController.permanentDeleteIncident(req as any, res)
);

// PDF and Excel Exports
router.get('/export/incident-pdf/:id', (req, res) =>
  adminController.exportSinglePdf(req as any, res)
);
router.post('/export/daily-pdf', (req, res) =>
  adminController.exportDailyPdf(req as any, res)
);
router.post('/export/daily-excel', (req, res) =>
  adminController.exportDailyExcel(req as any, res)
);

// Sites & QR Codes
router.get('/sites', (req, res) => adminController.listSites(req as any, res));
router.post('/sites', (req, res) => adminController.createSite(req as any, res));
router.put('/sites/:id', (req, res) =>
  adminController.updateSite(req as any, res)
);
router.delete('/sites/:id', (req, res) =>
  adminController.deleteSite(req as any, res)
);
router.post('/sites/:id/qr/generate', (req, res) =>
  adminController.generateSiteQr(req as any, res)
);
router.post('/sites/:id/qr/revoke', (req, res) =>
  adminController.revokeSiteQr(req as any, res)
);
router.get('/sites/:id/qr/poster', (req, res) =>
  adminController.getSiteQrPoster(req as any, res)
);
router.get('/sites/:id/qr/poster.pdf', (req, res) => {
  req.query.format = 'pdf';
  return adminController.getSiteQrPoster(req as any, res);
});

// Staff Guards Management
router.get('/staff', (req, res) => adminController.listStaff(req as any, res));
router.post('/staff', (req, res) => adminController.createStaff(req as any, res));
router.put('/staff/:id', (req, res) =>
  adminController.updateStaff(req as any, res)
);
router.delete('/staff/:id', (req, res) =>
  adminController.deleteStaff(req as any, res)
);
router.post('/staff/:id/reset-password', (req, res) =>
  adminController.resetStaffPassword(req as any, res)
);

// Settings, Audit & Backups
router.get('/settings', (req, res) =>
  adminController.getSettings(req as any, res)
);
router.put('/settings', (req, res) =>
  adminController.updateSettings(req as any, res)
);
router.post('/settings/logo', (req, res) =>
  adminController.uploadLogo(req as any, res)
);
router.get('/audit-logs', (req, res) =>
  adminController.listAuditLogs(req as any, res)
);
router.get('/backups/export', (req, res) =>
  adminController.exportBackup(req as any, res)
);
router.post('/backups/restore', (req, res) =>
  adminController.restoreBackup(req as any, res)
);

export const adminRouter = router;
