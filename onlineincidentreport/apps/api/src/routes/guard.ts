import { Router } from 'express';
import rateLimit from 'express-rate-limit';
import { guardController } from '../controllers/guard.js';
import { guardAuthMiddleware } from '../middleware/auth.js';

const router = Router();

// Rate limiting on guard login (max 15 attempts per 15 minutes per IP; relaxed in test mode)
const loginLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: process.env.NODE_ENV === 'test' ? 1000 : 15,
  message: { error: 'Too many login attempts. Please wait 15 minutes and try again.' },
  standardHeaders: true,
  legacyHeaders: false,
});

// Validate site QR token (supports param, wildcards with slashes, and query param)
router.get('/site-by-token/:token(*)', (req, res) =>
  guardController.getSiteByToken(req, res)
);
router.get('/site-by-token', (req, res) =>
  guardController.getSiteByToken(req, res)
);

// Guard login with site token + username + password
router.post('/login', loginLimiter, (req, res) =>
  guardController.login(req, res)
);

// Public / Guard Settings (configurable checkboxes & details)
router.get('/settings', (req, res) =>
  guardController.getSettings(req, res)
);

// Protected guard routes
router.get('/me', guardAuthMiddleware, (req, res) =>
  guardController.getMe(req as any, res)
);

router.post('/incidents', guardAuthMiddleware, (req, res) =>
  guardController.submitIncident(req as any, res)
);

router.post('/logout', (req, res) =>
  guardController.logout(req, res)
);

export const guardRouter = router;
