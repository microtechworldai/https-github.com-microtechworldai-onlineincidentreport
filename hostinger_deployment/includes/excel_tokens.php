<?php
/**
 * Central Excel Typography & Dimension Tokens (PhpSpreadsheet)
 * Used across Incident, Supervisor, and Attendance/Payroll exports.
 */

// Fonts
define('EXCEL_FONT_NAME', 'Noto Sans');
define('EXCEL_TABLE_FONT_NAME', 'Noto Sans Condensed');

// Sizes
define('EXCEL_TITLE_SIZE', 14);
define('EXCEL_HEADER_SIZE', 10);
define('EXCEL_WEEKDAY_SIZE', 7);
define('EXCEL_BODY_SIZE', 12);

// Dimensions
define('EXCEL_ROW_HEIGHT_STANDARD', 20);
define('EXCEL_ROW_HEIGHT_HEADER', 45);

// ARGB Colours (Alpha, Red, Green, Blue)
define('EXCEL_COLOR_HEADER_BG', 'FF235F63'); // #235F63
define('EXCEL_COLOR_HEADER_TEXT', 'FFFFFFFF');
define('EXCEL_COLOR_WEEKDAY_BG', 'FF12263F'); // #12263F
define('EXCEL_COLOR_ZEBRA_BG', 'FFF6F8FB');
define('EXCEL_COLOR_BORDER', 'FF8A97A3');
define('EXCEL_COLOR_OFF_BG', 'FFFF9A92');
define('EXCEL_COLOR_OFF_TEXT', 'FFB3261E');
define('EXCEL_COLOR_NIGHT_BG', 'FFDCE6F1');
define('EXCEL_COLOR_NIGHT_TEXT', 'FF1F3A93');
