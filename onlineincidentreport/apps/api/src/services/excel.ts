import ExcelJS from 'exceljs';
import { htmlToPlainText } from '@uss/shared';

function formatDateDMY(rawDate: string | null | undefined): string {
  if (!rawDate) return '';
  const trimmed = String(rawDate).trim();
  if (/^\d{2}[\/\-]\d{2}[\/\-]\d{4}$/.test(trimmed)) {
    return trimmed.replace(/\-/g, '/');
  }
  const match = trimmed.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/);
  if (match) {
    const year = match[1];
    const month = match[2].padStart(2, '0');
    const day = match[3].padStart(2, '0');
    return `${day}/${month}/${year}`;
  }
  const d = new Date(trimmed);
  if (!isNaN(d.getTime())) {
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    return `${day}/${month}/${year}`;
  }
  return trimmed;
}

export class ExcelService {
  /**
   * Generate consolidated Excel workbook with Summary and Totals sheets
   */
  async generateDailyConsolidatedExcel(
    site: any,
    date: string,
    incidents: any[],
    settings?: any
  ): Promise<Buffer> {
    const companyName = settings?.company_name || settings?.companyName || 'ULTIMATE SECURITY SERVICES';
    const workbook = new ExcelJS.Workbook();
    workbook.creator = `${companyName} Dubai`;
    workbook.created = new Date();

    // Sheet 1: Incidents Summary
    const sheet = workbook.addWorksheet('Incidents Summary', {
      views: [{ state: 'frozen', ySplit: 2 }],
    });

    // Title banner
    sheet.mergeCells('A1:O1');
    const titleCell = sheet.getCell('A1');
    titleCell.value = `${companyName.toUpperCase()} - DAILY INCIDENT REPORT (${site.name} • ${formatDateDMY(date)})`;
    titleCell.font = { name: 'Arial', size: 14, bold: true, color: { argb: 'FFFFFFFF' } };
    titleCell.fill = {
      type: 'pattern',
      pattern: 'solid',
      fgColor: { argb: 'FF0F2042' }, // USS Navy
    };
    titleCell.alignment = { vertical: 'middle', horizontal: 'center' };
    sheet.getRow(1).height = 32;

    // Table Headers
    const headers = [
      'Ref No',
      'Site Code',
      'Site Name',
      'Date',
      'Time',
      'Shift',
      'Reporter',
      'Designation',
      'Causes',
      'Incident Types',
      'Who Notified',
      'Summary / Narrative',
      'Status',
      'Recommendation / Action',
      'Photos Count',
    ];

    const headerRow = sheet.addRow(headers);
    headerRow.height = 24;
    headerRow.eachCell((cell) => {
      cell.font = { name: 'Arial', size: 10, bold: true, color: { argb: 'FFFFFFFF' } };
      cell.fill = {
        type: 'pattern',
        pattern: 'solid',
        fgColor: { argb: 'FF1E40AF' }, // Branded Blue
      };
      cell.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };
      cell.border = {
        top: { style: 'thin', color: { argb: 'FFCBD5E1' } },
        left: { style: 'thin', color: { argb: 'FFCBD5E1' } },
        bottom: { style: 'thin', color: { argb: 'FFCBD5E1' } },
        right: { style: 'thin', color: { argb: 'FFCBD5E1' } },
      };
    });

    // Populate rows
    for (const inc of incidents) {
      const causes = Array.isArray(inc.cause_main || inc.causeMain)
        ? (inc.cause_main || inc.causeMain).join(', ')
        : '';
      const types = Array.isArray(inc.incident_types || inc.incidentTypes)
        ? (inc.incident_types || inc.incidentTypes).join(', ')
        : '';
      const notified = Array.isArray(inc.who_notified || inc.whoNotified)
        ? (inc.who_notified || inc.whoNotified).join(', ')
        : '';
      const photoCount = inc.photos ? inc.photos.length : 0;

      const row = sheet.addRow([
        inc.ref_number || inc.refNumber,
        inc.site_code || inc.siteCode || site.code,
        inc.site_name || inc.siteName || site.name,
        formatDateDMY(inc.incident_date || inc.incidentDate),
        inc.incident_time || inc.incidentTime,
        inc.shift,
        inc.reported_by_name || inc.reportedByName,
        inc.reported_by_designation || inc.reportedByDesignation,
        causes,
        types,
        notified,
        inc.summary_line || inc.summaryLine || htmlToPlainText(inc.description),
        inc.status || 'Submitted',
        inc.recommendation_action_taken || 'Pending Review',
        photoCount,
      ]);

      row.height = 22;
      row.eachCell((cell, colNumber) => {
        cell.font = { name: 'Arial', size: 9 };
        cell.border = {
          bottom: { style: 'thin', color: { argb: 'FFE2E8F0' } },
          right: { style: 'thin', color: { argb: 'FFE2E8F0' } },
        };
        if (colNumber === 1) {
          cell.font = { bold: true, color: { argb: 'FF0F2042' } };
        }
      });
    }

    // Auto-fit column widths
    sheet.columns = [
      { width: 26 }, // Ref No
      { width: 14 }, // Site Code
      { width: 26 }, // Site Name
      { width: 14 }, // Date
      { width: 10 }, // Time
      { width: 10 }, // Shift
      { width: 22 }, // Reporter
      { width: 18 }, // Designation
      { width: 20 }, // Causes
      { width: 26 }, // Incident Types
      { width: 24 }, // Notified
      { width: 45 }, // Narrative
      { width: 18 }, // Status
      { width: 32 }, // Recommendation
      { width: 14 }, // Photos
    ];

    // Sheet 2: Totals & Analytics Sheet
    const statsSheet = workbook.addWorksheet('Metrics & Totals');
    statsSheet.addRow(['METRIC', 'VALUE']).font = { bold: true };
    statsSheet.addRow(['Total Incidents', incidents.length]);

    const statusCounts: Record<string, number> = {};
    const typeCounts: Record<string, number> = {};

    incidents.forEach((i) => {
      const st = i.status || 'Submitted';
      statusCounts[st] = (statusCounts[st] || 0) + 1;
      const tArr = i.incident_types || i.incidentTypes || [];
      tArr.forEach((t: string) => {
        typeCounts[t] = (typeCounts[t] || 0) + 1;
      });
    });

    statsSheet.addRow(['']);
    statsSheet.addRow(['STATUS BREAKDOWN', 'COUNT']).font = { bold: true };
    Object.entries(statusCounts).forEach(([k, v]) => {
      statsSheet.addRow([k, v]);
    });

    statsSheet.addRow(['']);
    statsSheet.addRow(['INCIDENT TYPE BREAKDOWN', 'COUNT']).font = { bold: true };
    Object.entries(typeCounts).forEach(([k, v]) => {
      statsSheet.addRow([k, v]);
    });

    statsSheet.columns = [{ width: 30 }, { width: 15 }];

    const buffer = await workbook.xlsx.writeBuffer();
    return Buffer.from(buffer);
  }
}

export const excelService = new ExcelService();
