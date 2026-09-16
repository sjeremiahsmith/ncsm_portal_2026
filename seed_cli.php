<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDb();

// 1. Counties
$counties = [
    ['Nimba', 'A', 'NM'], ['Grand Gedeh', 'A', 'GG'], ['River Gee', 'A', 'RG'],
    ['Gbarpolu', 'A', 'GP'], ['Grand Cape Mount', 'B', 'GM'], ['Bong', 'B', 'BG'],
    ['Maryland', 'B', 'ML'], ['River Cess', 'B', 'RC'], ['Grand Bassa', 'C', 'GB'],
    ['Lofa', 'C', 'LF'], ['Montserrado', 'C', 'MG'], ['Sinoe', 'C', 'SN'],
    ['Margibi', 'D', 'MR'], ['Grand Kru', 'D', 'GK'], ['Bomi', 'D', 'BM'],
];
foreach ($counties as $c) {
    $existing = $db->fetchOne("SELECT id FROM counties WHERE code = ?", [$c[2]]);
    if (!$existing) {
        $db->insert("INSERT INTO counties (name, group_label, code) VALUES (?, ?, ?)", $c);
    }
}
echo "Counties seeded.\n";

// 2. Sports disciplines
$sports = [
    ['Football', 'Liberia Football Association', 'LFA'],
    ['Kickball', 'Liberia Kickball Association', 'LKA'],
    ['Basketball', 'Liberia Basketball Association', 'LBA'],
    ['Athletics', 'Liberia Athletics Association', 'LAA'],
];
$sportIds = [];
foreach ($sports as $s) {
    $existing = $db->fetchOne("SELECT id FROM sports_disciplines WHERE association_code = ?", [$s[2]]);
    if (!$existing) {
        $sportIds[$s[2]] = $db->insert("INSERT INTO sports_disciplines (name, association_name, association_code) VALUES (?, ?, ?)", $s);
    } else {
        $sportIds[$s[2]] = $existing['id'];
    }
}
echo "Sports disciplines seeded.\n";

// 3. Users
$seedPassword = getenv('NCSM_SEED_PASSWORD');
if ($seedPassword === false || strlen($seedPassword) < 12) {
    fwrite(STDERR, "Set NCSM_SEED_PASSWORD to a password of at least 12 characters.\n");
    exit(1);
}
$adminPass = password_hash($seedPassword, PASSWORD_DEFAULT);

$existingAdmin = $db->fetchOne("SELECT id FROM users WHERE username = 'admin'");
if (!$existingAdmin) {
    $db->insert(
        "INSERT INTO users (username, password, email, full_name, role, status) VALUES (?, ?, ?, ?, ?, 'active')",
        ['admin', $adminPass, 'admin@sportsmeet.gov.lr', 'System Administrator', 'super_admin']
    );
}
echo "Admin user created. Change the seed password after first login.\n";

// County Coordinators
$countyCoords = [
    ['gedeh_coord', 'Grand Gedeh Coordinator', 'GG'],
    ['bong_coord', 'Bong Coordinator', 'BG'],
    ['lofa_coord', 'Lofa Coordinator', 'LF'],
    ['kru_coord', 'Grand Kru Coordinator', 'GK'],
];
foreach ($countyCoords as $cc) {
    $existing = $db->fetchOne("SELECT id FROM users WHERE username = ?", [$cc[0]]);
    if (!$existing) {
        $countyId = $db->fetchOne("SELECT id FROM counties WHERE code = ?", [$cc[2]])['id'];
        $db->insert(
            "INSERT INTO users (username, password, email, full_name, role, county_id, status) VALUES (?, ?, ?, ?, ?, ?, 'active')",
            [$cc[0], $adminPass, $cc[0] . '@sportsmeet.gov.lr', $cc[1], 'county_coordinator', $countyId]
        );
    }
}

// County Admins
$countyAdmins = [
    ['gedeh_admin', 'Grand Gedeh Admin', 'GG'],
    ['bong_admin', 'Bong Admin', 'BG'],
    ['lofa_admin', 'Lofa Admin', 'LF'],
    ['kru_admin', 'Grand Kru Admin', 'GK'],
];
foreach ($countyAdmins as $ca) {
    $existing = $db->fetchOne("SELECT id FROM users WHERE username = ?", [$ca[0]]);
    if (!$existing) {
        $countyId = $db->fetchOne("SELECT id FROM counties WHERE code = ?", [$ca[2]])['id'];
        $db->insert(
            "INSERT INTO users (username, password, email, full_name, role, county_id, status) VALUES (?, ?, ?, ?, ?, ?, 'active')",
            [$ca[0], $adminPass, $ca[0] . '@sportsmeet.gov.lr', $ca[1], 'county_coordinator', $countyId]
        );
    }
}

// Association Admins
foreach ($sportIds as $code => $sportId) {
    $existingAssoc = $db->fetchOne("SELECT id FROM users WHERE username = ?", [strtolower($code) . '_admin']);
    if (!$existingAssoc) {
        $db->insert(
            "INSERT INTO users (username, password, email, full_name, role, association_id, status) VALUES (?, ?, ?, ?, ?, ?, 'active')",
            [strtolower($code) . '_admin', $adminPass, strtolower($code) . '@' . strtolower($code) . '.org', "$code Administrator", 'association_admin', $sportId]
        );
    }
}

// Match Commissioner
$existingComm = $db->fetchOne("SELECT id FROM users WHERE username = 'commissioner'");
if (!$existingComm) {
    $db->insert(
        "INSERT INTO users (username, password, email, full_name, role, status) VALUES (?, ?, ?, ?, ?, 'active')",
        ['commissioner', $adminPass, 'commissioner@sportsmeet.gov.lr', 'Match Commissioner', 'match_commissioner']
    );
}

// Sports Bureau Coordinator
$existingSports = $db->fetchOne("SELECT id FROM users WHERE username = 'sports_coord'");
if (!$existingSports) {
    $db->insert(
        "INSERT INTO users (username, password, email, full_name, role, status) VALUES (?, ?, ?, ?, ?, 'active')",
        ['sports_coord', $adminPass, 'sports_coord@sportsmeet.gov.lr', 'Sports Bureau Coordinator', 'super_admin']
    );
}
echo "All users seeded.\n";
echo "Done!\n";
