<?php
/**
 * Official USS Incident Report Form
 * Strict One-Page A4 Portrait Layout matching the Official Specification
 * Pure black borders, bold typography, perfectly proportioned to fit exactly on A4 paper
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die("Invalid incident ID.");
}

$db = get_db();
$settings = get_system_settings();

$stmt = $db->prepare("
    SELECT i.*, s.client_name, s.client_contact, s.latitude as site_latitude, s.longitude as site_longitude 
    FROM incidents i 
    LEFT JOIN sites s ON s.id = i.site_id 
    WHERE i.id = ? 
    LIMIT 1
");
$stmt->execute([$id]);
$incident = $stmt->fetch();
if (!$incident) {
    die("Incident not found.");
}

$discReasons = json_decode($incident['disciplinary_reasons'] ?: '[]', true) ?: [];
$incTypes = json_decode($incident['incident_types'] ?: '[]', true) ?: [];
$notified = json_decode($incident['who_notified'] ?: '[]', true) ?: [];

$stmtPhotos = $db->prepare("SELECT * FROM incident_photos WHERE incident_id = ? ORDER BY sort_order ASC, id ASC");
$stmtPhotos->execute([$id]);
$photos = $stmtPhotos->fetchAll();
$photoChunks = !empty($photos) ? array_chunk($photos, 6) : [];
$totalPhotoPages = count($photoChunks);
$totalPages = 1 + $totalPhotoPages;

// Fixed checklist categories matching official form
$disciplinaryList = [
    ['Sleeping', 'Poor Grooming', 'Argument', 'Missing', 'Misbehavior'],
    ['Uniform', 'Late Reporting', 'Mobile', 'Others']
];

$incidentList = [
    ['Move In & Out', 'Fire & Safety', 'Intrusion & Access', 'Theft and loss'],
    ['CCTV & Systems', 'Utilities & Building', 'Accident & Medical', 'Disturbance'],
    ['Property Damage', 'Parking & Vehicles', 'Visitors & Contractors', 'Others']
];

if (!function_exists('render_pdf_box')) {
    function render_pdf_box($checked) {
        return $checked 
            ? '<span class="chk-box checked">&#10005;</span>' 
            : '<span class="chk-box"></span>';
    }
}

if (!function_exists('render_priority_pdf_box')) {
    function render_priority_pdf_box($checked) {
        return $checked 
            ? '<span class="chk-box-white checked">&#10005;</span>' 
            : '<span class="chk-box-white"></span>';
    }
}

/**
 * Format reference code to official USS DD/MM/YYYY standard
 * e.g. INC-PETALZBY-20261018-0004 -> INC-PETALZBY-18/10/2026-0004
 */
if (!function_exists('format_incident_ref')) {
    function format_incident_ref($ref) {
        if (!$ref) return '';
        if (preg_match('/^(INC-[A-Za-z0-9_-]+-)(\d{4})(\d{2})(\d{2})(-\d+)$/', $ref, $m)) {
            return $m[1] . $m[4] . '/' . $m[3] . '/' . $m[2] . $m[5];
        }
        return $ref;
    }
}

/**
 * Cleanly format GPS latitude and longitude coordinates
 */
if (!function_exists('format_coord')) {
    function format_coord($val, $type = 'lat') {
        $val = trim((string)$val);
        if ($val === '') {
            return $type === 'lat' ? '25.1610° N' : '55.4120° E';
        }
        if (preg_match('/[NSEWnsew]/', $val) || strpos($val, '°') !== false || strpos($val, '&deg;') !== false) {
            return $val;
        }
        if (is_numeric($val)) {
            $num = (float)$val;
            if ($type === 'lat') {
                return abs($num) . '° ' . ($num >= 0 ? 'N' : 'S');
            } else {
                return abs($num) . '° ' . ($num >= 0 ? 'E' : 'W');
            }
        }
        return $val;
    }
}

/**
 * Format patrol metadata date as standard DD-Mmm-YYYY (e.g. 18-Oct-2026)
 */
if (!function_exists('format_meta_date')) {
    function format_meta_date($dateStr) {
        if (empty($dateStr) || $dateStr === '0000-00-00') return '';
        $ts = is_numeric($dateStr) ? (int)$dateStr : strtotime((string)$dateStr);
        return $ts ? date('d-M-Y', $ts) : (string)$dateStr;
    }
}

/**
 * Resolve direct photo content as embedded base64 data URI to guarantee 100% rendering
 */
if (!function_exists('get_photo_src')) {
    function get_photo_src($filePath, $photoId = 0) {
        if (empty($filePath)) return '';

        $norm = str_replace('\\', '/', $filePath);

    // 1. Direct path on disk (absolute or relative)
    if (file_exists($filePath)) {
        $mime = mime_content_type($filePath) ?: 'image/jpeg';
        $data = file_get_contents($filePath);
        if ($data !== false) {
            return 'data:' . $mime . ';base64,' . base64_encode($data);
        }
    }

    // 2. Extracted storage/private or uploads sub-path relative to ROOT_PATH
    if (preg_match('#(storage/private/.*|uploads/.*)$#i', $norm, $m)) {
        $candRel = ROOT_PATH . '/' . $m[1];
        if (file_exists($candRel)) {
            $mime = mime_content_type($candRel) ?: 'image/jpeg';
            $data = file_get_contents($candRel);
            if ($data !== false) {
                return 'data:' . $mime . ';base64,' . base64_encode($data);
            }
        }
    }

    // 3. Relative to ROOT_PATH
    $cleanRel = ltrim($norm, '/');
    $cand1 = ROOT_PATH . '/' . $cleanRel;
    if (file_exists($cand1)) {
        $mime = mime_content_type($cand1) ?: 'image/jpeg';
        $data = file_get_contents($cand1);
        if ($data !== false) {
            return 'data:' . $mime . ';base64,' . base64_encode($data);
        }
    }

    // 4. Relative to UPLOADS_PATH
    if (defined('UPLOADS_PATH')) {
        $cand2 = UPLOADS_PATH . '/' . basename($filePath);
        if (file_exists($cand2)) {
            $mime = mime_content_type($cand2) ?: 'image/jpeg';
            $data = file_get_contents($cand2);
            if ($data !== false) {
                return 'data:' . $mime . ';base64,' . base64_encode($data);
            }
        }
    }

    // 5. Fallback to serve_file.php gateway
    if ($photoId > 0) {
        return APP_URL . '/admin/serve_file.php?photo_id=' . (int)$photoId;
    }

    return APP_URL . '/' . $cleanRel;
    }
}

$formattedRef = format_incident_ref($incident['ref_number']);
$companyDisplayName = !empty($settings['company_name'])
    ? (strcasecmp(trim($settings['company_name']), 'ULTIMATE SECURITY SERVICES') === 0 || stripos($settings['company_name'], '(USS)') === false
        ? 'Ultimate Security Services (USS)'
        : $settings['company_name'])
    : 'Ultimate Security Services (USS)';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Incident_Report_<?= htmlspecialchars(str_replace('/', '-', $formattedRef)) ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 6mm 10mm 6mm 10mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9.5pt;
            color: #000;
            background: #cbd5e1;
            line-height: 1.25;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .no-print-bar {
            background: #0c2356;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            font-size: 13px;
        }

        .btn-print {
            background: #0284c7;
            color: #fff;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 13px;
            cursor: pointer;
        }
        .btn-print:hover {
            background: #0369a1;
        }

        .page-sheet {
            width: 210mm;
            height: 297mm;
            max-height: 297mm;
            margin: 15px auto;
            background: #fff;
            padding: 6mm 10mm;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden;
        }

        /* Top Header */
        .report-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .header-logo-box {
            width: 120px;
        }

        .header-logo {
            max-height: 60px;
            max-width: 120px;
            object-fit: contain;
        }

        .header-title-box {
            flex: 1;
            text-align: center;
        }

        .header-title {
            font-size: 24pt;
            font-weight: 900;
            color: #0c2356;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .header-title-underline {
            height: 4px;
            background: #0c2356;
            width: 280px;
            margin: 0 auto;
        }

        /* Metadata 2-Column Table */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 10pt;
            font-weight: bold;
        }

        .meta-table td {
            padding: 2.5px 0;
            vertical-align: middle;
            color: #000;
        }

        .meta-label {
            font-weight: bold;
            white-space: nowrap;
        }

        .meta-value {
            font-weight: bold;
        }

        /* Main Form Border Grid */
        .main-form-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
        }

        .main-form-table td, .main-form-table th {
            border: 1px solid #000;
            padding: 3.5px 7px;
            vertical-align: middle;
        }

        .col-side-label {
            width: 16%;
            font-weight: bold;
            font-size: 10.5pt;
            text-align: center;
            vertical-align: middle;
        }

        .col-content-box {
            padding: 6px 8px;
        }

        .col-content-box .row-flex:not(:last-child) {
            margin-bottom: 7.5px !important;
        }

        .priority-navy-row td {
            background: #002266 !important;
            padding: 5px 8px !important;
            border-bottom: 1.5px solid #000 !important;
        }

        .chk-box-white {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 14px;
            height: 14px;
            border: 1.8px solid #ffffff;
            margin-right: 6px;
            vertical-align: middle;
            background: transparent;
            color: #ffffff;
            font-size: 11px;
            font-weight: 900;
            line-height: 1;
            flex-shrink: 0;
        }

        .chk-item-white {
            display: inline-flex;
            align-items: center;
            color: #ffffff;
            font-weight: bold;
            font-size: 10pt;
        }

        .row-flex {
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }

        .chk-item {
            display: inline-flex;
            align-items: center;
            width: 20%;
            font-size: 9.5pt;
            font-weight: bold;
            color: #000;
        }

        .chk-item-4 {
            display: inline-flex;
            align-items: center;
            width: 25%;
            font-size: 9.5pt;
            font-weight: bold;
            color: #000;
        }

        .chk-box {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 14px;
            height: 14px;
            border: 1.8px solid #000;
            margin-right: 6px;
            vertical-align: middle;
            background: #fff;
            color: #000;
            font-size: 11px;
            font-weight: 900;
            line-height: 1;
            flex-shrink: 0;
        }

        .navy-bar-italic {
            background: #002266 !important;
            color: #ffffff !important;
            font-weight: bold;
            font-style: italic;
            font-size: 10pt;
            padding: 4px 8px !important;
            border-top: 1.5px solid #000 !important;
            border-bottom: 1.5px solid #000 !important;
        }

        .navy-bar-italic td {
            background: #002266 !important;
            color: #ffffff !important;
            font-weight: bold;
            font-style: italic;
            font-size: 10pt;
            padding: 4px 8px !important;
            border-top: 1.5px solid #000 !important;
            border-bottom: 1.5px solid #000 !important;
        }

        .bar-header {
            background: #002266 !important;
            color: #ffffff !important;
            font-weight: bold;
            font-style: italic;
            font-size: 10pt;
            padding: 4px 8px !important;
            border-top: 1.5px solid #000 !important;
            border-bottom: 1.5px solid #000 !important;
        }

        .summary-line-box {
            height: 45px !important;
            min-height: 45px !important;
            max-height: 45px !important;
            padding: 3px 8px !important;
            font-size: 15px !important;
            font-weight: bold !important;
            color: #8b0000 !important;
            line-height: 1.30 !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            word-break: break-word !important;
            white-space: normal !important;
            border-bottom: 1.5px solid #000 !important;
            overflow: hidden !important;
            box-sizing: border-box !important;
            vertical-align: middle !important;
        }

        .description-box {
            height: 96mm !important;
            min-height: 96mm !important;
            max-height: 96mm !important;
            padding: 4px 8px !important;
            font-size: 13px !important;
            line-height: 1.30 !important;
            vertical-align: top !important;
            border-bottom: 1.5px solid #000 !important;
            overflow: hidden !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            word-break: break-word !important;
            white-space: normal !important;
            box-sizing: border-box !important;
        }

        .description-box,
        .description-box * {
            font-size: 13px !important;
            line-height: 1.30 !important;
            box-sizing: border-box !important;
        }

        .description-box p, 
        .description-box div {
            margin: 0 !important;
            padding: 0 !important;
            font-size: 13px !important;
            line-height: 1.30 !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            word-break: break-word !important;
        }

        .description-box p + p,
        .description-box div + div {
            margin-top: 0 !important;
        }

        .description-box br {
            line-height: 1.30 !important;
        }

        .description-box span {
            font-size: 13px !important;
            line-height: 1.30 !important;
        }

        .signoff-row {
            padding: 4.5px 8px !important;
            font-size: 9.5pt;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1.5px solid #000 !important;
        }

        .signoff-row u {
            font-weight: normal;
            text-decoration: none;
        }

        .action-box {
            height: 17mm;
            min-height: 17mm;
            padding: 4px 8px !important;
            font-size: 9.5pt;
            font-weight: bold;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-bottom: 1.5px solid #000 !important;
        }

        .action-text {
            font-weight: normal;
            margin-bottom: 4px;
            font-size: 9pt;
        }

        .signoff-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 9.5pt;
            font-weight: bold;
        }

        .signoff-bottom u {
            font-weight: normal;
            text-decoration: none;
        }

        .pdf-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 8.5pt;
            font-weight: bold;
            margin-top: 6px;
            padding-top: 4px;
            border-top: 1px solid #e2e8f0;
        }

        /* Print Media Overrides */
        @media print {
            .no-print-bar { display: none !important; }
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
                height: 297mm !important;
                max-height: 297mm !important;
                overflow: hidden !important;
            }
            .page-sheet {
                box-shadow: none !important;
                margin: 0 auto !important;
                padding: 0 !important;
                width: 100% !important;
                height: 285mm !important;
                max-height: 285mm !important;
                overflow: hidden !important;
                page-break-after: avoid !important;
                page-break-inside: avoid !important;
            }
            .page-sheet.page-break {
                page-break-before: always !important;
                page-break-after: auto !important;
            }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <div>
        <strong>Official Incident Dossier:</strong> <?= htmlspecialchars($formattedRef) ?> &bull; <?= htmlspecialchars($incident['site_name']) ?>
    </div>
    <div style="display: flex; align-items: center; gap: 10px;">
        <button class="btn-print" onclick="window.print();">&#128438; Print / Save as PDF</button>
        <button style="background: transparent; color: #fff; border: 1px solid #475569; padding: 7px 15px; border-radius: 6px; cursor: pointer; font-size: 13px;" onclick="window.close();">Close</button>
    </div>
</div>

<!-- ================================================= PAGE 1 ================================================= -->
<div class="page-sheet">

    <!-- Top Header with Logo and Underlined Title -->
    <div class="report-header">
        <div class="header-logo-box">
            <?php if (!empty($settings['logo_url']) && file_exists(ROOT_PATH . '/' . $settings['logo_url'])): ?>
                <img src="<?= APP_URL ?>/<?= htmlspecialchars($settings['logo_url']) ?>" alt="Logo" class="header-logo">
            <?php else: ?>
                <div style="width: 100px; height: 50px;"></div>
            <?php endif; ?>
        </div>
        <div class="header-title-box">
            <div class="header-title">INCIDENT REPORT</div>
            <div class="header-title-underline"></div>
        </div>
        <div class="header-logo-box"></div>
    </div>

    <!-- General Patrol Metadata (Left: Site/Reporter/Designation, Right: Date/Time/Shift) -->
    <table class="meta-table">
        <tr>
            <td style="width: 58%; vertical-align: top; padding: 0;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 32%; padding: 2.5px 0;" class="meta-label">Site Name :</td>
                        <td style="width: 68%; padding: 2.5px 0;" class="meta-value"><?= htmlspecialchars($incident['site_name']) ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 2.5px 0;" class="meta-label">Person Reporting :</td>
                        <td style="padding: 2.5px 0;" class="meta-value"><?= htmlspecialchars($incident['reported_by_name']) ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 2.5px 0;" class="meta-label">Designation :</td>
                        <td style="padding: 2.5px 0;" class="meta-value"><?= htmlspecialchars($incident['reported_by_designation']) ?></td>
                    </tr>
                </table>
            </td>
            <td style="width: 42%; vertical-align: top; text-align: right; padding: 0;">
                <div style="display: inline-block; text-align: left; padding-right: 8px;">
                    <div style="padding: 2.5px 0;"><span class="meta-label">Date :</span> <span class="meta-value"><?= format_meta_date($incident['incident_date']) ?></span></div>
                    <div style="padding: 2.5px 0;"><span class="meta-label">Time :</span> <span class="meta-value"><?= format_time($incident['incident_time']) ?></span></div>
                    <div style="padding: 2.5px 0;"><span class="meta-label">Shift :</span> <span class="meta-value"><?= htmlspecialchars($incident['shift']) ?></span></div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Main Incident Form Box -->
    <table class="main-form-table">
        <!-- Row 1: Priority Header Row (Deep Navy Blue Bar with White Text and White Checkboxes) -->
        <tr class="priority-navy-row">
            <td style="width: 16%; background: #002266 !important; border-right: 1px solid #000;"></td>
            <td style="background: #002266 !important; padding: 5px 12px !important;">
                <div style="display: flex; align-items: center; justify-content: space-around; width: 100%;">
                    <span class="chk-item-white"><?= render_priority_pdf_box($incident['priority'] === 'Critical') ?> Critical</span>
                    <span class="chk-item-white"><?= render_priority_pdf_box($incident['priority'] === 'High') ?> High</span>
                    <span class="chk-item-white"><?= render_priority_pdf_box($incident['priority'] === 'Medium') ?> Medium</span>
                    <span class="chk-item-white"><?= render_priority_pdf_box($incident['priority'] === 'Low') ?> Low</span>
                </div>
            </td>
        </tr>

        <!-- Row 2: Disciplinary (left) + Disciplinary Checkboxes (right) -->
        <tr>
            <td class="col-side-label">
                Disciplinary
            </td>
            <td class="col-content-box">
                <!-- Sub-row 1 -->
                <div class="row-flex" style="margin-bottom: 7.5px;">
                    <?php foreach ($disciplinaryList[0] as $dItem): ?>
                        <span class="chk-item">
                            <?= render_pdf_box(in_array($dItem, $discReasons)) ?>
                            <span><?= htmlspecialchars($dItem) ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
                <!-- Sub-row 2 -->
                <div class="row-flex">
                    <?php foreach ($disciplinaryList[1] as $dItem): ?>
                        <span class="chk-item">
                            <?= render_pdf_box(in_array($dItem, $discReasons)) ?>
                            <span><?= htmlspecialchars($dItem) ?></span>
                        </span>
                    <?php endforeach; ?>
                    <span class="chk-item"></span>
                </div>
            </td>
        </tr>

        <!-- Row 3: Incident (left) + Incident Checkboxes (right) -->
        <tr>
            <td class="col-side-label">
                Incident
            </td>
            <td class="col-content-box">
                <?php foreach ($incidentList as $rIdx => $rowItems): ?>
                    <div class="row-flex" <?= $rIdx < 2 ? 'style="margin-bottom: 7.5px;"' : '' ?>>
                        <?php foreach ($rowItems as $iItem): ?>
                            <span class="chk-item-4">
                                <?= render_pdf_box(in_array($iItem, $incTypes)) ?>
                                <span><?= htmlspecialchars($iItem) ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </td>
        </tr>

        <!-- Row 4: (Explain What, Where, When, Who, Why and How action taken) -->
        <tr class="navy-bar-italic">
            <td style="width: 16%; background: #002266 !important; border-right: 1px solid #000;"></td>
            <td style="background: #002266 !important;">
                (Explain What, Where, When, Who, Why and How action taken)
            </td>
        </tr>
        <tr>
            <td style="width: 16%; border-right: 1px solid #000;"></td>
            <td class="summary-line-box">
                <?= nl2br(htmlspecialchars($incident['summary_line'] ?: '')) ?>
            </td>
        </tr>

        <!-- Row 5: Who Was Notified? -->
        <tr>
            <td colspan="2" class="navy-bar-italic">
                Who Was Notified?
            </td>
        </tr>
        <tr>
            <td colspan="2" class="col-content-box">
                <div class="row-flex" style="margin-bottom: 7.5px;">
                    <span class="chk-item-4"><?= render_pdf_box(in_array('Police', $notified)) ?> Police</span>
                    <span class="chk-item-4"><?= render_pdf_box(in_array('Ambulance', $notified)) ?> Ambulance</span>
                    <span class="chk-item-4"><?= render_pdf_box(in_array('Civil Defense', $notified)) ?> Civil Defense</span>
                    <span class="chk-item-4"><?= render_pdf_box(in_array('Client Management', $notified)) ?> Client Management</span>
                </div>
                <div class="row-flex">
                    <span class="chk-item-4"><?= render_pdf_box(in_array('USS Management', $notified)) ?> USS Management</span>
                    <span class="chk-item-4" style="width: 75%; display: flex; align-items: center;">
                        <?= render_pdf_box(in_array('Others', $notified) || !empty($incident['notified_other_text'])) ?> 
                        Others: <span style="margin-left: 6px; border-bottom: 1px solid #000; flex: 1; min-height: 14px; font-weight: normal;"><?= htmlspecialchars($incident['notified_other_text'] ?: '') ?></span>
                    </span>
                </div>
            </td>
        </tr>

        <!-- Row 6: Description of Incident -->
        <tr>
            <td colspan="2" class="navy-bar-italic">
                Description of Incident
            </td>
        </tr>
        <tr>
            <td colspan="2" class="description-box">
                <?= render_rich_description($incident['description']) ?>
            </td>
        </tr>

        <!-- Row 7: Site Supervisor Sign-off Line -->
        <tr>
            <td colspan="2" style="padding: 0;">
                <div class="signoff-row">
                    <span>Site Supervisor: <u><?= htmlspecialchars($incident['site_supervisor_name'] ?: '') ?></u></span>
                    <span>ID No.: <u><?= htmlspecialchars($incident['site_supervisor_id'] ?: '') ?></u></span>
                    <span>Signature: <u></u></span>
                    <span>Date: <u><?= !empty($incident['site_supervisor_date']) ? format_date($incident['site_supervisor_date']) : '' ?></u></span>
                </div>
            </td>
        </tr>

        <!-- Row 8: Recommendation / Action Taken -->
        <tr>
            <td colspan="2" class="navy-bar-italic">
                Recommendation / Action Taken
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding: 0;">
                <div class="action-box">
                    <div class="action-text">
                        <?= htmlspecialchars($incident['recommendation_action_taken'] ?: '') ?>
                    </div>
                    <div class="signoff-bottom">
                        <span>Operations Manager: <u><?= htmlspecialchars($incident['operations_manager_name'] ?: '') ?></u></span>
                        <span>Signature: <u></u></span>
                        <span>Date: <u><?= !empty($incident['operations_manager_date']) ? format_date($incident['operations_manager_date']) : '' ?></u></span>
                    </div>
                </div>
            </td>
        </tr>

        <!-- Row 9: Action Taken By Client Manager -->
        <tr>
            <td colspan="2" class="navy-bar-italic">
                Action Taken By Client Manager
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding: 0;">
                <div class="action-box">
                    <div class="action-text">
                        <?= htmlspecialchars($incident['client_manager_action_taken'] ?: '') ?>
                    </div>
                    <div class="signoff-bottom">
                        <span>Client Manager: <u><?= htmlspecialchars($incident['client_manager_name'] ?: '') ?></u></span>
                        <span>Signature: <u></u></span>
                        <span>Date: <u><?= !empty($incident['client_manager_date']) ? format_date($incident['client_manager_date']) : '' ?></u></span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Page 1 Footer -->
    <div class="pdf-footer">
        <div style="color: #0284c7; font-weight: bold; font-size: 8.5pt;">
            <?= htmlspecialchars($companyDisplayName) ?> &bull; Report Ref: <?= htmlspecialchars($formattedRef) ?>
        </div>
        <div style="color: #64748b; font-weight: bold; font-size: 8.5pt;">
            Page 1 of <?= $totalPages ?>
        </div>
    </div>

</div>

<!-- ================================================= PAGE 2+ (PHOTOS: STRICTLY 6 IMAGES PER A4 PAGE) ================================================= -->
<?php if (!empty($photoChunks)): ?>
    <?php foreach ($photoChunks as $chunkIdx => $chunkPhotos): 
        $currentPhotoPage = 2 + $chunkIdx;
    ?>
    <div class="page-sheet page-break">

        <!-- Photographic Evidence Header -->
        <div style="border: 2px solid #000; padding: 4.5px 12px; margin-bottom: 7mm; font-weight: bold; font-size: 10pt; background: #002266; color: #fff; font-style: italic;">
            Attached Photographs &amp; Evidence <?= $totalPhotoPages > 1 ? '(' . ($chunkIdx + 1) . ' of ' . $totalPhotoPages . ')' : '' ?>
        </div>

        <!-- 2-Column x 3-Row Grid (Fits exactly 6 images per A4 page) -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: repeat(3, 76mm); gap: 7mm; height: 242mm;">
            <?php foreach ($chunkPhotos as $idx => $p): 
                $globalIdx = ($chunkIdx * 6) + $idx;
                $photoSrc = get_photo_src($p['file_path'], $p['id']);
                $sizeKb = round(($p['file_size'] ?: 102400) / 1024, 1);
                $pLat = !empty($p['latitude']) ? $p['latitude'] : (!empty($incident['site_latitude']) ? $incident['site_latitude'] : '');
                $pLng = !empty($p['longitude']) ? $p['longitude'] : (!empty($incident['site_longitude']) ? $incident['site_longitude'] : '');
                $latStr = htmlspecialchars(format_coord($pLat, 'lat'));
                $lngStr = htmlspecialchars(format_coord($pLng, 'lng'));
            ?>
                <div style="border: 1px solid #cbd5e1; padding: 6px; background: #fff; border-radius: 4px; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between; height: 76mm;">
                    <!-- Photo Frame with perfect edge-to-edge fit -->
                    <div style="width: 100%; height: 53mm; overflow: hidden; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 3px; display: flex; align-items: center; justify-content: center;">
                        <img src="<?= $photoSrc ?>" alt="Evidence <?= $globalIdx + 1 ?>" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                    </div>
                    <!-- Evidence Details -->
                    <div style="margin-top: 4px; font-size: 8.5pt; line-height: 1.4;">
                        <div style="font-weight: bold; color: #000; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            Photo #<?= $globalIdx + 1 ?>: Lat: <?= $latStr ?>, Long: <?= $lngStr ?>
                        </div>
                        <div style="font-weight: bold; color: #b91c1c; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            Caption: <?= htmlspecialchars($p['caption'] ?: 'Site inspection photographic evidence') ?>
                        </div>
                        <div style="font-size: 8pt; color: #64748b; font-weight: normal; margin-top: 1px;">
                            Ref: <?= htmlspecialchars($formattedRef) ?> &bull; Size: <?= $sizeKb ?> KB
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Photo Page Footer -->
        <div class="pdf-footer" style="position: absolute; bottom: 6mm; left: 10mm; right: 10mm;">
            <div style="color: #0284c7; font-weight: bold; font-size: 8.5pt;">
                <?= htmlspecialchars($companyDisplayName) ?> &bull; Report Ref: <?= htmlspecialchars($formattedRef) ?>
            </div>
            <div style="color: #64748b; font-weight: bold; font-size: 8.5pt;">
                Page <?= $currentPhotoPage ?> of <?= $totalPages ?>
            </div>
        </div>

    </div>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
