<?php
require_once('../../config.php');
//require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

require_once(__DIR__.'/lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

global $DB;

// Ambil role gurujurnal
$role = $DB->get_record('role', ['shortname' => 'gurujurnal']);
if (!$role) {
    die('Role gurujurnal tidak ditemukan');
}

// Ambil semua user dengan role gurujurnal
$sql = "SELECT u.id, u.lastname
        FROM {role_assignments} ra
        JOIN {user} u ON u.id = ra.userid
        WHERE ra.roleid = :roleid
        ORDER BY u.lastname";

$users = $DB->get_records_sql($sql, ['roleid' => $role->id]);

// Ambil hari sekolah
$hari_list = jurnalmengajar_get_hari_sekolah();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="format_acuan.csv"');

echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Header CSV
fputcsv($out, ['hari', 'userid', 'lastname', 'kelas', 'jamke']);

foreach ($users as $u) {
    foreach ($hari_list as $hari) {
        fputcsv($out, [
            $hari,
            $u->id,
            $u->lastname,
            '',
            ''
        ]);
    }
}

fclose($out);
exit;
