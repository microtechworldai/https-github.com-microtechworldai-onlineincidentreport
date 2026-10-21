export const DEFAULT_CAUSES = ['Incident', 'Disciplinary', 'Others'] as const;

export const DEFAULT_DISCIPLINARY = [
  'Sleeping',
  'Poor Grooming',
  'Argument',
  'Missing',
  'Misbehavior',
  'Uniform',
  'Late Reporting',
  'Mobile',
  'Others',
] as const;

export const DEFAULT_INCIDENTS = [
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
] as const;

export const DEFAULT_NOTIFIED = [
  'Police',
  'Ambulance',
  'Civil Defense',
  'Client Management',
  'USS Management',
  'Others',
] as const;

export const DEFAULT_SHIFTS = ['Day', 'Night'] as const;

export const INCIDENT_STATUSES = [
  'Submitted',
  'Under Review',
  'Follow-up Required',
  'Resolved',
  'Submitted to Client',
] as const;

export const INCIDENT_PRIORITIES = [
  'Critical',
  'High',
  'Medium',
  'Low',
] as const;

export type IncidentPriority = (typeof INCIDENT_PRIORITIES)[number];

export const SITE_TYPES = ['Commercial', 'Residential', 'Hospital'] as const;

export const DEFAULT_COMPANY_SETTINGS = {
  companyName: 'ULTIMATE SECURITY SERVICES',
  tagline: 'Total Security Solutions',
  addressLine: 'P O Box No: 61591, Al Maktoum Street | Dubai | UAE',
  phone: '+971 4 251 0050',
  email: 'ops@ultimatesecurity.ae',
  timezone: 'Asia/Dubai',
};
