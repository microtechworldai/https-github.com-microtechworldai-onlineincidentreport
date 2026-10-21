process.env.NODE_ENV = 'test';
import test from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { createApp } from '../src/app.js';
import { projectRoot } from '../src/config/index.js';
import { db } from '../src/db/index.js';
import { runFullSeed } from '../src/db/seed.js';
import { extractSiteToken } from '@uss/shared';

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

test('Mobile Scanner & QR Token Extraction Suite', async (t) => {
  await t.test('extractSiteToken correctly parses direct tokens and URLs', () => {
    const rawToken = 'marina7a9b2c3d4e5f60718293a4b5c6d7';

    // Direct token
    assert.equal(extractSiteToken(rawToken), rawToken);

    // Full HTTPS production URL
    assert.equal(
      extractSiteToken(`https://incidents.uss-security.ae/g/${rawToken}`),
      rawToken
    );

    // Localhost dev URL
    assert.equal(
      extractSiteToken(`http://localhost:5173/g/${rawToken}`),
      rawToken
    );

    // Mobile IP dev URL with query parameters and hash
    assert.equal(
      extractSiteToken(`http://192.168.1.100:5173/g/${rawToken}?scan=camera#checkpoint`),
      rawToken
    );

    // Quoted string from barcode scanner
    assert.equal(
      extractSiteToken(`"${rawToken}"`),
      rawToken
    );

    // Relative route path
    assert.equal(
      extractSiteToken(`/g/${rawToken}`),
      rawToken
    );
  });

  await t.test('GET /api/guard/site-by-token/:token resolves valid site with raw token', async () => {
    let testSite = (await db.listSites()).find((s: any) => s.active_qr_token);
    if (!testSite) {
      testSite = await db.createSite({
        name: 'Marina Heights Tower',
        code: 'TEST-MHT',
        type: 'Commercial',
        address: 'Dubai Marina',
        clientName: 'Emaar Properties',
        clientContact: 'Operations Office',
        clientEmail: 'ops@emaar.ae',
        clientMobile: '+971 4 362 7555',
      });
      const qr = await db.generateSiteQr(testSite.id);
      testSite.active_qr_token = qr.token;
    }

    const res = await fetch(`${baseUrl}/api/guard/site-by-token/${testSite.active_qr_token}`);
    assert.equal(res.status, 200);
    const body = await res.json();
    assert.equal(body.valid, true);
    assert.equal(body.site.id, testSite.id);
  });

  await t.test('GET /api/guard/site-by-token works when scanner passes full URL', async () => {
    let testSite = (await db.listSites()).find((s: any) => s.active_qr_token);
    if (!testSite) {
      testSite = await db.createSite({
        name: 'Marina Heights Tower',
        code: 'TEST-MHT',
        type: 'Commercial',
        address: 'Dubai Marina',
        clientName: 'Emaar Properties',
        clientContact: 'Operations Office',
      });
      const qr = await db.generateSiteQr(testSite.id);
      testSite.active_qr_token = qr.token;
    }

    const fullUrl = `https://incidents.uss-security.ae/g/${testSite.active_qr_token}`;
    const res = await fetch(`${baseUrl}/api/guard/site-by-token/${encodeURIComponent(fullUrl)}`);
    assert.equal(res.status, 200);
    const body = await res.json();
    assert.equal(body.valid, true);
    assert.equal(body.site.id, testSite.id);
  });

  await t.test('POST /api/guard/login succeeds when siteToken is a full URL from scanner', async () => {
    let testSite = (await db.listSites()).find((s: any) => s.active_qr_token);
    if (!testSite) {
      testSite = await db.createSite({
        name: 'Marina Heights Tower',
        code: 'TEST-MHT',
        type: 'Commercial',
        address: 'Dubai Marina',
      });
      const qr = await db.generateSiteQr(testSite.id);
      testSite.active_qr_token = qr.token;
    }

    const { hashPassword } = await import('../src/utils/crypto.js');
    const tempGuardId = crypto.randomUUID();
    const tempUsername = `tempguard_${Date.now()}`;
    const testGuard = {
      id: tempGuardId,
      staff_id: 'TEMP-SG',
      full_name: 'Temp Guard',
      designation: 'Security Officer',
      username: tempUsername,
      password_hash: await hashPassword('GuardPass123!'),
      is_active: true,
    };
    await db.saveGuard(testGuard, [testSite.id]);

    try {
      const res = await fetch(`${baseUrl}/api/guard/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          siteToken: `http://localhost:5173/g/${testSite.active_qr_token}`,
          username: tempUsername,
          password: 'GuardPass123!',
        }),
      });
      assert.equal(res.status, 200);
      const body = await res.json();
      assert.ok(body.token, 'Must return JWT guard session token');
      assert.equal(body.guard.username, tempUsername);
      assert.equal(body.site.id, testSite.id);
    } finally {
      await db.deleteGuard(tempGuardId);
    }
  });
});
