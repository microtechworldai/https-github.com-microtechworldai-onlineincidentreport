import {
  DEFAULT_CAUSES,
  DEFAULT_DISCIPLINARY,
  DEFAULT_INCIDENTS,
  DEFAULT_NOTIFIED,
  DEFAULT_SHIFTS,
  INCIDENT_STATUSES,
  SITE_TYPES,
} from '../constants/index.js';

export type CauseType = (typeof DEFAULT_CAUSES)[number] | string;
export type DisciplinaryType = (typeof DEFAULT_DISCIPLINARY)[number] | string;
export type IncidentType = (typeof DEFAULT_INCIDENTS)[number] | string;
export type NotifiedAuthority = (typeof DEFAULT_NOTIFIED)[number] | string;
export type ShiftType = (typeof DEFAULT_SHIFTS)[number] | string;
export type IncidentStatus = (typeof INCIDENT_STATUSES)[number];
export type SiteType = (typeof SITE_TYPES)[number];

export interface Site {
  id: string;
  name: string;
  code: string;
  type: SiteType;
  address: string;
  clientName: string;
  clientContact: string;
  latitude?: string | number | null;
  longitude?: string | number | null;
  isActive: boolean;
  activeQrToken?: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface GuardAccount {
  id: string;
  staffId: string;
  fullName: string;
  designation: string;
  username: string;
  isActive: boolean;
  assignedSites: string[]; // Site IDs
  createdAt: string;
  updatedAt: string;
}

export interface IncidentPhoto {
  id: string;
  incidentId: string;
  storageKey: string;
  url: string;
  thumbnailUrl?: string;
  originalFilename: string;
  fileSize: number;
  mimeType: string;
  caption?: string | null;
  capturedAt?: string | null;
  sortOrder: number;
  createdAt: string;
}

export interface IncidentNote {
  id: string;
  incidentId: string;
  adminId: string;
  adminName: string;
  note: string;
  createdAt: string;
}

export interface IncidentVersion {
  id: string;
  incidentId: string;
  adminId: string;
  adminName: string;
  changesSummary: string;
  snapshot: any;
  createdAt: string;
}

export interface Incident {
  id: string;
  refNumber: string;
  idempotencyKey?: string | null;
  siteId: string;
  siteName: string;
  siteCode: string;
  guardId?: string | null;
  reportedByName: string;
  reportedByDesignation: string;
  incidentDate: string; // YYYY-MM-DD in Asia/Dubai
  incidentTime: string; // HH:mm in Asia/Dubai
  serverSubmittedAt: string; // UTC ISO string
  shift: ShiftType;
  causeMain: CauseType[];
  disciplinaryReasons: DisciplinaryType[];
  disciplinaryOtherText?: string | null;
  incidentTypes: IncidentType[];
  incidentOtherText?: string | null;
  summaryLine: string;
  description: string;
  whoNotified: NotifiedAuthority[];
  notifiedOtherText?: string | null;

  // Management sign-off sections (4.7)
  siteSupervisorName?: string | null;
  siteSupervisorId?: string | null;
  siteSupervisorSignatureUrl?: string | null;
  siteSupervisorDate?: string | null;

  recommendationActionTaken?: string | null;

  operationsManagerName?: string | null;
  operationsManagerSignatureUrl?: string | null;
  operationsManagerDate?: string | null;

  clientManagerActionTaken?: string | null;

  clientManagerName?: string | null;
  clientManagerSignatureUrl?: string | null;
  clientManagerDate?: string | null;

  status: IncidentStatus;
  submittedToClientAt?: string | null;
  photos: IncidentPhoto[];
  notes?: IncidentNote[];
  versions?: IncidentVersion[];

  deletedAt?: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface SystemSettings {
  id: string;
  companyName: string;
  tagline: string;
  addressLine: string;
  phone: string;
  email: string;
  website?: string | null;
  reportTitle?: string;
  primaryColor?: string;
  detailsVersionMode?: 'current' | 'submission_time';
  logoUrl?: string | null;
  timezone: string;
  shiftOptions: string[];
  causeOptions: string[];
  disciplinaryOptions: string[];
  incidentOptions: string[];
  notifiedOptions: string[];
  updatedAt: string;
}

export interface AuditLog {
  id: string;
  actorType: 'SUPER_ADMIN' | 'GUARD' | 'SYSTEM';
  actorId?: string | null;
  actorName?: string | null;
  action: string;
  targetType: string;
  targetId?: string | null;
  details?: Record<string, any>;
  ipAddress?: string | null;
  userAgent?: string | null;
  createdAt: string;
}
