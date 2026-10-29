<?php
/**
 * Sitewise Daily Security Incident Consolidated Report (Official A4 Printable PDF)
 * Ultimate Security Services (USS)
 * Consolidated executive dossier aggregating all site incidents for a selected date
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/pdf_tokens.php';

@ini_set('memory_limit', '512M');
@set_time_limit(300);
require_admin_auth();

$db = get_db();
$settings = get_system_settings();

// Parameters
$siteId = (int)($_GET['site_id'] ?? 0);
$selectedDate = trim($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

// Fetch all active sites for selector
$allSites = $db->query("SELECT id, name, code, type, address, client_name, client_contact, latitude, longitude FROM sites WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

if ($siteId <= 0 && !empty($allSites)) {
    $siteId = (int)$allSites[0]['id'];
}

// Fetch current site
$siteStmt = $db->prepare("SELECT * FROM sites WHERE id = ? LIMIT 1");
$siteStmt->execute([$siteId]);
$site = $siteStmt->fetch();

if (!$site && !empty($allSites)) {
    $site = $allSites[0];
    $siteId = (int)$site['id'];
} elseif (!$site) {
    die("No active sites available. <a href='daily_report.php'>Return to Daily Reports</a>");
}

// Fetch all incidents for this site on this date
$incStmt = $db->prepare("
    SELECT i.*, 
           g.staff_id as guard_staff_id, g.designation as guard_designation
    FROM incidents i
    LEFT JOIN guards g ON g.id = i.guard_id
    WHERE i.site_id = ? AND i.incident_date = ? AND i.deleted_at IS NULL
    ORDER BY i.incident_time ASC, i.id ASC
");
$incStmt->execute([$siteId, $selectedDate]);
$incidents = $incStmt->fetchAll();

// Fetch photos for each incident
$incidentPhotos = [];
if (!empty($incidents)) {
    $incIds = array_column($incidents, 'id');
    $placeholders = implode(',', array_fill(0, count($incIds), '?'));
    $pStmt = $db->prepare("SELECT * FROM incident_photos WHERE incident_id IN ($placeholders) ORDER BY sort_order ASC, id ASC");
    $pStmt->execute($incIds);
    $allPhotos = $pStmt->fetchAll();
    foreach ($allPhotos as $p) {
        $incidentPhotos[$p['incident_id']][] = $p;
    }
}

// Aggregate metrics
$totalIncidents = count($incidents);
$criticalCount = 0;
$highCount = 0;
$mediumCount = 0;
$lowCount = 0;
$dayShiftCount = 0;
$nightShiftCount = 0;
$resolvedCount = 0;
$underReviewCount = 0;

foreach ($incidents as $inc) {
    if ($inc['priority'] === 'Critical') $criticalCount++;
    elseif ($inc['priority'] === 'High') $highCount++;
    elseif ($inc['priority'] === 'Medium') $mediumCount++;
    elseif ($inc['priority'] === 'Low') $lowCount++;

    if (strtolower($inc['shift']) === 'day') $dayShiftCount++;
    else $nightShiftCount++;

    if (in_array($inc['status'], ['Resolved', 'Submitted to Client'])) $resolvedCount++;
    else $underReviewCount++;
}

// Format coordinates helper
if (!function_exists('format_coord_val')) {
    function format_coord_val($val, $type = 'lat') {
        $val = trim((string)$val);
        if ($val === '') {
            return $type === 'lat' ? '25.1610° N' : '55.4120° E';
        }
        if (preg_match('/[NSEWnsew]/', $val) || strpos($val, '°') !== false) {
            return $val;
        }
        if (is_numeric($val)) {
            $num = (float)$val;
            return abs($num) . '° ' . ($type === 'lat' ? ($num >= 0 ? 'N' : 'S') : ($num >= 0 ? 'E' : 'W'));
        }
        return $val;
    }
}

if (!function_exists('resolve_photo_disk_path')) {
    function resolve_photo_disk_path($filePath, $photoId = 0) {
        if (empty($filePath) && $photoId <= 0) return false;
        $norm = str_replace('\\', '/', trim((string)$filePath));
        $filename = basename($norm);

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
        if (empty($filename) || $filename === '.' || $filename === '/') return false;

        $cleanNorm = ltrim($norm, '/');
        $relTail = $cleanNorm;
        if (preg_match('#(storage/private/.*|uploads/.*|photos/.*)$#i', $norm, $m)) {
            $relTail = $m[1];
        }

        $candidates = [
            $filePath, $norm, @realpath($filePath), @realpath($norm),
            ROOT_PATH . '/' . $cleanNorm,
            ROOT_PATH . '/' . $relTail,
            ROOT_PATH . '/storage/private/' . ltrim(preg_replace('#^.*?(storage/private/)?#i', '', $norm), '/'),
            ROOT_PATH . '/uploads/' . ltrim(preg_replace('#^.*?(uploads/)?#i', '', $norm), '/'),
            defined('PHOTOS_PRIVATE_PATH') ? (PHOTOS_PRIVATE_PATH . '/' . ltrim(preg_replace('#^.*?(photos/)?#i', '', $norm), '/')) : false,
            defined('PHOTOS_PRIVATE_PATH') ? (PHOTOS_PRIVATE_PATH . '/' . date('Y/m/') . $filename) : false,
            defined('PHOTOS_PRIVATE_PATH') ? (PHOTOS_PRIVATE_PATH . '/' . $filename) : false,
            defined('STORAGE_PRIVATE_PATH') ? (STORAGE_PRIVATE_PATH . '/photos/' . date('Y/m/') . $filename) : false,
            defined('STORAGE_PRIVATE_PATH') ? (STORAGE_PRIVATE_PATH . '/photos/' . $filename) : false,
            defined('UPLOADS_PATH') ? (UPLOADS_PATH . '/photos/' . date('Y/m/') . $filename) : false,
            defined('UPLOADS_PATH') ? (UPLOADS_PATH . '/photos/' . $filename) : false,
            ROOT_PATH . '/storage/private/photos/' . date('Y/m/') . $filename,
            ROOT_PATH . '/storage/private/photos/' . $filename,
            ROOT_PATH . '/uploads/photos/' . date('Y/m/') . $filename,
            ROOT_PATH . '/uploads/photos/' . $filename,
        ];

        foreach ($candidates as $cand) {
            if ($cand && @file_exists($cand) && !is_dir($cand)) return $cand;
        }

        $searchRoots = array_unique(array_filter([
            defined('PHOTOS_PRIVATE_PATH') ? PHOTOS_PRIVATE_PATH : null,
            ROOT_PATH . '/storage/private/photos',
            defined('STORAGE_PRIVATE_PATH') ? (STORAGE_PRIVATE_PATH . '/photos') : null,
            defined('UPLOADS_PATH') ? (UPLOADS_PATH . '/photos') : null,
            ROOT_PATH . '/uploads/photos',
        ]));

        foreach ($searchRoots as $sDir) {
            if (is_dir($sDir)) {
                $matches = @glob(rtrim($sDir, '/\\') . '/*/*/' . $filename);
                if (!empty($matches) && file_exists($matches[0])) return $matches[0];
                $matches1 = @glob(rtrim($sDir, '/\\') . '/*/' . $filename);
                if (!empty($matches1) && file_exists($matches1[0])) return $matches1[0];
            }
        }
        return false;
    }
}

if (!function_exists('optimize_photo_for_pdf')) {
    function optimize_photo_for_pdf($rawData, $maxW = 600, $maxH = 450, $quality = 75) {
        if (empty($rawData)) return ['data' => '', 'mime' => 'image/jpeg'];
        if (extension_loaded('gd') && function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring($rawData);
            if ($img !== false) {
                $w = imagesx($img);
                $h = imagesy($img);
                $scale = min($maxW / max(1, $w), $maxH / max(1, $h), 1.0);
                if ($scale < 1.0) {
                    $newW = (int)round($w * $scale);
                    $newH = (int)round($h * $scale);
                    $dst = imagecreatetruecolor($newW, $newH);
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
                if (!empty($compressed)) return ['data' => $compressed, 'mime' => 'image/jpeg'];
            }
        }
        $mime = 'image/jpeg';
        if (strncmp($rawData, "\x89PNG", 4) === 0) $mime = 'image/png';
        elseif (strncmp($rawData, "RIFF", 4) === 0) $mime = 'image/webp';
        return ['data' => $rawData, 'mime' => $mime];
    }
}

// Base64 image resolver for photos
if (!function_exists('get_consolidated_photo_src')) {
    function get_consolidated_photo_src($filePath, $photoId = 0) {
        if (!empty($filePath) && strpos($filePath, 'data:image/') === 0) return $filePath;
        $resolved = resolve_photo_disk_path($filePath, $photoId);
        if ($resolved && file_exists($resolved)) {
            $data = @file_get_contents($resolved);
            if ($data !== false && strlen($data) > 0) {
                $opt = optimize_photo_for_pdf($data, 600, 450, 75);
                return 'data:' . $opt['mime'] . ';base64,' . base64_encode($opt['data']);
            }
        }
        if (!empty($filePath) && (strpos($filePath, 'http://') === 0 || strpos($filePath, 'https://') === 0)) {
            return $filePath;
        }
        if ($photoId > 0) {
            return APP_URL . '/admin/serve_file.php?photo_id=' . (int)$photoId;
        }
        $cleanRel = ltrim(str_replace('\\', '/', (string)$filePath), '/');
        return APP_URL . '/' . $cleanRel;
    }
}

// Generate consolidated report ref
$cleanSiteCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $site['code'] ?: 'SITE'));
$dateCompact = date('Ymd', strtotime($selectedDate));
$conReportRef = "CON-INC-{$cleanSiteCode}-{$dateCompact}";
$printDateTime = date('d-M-Y H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DAILY_SECURITY_INCIDENT_CONSOLIDATED_REPORT_<?= htmlspecialchars($cleanSiteCode) ?>_<?= htmlspecialchars($dateCompact) ?></title>
    <style>
    @page {
        size: A4 landscape;
        margin: 8mm 10mm;
    }
    body {
        font-family: "Noto Sans", Arial, Helvetica, sans-serif !important;
        font-size: 9pt !important;
        color: #000000 !important;
        background: #FFFFFF !important;
    }
    .report-title, h1 {
        font-size: 16pt !important;
        font-weight: 700 !important;
        color: #12263F !important;
    }
    .section-bar, .section-title {
        font-size: 10pt !important;
        font-weight: 700 !important;
        background-color: #12263F !important;
        color: #FFFFFF !important;
    }
    th {
        font-size: 9pt !important;
        font-weight: 600 !important;
        background-color: #235F63 !important;
        color: #FFFFFF !important;
    }
    td {
        font-size: 9pt !important;
    }
    .kpi-val, .metric-value {
        font-size: 24pt !important;
        font-weight: 700 !important;
    }
    .kpi-lbl {
        font-size: 9pt !important;
    }
    .footer-pt, .signoff-text {
        font-size: 8pt !important;
    }
    
        @page {
            size: A4 portrait;
            margin: 8mm 8mm 8mm 8mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: #cbd5e1;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9.5pt;
            line-height: 1.30;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Top Bar for Browser Preview */
        .no-print-bar {
            background: #002266;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            font-size: 13px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .no-print-bar select, .no-print-bar input {
            background: #ffffff;
            color: #000;
            border: 1px solid #94a3b8;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12.5px;
            font-weight: 600;
        }

        .btn-print {
            background: #0284c7;
            color: #fff;
            border: none;
            padding: 7px 18px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-print:hover {
            background: #0369a1;
        }

        .btn-link {
            color: #93c5fd;
            text-decoration: none;
            font-weight: 600;
            font-size: 12.5px;
        }

        .btn-link:hover {
            color: #ffffff;
            text-decoration: underline;
        }

        /* Printable Page Sheet */
        .page-sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 15px auto;
            background: #fff;
            padding: 8mm 10mm;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            position: relative;
            box-sizing: border-box;
        }

        .page-sheet.page-break {
            page-break-before: always;
        }

        /* Top Header */
        .report-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2.5px solid #002266;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }

        .header-logo-box {
            width: 140px;
        }

        .header-logo {
            max-height: 55px;
            max-width: 140px;
            object-fit: contain;
        }

        .header-title-box {
            flex: 1;
            text-align: center;
            padding: 0 10px;
        }

        .header-main-title {
            font-size: 15pt;
            font-weight: 900;
            color: #002266;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.15;
        }

        .header-sub-title {
            font-size: 9.5pt;
            font-weight: bold;
            color: #475569;
            margin-top: 2px;
            letter-spacing: 0.3px;
        }

        .header-ref-box {
            text-align: right;
            font-size: 8.5pt;
            line-height: 1.35;
        }

        .header-ref-box strong {
            color: #002266;
            font-family: monospace;
            font-size: 9.5pt;
        }

        /* Metadata Banner Grid */
        .meta-grid-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            margin-bottom: 8px;
            font-size: 9pt;
        }

        .meta-grid-table td {
            border: 1px solid #000;
            padding: 4px 8px;
            vertical-align: middle;
        }

        .meta-cell-label {
            background: #f1f5f9;
            font-weight: bold;
            width: 15%;
            color: #0f172a;
        }

        .meta-cell-value {
            width: 35%;
            font-weight: bold;
            color: #000;
        }

        /* KPI Summary Strip */
        .kpi-strip {
            display: flex;
            width: 100%;
            border: 1.5px solid #000;
            margin-bottom: 10px;
            background: #002266;
            color: #fff;
            text-align: center;
        }

        .kpi-col {
            flex: 1;
            padding: 5px 6px;
            border-right: 1px solid rgba(255,255,255,0.3);
        }

        .kpi-col:last-child {
            border-right: none;
        }

        .kpi-title {
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.9;
        }

        .kpi-num {
            font-size: 13pt;
            font-weight: 900;
            margin-top: 1px;
            line-height: 1;
        }

        .kpi-badge-critical { color: #f87171; }
        .kpi-badge-high { color: #fb923c; }
        .kpi-badge-med { color: #facc15; }
        .kpi-badge-low { color: #4ade80; }

        /* Incident List Section */
        .section-header-bar {
            background: #002266;
            color: #fff;
            font-weight: bold;
            font-size: 10pt;
            font-style: italic;
            padding: 4px 8px;
            border: 1.5px solid #000;
            border-bottom: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .incidents-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            margin-bottom: 10px;
            font-size: 8.5pt;
        }

        .incidents-table th {
            background: #e2e8f0;
            border: 1px solid #000;
            padding: 5px 6px;
            font-weight: bold;
            text-align: left;
            font-size: 8.5pt;
            color: #0f172a;
        }

        .incidents-table td {
            border: 1px solid #000;
            padding: 5px 6px;
            vertical-align: top;
            line-height: 1.30;
        }

        .badge-priority {
            display: inline-block;
            padding: 1.5px 6px;
            border-radius: 3px;
            font-size: 7.5pt;
            font-weight: 900;
            text-transform: uppercase;
            border: 1px solid #000;
        }

        .badge-Critical { background: #fee2e2; color: #991b1b; border-color: #991b1b; }
        .badge-High { background: #ffedd5; color: #c2410c; border-color: #c2410c; }
        .badge-Medium { background: #fef9c3; color: #854d0e; border-color: #854d0e; }
        .badge-Low { background: #dcfce7; color: #166534; border-color: #166534; }

        .badge-shift {
            display: inline-block;
            padding: 1px 4px;
            background: #f1f5f9;
            border: 1px solid #94a3b8;
            font-size: 7.5pt;
            font-weight: bold;
            border-radius: 2px;
        }

        /* Detailed Incident Item Card (When multi-line reporting) */
        .incident-card {
            border: 1.5px solid #000;
            margin-bottom: 8px;
            page-break-inside: avoid;
        }

        .incident-card-header {
            background: #002266;
            color: #fff;
            padding: 4px 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 9pt;
            font-weight: bold;
        }

        .incident-card-body {
            padding: 6px 8px;
            font-size: 9pt;
            line-height: 1.30;
        }

        .incident-card-summary {
            font-size: 11pt;
            font-weight: bold;
            color: #8b0000;
            margin-bottom: 4px;
            line-height: 1.25;
            word-wrap: break-word;
        }

        .incident-card-desc {
            font-size: 9pt;
            line-height: 1.30;
            color: #1e293b;
            margin-bottom: 6px;
            word-wrap: break-word;
        }

        .incident-card-desc p, .incident-card-desc div {
            margin: 0 !important;
            padding: 0 !important;
            line-height: 1.30 !important;
        }

        .incident-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 8.5pt;
            background: #f8fafc;
            padding: 4px 6px;
            border-top: 1px solid #cbd5e1;
        }

        .incident-photos-strip {
            display: flex;
            gap: 6px;
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px dashed #cbd5e1;
        }

        .photo-thumb-frame {
            width: 75px;
            height: 55px;
            border: 1px solid #000;
            background: #f1f5f9;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .photo-thumb-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Nil Incidents Clear Banner */
        .nil-incidents-box {
            border: 2px dashed #059669;
            background: #f0fdf4;
            padding: 28px 20px;
            text-align: center;
            margin: 15px 0;
            border-radius: 4px;
        }

        .nil-title {
            font-size: 14pt;
            font-weight: 900;
            color: #065f46;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .nil-text {
            font-size: 10pt;
            color: #047857;
            max-width: 650px;
            margin: 0 auto;
            line-height: 1.4;
        }

        /* Formal Sign-Off Table */
        .signoff-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            margin-top: 12px;
            font-size: 8.5pt;
            page-break-inside: avoid;
        }

        .signoff-table th {
            background: #002266;
            color: #fff;
            padding: 4px 8px;
            font-size: 8.5pt;
            font-weight: bold;
            text-align: left;
            border: 1px solid #000;
        }

        .signoff-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            vertical-align: top;
            height: 48px;
        }

        /* Footer */
        .report-footer {
            margin-top: 8px;
            padding-top: 4px;
            border-top: 1px solid #94a3b8;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 8pt;
            color: #475569;
            font-weight: bold;
        }

        @media print {
            .no-print-bar { display: none !important; }
            body {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .page-sheet {
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                min-height: auto !important;
            }
        }
    </style>
</head>
<body>

<!-- Browser Top Controls -->
<div class="no-print-bar">
    <div style="display: flex; align-items: center; gap: 14px;">
        <a href="daily_report.php?site_id=<?= $site['id'] ?>&date=<?= $selectedDate ?>" class="btn-link">&larr; Back to Dossier</a>
        <span style="font-weight: 800; letter-spacing: 0.5px; color: #38bdf8;">
            DAILY SECURITY INCIDENT CONSOLIDATED REPORT
        </span>
    </div>

    <form method="GET" action="" style="display: flex; align-items: center; gap: 10px;">
        <select name="site_id" onchange="this.form.submit()">
            <?php foreach ($allSites as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $site['id'] == $s['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['code']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()">
        
        <button type="submit" style="display:none;">Submit</button>
    </form>

    <div style="display: flex; align-items: center; gap: 10px;">
        <a href="<?= APP_URL ?>/api/export_excel.php?site_id=<?= $site['id'] ?>&date_from=<?= $selectedDate ?>&date_to=<?= $selectedDate ?>" class="btn-link" style="padding: 6px 12px; background: rgba(255,255,255,0.1); border-radius: 4px;">
            📊 Export CSV
        </a>
        <button class="btn-print" onclick="window.print()">
            🖨️ Print Consolidated Report (A4)
        </button>
    </div>
</div>

<!-- Main Printable A4 Sheet -->
<div class="page-sheet">

    <!-- Top Report Header -->
    <div class="report-header">
        <div class="header-logo-box">
            <?php 
            $logoPath = ROOT_PATH . '/assets/images/logo.png';
            $logoSrc = file_exists($logoPath) ? get_consolidated_photo_src($logoPath) : '';
            if ($logoSrc): ?>
                <img src="<?= $logoSrc ?>" alt="USS Logo" class="header-logo">
            <?php else: ?>
                <div style="font-size: 14pt; font-weight: 900; color: #002266; line-height: 1;">USS</div>
                <div style="font-size: 7pt; font-weight: bold; color: #475569;">Ultimate Security Services</div>
            <?php endif; ?>
        </div>

        <div class="header-title-box">
            <h1 class="header-main-title">DAILY SECURITY INCIDENT CONSOLIDATED REPORT</h1>
            <div class="header-sub-title">Ultimate Security Services (USS) &bull; Sitewise Operational Summary</div>
        </div>

        <div class="header-ref-box">
            <div>Report Ref: <strong><?= htmlspecialchars($conReportRef) ?></strong></div>
            <div>Generated: <strong><?= htmlspecialchars($printDateTime) ?></strong></div>
            <div>Classification: <strong style="color: #991b1b;">CONFIDENTIAL</strong></div>
        </div>
    </div>

    <!-- Metadata Grid Table -->
    <table class="meta-grid-table">
        <tr>
            <td class="meta-cell-label">Facility / Site:</td>
            <td class="meta-cell-value"><?= htmlspecialchars($site['name']) ?> (<?= htmlspecialchars($site['code']) ?>)</td>
            <td class="meta-cell-label">Report Date:</td>
            <td class="meta-cell-value"><?= date('d-M-Y (l)', strtotime($selectedDate)) ?></td>
        </tr>
        <tr>
            <td class="meta-cell-label">Client Name:</td>
            <td class="meta-cell-value"><?= htmlspecialchars($site['client_name'] ?: 'Corporate Client') ?></td>
            <td class="meta-cell-label">Coverage Shifts:</td>
            <td class="meta-cell-value">Day Shift (06:00 - 18:00) &bull; Night Shift (18:00 - 06:00)</td>
        </tr>
        <tr>
            <td class="meta-cell-label">Site Location:</td>
            <td class="meta-cell-value"><?= htmlspecialchars($site['address'] ?: 'United Arab Emirates') ?></td>
            <td class="meta-cell-label">Site Coordinates:</td>
            <td class="meta-cell-value" style="font-family: monospace; font-size: 8.5pt;">
                Lat: <?= htmlspecialchars(format_coord_val($site['latitude'], 'lat')) ?> &bull; Long: <?= htmlspecialchars(format_coord_val($site['longitude'], 'lng')) ?>
            </td>
        </tr>
    </table>

    <!-- KPI Summary Strip -->
    <div class="kpi-strip">
        <div class="kpi-col">
            <div class="kpi-title">Total Incidents</div>
            <div class="kpi-num"><?= $totalIncidents ?></div>
        </div>
        <div class="kpi-col">
            <div class="kpi-title">Critical</div>
            <div class="kpi-num kpi-badge-critical"><?= $criticalCount ?></div>
        </div>
        <div class="kpi-col">
            <div class="kpi-title">High</div>
            <div class="kpi-num kpi-badge-high"><?= $highCount ?></div>
        </div>
        <div class="kpi-col">
            <div class="kpi-title">Medium</div>
            <div class="kpi-num kpi-badge-med"><?= $mediumCount ?></div>
        </div>
        <div class="kpi-col">
            <div class="kpi-title">Low</div>
            <div class="kpi-num kpi-badge-low"><?= $lowCount ?></div>
        </div>
        <div class="kpi-col">
            <div class="kpi-title">Day Shift</div>
            <div class="kpi-num"><?= $dayShiftCount ?></div>
        </div>
        <div class="kpi-col">
            <div class="kpi-title">Night Shift</div>
            <div class="kpi-num"><?= $nightShiftCount ?></div>
        </div>
        <div class="kpi-col">
            <div class="kpi-title">Resolved / Client</div>
            <div class="kpi-num" style="color: #67e8f9;"><?= $resolvedCount ?></div>
        </div>
    </div>

    <!-- Section Header Bar -->
    <div class="section-header-bar">
        <span>Consolidated Incident Log &amp; Detailed Findings</span>
        <span style="font-size: 8.5pt; font-weight: normal; font-style: normal;">Total Recorded: <?= $totalIncidents ?> Incident<?= $totalIncidents !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($incidents)): ?>
        <!-- NIL INCIDENTS OFFICIAL CERTIFICATION -->
        <div class="nil-incidents-box">
            <div class="nil-title">✓ NIL INCIDENTS LOGGED &bull; ALL PATROLS CLEAR</div>
            <p class="nil-text">
                During the 24-hour operational reporting cycle for <strong><?= date('d-M-Y', strtotime($selectedDate)) ?></strong>, 
                routine security patrols, physical access control checkpoints, and automated perimeter inspections were executed in full compliance with USS Standard Operating Procedures. 
                <strong>Zero security breaches, disciplinary infractions, property damages, or emergency safety hazards</strong> were logged across all active shifts.
            </p>
        </div>
    <?php else: ?>
        <!-- INCIDENTS DETAILED BREAKDOWN -->
        <?php foreach ($incidents as $idx => $inc): 
            $disc = json_decode($inc['disciplinary_reasons'] ?: '[]', true) ?: [];
            $types = json_decode($inc['incident_types'] ?: '[]', true) ?: [];
            $notif = json_decode($inc['who_notified'] ?: '[]', true) ?: [];
            $photos = $incidentPhotos[$inc['id']] ?? [];
        ?>
            <div class="incident-card">
                <div class="incident-card-header">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span>#<?= $idx + 1 ?></span>
                        <span style="font-family: monospace;"><?= htmlspecialchars($inc['ref_number']) ?></span>
                        <span class="badge-shift"><?= htmlspecialchars($inc['shift']) ?> Shift &bull; <?= format_time($inc['incident_time']) ?></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="badge-priority badge-<?= htmlspecialchars($inc['priority']) ?>"><?= htmlspecialchars($inc['priority']) ?></span>
                        <span style="font-size: 8pt; background: rgba(255,255,255,0.2); padding: 1.5px 6px; border-radius: 3px;">
                            Status: <?= htmlspecialchars($inc['status']) ?>
                        </span>
                    </div>
                </div>

                <div class="incident-card-body">
                    <!-- Summary Line -->
                    <div class="incident-card-summary">
                        <?= htmlspecialchars($inc['summary_line'] ?: 'Incident Recorded') ?>
                    </div>

                    <!-- Description -->
                    <div class="incident-card-desc">
                        <?= render_rich_description($inc['description']) ?>
                    </div>

                    <!-- Categories / Notifications / Action -->
                    <div class="incident-meta-row">
                        <div>
                            <strong>Officer:</strong> <?= htmlspecialchars($inc['reported_by_name']) ?> <?= !empty($inc['guard_staff_id']) ? '(' . htmlspecialchars($inc['guard_staff_id']) . ')' : '' ?>
                        </div>

                        <?php if (!empty($disc)): ?>
                            <div>
                                <strong>Disciplinary:</strong> <?= htmlspecialchars(implode(', ', $disc)) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($types)): ?>
                            <div>
                                <strong>Category:</strong> <?= htmlspecialchars(implode(', ', $types)) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($notif)): ?>
                            <div>
                                <strong>Notified:</strong> <?= htmlspecialchars(implode(', ', $notif)) ?>
                            </div>
                        <?php endif; ?>

                        <?php 
                        $actionText = $inc['recommendation_action_taken'] ?? ($inc['action_taken'] ?? '');
                        if (!empty($actionText)): ?>
                            <div style="width: 100%; text-align: left !important; word-wrap: break-word !important; overflow-wrap: break-word !important; word-break: break-word !important; white-space: normal !important; margin-top: 3px;">
                                <strong>Action Taken / Recommendation:</strong> <span style="color: #8B0000; font-weight: 700;"><?= htmlspecialchars(trim($actionText)) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Attached Evidence Thumbnails if present -->
                    <?php if (!empty($photos)): ?>
                        <div class="incident-photos-strip">
                            <span style="font-size: 8pt; font-weight: bold; color: #475569; align-self: center; margin-right: 4px;">
                                📷 Attached Photos (<?= count($photos) ?>):
                            </span>
                            <?php foreach (array_slice($photos, 0, 5) as $ph): 
                                $phFile = !empty($ph['file_path']) ? $ph['file_path'] : (!empty($ph['photo_path']) ? $ph['photo_path'] : '');
                                $src = get_consolidated_photo_src($phFile, $ph['id'] ?? 0);
                            ?>
                                <div class="photo-thumb-frame">
                                    <?php if ($src): ?>
                                        <img src="<?= $src ?>" alt="Photo">
                                    <?php else: ?>
                                        <span style="font-size: 7pt; color: #94a3b8;">No preview</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($photos) > 5): ?>
                                <span style="font-size: 8pt; color: #64748b; align-self: center;">+<?= count($photos) - 5 ?> more</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Multi-Signature Endorsement & Authorization Section -->
    <table class="signoff-table">
        <tr>
            <th style="width: 33.3%;">Prepared By (Site Supervisor)</th>
            <th style="width: 33.3%;">Reviewed By (Security Operations Manager)</th>
            <th style="width: 33.3%;">Client Representative Acknowledgement</th>
        </tr>
        <tr>
            <td>
                <div>Name: _______________________________</div>
                <div style="margin-top: 4px;">ID / Badge No: _________________________</div>
                <div style="margin-top: 4px;">Signature: ____________________________</div>
                <div style="margin-top: 4px;">Date: <?= date('d-M-Y') ?></div>
            </td>
            <td>
                <div>Name: _______________________________</div>
                <div style="margin-top: 4px;">Designation: Security Controller / OM</div>
                <div style="margin-top: 4px;">Signature: ____________________________</div>
                <div style="margin-top: 4px;">Date: <?= date('d-M-Y') ?></div>
            </td>
            <td>
                <div>Name: _______________________________</div>
                <div style="margin-top: 4px;">Designation: Facilities / Security Mgr</div>
                <div style="margin-top: 4px;">Signature: ____________________________</div>
                <div style="margin-top: 4px;">Date: _________________________________</div>
            </td>
        </tr>
    </table>

    <!-- Footer -->
    <div class="report-footer">
        <div>Ultimate Security Services (USS) &bull; Official Daily Incident Consolidated Dossier</div>
        <div>Confidential Client Document &bull; Printed: <?= htmlspecialchars($printDateTime) ?></div>
    </div>

</div>

</body>
</html>
