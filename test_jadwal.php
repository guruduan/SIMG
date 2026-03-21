<?php
require('../../config.php');
require_once(__DIR__.'/jadwal_acuan_lib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/test_jadwal.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Test Jadwal Acuan');
$PAGE->set_heading('Test Jadwal Acuan');

echo $OUTPUT->header();

$jadwal = jurnalmengajar_get_jadwal_acuan();

echo "<h3>Hasil Parsing CSV</h3>";

echo "<table class='generaltable'>";
echo "<tr><th>Hari</th><th>UserID</th><th>Kelas</th><th>Jam Ke</th></tr>";

foreach ($jadwal as $j) {
    echo "<tr>";
    echo "<td>{$j['hari']}</td>";
    echo "<td>{$j['userid']}</td>";
    echo "<td>{$j['kelas']}</td>";
    echo "<td>{$j['jamke']}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<br><h3>Hari Ini:</h3>";
echo jurnalmengajar_get_hari_ini();

echo $OUTPUT->footer();
