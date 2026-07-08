<?php
require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

global $DB;

// Header download CSV.
$filename = 'siswa_aktif_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

// BOM UTF-8 agar Excel membaca UTF-8 dengan benar.
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Header kolom.
fputcsv($out, [
    'username',
    'pwexam',
    'nis',
    'firstname',
    'lastname',
    'cohort1'
]);

$sql = "
SELECT
       u.username,
       uid.data AS nis,
       uidpw.data AS pwexam,
       u.firstname,
       u.lastname,
       c.name AS cohort1
FROM {user} u

LEFT JOIN {cohort_members} cm
    ON cm.userid = u.id

LEFT JOIN {cohort} c
    ON c.id = cm.cohortid
LEFT JOIN {user_info_field} uif
     ON uif.shortname = 'nis'
LEFT JOIN {user_info_data} uid
     ON uid.userid = u.id
    AND uid.fieldid = uif.id
LEFT JOIN {user_info_field} uifpw
     ON uifpw.shortname = 'pwexam'
LEFT JOIN {user_info_data} uidpw
     ON uidpw.userid = u.id
    AND uidpw.fieldid = uifpw.id
ORDER BY c.name, u.lastname
";

$rows = $DB->get_records_sql($sql);

foreach ($rows as $r) {

    fputcsv($out, [
        $r->username,
        $r->pwexam,
        $r->nis,
        $r->firstname,
        $r->lastname,
        $r->cohort1
    ]);
}

fclose($out);
exit;
