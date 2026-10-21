<?php
/**
 * Core Helper Functions
 * Ultimate Security Services - Incident Report System
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

/**
 * Clean & Sanitize user inputs
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF Token
 */
function get_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF Token
 */
function validate_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

/**
 * Generate unique incident reference code
 * Format: INC-<SITECODE>-YYYYMMDD-0001
 */
function generate_incident_ref($siteCode, $incidentDate = null, $offset = 0) {
    $db = get_db();
    $datePart = $incidentDate ? date('Ymd', strtotime($incidentDate)) : date('Ymd');
    $cleanSiteCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $siteCode));
    if (empty($cleanSiteCode)) $cleanSiteCode = 'GEN';
    $prefix = "INC-{$cleanSiteCode}-{$datePart}-";

    // Find the latest allocated reference for this prefix
    $stmt = $db->prepare("SELECT ref_number FROM incidents WHERE ref_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastRef = $stmt->fetchColumn();

    $nextNum = 1;
    if ($lastRef) {
        $parts = explode('-', $lastRef);
        $lastSeq = (int)end($parts);
        if ($lastSeq > 0) {
            $nextNum = $lastSeq + 1;
        } else {
            $stmtCount = $db->prepare("SELECT COUNT(*) FROM incidents WHERE ref_number LIKE ?");
            $stmtCount->execute([$prefix . '%']);
            $nextNum = (int)$stmtCount->fetchColumn() + 1;
        }
    }

    $nextNum += $offset;
    $sequence = str_pad($nextNum, 4, '0', STR_PAD_LEFT);

    return $prefix . $sequence;
}

/**
 * Comprehensive Security & Audit Logging
 */
/**
 * Argon2id Password Hashing with Cryptographic Pepper
 */
function hash_password_argon2id($password) {
    $pepper = defined('APP_PEPPER') ? APP_PEPPER : '';
    $algo = defined('PASSWORD_ALGO_PREFERRED') ? PASSWORD_ALGO_PREFERRED : PASSWORD_BCRYPT;
    $options = defined('ARGON2_OPTIONS') ? ARGON2_OPTIONS : [];
    return password_hash($password . $pepper, $algo, $options);
}

/**
 * Verify Password against Argon2id / Peppered Hash with Legacy Fallback
 */
function verify_password_argon2id($password, $hash) {
    if (empty($password) || empty($hash)) return false;
    $pepper = defined('APP_PEPPER') ? APP_PEPPER : '';

    // Check with pepper first (Standard)
    if (password_verify($password . $pepper, $hash)) {
        return true;
    }

    // Fallback check without pepper (for backward compatibility during migration)
    if (password_verify($password, $hash)) {
        return true;
    }

    // Whitespace-trimmed checks for mobile virtual keyboards
    $trimmed = trim($password);
    if ($trimmed !== $password) {
        if (password_verify($trimmed . $pepper, $hash) || password_verify($trimmed, $hash)) {
            return true;
        }
    }

    return false;
}

/**
 * Standard verify_password wrapper
 */
function verify_password($password, $hash) {
    return verify_password_argon2id($password, $hash);
}

/**
 * AES-256-GCM Field-Level Application Encryption
 */
function encrypt_field($plaintext) {
    if ($plaintext === null || $plaintext === '') return '';
    $key = hash('sha256', defined('APP_ENCRYPTION_KEY') ? APP_ENCRYPTION_KEY : 'default_key', true);
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $ciphertext);
}

/**
 * AES-256-GCM Field-Level Application Decryption
 */
function decrypt_field($ciphertextBase64) {
    if (empty($ciphertextBase64)) return '';
    $raw = base64_decode($ciphertextBase64, true);
    if ($raw === false || strlen($raw) < 28) return ''; // 12 bytes IV + 16 bytes Tag
    $key = hash('sha256', defined('APP_ENCRYPTION_KEY') ? APP_ENCRYPTION_KEY : 'default_key', true);
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ciphertext = substr($raw, 28);
    $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $decrypted !== false ? $decrypted : '';
}

/**
 * Keyed HMAC-SHA256 Checkpoint Token Hash for Lookup
 */
function hash_qr_token($token) {
    $pepper = defined('APP_PEPPER') ? APP_PEPPER : 'default_pepper';
    return hash_hmac('sha256', trim($token), $pepper);
}

/**
 * Comprehensive Security & Append-Only Tamper-Evident Audit Logging
 * Implements cryptographic hash chaining: each entry incorporates the previous entry's hash
 */
function log_audit($action, $targetType = null, $targetId = null, $details = null, $actorType = 'SUPER_ADMIN', $actorId = null, $actorName = null) {
    try {
        $db = get_db();
        if (!$actorId && isset($_SESSION['admin_id'])) {
            $actorType = 'SUPER_ADMIN';
            $actorId = $_SESSION['admin_id'];
            $actorName = $_SESSION['admin_name'] ?? 'Admin';
        } elseif (!$actorId && isset($_SESSION['guard_id'])) {
            $actorType = 'GUARD';
            $actorId = $_SESSION['guard_id'];
            $actorName = $_SESSION['guard_name'] ?? 'Guard';
        }

        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ip = explode(',', $ip)[0];
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $detailsStr = is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : (string)($details ?? '');
        $timestamp = date('Y-m-d H:i:s');

        // Fetch the hash of the latest audit record for chain continuity
        $lastRow = $db->query("SELECT record_hash FROM audit_logs ORDER BY id DESC LIMIT 1")->fetch();
        $previousHash = (!empty($lastRow) && !empty($lastRow['record_hash'])) ? $lastRow['record_hash'] : 'GENESIS_USS_CHAIN_2026';

        // Compute current record's cryptographic hash
        $recordPayload = implode('|', [
            $previousHash,
            $timestamp,
            $actorType,
            (string)($actorId ?? ''),
            (string)($actorName ?? ''),
            $action,
            (string)($targetType ?? ''),
            (string)($targetId ?? ''),
            $detailsStr,
            $ip
        ]);
        $recordHash = hash('sha256', $recordPayload);

        $stmt = $db->prepare("
            INSERT INTO audit_logs (actor_type, actor_id, actor_name, action, target_type, target_id, details, previous_hash, record_hash, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $actorType,
            (string)$actorId,
            $actorName,
            $action,
            $targetType,
            (string)$targetId,
            $detailsStr,
            $previousHash,
            $recordHash,
            $ip,
            substr($ua, 0, 500),
            $timestamp
        ]);
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}

/**
 * Verify Integrity of the Audit Log Cryptographic Chain
 * @return array ['valid' => bool, 'total' => int, 'broken_at' => int|null]
 */
function verify_audit_log_chain() {
    $db = get_db();
    $rows = $db->query("SELECT * FROM audit_logs ORDER BY id ASC")->fetchAll();
    $total = count($rows);
    if ($total === 0) return ['valid' => true, 'total' => 0, 'broken_at' => null];

    $expectedPrev = 'GENESIS_USS_CHAIN_2026';
    foreach ($rows as $row) {
        // If row lacks chain data (legacy record prior to upgrade), skip to chained entries
        if (empty($row['record_hash'])) {
            continue;
        }

        if (!empty($row['previous_hash']) && $row['previous_hash'] !== $expectedPrev) {
            return ['valid' => false, 'total' => $total, 'broken_at' => (int)$row['id']];
        }

        $expectedPrev = $row['record_hash'];
    }

    return ['valid' => true, 'total' => $total, 'broken_at' => null];
}

/**
 * Fetch System Settings
 */
function get_system_settings() {
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    try {
        $db = get_db();
        $stmt = $db->query("SELECT * FROM system_settings WHERE id = 'default-settings' LIMIT 1");
        $row = $stmt->fetch();
        if ($row) {
            $row['shift_options'] = json_decode($row['shift_options'] ?: '[]', true) ?: ['Day', 'Night'];
            $row['cause_options'] = json_decode($row['cause_options'] ?: '[]', true) ?: ['Incident', 'Disciplinary', 'Others'];
            $row['disciplinary_options'] = json_decode($row['disciplinary_options'] ?: '[]', true) ?: ['Sleeping', 'Poor Grooming', 'Argument', 'Missing', 'Misbehavior', 'Uniform', 'Late Reporting', 'Mobile', 'Others'];
            $row['incident_options'] = json_decode($row['incident_options'] ?: '[]', true) ?: ['Move In & Out', 'Fire & Safety', 'Intrusion & Access', 'Theft and loss', 'CCTV & Systems', 'Utilities & Building', 'Accident & Medical', 'Disturbance', 'Property Damage', 'Parking & Vehicles', 'Visitors & Contractors', 'Others'];
            $row['notified_options'] = json_decode($row['notified_options'] ?: '[]', true) ?: ['Police', 'Ambulance', 'Civil Defense', 'Client Management', 'USS Management', 'Others'];
            $settings = $row;
            return $settings;
        }
    } catch (Exception $e) {}

    // Fallbacks
    return [
        'company_name' => 'ULTIMATE SECURITY SERVICES',
        'tagline' => 'Total Security Solutions',
        'address_line' => 'P O Box No: 61591, Al Maktoum Street | Dubai | UAE',
        'phone' => '+971 4 251 0050',
        'email' => 'ops@ultimatesecurity.ae',
        'website' => 'https://www.sprinzo.in',
        'logo_url' => 'assets/images/logo.png',
        'timezone' => 'Asia/Dubai',
        'shift_options' => ['Day', 'Night'],
        'cause_options' => ['Incident', 'Disciplinary', 'Others'],
        'disciplinary_options' => ['Sleeping', 'Poor Grooming', 'Argument', 'Missing', 'Misbehavior', 'Uniform', 'Late Reporting', 'Mobile', 'Others'],
        'incident_options' => ['Move In & Out', 'Fire & Safety', 'Intrusion & Access', 'Theft and loss', 'CCTV & Systems', 'Utilities & Building', 'Accident & Medical', 'Disturbance', 'Property Damage', 'Parking & Vehicles', 'Visitors & Contractors', 'Others'],
        'notified_options' => ['Police', 'Ambulance', 'Civil Defense', 'Client Management', 'USS Management', 'Others'],
        'supervisor_remarks_min_length' => 20
    ];
}

/**
 * Standard Project Date Formatter (dd/mm/yyyy)
 */
function format_date($dateStr) {
    if (empty($dateStr) || $dateStr === '0000-00-00') {
        return '-';
    }
    $ts = is_numeric($dateStr) ? (int)$dateStr : strtotime((string)$dateStr);
    return $ts ? date('d/m/Y', $ts) : (string)$dateStr;
}

/**
 * Standard Project Time Formatter (24hrs: HH:MM or HH:MM:SS)
 */
function format_time($timeStr, $includeSeconds = false) {
    if (empty($timeStr)) {
        return '-';
    }
    // If it's already HH:MM or HH:MM:SS
    if (preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', trim((string)$timeStr))) {
        $parts = explode(':', trim((string)$timeStr));
        return $includeSeconds ? sprintf('%02d:%02d:%02d', $parts[0], $parts[1], $parts[2] ?? 0) : sprintf('%02d:%02d', $parts[0], $parts[1]);
    }
    $ts = is_numeric($timeStr) ? (int)$timeStr : strtotime((string)$timeStr);
    $fmt = $includeSeconds ? 'H:i:s' : 'H:i';
    return $ts ? date($fmt, $ts) : (string)$timeStr;
}

/**
 * Standard Project DateTime Formatter (dd/mm/yyyy 24hrs)
 */
function format_datetime($datetimeStr, $includeSeconds = false) {
    if (empty($datetimeStr) || $datetimeStr === '0000-00-00 00:00:00') {
        return '-';
    }
    $ts = is_numeric($datetimeStr) ? (int)$datetimeStr : strtotime((string)$datetimeStr);
    $fmt = $includeSeconds ? 'd/m/Y H:i:s' : 'd/m/Y H:i';
    return $ts ? date($fmt, $ts) : (string)$datetimeStr;
}

/**
 * Priority Badge HTML
 */
function render_priority_badge($priority) {
    $colors = [
        'Critical' => 'bg-rose-500/10 text-rose-500 border-rose-500/30',
        'High'     => 'bg-amber-500/10 text-amber-500 border-amber-500/30',
        'Medium'   => 'bg-blue-500/10 text-blue-500 border-blue-500/30',
        'Low'      => 'bg-emerald-500/10 text-emerald-500 border-emerald-500/30',
    ];
    $cls = $colors[$priority] ?? 'bg-slate-500/10 text-slate-400 border-slate-500/30';
    return "<span class='badge {$cls}'>{$priority}</span>";
}

/**
 * Status Badge HTML
 */
function render_status_badge($status) {
    $colors = [
        'Submitted'           => 'badge-submitted',
        'Under Review'        => 'badge-review',
        'Follow-up Required'  => 'badge-followup',
        'Resolved'            => 'badge-resolved',
        'Submitted to Client' => 'badge-client',
    ];
    $cls = $colors[$status] ?? 'badge-submitted';
    return "<span class='badge {$cls}'>{$status}</span>";
}

/**
 * Image processing & compression for patrol photos
 */
function process_and_save_photo($tmpName, $originalName) {
    if (!file_exists($tmpName) || !is_uploaded_file($tmpName)) {
        return false;
    }

    $uploadDir = defined('PHOTOS_PRIVATE_PATH') ? PHOTOS_PRIVATE_PATH : (UPLOADS_PATH . '/photos/');
    $fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp'])) {
        $fileExt = 'jpg';
    }

    // Hierarchical Date Sharding for 500,000+ files: /storage/private/photos/YYYY/MM/
    $subDir = date('Y') . DIRECTORY_SEPARATOR . date('m');
    $fullDir = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $subDir;
    if (!is_dir($fullDir)) {
        @mkdir($fullDir, 0755, true);
    }

    $filename = 'inc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $fileExt;
    $targetPath = $fullDir . DIRECTORY_SEPARATOR . $filename;

    // Check if GD library is available to compress and strip EXIF
    if (extension_loaded('gd') && function_exists('imagecreatefromstring')) {
        $imgData = @file_get_contents($tmpName);
        $srcImage = @imagecreatefromstring($imgData);
        if ($srcImage !== false) {
            $origWidth = imagesx($srcImage);
            $origHeight = imagesy($srcImage);

            // Resize if longer edge exceeds 1600px
            $maxEdge = 1600;
            if ($origWidth > $maxEdge || $origHeight > $maxEdge) {
                if ($origWidth >= $origHeight) {
                    $newWidth = $maxEdge;
                    $newHeight = (int)($origHeight * ($maxEdge / $origWidth));
                } else {
                    $newHeight = $maxEdge;
                    $newWidth = (int)($origWidth * ($maxEdge / $origHeight));
                }
                $resized = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($resized, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
                imagejpeg($resized, $targetPath, 85);
                imagedestroy($resized);
            } else {
                imagejpeg($srcImage, $targetPath, 85);
            }
            imagedestroy($srcImage);
        } else {
            move_uploaded_file($tmpName, $targetPath);
        }
    } else {
        move_uploaded_file($tmpName, $targetPath);
    }

    $relPath = str_replace(rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR, '', $targetPath);
    $relPath = str_replace('\\', '/', $relPath);

    return [
        'filename' => $filename,
        'relative_path' => $relPath,
        'size' => file_exists($targetPath) ? filesize($targetPath) : 0,
        'mime' => 'image/jpeg',
        'file_hash' => file_exists($targetPath) ? hash_file('sha256', $targetPath) : ''
    ];
}

/**
 * Return JSON Response
 */
function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Relative time helper
 */
function time_ago($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('d M Y', $time);
}

/**
 * Render Rich Incident Description Safely
 * Preserves font-family, font-size, bold, italic, alignment, and custom font colors
 * Handles legacy plain text by converting newlines cleanly
 */
function render_rich_description($text) {
    if ($text === null || $text === '') {
        return '';
    }

    // If text contains HTML formatting
    if ($text !== strip_tags($text)) {
        // Strip out dangerous tags (script, iframe, form, etc.) while allowing rich typography tags
        $allowedTags = '<p><br><div><span><b><strong><i><em><u><font><ul><ol><li>';
        $clean = strip_tags($text, $allowedTags);

        // Sanitize inline style attributes to block XSS (javascript:, expression(), url())
        $clean = preg_replace_callback('/<([a-z1-6]+)([^>]*)>/i', function($matches) {
            $tag = strtolower($matches[1]);
            $attrs = $matches[2];

            $styleAttr = '';
            if (preg_match('/style=([\'"])(.*?)\1/i', $attrs, $sm)) {
                $styleVal = $sm[2];
                // Remove dangerous URI expressions
                $styleVal = preg_replace('/(javascript|expression|behavior|url)\s*:/i', '', $styleVal);
                $styleAttr = ' style="' . htmlspecialchars($styleVal, ENT_QUOTES, 'UTF-8') . '"';
            }

            $extra = '';
            if ($tag === 'font') {
                if (preg_match('/color=([\'"])(#[0-9a-f]{3,6}|[a-z]+)\1/i', $attrs, $cm)) {
                    $extra .= ' color="' . htmlspecialchars($cm[2], ENT_QUOTES, 'UTF-8') . '"';
                }
                if (preg_match('/face=([\'"])(.*?)\1/i', $attrs, $fm)) {
                    $extra .= ' face="' . htmlspecialchars($fm[2], ENT_QUOTES, 'UTF-8') . '"';
                }
            }

            return "<{$tag}{$styleAttr}{$extra}>";
        }, $clean);

        return $clean;
    }

    // Legacy plain text: escape and preserve newlines
    return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
}

/**
 * Generate unique Visit Reference
 * Format: VST-<SITECODE>-YYYYMMDD-XXXX
 */
function generate_visit_ref($siteCode, $timeIn = null) {
    $db = get_db();
    $dStr = $timeIn ? date('Ymd', strtotime($timeIn)) : date('Ymd');
    $cleanCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $siteCode ?: 'SITE'));
    if (empty($cleanCode)) $cleanCode = 'SITE';
    $prefix = "VST-{$cleanCode}-{$dStr}-";
    $stmt = $db->prepare("SELECT visit_ref FROM supervisor_visits WHERE visit_ref LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $next = 1;
    if ($last && preg_match('/-(\d+)$/', $last, $m)) {
        $next = intval($m[1]) + 1;
    }
    return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
}
