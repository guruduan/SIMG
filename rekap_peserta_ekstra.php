<?php
require('../../config.php');
require_once(__DIR__.'/lib.php');
require_login();

global $DB, $PAGE, $OUTPUT;

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/rekap_peserta_ekstra.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Rekap Peserta Ekstrakurikuler');
$PAGE->set_heading('Rekap Peserta Ekstrakurikuler');

echo $OUTPUT->header();
echo $OUTPUT->heading('Rekap Peserta Ekstrakurikuler');

$sql = "SELECT p.id,
               e.namaekstra,
               u.lastname AS namasiswa,
               c.name AS kelas
        FROM {local_jm_ekstra_peserta} p
        JOIN {user} u ON u.id = p.userid
        JOIN {local_jm_ekstra} e ON e.id = p.ekstraid
        LEFT JOIN {cohort} c ON c.id = p.cohortid
        ORDER BY e.namaekstra, c.name, u.lastname";

$data = $DB->get_records_sql($sql);

echo '<table class="generaltable">';
echo '<thead>
        <tr>
            <th>No</th>
            <th>Ekstrakurikuler</th>
            <th>Kelas</th>
            <th>Nama Siswa</th>
        </tr>
      </thead>';
echo '<tbody>';

$no = 1;
foreach ($data as $d) {
    echo '<tr>';
    echo '<td>'.$no++.'</td>';
    echo '<td>'.$d->namaekstra.'</td>';
    echo '<td>'.$d->kelas.'</td>';
    echo '<td>'.format_nama_siswa($d->namasiswa).'</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';

echo $OUTPUT->footer();
