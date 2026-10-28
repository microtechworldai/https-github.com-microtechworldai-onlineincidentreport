<?php
/**
 * Universal Security Services - Attendance Roster Subsystem
 * A4 Landscape Print / PDF Engine
 * File: /attendance_roster/exports/PdfExport.php
 */
require_once dirname(__DIR__) . '/config/roster_config.php';
require_once dirname(__DIR__) . '/src/RosterEntry.php';

class PdfExport {
    public static function renderPrintView(int $siteId, int $year, int $month) {
        $data = RosterEntry::getMonthlySheet($siteId, $year, $month);
        $site = $data['site'];
        $daysInMonth = $data['daysInMonth'];
        $staffList = $data['staffList'];
        $entries = $data['entries'];
        $monthName = strtoupper(date('F', mktime(0, 0, 0, $month, 1, $year)));

        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>USS Attendance Roster - <?= htmlspecialchars($site['site_name']) ?> - <?= $monthName ?> <?= $year ?></title>
            <style>
                @page {
                    size: A4 landscape;
                    margin: 8mm;
                }
                body {
                    margin: 0;
                    padding: 0;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Arial Narrow", Arial, sans-serif;
                    font-size: 10px;
                    color: #000000;
                    background: #ffffff;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    page-break-inside: auto;
                }
                tr {
                    page-break-inside: avoid;
                    page-break-after: auto;
                }
                th, td {
                    border: 0.5pt solid #000000;
                    padding: 0;
                    text-align: center;
                    vertical-align: middle;
                    height: 18px;
                }
                .banner-th {
                    background-color: #0f766e !important;
                    color: #ffffff !important;
                    font-size: 13px;
                    font-weight: 800;
                    height: 32px !important;
                    letter-spacing: 0.5px;
                    text-transform: uppercase;
                    -webkit-print-color-adjust: exact;
                }
                .th-meta {
                    background-color: #f8fafc;
                    font-weight: 800;
                    font-size: 10.5px;
                    height: 22px;
                    -webkit-print-color-adjust: exact;
                }
                .th-sub {
                    background-color: #f1f5f9;
                    font-weight: 800;
                    font-size: 9.5px;
                    height: 18px;
                    -webkit-print-color-adjust: exact;
                }
                .th-weekend {
                    background-color: #fef3c7 !important;
                    color: #92400e !important;
                    font-weight: 800;
                    -webkit-print-color-adjust: exact;
                }
                .col-emp { width: 55px; }
                .col-name { width: 140px; text-align: left; padding-left: 4px; font-weight: 700; }
                .col-site { width: 110px; text-align: left; padding-left: 4px; }
                .col-day { width: 22px; }
                .tag-D { background-color: #d1fae5; color: #047857; font-weight: 700; -webkit-print-color-adjust: exact; }
                .tag-N { background-color: #dbeafe; color: #1d4ed8; font-weight: 700; -webkit-print-color-adjust: exact; }
                .tag-OFF { background-color: #fee2e2; color: #b91c1c; font-weight: 700; -webkit-print-color-adjust: exact; }
                .tag-S { background-color: #fef3c7; color: #b45309; font-weight: 700; -webkit-print-color-adjust: exact; }
                .tag-B { background-color: #ede9fe; color: #6d28d9; font-weight: 700; -webkit-print-color-adjust: exact; }
                
                .deploy-ok { background-color: #dff3e4 !important; color: #064e3b !important; font-weight: 800; -webkit-print-color-adjust: exact; }
                .deploy-less { background-color: #fadbd8 !important; color: #7f1d1d !important; font-weight: 800; -webkit-print-color-adjust: exact; }
                .deploy-over { background-color: #fff3c4 !important; color: #78350f !important; font-weight: 800; -webkit-print-color-adjust: exact; }
                .deploy-total { background-color: #e5e7eb !important; font-weight: 800; -webkit-print-color-adjust: exact; }

                .lbl-sum { text-align: right; padding-right: 6px; font-weight: 800; font-size: 10px; }
                
                .signatures {
                    margin-top: 15px;
                    display: flex;
                    justify-content: space-between;
                    gap: 15px;
                    page-break-inside: avoid;
                }
                .sig-box {
                    flex: 1;
                    border: 0.5pt solid #000000;
                    padding: 8px 12px;
                    height: 55px;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                }
                .sig-title { font-weight: 800; font-size: 9.5px; text-transform: uppercase; }
                .sig-line { display: flex; justify-content: space-between; font-size: 9px; color: #475569; }
                
                @media print {
                    .no-print { display: none !important; }
                }
            </style>
        </head>
        <body>
            <div class="no-print" style="padding:10px;background:#f1f5f9;border-bottom:1px solid #cbd5e1;text-align:right;">
                <button onclick="window.print()" style="padding:6px 16px;background:#0f766e;color:#fff;border:none;border-radius:4px;font-weight:bold;cursor:pointer;">🖨 Print / Save as PDF</button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th colspan="<?= $daysInMonth + 3 ?>" class="banner-th">
                            ULTIMATE SECURITY SERVICES &bull; ATTENDANCE ROSTER FOR <?= htmlspecialchars($site['site_name']) ?> &bull; <?= $monthName ?> - <?= $year ?>
                        </th>
                    </tr>
                    <tr>
                        <th rowspan="2" class="th-meta col-emp">Emp No</th>
                        <th rowspan="2" class="th-meta col-name">Guard Name</th>
                        <th rowspan="2" class="th-meta col-site">Site Name</th>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                            $w = strtoupper(substr(date('D', mktime(0, 0, 0, $month, $d, $year)), 0, 3));
                            $isWk = in_array($w, ['FRI', 'SUN']);
                        ?>
                            <th class="th-meta col-day <?= $isWk ? 'th-weekend' : '' ?>"><?= $d ?></th>
                        <?php endfor; ?>
                    </tr>
                    <tr>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                            $w = strtoupper(substr(date('D', mktime(0, 0, 0, $month, $d, $year)), 0, 3));
                            $isWk = in_array($w, ['FRI', 'SUN']);
                        ?>
                            <th class="th-sub col-day <?= $isWk ? 'th-weekend' : '' ?>"><?= $w ?></th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffList as $st): $sid = $st['id']; ?>
                        <tr>
                            <td class="col-emp"><?= htmlspecialchars($st['emp_no']) ?></td>
                            <td class="col-name"><?= htmlspecialchars($st['name']) ?></td>
                            <td class="col-site"><?= htmlspecialchars($site['site_name']) ?></td>
                            <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                                $code = $entries[$sid][$d]['code'] ?? '';
                                $cls = $code ? 'tag-' . $code : '';
                            ?>
                                <td class="col-day <?= $cls ?>"><?= htmlspecialchars($code) ?></td>
                            <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>

                    <!-- Bottom Summary Rows -->
                    <tr>
                        <td colspan="3" class="lbl-sum">REQUIRED DAY:</td>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                            <td><?= $data['requirements'][$d]['required_day'] ?></td>
                        <?php endfor; ?>
                    </tr>
                    <tr>
                        <td colspan="3" class="lbl-sum">REQUIRED NIGHT:</td>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                            <td><?= $data['requirements'][$d]['required_night'] ?></td>
                        <?php endfor; ?>
                    </tr>
                    <tr>
                        <td colspan="3" class="lbl-sum">ACTUAL DAY SHIFT (D):</td>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                            $act = $data['dailySummary'][$d]['act_day'];
                            $req = $data['requirements'][$d]['required_day'];
                            $st = ($req === 0 && $act === 0) ? 'deploy-total' : ($act === $req ? 'deploy-ok' : ($act < $req ? 'deploy-less' : 'deploy-over'));
                            $sym = ($req === 0) ? '' : ($act === $req ? ' =' : ($act < $req ? ' ▼' . ($req - $act) : ' ▲' . ($act - $req)));
                        ?>
                            <td class="<?= $st ?>"><?= $act . $sym ?></td>
                        <?php endfor; ?>
                    </tr>
                    <tr>
                        <td colspan="3" class="lbl-sum">ACTUAL NIGHT SHIFT (N):</td>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                            $act = $data['dailySummary'][$d]['act_night'];
                            $req = $data['requirements'][$d]['required_night'];
                            $st = ($req === 0 && $act === 0) ? 'deploy-total' : ($act === $req ? 'deploy-ok' : ($act < $req ? 'deploy-less' : 'deploy-over'));
                            $sym = ($req === 0) ? '' : ($act === $req ? ' =' : ($act < $req ? ' ▼' . ($req - $act) : ' ▲' . ($act - $req)));
                        ?>
                            <td class="<?= $st ?>"><?= $act . $sym ?></td>
                        <?php endfor; ?>
                    </tr>
                    <tr>
                        <td colspan="3" class="lbl-sum deploy-total">TOTAL ON DUTY (D + N):</td>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                            <td class="deploy-total"><?= $data['dailySummary'][$d]['act_total'] ?></td>
                        <?php endfor; ?>
                    </tr>
                </tbody>
            </table>

            <div class="signatures">
                <div class="sig-box">
                    <div class="sig-title">Prepared By (Site Supervisor)</div>
                    <div class="sig-line"><span>Name & Signature: _______________________</span><span>Date: ____/____/2026</span></div>
                </div>
                <div class="sig-box">
                    <div class="sig-title">Checked By (Operations Manager)</div>
                    <div class="sig-line"><span>Name & Signature: _______________________</span><span>Date: ____/____/2026</span></div>
                </div>
                <div class="sig-box">
                    <div class="sig-title">Approved By (Security Director)</div>
                    <div class="sig-line"><span>Name & Signature: _______________________</span><span>Date: ____/____/2026</span></div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

