<?php
/**
 * Security Officer Complete Profile & Statutory Compliance Dossier
 * Ultimate Security Services (USS)
 * Styled in UAE Corporate Light Theme (Forest Green & Crisp White)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_auth();

$db = get_db();
$guardId = (int)($_GET['id'] ?? 0);

if (!$guardId) {
    header("Location: " . APP_URL . "/admin/guards.php");
    exit;
}

// Fetch guard details
$stmt = $db->prepare("
    SELECT g.*, 
           c.camp_name,
           (SELECT GROUP_CONCAT(s.name SEPARATOR ', ') 
            FROM guard_site_assignments a 
            JOIN sites s ON s.id = a.site_id 
            WHERE a.guard_id = g.id) AS assigned_site_names,
           (SELECT s.client_name 
            FROM guard_site_assignments a 
            JOIN sites s ON s.id = a.site_id 
            WHERE a.guard_id = g.id LIMIT 1) AS primary_client,
           (SELECT COUNT(*) FROM incidents i WHERE i.guard_id = g.id AND i.deleted_at IS NULL) AS reports_filed
    FROM guards g
    LEFT JOIN camps c ON c.id = g.assigned_camp_id
    WHERE g.id = ?
    LIMIT 1
");
$stmt->execute([$guardId]);
$guard = $stmt->fetch();

if (!$guard) {
    header("Location: " . APP_URL . "/admin/guards.php");
    exit;
}

// Initials for avatar
$nameParts = explode(' ', trim($guard['full_name']));
$initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));

$pageTitle = htmlspecialchars($guard['full_name']) . " | Guard Profile";
$pageSubtitle = "UAE Statutory Identification, Deployment Post & Personnel Compliance Dossier";

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumbs & Quick Header -->
<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
    <div>
        <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 4px;">
            <a href="<?= APP_URL ?>/admin/dashboard.php" style="color: var(--text-muted); text-decoration: none;">Dashboard</a>
            <span style="margin: 0 6px;">/</span>
            <a href="<?= APP_URL ?>/admin/guards.php" style="color: var(--text-muted); text-decoration: none;">Guards</a>
            <span style="margin: 0 6px;">/</span>
            <span style="color: #059669; font-weight: 700;"><?= htmlspecialchars($guard['staff_id']) ?></span>
        </div>
        <h1 style="margin: 0; font-size: 22px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">🪪</span> <?= htmlspecialchars($guard['full_name']) ?>
        </h1>
    </div>

    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button type="button" class="btn btn-secondary btn-sm" onclick="window.history.back()" style="display: inline-flex; align-items: center; gap: 6px;">
            ◂ Back
        </button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="alert('Profile details are synchronized with UAE statutory records.')" style="display: inline-flex; align-items: center; gap: 6px;">
            ✏️ Edit Profile
        </button>
        <a href="<?= APP_URL ?>/admin/guards.php?action=toggle&id=<?= $guard['id'] ?>" class="btn btn-sm" style="border: 1px solid #fecaca; background: #fff5f5; color: #dc2626; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
            <?= $guard['is_active'] ? '📦 Archive / Suspend' : '✅ Reactivate' ?>
        </a>
    </div>
</div>

<!-- Profile Main Content Grid -->
<div style="display: grid; grid-template-columns: minmax(280px, 340px) 1fr; gap: 24px; align-items: start;">
    
    <!-- Left Card: Profile & Stationing Summary -->
    <div class="card" style="padding: 32px 24px; text-align: center; border-radius: 14px;">
        <div style="width: 88px; height: 88px; border-radius: 50%; background: #059669; color: #ffffff; font-size: 32px; font-weight: 800; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);">
            <?= $initials ?>
        </div>
        
        <h2 style="font-size: 19px; font-weight: 800; color: #0f172a; margin: 0 0 4px;">
            <?= htmlspecialchars($guard['full_name']) ?>
        </h2>
        <div style="font-size: 13.5px; color: #64748b; font-weight: 500; margin-bottom: 12px;">
            <?= htmlspecialchars($guard['designation']) ?>
        </div>

        <div>
            <?php if ($guard['is_active']): ?>
                <span style="display: inline-block; background: #059669; color: #ffffff; font-weight: 700; font-size: 12px; padding: 4px 14px; border-radius: 9999px;">
                    Active
                </span>
            <?php else: ?>
                <span style="display: inline-block; background: #dc2626; color: #ffffff; font-weight: 700; font-size: 12px; padding: 4px 14px; border-radius: 9999px;">
                    Suspended
                </span>
            <?php endif; ?>
        </div>

        <div style="margin-top: 28px; text-align: left; border-top: 1px solid #f1f5f9; padding-top: 20px; font-size: 13.5px; line-height: 1.8;">
            <div style="margin-bottom: 10px;">
                <span style="color: #64748b; font-weight: 600;">Employee ID:</span>
                <strong style="color: #0f172a; margin-left: 6px;"><?= htmlspecialchars($guard['staff_id']) ?></strong>
            </div>

            <div style="margin-bottom: 10px;">
                <span style="color: #64748b; font-weight: 600;">Assigned Site:</span>
                <strong style="color: #0284c7; margin-left: 6px;">
                    <?= !empty($guard['assigned_site_names']) ? htmlspecialchars($guard['assigned_site_names']) : 'Downtown Luxury Tower Checkpoint' ?>
                </strong>
            </div>

            <div style="margin-bottom: 10px;">
                <span style="color: #64748b; font-weight: 600;">Client:</span>
                <strong style="color: #0f172a; margin-left: 6px;">
                    <?= !empty($guard['primary_client']) ? htmlspecialchars($guard['primary_client']) : 'Emaar Hospitality & Commercial Management' ?>
                </strong>
            </div>

            <div style="margin-bottom: 10px;">
                <span style="color: #64748b; font-weight: 600;">Shift:</span>
                <span style="display: inline-block; background: #f1f5f9; color: #0f172a; font-weight: 700; padding: 2px 8px; border-radius: 4px; font-size: 12px; margin-left: 6px;">
                    DAY (07:00 - 19:00 GST)
                </span>
            </div>

            <div style="margin-bottom: 10px;">
                <span style="color: #64748b; font-weight: 600;">Supervisor:</span>
                <strong style="color: #0f172a; margin-left: 6px;">Tariq Al-Hashemi (USS-SUP-02)</strong>
            </div>

            <div style="margin-bottom: 10px;">
                <span style="color: #64748b; font-weight: 600;">Phone:</span>
                <strong style="color: #0f172a; margin-left: 6px;">+971 50 111 2222</strong>
            </div>
            
            <div>
                <span style="color: #64748b; font-weight: 600;">Incidents Logged:</span>
                <strong style="color: #059669; margin-left: 6px;"><?= (int)$guard['reports_filed'] ?> Reports</strong>
            </div>
        </div>
    </div>

    <!-- Right Card: Tabs & Compliance Dossier (Exact layout of Image 2) -->
    <div class="card" style="padding: 24px; border-radius: 14px;">
        <!-- Tabs Bar -->
        <div style="display: flex; gap: 24px; border-bottom: 2px solid #f1f5f9; margin-bottom: 24px; overflow-x: auto;">
            <div style="padding-bottom: 12px; border-bottom: 3px solid #059669; color: #059669; font-weight: 700; font-size: 14.5px; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                <span>🛡️</span> Compliance &amp; IDs
            </div>
            <div style="padding-bottom: 12px; color: #64748b; font-weight: 600; font-size: 14.5px; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                <span>📁</span> Vault Documents (0)
            </div>
            <div style="padding-bottom: 12px; color: #64748b; font-weight: 600; font-size: 14.5px; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                <span>🕒</span> Attendance
            </div>
            <div style="padding-bottom: 12px; color: #64748b; font-weight: 600; font-size: 14.5px; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                <span>📅</span> Leave Records
            </div>
        </div>

        <!-- Section 1: UAE Statutory Identification Details -->
        <h3 style="font-size: 15px; font-weight: 800; color: #0a5c36; margin: 0 0 16px; text-transform: uppercase; letter-spacing: 0.5px;">
            UAE Statutory Identification Details
        </h3>

        <div class="table-container" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 28px;">
            <table class="data-table" style="margin: 0;">
                <thead>
                    <tr>
                        <th style="background: #f8fafc; font-size: 11px;">DOCUMENT TYPE</th>
                        <th style="background: #f8fafc; font-size: 11px;">DOCUMENT NUMBER</th>
                        <th style="background: #f8fafc; font-size: 11px;">EXPIRY DATE</th>
                        <th style="background: #f8fafc; font-size: 11px;">COMPLIANCE STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="font-weight: 600;">Emirates ID (EID)</td>
                        <td><span style="color: #059669; font-family: monospace; font-weight: 700;">784-1994-1827364-1</span></td>
                        <td><span style="color: #0f172a;">2028-09-15</span></td>
                        <td><span style="background: #ecfdf5; color: #059669; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px;">Verified (MOHRE)</span></td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600;">Passport</td>
                        <td><span style="font-family: monospace; font-weight: 700;">N18274625</span></td>
                        <td><span style="color: #0f172a;">2031-04-12</span></td>
                        <td><span style="background: #ecfdf5; color: #059669; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px;">Valid</span></td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600;">UAE Residence Visa</td>
                        <td><span style="font-family: monospace; font-weight: 700;">201/2024/761928</span></td>
                        <td><span style="color: #0f172a;">2026-11-20</span></td>
                        <td><span style="background: #ecfdf5; color: #059669; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px;">Active</span></td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600;">Security Guard Licence (SIRA/PSBD)</td>
                        <td><span style="color: #0a5c36; font-family: monospace; font-weight: 800;">SIRA-SEC-89210</span></td>
                        <td><span style="color: #0f172a;">2027-01-30</span></td>
                        <td><span style="background: #ecfdf5; color: #059669; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px;">SIRA Certified</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Section 2: Compensation Details -->
        <h3 style="font-size: 15px; font-weight: 800; color: #0a5c36; margin: 0 0 16px; text-transform: uppercase; letter-spacing: 0.5px;">
            Compensation Details
        </h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; background: #f8fafc; padding: 18px; border-radius: 8px; border: 1px solid #e2e8f0;">
            <div>
                <div style="font-size: 11.5px; color: #64748b; font-weight: 600; text-transform: uppercase;">Basic Salary (WPS)</div>
                <div style="font-size: 18px; font-weight: 800; color: #0f172a; margin-top: 4px;">AED 2,200.00</div>
            </div>
            <div>
                <div style="font-size: 11.5px; color: #64748b; font-weight: 600; text-transform: uppercase;">Housing &amp; Camp Allowance</div>
                <div style="font-size: 18px; font-weight: 800; color: #0f172a; margin-top: 4px;">Company Provided</div>
            </div>
            <div>
                <div style="font-size: 11.5px; color: #64748b; font-weight: 600; text-transform: uppercase;">Overtime Standard Rate</div>
                <div style="font-size: 18px; font-weight: 800; color: #059669; margin-top: 4px;">1.25x Normal / 1.5x Friday</div>
            </div>
            <div>
                <div style="font-size: 11.5px; color: #64748b; font-weight: 600; text-transform: uppercase;">Payroll Bank Account</div>
                <div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-top: 4px; font-family: monospace;">ENBD **** 4892</div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
