<?php
require('../../config.php');

require_login();

$cohortid = required_param('kelas', PARAM_INT);

global $DB;

$members = $DB->get_records_sql("
    SELECT u.id, u.lastname
    FROM {cohort_members} cm
    JOIN {user} u ON u.id = cm.userid
    WHERE cm.cohortid = ?
    ORDER BY u.lastname ASC
", [$cohortid]);

echo '<option value="">-- Pilih Murid --</option>';

foreach ($members as $user) {

    $nama = ucwords(strtolower(trim($user->lastname)));

    echo '<option value="' . $user->id . '">'
        . s($nama)
        . '</option>';
}
