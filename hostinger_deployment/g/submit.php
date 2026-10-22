<?php
/**
 * Security Guard Mobile Patrol Incident Form
 * High-Contrast 2-Column Checklist UI Matching Official Specification (Images 3 & 4)
 * Camera Capture, Local Draft Auto-Save, and Instant Session Security
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_guard_auth();

$settings = get_system_settings();
$db = get_db();
$error = '';

// Support site_id parameter from supervisor visit escalation
if (!empty($_GET['site_id'])) {
    $paramSiteId = intval($_GET['site_id']);
    $stmtSite = $db->prepare("SELECT id, name, code FROM sites WHERE id = ?");
    $stmtSite->execute([$paramSiteId]);
    $siteRow = $stmtSite->fetch();
    if ($siteRow) {
        $_SESSION['current_site_id'] = $siteRow['id'];
        $_SESSION['current_site_code'] = $siteRow['code'];
        $_SESSION['current_site_name'] = $siteRow['name'];
    }
}

if (empty($_SESSION['current_site_id'])) {
    if (!empty($_SESSION['uss_supervisor_logged_in'])) {
        header("Location: " . APP_URL . "/sup/duty.php");
    } else {
        header("Location: " . APP_URL . "/g/index.php");
    }
    exit;
}

$siteId = $_SESSION['current_site_id'];
$siteCode = $_SESSION['current_site_code'];
$siteName = $_SESSION['current_site_name'];
$guardName = $_SESSION['guard_name'] ?? 'Officer';
$guardStaffId = $_SESSION['guard_staff_id'] ?? '';
$guardDesignation = $_SESSION['guard_designation'] ?? 'Security Officer';

// Pre-fill from supervisor inspection visit
$prefillSummary = '';
$prefillDescription = '';
if (!empty($_GET['supervisor_visit_ref'])) {
    $vRef = trim($_GET['supervisor_visit_ref']);
    $prefillSummary = "Supervisor Inspection Finding - Ref: " . $vRef;
    $prefillDescription = "Incident noted during supervisor site visit [Ref: " . $vRef . "].\n\nAction / Observations: ";
}

// Current UAE Time & Auto Shift Detection
$currentTime = date('H:i');
$currentDate = date('Y-m-d');
$currentHour = (int)date('H');
$defaultShift = ($currentHour >= 7 && $currentHour < 19) ? 'Day' : 'Night';

// Fixed official category lists from template
$disciplinaryOptions = [
    'Sleeping', 'Poor Grooming', 'Argument', 'Missing', 'Misbehavior',
    'Uniform', 'Late Reporting', 'Mobile', 'Others'
];

$incidentOptions = [
    'Move In & Out', 'Fire & Safety', 'Intrusion & Access', 'Theft and loss',
    'CCTV & Systems', 'Utilities & Building', 'Accident & Medical', 'Disturbance',
    'Property Damage', 'Parking & Vehicles', 'Visitors & Contractors', 'Others'
];

$notifiedOptions = [
    'Police', 'Ambulance', 'Civil Defense', 'Client Management',
    'USS Management', 'Others'
];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Read and preserve guard session credentials immediately
    $guardId = $_SESSION['guard_id'];
    $guardName = $_SESSION['guard_name'];
    $guardStaffId = $_SESSION['guard_staff_id'];
    $guardDesignation = $_SESSION['guard_designation'] ?? 'Security Guard';
    $siteId = $_SESSION['current_site_id'];
    $siteCode = $_SESSION['current_site_code'];
    $siteName = $_SESSION['current_site_name'];
    $_SESSION['guard_last_activity'] = time();

    // 2. UNLOCK SESSION FILE: Releases PHP session lock so simultaneous or consecutive submissions never hang
    session_write_close();

    $shift = trim($_POST['shift'] ?? $defaultShift);
    $priority = trim($_POST['priority'] ?? 'Low');
    $summary = trim($_POST['summary_line'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $postDate = !empty($_POST['incident_date']) ? trim($_POST['incident_date']) : $currentDate;
    $postTime = !empty($_POST['incident_time']) ? trim($_POST['incident_time']) : $currentTime;

    $causeMain = isset($_POST['cause_main']) ? json_encode(array_values($_POST['cause_main']), JSON_UNESCAPED_UNICODE) : '[]';
    $discReasons = isset($_POST['disciplinary_reasons']) ? json_encode(array_values($_POST['disciplinary_reasons']), JSON_UNESCAPED_UNICODE) : '[]';
    $discOther = trim($_POST['disciplinary_other_text'] ?? '');
    $incTypes = isset($_POST['incident_types']) ? json_encode(array_values($_POST['incident_types']), JSON_UNESCAPED_UNICODE) : '[]';
    $incOther = trim($_POST['incident_other_text'] ?? '');
    $notified = isset($_POST['who_notified']) ? json_encode(array_values($_POST['who_notified']), JSON_UNESCAPED_UNICODE) : '[]';
    $notifiedOther = trim($_POST['notified_other_text'] ?? '');

    if (empty($summary) || empty($description)) {
        $error = "Please provide both an executive summary and a detailed narrative description.";
    } else {
        $incidentId = null;
        $refNumber = '';
        $maxRetries = 5;

        // 3. CONCURRENT COLLISION-PROOF RETRY LOOP
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            try {
                $refNumber = generate_incident_ref($siteCode, $postDate, $attempt);
                if ($attempt > 0) {
                    $refNumber .= '-' . strtoupper(bin2hex(random_bytes(2)));
                }

                $db->beginTransaction();

                $stmt = $db->prepare("
                    INSERT INTO incidents (
                        ref_number, site_id, site_name, site_code, guard_id,
                        reported_by_name, reported_by_designation,
                        incident_date, incident_time, shift, priority,
                        cause_main, disciplinary_reasons, disciplinary_other_text,
                        incident_types, incident_other_text,
                        summary_line, description,
                        who_notified, notified_other_text,
                        status, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?,
                        ?, ?,
                        ?, ?,
                        'Submitted', NOW()
                    )
                ");

                $stmt->execute([
                    $refNumber, $siteId, $siteName, $siteCode, $guardId,
                    $guardName, "{$guardDesignation} ({$guardStaffId})",
                    $postDate, $postTime, $shift, $priority,
                    $causeMain, $discReasons, $discOther,
                    $incTypes, $incOther,
                    $summary, $description,
                    $notified, $notifiedOther
                ]);

                $incidentId = (int)$db->lastInsertId();
                $db->commit();
                break; // Incident created successfully!
            } catch (PDOException $pe) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                // If collision on unique ref_number under concurrent traffic, retry with jitter
                if ($pe->getCode() == 23000 || strpos($pe->getMessage(), 'Duplicate entry') !== false) {
                    usleep(random_int(10000, 40000)); // 10ms - 40ms jitter
                    continue;
                }
                $error = "Database error: " . $pe->getMessage();
                break;
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = "System error: " . $e->getMessage();
                break;
            }
        }

        if ($incidentId) {
            // 4. Process Uploaded Photos safely into storage (matching database schema)
            if (!empty($_FILES['photos']['name'][0])) {
                $totalFiles = count($_FILES['photos']['name']);
                try {
                    $stmtPhoto = $db->prepare("
                        INSERT INTO incident_photos (incident_id, file_path, original_filename, file_size, mime_type, latitude, longitude, caption, sort_order, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");

                    for ($i = 0; $i < min($totalFiles, MAX_PHOTOS_PER_INCIDENT); $i++) {
                        if (!empty($_FILES['photos']['tmp_name'][$i]) && is_uploaded_file($_FILES['photos']['tmp_name'][$i])) {
                            try {
                                $processed = process_and_save_photo(
                                    $_FILES['photos']['tmp_name'][$i],
                                    $_FILES['photos']['name'][$i]
                                );
                                if ($processed && !empty($processed['relative_path'])) {
                                    $caption = !empty($_POST['photo_captions'][$i]) ? trim($_POST['photo_captions'][$i]) : 'Incident scene evidence';
                                    $lat = !empty($_POST['photo_latitudes'][$i]) ? trim($_POST['photo_latitudes'][$i]) : '25.1610° N';
                                    $lng = !empty($_POST['photo_longitudes'][$i]) ? trim($_POST['photo_longitudes'][$i]) : '55.4120° E';
                                    $stmtPhoto->execute([
                                        $incidentId,
                                        $processed['relative_path'],
                                        $_FILES['photos']['name'][$i],
                                        $processed['size'],
                                        $processed['mime'],
                                        $lat,
                                        $lng,
                                        $caption,
                                        $i
                                    ]);
                                }
                            } catch (Throwable $photoEx) {
                                error_log("Photo upload error for incident {$incidentId}: " . $photoEx->getMessage());
                            }
                        }
                    }
                } catch (Throwable $photoBatchEx) {
                    error_log("Photo batch prepare warning for incident {$incidentId}: " . $photoBatchEx->getMessage());
                }
            }

            // 5. Audit Log Entry
            try {
                log_audit('GUARD_SUBMIT_INCIDENT', 'INCIDENT', $incidentId, [
                    'ref_number' => $refNumber,
                    'site_code' => $siteCode,
                    'priority' => $priority
                ], 'GUARD', $guardId, $guardName);
            } catch (Exception $auditEx) {}

            // 6. Direct Immediate Success Redirect (No lag, no wait)
            header("Location: " . APP_URL . "/g/success.php?ref=" . urlencode($refNumber));
            exit;
        } elseif (empty($error)) {
            $error = "Unable to generate unique incident reference after multiple concurrent attempts. Please tap submit again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Incident Report | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/guard.css?v=<?= file_exists(__DIR__ . '/../assets/css/guard.css') ? filemtime(__DIR__ . '/../assets/css/guard.css') : time() ?>">
    <script>
        (function() {
            var theme = localStorage.getItem('uss_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
</head>
<body class="guard-body">

<!-- Guard Top Header matching Image 3 -->
<header class="guard-header">
    <div class="guard-logo-box">
        <img src="<?= APP_URL ?>/<?= htmlspecialchars($settings['logo_url']) ?>" alt="Logo" onerror="this.style.display='none'">
        <div class="guard-logo-text">
            <div class="title"><?= htmlspecialchars($settings['company_name']) ?></div>
            <div class="sub"><?= htmlspecialchars($settings['tagline'] ?: 'INCIDENT REPORT SYSTEM') ?></div>
        </div>
    </div>
    <div style="display: flex; align-items: center; gap: 8px;">
        <button type="button" class="guard-theme-btn" onclick="openThemePaletteModal()" title="Choose Theme Palette" style="display: inline-flex; align-items: center; gap: 5px; font-weight: 700; font-size: 12px; padding: 6px 10px; border-radius: 20px; cursor: pointer;">
            <span>🎨</span>
            <span id="activeThemeNameLabel">Theme</span>
        </button>
        <a href="<?= APP_URL ?>/g/logout.php" class="btn-signout" onclick="return confirm('Exit report form? Any draft on your phone will remain saved.');">
            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            <span>Sign Out</span>
        </a>
    </div>
</header>

<!-- Guard Status Sub-ribbon -->
<div class="patrol-ribbon">
    <div class="site-tag" style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700;">
        <span style="display: inline-block; width: 8px; height: 8px; background: #10b981; border-radius: 50%;"></span>
        <span style="text-transform: capitalize;"><?= htmlspecialchars(ucwords(strtolower($guardName))) ?></span>
        <span style="font-family: 'JetBrains Mono', monospace; opacity: 0.85;">(<?= htmlspecialchars($guardStaffId) ?>)</span>
    </div>
    <div class="officer-tag" style="display: flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 700;">
        <span>📍 <?= htmlspecialchars($siteName) ?></span>
    </div>
</div>

<main class="guard-container">

    <?php if (!empty($_SESSION['uss_supervisor_logged_in'])): ?>
        <div style="background: rgba(2, 132, 199, 0.15); border: 1px solid #0284c7; border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
            <div style="font-size: 13px; font-weight: 700; color: #bae6fd; display: flex; align-items: center; gap: 6px;">
                <span>🛡️</span>
                <span>Supervisor Inspection Mode &bull; Site: <?= htmlspecialchars($siteName) ?></span>
            </div>
            <a href="<?= APP_URL ?>/sup/duty.php" style="font-size: 12px; font-weight: 800; color: #38bdf8; text-decoration: none; padding: 4px 10px; background: rgba(2, 132, 199, 0.25); border-radius: 6px;">
                &larr; Back to Supervisor Duty
            </a>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background: rgba(244, 63, 94, 0.2); border: 1px solid #f43f5e; color: #fecdd3; padding: 14px; border-radius: 12px; margin-bottom: 16px; font-size: 14px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form id="incidentForm" method="POST" action="" enctype="multipart/form-data">

        <!-- 1. OFFICIAL FORM HEADER CARD (IMAGE 3 MATCH) -->
        <div class="section-card card-official-header">
            <img src="<?= APP_URL ?>/<?= htmlspecialchars($settings['logo_url']) ?>" alt="Logo" style="height: 64px; margin: 0 auto 12px; display: block;" onerror="this.style.display='none'">
            <div class="doc-title">INCIDENT REPORT</div>
            <div class="doc-subtitle">OFFICIAL SECURITY OPERATIONS FORM</div>
            
            <div class="company-name"><?= htmlspecialchars($settings['company_name']) ?></div>
            <div class="company-meta">
                <?= htmlspecialchars($settings['address_line'] ?: 'P.O. Box No: 61591, Al Maktoum Street, DUBAI | U.A.E') ?><br>
                <a href="mailto:<?= htmlspecialchars($settings['email']) ?>" style="color: var(--g-accent); text-decoration: none;"><?= htmlspecialchars($settings['email']) ?></a>
            </div>

            <div class="draft-autosave-pill">
                <span>📄</span>
                <span>Draft autosaved locally (<span id="draftTime"><?= date('H:i:s') ?></span>)</span>
            </div>
        </div>

        <!-- 2. PATROL METADATA CARD (IMAGE 3 MATCH) -->
        <div class="section-card">
            <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--g-border); padding-bottom: 10px; gap: 10px;">
                    <span style="font-size: 14px; font-weight: 800; color: var(--g-text); white-space: nowrap; flex-shrink: 0;">Site Name :</span>
                    <span style="font-size: 14px; font-weight: 800; color: #0284c7; display: flex; align-items: center; gap: 4px; text-align: right;">
                        📍 <?= htmlspecialchars($siteName) ?>
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--g-border); padding-bottom: 10px; gap: 10px;">
                    <span style="font-size: 14px; font-weight: 800; color: var(--g-text); white-space: nowrap; flex-shrink: 0;">Person Reporting :</span>
                    <span style="font-size: 14px; font-weight: 800; color: var(--g-text); text-transform: capitalize; text-align: right;">
                        <?= htmlspecialchars(ucwords(strtolower($guardName))) ?>
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--g-border); padding-bottom: 10px; gap: 10px;">
                    <span style="font-size: 14px; font-weight: 800; color: var(--g-text); white-space: nowrap; flex-shrink: 0;">Designation :</span>
                    <span style="font-size: 13.5px; font-weight: 800; color: var(--g-text-muted); text-align: right; word-break: break-word;">
                        <?= htmlspecialchars(ucwords(strtolower($guardDesignation))) ?> <span style="font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 700;">(<?= htmlspecialchars($guardStaffId) ?>)</span>
                    </span>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div>
                    <label style="display:block;font-size:13.5px;font-weight:800;color:var(--g-text);margin-bottom:6px;">Date (dd/mm/yyyy) :</label>
                    <input type="date" name="incident_date" class="guard-input" value="<?= htmlspecialchars($currentDate) ?>" required style="font-weight: 800; font-size: 15px;">
                </div>
                <div>
                    <label style="display:block;font-size:13.5px;font-weight:800;color:var(--g-text);margin-bottom:6px;">Time (24h) :</label>
                    <input type="time" name="incident_time" class="guard-input" value="<?= htmlspecialchars($currentTime) ?>" required style="font-weight: 800; font-size: 15px;">
                </div>
            </div>

            <div>
                <label style="display:block;font-size:13.5px;font-weight:800;color:var(--g-text);margin-bottom:6px;">Shift :</label>
                <select name="shift" class="guard-input" style="cursor: pointer; font-weight: 800; font-size: 15px;">
                    <?php foreach ($settings['shift_options'] as $sh): ?>
                        <option value="<?= htmlspecialchars($sh) ?>" <?= $defaultShift === $sh ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sh) ?> Shift
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- 3. INCIDENT PRIORITY LEVEL (NAVY FRAME) -->
        <div class="navy-section-frame">
            <div class="navy-frame-header" style="justify-content: center;">
                <div class="navy-frame-title">Incident Priority Level</div>
            </div>
            <div class="navy-frame-body">
                <div class="priority-pill-grid">
                    <div class="priority-pill-item">
                        <input type="radio" name="priority" id="p_crit" value="Critical">
                        <label class="priority-pill-label p-crit-label" for="p_crit">
                            <span class="priority-box-indicator"></span>
                            <span>Critical</span>
                        </label>
                    </div>
                    <div class="priority-pill-item">
                        <input type="radio" name="priority" id="p_high" value="High">
                        <label class="priority-pill-label p-high-label" for="p_high">
                            <span class="priority-box-indicator"></span>
                            <span>High</span>
                        </label>
                    </div>
                    <div class="priority-pill-item">
                        <input type="radio" name="priority" id="p_med" value="Medium">
                        <label class="priority-pill-label p-med-label" for="p_med">
                            <span class="priority-box-indicator"></span>
                            <span>Medium</span>
                        </label>
                    </div>
                    <div class="priority-pill-item">
                        <input type="radio" name="priority" id="p_low" value="Low" checked>
                        <label class="priority-pill-label p-low-label" for="p_low">
                            <span class="priority-box-indicator"></span>
                            <span>Low</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. DISCIPLINARY CATEGORIES (NAVY FRAME) -->
        <div class="navy-section-frame">
            <div class="navy-frame-header">
                <div class="navy-frame-title">Disciplinary</div>
                <div class="navy-frame-sub">Select Any That Apply</div>
            </div>
            <div class="navy-frame-body">
                <div class="checklist-grid">
                    <?php foreach ($disciplinaryOptions as $dOpt): ?>
                        <label class="check-item">
                            <input type="checkbox" name="disciplinary_reasons[]" value="<?= htmlspecialchars($dOpt) ?>" onchange="saveDraft()">
                            <div class="check-label">
                                <span class="check-box-indicator"></span>
                                <span><?= htmlspecialchars($dOpt) ?></span>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 5. INCIDENT CLASSIFICATION (NAVY FRAME) -->
        <div class="navy-section-frame">
            <div class="navy-frame-header">
                <div class="navy-frame-title">Incident Classification</div>
                <div class="navy-frame-sub">Official Incident Options</div>
            </div>
            <div class="navy-frame-body">
                <div class="checklist-grid">
                    <?php foreach ($incidentOptions as $iOpt): ?>
                        <label class="check-item">
                            <input type="checkbox" name="incident_types[]" value="<?= htmlspecialchars($iOpt) ?>" onchange="saveDraft()">
                            <div class="check-label">
                                <span class="check-box-indicator"></span>
                                <span><?= htmlspecialchars($iOpt) ?></span>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 6. NAVY ACTION FRAME FOR SUMMARY LINE (EXACT MATCH FOR IMAGE 4) -->
        <div class="navy-section-frame">
            <div class="navy-frame-header" style="justify-content: center; text-align: center;">
                <div class="navy-frame-title" style="font-size: 14.5px; font-weight: 900; text-transform: none; text-align: center;">
                    (Explain What, Where, When, Who, Why and How Action Taken)
                </div>
            </div>
            <div class="navy-frame-body">
                <input type="text" id="summaryLine" name="summary_line" class="guard-input" placeholder="Write concise summary line (e.g., Water leakage in B1 park..." value="<?= htmlspecialchars($prefillSummary) ?>" required style="margin-bottom: 0; font-weight: 700; font-size: 15.5px;">
            </div>
        </div>

        <!-- 7. WHO WAS NOTIFIED? (NAVY FRAME) -->
        <div class="navy-section-frame">
            <div class="navy-frame-header">
                <div class="navy-frame-title">Who Was Notified?</div>
                <div class="navy-frame-sub">Emergency &amp; Client Services</div>
            </div>
            <div class="navy-frame-body">
                <div class="checklist-grid" style="margin-bottom: 12px;">
                    <?php foreach ($notifiedOptions as $nOpt): ?>
                        <label class="check-item">
                            <input type="checkbox" name="who_notified[]" value="<?= htmlspecialchars($nOpt) ?>" id="notif_<?= htmlspecialchars(md5($nOpt)) ?>" onchange="toggleNotifiedOther(this); saveDraft();">
                            <div class="check-label">
                                <span class="check-box-indicator"></span>
                                <span><?= htmlspecialchars($nOpt) ?></span>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div id="notifiedOtherBox" style="display: none;">
                    <input type="text" name="notified_other_text" class="guard-input" placeholder="Specify other authority / person notified..." style="margin-bottom: 0;">
                </div>
            </div>
        </div>

        <!-- 8. DESCRIPTION OF INCIDENT (NAVY FRAME WITH RICH FORMATTING TOOLBAR) -->
        <div class="navy-section-frame">
            <div class="navy-frame-header">
                <div class="navy-frame-title">Description Of Incident</div>
                <div class="navy-frame-sub">Detailed Statement Of Occurrence</div>
            </div>
            <div class="navy-frame-body">
                <div class="rich-text-wrapper">
                    <!-- Rich Formatting Toolbar -->
                    <div class="rich-toolbar">
                        <!-- Font Family -->
                        <select class="rt-select" onchange="applyRichFont(this.value)" title="Change Font">
                            <option value="inherit">Font: Default</option>
                            <option value="Arial, sans-serif">Arial</option>
                            <option value="'Times New Roman', serif">Times New Roman</option>
                            <option value="Georgia, serif">Georgia</option>
                            <option value="'Courier New', monospace">Courier New</option>
                            <option value="Verdana, sans-serif">Verdana</option>
                        </select>

                        <!-- Font Size -->
                        <select class="rt-select" onchange="applyRichFontSize(this.value)" title="Change Font Size">
                            <option value="14.5px">Size: 14px</option>
                            <option value="12px">12px (Small)</option>
                            <option value="16px">16px (Medium)</option>
                            <option value="18px">18px (Large)</option>
                            <option value="22px">22px (Heading)</option>
                        </select>

                        <!-- Bold & Italic -->
                        <div class="rt-btn-group">
                            <button type="button" class="rt-btn" onclick="execRichCmd('bold')" title="Bold (Ctrl+B)">
                                <strong>B</strong>
                            </button>
                            <button type="button" class="rt-btn" onclick="execRichCmd('italic')" title="Italic (Ctrl+I)">
                                <em>I</em>
                            </button>
                        </div>

                        <!-- Alignment: Left, Center, Right -->
                        <div class="rt-btn-group">
                            <button type="button" class="rt-btn" onclick="execRichCmd('justifyLeft')" title="Align Left">
                                <svg style="width:14px;height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="19" y2="18"/></svg>
                            </button>
                            <button type="button" class="rt-btn" onclick="execRichCmd('justifyCenter')" title="Align Center">
                                <svg style="width:14px;height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
                            </button>
                            <button type="button" class="rt-btn" onclick="execRichCmd('justifyRight')" title="Align Right">
                                <svg style="width:14px;height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="9" y1="12" x2="21" y2="12"/><line x1="5" y1="18" x2="21" y2="18"/></svg>
                            </button>
                        </div>

                        <!-- Font Color Swatches -->
                        <div class="rt-colors">
                            <span class="rt-color-swatch" style="background:#0f172a;" onclick="applyRichColor('#0f172a')" title="Dark Text"></span>
                            <span class="rt-color-swatch" style="background:#8b0000;" onclick="applyRichColor('#8b0000')" title="Dark Red"></span>
                            <span class="rt-color-swatch" style="background:#0c2356;" onclick="applyRichColor('#0c2356')" title="Navy Blue"></span>
                            <span class="rt-color-swatch" style="background:#15803d;" onclick="applyRichColor('#15803d')" title="Dark Green"></span>
                            <label class="rt-color-picker-label" title="Custom Color Palette">
                                <input type="color" class="rt-color-input" onchange="applyRichColor(this.value)">
                                <span>🎨</span>
                            </label>
                        </div>
                    </div>

                    <!-- Editable Content Box -->
                    <div id="richDescriptionEditor" class="rich-editor-box" contenteditable="true" placeholder="Dear sir,&#10;This is to inform you that..." oninput="syncRichEditor()" onblur="syncRichEditor()"><?= !empty($prefillDescription) ? nl2br(htmlspecialchars($prefillDescription)) : '' ?></div>
                    <textarea id="narrativeDesc" name="description" style="display: none;" required><?= htmlspecialchars($prefillDescription) ?></textarea>
                </div>
            </div>
        </div>

        <!-- 9. ATTACHED PHOTOGRAPHS & EVIDENCE (NAVY FRAME & GALLERY UPLOADER) -->
        <div class="navy-section-frame">
            <div class="navy-frame-header">
                <div class="navy-frame-title">Attached Photographs &amp; Evidence</div>
                <div class="navy-frame-sub">Up To 12 Photos</div>
            </div>
            <div class="navy-frame-body">
                <!-- Dual Action Buttons: Live Camera or Gallery -->
                <div class="photo-btn-grid">
                    <label class="btn-photo-action btn-photo-camera" for="cameraInput">
                        <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        <span>Take Photo</span>
                    </label>
                    <label class="btn-photo-action btn-photo-gallery" for="galleryInput">
                        <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span>Upload Gallery</span>
                    </label>
                </div>

                <!-- Hidden inputs -->
                <input type="file" id="cameraInput" accept="image/*" capture="environment" style="display: none;">
                <input type="file" id="galleryInput" accept="image/*" multiple style="display: none;">
                <input type="file" id="masterPhotoInput" name="photos[]" multiple style="display: none;">

                <!-- Photo counter & Live GPS indicator -->
                <div class="photo-counter-badge">
                    <span id="photoCounterText">0 / 12 Photos Attached</span>
                    <span id="gpsStatusBadge" style="color: #059669; display: flex; align-items: center; gap: 4px;">
                        <span>📍</span> <span id="gpsStatusText">Detecting GPS...</span>
                    </span>
                </div>

                <!-- Mobile Photo Cards (Zero Overlap) -->
                <div id="photoPreviewsList"></div>
            </div>
        </div>

        <!-- Sticky Submit Bar -->
        <div class="sticky-submit-bar">
            <div class="draft-indicator">
                <span class="draft-dot" id="draftDot"></span>
                <span id="draftStatus">Draft Saved</span>
            </div>

            <button type="submit" id="btnSubmit" class="btn-guard-submit">
                <span>Submit Incident Report &rarr;</span>
            </button>
        </div>

    </form>
</main>

<script>
// Toggle 'Others' detail input
function toggleNotifiedOther(checkbox) {
    const box = document.getElementById('notifiedOtherBox');
    if (checkbox.value === 'Others') {
        box.style.display = checkbox.checked ? 'block' : 'none';
    }
}

// Global Photo Store (Array of { id, file, dataUrl, lat, lng, caption })
let photoStore = [];
let detectedLat = '25.1610° N';
let detectedLng = '55.4120° E';

// GPS Auto-Detection
if ('geolocation' in navigator) {
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            detectedLat = (lat >= 0 ? lat.toFixed(4) + '° N' : Math.abs(lat).toFixed(4) + '° S');
            detectedLng = (lng >= 0 ? lng.toFixed(4) + '° E' : Math.abs(lng).toFixed(4) + '° W');
            const gpsText = document.getElementById('gpsStatusText');
            if (gpsText) {
                gpsText.textContent = 'GPS: ' + detectedLat + ', ' + detectedLng;
            }
            // Update any existing photo cards that have not been manually altered
            photoStore.forEach((p, idx) => {
                const latEl = document.getElementById('lat_' + p.id);
                const lngEl = document.getElementById('lng_' + p.id);
                if (latEl && (!p.userEditedLat)) { p.lat = detectedLat; latEl.value = detectedLat; }
                if (lngEl && (!p.userEditedLng)) { p.lng = detectedLng; lngEl.value = detectedLng; }
            });
        },
        function(err) {
            const gpsText = document.getElementById('gpsStatusText');
            if (gpsText) {
                gpsText.textContent = 'GPS Default (Dubai)';
            }
        },
        { enableHighAccuracy: true, timeout: 7000 }
    );
}

const cameraInput = document.getElementById('cameraInput');
const galleryInput = document.getElementById('galleryInput');
const masterPhotoInput = document.getElementById('masterPhotoInput');
const photoPreviewsList = document.getElementById('photoPreviewsList');
const photoCounterText = document.getElementById('photoCounterText');

cameraInput.addEventListener('change', function(e) {
    handleFilesSelected(e.target.files);
    cameraInput.value = '';
});

galleryInput.addEventListener('change', function(e) {
    handleFilesSelected(e.target.files);
    galleryInput.value = '';
});

// Client-side fast image compression (Prevents mobile crashes & upload freezes)
function compressImageFile(file, maxDimension = 1600, quality = 0.82) {
    if (!file.type || !file.type.startsWith('image/')) {
        return Promise.resolve({ file: file, dataUrl: '' });
    }

    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                let width = img.width;
                let height = img.height;

                // Scale down if larger than maxDimension while preserving aspect ratio
                if (width > maxDimension || height > maxDimension) {
                    if (width > height) {
                        height = Math.round((height * maxDimension) / width);
                        width = maxDimension;
                    } else {
                        width = Math.round((width * maxDimension) / height);
                        height = maxDimension;
                    }
                }

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob(function(blob) {
                    if (!blob) {
                        resolve({ file: file, dataUrl: e.target.result });
                        return;
                    }
                    const cleanName = file.name.replace(/\.[^/.]+$/, '') + '.jpg';
                    const compressedFile = new File([blob], cleanName, {
                        type: 'image/jpeg',
                        lastModified: Date.now()
                    });
                    // Compact thumbnail DataURL to avoid high mobile RAM consumption
                    const thumbUrl = canvas.toDataURL('image/jpeg', 0.5);
                    resolve({ file: compressedFile, dataUrl: thumbUrl });
                }, 'image/jpeg', quality);
            };
            img.onerror = function() {
                resolve({ file: file, dataUrl: e.target.result });
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}

async function handleFilesSelected(fileList) {
    const incoming = Array.from(fileList);
    const availableSlots = 12 - photoStore.length;
    if (availableSlots <= 0) {
        alert('Maximum 12 photos reached.');
        return;
    }
    const toAdd = incoming.slice(0, availableSlots);
    if (incoming.length > availableSlots) {
        alert('Added ' + availableSlots + ' photo(s). Maximum 12 photos allowed.');
    }

    // Temporary loading indicator
    photoCounterText.textContent = 'Optimizing ' + toAdd.length + ' photo(s)...';

    for (const file of toAdd) {
        try {
            const optimized = await compressImageFile(file);
            const photoId = 'ph_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
            photoStore.push({
                id: photoId,
                file: optimized.file,
                dataUrl: optimized.dataUrl,
                lat: detectedLat,
                lng: detectedLng,
                caption: '',
                userEditedLat: false,
                userEditedLng: false
            });
        } catch(err) {
            console.error('Photo optimization error:', err);
        }
    }

    renderPhotoCards();
    syncMasterInput();
}

function removePhoto(id) {
    photoStore = photoStore.filter(p => p.id !== id);
    renderPhotoCards();
    syncMasterInput();
}

function updatePhotoCaption(id, val) {
    const item = photoStore.find(p => p.id === id);
    if (item) item.caption = val;
}

function updatePhotoLat(id, val) {
    const item = photoStore.find(p => p.id === id);
    if (item) {
        item.lat = val;
        item.userEditedLat = true;
    }
}

function updatePhotoLng(id, val) {
    const item = photoStore.find(p => p.id === id);
    if (item) {
        item.lng = val;
        item.userEditedLng = true;
    }
}

function syncMasterInput() {
    try {
        const dt = new DataTransfer();
        photoStore.forEach(p => dt.items.add(p.file));
        masterPhotoInput.files = dt.files;
    } catch(e) {}
}

function formatKb(bytes) {
    return (bytes / 1024).toFixed(1) + ' KB';
}

function renderPhotoCards() {
    photoPreviewsList.innerHTML = '';
    photoCounterText.textContent = photoStore.length + ' / 12 Photos Attached';

    photoStore.forEach((p, idx) => {
        const card = document.createElement('div');
        card.className = 'photo-card';
        card.id = 'card_' + p.id;
        card.innerHTML = `
            <div class="photo-card-head">
                <span class="photo-badge-num">Photo #${idx + 1}</span>
                <button type="button" class="btn-remove-photo" onclick="removePhoto('${p.id}')">✕ Remove</button>
            </div>
            <div class="photo-img-box">
                <img src="${p.dataUrl}" alt="Photo #${idx + 1}">
            </div>
            <div class="photo-coords-grid">
                <div>
                    <label class="photo-field-label">📍 Latitude</label>
                    <input type="text" id="lat_${p.id}" name="photo_latitudes[]" class="guard-input photo-coord-input" value="${escapeHtml(p.lat)}" oninput="updatePhotoLat('${p.id}', this.value)" placeholder="e.g. 25.1610° N">
                </div>
                <div>
                    <label class="photo-field-label">📍 Longitude</label>
                    <input type="text" id="lng_${p.id}" name="photo_longitudes[]" class="guard-input photo-coord-input" value="${escapeHtml(p.lng)}" oninput="updatePhotoLng('${p.id}', this.value)" placeholder="e.g. 55.4120° E">
                </div>
            </div>
            <div style="margin-top: 8px;">
                <label class="photo-field-label">Caption / Description</label>
                <input type="text" name="photo_captions[]" class="guard-input photo-caption-input" value="${escapeHtml(p.caption)}" oninput="updatePhotoCaption('${p.id}', this.value)" placeholder="Caption (e.g. Ground floor near block 2)">
            </div>
            <div class="photo-file-meta">
                <span>📁 ${escapeHtml(p.file.name)}</span>
                <span>⚖️ ${formatKb(p.file.size)}</span>
            </div>
        `;
        photoPreviewsList.appendChild(card);
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Rich Text Formatting Helper Functions
function execRichCmd(cmd, val = null) {
    const editor = document.getElementById('richDescriptionEditor');
    editor.focus();
    document.execCommand(cmd, false, val);
    syncRichEditor();
}

function applyRichFont(font) {
    const editor = document.getElementById('richDescriptionEditor');
    editor.focus();
    if (!font || font === 'inherit') {
        document.execCommand('fontName', false, 'inherit');
    } else {
        document.execCommand('fontName', false, font);
    }
    syncRichEditor();
}

function applyRichFontSize(size) {
    const editor = document.getElementById('richDescriptionEditor');
    editor.focus();
    const sel = window.getSelection();
    if (!sel || !sel.rangeCount || sel.isCollapsed) {
        editor.style.fontSize = size;
        syncRichEditor();
        return;
    }
    document.execCommand('fontSize', false, '7');
    const fontTags = editor.querySelectorAll('font[size="7"]');
    fontTags.forEach(f => {
        const span = document.createElement('span');
        span.style.fontSize = size;
        span.innerHTML = f.innerHTML;
        f.parentNode.replaceChild(span, f);
    });
    syncRichEditor();
}

function applyRichColor(color) {
    const editor = document.getElementById('richDescriptionEditor');
    editor.focus();
    document.execCommand('foreColor', false, color);
    syncRichEditor();
}

function syncRichEditor() {
    const editor = document.getElementById('richDescriptionEditor');
    const descInput = document.getElementById('narrativeDesc');
    if (editor && descInput) {
        descInput.value = editor.innerHTML.trim();
        saveDraft();
    }
}

// Offline Draft Auto-Save to LocalStorage
const draftKey = 'uss_guard_draft_<?= htmlspecialchars($siteCode) ?>';
const summaryInput = document.getElementById('summaryLine');
const descInput = document.getElementById('narrativeDesc');
const richEditor = document.getElementById('richDescriptionEditor');
const draftDot = document.getElementById('draftDot');
const draftStatus = document.getElementById('draftStatus');
const draftTimeSpan = document.getElementById('draftTime');

try {
    const saved = localStorage.getItem(draftKey);
    if (saved && !summaryInput.value) {
        const data = JSON.parse(saved);
        if (data.summary) summaryInput.value = data.summary;
        if (data.desc) {
            descInput.value = data.desc;
            if (richEditor) richEditor.innerHTML = data.desc;
        }
        if (data.time) {
            const d = new Date(data.time);
            draftTimeSpan.textContent = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
        }
    }
} catch(e) {}

function saveDraft() {
    draftDot.className = 'draft-dot unsaved';
    draftStatus.textContent = 'Saving...';
    try {
        const now = Date.now();
        localStorage.setItem(draftKey, JSON.stringify({
            summary: summaryInput.value,
            desc: richEditor ? richEditor.innerHTML : descInput.value,
            time: now
        }));
        const d = new Date(now);
        draftTimeSpan.textContent = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });

        setTimeout(() => {
            draftDot.className = 'draft-dot';
            draftStatus.textContent = 'Draft Saved';
        }, 400);
    } catch(e) {}
}

summaryInput.addEventListener('input', saveDraft);

// Clear draft on successful submit
document.getElementById('incidentForm').addEventListener('submit', function(e) {
    syncRichEditor();
    if (!descInput.value.trim() && (!richEditor || !richEditor.innerText.trim())) {
        e.preventDefault();
        alert('Please enter a detailed narrative description before submitting.');
        return false;
    }
    document.getElementById('btnSubmit').disabled = true;
    document.getElementById('btnSubmit').innerHTML = '<span>Uploading Incident Report...</span>';
    try {
        localStorage.removeItem(draftKey);
    } catch(e) {}
});

function toggleGuardTheme() {
    if (typeof openThemePaletteModal === 'function') {
        openThemePaletteModal();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/theme_picker.php'; ?>
</body>
</html>
