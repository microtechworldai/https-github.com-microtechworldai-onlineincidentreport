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
    $stmt = $db->prepare("SELECT file_path, original_filename, mime_type, file_hash FROM incident_photos WHERE id = ? LIMIT 1");
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

$fullPath = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fileRel);
$realTarget = realpath($fullPath);
$realRoot = realpath(ROOT_PATH);

if (!$realTarget || strpos($realTarget, $realRoot) !== 0 || !file_exists($realTarget)) {
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
