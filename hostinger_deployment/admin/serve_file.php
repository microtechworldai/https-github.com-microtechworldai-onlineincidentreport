<?php
/**
 * Protected Private Evidence & Document Delivery Gateway
 * Serves incident photos, generated PDFs, and private assets strictly to authenticated Super Admins
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Strict Super Admin Access Only
require_admin_auth();

$fileRel = trim($_GET['file'] ?? '');
$photoId = (int)($_GET['photo_id'] ?? 0);
$db = get_db();

$targetPath = '';
$origName = 'evidence_document';
$mimeType = 'application/octet-stream';

if ($photoId > 0) {
    $stmt = $db->prepare("SELECT file_path, original_filename, mime_type FROM incident_photos WHERE id = ? LIMIT 1");
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch();
    if (!$photo) {
        http_response_code(404);
        die("Evidence photo record not found.");
    }
    $fileRel = $photo['file_path'];
    $origName = $photo['original_filename'];
    $mimeType = $photo['mime_type'] ?: 'image/jpeg';
}

if (empty($fileRel)) {
    http_response_code(400);
    die("No file specified.");
}

// Security: Prevent path traversal attacks
$fileRel = str_replace(['..', "\0"], '', $fileRel);
$fileRel = ltrim($fileRel, '/\\');

$cleanNorm = str_replace('\\', '/', $fileRel);
$filename = basename($cleanNorm);
$candidates = [
    ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fileRel),
    ROOT_PATH . '/' . ltrim($cleanNorm, '/'),
    ROOT_PATH . '/storage/private/' . ltrim(preg_replace('#^.*?(storage/private/)?#i', '', $cleanNorm), '/'),
    ROOT_PATH . '/uploads/' . ltrim(preg_replace('#^.*?(uploads/)?#i', '', $cleanNorm), '/'),
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
    ROOT_PATH . '/uploads/' . $filename,
];

$realTarget = false;
foreach ($candidates as $cand) {
    if ($cand && file_exists($cand) && !is_dir($cand)) {
        $rp = realpath($cand);
        if ($rp && file_exists($rp)) {
            $realTarget = $rp;
            break;
        }
    }
}

if (!$realTarget) {
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
            if (!empty($matches) && file_exists($matches[0])) {
                $realTarget = realpath($matches[0]);
                break;
            }
            $matches1 = @glob(rtrim($sDir, '/\\') . '/*/' . $filename);
            if (!empty($matches1) && file_exists($matches1[0])) {
                $realTarget = realpath($matches1[0]);
                break;
            }
        }
    }
}

$realRoot = realpath(ROOT_PATH);
if (!$realTarget || !file_exists($realTarget)) {
    http_response_code(404);
    die("File not found in secure storage.");
}

// Detect correct MIME type
$ext = strtolower(pathinfo($realTarget, PATHINFO_EXTENSION));
$allowedMimes = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'pdf'  => 'application/pdf'
];
$mime = $allowedMimes[$ext] ?? $mimeType;

// Release session lock before streaming file
session_write_close();

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($realTarget));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
header('Content-Disposition: inline; filename="' . basename($origName) . '"');

readfile($realTarget);
exit;
