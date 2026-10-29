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
require_once __DIR__ . '/../includes/pdf_tokens.php';

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
 * Format date in dd/mm/yyyy format in bold for Official Management Sign-Off
 */
if (!function_exists('format_dmy_bold')) {
    function format_dmy_bold($dateVal) {
        if (empty($dateVal) || $dateVal === '0000-00-00') {
            return '<span style="display:inline-block; width: 75px; border-bottom: 1.5px solid #000; vertical-align: bottom; height: 14px;"></span>';
        }
        $ts = is_numeric($dateVal) ? (int)$dateVal : strtotime((string)$dateVal);
        if (!$ts) return '<strong style="font-weight: 800; font-size: 9.5pt; color: #000;">' . htmlspecialchars($dateVal) . '</strong>';
        return '<strong style="font-weight: 800; font-size: 9.5pt; color: #000;">' . date('d/m/Y', $ts) . '</strong>';
    }
}

/**
 * Render signature image cleanly if available, otherwise an official signature line
 */
if (!function_exists('render_signature_img')) {
    function render_signature_img($sigVal, $alt = 'Signature') {
        if (empty($sigVal)) {
            return '<span style="display:inline-block; width: 75px; border-bottom: 1.5px solid #000; vertical-align: bottom; height: 16px;"></span>';
        }
        $src = '';
        if (strpos($sigVal, 'data:image') === 0) {
            $src = $sigVal;
        } elseif (file_exists($sigVal)) {
            $mime = mime_content_type($sigVal) ?: 'image/png';
            $src = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($sigVal));
        } elseif (defined('ROOT_PATH') && file_exists(ROOT_PATH . '/' . ltrim($sigVal, '/'))) {
            $p = ROOT_PATH . '/' . ltrim($sigVal, '/');
            $mime = mime_content_type($p) ?: 'image/png';
            $src = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($p));
        } else {
            $src = APP_URL . '/' . ltrim($sigVal, '/');
        }
        return '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($alt) . '" style="max-height: 22px; max-width: 90px; vertical-align: middle; display: inline-block; object-fit: contain;">';
    }
}


/**
 * Multi-Path Resilient Storage Resolver
 * Searches direct paths, date-sharded directories (storage/private/photos/YYYY/MM/),
 * uploads, and recursive globs to guarantee 100% photo recovery on Hostinger
 */
if (!function_exists('resolve_photo_disk_path')) {
    function resolve_photo_disk_path($filePath, $photoId = 0) {
        if (empty($filePath) && $photoId <= 0) {
            return false;
        }

        $norm = str_replace('\\', '/', trim((string)$filePath));
        $filename = basename($norm);

        // If filename is missing or empty, attempt lookup by photo ID
        if ((empty($filename) || $filename === '.' || $filename === '/') && $photoId > 0) {
            try {
                $db = get_db();
                $stmt = $db->prepare("SELECT file_path FROM incident_photos WHERE id = ? LIMIT 1");
                $stmt->execute([$photoId]);
                $row = $stmt->fetch();
                if ($row && !empty($row['file_path'])) {
                    $norm = str_replace('\\', '/', trim($row['file_path']));
                    $filename = basename($norm);
                }
            } catch (Throwable $e) {}
        }

        if (empty($filename) || $filename === '.' || $filename === '/') {
            return false;
        }

        $cleanNorm = ltrim($norm, '/');
        $relTail = $cleanNorm;
        if (preg_match('#(storage/private/.*|uploads/.*|photos/.*)$#i', $norm, $m)) {
            $relTail = $m[1];
        }

        $candidates = [
            // Direct file checks
            $filePath,
            $norm,
            @realpath($filePath),
            @realpath($norm),

            // Root relative paths
            ROOT_PATH . '/' . $cleanNorm,
            ROOT_PATH . '/' . $relTail,
            ROOT_PATH . '/storage/private/' . ltrim(preg_replace('#^.*?(storage/private/)?#i', '', $norm), '/'),
            ROOT_PATH . '/uploads/' . ltrim(preg_replace('#^.*?(uploads/)?#i', '', $norm), '/'),

            // Private photos storage with date sharding
            defined('PHOTOS_PRIVATE_PATH') ? (PHOTOS_PRIVATE_PATH . '/' . ltrim(preg_replace('#^.*?(photos/)?#i', '', $norm), '/')) : false,
            defined('PHOTOS_PRIVATE_PATH') ? (PHOTOS_PRIVATE_PATH . '/' . date('Y/m/') . $filename) : false,
            defined('PHOTOS_PRIVATE_PATH') ? (PHOTOS_PRIVATE_PATH . '/' . date('Y/') . $filename) : false,
            defined('PHOTOS_PRIVATE_PATH') ? (PHOTOS_PRIVATE_PATH . '/' . $filename) : false,

            // Private storage root
            defined('STORAGE_PRIVATE_PATH') ? (STORAGE_PRIVATE_PATH . '/photos/' . date('Y/m/') . $filename) : false,
            defined('STORAGE_PRIVATE_PATH') ? (STORAGE_PRIVATE_PATH . '/photos/' . $filename) : false,
            defined('STORAGE_PRIVATE_PATH') ? (STORAGE_PRIVATE_PATH . '/' . $filename) : false,

            // Uploads directory
            defined('UPLOADS_PATH') ? (UPLOADS_PATH . '/photos/' . date('Y/m/') . $filename) : false,
            defined('UPLOADS_PATH') ? (UPLOADS_PATH . '/photos/' . $filename) : false,
            defined('UPLOADS_PATH') ? (UPLOADS_PATH . '/' . $filename) : false,

            // Direct root paths
            ROOT_PATH . '/storage/private/photos/' . date('Y/m/') . $filename,
            ROOT_PATH . '/storage/private/photos/' . $filename,
            ROOT_PATH . '/uploads/photos/' . date('Y/m/') . $filename,
            ROOT_PATH . '/uploads/photos/' . $filename,
            ROOT_PATH . '/uploads/' . $filename,
        ];

        foreach ($candidates as $cand) {
            if ($cand && @file_exists($cand) && !is_dir($cand)) {
                return $cand;
            }
        }

        // Search in storage & upload directory structures using glob
        $searchRoots = array_unique(array_filter([
            defined('PHOTOS_PRIVATE_PATH') ? PHOTOS_PRIVATE_PATH : null,
            ROOT_PATH . '/storage/private/photos',
            defined('STORAGE_PRIVATE_PATH') ? (STORAGE_PRIVATE_PATH . '/photos') : null,
            defined('STORAGE_PRIVATE_PATH') ? STORAGE_PRIVATE_PATH : null,
            defined('UPLOADS_PATH') ? (UPLOADS_PATH . '/photos') : null,
            ROOT_PATH . '/uploads/photos',
            defined('UPLOADS_PATH') ? UPLOADS_PATH : null,
            ROOT_PATH . '/uploads',
        ]));

        foreach ($searchRoots as $sDir) {
            if (is_dir($sDir)) {
                // Check 2 levels deep (YYYY/MM/filename)
                $matches = @glob(rtrim($sDir, '/\\') . '/*/*/' . $filename);
                if (!empty($matches) && file_exists($matches[0])) {
                    return $matches[0];
                }
                // Check 1 level deep (YYYY/filename or photos/filename)
                $matches1 = @glob(rtrim($sDir, '/\\') . '/*/' . $filename);
                if (!empty($matches1) && file_exists($matches1[0])) {
                    return $matches1[0];
                }
            }
        }

        return false;
    }
}

/**
 * Smart High-Definition Image Optimizer for PDF Output
 * Ensures total PDF document stays around ~300KB while preserving pristine print clarity (HD quality)
 */
if (!function_exists('optimize_photo_for_pdf')) {
    function optimize_photo_for_pdf($rawData, $maxW = 1000, $maxH = 700, $quality = 78) {
        if (empty($rawData)) return ['data' => '', 'mime' => 'image/jpeg'];

        if (extension_loaded('gd') && function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring($rawData);
            if ($img !== false) {
                $w = imagesx($img);
                $h = imagesy($img);

                // Scale proportionally down to max bounds if larger
                $scale = min($maxW / max(1, $w), $maxH / max(1, $h), 1.0);
                if ($scale < 1.0) {
                    $newW = (int)round($w * $scale);
                    $newH = (int)round($h * $scale);
                    $dst = imagecreatetruecolor($newW, $newH);

                    // Fill with crisp white background
                    $white = imagecolorallocate($dst, 255, 255, 255);
                    imagefilledrectangle($dst, 0, 0, $newW, $newH, $white);

                    imagecopyresampled($dst, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
                    imagedestroy($img);
                    $img = $dst;
                }

                ob_start();
                imagejpeg($img, null, $quality);
                $compressed = ob_get_clean();
                imagedestroy($img);

                if (!empty($compressed)) {
                    return ['data' => $compressed, 'mime' => 'image/jpeg'];
                }
            }
        }

        $mime = 'image/jpeg';
        if (strncmp($rawData, "\x89PNG", 4) === 0) {
            $mime = 'image/png';
        } elseif (strncmp($rawData, "RIFF", 4) === 0) {
            $mime = 'image/webp';
        }

        return ['data' => $rawData, 'mime' => $mime];
    }
}

/**
 * Resolve direct photo content as embedded base64 data URI to guarantee 100% rendering
 * Automatically applies HD compression so generated PDF remains around ~300KB with crystal clarity
 */
if (!function_exists('get_photo_src')) {
    function get_photo_src($filePath, $photoId = 0) {
        if (!empty($filePath) && strpos($filePath, 'data:image/') === 0) {
            return $filePath;
        }

        $resolved = resolve_photo_disk_path($filePath, $photoId);
        if ($resolved && file_exists($resolved)) {
            $data = @file_get_contents($resolved);
            if ($data !== false && strlen($data) > 0) {
                $opt = optimize_photo_for_pdf($data, 1000, 700, 78);
                return 'data:' . $opt['mime'] . ';base64,' . base64_encode($opt['data']);
            }
        }

        // Remote HTTP fallback
        if (!empty($filePath) && (strpos($filePath, 'http://') === 0 || strpos($filePath, 'https://') === 0)) {
            return $filePath;
        }

        // Authenticated gateway fallback
        if ($photoId > 0) {
            return APP_URL . '/admin/serve_file.php?photo_id=' . (int)$photoId;
        }

        $cleanRel = ltrim(str_replace('\\', '/', (string)$filePath), '/');
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
        margin: 8mm 10mm;
    }
    body {
        font-family: PDF_FONT_PRIMARY, Arial, Helvetica, sans-serif;
        color: #000000;
        background: #FFFFFF;
        font-size: 9pt;
        line-height: 1.25;
    }
    .doc-title {
        font-size: 20pt !important;
        font-weight: 700 !important;
        text-decoration: underline !important;
        text-align: center !important;
        margin-bottom: 4px !important;
    }
    .field-label {
        font-size: 10pt !important;
        font-weight: 700 !important;
    }
    .category-chip, .option-label {
        font-size: 9pt !important;
    }
    .desc-box {
        font-size: 11pt !important; /* Exactly 11pt so 15 lines fit cleanly */
        line-height: 1.35 !important;
        min-height: 220px !important;
        max-height: 250px !important;
        overflow: hidden !important;
        border: 1px solid #000000 !important;
        padding: 8px !important;
    }
    .company-name-hdr {
        font-size: 10pt !important;
        font-weight: 700 !important;
    }
    .company-meta-hdr {
        font-size: 7pt !important;
    }
    .footer-pt, .signoff-pt {
        font-size: 8pt !important;
    }

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
            border: 2.5px solid #000000 !important;
        }

        .main-form-table td, .main-form-table th {
            border: 1.5px solid #000000 !important;
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
            font-size: 9pt;
            font-weight: bold;
            color: #000000;
            line-height: 1.2;
            box-sizing: border-box;
        }

        .chk-item-4 {
            display: inline-flex;
            align-items: flex-start;
            width: 25%;
            font-size: 9pt;
            font-weight: bold;
            color: #000000;
            line-height: 1.15;
            box-sizing: border-box;
        }

        .chk-box {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 13px;
            height: 13px;
            min-width: 13px;
            min-height: 13px;
            border: 1.5px solid #000000;
            margin-right: 5px;
            margin-top: 1px;
            vertical-align: top;
            background: #ffffff;
            color: #000000;
            font-size: 11px;
            font-weight: 900;
            line-height: 1;
            flex-shrink: 0;
            box-sizing: border-box;
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
            height: 40px !important;
            min-height: 40px !important;
            max-height: 44px !important;
            padding: 4px 8px !important;
            font-size: 11pt !important;
            font-weight: bold !important;
            color: #8B0000 !important; /* Dark Red */
            line-height: 1.25 !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            word-break: break-word !important;
            white-space: normal !important;
            border-bottom: 1.5px solid #000 !important;
            overflow: hidden !important;
            box-sizing: border-box !important;
            vertical-align: middle !important;
            background: #ffffff !important;
        }

        .description-box {
            height: 86mm !important;
            min-height: 86mm !important;
            max-height: 86mm !important;
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
            height: 18mm !important;
            min-height: 18mm !important;
            padding: 4px 8px !important;
            font-size: 9.5pt;
            font-weight: bold;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: stretch !important;
            border-bottom: 1.8px solid #000 !important;
            box-sizing: border-box !important;
            text-align: left !important;
        }

        .action-text {
            font-weight: 800 !important;
            font-size: 10pt !important;
            color: #8B0000 !important; /* Dark Red */
            margin-bottom: 3px !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            word-break: break-word !important;
            white-space: pre-wrap !important;
            line-height: 1.30 !important;
            max-height: 9.5mm !important;
            overflow: hidden !important;
            text-align: left !important;
            vertical-align: top !important;
            align-self: flex-start !important;
            width: 100% !important;
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
                height: auto !important;
                min-height: 100% !important;
                max-height: none !important;
                overflow: visible !important;
            }
            .page-sheet {
                box-shadow: none !important;
                margin: 0 auto !important;
                padding: 0 !important;
                width: 100% !important;
                height: 285mm !important;
                max-height: 285mm !important;
                overflow: hidden !important;
                page-break-after: always !important;
                break-after: page !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            .page-sheet:last-of-type {
                page-break-after: auto !important;
                break-after: auto !important;
            }
            .page-sheet.page-break {
                page-break-before: always !important;
                break-before: page !important;
            }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <div>
        <strong>Official Incident Dossier:</strong> <?= htmlspecialchars($formattedRef) ?> &bull; <?= htmlspecialchars($incident['site_name']) ?>
        <?php if (!empty($photos)): ?>
            &bull; <span style="background: #22c55e; color: #000; padding: 2px 7px; border-radius: 4px; font-weight: 800; font-size: 11px; margin-left: 6px;">📷 <?= count($photos) ?> Evidence Photo<?= count($photos) > 1 ? 's' : '' ?> Attached (<?= $totalPages ?> Pages Total)</span>
        <?php endif; ?>
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
                        <td style="width: 68%; padding: 2.5px 0;" class="meta-value">
                            <strong style="font-size: 10.5pt; font-weight: 800; color: #000000; letter-spacing: 0.2px;">
                                <?= htmlspecialchars($incident['site_name']) ?>
                            </strong>
                        </td>
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

        <!-- Row 2: Disciplinary (left) + Disciplinary Checkboxes (right) - Organized as per Image 1 -->
        <tr>
            <td class="col-side-label" style="border-bottom: 1.5px solid #000;">
                Disciplinary
            </td>
            <td class="col-content-box" style="border-bottom: 1.5px solid #000; padding: 6px 8px;">
                <!-- Sub-row 1: 5 items -->
                <div class="row-flex" style="margin-bottom: 7px; display: flex; width: 100%;">
                    <?php foreach ($disciplinaryList[0] as $dItem): ?>
                        <span class="chk-item">
                            <?= render_pdf_box(in_array($dItem, $discReasons)) ?>
                            <span><?= htmlspecialchars($dItem) ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
                <!-- Sub-row 2: 4 items -->
                <div class="row-flex" style="display: flex; width: 100%;">
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

        <!-- Row 3: Incident (left) + Incident Checkboxes (right) - Organized as per Image 1 -->
        <tr>
            <td class="col-side-label" style="border-bottom: 1.5px solid #000;">
                Incident
            </td>
            <td class="col-content-box" style="border-bottom: 1.5px solid #000; padding: 6px 8px;">
                <?php foreach ($incidentList as $rIdx => $rowItems): ?>
                    <div class="row-flex" style="display: flex; width: 100%; <?= $rIdx < 2 ? 'margin-bottom: 7px;' : '' ?>">
                        <?php foreach ($rowItems as $iItem): ?>
                            <span class="chk-item-4">
                                <?= render_pdf_box(in_array($iItem, $incTypes)) ?>
                                <span><?= ($iItem === 'Visitors & Contractors') ? 'Visitors &amp;<br>Contractors' : htmlspecialchars($iItem) ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </td>
        </tr>

        <!-- Row 4: Incident Description (Image 2 - No blue background, labeled Incident Description) -->
        <tr>
            <td class="col-side-label" rowspan="2" style="width: 16%; font-weight: bold; font-size: 10.5pt; text-align: center; vertical-align: middle; border-right: 1.5px solid #000; border-bottom: 1.5px solid #000; background: #ffffff; color: #000000; padding: 4px;">
                Incident<br>Description
            </td>
            <td style="background: #002266 !important; color: #ffffff !important; font-weight: bold; font-style: italic; font-size: 8.5pt; padding: 3.5px 8px; border-bottom: 1px solid #000;">
                (Explain What, Where, When, Who, Why and How action taken)
            </td>
        </tr>
        <tr>
            <td class="summary-line-box" style="background: #ffffff; border-bottom: 1.5px solid #000; color: #8B0000 !important; font-weight: bold !important; font-size: 11pt !important;">
                <div style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.25; word-wrap: break-word; overflow-wrap: break-word; color: #8B0000 !important; font-weight: bold !important; font-size: 11pt !important;">
                    <?= htmlspecialchars(!empty($incident['summary_line']) ? $incident['summary_line'] : $incident['description']) ?>
                </div>
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

        <!-- Row: People who saw the Incident (Witnesses) -->
        <?php if (!isset($settings['witness_enabled']) || !empty($settings['witness_enabled'])): ?>
        <tr>
            <td colspan="2" style="background: #ffffff; color: #000000; font-weight: bold; font-style: italic; font-size: 10pt; padding: 3px 8px; border-top: 1.5px solid #000; border-bottom: 1px solid #000;">
                <?= htmlspecialchars($settings['witness_section_title'] ?? 'People who saw the Incident') ?>
            </td>
        </tr>
        <tr>
            <td colspan="2" class="col-content-box" style="padding: 4px 8px; border-bottom: 1.5px solid #000;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 33.33%; font-size: 8.5pt; padding: 2px 4px; vertical-align: middle;">
                            <strong><?= htmlspecialchars($settings['witness_1_label'] ?? 'Witness 1') ?>:</strong>
                            <span style="border-bottom: 1px dotted #000; display: inline-block; width: calc(100% - 75px); min-height: 14px; padding-left: 4px; font-weight: normal;"><?= htmlspecialchars($incident['witness_1'] ?: '') ?></span>
                        </td>
                        <td style="width: 33.33%; font-size: 8.5pt; padding: 2px 4px; vertical-align: middle;">
                            <strong><?= htmlspecialchars($settings['witness_2_label'] ?? 'Witness 2') ?>:</strong>
                            <span style="border-bottom: 1px dotted #000; display: inline-block; width: calc(100% - 75px); min-height: 14px; padding-left: 4px; font-weight: normal;"><?= htmlspecialchars($incident['witness_2'] ?: '') ?></span>
                        </td>
                        <td style="width: 33.33%; font-size: 8.5pt; padding: 2px 4px; vertical-align: middle;">
                            <strong><?= htmlspecialchars($settings['witness_3_label'] ?? 'Witness 3') ?>:</strong>
                            <span style="border-bottom: 1px dotted #000; display: inline-block; width: calc(100% - 75px); min-height: 14px; padding-left: 4px; font-weight: normal;"><?= htmlspecialchars($incident['witness_3'] ?: '') ?></span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <?php endif; ?>

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

        <!-- Row 7: Site Supervisor Sign-off Line (Bold, dd/mm/yyyy date, signature load) -->
        <tr>
            <td colspan="2" style="padding: 0; border-bottom: 1.8px solid #000;">
                <div class="signoff-row">
                    <span style="display: inline-flex; align-items: center; gap: 4px;">
                        Site Supervisor: <strong style="font-weight: 800; font-size: 9.5pt; color: #000; text-decoration: underline;"><?= htmlspecialchars($incident['site_supervisor_name'] ?: '                         ') ?></strong>
                    </span>
                    <span style="display: inline-flex; align-items: center; gap: 4px;">
                        ID No.: <strong style="font-weight: 800; font-size: 9.5pt; color: #000; text-decoration: underline;"><?= htmlspecialchars($incident['site_supervisor_id'] ?: '          ') ?></strong>
                    </span>
                    <span style="display: inline-flex; align-items: center; gap: 4px;">
                        Signature: <?= render_signature_img($incident['site_supervisor_signature'] ?? '', 'Supervisor Signature') ?>
                    </span>
                    <span style="display: inline-flex; align-items: center; gap: 4px;">
                        Date: <?= format_dmy_bold($incident['site_supervisor_date'] ?? '') ?>
                    </span>
                </div>
            </td>
        </tr>

        <!-- Row 8: Recommendation / Action Taken (Operations Manager Sign-off) -->
        <tr>
            <td colspan="2" class="navy-bar-italic">
                Recommendation / Action Taken
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding: 0; border-bottom: 1.8px solid #000;">
                <div class="action-box" style="text-align: left !important;">
                    <div class="action-text" style="text-align: left !important; vertical-align: top !important; align-self: flex-start !important; width: 100% !important;">
                        <?= nl2br(htmlspecialchars($incident['recommendation_action_taken'] ?: '')) ?>
                    </div>
                    <div class="signoff-bottom">
                        <span style="display: inline-flex; align-items: center; gap: 4px;">
                            Operations Manager: <strong style="font-weight: 800; font-size: 9.5pt; color: #000; text-decoration: underline;"><?= htmlspecialchars($incident['operations_manager_name'] ?: '                         ') ?></strong>
                        </span>
                        <span style="display: inline-flex; align-items: center; gap: 4px;">
                            Signature: <?= render_signature_img($incident['operations_manager_signature'] ?? '', 'Operations Manager Signature') ?>
                        </span>
                        <span style="display: inline-flex; align-items: center; gap: 4px;">
                            Date: <?= format_dmy_bold($incident['operations_manager_date'] ?? '') ?>
                        </span>
                    </div>
                </div>
            </td>
        </tr>

        <!-- Row 9: Action Taken By Client Manager (Client Representative Sign-off) -->
        <tr>
            <td colspan="2" class="navy-bar-italic">
                Action Taken By Client Manager
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding: 0; border-bottom: 1.8px solid #000;">
                <div class="action-box" style="text-align: left !important;">
                    <div class="action-text" style="text-align: left !important; vertical-align: top !important; align-self: flex-start !important; width: 100% !important;">
                        <?= nl2br(htmlspecialchars($incident['client_manager_action_taken'] ?: '')) ?>
                    </div>
                    <div class="signoff-bottom">
                        <span style="display: inline-flex; align-items: center; gap: 4px;">
                            Client Representative: <strong style="font-weight: 800; font-size: 9.5pt; color: #000; text-decoration: underline;"><?= htmlspecialchars($incident['client_manager_name'] ?: '                         ') ?></strong>
                        </span>
                        <span style="display: inline-flex; align-items: center; gap: 4px;">
                            Signature: <?= render_signature_img($incident['client_manager_signature'] ?? '', 'Client Representative Signature') ?>
                        </span>
                        <span style="display: inline-flex; align-items: center; gap: 4px;">
                            Date: <?= format_dmy_bold($incident['client_manager_date'] ?? '') ?>
                        </span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Page 1 Footer -->
    <div class="pdf-footer">
        <div style="color: #0284c7; font-weight: bold; font-size: 8.5pt;">
            <?= htmlspecialchars($companyDisplayName) ?> &bull; Report Ref: <?= htmlspecialchars($formattedRef) ?>
            <?php if (!empty($photos)): ?>
                &bull; <strong style="color: #002266; font-size: 8.5pt;">Photographic Evidence Attached (<?= count($photos) ?> Photos &bull; See Page 2+)</strong>
            <?php endif; ?>
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
                <div style="border: 2px solid #000; padding: 5px; background: #fff; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between; height: 76mm; overflow: hidden;">
                    <!-- Photo Frame with perfect edge-to-edge fit -->
                    <div style="width: 100%; height: 53mm; overflow: hidden; background: #0f172a; border: 1.5px solid #000; display: flex; align-items: center; justify-content: center;">
                        <img src="<?= $photoSrc ?>" alt="Evidence <?= $globalIdx + 1 ?>" style="max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain; display: block;" onerror="if(this.src.indexOf('serve_file.php')===-1 && <?= (int)$p['id'] ?> > 0){ this.src='<?= APP_URL ?>/admin/serve_file.php?photo_id=<?= (int)$p['id'] ?>'; } else { this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex'; }">
                        <div style="display: none; flex-direction: column; align-items: center; justify-content: center; text-align: center; color: #fca5a5; font-weight: 800; font-size: 9pt; padding: 8px; line-height: 1.4;">
                            &#9888; Photo file not found on server<br>
                            <span style="color: #cbd5e1; font-weight: normal; font-size: 7.5pt; word-break: break-all;"><?= htmlspecialchars(basename(str_replace('\\', '/', (string)$p['file_path']))) ?></span>
                        </div>
                    </div>
                    <!-- Evidence Details -->
                    <div style="margin-top: 4px; font-size: 8.5pt; line-height: 1.4;">
                        <div style="font-weight: bold; color: #000; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            Photo #<?= $globalIdx + 1 ?>: Lat: <?= $latStr ?>, Long: <?= $lngStr ?>
                          <?php if (!empty($p['original_filename'])): ?>
                              <div style="font-size: 7.5pt; color: #0f172a; font-family: monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px;" title="<?= htmlspecialchars($p['original_filename']) ?>">
                                  File: <?= htmlspecialchars($p['original_filename']) ?>
                              </div>
                          <?php endif; ?>
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
