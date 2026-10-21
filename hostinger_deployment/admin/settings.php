<?php
/**
 * Company Branding & System Configuration
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$pageTitle = "System & Branding Settings";
$pageSubtitle = "Configure company details, logo, timezones, and report checklist options";
$db = get_db();
$msg = '';
$msgType = 'success';

// Handle Save Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $companyName = trim($_POST['company_name'] ?? 'ULTIMATE SECURITY SERVICES');
    $tagline = trim($_POST['tagline'] ?? '');
    $address = trim($_POST['address_line'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $timezone = trim($_POST['timezone'] ?? 'Asia/Dubai');
    $remarksMinLength = max(1, intval($_POST['supervisor_remarks_min_length'] ?? 20));

    // Parse textarea lines to JSON arrays
    $shifts = array_filter(array_map('trim', explode("\n", $_POST['shift_options'] ?? '')));
    $causes = array_filter(array_map('trim', explode("\n", $_POST['cause_options'] ?? '')));
    $disciplinary = array_filter(array_map('trim', explode("\n", $_POST['disciplinary_options'] ?? '')));
    $incidents = array_filter(array_map('trim', explode("\n", $_POST['incident_options'] ?? '')));
    $notified = array_filter(array_map('trim', explode("\n", $_POST['notified_options'] ?? '')));

    // Handle Logo Replacement (Upload, Custom URL, or Reset)
    $logoUrl = null;
    if (!empty($_POST['reset_default_logo'])) {
        $logoUrl = 'assets/images/logo.png';
    } elseif (!empty($_FILES['company_logo']['name'])) {
        $logoTmp = $_FILES['company_logo']['tmp_name'];
        $logoExt = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
        if (in_array($logoExt, ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif'])) {
            $destPath = UPLOADS_PATH . '/logo_' . time() . '.' . $logoExt;
            if (move_uploaded_file($logoTmp, $destPath)) {
                $logoUrl = 'uploads/' . basename($destPath);
            }
        }
    } elseif (!empty($_POST['logo_url_manual'])) {
        $logoUrl = trim($_POST['logo_url_manual']);
    }

    $sql = "
        UPDATE system_settings SET
            company_name = ?,
            tagline = ?,
            address_line = ?,
            phone = ?,
            email = ?,
            website = ?,
            timezone = ?,
            supervisor_remarks_min_length = ?,
            shift_options = ?,
            cause_options = ?,
            disciplinary_options = ?,
            incident_options = ?,
            notified_options = ?,
            " . ($logoUrl !== null ? "logo_url = " . $db->quote($logoUrl) . ", " : "") . "
            updated_at = NOW()
        WHERE id = 'default-settings'
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $companyName,
        $tagline,
        $address,
        $phone,
        $email,
        $website,
        $timezone,
        $remarksMinLength,
        json_encode(array_values($shifts), JSON_UNESCAPED_UNICODE),
        json_encode(array_values($causes), JSON_UNESCAPED_UNICODE),
        json_encode(array_values($disciplinary), JSON_UNESCAPED_UNICODE),
        json_encode(array_values($incidents), JSON_UNESCAPED_UNICODE),
        json_encode(array_values($notified), JSON_UNESCAPED_UNICODE)
    ]);

    log_audit('UPDATE_SETTINGS', 'SETTINGS', 'default-settings', 'Updated system branding, options and supervisor remarks minimum length');
    $msg = "System settings successfully updated!";
}

$settings = get_system_settings();

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data">
    <input type="hidden" name="save_settings" value="1">

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">

        <!-- Left: Company Identity -->
        <div class="card">
            <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 18px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 10px;">
                🏢 Company Branding & Details
            </h3>

            <div class="form-group">
                <label class="form-label">Official Company Name (Appears on Dossier Headers)</label>
                <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($settings['company_name']) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Tagline</label>
                <input type="text" name="tagline" class="form-control" value="<?= htmlspecialchars($settings['tagline'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Official Postal Address</label>
                <textarea name="address_line" class="form-control" rows="2"><?= htmlspecialchars($settings['address_line'] ?? '') ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Operations Hotline</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($settings['phone'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Operations Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($settings['email'] ?? '') ?>">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Company Website URL</label>
                    <input type="text" name="website" class="form-control" value="<?= htmlspecialchars($settings['website'] ?? '') ?>" placeholder="https://www.sprinzo.in">
                </div>

                <div class="form-group">
                    <label class="form-label">Server Timezone</label>
                    <input type="text" name="timezone" class="form-control" value="<?= htmlspecialchars($settings['timezone'] ?? 'Asia/Dubai') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Supervisor Remarks Min Character Length</label>
                    <input type="number" name="supervisor_remarks_min_length" min="1" max="500" class="form-control" value="<?= htmlspecialchars((string)($settings['supervisor_remarks_min_length'] ?? 20)) ?>">
                    <span style="font-size: 11.5px; color: var(--text-dim); display: block; margin-top: 4px;">
                        Enforces minimum characters required before a supervisor can submit Time Out and site visit report (default: 20).
                    </span>
                </div>
            </div>

            <!-- Logo Upload & Replacement -->
            <div class="form-group">
                <label class="form-label">Company Logo (Replaces logo on Guard Login, Mobile Forms, and Official PDF Dossiers)</label>
                <div style="background: #ffffff !important; padding: 18px; border-radius: 10px; border: 1px solid #cbd5e1; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); margin-top: 6px;">
                    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 14px;">
                        <div style="background: #ffffff; padding: 10px 18px; border-radius: 8px; border: 1px solid #cbd5e1; text-align: center; min-width: 140px;">
                            <img id="logoPreviewImg" src="<?= APP_URL ?>/<?= htmlspecialchars($settings['logo_url']) ?>" alt="Company Logo" style="max-height: 52px; max-width: 160px; object-fit: contain; display: block; margin: 0 auto;">
                        </div>
                        <div>
                            <span style="font-size: 13.5px; font-weight: 800; color: #0f172a !important;">Current Active Logo</span>
                            <div style="font-size: 12px; font-weight: 600; color: #64748b !important; margin-top: 3px;">Path: <?= htmlspecialchars($settings['logo_url']) ?></div>
                        </div>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 12.5px; font-weight: 700; color: #0f172a !important; margin-bottom: 5px;">Upload New Logo File</label>
                        <input type="file" name="company_logo" id="logoFileInput" class="form-control" accept="image/*" onchange="previewNewLogo(this)" style="background: #ffffff !important; color: #0f172a !important; border: 1px solid #cbd5e1 !important;">
                    </div>

                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 12.5px; font-weight: 700; color: #0f172a !important; margin-bottom: 5px;">Or Specify Logo Path / URL</label>
                        <input type="text" name="logo_url_manual" class="form-control" placeholder="e.g. assets/images/logo.png or uploads/your_logo.png" value="<?= htmlspecialchars($settings['logo_url']) ?>" style="background: #ffffff !important; color: #0f172a !important; border: 1px solid #cbd5e1 !important;">
                    </div>

                    <label style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 600; color: #334155 !important; cursor: pointer; margin-top: 10px;">
                        <input type="checkbox" name="reset_default_logo" value="1">
                        <span>Reset to system default USS logo (assets/images/logo.png)</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Right: Configurable Dropdowns & Checklists -->
        <div class="card">
            <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 18px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 10px;">
                ☑️ Form Checkbox & Category Config
            </h3>
            <p style="font-size: 12px; color: var(--text-dim); margin-bottom: 16px;">Enter one option per line. These dynamically populate the guard patrol submission form and official PDF checkboxes.</p>

            <div class="form-group">
                <label class="form-label">Incident Category Classifications</label>
                <textarea name="incident_options" class="form-control" rows="5"><?= htmlspecialchars(implode("\n", $settings['incident_options'])) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Disciplinary Reasons</label>
                <textarea name="disciplinary_options" class="form-control" rows="4"><?= htmlspecialchars(implode("\n", $settings['disciplinary_options'])) ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Main Causes</label>
                    <textarea name="cause_options" class="form-control" rows="3"><?= htmlspecialchars(implode("\n", $settings['cause_options'])) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Shifts</label>
                    <textarea name="shift_options" class="form-control" rows="3"><?= htmlspecialchars(implode("\n", $settings['shift_options'])) ?></textarea>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Authorities Notified</label>
                <textarea name="notified_options" class="form-control" rows="3"><?= htmlspecialchars(implode("\n", $settings['notified_options'])) ?></textarea>
            </div>
        </div>

    </div>

    <div style="margin-top: 24px; text-align: right;">
        <button type="submit" class="btn btn-primary" style="padding: 12px 28px; font-size: 15px;">
            💾 Save All Settings
        </button>
    </div>
</form>

<script>
function previewNewLogo(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('logoPreviewImg').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
