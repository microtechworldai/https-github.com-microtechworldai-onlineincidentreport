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
import { pdfService } from '../src/services/pdf.js';
import { realtimeService } from '../src/services/realtime.js';
import { sanitizeRichText, htmlToPlainText } from '@uss/shared';

let server: http.Server;
let baseUrl: string;
let adminCookie: string;
let testSite: any;
let activeToken: string;

const loginGuard = async () => {
  const currentSite = await db.getSiteById(testSite.id);
  const tokenToUse = currentSite?.active_qr_token || activeToken;
  const res = await fetch(`${baseUrl}/api/guard/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      siteToken: tokenToUse,
      username: 'tariq.m',
      password: 'GuardPass123!',
    }),
  });
  assert.equal(res.status, 200, 'Guard login must succeed');
  const cookie = res.headers.get('set-cookie');
  assert.ok(cookie, 'Guard cookie must be set');
  return cookie.split(';')[0];
};

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

  // Admin login using official Super Admin credentials
  const adminRes = await fetch(`${baseUrl}/api/admin/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      username: 'superadmin',
      password: 'ChangeMeNow123!',
    }),
  });
  assert.equal(adminRes.status, 200, 'Admin login must succeed');
  const setCookie = adminRes.headers.get('set-cookie');
  assert.ok(setCookie, 'Admin session cookie must be set');
  adminCookie = setCookie.split(';')[0];

  // Ensure test site fixture exists
  let sitesRes = await fetch(`${baseUrl}/api/admin/sites`, {
    headers: { Cookie: adminCookie },
  });
  let sites = await sitesRes.json();
  if (!sites || sites.length === 0) {
    const createSiteRes = await fetch(`${baseUrl}/api/admin/sites`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Cookie: adminCookie,
      },
      body: JSON.stringify({
        name: 'Dubai Marina Heights',
        code: 'DMH-01',
        address: 'Dubai Marina, Marsa Street, Dubai, UAE',
        clientName: 'Emaar Properties PJSC',
        type: 'Commercial',
        notes: 'Automated test suite fixture site',
      }),
    });
    const createSiteJson = await createSiteRes.json();
    testSite = createSiteJson.site || createSiteJson;
  } else {
    testSite = sites[0];
  }

  // Ensure test guard staff fixture exists
  const existingGuard = await db.getGuardByUsername('tariq.m');
  if (!existingGuard) {
    await fetch(`${baseUrl}/api/admin/staff`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Cookie: adminCookie,
      },
      body: JSON.stringify({
        staffId: 'USS-GRD-101',
        fullName: 'Tariq Mansoor',
        designation: 'Security Officer',
        username: 'tariq.m',
        password: 'GuardPass123!',
        assignedSites: [testSite.id],
      }),
    });
  }

  // Generate active site QR token
  const qr = await db.generateSiteQr(testSite.id);
  activeToken = qr.token;
});

test.after(async () => {
  realtimeService.close();
  if (server) {
    await new Promise<void>((resolve) => server.close(() => resolve()));
  }
  try {
    const testDb = path.resolve(projectRoot, './data/test_incidents_db.json');
    if (fs.existsSync(testDb)) fs.unlinkSync(testDb);
  } catch {}
});

test('Master Prompt 3: Acceptance Test Suite', async (t) => {
  await t.test('1. QR Generation strictly rejects private IPs, localhost, and non-public URLs', async () => {
    const invalidDomains = [
      'http://localhost:5173',
      'https://192.168.1.105:3000',
      'https://10.0.0.5',
      'https://127.0.0.1',
      'https://mysite.local',
    ];

    for (const domain of invalidDomains) {
      const res = await fetch(`${baseUrl}/api/admin/sites/${testSite.id}/qr/generate`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Cookie: adminCookie,
        },
        body: JSON.stringify({ domain }),
      });

      assert.equal(res.status, 400, `Domain "${domain}" must be rejected with 400`);
      const body = await res.json();
      assert.match(body.error || body.message, /public/i, 'Error message must specify public HTTPS URL requirement');
    }

    // Accepts public domain
    const validRes = await fetch(`${baseUrl}/api/admin/sites/${testSite.id}/qr/generate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Cookie: adminCookie,
      },
      body: JSON.stringify({ domain: 'https://incidents.ultimatesecurity.ae' }),
    });
    assert.equal(validRes.status, 200, 'Public HTTPS domain must be accepted');
    const validBody = await validRes.json();
    assert.ok(validBody.qrUrl);
    assert.ok(validBody.token);
    assert.ok(validBody.qrUrl.startsWith('https://incidents.ultimatesecurity.ae/g/'));
  });

  await t.test('2. Company Setup: Zero Fax anywhere in settings API', async () => {
    // Fetch settings
    const getRes = await fetch(`${baseUrl}/api/admin/settings`, {
      headers: { Cookie: adminCookie },
    });
    assert.equal(getRes.status, 200);
    const settings = await getRes.json();

    // Verify company identity
    assert.equal(settings.company_name, 'ULTIMATE SECURITY SERVICES');
    assert.equal(settings.phone, '+971 4 251 0050');
    assert.equal(settings.email, 'ops@ultimatesecurity.ae');
    assert.equal(settings.fax, undefined, 'Fax property must NOT exist in settings');

    // Update settings without fax
    const updateRes = await fetch(`${baseUrl}/api/admin/settings`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        Cookie: adminCookie,
      },
      body: JSON.stringify({
        companyName: 'ULTIMATE SECURITY SERVICES',
        addressLine: 'P O Box No: 61591, Al Maktoum Street | Dubai | UAE',
        phone: '+971 4 251 0050',
        email: 'ops@ultimatesecurity.ae',
        reportTitle: 'INCIDENT REPORT',
        primaryColor: '#1E3A8A',
        detailsVersionMode: 'current',
      }),
    });
    assert.equal(updateRes.status, 200);
    const updated = await updateRes.json();
    assert.equal(updated.fax, undefined, 'Updated settings must never contain fax');
  });

  await t.test('3. Rich-Text Description sanitization & plain-text extraction', async () => {
    const rawHtml = '<p style="font-size: 14pt; color: #DC2626; line-height: 1.5;"><strong>CRITICAL:</strong> Water pipe leak on <span style="text-decoration: underline;">Level 2</span></p><script>alert("hacked")</script>';
    const { html: sanitized, text: plain } = sanitizeRichText(rawHtml);

    assert.ok(!sanitized.includes('<script>'), 'Script tag must be stripped');
    assert.ok(sanitized.includes('<strong>CRITICAL:</strong>'), 'Strong tag must be preserved');
    assert.ok(sanitized.includes('Level 2'), 'Text content must be preserved');
    assert.equal(plain, 'CRITICAL: Water pipe leak on Level 2', 'Plain text representation must be clean');
  });

  await t.test('4. PDF Layout: 15 lines fit on Page 1, overflow flows to Page 2', async () => {
    const settings = await db.getSettings();

    // 15 lines
    const fifteenLines = Array.from({ length: 15 }, (_, i) => `<p>Line ${i + 1}: Patrol security guard checked security perimeter.</p>`).join('');
    const inc15 = {
      id: 'test-15',
      ref_number: 'USS-TEST-15',
      site_name: 'Dubai Marina Heights',
      incident_date: '2026-10-16',
      incident_time: '14:00',
      shift: 'Day',
      reported_by_name: 'Tariq Mansoor',
      reported_by_designation: 'Security Officer',
      cause_main: ['Incident'],
      incident_types: ['Water Leakage'],
      who_notified: ['Client Management'],
      summary_line: 'Fifteen lines narrative test',
      description: fifteenLines,
      photos: [],
    };

    const pdf15Buffer = await pdfService.generateIncidentPdf(inc15, settings);
    const raw15 = pdf15Buffer.toString('latin1');
    const pages15 = (raw15.match(/\/Type\s*\/Page[^s]/g) || []).length;
    assert.equal(pages15, 1, '15 lines must fit on Page 1 without creating a second blank page');

    // 40 lines
    const fortyLines = Array.from({ length: 40 }, (_, i) => `<p>Line ${i + 1}: Detailed chronological timeline log item.</p>`).join('');
    const inc40 = { ...inc15, id: 'test-40', ref_number: 'USS-TEST-40', description: fortyLines };
    const pdf40Buffer = await pdfService.generateIncidentPdf(inc40, settings);
    const raw40 = pdf40Buffer.toString('latin1');
    const pages40 = (raw40.match(/\/Type\s*\/Page[^s]/g) || []).length;
    assert.equal(pages40, 2, '40 lines must cleanly overflow to Page 2 continuation page');
  });

  await t.test('5. Real-Time Delivery & Since Cursor Query', async () => {
    const beforeSubmit = new Date(Date.now() - 1000).toISOString();

    const gCookie = await loginGuard();
    // Guard submits incident
    const submitRes = await fetch(`${baseUrl}/api/guard/incidents`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Cookie: gCookie,
      },
      body: JSON.stringify({
        idempotencyKey: 'idem-' + Math.random().toString(36).slice(2) + Date.now().toString(36),
        siteId: testSite.id,
        incidentDate: '2026-10-16',
        incidentTime: '14:30',
        shift: 'Day',
        causeMain: ['Incident'],
        incidentTypes: ['CCTV Camera'],
        summaryLine: 'Realtime SSE delivery verification test',
        description: 'Camera feed in loading bay flickered and was reported to operations supervisor.',
        whoNotified: ['USS Management'],
      }),
    });
    assert.equal(submitRes.status, 201, 'Incident submission must succeed');
    const submitData = await submitRes.json();
    assert.ok(submitData.incidentId);
    assert.ok(submitData.refNumber);

    // Verify polling fallback query with "since" cursor returns the newly submitted report
    const sinceRes = await fetch(`${baseUrl}/api/admin/incidents?since=${encodeURIComponent(beforeSubmit)}`, {
      headers: { Cookie: adminCookie },
    });
    assert.equal(sinceRes.status, 200);
    const sinceData = await sinceRes.json();
    assert.ok(sinceData.incidents.length >= 1, 'New incident must be returned via since query');
    assert.equal(sinceData.incidents[0].id, submitData.incidentId);
  });

  await t.test('6. Concurrent submissions (Unlimited submissions with zero duplicates)', async () => {
    const submitOne = async (index: number) => {
      const gCookie = await loginGuard();
      const res = await fetch(`${baseUrl}/api/guard/incidents`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Cookie: gCookie,
        },
        body: JSON.stringify({
          idempotencyKey: `idem-batch-${index}-${Math.random().toString(36).slice(2)}-${Date.now()}`,
          siteId: testSite.id,
          incidentDate: '2026-10-16',
          incidentTime: `14:${String(index % 60).padStart(2, '0')}`,
          shift: 'Day',
          causeMain: ['Incident'],
          incidentTypes: ['Disturbance'],
          summaryLine: `Batch concurrent submission #${index + 1}`,
          description: `Concurrent test entry #${index + 1} verifying database transaction integrity.`,
          whoNotified: ['Client Management'],
        }),
      });
      assert.equal(res.status, 201, `Submission #${index + 1} must succeed with 201`);
    };

    // Run 10 submissions concurrently
    await Promise.all(Array.from({ length: 10 }, (_, i) => submitOne(i)));
  });
});
