import PDFDocument from 'pdfkit';
import fs from 'fs';
import path from 'path';
import {
  DEFAULT_CAUSES,
  DEFAULT_DISCIPLINARY,
  DEFAULT_INCIDENTS,
  DEFAULT_NOTIFIED,
} from '@uss/shared';
import { storage } from './storage.js';

interface SettingsData {
  companyName: string;
  tagline?: string;
  addressLine: string;
  phone: string;
  email: string;
  website?: string | null;
  reportTitle?: string;
  primaryColor?: string;
  logoUrl?: string | null;
  notified_options?: string[] | string;
  notifiedOptions?: string[] | string;
}

interface RichSpan {
  text: string;
  font: string;
  fontSize: number;
  color: string;
  underline: boolean;
  lineGap: number;
}

interface RichLine {
  spans: RichSpan[];
}

export class PdfService {
  /**
   * Render single incident report PDF replicating the official USS layout
   */
  async generateIncidentPdf(
    incident: any,
    settings: SettingsData
  ): Promise<Buffer> {
    return new Promise(async (resolve, reject) => {
      try {
        const doc = new PDFDocument({
          size: 'A4',
          margin: 30, // crisp balanced margin to perfectly fit A4
          autoFirstPage: true,
          bufferPages: true,
        });

        const chunks: Buffer[] = [];
        doc.on('data', (chunk) => chunks.push(chunk));
        doc.on('end', () => resolve(Buffer.concat(chunks)));
        doc.on('error', (err) => reject(err));

        const overflow = await this.renderIncidentFormPage(doc, incident, settings);

        // Continuation page(s) if description exceeds 15 lines
        if (overflow && overflow.length > 0) {
          await this.renderContinuationPages(doc, incident, settings, overflow);
        }

        // Appended photographs section if any photos exist
        if (incident.photos && incident.photos.length > 0) {
          await this.renderPhotoPages(doc, incident);
        }

        // Apply headers/footers with page numbering (Page X of Y)
        // Set bottom margin to 0 temporarily to prevent PDFKit from triggering auto page break past margin
        const pages = doc.bufferedPageRange();
        for (let i = 0; i < pages.count; i++) {
          doc.switchToPage(i);
          const oldBottomMargin = doc.page.margins.bottom;
          doc.page.margins.bottom = 0;
          // Footer
          doc
            .fontSize(8.5)
            .font('Helvetica-Bold')
            .fillColor('#475569')
            .text(
              `Ultimate Security Services (USS) • Report Ref: ${this.formatRefWithDMY(incident.ref_number || incident.refNumber)}`,
              30,
              doc.page.height - 20,
              { width: 360, align: 'left', lineBreak: false }
            );
          doc
            .fontSize(8.5)
            .font('Helvetica-Bold')
            .fillColor('#475569')
            .text(
              `Page ${i + 1} of ${pages.count}`,
              doc.page.width - 150,
              doc.page.height - 20,
              { width: 120, align: 'right', lineBreak: false }
            );
          doc.page.margins.bottom = oldBottomMargin;
        }

        doc.end();
      } catch (err) {
        reject(err);
      }
    });
  }

  /**
   * Render daily consolidated report PDF per site
   */
  async generateDailyConsolidatedPdf(
    site: any,
    date: string,
    incidents: any[],
    settings: SettingsData
  ): Promise<Buffer> {
    return new Promise(async (resolve, reject) => {
      try {
        const doc = new PDFDocument({
          size: 'A4',
          margin: 30,
          autoFirstPage: true,
          bufferPages: true,
        });

        const chunks: Buffer[] = [];
        doc.on('data', (chunk) => chunks.push(chunk));
        doc.on('end', () => resolve(Buffer.concat(chunks)));
        doc.on('error', (err) => reject(err));

        // Cover Page
        await this.renderDailyCoverPage(doc, site, date, incidents, settings);

        // Individual Incidents
        for (const inc of incidents) {
          doc.addPage();
          const overflow = await this.renderIncidentFormPage(doc, inc, settings);
          if (overflow && overflow.length > 0) {
            await this.renderContinuationPages(doc, inc, settings, overflow);
          }
          if (inc.photos && inc.photos.length > 0) {
            await this.renderPhotoPages(doc, inc);
          }
        }

        // Apply page numbering
        const pages = doc.bufferedPageRange();
        for (let i = 0; i < pages.count; i++) {
          doc.switchToPage(i);
          const oldBottomMargin = doc.page.margins.bottom;
          doc.page.margins.bottom = 0;
          doc
            .fontSize(8.5)
            .font('Helvetica-Bold')
            .fillColor('#64748B')
            .text(
              `Ultimate Security Services (USS) • Daily Consolidated Report • ${site.name} • ${this.formatDateDMY(date)}`,
              30,
              doc.page.height - 20,
              { width: 380, align: 'left', lineBreak: false }
            );
          doc
            .fontSize(8.5)
            .font('Helvetica-Bold')
            .fillColor('#64748B')
            .text(
              `Page ${i + 1} of ${pages.count}`,
              doc.page.width - 150,
              doc.page.height - 20,
              { width: 120, align: 'right', lineBreak: false }
            );
          doc.page.margins.bottom = oldBottomMargin;
        }

        doc.end();
      } catch (err) {
        reject(err);
      }
    });
  }

  async getLogoBuffer(settings: SettingsData): Promise<Buffer | null> {
    try {
      if (settings.logoUrl) {
        if (settings.logoUrl.startsWith('/uploads/')) {
          const storageKey = settings.logoUrl.replace(/^\/uploads\//, '');
          const buf = await storage.getFileBuffer(storageKey);
          if (buf) return buf;
        } else if (settings.logoUrl.startsWith('data:image')) {
          const base64Data = settings.logoUrl.replace(/^data:image\/\w+;base64,/, '');
          return Buffer.from(base64Data, 'base64');
        }
      }

      // Check standard locations
      const possiblePaths = [
        path.resolve(process.cwd(), './uploads/settings/uss-logo.png'),
        path.resolve(process.cwd(), '../web/public/assets/uss-logo.png'),
        path.resolve(process.cwd(), './apps/web/public/assets/uss-logo.png'),
        path.resolve(process.cwd(), './apps/api/uploads/settings/uss-logo.png'),
      ];

      for (const p of possiblePaths) {
        if (fs.existsSync(p)) {
          return fs.readFileSync(p);
        }
      }
    } catch (e: any) {
      console.warn('[PDF] Failed to load logo for PDF rendering:', e.message);
    }
    return null;
  }

  /**
   * Format any date string to DD/MM/YYYY
   */
  formatDateDMY(rawDate: string | null | undefined): string {
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

  /**
   * Format any date string to official format: DD-MMM-YYYY (e.g. 17-Oct-2026)
   */
  formatDateOfficial(rawDate: string | null | undefined): string {
    if (!rawDate) return '';
    const trimmed = String(rawDate).trim();
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const matchYMD = trimmed.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/);
    if (matchYMD) {
      const year = matchYMD[1];
      const monthIdx = parseInt(matchYMD[2], 10) - 1;
      const day = matchYMD[3].padStart(2, '0');
      return `${day}-${months[monthIdx] || 'Jan'}-${year}`;
    }
    const matchDMY = trimmed.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/);
    if (matchDMY) {
      const day = matchDMY[1].padStart(2, '0');
      const monthIdx = parseInt(matchDMY[2], 10) - 1;
      const year = matchDMY[3];
      return `${day}-${months[monthIdx] || 'Jan'}-${year}`;
    }
    const d = new Date(trimmed);
    if (!isNaN(d.getTime())) {
      const day = String(d.getDate()).padStart(2, '0');
      const month = months[d.getMonth()] || 'Jan';
      const year = d.getFullYear();
      return `${day}-${month}-${year}`;
    }
    return trimmed;
  }

  /**
   * Format any incident reference code to use DD/MM/YYYY format:
   * e.g. INC-SOAM-20261017-0005 -> INC-SOAM-17/10/2026-0005
   */
  formatRefWithDMY(rawRef: string | null | undefined): string {
    if (!rawRef) return '';
    return String(rawRef).replace(/(INC-[A-Za-z0-9_-]+-)(\d{4})(\d{2})(\d{2})(-\d+)/, '$1$4/$3/$2$5');
  }

  /**
   * Resolve signature buffer from base64, storage key, file path, URL, or buffer
   */
  private async getSignatureBuffer(sigValue: any): Promise<Buffer | null> {
    if (!sigValue) return null;
    try {
      // If object with nested url/data/base64
      if (typeof sigValue === 'object' && !Buffer.isBuffer(sigValue)) {
        return this.getSignatureBuffer(sigValue.url || sigValue.dataBase64 || sigValue.data || sigValue.base64 || sigValue.storage_key);
      }
      if (Buffer.isBuffer(sigValue)) {
        return sigValue;
      }
      if (typeof sigValue === 'string') {
        const trimmed = sigValue.trim();
        // Ignore plain non-image placeholder strings
        if (trimmed === 'Signed' || trimmed === 'signed' || trimmed === 'Verified' || trimmed.startsWith('____')) {
          return null;
        }
        // Data URI (image/jpeg, image/png, image/webp, image/svg+xml, etc.)
        if (trimmed.startsWith('data:image')) {
          const rawBase64 = trimmed.includes(',') ? trimmed.slice(trimmed.indexOf(',') + 1) : trimmed;
          const cleanBase64 = rawBase64.replace(/\s+/g, '');
          return Buffer.from(cleanBase64, 'base64');
        }
        // Direct local file path
        if (fs.existsSync(trimmed)) {
          return fs.readFileSync(trimmed);
        }
        // Remote / local upload URL or storage key
        let cleanKey = trimmed;
        if (cleanKey.startsWith('http://') || cleanKey.startsWith('https://')) {
          try {
            cleanKey = new URL(cleanKey).pathname;
          } catch (_) {}
        }
        cleanKey = cleanKey.replace(/^\/?uploads\//, '').replace(/^\/+/, '');
        if (cleanKey.length > 0) {
          const buf = await storage.getFileBuffer(cleanKey);
          if (buf) return buf;
        }
        // Raw base64 string without data prefix
        if (trimmed.length > 100 && /^[A-Za-z0-9+/=\s]+$/.test(trimmed.slice(0, 50))) {
          return Buffer.from(trimmed.replace(/\s+/g, ''), 'base64');
        }
      }
    } catch (err: any) {
      console.warn('[PDF] Failed to load signature buffer:', err.message);
    }
    return null;
  }

  private async renderDailyCoverPage(
    doc: PDFKit.PDFDocument,
    site: any,
    date: string,
    incidents: any[],
    settings: SettingsData
  ) {
    const pageWidth = doc.page.width;
    const margin = 36;
    const contentWidth = pageWidth - margin * 2;

    // Header block (Clean professional white background, no blue fill)
    doc.rect(margin, margin, contentWidth, 62).strokeColor('#000000').lineWidth(1).stroke();

    const logoBuf = await this.getLogoBuffer(settings);

    if (logoBuf) {
      try {
        doc.image(logoBuf, margin + 8, margin + 5, {
          fit: [52, 52],
          align: 'center',
          valign: 'center',
        });
      } catch (err) {
        console.warn('[PDF] Failed to draw cover logo:', err);
      }
    }
    doc
      .fontSize(15)
      .fillColor('#000000')
      .font('Helvetica-Bold')
      .text(settings.companyName || 'ULTIMATE SECURITY SERVICES', margin + 68, margin + 14, {
        lineBreak: false,
      });
    doc
      .fontSize(10)
      .font('Helvetica-Bold')
      .fillColor('#334155')
      .text('DAILY SECURITY INCIDENT CONSOLIDATED REPORT', margin + 68, margin + 35, {
        lineBreak: false,
      });

    let y = margin + 74;

    // Report Summary Card
    doc.rect(margin, y, contentWidth, 110).strokeColor('#000000').lineWidth(0.75).stroke();
    doc.rect(margin, y, contentWidth, 24).fillAndStroke('#D1D5DB', '#000000');
    doc
      .fontSize(10.5)
      .font('Helvetica-Bold')
      .fillColor('#000000')
      .text('CLIENT SUBMISSION OVERVIEW', margin + 10, y + 6);

    y += 34;
    doc
      .fontSize(9.5)
      .font('Helvetica-Bold')
      .fillColor('#000000')
      .text('SITE NAME:', margin + 10, y);
    doc
      .font('Helvetica')
      .text(`${site.name} (${site.code})`, margin + 120, y);

    y += 18;
    doc
      .font('Helvetica-Bold')
      .text('CLIENT:', margin + 10, y);
    doc
      .font('Helvetica')
      .text(`${site.client_name || site.clientName} (Contact: ${site.client_contact || site.clientContact})`, margin + 120, y);

    y += 18;
    doc
      .font('Helvetica-Bold')
      .text('REPORT DATE:', margin + 10, y);
    doc
      .font('Helvetica')
      .text(`${this.formatDateDMY(date)} (Asia/Dubai)`, margin + 120, y);

    y += 18;
    doc
      .font('Helvetica-Bold')
      .text('TOTAL INCIDENTS:', margin + 10, y);
    doc
      .font('Helvetica-Bold')
      .fillColor('#B91C1C')
      .text(`${incidents.length} Incident(s) Recorded`, margin + 120, y);

    y += 36;

    // Summary Table
    doc
      .fontSize(11)
      .font('Helvetica-Bold')
      .fillColor('#000000')
      .text('INCIDENTS LOGGED FOR THIS DATE', margin, y);
    y += 16;

    // Table header (Clean grey fill, black text - no blue background)
    doc.rect(margin, y, contentWidth, 22).fillAndStroke('#D1D5DB', '#000000');
    doc.fontSize(8.5).font('Helvetica-Bold').fillColor('#000000');
    doc.text('REF NUMBER', margin + 8, y + 6, { width: 125, lineBreak: false });
    doc.text('TIME/SHIFT', margin + 135, y + 6, { width: 70, lineBreak: false });
    doc.text('CAUSE & TYPE', margin + 210, y + 6, { width: 125, lineBreak: false });
    doc.text('SUMMARY OF INCIDENT', margin + 340, y + 6, { width: 120, lineBreak: false });
    doc.text('STATUS', margin + 465, y + 6, { width: 50, align: 'right', lineBreak: false });
    y += 22;

    // Table rows
    doc.font('Helvetica').fontSize(8.5);
    for (const inc of incidents) {
      doc.rect(margin, y, contentWidth, 30).strokeColor('#CBD5E1').lineWidth(0.75).stroke();
      doc.fillColor('#000000');

      const ref = inc.ref_number || inc.refNumber;
      const time = `${inc.incident_time || inc.incidentTime} (${inc.shift})`;
      const causes = (inc.cause_main || inc.causeMain || []).join(', ');
      const types = (inc.incident_types || inc.incidentTypes || []).join(', ');
      const summary = (inc.summary_line || inc.summaryLine || '').slice(0, 75);
      const status = inc.status || 'Submitted';

      doc.font('Helvetica-Bold').text(ref, margin + 8, y + 8, { width: 125, lineBreak: false });
      doc.font('Helvetica').text(time, margin + 135, y + 8, { width: 70, lineBreak: false });
      doc.text(`${causes} - ${types}`.slice(0, 35), margin + 210, y + 8, { width: 125, lineBreak: false });
      doc.text(summary, margin + 340, y + 8, { width: 120, lineBreak: false });
      doc.font('Helvetica-Bold').text(status, margin + 465, y + 8, { width: 50, align: 'right', lineBreak: false });

      y += 30;
    }
  }

  /**
   * Render the exact USS Incident Report form (A4 Single-Page Form)
   */
  private async renderIncidentFormPage(
    doc: PDFKit.PDFDocument,
    incident: any,
    settings: SettingsData
  ) {
    const pageWidth = doc.page.width;
    const pageHeight = doc.page.height;
    const margin = 36;
    const contentWidth = pageWidth - margin * 2; // 523 pt
    let y = margin;

    // =========================================================================
    // 1. HEADER (No outer border, matching official PDF)
    // =========================================================================
    const headerHeight = 74; // Generous height with 1 full line space before next line
    const logoBuf = await this.getLogoBuffer(settings);

    if (logoBuf) {
      try {
        doc.image(logoBuf, margin, y, {
          fit: [68, 68],
          valign: 'center',
        });
      } catch (err) {
        console.warn('[PDF] Failed to draw form logo:', err);
      }
    }

    // Centered Title "INCIDENT REPORT" (Perfect center of the page)
    const centerLeft = 0;
    const centerWidth = pageWidth;
    doc
      .fontSize(22)
      .font('Helvetica-Bold')
      .fillColor('#0F2042')
      .text(settings.reportTitle || 'INCIDENT REPORT', centerLeft, y + 16, {
        width: centerWidth,
        align: 'center',
        lineBreak: false,
      });

    // Decorative underline beneath title
    const titleWidth = doc.widthOfString(settings.reportTitle || 'INCIDENT REPORT');
    const titleUnderlineX = (pageWidth - titleWidth) / 2;
    doc
      .rect(titleUnderlineX - 4, y + 43, titleWidth + 8, 2.5)
      .fill('#0F2042');

    y += headerHeight;

    const metaCol1LabelX = margin + 4;
    const metaCol1ValX = margin + 115;

    // Right side of the page for Date, Time, Shift (Flush with table right boundary)
    const metaCol2LabelWidth = 46;
    const metaCol2ValWidth = 95;
    const metaCol2RightMargin = 4;
    const metaCol2ValX = margin + contentWidth - metaCol2ValWidth - metaCol2RightMargin;
    const metaCol2LabelX = metaCol2ValX - metaCol2LabelWidth - 6;
    const metaCol1ValWidth = metaCol2LabelX - metaCol1ValX - 10;
    const metaRowHeight = 18;

    // Row 1: Site Name | Date
    doc.fontSize(10).font('Helvetica-Bold').fillColor('#000000');
    doc.text('Site Name :', metaCol1LabelX, y, { lineBreak: false });
    doc.fontSize(10.5).font('Helvetica-Bold').text(incident.site_name || incident.siteName || '', metaCol1ValX, y, { width: metaCol1ValWidth, lineBreak: false });

    doc.fontSize(10).font('Helvetica-Bold').text('Date :', metaCol2LabelX, y, { lineBreak: false });
    doc.fontSize(10.5).font('Helvetica-Bold').text(this.formatDateOfficial(incident.incident_date || incident.incidentDate), metaCol2ValX, y, { width: metaCol2ValWidth, lineBreak: false });

    // Row 2: Person Reporting | Time
    y += metaRowHeight;
    doc.fontSize(10).font('Helvetica-Bold').text('Person Reporting :', metaCol1LabelX, y, { lineBreak: false });
    doc.fontSize(10.5).font('Helvetica-Bold').text(incident.reported_by_name || incident.reportedByName || '', metaCol1ValX, y, { width: metaCol1ValWidth, lineBreak: false });

    doc.fontSize(10).font('Helvetica-Bold').text('Time :', metaCol2LabelX, y, { lineBreak: false });
    doc.fontSize(10.5).font('Helvetica-Bold').text(incident.incident_time || incident.incidentTime || '', metaCol2ValX, y, { width: metaCol2ValWidth, lineBreak: false });

    // Row 3: Designation | Shift
    y += metaRowHeight;
    doc.fontSize(10).font('Helvetica-Bold').text('Designation :', metaCol1LabelX, y, { lineBreak: false });
    doc.fontSize(10.5).font('Helvetica-Bold').text(incident.reported_by_designation || incident.reportedByDesignation || '', metaCol1ValX, y, { width: metaCol1ValWidth, lineBreak: false });

    doc.fontSize(10).font('Helvetica-Bold').text('Shift :', metaCol2LabelX, y, { lineBreak: false });
    doc.fontSize(10.5).font('Helvetica-Bold').text(incident.shift || 'Day', metaCol2ValX, y, { width: metaCol2ValWidth, lineBreak: false });

    y += metaRowHeight + 7;

    // =========================================================================
    // 3. MAIN CLASSIFICATION & REPORT TABLE (Official Outer Border)
    // =========================================================================
    const sideColWidth = 78;
    const mainColWidth = contentWidth - sideColWidth; // 445.28 pt

    // 3.1 Priority Row (Top row: Navy Blue #002776 fill with bold white text)
    const priorityBarHeight = 22;
    doc.rect(margin, y, contentWidth, priorityBarHeight).fillColor('#002776').strokeColor('#000000').lineWidth(1).fillAndStroke();
    doc.moveTo(margin + sideColWidth, y).lineTo(margin + sideColWidth, y + priorityBarHeight).strokeColor('#000000').lineWidth(0.85).stroke();

    const priorityList = ['Critical', 'High', 'Medium', 'Low'];
    const curPriority = incident.priority || 'Medium';
    const priColWidth = mainColWidth / 4;

    for (let i = 0; i < priorityList.length; i++) {
      const p = priorityList[i];
      const isChecked = String(curPriority).toLowerCase() === p.toLowerCase();
      this.drawCheckbox(doc, margin + sideColWidth + 14 + i * priColWidth, y + 5.5, p, isChecked, 10.5, 9.5, '#FFFFFF');
    }
    y += priorityBarHeight;

    // 3.2 Disciplinary Row (Organized uniform 5-column grid)
    const discHeight = 42;
    doc.rect(margin, y, contentWidth, discHeight).strokeColor('#000000').lineWidth(0.85).stroke();
    doc.moveTo(margin + sideColWidth, y).lineTo(margin + sideColWidth, y + discHeight).strokeColor('#000000').lineWidth(0.85).stroke();

    // Left cell label: Disciplinary
    doc.fontSize(10.5).font('Helvetica-Bold').fillColor('#000000').text('Disciplinary', margin + 6, y + 15, { width: sideColWidth - 12, lineBreak: false });

    // Disciplinary checkboxes aligned to a clean 5-column grid
    const discSelected = incident.disciplinary_reasons || incident.disciplinaryReasons || [];
    const discRow1 = ['Sleeping', 'Poor Grooming', 'Argument', 'Missing', 'Misbehavior'];
    const discRow2 = ['Uniform', 'Late Reporting', 'Mobile', 'Others'];

    const discColWidth = mainColWidth / 5;
    for (let i = 0; i < discRow1.length; i++) {
      const name = discRow1[i];
      const isChecked = discSelected.includes(name);
      this.drawCheckbox(doc, margin + sideColWidth + 6 + i * discColWidth, y + 6, name, isChecked, 10, 8.5);
    }

    for (let i = 0; i < discRow2.length; i++) {
      const name = discRow2[i];
      const isChecked = discSelected.includes(name) || (name === 'Others' && !!incident.disciplinary_other_text);
      this.drawCheckbox(doc, margin + sideColWidth + 6 + i * discColWidth, y + 24, name, isChecked, 10, 8.5);
    }

    // Others write-in field / line spanning through column 4
    const othersLineStartX = margin + sideColWidth + 6 + 3 * discColWidth + 48;
    const othersLineEndX = margin + sideColWidth + mainColWidth - 8;
    if (incident.disciplinary_other_text) {
      doc.fontSize(8).font('Helvetica-Bold').fillColor('#000000').text(incident.disciplinary_other_text, othersLineStartX, y + 25, { width: othersLineEndX - othersLineStartX, lineBreak: false });
    } else {
      doc.moveTo(othersLineStartX, y + 33).lineTo(othersLineEndX, y + 33).strokeColor('#64748B').lineWidth(0.5).stroke();
    }
    y += discHeight;

    // 3.3 Incident Row
    const incHeight = 62;
    doc.rect(margin, y, contentWidth, incHeight).strokeColor('#000000').lineWidth(0.85).stroke();
    doc.moveTo(margin + sideColWidth, y).lineTo(margin + sideColWidth, y + incHeight).strokeColor('#000000').lineWidth(0.85).stroke();

    // Left cell label: Incident
    doc.fontSize(10.5).font('Helvetica-Bold').fillColor('#000000').text('Incident', margin + 6, y + 25, { width: sideColWidth - 12, lineBreak: false });

    // Incident checkboxes (3 rows x 4 columns)
    const incSelected = incident.incident_types || incident.incidentTypes || [];
    const incRow1 = ['Move In & Out', 'Fire & Safety', 'Intrusion & Access', 'Theft and loss'];
    const incRow2 = ['CCTV & Systems', 'Utilities & Building', 'Accident & Medical', 'Disturbance'];
    const incRow3 = ['Property Damage', 'Parking & Vehicles', 'Visitors & Contractors', 'Others'];

    const incColWidth = mainColWidth / 4;
    for (let i = 0; i < incRow1.length; i++) {
      const name = incRow1[i];
      const isChecked = incSelected.includes(name);
      this.drawCheckbox(doc, margin + sideColWidth + 6 + i * incColWidth, y + 6, name, isChecked, 10, 8.5);
    }
    for (let i = 0; i < incRow2.length; i++) {
      const name = incRow2[i];
      const isChecked = incSelected.includes(name);
      this.drawCheckbox(doc, margin + sideColWidth + 6 + i * incColWidth, y + 24, name, isChecked, 10, 8.5);
    }
    for (let i = 0; i < incRow3.length; i++) {
      const name = incRow3[i];
      const isChecked = incSelected.includes(name) || (name === 'Others' && !!incident.incident_other_text);
      this.drawCheckbox(doc, margin + sideColWidth + 6 + i * incColWidth, y + 42, name, isChecked, 10, 8.5);
    }
    y += incHeight;

    // 3.4 Summary Prompt Banner (Full width banner across table: Navy Blue #002776 background with bold white text)
    const promptHeight = 16;
    doc.rect(margin, y, contentWidth, promptHeight).fillColor('#002776').strokeColor('#000000').lineWidth(0.85).fillAndStroke();
    doc.fontSize(9).font('Helvetica-BoldOblique').fillColor('#FFFFFF').text(
      '(Explain What, Where, When, Who, Why and How action taken)',
      margin + 8,
      y + 3.5,
      { width: contentWidth - 16, lineBreak: false }
    );
    y += promptHeight;

    // 3.5 Summary Text Field Box (Dedicated 10pt Bold Dark Red highlighted summary, fitting 2 lines with 1.25 line spacing)
    const summaryBoxHeight = 32;
    doc.rect(margin, y, contentWidth, summaryBoxHeight).strokeColor('#000000').lineWidth(0.85).stroke();
    const summaryText = incident.summary_line || incident.summaryLine || '';
    if (summaryText) {
      doc
        .fontSize(10)
        .font('Helvetica-Bold')
        .fillColor('#8B0000') // Dark Red
        .text(
          summaryText,
          margin + 8,
          y + 3.5,
          {
            width: contentWidth - 16,
            height: summaryBoxHeight - 5,
            lineGap: 1.25,
            ellipsis: true,
          }
        );
    } else {
      // Clean subtle writing guides if empty
      doc.moveTo(margin + 8, y + summaryBoxHeight - 6).lineTo(margin + contentWidth - 8, y + summaryBoxHeight - 6).strokeColor('#E2E8F0').lineWidth(0.5).stroke();
    }
    y += summaryBoxHeight;

    // 3.6 Who was notified? Section (Header strip: Navy Blue #002776 fill with bold italic white text, Capitalized each word)
    const notifHeight = 56;
    const notifHeaderHeight = 16;
    doc.rect(margin, y, contentWidth, notifHeight).strokeColor('#000000').lineWidth(0.85).stroke();
    doc.rect(margin, y, contentWidth, notifHeaderHeight).fillColor('#002776').strokeColor('#000000').lineWidth(0.85).fillAndStroke();

    doc.fontSize(9.5).font('Helvetica-BoldOblique').fillColor('#FFFFFF').text('Who Was Notified?', margin + 8, y + 3.5, { lineBreak: false });

    const notifiedSelected = incident.who_notified || incident.whoNotified || [];
    const notifRow1 = ['Police', 'Ambulance', 'Civil Defense', 'Client Management'];
    const notifColWidth = contentWidth / 4;

    // Row 1
    for (let i = 0; i < notifRow1.length; i++) {
      const notifName = notifRow1[i];
      const isChecked = notifiedSelected.includes(notifName);
      this.drawCheckbox(doc, margin + 8 + i * notifColWidth, y + 20, notifName, isChecked, 10, 8.5);
    }

    // Row 2: One full line space between rows
    const isUssChecked = notifiedSelected.includes('USS Management');
    this.drawCheckbox(doc, margin + 8, y + 38, 'USS Management', isUssChecked, 10, 8.5);

    const isOtherNotifiedChecked = notifiedSelected.includes('Others') || !!incident.notified_other_text;
    this.drawCheckbox(doc, margin + 8 + notifColWidth, y + 38, 'Others:', isOtherNotifiedChecked, 10, 8.5);
    if (incident.notified_other_text) {
      doc.fontSize(8.5).font('Helvetica-Bold').fillColor('#000000').text(incident.notified_other_text, margin + 8 + notifColWidth + 50, y + 38, { width: 220, lineBreak: false });
    } else {
      doc.moveTo(margin + 8 + notifColWidth + 50, y + 47).lineTo(margin + 8 + notifColWidth + 240, y + 47).strokeColor('#64748B').lineWidth(0.5).stroke();
    }
    y += notifHeight;

    // 3.7 Description of Incident Section (Generous height filling the official page)
    const descHeight = 166;
    doc.rect(margin, y, contentWidth, descHeight).strokeColor('#000000').lineWidth(0.85).stroke();
    doc.moveTo(margin + sideColWidth, y).lineTo(margin + sideColWidth, y + descHeight).strokeColor('#000000').lineWidth(0.85).stroke();

    // Left cell label: Description of Incident
    doc.fontSize(10.5).font('Helvetica-Bold').fillColor('#000000').text(
      'Description of\nIncident',
      margin + 6,
      y + 35,
      { width: sideColWidth - 12, align: 'center' }
    );

    const maxLineWidth = mainColWidth - 16;
    const descContent = incident.description || '';

    const allLines = this.parseRichTextLines(doc, descContent, maxLineWidth);
    const page1Lines = allLines.slice(0, 15);
    const overflowLines = allLines.length > 15 ? allLines.slice(15) : [];

    this.renderRichLines(doc, page1Lines, margin + sideColWidth + 8, y + 8, 15);

    if (overflowLines.length > 0) {
      doc
        .fontSize(8)
        .font('Helvetica-BoldOblique')
        .fillColor('#475569')
        .text('[Description continued on next page...]', margin + sideColWidth + 8, y + descHeight - 11, {
          width: mainColWidth - 16,
          align: 'right',
          lineBreak: false,
        });
    }
    y += descHeight;

    // 3.8 Site Supervisor Sign-Off Row (Perfect proportional column alignment)
    const supervisorHeight = 26;
    doc.rect(margin, y, contentWidth, supervisorHeight).strokeColor('#000000').lineWidth(0.85).stroke();
    doc.fontSize(9.5).font('Helvetica-Bold').fillColor('#000000');
    doc.text('Site Supervisor:', margin + 6, y + 8, { lineBreak: false });
    const supName = incident.site_supervisor_name || incident.siteSupervisorName || '_______________';
    doc.fontSize(9.5).font('Helvetica-Bold').text(supName, margin + 84, y + 8, { width: 98, lineBreak: false });

    doc.fontSize(9.5).font('Helvetica-Bold').text('ID No.:', margin + 188, y + 8, { lineBreak: false });
    const supId = incident.site_supervisor_id || incident.siteSupervisorId || '__________';
    doc.fontSize(9.5).font('Helvetica-Bold').text(supId, margin + 225, y + 8, { width: 46, lineBreak: false });

    doc.fontSize(9.5).font('Helvetica-Bold').text('Signature:', margin + 276, y + 8, { lineBreak: false });
    const supSigVal = incident.site_supervisor_signature || incident.siteSupervisorSignature || incident.site_supervisor_signature_url || incident.siteSupervisorSignatureUrl || incident.site_supervisor_signature_image || incident.siteSupervisorSignatureImage || incident.supervisor_signature || incident.supervisorSignature || incident.security_guard_signature || incident.securityGuardSignature || incident.guard_signature || incident.guardSignature || incident.signature;
    const supSigBuf = await this.getSignatureBuffer(supSigVal);
    if (supSigBuf) {
      try {
        doc.image(supSigBuf, margin + 328, y + 2.5, { fit: [82, supervisorHeight - 5], valign: 'center' });
      } catch (err: any) {
        console.warn('[PDF] Failed to draw supervisor signature image:', err.message);
        doc.fontSize(9.5).font('Helvetica-Bold').text('Signed', margin + 328, y + 8, { width: 82, lineBreak: false });
      }
    } else {
      const hasSigned = (typeof supSigVal === 'string' && supSigVal.trim().length > 0) || Boolean(incident.site_supervisor_name || incident.siteSupervisorName);
      doc.fontSize(9.5).font('Helvetica-Bold').text(hasSigned ? 'Signed' : '__________', margin + 328, y + 8, { width: 82, lineBreak: false });
    }

    doc.fontSize(9.5).font('Helvetica-Bold').text('Date:', margin + 416, y + 8, { lineBreak: false });
    const supDate = this.formatDateOfficial(incident.site_supervisor_date || incident.siteSupervisorDate) || '__________';
    doc.fontSize(9.5).font('Helvetica-Bold').text(supDate, margin + 446, y + 8, { width: 72, lineBreak: false });
    y += supervisorHeight;

    // 3.9 RECOMMENDATION / ACTION TAKEN (Header strip: Navy Blue #002776 fill with bold italic white text, Capitalize each word)
    const recHeight = 78;
    const recHeaderHeight = 16;
    doc.rect(margin, y, contentWidth, recHeight).strokeColor('#000000').lineWidth(0.85).stroke();
    doc.rect(margin, y, contentWidth, recHeaderHeight).fillColor('#002776').strokeColor('#000000').lineWidth(0.85).fillAndStroke();
    doc.fontSize(9.5).font('Helvetica-BoldOblique').fillColor('#FFFFFF').text('Recommendation / Action Taken', margin + 6, y + 3.5, { lineBreak: false });

    const recText = incident.recommendation_action_taken || incident.recommendationActionTaken;
    if (recText) {
      doc.fontSize(9).font('Helvetica-Bold').fillColor('#000000').text(recText, margin + 8, y + 20, { width: contentWidth - 16, height: 38 });
    }

    // Operations Manager line (Signature: IMAGE support)
    const omY = y + recHeight - 16;
    doc.fontSize(9.5).font('Helvetica-Bold').fillColor('#000000');
    doc.text('Operations Manager:', margin + 6, omY, { lineBreak: false });
    const omName = incident.operations_manager_name || incident.operationsManagerName || '__________';
    doc.fontSize(9.5).font('Helvetica-Bold').text(omName, margin + 115, omY, { width: 155, lineBreak: false });

    doc.fontSize(9.5).font('Helvetica-Bold').text('Signature:', margin + 276, omY, { lineBreak: false });
    const omSigVal = incident.operations_manager_signature || incident.operationsManagerSignature || incident.operations_manager_signature_url || incident.operationsManagerSignatureUrl || incident.operations_manager_signature_image || incident.operationsManagerSignatureImage;
    const omSigBuf = await this.getSignatureBuffer(omSigVal);
    if (omSigBuf) {
      try {
        doc.image(omSigBuf, margin + 328, omY - 6, { fit: [82, 20], valign: 'center' });
      } catch (err: any) {
        console.warn('[PDF] Failed to draw operations manager signature image:', err.message);
        doc.fontSize(9.5).font('Helvetica-Bold').text('Signed', margin + 328, omY, { width: 82, lineBreak: false });
      }
    } else {
      const hasOmSigned = (typeof omSigVal === 'string' && omSigVal.trim().length > 0) || Boolean(incident.operations_manager_name || incident.operationsManagerName);
      doc.fontSize(9.5).font('Helvetica-Bold').text(hasOmSigned ? 'Signed' : '__________', margin + 328, omY, { width: 82, lineBreak: false });
    }

    doc.fontSize(9.5).font('Helvetica-Bold').text('Date:', margin + 416, omY, { lineBreak: false });
    const omDate = this.formatDateOfficial(incident.operations_manager_date || incident.operationsManagerDate) || '__________';
    doc.fontSize(9.5).font('Helvetica-Bold').text(omDate, margin + 446, omY, { width: 72, lineBreak: false });
    y += recHeight;

    // 3.10 ACTION TAKEN BY CLIENT MANAGER (Header strip: Navy Blue #002776 fill with bold italic white text, Capitalize each word)
    const clientHeight = 78;
    const clientHeaderHeight = 16;
    doc.rect(margin, y, contentWidth, clientHeight).strokeColor('#000000').lineWidth(0.85).stroke();
    doc.rect(margin, y, contentWidth, clientHeaderHeight).fillColor('#002776').strokeColor('#000000').lineWidth(0.85).fillAndStroke();
    doc.fontSize(9.5).font('Helvetica-BoldOblique').fillColor('#FFFFFF').text('Action Taken By Client Manager', margin + 6, y + 3.5, { lineBreak: false });

    const clientActionText = incident.client_manager_action_taken || incident.clientManagerActionTaken;
    if (clientActionText) {
      doc.fontSize(9).font('Helvetica-Bold').fillColor('#000000').text(clientActionText, margin + 8, y + 20, { width: contentWidth - 16, height: 38 });
    }

    // Client Manager line (Signature: IMAGE support)
    const cmY = y + clientHeight - 16;
    doc.fontSize(9.5).font('Helvetica-Bold').fillColor('#000000');
    doc.text('Client Manager:', margin + 6, cmY, { lineBreak: false });
    const cmName = incident.client_manager_name || incident.clientManagerName || '__________';
    doc.fontSize(9.5).font('Helvetica-Bold').text(cmName, margin + 92, cmY, { width: 178, lineBreak: false });

    doc.fontSize(9.5).font('Helvetica-Bold').text('Signature:', margin + 276, cmY, { lineBreak: false });
    const cmSigVal = incident.client_manager_signature || incident.clientManagerSignature || incident.client_manager_signature_url || incident.clientManagerSignatureUrl || incident.client_manager_signature_image || incident.clientManagerSignatureImage;
    const cmSigBuf = await this.getSignatureBuffer(cmSigVal);
    if (cmSigBuf) {
      try {
        doc.image(cmSigBuf, margin + 328, cmY - 6, { fit: [82, 20], valign: 'center' });
      } catch (err: any) {
        console.warn('[PDF] Failed to draw client manager signature image:', err.message);
        doc.fontSize(9.5).font('Helvetica-Bold').text('Signed', margin + 328, cmY, { width: 82, lineBreak: false });
      }
    } else {
      const hasCmSigned = (typeof cmSigVal === 'string' && cmSigVal.trim().length > 0) || Boolean(incident.client_manager_name || incident.clientManagerName);
      doc.fontSize(9.5).font('Helvetica-Bold').text(hasCmSigned ? 'Signed' : '__________', margin + 328, cmY, { width: 82, lineBreak: false });
    }

    doc.fontSize(9.5).font('Helvetica-Bold').text('Date:', margin + 416, cmY, { lineBreak: false });
    const cmDate = this.formatDateOfficial(incident.client_manager_date || incident.clientManagerDate) || '__________';
    doc.fontSize(9.5).font('Helvetica-Bold').text(cmDate, margin + 446, cmY, { width: 72, lineBreak: false });

    return overflowLines;
  }

  /**
   * Render continuation pages for overflowing incident description (>15 lines)
   */
  private async renderContinuationPages(
    doc: PDFKit.PDFDocument,
    incident: any,
    settings: SettingsData,
    overflowLines: RichLine[]
  ) {
    const pageWidth = doc.page.width;
    const pageHeight = doc.page.height;
    const margin = 30;
    const contentWidth = pageWidth - margin * 2;
    const linesPerPage = 42;

    let remaining = [...overflowLines];

    while (remaining.length > 0) {
      doc.addPage();
      let y = margin;

      // 1. Continuation Header Block
      const headerHeight = 64;
      doc.rect(margin, y, contentWidth, headerHeight).strokeColor('#000000').lineWidth(1).stroke();

      const logoBuf = await this.getLogoBuffer(settings);
      let logoEnd = margin + 12;

      if (logoBuf) {
        try {
          doc.image(logoBuf, margin + 8, y + 6, {
            fit: [52, 52],
            align: 'center',
            valign: 'center',
          });
          logoEnd = margin + 66;
        } catch (err) {
          console.warn('[PDF] Failed to draw continuation logo:', err);
        }
      }

      // Centered Title
      doc
        .fontSize(16)
        .font('Helvetica-Bold')
        .fillColor('#000000')
        .text('INCIDENT REPORT (CONTINUED)', margin, y + 22, {
          width: contentWidth,
          align: 'center',
          lineBreak: false,
        });

      y += headerHeight + 3;

      // Meta Ribbon
      doc.rect(margin, y, contentWidth, 20).strokeColor('#000000').lineWidth(0.85).stroke();
      doc.fontSize(8.5).font('Helvetica-Bold').fillColor('#0F172A');
      const ref = this.formatRefWithDMY(incident.ref_number || incident.refNumber || '');
      const site = incident.site_name || incident.siteName || '';
      const dt = this.formatDateDMY(incident.incident_date || incident.incidentDate);
      const sh = incident.shift || 'Day';
      doc.text(`Reference: ${ref}   •   Site: ${site}   •   Date: ${dt}   •   Shift: ${sh}`, margin + 8, y + 5.5, {
        lineBreak: false,
      });

      y += 24;

      // Continuation Content Box
      const boxHeight = pageHeight - y - 35; // Leave room for footer
      doc.rect(margin, y, contentWidth, boxHeight).strokeColor('#000000').lineWidth(0.85).stroke();
      doc.rect(margin, y, contentWidth, 18).fill('#D1D5DB');
      doc.moveTo(margin, y + 18).lineTo(margin + contentWidth, y + 18).strokeColor('#000000').lineWidth(0.85).stroke();

      doc
        .fontSize(10.5)
        .font('Helvetica-Bold')
        .fillColor('#000000')
        .text('Description of Incident (continued)', margin + 8, y + 4.5, { lineBreak: false });

      const currentChunk = remaining.slice(0, linesPerPage);
      remaining = remaining.slice(linesPerPage);

      this.renderRichLines(doc, currentChunk, margin + 8, y + 24, linesPerPage);
    }
  }

  /**
   * Parse rich text HTML/text into formatted wrapped lines with styling preserved
   */
  private parseRichTextLines(doc: PDFKit.PDFDocument, rawInput: string, maxLineWidth: number): RichLine[] {
    if (!rawInput) return [];

    const isHtml = /<[a-z][\s\S]*>/i.test(rawInput);
    if (!isHtml) {
      const rawLines = String(rawInput).split(/\r?\n/);
      const lines: RichLine[] = [];
      for (const rawLine of rawLines) {
        if (!rawLine.trim()) {
          lines.push({ spans: [{ text: ' ', font: 'Helvetica', fontSize: 9.5, color: '#000000', underline: false, lineGap: 2.8 }] });
          continue;
        }
        const wrapped = this.wrapTextToLines(doc, rawLine, 'Helvetica', 9.5, maxLineWidth);
        for (const w of wrapped) {
          lines.push({
            spans: [{ text: w, font: 'Helvetica', fontSize: 9.5, color: '#000000', underline: false, lineGap: 2.8 }],
          });
        }
      }
      return lines;
    }

    let html = rawInput
      .replace(/<br\s*[\/]?>/gi, '\n')
      .replace(/<\/p>/gi, '\n')
      .replace(/<\/div>/gi, '\n')
      .replace(/<\/li>/gi, '\n')
      .replace(/<li>/gi, '• ');

    const rawParagraphs = html.trim().split(/\n/).filter((p) => p.trim().length > 0);
    const lines: RichLine[] = [];

    for (const para of rawParagraphs) {
      if (!para.trim()) {
        lines.push({ spans: [{ text: ' ', font: 'Helvetica', fontSize: 9.5, color: '#000000', underline: false, lineGap: 2.8 }] });
        continue;
      }

      const tokens: RichSpan[] = [];
      const tagRegex = /<(\/)?([a-z0-9]+)([^>]*)>|([^<]+)/gi;
      let match: RegExpExecArray | null;

      let isBold = false;
      let isItalic = false;
      let isUnderline = false;
      let curColor = '#000000';
      let curFontSize = 9.5;
      let curLineGap = 2.8;

      while ((match = tagRegex.exec(para)) !== null) {
        if (match[4]) {
          const text = match[4]
            .replace(/&nbsp;/g, ' ')
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&quot;/g, '"')
            .replace(/&#39;/g, "'");

          if (!text) continue;

          let font = 'Helvetica';
          if (isBold && isItalic) font = 'Helvetica-BoldOblique';
          else if (isBold) font = 'Helvetica-Bold';
          else if (isItalic) font = 'Helvetica-Oblique';

          tokens.push({
            text,
            font,
            fontSize: curFontSize,
            color: curColor,
            underline: isUnderline,
            lineGap: curLineGap,
          });
        } else if (match[2]) {
          const isClose = Boolean(match[1]);
          const tagName = match[2].toLowerCase();
          const attrs = match[3] || '';

          if (tagName === 'b' || tagName === 'strong') {
            isBold = !isClose;
          } else if (tagName === 'i' || tagName === 'em') {
            isItalic = !isClose;
          } else if (tagName === 'u') {
            isUnderline = !isClose;
          } else if (tagName === 'span' || tagName === 'p') {
            if (isClose) {
              isBold = false;
              isItalic = false;
              isUnderline = false;
              curColor = '#000000';
              curFontSize = 9.5;
              curLineGap = 2.8;
            } else {
              const styleMatch = attrs.match(/style=(["'])(.*?)\1/i);
              if (styleMatch) {
                const s = styleMatch[2].toLowerCase();
                if (/font-weight:\s*bold/i.test(s)) isBold = true;
                if (/font-style:\s*italic/i.test(s)) isItalic = true;
                if (/text-decoration:\s*underline/i.test(s)) isUnderline = true;
                const colMatch = s.match(/color:\s*(#[0-9a-f]{3,8}|[a-z]+)/i);
                if (colMatch) curColor = colMatch[1];
                const szMatch = s.match(/font-size:\s*(\d+(?:\.\d+)?)(pt|px)?/i);
                if (szMatch) {
                  const sz = parseFloat(szMatch[1]);
                  if (sz >= 8 && sz <= 24) curFontSize = sz;
                }
                const lhMatch = s.match(/line-height:\s*(\d+(?:\.\d+)?)/i);
                if (lhMatch) {
                  const lh = parseFloat(lhMatch[1]);
                  curLineGap = Math.max(1, (lh - 1) * curFontSize);
                }
              }
            }
          }
        }
      }

      const wrappedLines = this.wrapSpansIntoLines(doc, tokens, maxLineWidth);
      lines.push(...wrappedLines);
    }

    return lines;
  }

  private wrapTextToLines(doc: PDFKit.PDFDocument, text: string, font: string, size: number, maxWidth: number): string[] {
    doc.font(font).fontSize(size);
    const words = text.split(/\s+/);
    const lines: string[] = [];
    let curLine = '';

    for (const w of words) {
      const testLine = curLine ? `${curLine} ${w}` : w;
      if (doc.widthOfString(testLine) > maxWidth && curLine) {
        lines.push(curLine);
        curLine = w;
      } else {
        curLine = testLine;
      }
    }
    if (curLine) lines.push(curLine);
    return lines.length > 0 ? lines : [''];
  }

  private wrapSpansIntoLines(doc: PDFKit.PDFDocument, spans: RichSpan[], maxWidth: number): RichLine[] {
    const lines: RichLine[] = [];
    let currentLineSpans: RichSpan[] = [];
    let currentLineWidth = 0;

    for (const span of spans) {
      doc.font(span.font).fontSize(span.fontSize);
      const words = span.text.split(/(\s+)/);

      for (const w of words) {
        if (!w) continue;
        const wWidth = doc.widthOfString(w);

        if (currentLineWidth + wWidth > maxWidth && currentLineSpans.length > 0 && !/^\s+$/.test(w)) {
          lines.push({ spans: currentLineSpans });
          currentLineSpans = [];
          currentLineWidth = 0;
        }

        if (
          currentLineSpans.length > 0 &&
          currentLineSpans[currentLineSpans.length - 1].font === span.font &&
          currentLineSpans[currentLineSpans.length - 1].color === span.color &&
          currentLineSpans[currentLineSpans.length - 1].fontSize === span.fontSize &&
          currentLineSpans[currentLineSpans.length - 1].underline === span.underline
        ) {
          currentLineSpans[currentLineSpans.length - 1].text += w;
        } else {
          currentLineSpans.push({ ...span, text: w });
        }
        currentLineWidth += wWidth;
      }
    }

    if (currentLineSpans.length > 0) {
      lines.push({ spans: currentLineSpans });
    }

    return lines.length > 0
      ? lines
      : [{ spans: [{ text: '', font: 'Helvetica', fontSize: 9.5, color: '#000000', underline: false, lineGap: 2.8 }] }];
  }

  private renderRichLines(doc: PDFKit.PDFDocument, lines: RichLine[], startX: number, startY: number, maxLines: number): number {
    let curY = startY;
    const toRender = lines.slice(0, maxLines);

    for (const line of toRender) {
      let curX = startX;
      const maxSpanGap = Math.max(...line.spans.map((s) => s.lineGap || 2.8));
      const maxSpanSize = Math.max(...line.spans.map((s) => s.fontSize || 9.5));

      for (let i = 0; i < line.spans.length; i++) {
        const span = line.spans[i];
        doc
          .font(span.font)
          .fontSize(span.fontSize)
          .fillColor(span.color);

        doc.text(span.text, curX, curY, {
          underline: span.underline,
          lineBreak: false,
        });

        curX += doc.widthOfString(span.text);
      }

      curY += maxSpanSize + maxSpanGap;
    }

    return curY;
  }

  /**
   * Render attached photographs in 2x3 grid pages (6 images per A4 page, filling the box frame)
   */
  private async renderPhotoPages(doc: PDFKit.PDFDocument, incident: any) {
    const photos = incident.photos || [];
    const margin = 30;
    const contentWidth = doc.page.width - margin * 2;
    const photosPerPage = 6; // 2x3 grid: 6 images per A4 page

    for (let pIdx = 0; pIdx < photos.length; pIdx += photosPerPage) {
      doc.addPage();
      const pagePhotos = photos.slice(pIdx, pIdx + photosPerPage);

      // Section Header (Navy Blue #002776 background with white text Capitalize each word)
      doc.rect(margin, margin, contentWidth, 22).fillColor('#002776').strokeColor('#000000').lineWidth(0.85).fillAndStroke();
      doc
        .fontSize(10.5)
        .font('Helvetica-Bold')
        .fillColor('#FFFFFF')
        .text('Attached Photographs & Evidence', margin + 10, margin + 5.5, { lineBreak: false });

      const colGap = 15;
      const rowGap = 12;
      const cellWidth = (contentWidth - colGap) / 2; // ~260 pt
      const cellHeight = 232; // Exactly fits 3 rows on A4 without any overlap or double pages
      const gridStartY = margin + 28;

      for (let i = 0; i < pagePhotos.length; i++) {
        const photo = pagePhotos[i];
        const col = i % 2;
        const row = Math.floor(i / 2);
        const cellX = margin + col * (cellWidth + colGap);
        const cellY = gridStartY + row * (cellHeight + rowGap);

        // Frame
        doc.rect(cellX, cellY, cellWidth, cellHeight).strokeColor('#CBD5E1').lineWidth(0.85).stroke();

        // Image placeholder or real image filling the box frame
        const imgPadding = 6;
        const imgX = cellX + imgPadding;
        const imgY = cellY + imgPadding;
        const imgW = cellWidth - imgPadding * 2;
        const imgH = 152;

        doc.rect(imgX, imgY, imgW, imgH).fill('#F1F5F9');

        try {
          const imgBuffer = await storage.getFileBuffer(photo.storage_key || photo.storageKey);
          if (imgBuffer) {
            // Fill the box frame completely (cover) without half-size letterboxing, neatly clipped
            doc.save();
            doc.rect(imgX, imgY, imgW, imgH).clip();
            doc.image(imgBuffer, imgX, imgY, {
              cover: [imgW, imgH],
              align: 'center',
              valign: 'center',
            });
            doc.restore();
            doc.rect(imgX, imgY, imgW, imgH).strokeColor('#E2E8F0').lineWidth(0.5).stroke();
          } else {
            doc
              .fontSize(9)
              .font('Helvetica')
              .fillColor('#94A3B8')
              .text('[Photo File On Remote Storage]', imgX, imgY + imgH / 2 - 5, {
                width: imgW,
                align: 'center',
              });
          }
        } catch {
          doc
            .fontSize(9)
            .font('Helvetica')
            .fillColor('#94A3B8')
            .text('[Photo Evidence]', imgX, imgY + imgH / 2 - 5, {
              width: imgW,
              align: 'center',
            });
        }

        // Caption & Metadata
        const textStartY = imgY + imgH + 8;
        const photoIndex = pIdx + i + 1;
        const lat = photo.latitude || incident.site_latitude || incident.latitude || '25.1185° N';
        const lng = photo.longitude || incident.site_longitude || incident.longitude || '55.1384° E';
        const coordLabel = `Photo #${photoIndex}: Lat: ${lat}, Long: ${lng}`;

        doc
          .fontSize(8.5)
          .font('Helvetica-Bold')
          .fillColor('#0F172A')
          .text(coordLabel, cellX + 8, textStartY, {
            width: cellWidth - 16,
            lineBreak: false,
          });

        doc
          .fontSize(9.5)
          .font('Helvetica-Bold')
          .fillColor('#C00000')
          .text(
            photo.caption ? `Caption: ${photo.caption}` : 'Caption: Ground floor near block 2',
            cellX + 8,
            textStartY + 14,
            { width: cellWidth - 16, lineBreak: false }
          );

        const rawRef = incident.ref_number || incident.refNumber || '';
        const displayRef = this.formatRefWithDMY(rawRef);
        const fileSizeKb = photo.file_size ? (photo.file_size / 1024).toFixed(1) : '141.0';

        doc
          .fontSize(8)
          .font('Helvetica')
          .fillColor('#64748B')
          .text(
            `Ref: ${displayRef} • Size: ${fileSizeKb} KB`,
            cellX + 8,
            textStartY + 29,
            { width: cellWidth - 16, lineBreak: false }
          );
      }
    }
  }

  // --- Helper Drawing Utilities ---
  private drawSectionHeader(
    doc: PDFKit.PDFDocument,
    x: number,
    y: number,
    width: number,
    title: string,
    height = 18
  ) {
    doc.rect(x, y, width, height).fillAndStroke('#D1D5DB', '#000000'); // Clean professional grey
    doc
      .fontSize(10.5)
      .font('Helvetica-Bold')
      .fillColor('#000000')
      .text(title, x + 8, y + 4, { lineBreak: false });
  }

  private drawCell(
    doc: PDFKit.PDFDocument,
    x: number,
    y: number,
    width: number,
    height: number,
    label: string,
    value: string
  ) {
    doc.rect(x, y, width, height).strokeColor('#000000').lineWidth(0.85).stroke();
    doc
      .fontSize(10)
      .font('Helvetica-Bold')
      .fillColor('#000000')
      .text(label, x + 6, y + 5.5, { lineBreak: false });
    const labelWidth = doc.widthOfString(label);
    doc
      .fontSize(10)
      .font('Helvetica-Bold')
      .fillColor('#000000')
      .text(value, x + 10 + labelWidth, y + 5.5, { width: width - labelWidth - 14, lineBreak: false });
  }

  private drawCellContent(
    doc: PDFKit.PDFDocument,
    x: number,
    y: number,
    width: number,
    label: string,
    value: string
  ) {
    doc
      .fontSize(10)
      .font('Helvetica-Bold')
      .fillColor('#000000')
      .text(label, x + 6, y + 5.5, { lineBreak: false });
    const labelWidth = doc.widthOfString(label);
    doc
      .fontSize(10)
      .font('Helvetica-Bold')
      .fillColor('#000000')
      .text(value, x + 10 + labelWidth, y + 5.5, { width: width - labelWidth - 14, lineBreak: false });
  }

  private drawCheckbox(
    doc: PDFKit.PDFDocument,
    x: number,
    y: number,
    label: string,
    checked: boolean,
    boxSize = 10.5,
    fontSize = 9.5,
    color = '#000000'
  ) {
    doc.rect(x, y, boxSize, boxSize).strokeColor(color).lineWidth(0.85).stroke();
    if (checked) {
      const pad = 2;
      doc
        .moveTo(x + pad, y + pad)
        .lineTo(x + boxSize - pad, y + boxSize - pad)
        .strokeColor(color)
        .lineWidth(1.3)
        .stroke();
      doc
        .moveTo(x + boxSize - pad, y + pad)
        .lineTo(x + pad, y + boxSize - pad)
        .strokeColor(color)
        .lineWidth(1.3)
        .stroke();
    }
    doc
      .fontSize(fontSize)
      .font('Helvetica-Bold')
      .fillColor(color)
      .text(label, x + boxSize + 4.5, y + 0.5, { lineBreak: false });
  }
}

export const pdfService = new PdfService();
