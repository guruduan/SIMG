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

/*
=====================================================
FORM FILTER & NAVIGASI (RESPONSIF BOOTSTRAP)
=====================================================
*/
echo html_writer::start_tag('form', [
    'method' => 'get',
    'class'  => 'mb-4 p-3 bg-light rounded border shadow-sm'
]);

echo html_writer::start_div('row align-items-end');

// Sisi Kiri: Filter Dropdown
echo html_writer::start_div('col-md-5 mb-2 mb-md-0');
echo html_writer::tag('label', 'Filter Guru', ['class' => 'font-weight-bold mb-1']);
echo html_writer::select($daftarguru, 'guru', $filterguru, null, [
    'class'    => 'form-control form-control-sm',
    'onchange' => 'this.form.submit(); this.disabled=true;'
]);
echo html_writer::end_div();

// Sisi Kanan: Tombol Navigasi
echo html_writer::start_div('col-md-7 d-flex justify-content-md-end gap-2');
echo html_writer::link(
    '#',
    '<i class="fa fa-arrow-left"></i> Kembali',
    [
        'class' => 'btn btn-secondary btn-sm mr-2',
        'onclick' => 'history.back(); return false;'
    ]
);

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/jam_pelajaran_view.php'),
    '<i class="fa fa-clock-o"></i> Lihat Alokasi Jam Pelajaran',
    ['class' => 'btn btn-primary btn-sm']
);
echo html_writer::end_div();

echo html_writer::end_div(); // End Row
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


/*
=====================================================
TABEL JADWAL MENGAJAR (LEBIH BERSIH & JELAS)
=====================================================
*/
echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-hover bg-white shadow-sm">';
echo '<thead class="thead-dark">';
echo '<tr>';
echo '<th style="width: 5%;" class="text-center">No</th>';
echo '<th style="width: 12%;">Hari</th>';
echo '<th>Guru</th>';
echo '<th style="width: 15%;" class="text-center">Kelas</th>';
echo '<th style="width: 18%;" class="text-center">Jam Pelajaran</th>';
echo '<th style="width: 20%;" class="text-center">Pukul</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

$no = 1;
$hari_sebelumnya = '';
$totaljam = 0;

// Palet warna teks badge kelas (soft/pastel text on dark background atau badge standar)
$warna_kelas = [];
$warna_list = [
    'badge-primary',
    'badge-success',
    'badge-info',
    'badge-warning text-dark',
    'badge-danger',
    'badge-dark',
    'badge-secondary'
];
$index_warna = 0;

foreach ($grouped as $g) {

    if ($filterguru && $g['userid'] != $filterguru) {
        continue;
    }

    sort($g['jamke']);
    $jamgabung = implode(', ', array_unique($g['jamke'])); // Ditambahkan spasi setelah koma agar rapi

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

    echo '<tr>';

    // Logika pengelompokan baris Hari
    if ($hari_sebelumnya != $g['hari']) {
        echo "<td class='text-center align-middle font-weight-bold table-active'>$no</td>";
        echo "<td class='align-middle font-weight-bold table-active'>{$g['hari']}</td>";
        $hari_sebelumnya = $g['hari'];
        $no++;
    } else {
        echo "<td class='table-active'></td>";
        echo "<td class='table-active'></td>";
    }

    // Kolom Guru (Biasa/Normal tidak tebal)
    echo "<td class='align-middle' style='color: #212529;'>{$g['lastname']}</td>";
    
    // Kolom Kelas menggunakan Badge agar kontras teks tetap aman terjaga
    echo "<td class='text-center align-middle'><span class='badge {$warna_kelas[$kelas]} p-2' style='font-size:0.9rem; min-width:60px;'>$kelas</span></td>";
    
    // Jam Pelajaran
    echo "<td class='text-center align-middle font-weight-bold text-info'>Jam ke-$jamgabung</td>";
    
    // Pukul/Waktu (Jelas dan Kontras)
    echo "<td class='text-center align-middle' style='color: #212529;'><i class='fa fa-clock-o text-muted mr-1'></i> $pukul</td>";

    echo '</tr>';
}

// Baris Total Jam Pelajaran
echo "<tr class='table-warning font-weight-bold' style='color: #212529;'>";
echo "<td></td>";
echo "<td></td>";
echo "<td colspan='2' class='text-right align-middle'>Total Alokasi Mengajar:</td>";
echo "<td class='text-center align-middle' style='font-size:1.1rem;'><span class='badge badge-dark p-2'>$totaljam Jam</span></td>";
echo "<td></td>";
echo "</tr>";

echo '</tbody>';
echo '</table>';
echo '</div>'; // End table-responsive

echo $OUTPUT->footer();
