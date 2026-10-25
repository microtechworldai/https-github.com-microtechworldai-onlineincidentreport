<?php
/**
 * ====================================================================
 * ULTIMATE SECURITY SERVICES (USS) - ENTERPRISE 500 DATA GENERATOR
 * Seeds 500 physical patrol checkpoints with cryptographic QR tokens,
 * 50 supervisors, 250 security guards, camp checkpoints, and active
 * site visits with completed Time In and Time Out records.
 * ====================================================================
 */
@ini_set('memory_limit', '512M');
@set_time_limit(600);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Allow execution from CLI or authenticated Super Admin
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    require_admin_auth();
}

$db = get_db();
$pageTitle = "Enterprise 500 Checkpoint & Staff Seeder";
$pageSubtitle = "Bulk generate 500 patrol sites, cryptographic QR tokens, guards, supervisors, and visit records";

$message = '';
$messageType = 'info';
$stats = null;

// Function to generate realistic Dubai / UAE building names
function generate_building_names($count = 500) {
    $zones = [
        'Downtown Dubai', 'Business Bay', 'Dubai Marina', 'JLT Cluster', 'Palm Jumeirah',
        'DIFC Financial Centre', 'Deira Commercial District', 'Bur Dubai Heritage Quarter',
        'Al Barsha Commercial', 'JVC District', 'Dubai Silicon Oasis', 'Dubai Hills Estate',
        'City Walk Boulevard', 'Al Quoz Industrial Park', 'Sharjah Al Majaz Waterfront',
        'Sharjah Corniche', 'Abu Dhabi Corniche', 'Al Reem Island Central', 'Yas Island Plaza',
        'Mirdif Hills', 'Dubai Creek Harbour', 'Al Wasl Boulevard'
    ];

    $prefixes = [
        'Burj', 'Al Manara', 'Marina', 'Silver', 'Golden', 'Royal', 'Emirates', 'Al Zahra',
        'Oasis', 'Green', 'Crystal', 'Prime', 'Grand', 'Elite', 'Towering', 'Falcon', 'Pearl',
        'Sapphire', 'Diamond', 'Crown', 'Skyline', 'Horizon', 'Prestige', 'Apex', 'Zenith',
        'Harmony', 'Summit', 'Majestic', 'Signature', 'Imperial', 'Vanguard', 'Pinnacle'
    ];

    $types = [
        'Tower', 'Plaza', 'Heights', 'Residence', 'Centre', 'Complex', 'Executive Suites',
        'Pavilion', 'Boulevard', 'Gate', 'Court', 'Hub', 'Point', 'View', 'Park', 'Square'
    ];

    $list = [];
    $totalZones = count($zones);
    $totalPrefixes = count($prefixes);
    $totalTypes = count($types);

    for ($i = 1; $i <= $count; $i++) {
        $p = $prefixes[($i * 7 + 3) % $totalPrefixes];
        $t = $types[($i * 11 + 5) % $totalTypes];
        $z = $zones[($i * 13 + 7) % $totalZones];
        $sector = ceil($i / 25);
        $unit = str_pad($i, 3, '0', STR_PAD_LEFT);
        
        $name = "{$p} {$t} {$unit} ({$z})";
        $code = "USS-DXB-" . str_pad($i, 3, '0', STR_PAD_LEFT);
        
        // Realistic coordinates across Dubai (Lat ~25.0 to 25.3, Lng ~55.1 to 55.4)
        $lat = number_format(25.050000 + (($i * 137) % 2500) / 10000, 6, '.', '');
        $lng = number_format(55.150000 + (($i * 193) % 2500) / 10000, 6, '.', '');
        
        $siteType = ($i % 5 === 0) ? 'Hospital' : (($i % 2 === 0) ? 'Commercial' : 'Residential');
        
        $list[] = [
            'num' => $i,
            'name' => $name,
            'code' => $code,
            'type' => $siteType,
            'address' => "Plot {$unit}, Sector {$sector}, {$z}, United Arab Emirates",
            'lat' => $lat,
            'lng' => $lng,
            'client_name' => "Emaar & Nakheel Joint Management",
            'client_contact' => "+971 4 " . rand(200, 899) . " " . rand(1000, 9999)
        ];
    }
    return $list;
}

// Helper to inspect table columns safely
function get_table_columns(PDO $db, string $table): array {
    try {
        return $db->query("DESCRIBE {$table}")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return [];
    }
}

// Bulk seeder logic
function run_enterprise_seed(PDO $db) {
    $createdSites = 0;
    $createdGuards = 0;
    $createdSupervisors = 0;
    $createdVisits = 0;
    
    $sitesCols = get_table_columns($db, 'sites');
    $campsCols = get_table_columns($db, 'camps');
    $guardsCols = get_table_columns($db, 'guards');
    $visitsCols = get_table_columns($db, 'supervisor_visits');
    $dutiesCols = get_table_columns($db, 'supervisor_duties');

    // 1. Ensure Default Operational Camp Checkpoint
    $campToken = 'camp_uss_hq_checkpoint_master_token_2026';
    $campId = 1;
    if (!empty($campsCols)) {
        $cCheck = $db->query("SELECT id FROM camps WHERE camp_code = 'USS-CAMP-01' LIMIT 1")->fetch();
        if (!$cCheck) {
            $campFields = ['camp_code', 'camp_name', 'location', 'qr_token', 'is_active', 'created_at'];
            $campPlaceholders = ['?', '?', '?', '?', '1', 'NOW()'];
            $campValues = ['USS-CAMP-01', 'USS Central Command Camp & Operations Depot', 'Al Quoz Industrial 3, Dubai, UAE', $campToken];
            
            if (in_array('qr_token_hash', $campsCols)) {
                $campFields[] = 'qr_token_hash';
                $campPlaceholders[] = '?';
                $campValues[] = hash('sha256', $campToken);
            }
            if (in_array('qr_token_encrypted', $campsCols)) {
                $campFields[] = 'qr_token_encrypted';
                $campPlaceholders[] = '?';
                $campValues[] = encrypt_field($campToken);
            }

            $sqlCamp = "INSERT INTO camps (" . implode(', ', $campFields) . ") VALUES (" . implode(', ', $campPlaceholders) . ")";
            $stmtCamp = $db->prepare($sqlCamp);
            $stmtCamp->execute($campValues);
            $campId = (int)$db->lastInsertId();
        } else {
            $campId = (int)$cCheck['id'];
        }
    }

    // 2. Seed 500 Physical Sites
    $sitesList = generate_building_names(500);
    
    // Check which token columns exist in sites
    $hasActiveHash = in_array('active_qr_token_hash', $sitesCols);
    $hasActiveEnc = in_array('active_qr_token_encrypted', $sitesCols);
    $hasSupToken = in_array('supervisor_qr_token', $sitesCols);
    $hasSupHash = in_array('supervisor_qr_token_hash', $sitesCols);
    $hasSupEnc = in_array('supervisor_qr_token_encrypted', $sitesCols);
    $hasTokenHmac = in_array('token_hmac', $sitesCols);
    $hasTokenEnc = in_array('token_encrypted', $sitesCols);
    $hasGeofence = in_array('geofence_radius_meters', $sitesCols);

    // Fetch existing site codes to avoid duplicate inserts
    $existingCodes = $db->query("SELECT code, id FROM sites")->fetchAll(PDO::FETCH_KEY_PAIR);

    $db->beginTransaction();

    foreach ($sitesList as $s) {
        $guardToken = 'uss_guard_' . strtolower(str_replace('-', '_', $s['code'])) . '_' . substr(md5($s['code'] . 'guard_seed_salt_2026'), 0, 16);
        $supToken = 'uss_sup_' . strtolower(str_replace('-', '_', $s['code'])) . '_' . substr(md5($s['code'] . 'sup_seed_salt_2026'), 0, 16);
        
        if (isset($existingCodes[$s['code']])) {
            // Update existing site with valid QR tokens
            $updates = ["is_active = 1"];
            $params = [];
            
            if (in_array('active_qr_token', $sitesCols)) {
                $updates[] = "active_qr_token = ?";
                $params[] = $guardToken;
            }
            if ($hasSupToken) {
                $updates[] = "supervisor_qr_token = ?";
                $params[] = $supToken;
            }
            if ($hasActiveHash) {
                $updates[] = "active_qr_token_hash = ?";
                $params[] = hash('sha256', $guardToken);
            }
            if ($hasActiveEnc) {
                $updates[] = "active_qr_token_encrypted = ?";
                $params[] = encrypt_field($guardToken);
            }
            if ($hasSupHash) {
                $updates[] = "supervisor_qr_token_hash = ?";
                $params[] = hash('sha256', $supToken);
            }
            if ($hasSupEnc) {
                $updates[] = "supervisor_qr_token_encrypted = ?";
                $params[] = encrypt_field($supToken);
            }
            if ($hasTokenHmac) {
                $updates[] = "token_hmac = ?";
                $params[] = hash('sha256', $guardToken);
            }
            if ($hasTokenEnc) {
                $updates[] = "token_encrypted = ?";
                $params[] = encrypt_field($guardToken);
            }
            
            $params[] = $existingCodes[$s['code']];
            $uSql = "UPDATE sites SET " . implode(', ', $updates) . " WHERE id = ?";
            $uStmt = $db->prepare($uSql);
            $uStmt->execute($params);
        } else {
            // Insert new site
            $cols = ['name', 'code', 'type', 'address', 'latitude', 'longitude', 'client_name', 'client_contact', 'is_active', 'created_at'];
            $places = ['?', '?', '?', '?', '?', '?', '?', '?', '1', 'NOW()'];
            $vals = [$s['name'], $s['code'], $s['type'], $s['address'], $s['lat'], $s['lng'], $s['client_name'], $s['client_contact']];

            if (in_array('active_qr_token', $sitesCols)) {
                $cols[] = 'active_qr_token';
                $places[] = '?';
                $vals[] = $guardToken;
            }
            if ($hasSupToken) {
                $cols[] = 'supervisor_qr_token';
                $places[] = '?';
                $vals[] = $supToken;
            }
            if ($hasActiveHash) {
                $cols[] = 'active_qr_token_hash';
                $places[] = '?';
                $vals[] = hash('sha256', $guardToken);
            }
            if ($hasActiveEnc) {
                $cols[] = 'active_qr_token_encrypted';
                $places[] = '?';
                $vals[] = encrypt_field($guardToken);
            }
            if ($hasSupHash) {
                $cols[] = 'supervisor_qr_token_hash';
                $places[] = '?';
                $vals[] = hash('sha256', $supToken);
            }
            if ($hasSupEnc) {
                $cols[] = 'supervisor_qr_token_encrypted';
                $places[] = '?';
                $vals[] = encrypt_field($supToken);
            }
            if ($hasTokenHmac) {
                $cols[] = 'token_hmac';
                $places[] = '?';
                $vals[] = hash('sha256', $guardToken);
            }
            if ($hasTokenEnc) {
                $cols[] = 'token_encrypted';
                $places[] = '?';
                $vals[] = encrypt_field($guardToken);
            }
            if ($hasGeofence) {
                $cols[] = 'geofence_radius_meters';
                $places[] = '250';
            }

            $iSql = "INSERT INTO sites (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $places) . ")";
            $iStmt = $db->prepare($iSql);
            $iStmt->execute($vals);
            $createdSites++;
        }
    }
    $db->commit();

    // 3. Seed 50 Supervisors
    $existingStaffIds = $db->query("SELECT staff_id FROM guards")->fetchAll(PDO::FETCH_COLUMN);
    $existingStaffSet = array_flip($existingStaffIds);

    $hasDesignation = in_array('designation', $guardsCols);
    $hasRole = in_array('role', $guardsCols);
    $hasCampId = in_array('assigned_camp_id', $guardsCols);

    $supNames = [
        'Ahmed Al-Mansoori', 'Tariq Al-Hashemi', 'Rashid Bin Zayed', 'Fahad Al-Qasimi', 'Sultan Al-Nuaimi',
        'Zubair Khan', 'Muhammad Usman', 'Imran Nazir', 'Faisal Mehmood', 'Waqas Ali',
        'Bilal Haider', 'Kamran Akmal', 'Khurram Shehzad', 'Adeel Arshad', 'Hamza Tariq',
        'Rajesh Kumar', 'Vikram Singh', 'Arun Nair', 'Suresh Menon', 'Manoj Sharma',
        'Pradeep Verma', 'Ramesh Patel', 'Sunil Thomas', 'Deepak Joshi', 'Girish Pillai',
        'Kofi Mensah', 'Kwame Boateng', 'Emmanuel Osei', 'Samuel Mensah', 'John Owusu',
        'Peter Amankwah', 'David Quaye', 'Michael Addo', 'Isaac Appiah', 'Joseph Donkor',
        'Patrick Ssempa', 'Moses Mukasa', 'Ivan Byaruhanga', 'Julius Kato', 'Robert Ssenyonjo',
        'Brian Okello', 'Godfrey Musoke', 'Ronald Kizza', 'Simon Mugabe', 'Paul Lubega',
        'Mustafa Mahmoud', 'Omar Farooq', 'Hassan El-Sayed', 'Khaled Mostafa', 'Yasser Ibrahim'
    ];
    $supPassHash = password_hash('Supervisor@2026', PASSWORD_BCRYPT);
    $db->beginTransaction();
    for ($i = 1; $i <= 50; $i++) {
        $staffId = "USS-SUP-" . str_pad($i, 2, '0', STR_PAD_LEFT);
        if (!isset($existingStaffSet[$staffId])) {
            $name = $supNames[($i - 1) % count($supNames)];
            $uname = "sup." . strtolower(explode(' ', $name)[0]) . $i;
            $passHash = $supPassHash;
            
            $gCols = ['staff_id', 'full_name', 'is_supervisor', 'username', 'password_hash', 'is_active', 'created_at'];
            $gPlaces = ['?', '?', '1', '?', '?', '1', 'NOW()'];
            $gVals = [$staffId, $name, $uname, $passHash];

            if ($hasDesignation) {
                $gCols[] = 'designation';
                $gPlaces[] = "'Field Operations Supervisor'";
            }
            if ($hasRole) {
                $gCols[] = 'role';
                $gPlaces[] = "'Supervisor'";
            }
            if ($hasCampId) {
                $gCols[] = 'assigned_camp_id';
                $gPlaces[] = (string)$campId;
            }

            $sqlG = "INSERT INTO guards (" . implode(', ', $gCols) . ") VALUES (" . implode(', ', $gPlaces) . ")";
            $stmtG = $db->prepare($sqlG);
            $stmtG->execute($gVals);
            $createdSupervisors++;
        }
    }
    $db->commit();

    // 4. Seed 250 Security Guards
    $guardFirstNames = ['Arjun', 'Bikash', 'Chetan', 'Dinesh', 'Eshwar', 'Francis', 'Gopal', 'Hari', 'Inder', 'Jagdish', 'Kiran', 'Laxman', 'Mahesh', 'Naresh', 'Omkar', 'Pravin', 'Qasim', 'Raj', 'Satish', 'Tej', 'Umesh', 'Vijay', 'Waqar', 'Xavier', 'Yogesh', 'Zahid'];
    $guardLastNames = ['Thapa', 'Gurung', 'Tamang', 'Rana', 'Magar', 'Shrestha', 'Karki', 'Adhikari', 'Bhandari', 'Sharma', 'Pandey', 'Yadav', 'Singh', 'Chaudhary', 'Patel', 'Reddy', 'Rao', 'Das', 'Roy', 'Sen'];
    $guardPassHash = password_hash('Guard@2026', PASSWORD_BCRYPT);
    $db->beginTransaction();
    for ($i = 1; $i <= 250; $i++) {
        $staffId = "USS-GRD-" . str_pad($i, 3, '0', STR_PAD_LEFT);
        if (!isset($existingStaffSet[$staffId])) {
            $fn = $guardFirstNames[($i * 7) % count($guardFirstNames)];
            $ln = $guardLastNames[($i * 11) % count($guardLastNames)];
            $name = "{$fn} {$ln}";
            $uname = "guard." . strtolower($fn) . $i;
            $passHash = $guardPassHash;
            
            $gCols = ['staff_id', 'full_name', 'is_supervisor', 'username', 'password_hash', 'is_active', 'created_at'];
            $gPlaces = ['?', '?', '0', '?', '?', '1', 'NOW()'];
            $gVals = [$staffId, $name, $uname, $passHash];

            if ($hasDesignation) {
                $gCols[] = 'designation';
                $gPlaces[] = "'Security Officer'";
            }
            if ($hasRole) {
                $gCols[] = 'role';
                $gPlaces[] = "'Guard'";
            }
            if ($hasCampId) {
                $gCols[] = 'assigned_camp_id';
                $gPlaces[] = (string)$campId;
            }

            $sqlG = "INSERT INTO guards (" . implode(', ', $gCols) . ") VALUES (" . implode(', ', $gPlaces) . ")";
            $stmtG = $db->prepare($sqlG);
            $stmtG->execute($gVals);
            $createdGuards++;
        }
    }
    $db->commit();

    // 5. Seed Supervisor Duties & Completed Site Visits with Time In and Time Out
    $supervisors = $db->query("SELECT id, full_name, staff_id FROM guards WHERE is_supervisor = 1 ORDER BY id ASC LIMIT 10")->fetchAll();
    $allSites = $db->query("SELECT id, name, code FROM sites ORDER BY id ASC LIMIT 50")->fetchAll();

    if (!empty($supervisors) && !empty($allSites) && !empty($visitsCols)) {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $twoDaysAgo = date('Y-m-d', strtotime('-2 days'));
        
        $dates = [$twoDaysAgo, $yesterday, $today];
        $shifts = ['Day', 'Night'];

        $hasTravelMins = in_array('travel_minutes_from_previous', $visitsCols);
        $hasGuardsCount = in_array('guards_count', $visitsCols);
        $hasTurnout = in_array('rating_turnout', $visitsCols);
        $hasReport = in_array('site_report', $visitsCols);

        $db->beginTransaction();

        foreach ($supervisors as $sIdx => $sup) {
            foreach ($dates as $dIdx => $dateStr) {
                $shift = $shifts[($sIdx + $dIdx) % 2];
                $dutyRef = "DUTY-" . strtoupper($shift[0]) . "-" . date('Ymd', strtotime($dateStr)) . "-" . $sup['staff_id'];
                
                $campOutHour = ($shift === 'Day') ? '06:30:00' : '18:30:00';
                $campInHour = ($shift === 'Day') ? '18:30:00' : '06:30:00';
                $campOut = "{$dateStr} {$campOutHour}";
                $campIn = date('Y-m-d H:i:s', strtotime("{$campOut} +12 hours"));

                // Check or insert supervisor_duties
                $dutyId = 0;
                if (!empty($dutiesCols)) {
                    $dCheck = $db->prepare("SELECT id FROM supervisor_duties WHERE duty_ref = ? LIMIT 1");
                    $dCheck->execute([$dutyRef]);
                    $existingDuty = $dCheck->fetch();
                    if ($existingDuty) {
                        $dutyId = (int)$existingDuty['id'];
                    } else {
                        $dutyCols = ['duty_ref', 'supervisor_id', 'duty_date', 'shift_type', 'camp_out_time', 'camp_in_time', 'status', 'total_sites', 'total_minutes', 'created_at'];
                        $dutyPlaces = ['?', '?', '?', '?', '?', '?', "'Completed'", '4', '180', 'NOW()'];
                        $dutyVals = [$dutyRef, $sup['id'], $dateStr, $shift, $campOut, $campIn];
                        if (in_array('camp_id', $dutiesCols)) {
                            $dutyCols[] = 'camp_id';
                            $dutyPlaces[] = (string)$campId;
                        }
                        $stmtDuty = $db->prepare("INSERT INTO supervisor_duties (" . implode(', ', $dutyCols) . ") VALUES (" . implode(', ', $dutyPlaces) . ")");
                        $stmtDuty->execute($dutyVals);
                        $dutyId = (int)$db->lastInsertId();
                    }
                }

                // Create 4 completed visits per shift for this supervisor
                $baseStart = ($shift === 'Day') ? 7 : 19;
                for ($v = 0; $v < 4; $v++) {
                    $siteIndex = (($sIdx * 4) + ($dIdx * 8) + $v) % count($allSites);
                    $site = $allSites[$siteIndex];

                    $hourIn = str_pad($baseStart + ($v * 2), 2, '0', STR_PAD_LEFT);
                    $minuteIn = str_pad(15 + ($v * 5), 2, '0', STR_PAD_LEFT);
                    $timeIn = "{$dateStr} {$hourIn}:{$minuteIn}:00";
                    
                    $durationMins = 30 + ($v * 10);
                    $timeOut = date('Y-m-d H:i:s', strtotime("{$timeIn} +{$durationMins} minutes"));
                    $visitRef = "VIS-" . date('Ymd', strtotime($dateStr)) . "-" . $site['code'] . "-" . str_pad($v + 1, 2, '0', STR_PAD_LEFT);

                    // Check if visit exists
                    $vCheck = $db->prepare("SELECT id FROM supervisor_visits WHERE visit_ref = ? LIMIT 1");
                    $vCheck->execute([$visitRef]);
                    if (!$vCheck->fetch()) {
                        $vCols = ['visit_ref', 'supervisor_id', 'site_id', 'time_in', 'time_out', 'minutes_spent', 'remarks', 'purpose_of_visit', 'status', 'created_at'];
                        $vPlaces = ['?', '?', '?', '?', '?', '?', '?', '?', "'Completed'", 'NOW()'];
                        $vVals = [
                            $visitRef,
                            $sup['id'],
                            $site['id'],
                            $timeIn,
                            $timeOut,
                            $durationMins,
                            "Routine perimeter audit, CCTV control room check, and security log review. All guards alert and post fully secure.",
                            "Routine Patrol & Post Turnout Inspection"
                        ];

                        if (in_array('duty_id', $visitsCols)) {
                            $vCols[] = 'duty_id';
                            $vPlaces[] = '?';
                            $vVals[] = $dutyId;
                        }
                        if ($hasTravelMins) {
                            $vCols[] = 'travel_minutes_from_previous';
                            $vPlaces[] = '15';
                        }
                        if ($hasGuardsCount) {
                            $vCols[] = 'guards_count';
                            $vPlaces[] = '2';
                        }
                        if (in_array('guards_attendance', $visitsCols)) {
                            $vCols[] = 'guards_attendance';
                            $vPlaces[] = "'Present'";
                        }
                        if (in_array('guards_names', $visitsCols)) {
                            $vCols[] = 'guards_names';
                            $vPlaces[] = "'Suresh Thapa, Bikash Gurung'";
                        }
                        if ($hasTurnout) {
                            $vCols[] = 'rating_turnout';
                            $vPlaces[] = "'Good'";
                        }
                        if (in_array('rating_records', $visitsCols)) {
                            $vCols[] = 'rating_records';
                            $vPlaces[] = "'Good'";
                        }
                        if (in_array('rating_condition', $visitsCols)) {
                            $vCols[] = 'rating_condition';
                            $vPlaces[] = "'Good'";
                        }
                        if (in_array('rating_equipment', $visitsCols)) {
                            $vCols[] = 'rating_equipment';
                            $vPlaces[] = "'Good'";
                        }
                        if (in_array('has_issues', $visitsCols)) {
                            $vCols[] = 'has_issues';
                            $vPlaces[] = '0';
                        }
                        if ($hasReport) {
                            $vCols[] = 'site_report';
                            $vPlaces[] = "'Satisfactory Post Audit'";
                        }

                        $vSql = "INSERT INTO supervisor_visits (" . implode(', ', $vCols) . ") VALUES (" . implode(', ', $vPlaces) . ")";
                        $stmtV = $db->prepare($vSql);
                        $stmtV->execute($vVals);
                        $createdVisits++;
                    }
                }
            }
        }
        $db->commit();
    }

    // 6. Synchronize with Attendance Module (att_sites and att_staff) if tables exist
    try {
        $hasAttSites = (bool)$db->query("SHOW TABLES LIKE 'att_sites'")->fetch();
        if ($hasAttSites) {
            $attSitesCols = get_table_columns($db, 'att_sites');
            $existingAttCodes = $db->query("SELECT site_code FROM att_sites")->fetchAll(PDO::FETCH_COLUMN);
            $existingAttSet = array_flip($existingAttCodes);
            
            $db->beginTransaction();
            foreach ($sitesList as $s) {
                if (!isset($existingAttSet[$s['code']])) {
                    $aCols = ['site_code', 'site_name', 'status'];
                    $aVals = [$s['code'], $s['name'], 'Active'];
                    if (in_array('address', $attSitesCols)) {
                        $aCols[] = 'address';
                        $aVals[] = $s['address'];
                    }
                    if (in_array('client_name', $attSitesCols)) {
                        $aCols[] = 'client_name';
                        $aVals[] = $s['client_name'];
                    }
                    if (in_array('contact_number', $attSitesCols)) {
                        $aCols[] = 'contact_number';
                        $aVals[] = $s['client_contact'];
                    }
                    $places = array_fill(0, count($aCols), '?');
                    $sqlAtt = "INSERT INTO att_sites (" . implode(', ', $aCols) . ") VALUES (" . implode(', ', $places) . ")";
                    $db->prepare($sqlAtt)->execute($aVals);
                }
            }
            $db->commit();
        }
    } catch (Exception $e) {
        // Attendance module may reside in separate DB or not installed yet; continue safely
    }

    return [
        'sites_created' => $createdSites,
        'supervisors_created' => $createdSupervisors,
        'guards_created' => $createdGuards,
        'visits_created' => $createdVisits
    ];
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' || ($isCli && in_array('--run', $argv ?? []))) {
    if (!$isCli) {
        verify_csrf_token($_POST['csrf_token'] ?? '');
    }
    
    try {
        $stats = run_enterprise_seed($db);
        $message = "Enterprise Seeding Completed Successfully! All 500 Checkpoints, Supervisors, Guards, and Time In/Out Visits are now live.";
        $messageType = 'success';
    } catch (Exception $e) {
        $message = "Seeding failed: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Fetch current database statistics
$totalSites = (int)$db->query("SELECT COUNT(*) FROM sites")->fetchColumn();
$activeSites = (int)$db->query("SELECT COUNT(*) FROM sites WHERE is_active = 1")->fetchColumn();
$totalSupervisors = (int)$db->query("SELECT COUNT(*) FROM guards WHERE is_supervisor = 1")->fetchColumn();
$totalGuards = (int)$db->query("SELECT COUNT(*) FROM guards WHERE is_supervisor = 0")->fetchColumn();
$totalVisits = 0;
try {
    $totalVisits = (int)$db->query("SELECT COUNT(*) FROM supervisor_visits WHERE time_out IS NOT NULL")->fetchColumn();
} catch (Exception $e) {}

if ($isCli) {
    echo "=== USS ENTERPRISE SEEDER CLI ===
";
    if ($message) echo "Result: {$message}
";
    echo "Current Database Totals:
";
    echo "- Sites: {$totalSites} (Active: {$activeSites})
";
    echo "- Supervisors: {$totalSupervisors}
";
    echo "- Guards: {$totalGuards}
";
    echo "- Completed Visits (Time In & Out): {$totalVisits}
";
    exit(0);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="content-header" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="margin: 0; font-size: 24px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                <span>🏢</span> 500 Enterprise Checkpoint & Staff Generator
            </h1>
            <p style="margin: 4px 0 0; color: #64748b; font-size: 14px;">
                Populate complete Dubai / UAE physical infrastructure with cryptographic Guard & Supervisor QR tokens, staff, and supervisor reports.
            </p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="<?= APP_URL ?>/admin/sites.php" class="btn btn-outline">
                📍 Back to Sites
            </a>
            <a href="<?= APP_URL ?>/admin/bulk_qr_posters.php" class="btn btn-outline" style="border-color: #6366f1; color: #6366f1;">
                🖨️ Bulk QR Posters
            </a>
            <a href="<?= APP_URL ?>/admin/supervisor_visits.php" class="btn btn-outline" style="border-color: #0284c7; color: #0284c7;">
                📋 Supervisor Visits
            </a>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div style="padding: 16px 20px; border-radius: 8px; margin-bottom: 24px; font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 12px; <?= $messageType === 'success' ? 'background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;' : 'background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;' ?>">
        <span style="font-size: 20px;"><?= $messageType === 'success' ? '✅' : '⚠️' ?></span>
        <span><?= htmlspecialchars($message) ?></span>
    </div>
<?php endif; ?>

<!-- System Statistics Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 28px;">
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase;">Total Sites</div>
        <div style="font-size: 32px; font-weight: 800; color: #0f172a; margin-top: 6px;"><?= number_format($totalSites) ?></div>
        <div style="font-size: 12px; color: #10b981; margin-top: 4px; font-weight: 500;">Active: <?= number_format($activeSites) ?> Checkpoints</div>
    </div>
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase;">Supervisors</div>
        <div style="font-size: 32px; font-weight: 800; color: #6366f1; margin-top: 6px;"><?= number_format($totalSupervisors) ?></div>
        <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Field Patrol Leaders</div>
    </div>
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase;">Guards</div>
        <div style="font-size: 32px; font-weight: 800; color: #0284c7; margin-top: 6px;"><?= number_format($totalGuards) ?></div>
        <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Stationed Security Officers</div>
    </div>
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase;">Time In / Out Visits</div>
        <div style="font-size: 32px; font-weight: 800; color: #10b981; margin-top: 6px;"><?= number_format($totalVisits) ?></div>
        <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Verified Audit Records</div>
    </div>
</div>

<!-- Seeder Action Box -->
<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 28px; box-shadow: 0 2px 6px rgba(0,0,0,0.04); margin-bottom: 30px;">
    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 12px; display: flex; align-items: center; gap: 8px;">
        <span>🚀</span> One-Click Auto-Populate Entire Enterprise Infrastructure
    </h2>
    <p style="color: #475569; font-size: 14px; line-height: 1.6; margin: 0 0 20px; max-width: 800px;">
        Clicking the button below will immediately populate your system with <strong>500 physical checkpoints</strong> across Dubai (Business Bay, Marina, Downtown, Palm Jumeirah, JLT, DIFC, etc.), generate <strong>cryptographic Guard and Supervisor QR tokens</strong>, create <strong>50 Field Supervisors</strong>, <strong>250 Guards</strong>, and seed completed supervisor site visit logs with verified Time In and Time Out records.
    </p>

    <form method="POST" onsubmit="return confirm('Generate all 500 physical sites, staff members, and supervisor visit logs now?');">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
        <button type="submit" class="btn btn-primary" style="padding: 12px 28px; font-size: 16px; font-weight: 700; background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); border-radius: 8px; cursor: pointer;">
            ⚡ Generate 500 Sites & All Staff (1-Click)
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
