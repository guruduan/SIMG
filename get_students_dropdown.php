<?php
require('../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$cohortid = required_param('kelas', PARAM_INT);
$mapel    = optional_param('matapelajaran', '', PARAM_TEXT);

global $DB;

$members = $DB->get_records_sql("
    SELECT u.id, u.lastname
    FROM {cohort_members} cm
    JOIN {user} u
        ON u.id = cm.userid
    WHERE cm.cohortid = ?
    ORDER BY u.lastname ASC
", [$cohortid]);

// Filter sesuai mapel
$members = jurnalmengajar_filter_peserta_mapel(
    $members,
    $mapel
);

echo '<option value="">-- Pilih Murid --</option>';

foreach ($members as $user) {

    $nama = format_nama_siswa($user->lastname);

    echo '<option value="' . $user->id . '">'
        . s($nama)
        . '</option>';
}
