import express from 'express';
import cors from 'cors';
import helmet from 'helmet';
import cookieParser from 'cookie-parser';
import path from 'path';
import { config } from './config/index.js';
import { guardRouter } from './routes/guard.js';
import { adminRouter } from './routes/admin.js';

export function createApp() {
  const app = express();

  // Trust Cloudflare tunnel and reverse proxies for rate limiting & secure cookies
  app.set('trust proxy', 1);

  // Security Headers
  app.use(
    helmet({
      crossOriginResourcePolicy: { policy: 'cross-origin' },
      contentSecurityPolicy: false, // Managed at Caddy/Nginx reverse proxy
    })
  );

  // CORS Configuration
  app.use(
    cors({
      origin: (origin, callback) => {
        if (!origin) return callback(null, true);
        if (config.env !== 'production') return callback(null, true);
        if (
          origin === config.corsOrigin ||
          origin === 'http://localhost:5173' ||
          origin === 'http://127.0.0.1:5173' ||
          origin.startsWith('http://192.168.') ||
          origin.startsWith('http://10.') ||
          origin.endsWith('.loca.lt') ||
          origin.endsWith('.trycloudflare.com') ||
          origin.endsWith('.ngrok-free.app') ||
          (config.appDomain && origin.includes(config.appDomain))
        ) {
          return callback(null, true);
        }
        callback(null, false);
      },
      credentials: true,
      methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
      allowedHeaders: ['Content-Type', 'Authorization', 'X-Requested-With'],
    })
  );

  // Body and cookie parsing
  app.use(express.json({ limit: '50mb' }));
  app.use(express.urlencoded({ extended: true, limit: '50mb' }));
  app.use(cookieParser());

  // Static uploads (local fallback storage driver)
  app.use('/uploads', express.static(config.storage.localUploadDir));

  // Health check endpoint
  app.get('/api/health', (req, res) => {
    res.json({
      status: 'UP',
      system: 'USS Incident Report System',
      time: new Date().toISOString(),
      timezone: config.timezone,
    });
  });

  // Prevent any mobile carrier, edge tunnel, or browser from caching API responses
  app.use('/api', (req, res, next) => {
    res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate');
    res.setHeader('Pragma', 'no-cache');
    res.setHeader('Expires', '0');
    res.setHeader('Surrogate-Control', 'no-store');
    next();
  });

  // Isolated API Realms
  app.use('/api/guard', guardRouter);
  app.use('/api/admin', adminRouter);

  // Fallback 404 handler for admin surfaces to prevent enumeration
  app.use('/api/admin/*', (req, res) => {
    res.status(404).json({ error: 'Not Found' });
  });

  // Central error handling
  app.use(
    (
      err: any,
      req: express.Request,
      res: express.Response,
      next: express.NextFunction
    ) => {
      console.error('[SERVER ERROR]', err);
      res.status(err.status || 500).json({
        error:
          config.env === 'production'
            ? 'Internal server error occurred'
            : err.message || 'Server error',
      });
    }
  );

  return app;
}
