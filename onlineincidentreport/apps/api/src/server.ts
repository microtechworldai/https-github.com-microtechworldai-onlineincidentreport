import { createApp } from './app.js';
import { config } from './config/index.js';
import { db } from './db/index.js';
import { runSeedIfNeeded } from './db/seed.js';

async function bootstrap() {
  console.log('----------------------------------------------------');
  console.log('Ultimate Security Services (USS) Dubai');
  console.log('Incident Report System - API Server');
  console.log('----------------------------------------------------');

  // Initialize Database
  await db.init();

  // Run seed if this is the first boot
  await runSeedIfNeeded();

  const app = createApp();

  const server = app.listen(config.port, () => {
    console.log(`[API] Server listening on port ${config.port} (Env: ${config.env})`);
    console.log(`[API] Guard Realm: http://localhost:${config.port}/api/guard`);
    console.log(`[API] Admin Realm: http://localhost:${config.port}/api/admin`);
  });

  process.on('SIGTERM', () => {
    console.log('[API] SIGTERM received. Shutting down gracefully...');
    server.close(() => {
      console.log('[API] Server closed.');
      process.exit(0);
    });
  });
}

bootstrap().catch((err) => {
  console.error('[API] Fatal bootstrap error:', err);
  process.exit(1);
});
