import { config } from '../config/index.js';
import { db } from './index.js';
import {
  hashPassword,
  generateUuid,
  generateSiteQrToken,
} from '../utils/crypto.js';
import { generateIncidentReference } from '../utils/reference.js';
import { DEFAULT_COMPANY_SETTINGS } from '@uss/shared';

export async function runSeedIfNeeded() {
  const existingAdmin = await db.getAdminByUsername(config.superadmin.username);
  if (!existingAdmin) {
    console.log('[SEED] No Super Admin detected. Seeding initial environment dataset...');
    await runFullSeed();
  }
}

export async function runFullSeed() {
  console.log('[SEED] Starting full initial seed...');

  // 1. Seed Super Admin (Force change password on first login)
  const adminPasswordHash = await hashPassword(config.superadmin.password);
  const adminId = '11111111-1111-4111-8111-111111111111';
  await db.saveAdmin({
    id: adminId,
    username: config.superadmin.username,
    password_hash: adminPasswordHash,
    full_name: config.superadmin.name,
    must_change_password: true,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
  });
  console.log(`[SEED] Created Super Admin account: "${config.superadmin.username}"`);

  // 2. Seed Default Company Settings
  await db.saveSettings({
    id: 'default-settings',
    company_name: DEFAULT_COMPANY_SETTINGS.companyName,
    tagline: DEFAULT_COMPANY_SETTINGS.tagline,
    address_line: DEFAULT_COMPANY_SETTINGS.addressLine,
    phone: DEFAULT_COMPANY_SETTINGS.phone,
    email: DEFAULT_COMPANY_SETTINGS.email,
    logo_url: '/assets/uss-logo.png',
    timezone: 'Asia/Dubai',
    shift_options: ['Day', 'Night'],
    cause_options: ['Incident', 'Disciplinary', 'Others'],
    disciplinary_options: [
      'Sleeping',
      'Poor Grooming',
      'Argument',
      'Missing',
      'Misbehavior',
      'Uniform',
      'Late Reporting',
      'Mobile',
      'Others',
    ],
    incident_options: [
      'Move In & Out',
      'Fire & Safety',
      'Intrusion & Access',
      'Theft and loss',
      'CCTV & Systems',
      'Utilities & Building',
      'Accident & Medical',
      'Disturbance',
      'Property Damage',
      'Parking & Vehicles',
      'Visitors & Contractors',
      'Others',
    ],
    notified_options: [
      'Police',
      'Ambulance',
      'Civil Defense',
      'Client Management',
      'USS Management',
      'Others',
    ],
    updated_at: new Date().toISOString(),
  });

  // 3. Test Sites & Test Staff auto-creation disabled per project requirements
  // Sites, staff accounts, and incident reports are NOT created automatically.
  // Super Admin will create real sites and security staff via the dashboard.
  console.log('[SEED] System initialization completed successfully (Automatic test staff and test site creation is disabled).');
}

// Standalone execution
if (process.argv[1]?.endsWith('seed.ts') || process.argv[1]?.endsWith('seed.js')) {
  db.init().then(() => runFullSeed()).then(() => process.exit(0)).catch((err) => {
    console.error('[SEED] Failed:', err);
    process.exit(1);
  });
}
