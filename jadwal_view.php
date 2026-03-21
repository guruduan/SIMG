<?php
require('../../config.php');
require_once(__DIR__.'/jadwal_acuan_lib.php');
require_once(__DIR__.'/jam_pelajaran_lib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/jadwal_view.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Jadwal Mengajar');
$PAGE->set_heading('Jadwal Mengajar');

echo $OUTPUT->header();
echo "<div style='margin-bottom:15px;'>";
echo "<a href='/local/jurnalmengajar/jam_pelajaran_view.php' class='btn btn-primary'>Lihat Alokasi Jam Pelajaran</a> ";
echo "</div>";

global $USER;

// Ambil jadwal dari CSV
$jadwal = jurnalmengajar_get_jadwal_acuan();

$jam_pelajaran = jurnalmengajar_generate_jam();

// Ambil daftar guru unik
$daftarguru = [];
foreach ($jadwal as $j) {
    $daftarguru[$j['lastname']] = $j['lastname'];
}

// Default filter = guru yang login
$filterguru = $_GET['guru'] ?? $USER->lastname;

// Form filter
echo "<form method='get'>";
echo "Filter Guru: ";
echo "<select name='guru'>";
echo "<option value=''>Semua Guru</option>";

foreach ($daftarguru as $g) {
    $selected = ($filterguru == $g) ? 'selected' : '';
    echo "<option value='$g' $selected>$g</option>";
}

echo "</select>";
echo " <input type='submit' value='Tampilkan' class='btn btn-secondary'>";
echo "</form><br>";

$hariurut = [
    'Senin' => 1,
    'Selasa' => 2,
    'Rabu' => 3,
    'Kamis' => 4,
    'Jumat' => 5
];

$jadwal = jurnalmengajar_get_jadwal_acuan();

// GROUPING
$grouped = [];

$hariurut = [
    'Senin' => 1,
    'Selasa' => 2,
    'Rabu' => 3,
    'Kamis' => 4,
    'Jumat' => 5
];

foreach ($jadwal as $j) {
    $key = $j['hari'] . '|' . $j['lastname'] . '|' . $j['kelas'];

    if (!isset($grouped[$key])) {
        $grouped[$key] = [
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

// Tabel jadwal
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

foreach ($grouped as $g) {

    if ($filterguru && $g['lastname'] != $filterguru) {
        continue;
    }

    sort($g['jamke']);
$jamgabung = implode(',', $g['jamke']);

$jamlist = $g['jamke'];
$jamawal = min($jamlist);
$jamakhir = max($jamlist);

$mulai = $jam_pelajaran[$jamawal]['mulai'] ?? '';
$selesai = $jam_pelajaran[$jamakhir]['selesai'] ?? '';

$pukul = $mulai . ' - ' . $selesai;

$jumlahjam = count($g['jamke']);
$totaljam += $jumlahjam;

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

echo "<td>{$g['lastname']}</td>";
echo "<td>{$g['kelas']}</td>";
echo "<td>$jamgabung</td>";
echo "<td>$pukul</td>";

echo "</tr>";
}

echo "<tr style='font-weight:bold; background:#f8f9fa;'>";
echo "<td></td>"; // kolom 1 No
echo "<td></td>"; // kolom 2 Hari
echo "<td style='text-align:left;'>Jumlah Jam Pelajaran</td>"; // kolom 3 Guru
echo "<td></td>"; // kolom 4 Kelas
echo "<td style='text-align:left;'>$totaljam Jam</td>"; // kolom 5 Jam
echo "<td></td>"; // kolom 6 Pukul
echo "</tr>";

echo "</table>";

echo $OUTPUT->footer();
