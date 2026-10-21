process.env.NODE_ENV = 'test';
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { projectRoot } from '../src/config/index.js';
import { pdfService } from '../src/services/pdf.js';
import { excelService } from '../src/services/excel.js';
import { db } from '../src/db/index.js';
import { runFullSeed } from '../src/db/seed.js';
import { storage } from '../src/services/storage.js';

test.before(async () => {
  await db.init();
  await runFullSeed();
});

test.after(async () => {
  try {
    const testDb = path.resolve(projectRoot, './data/test_incidents_db.json');
    if (fs.existsSync(testDb)) fs.unlinkSync(testDb);
  } catch {}
});

test('Incident Data & Export Engine Test Suite', async (t) => {
  let sites = await db.listSites();
  if (sites.length === 0) {
    const newSite = await db.createSite({
      name: 'Dubai Marina Test',
      code: 'TEST-DMT',
      type: 'Commercial',
      address: 'Dubai Marina',
    });
    sites = [newSite];
  }
  assert.ok(sites.length >= 1, 'At least one site must exist');
  const site = sites[0];

  let { incidents } = await db.listIncidents({ siteId: site.id });
  if (!incidents || incidents.length === 0) {
    const newInc = {
      id: 'test-incident-uuid-001',
      site_id: site.id,
      ref_number: 'USS-TEST-0001',
      incident_date: '2026-10-16',
      incident_time: '02:15',
      shift: 'Night',
      reported_by_name: 'Tariq Mansoor',
      reported_by_designation: 'Senior Security Guard',
      summary_line: 'Test incident summary line for test runner',
      description: 'Test incident thorough description explaining all sequence of events.',
      status: 'Submitted',
      priority: 'Medium',
      cause_main: ['Incident'],
      incident_types: ['Water Leakage'],
      who_notified: ['Client Management'],
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };
    await db.saveIncident(newInc);
    incidents = [newInc];
  }
  const incident = incidents[0];

  await t.test('Pixel-Perfect PDF Generation smoke test', async () => {
    const settings = await db.getSettings();
    const incidentNoPhotos = { ...incident, photos: [] };
    const pdfBuffer = await pdfService.generateIncidentPdf(incidentNoPhotos, settings);

    assert.ok(pdfBuffer, 'PDF buffer must be generated');
    assert.ok(pdfBuffer.length > 1000, 'PDF buffer must contain substantial content');

    // PDF magic bytes check (%PDF-)
    const pdfHeader = pdfBuffer.subarray(0, 5).toString('ascii');
    assert.equal(pdfHeader, '%PDF-', 'Buffer must be a genuine PDF file');

    // Assert exact single-page fit without trailing blank pages
    const rawContent = pdfBuffer.toString('latin1');
    const pageMatches = rawContent.match(/\/Type\s*\/Page[^s]/g);
    assert.equal(pageMatches ? pageMatches.length : 0, 1, 'Form-only incident report must fit on exactly 1 page');
  });

  await t.test('PDF with 4 photos generates exactly 2 pages (no trailing blank pages)', async () => {
    const settings = await db.getSettings();
    const incidentWithPhotos = {
      ...incident,
      description: 'Dear Sir,\n\nThis is to inform you that\nIt includes the database architecture for:\nUsers, Riders & Drivers\nAuthentication sessions & OTP\nDriver documents\nVehicles & vehicle types\nCities & service areas\nRides & ride events...',
      photos: [
        { id: 'p1', original_filename: 'leak.jpg', file_size: 116100, caption: 'Water leakage' },
        { id: 'p2', original_filename: 'cctv.jpg', file_size: 128400, caption: 'Blur CCTV camera' },
        { id: 'p3', original_filename: 'mobile.jpg', file_size: 136400, caption: 'Using mobile on duty' },
        { id: 'p4', original_filename: 'report.jpg', file_size: 176100, caption: 'Reporting failure' },
      ],
    };
    const pdfBuffer = await pdfService.generateIncidentPdf(incidentWithPhotos, settings);
    const rawContent = pdfBuffer.toString('latin1');
    const pageMatches = rawContent.match(/\/Type\s*\/Page[^s]/g);
    assert.equal(pageMatches ? pageMatches.length : 0, 2, 'Incident with 4 photos must be exactly 2 pages (Page 1: form, Page 2: photos)');
  });

  await t.test('Daily Consolidated PDF Generation smoke test', async () => {
    const settings = await db.getSettings();
    const pdfBuffer = await pdfService.generateDailyConsolidatedPdf(
      site,
      incident.incident_date,
      incidents,
      settings
    );

    assert.ok(pdfBuffer, 'Consolidated PDF buffer must be generated');
    assert.ok(pdfBuffer.length > 1000, 'Consolidated PDF must contain content');
    assert.equal(pdfBuffer.subarray(0, 5).toString('ascii'), '%PDF-');
  });

  await t.test('Excel (.xlsx) Generation smoke test', async () => {
    const excelBuffer = await excelService.generateDailyConsolidatedExcel(
      site,
      incident.incident_date,
      incidents
    );

    assert.ok(excelBuffer, 'Excel buffer must be generated');
    assert.ok(excelBuffer.length > 500, 'Excel buffer must be non-empty');

    // ZIP magic bytes (PK..) as .xlsx is an OpenXML ZIP archive
    assert.equal(excelBuffer[0], 0x50, 'Excel file must start with PK zip header');
    assert.equal(excelBuffer[1], 0x4b, 'Excel file must start with PK zip header');
  });

  await t.test('MIME Sniffing & Storage Security Validation', async () => {
    // Valid PNG header: 89 50 4E 47 0D 0A 1A 0A
    const fakePngBuffer = Buffer.from([
      0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a, 0x00, 0x00, 0x00, 0x0d,
      0x49, 0x48, 0x44, 0x52,
    ]);
    const detected = storage.detectMimeType(fakePngBuffer);
    assert.equal(detected, 'image/png');

    // Malicious executable disguised with .jpg extension
    const maliciousBuffer = Buffer.from('MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00');
    const rejected = storage.detectMimeType(maliciousBuffer);
    assert.equal(rejected, null, 'Disguised non-image files must be rejected');
  });
});
