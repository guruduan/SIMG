<?php
require_once('../../config.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="format_binaan.csv"');

// BOM UTF-8 agar Excel membuka karakter dengan benar.
echo "\xEF\xBB\xBF";

echo "userid,nama guru,nis\n";

global $DB;

// Ambil role guru jurnal.
$role = $DB->get_record(
    'role',
    ['shortname' => 'gurujurnal'],
    'id',
    MUST_EXIST
);

// Ambil seluruh guru jurnal.
$sql = "
    SELECT
        u.id,
        u.lastname
    FROM {role_assignments} ra
    JOIN {user} u
         ON u.id = ra.userid
    WHERE ra.roleid = :roleid
    ORDER BY u.lastname
";

$users = $DB->get_records_sql($sql, [
    'roleid' => $role->id
]);

// Satu baris kosong untuk setiap guru.
foreach ($users as $u) {

    echo $u->id . ',' .
         '"' . str_replace('"', '""', $u->lastname) . '",' .
         "\n";
}

exit;
