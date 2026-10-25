<?php
/**
 * Central PDF Typography & Colour Tokens (Print / PDF Engine)
 * Strictly always light mode for pristine physical printing on A4 paper.
 */

// Fonts
define('PDF_FONT_PRIMARY', 'Noto Sans');
define('PDF_FONT_CONDENSED', 'Noto Sans Condensed');
define('PDF_FONT_ARABIC', 'Noto Sans Arabic');

// Incident Report Sizes (A4 Portrait)
define('PDF_INCIDENT_TITLE_PT', 20); // Bold, underlined
define('PDF_INCIDENT_LABEL_PT', 10); // Bold
define('PDF_INCIDENT_OPTIONS_PT', 9); // min 7pt
define('PDF_INCIDENT_DESC_PT', 11); // Strict 15-line box fit
define('PDF_INCIDENT_COMPANY_PT', 10);
define('PDF_INCIDENT_ADDRESS_PT', 7);
define('PDF_INCIDENT_SIGNOFF_PT', 9);
define('PDF_INCIDENT_FOOTER_PT', 8);

// Patrol Checklist Sizes (A4 Landscape)
define('PDF_PATROL_TITLE_PT', 16);
define('PDF_PATROL_SUBTITLE_PT', 11);
define('PDF_PATROL_SECTION_BAR_PT', 10);
define('PDF_PATROL_HEADER_PT', 9);
define('PDF_PATROL_POINT_PT', 8); // Never smaller than 8pt
define('PDF_PATROL_FOOTER_PT', 8);

// Daily & Monthly Reports
define('PDF_REPORT_TITLE_PT', 16);
define('PDF_REPORT_SECTION_PT', 10);
define('PDF_REPORT_TABLE_HDR_PT', 9);
define('PDF_REPORT_BODY_PT', 9); // min 8pt
define('PDF_REPORT_KPI_NUM_PT', 24);
define('PDF_REPORT_KPI_LBL_PT', 9);

// Attendance & Payroll
define('PDF_ATT_TITLE_PT', 14);
define('PDF_ATT_HEADER_PT', 9.5);
define('PDF_ATT_WEEKDAY_PT', 7);
define('PDF_ATT_BODY_PT', 12);
define('PDF_ATT_ROW_HEIGHT', 20);
define('PDF_ATT_HEADER_HEIGHT', 45);

// Colors (Always Light Hex)
define('PDF_COLOR_HEADER_BAND', '#12263F');
define('PDF_COLOR_TABLE_HEADER', '#235F63');
define('PDF_COLOR_TEXT_MAIN', '#000000');
define('PDF_COLOR_TEXT_MUTED', '#4A5560');
define('PDF_COLOR_BORDER', '#8A97A3');
define('PDF_COLOR_ZEBRA', '#F6F8FB');
define('PDF_COLOR_FIRE', '#B03A2E');
define('PDF_COLOR_SECURITY', '#1F4E79');
define('PDF_COLOR_PARKING', '#9A5A08');
define('PDF_COLOR_UTILITIES', '#0B6E77');
define('PDF_COLOR_COMMON', '#6C3483');
