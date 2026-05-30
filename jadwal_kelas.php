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
$filterkelas = optional_param(
    'kelas',
    array_key_first($daftarkelas),
    PARAM_TEXT
);

/*
=====================================================
FORM FILTER UI (STRUKTUR HORIZONTAL BOOTSTRAP)
=====================================================
*/
echo html_writer::start_tag('form', [
    'method' => 'get',
    'class'  => 'mb-4 p-3 bg-light rounded border shadow-sm'
]);

echo html_writer::start_div('row align-items-end');

// Dropdown Pilihan Kelas
echo html_writer::start_div('col-md-5 mb-2 mb-md-0');
echo html_writer::tag('label', 'Filter Kelas', ['class' => 'font-weight-bold mb-1']);
echo html_writer::select($daftarkelas, 'kelas', $filterkelas, null, [
    'class' => 'form-control form-control-sm',
    'onchange' => 'this.form.submit();'
]);
echo html_writer::end_div();

echo html_writer::end_div(); // End Row
echo html_writer::end_tag('form');


/*
=====================================================
GROUPING DATA
=====================================================
*/
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
            'jam_awal' => $j['jamke']
        ];
    }

    $grouped[$key]['jamke'][] = $j['jamke'];
}

// Urutkan hari dan jam awal
usort($grouped, function($a, $b) {
    if ($a['hari_no'] != $b['hari_no']) {
        return $a['hari_no'] <=> $b['hari_no'];
    }
    return $a['jam_awal'] <=> $b['jam_awal'];
});


/*
=====================================================
TABEL JADWAL KELAS (GAYA BERSIH & TEKS KONTRAS)
=====================================================
*/
echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-hover bg-white shadow-sm align-middle">';
echo '<thead class="thead-dark">';
echo '<tr>';
echo '<th style="width: 6%;" class="text-center">No</th>';
echo '<th style="width: 15%;">Hari</th>';
echo '<th style="width: 20%;" class="text-center">Jam Pelajaran</th>';
echo '<th>Guru Pengajar</th>';
echo '<th style="width: 22%;" class="text-center">Pukul</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

$no = 1;
$hari_sebelumnya = '';

foreach ($grouped as $g) {

    sort($g['jamke']);
    $jamgabung = implode(', ', array_unique($g['jamke'])); // Ditambahkan spasi setelah koma

    $jamawal = min($g['jamke']);
    $jamakhir = max($g['jamke']);

    $mulai = $jam_pelajaran[$jamawal]['mulai'] ?? '';
    $selesai = $jam_pelajaran[$jamakhir]['selesai'] ?? '';
    $pukul = $mulai . ' - ' . $selesai;

    echo '<tr>';

    // Logika Pengelompokan Visual Kolom Hari (.table-active)
    if ($hari_sebelumnya != $g['hari']) {
        echo "<td class='text-center align-middle font-weight-bold table-active'>$no</td>";
        echo "<td class='align-middle font-weight-bold table-active'>{$g['hari']}</td>";
        $hari_sebelumnya = $g['hari'];
        $no++;
    } else {
        echo "<td class='table-active'></td>";
        echo "<td class='table-active'></td>";
    }

    // Kolom Jam Pelajaran (Teks biasa warna info biru)
    echo "<td class='text-center align-middle text-info' style='font-size: 0.95rem;'>Jam ke-$jamgabung</td>";
    
    // Kolom Guru (Teks biasa/normal warna gelap kontras #212529)
    echo "<td class='align-middle' style='color: #212529;'>{$g['guru']}</td>";
    
    // Kolom Pukul (Teks biasa warna gelap kontras dengan ikon jam)
    echo "<td class='text-center align-middle' style='color: #212529;'><i class='fa fa-clock-o text-muted mr-1'></i> $pukul</td>";

    echo '</tr>';
}

if (empty($grouped)) {
    echo '<tr><td colspan="5" class="text-center text-muted p-4"><i>Tidak ada data jadwal untuk kelas ini.</i></td></tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>'; // End table-responsive

echo $OUTPUT->footer();
