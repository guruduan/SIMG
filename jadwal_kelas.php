<?php
require('../../config.php');
require_once(__DIR__.'/jadwal_acuan_lib.php');
require_once(__DIR__.'/jam_pelajaran_lib.php');
require_once(__DIR__.'/lib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/jadwal_kelas.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Jadwal Per Kelas');
$PAGE->set_heading('Jadwal Per Kelas');

echo $OUTPUT->header();

// Ambil data
$jadwal = jurnalmengajar_get_jadwal_acuan();
$jam_pelajaran = jurnalmengajar_generate_jam();
$hariurut = jurnalmengajar_get_urutan_hari();

// ===== Ambil daftar kelas =====
$daftarkelas = [];
foreach ($jadwal as $j) {
    $daftarkelas[$j['kelas']] = $j['kelas'];
}
asort($daftarkelas);

// Default filter kelas
$filterkelas = $_GET['kelas'] ?? array_key_first($daftarkelas);

// ===== Filter UI =====
echo html_writer::start_tag('form', [
    'method' => 'get',
    'style' => 'margin-bottom:15px;'
]);

echo "Filter Kelas: ";
echo html_writer::select($daftarkelas, 'kelas', $filterkelas);

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => 'Tampilkan',
    'class' => 'btn btn-secondary',
    'style' => 'margin-left:5px'
]);

echo html_writer::end_tag('form');


// ===== GROUPING =====
$grouped = [];
foreach ($jadwal as $j) {

    if ($filterkelas && $j['kelas'] != $filterkelas) {
        continue;
    }

    $key = $j['hari'] . '|' . $j['kelas'] . '|' . $j['userid'];

if (!isset($grouped[$key])) {
    $grouped[$key] = [
        'hari' => $j['hari'],
        'hari_no' => $hariurut[$j['hari']] ?? 9,
        'kelas' => $j['kelas'],
        'guru' => $j['lastname'],
        'jamke' => [],
        'jam_awal' => $j['jamke'] // ✅ INI WAJIB
    ];
}

    $grouped[$key]['jamke'][] = $j['jamke'];
}

// Urutkan hari
usort($grouped, function($a, $b) {

    // Urutkan hari dulu
    if ($a['hari_no'] != $b['hari_no']) {
        return $a['hari_no'] <=> $b['hari_no'];
    }

    // Kalau hari sama, urut jam awal
    return $a['jam_awal'] <=> $b['jam_awal'];
});


// ===== TABEL =====
echo "<table class='generaltable'>";
echo "<tr>
        <th>No</th>
        <th>Hari</th>
        <th>Jamke</th>
        <th>Guru</th>
        <th>Pukul</th>
      </tr>";

$no = 1;
$hari_sebelumnya = '';

foreach ($grouped as $g) {

    sort($g['jamke']);
    $jamgabung = implode(',', $g['jamke']);

    $jamawal = min($g['jamke']);
    $jamakhir = max($g['jamke']);

    $mulai = $jam_pelajaran[$jamawal]['mulai'] ?? '';
    $selesai = $jam_pelajaran[$jamakhir]['selesai'] ?? '';

    $pukul = $mulai . ' - ' . $selesai;

    echo "<tr>";

    if ($hari_sebelumnya != $g['hari']) {
        echo "<td>$no</td>";
        echo "<td>{$g['hari']}</td>";
        $hari_sebelumnya = $g['hari'];
        $no++;
    } else {
        echo "<td></td>";
        echo "<td></td>";
    }

    echo "<td>$jamgabung</td>";
    echo "<td>{$g['guru']}</td>";
    echo "<td>$pukul</td>";

    echo "</tr>";
}

echo "</table>";

echo $OUTPUT->footer();
