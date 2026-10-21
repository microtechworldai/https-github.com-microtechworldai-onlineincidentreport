process.env.NODE_ENV = 'test';
import test from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import jwt from 'jsonwebtoken';
import { createApp } from '../src/app.js';
import { config, projectRoot } from '../src/config/index.js';
import { db } from '../src/db/index.js';
import { runFullSeed } from '../src/db/seed.js';

let server: http.Server;
let baseUrl: string;

test.before(async () => {
  await db.init();
  await runFullSeed();
  const app = createApp();
  await new Promise<void>((resolve) => {
    server = app.listen(0, () => {
      const addr = server.address() as any;
      baseUrl = `http://127.0.0.1:${addr.port}`;
      resolve();
    });
  });
});

test.after(async () => {
  if (server) {
    await new Promise<void>((resolve) => server.close(() => resolve()));
  }
  try {
    const testDb = path.resolve(projectRoot, './data/test_incidents_db.json');
    if (fs.existsSync(testDb)) fs.unlinkSync(testDb);
  } catch {}
});

test('Dual-Realm Security Isolation Test Suite (Real HTTP)', async (t) => {
  // Generate a legitimate Guard Realm Token (aud: uss-guard)
  const guardToken = jwt.sign(
    {
      sub: '00000000-0000-4000-8000-000000000099',
      staffId: 'SG-TEST-01',
      aud: 'uss-guard',
    },
    config.jwt.guardSecret,
    { expiresIn: '1h' }
  );

  const adminEndpoints = [
    { method: 'GET', path: '/api/admin/me' },
    { method: 'GET', path: '/api/admin/dashboard/stats' },
    { method: 'GET', path: '/api/admin/incidents' },
    { method: 'GET', path: '/api/admin/sites' },
    { method: 'GET', path: '/api/admin/staff' },
    { method: 'GET', path: '/api/admin/settings' },
    { method: 'GET', path: '/api/admin/audit-logs' },
    { method: 'GET', path: '/api/admin/backups/export' },
    { method: 'POST', path: '/api/admin/change-password' },
    { method: 'POST', path: '/api/admin/sites' },
    { method: 'POST', path: '/api/admin/staff' },
  ];

  for (const endpoint of adminEndpoints) {
    await t.test(
      `Guard session MUST receive 404 Not Found on ${endpoint.method} ${endpoint.path}`,
      async () => {
        const res = await fetch(`${baseUrl}${endpoint.path}`, {
          method: endpoint.method,
          headers: {
            Authorization: `Bearer ${guardToken}`,
            'Content-Type': 'application/json',
          },
          body: endpoint.method === 'POST' ? JSON.stringify({}) : undefined,
        });

        assert.equal(
          res.status,
          404,
          `Expected 404 Not Found for guard on ${endpoint.path}, received ${res.status}`
        );
      }
    );

    await t.test(
      `Unauthenticated visitor MUST receive 404 Not Found on ${endpoint.method} ${endpoint.path}`,
      async () => {
        const res = await fetch(`${baseUrl}${endpoint.path}`, {
          method: endpoint.method,
          headers: {
            'Content-Type': 'application/json',
          },
          body: endpoint.method === 'POST' ? JSON.stringify({}) : undefined,
        });

        assert.equal(
          res.status,
          404,
          `Expected 404 Not Found for visitor on ${endpoint.path}, received ${res.status}`
        );
      }
    );
  }
});
