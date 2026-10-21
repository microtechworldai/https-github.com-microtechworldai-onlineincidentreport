import { z } from 'zod';
import { INCIDENT_STATUSES, INCIDENT_PRIORITIES, SITE_TYPES } from '../constants/index.js';

export const AdminLoginSchema = z.object({
  username: z.string().min(3, 'Username must be at least 3 characters'),
  password: z.string().min(6, 'Password must be at least 6 characters'),
});

export const AdminChangePasswordSchema = z.object({
  currentPassword: z.string().min(1, 'Current password is required'),
  newPassword: z
    .string()
    .min(8, 'New password must be at least 8 characters')
    .regex(/[A-Z]/, 'Password must contain at least one uppercase letter')
    .regex(/[a-z]/, 'Password must contain at least one lowercase letter')
    .regex(/[0-9]/, 'Password must contain at least one number'),
});

export const GuardLoginSchema = z.object({
  siteToken: z.string().min(10, 'Valid site token is required'),
  username: z.string().min(2, 'Username is required'),
  password: z.string().min(4, 'Password is required'),
});

export const IncidentPhotoUploadSchema = z.object({
  originalFilename: z.string(),
  caption: z.string().max(200).optional().nullable(),
  fileData: z.string(), // Base64 data or uploaded multipart
  mimeType: z.string(),
});

export const GuardIncidentSubmitSchema = z.object({
  idempotencyKey: z.string().min(8, 'Idempotency key required'),
  siteId: z.string().uuid('Valid site is required'),
  incidentDate: z
    .string()
    .regex(/^\d{4}-\d{2}-\d{2}$/, 'Valid incident date (YYYY-MM-DD) required'),
  incidentTime: z
    .string()
    .regex(/^\d{2}:\d{2}$/, 'Valid incident time (HH:mm) required'),
  shift: z.string().min(1, 'Shift is required'),
  causeMain: z.array(z.string()).default(['Incident']),
  disciplinaryReasons: z.array(z.string()).default([]),
  disciplinaryOtherText: z.string().max(300).optional().nullable(),
  incidentTypes: z.array(z.string()).default([]),
  incidentOtherText: z.string().max(300).optional().nullable(),
  summaryLine: z
    .string()
    .min(5, 'Summary line must explain What, Where, When, Who, Why, How')
    .max(500),
  description: z
    .string()
    .min(20, 'Description of incident is required (detailed narrative)'),
  priority: z.enum(INCIDENT_PRIORITIES).default('Medium'),
  whoNotified: z.array(z.string()).default([]),
  notifiedOtherText: z.string().max(300).optional().nullable(),
  siteSupervisorName: z.string().max(150).optional().nullable(),
  siteSupervisorId: z.string().max(50).optional().nullable(),
  siteSupervisorDate: z.string().optional().nullable(),
  siteSupervisorSignature: z.string().optional().nullable(),
  photos: z.array(
    z.object({
      originalFilename: z.string(),
      mimeType: z.string(),
      dataBase64: z.string().optional(),
      caption: z.string().max(250).optional().nullable(),
      storageKey: z.string().optional(),
    })
  ).max(10, 'Maximum 10 photos permitted').default([]),
});

export const AdminIncidentUpdateSchema = z.object({
  siteId: z.string().uuid().optional(),
  incidentDate: z.string().regex(/^\d{4}-\d{2}-\d{2}$/).optional(),
  incidentTime: z.string().regex(/^\d{2}:\d{2}$/).optional(),
  shift: z.string().optional(),
  priority: z.enum(INCIDENT_PRIORITIES).optional(),
  causeMain: z.array(z.string()).optional(),
  disciplinaryReasons: z.array(z.string()).optional(),
  disciplinaryOtherText: z.string().optional().nullable(),
  incidentTypes: z.array(z.string()).optional(),
  incidentOtherText: z.string().optional().nullable(),
  summaryLine: z.string().optional(),
  description: z.string().optional(),
  whoNotified: z.array(z.string()).optional(),
  notifiedOtherText: z.string().optional().nullable(),

  // Sign-off section
  siteSupervisorName: z.string().optional().nullable(),
  site_supervisor_name: z.string().optional().nullable(),
  siteSupervisorId: z.string().optional().nullable(),
  site_supervisor_id: z.string().optional().nullable(),
  siteSupervisorDate: z.string().optional().nullable(),
  site_supervisor_date: z.string().optional().nullable(),
  siteSupervisorSignature: z.string().optional().nullable(),
  site_supervisor_signature: z.string().optional().nullable(),
  site_supervisor_signature_url: z.string().optional().nullable(),
  recommendationActionTaken: z.string().optional().nullable(),
  recommendation_action_taken: z.string().optional().nullable(),
  operationsManagerName: z.string().optional().nullable(),
  operations_manager_name: z.string().optional().nullable(),
  operationsManagerDate: z.string().optional().nullable(),
  operations_manager_date: z.string().optional().nullable(),
  operationsManagerSignature: z.string().optional().nullable(),
  operations_manager_signature: z.string().optional().nullable(),
  operations_manager_signature_url: z.string().optional().nullable(),
  clientManagerActionTaken: z.string().optional().nullable(),
  client_manager_action_taken: z.string().optional().nullable(),
  clientManagerName: z.string().optional().nullable(),
  client_manager_name: z.string().optional().nullable(),
  clientManagerDate: z.string().optional().nullable(),
  client_manager_date: z.string().optional().nullable(),
  clientManagerSignature: z.string().optional().nullable(),
  client_manager_signature: z.string().optional().nullable(),
  client_manager_signature_url: z.string().optional().nullable(),

  summary_line: z.string().optional(),
  who_notified: z.array(z.string()).optional(),
  notified_other_text: z.string().optional().nullable(),
  incident_date: z.string().optional(),
  incident_time: z.string().optional(),

  status: z.enum(INCIDENT_STATUSES).optional(),
}).passthrough();

export const SiteCreateSchema = z.object({
  name: z.string().min(2, 'Site name is required'),
  code: z
    .string()
    .min(2, 'Site code is required (e.g. DXB-TWR)')
    .regex(/^[A-Za-z0-9_-]+$/, 'Letters, numbers, dash and underscore only'),
  type: z.enum(SITE_TYPES),
  address: z.string().min(3, 'Address is required'),
  clientName: z.string().min(2, 'Client name is required'),
  clientContact: z.string().optional().default(''),
  clientEmail: z.string().optional().default(''),
  clientMobile: z.string().optional().default(''),
  latitude: z.union([z.string(), z.number()]).optional().nullable(),
  longitude: z.union([z.string(), z.number()]).optional().nullable(),
});

export const SiteUpdateSchema = z.object({
  name: z.string().min(2).optional(),
  type: z.enum(SITE_TYPES).optional(),
  address: z.string().optional(),
  clientName: z.string().optional(),
  clientContact: z.string().optional(),
  clientEmail: z.string().optional(),
  clientMobile: z.string().optional(),
  latitude: z.union([z.string(), z.number()]).optional().nullable(),
  longitude: z.union([z.string(), z.number()]).optional().nullable(),
  isActive: z.boolean().optional(),
});

export const GuardAccountCreateSchema = z.object({
  staffId: z.string().min(2, 'Staff ID is required'),
  fullName: z.string().min(2, 'Full name is required'),
  designation: z.string().min(2, 'Designation is required'),
  username: z
    .string()
    .min(3, 'Username must be at least 3 characters')
    .regex(/^[a-zA-Z0-9_.-]+$/, 'Alphanumeric and .-_ only'),
  password: z.string().min(6, 'Password must be at least 6 characters'),
  assignedSites: z.array(z.string()).default([]),
});

export const GuardAccountUpdateSchema = z.object({
  staffId: z.string().optional(),
  username: z.string().optional(),
  fullName: z.string().min(2).optional(),
  designation: z.string().min(2).optional(),
  isActive: z.boolean().optional(),
  assignedSites: z.array(z.string()).optional(),
});

export const SystemSettingsUpdateSchema = z.object({
  companyName: z.string().min(2).optional(),
  tagline: z.string().optional(),
  addressLine: z.string().optional(),
  phone: z.string().optional(),
  email: z.string().email().optional(),
  website: z.string().optional().nullable(),
  reportTitle: z.string().optional(),
  primaryColor: z.string().optional(),
  detailsVersionMode: z.enum(['current', 'submission_time']).optional(),
  logoUrl: z.string().optional().nullable(),
  timezone: z.string().optional(),
  shiftOptions: z.array(z.string()).optional(),
  causeOptions: z.array(z.string()).optional(),
  disciplinaryOptions: z.array(z.string()).optional(),
  incidentOptions: z.array(z.string()).optional(),
  notifiedOptions: z.array(z.string()).optional(),
  priorityOptions: z.array(z.string()).optional(),
});
