<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/peserta_ekstra_view.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Peserta Ekstrakurikuler');
$PAGE->set_heading('Peserta Ekstrakurikuler');

global $DB;

echo $OUTPUT->header();

// =======================
// AMBIL DATA GABUNGAN
// =======================
$sql = "
SELECT 
    p.id,
    e.namaekstra,
    c.name AS namakelas,
    u.lastname
FROM {local_jm_ekstra_peserta} p
JOIN {local_jm_ekstra} e ON e.id = p.ekstraid
JOIN {cohort} c ON c.id = p.cohortid
JOIN {user} u ON u.id = p.userid
ORDER BY e.namaekstra, c.name, u.lastname
";

$data = $DB->get_records_sql($sql);

// =======================
// KELOMPOKKAN DATA
// =======================
$grouped = [];

foreach ($data as $d) {
    $nama = $d->namakelas . ' ' . ucwords(strtolower($d->lastname));
    $grouped[$d->namaekstra][$nama] = true; // pakai key
}

// =======================
// TAMPILKAN
// =======================
foreach ($grouped as $ekstra => $siswa_list) {

    $siswa = array_keys($siswa_list);

    echo '<h3>'.format_string($ekstra).' ('.count($siswa).' siswa)</h3>';

    echo '<table border="1" cellpadding="6" style="border-collapse:collapse; width:50%; margin-bottom:20px;">';

    echo '<tr style="background:#ddd;">
            <th style="width:50px;">No</th>
            <th>Nama</th>
          </tr>';

    $no = 1;
    foreach ($siswa as $nama) {

        $bg = ($no % 2 == 0) ? '#f9f9f9' : '#ffffff';

        echo '<tr style="background:'.$bg.';">';
        echo '<td>'.$no.'</td>';
        echo '<td>'.format_string($nama).'</td>';
        echo '</tr>';

        $no++;
    }

    echo '</table>';
}

echo $OUTPUT->footer();
