<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/jadwal_acuan_lib.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$filename = 'guru_jurnal_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

// UTF-8 BOM
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, [
    'userid',
    'username',
    'nip',
    'nama_guru',
    'nowa'
]);

global $DB;

$jadwal = jurnalmengajar_get_jadwal_acuan();

$guruids = [];

foreach ($jadwal as $j) {
    $guruids[$j['userid']] = true;
}

foreach (array_keys($guruids) as $userid) {

    $user = $DB->get_record(
        'user',
        ['id' => $userid],
        'id,username,lastname'
    );

    if (!$user) {
        continue;
    }

    // NIP.
    $nip = $DB->get_field_sql("
        SELECT uid.data
        FROM {user_info_data} uid
        JOIN {user_info_field} uif
             ON uif.id = uid.fieldid
        WHERE uid.userid = :userid
          AND uif.shortname = 'nip'
    ", ['userid' => $userid]);

    // No WA.
    $nowa = $DB->get_field_sql("
        SELECT uid.data
        FROM {user_info_data} uid
        JOIN {user_info_field} uif
             ON uif.id = uid.fieldid
        WHERE uid.userid = :userid
          AND uif.shortname = 'nowa'
    ", ['userid' => $userid]);

    fputcsv($out, [
        $userid,
        $user->username,
        $nip,
        $user->lastname,
        $nowa
    ]);
}

fclose($out);
exit;
