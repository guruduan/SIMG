<?php
require('../../config.php');
require_once(__DIR__.'/jadwal_acuan_lib.php');
require_once(__DIR__.'/jam_pelajaran_lib.php');
require_once(__DIR__.'/lib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/jadwal_view.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Jadwal Mengajar');
$PAGE->set_heading('Jadwal Mengajar');

echo $OUTPUT->header();

global $USER;

// Ambil jadwal dan jam pelajaran
$jadwal = jurnalmengajar_get_jadwal_acuan();
$jam_pelajaran = jurnalmengajar_generate_jam();

// Ambil daftar guru unik
$daftarguru = [];
foreach ($jadwal as $j) {
    $daftarguru[$j['userid']] = $j['lastname'];
}

// Urutkan nama guru A-Z
asort($daftarguru);

// Default filter guru
$filterguru = $_GET['guru'] ?? $USER->id;
// ===== Baris Filter kiri & Tombol kanan =====
echo html_writer::start_tag('form', [
    'method' => 'get',
    'style' => 'display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;'
]);

// Kiri (Filter)
echo html_writer::start_tag('div');
echo "Filter Guru: ";
echo html_writer::select($daftarguru, 'guru', $filterguru, null, [
    'onchange' => 'this.form.submit(); this.disabled=true;'
]);

echo html_writer::end_tag('div');

// Kanan (Tombol)
echo html_writer::start_tag('div', [
    'style' => 'display:flex; gap:10px;'
]);

echo html_writer::link(
    '#',
    '⬅ Kembali',
    [
        'class' => 'btn btn-secondary',
        'onclick' => 'history.back(); return false;'
    ]
);

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/jam_pelajaran_view.php'),
    'Lihat Alokasi Jam Pelajaran',
    ['class' => 'btn btn-primary']
);

echo html_writer::end_tag('div');
echo html_writer::end_tag('form');


$hariurut = jurnalmengajar_get_urutan_hari();

// GROUPING
$grouped = [];

foreach ($jadwal as $j) {
    $key = $j['hari'] . '|' . $j['userid'] . '|' . $j['kelas'];

    if (!isset($grouped[$key])) {
        $grouped[$key] = [
    'userid' => $j['userid'],
    'hari' => $j['hari'],
    'hari_no' => $hariurut[$j['hari']] ?? 9,
    'lastname' => $j['lastname'],
    'kelas' => $j['kelas'],
    'jamke' => []
];
    }

    $grouped[$key]['jamke'][] = $j['jamke'];
}

usort($grouped, function($a, $b) {
    return $a['hari_no'] <=> $b['hari_no'];
});


// ===== Tabel Jadwal =====
echo "<table class='generaltable'>";
echo "<tr>
        <th>No</th>
        <th>Hari</th>
        <th>Guru</th>
        <th>Kelas</th>
        <th>Jam Pelajaran</th>
        <th>Pukul</th>
      </tr>";

$no = 1;
$hari_sebelumnya = '';
$totaljam = 0;

$warna_kelas = [];
$warna_list = [
    '#bbdefb', // biru lebih tegas
    '#c8e6c9', // hijau lebih tegas
    '#ffe0b2', // orange
    '#f8bbd0', // pink
    '#d1c4e9', // ungu
    '#fff59d', // kuning
    '#b2ebf2', // cyan
    '#dcedc8'  // lime
];

$index_warna = 0;

foreach ($grouped as $g) {

    if ($filterguru && $g['userid'] != $filterguru) {
        continue;
    }

    sort($g['jamke']);
    $jamgabung = implode(',', array_unique($g['jamke']));

    $jamawal = min($g['jamke']);
    $jamakhir = max($g['jamke']);

    $mulai = $jam_pelajaran[$jamawal]['mulai'] ?? '';
    $selesai = $jam_pelajaran[$jamakhir]['selesai'] ?? '';

    $pukul = $mulai . ' - ' . $selesai;

    $jumlahjam = count(array_unique($g['jamke']));
    $totaljam += $jumlahjam;

$kelas = $g['kelas'];

if (!isset($warna_kelas[$kelas])) {
    $warna_kelas[$kelas] = $warna_list[$index_warna % count($warna_list)];
    $index_warna++;
}

echo "<tr style='background:" . $warna_kelas[$kelas] . "'>";

    if ($hari_sebelumnya != $g['hari']) {
        echo "<td>$no</td>";
        echo "<td>{$g['hari']}</td>";
        $hari_sebelumnya = $g['hari'];
        $no++;
    } else {
        echo "<td></td>";
        echo "<td></td>";
    }

    echo "<td>{$g['lastname']}</td>";
    echo "<td>{$g['kelas']}</td>";
    echo "<td>$jamgabung</td>";
    echo "<td>$pukul</td>";

    echo "</tr>";
}

// Total jam
echo "<tr style='font-weight:bold; background:#f8f9fa;'>";
echo "<td></td>";
echo "<td></td>";
echo "<td>Jumlah Jam Pelajaran</td>";
echo "<td></td>";
echo "<td>$totaljam Jam</td>";
echo "<td></td>";
echo "</tr>";

echo "</table>";

echo $OUTPUT->footer();
