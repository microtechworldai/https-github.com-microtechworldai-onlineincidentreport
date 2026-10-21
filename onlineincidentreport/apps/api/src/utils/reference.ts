/**
 * Formats a unique incident reference code: INC-<SITECODE>-DD/MM/YYYY-0001
 */
export function generateIncidentReference(
  siteCode: string,
  incidentDate: string, // YYYY-MM-DD
  sequenceNumber: number
): string {
  const cleanCode = (siteCode || 'GEN')
    .toUpperCase()
    .replace(/[^A-Z0-9]/g, '')
    .slice(0, 8);
  
  let d = '01', m = '01', y = '2026';
  const match = String(incidentDate).match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/);
  if (match) {
    y = match[1];
    m = match[2].padStart(2, '0');
    d = match[3].padStart(2, '0');
  } else {
    const dt = new Date(incidentDate);
    if (!isNaN(dt.getTime())) {
      d = String(dt.getDate()).padStart(2, '0');
      m = String(dt.getMonth() + 1).padStart(2, '0');
      y = String(dt.getFullYear());
    }
  }
  const dateStr = `${d}/${m}/${y}`;
  const seqStr = String(sequenceNumber).padStart(4, '0');
  return `INC-${cleanCode}-${dateStr}-${seqStr}`;
}

/**
 * Normalizes any reference number (e.g. INC-SOAM-20261017-0005) to DD/MM/YYYY format:
 * INC-SOAM-17/10/2026-0005
 */
export function formatRefWithDMY(ref: string | null | undefined): string {
  if (!ref) return '';
  return String(ref).replace(/(INC-[A-Za-z0-9_-]+-)(\d{4})(\d{2})(\d{2})(-\d+)/, '$1$4/$3/$2$5');
}
