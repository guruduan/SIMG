<?php
require('../../config.php');
require_once(__DIR__.'/jadwal_acuan_lib.php');
require_once(__DIR__.'/jam_pelajaran_lib.php');
require_once(__DIR__.'/lib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/jadwal_perhari.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Jadwal Per Hari');
$PAGE->set_heading('Jadwal Mengajar Per Hari');

echo $OUTPUT->header();

// Tambahkan CSS Inline khusus untuk fitur Freeze Column agar tabel tidak berantakan saat dicrop/scroll
echo '<style>
    .table-freeze-container {
        max-width: 100%;
        overflow-x: auto;
        position: relative;
    }
    /* Bekukan Kolom 1 (No) */
    .table-freeze-container th.freeze-col-1,
    .table-freeze-container td.freeze-col-1 {
        position: -webkit-sticky;
        position: sticky;
        left: 0;
        z-index: 3;
        background-color: #f8f9fa !important;
        box-shadow: inset -1px 0 0 #dee2e6;
    }
    /* Bekukan Kolom 2 (Kelas) */
    .table-freeze-container th.freeze-col-2,
    .table-freeze-container td.freeze-col-2 {
        position: -webkit-sticky;
        position: sticky;
        left: 45px; /* Menyesuaikan perkiraan lebar kolom No */
        z-index: 3;
        background-color: #f8f9fa !important;
        box-shadow: inset -1px 0 0 #dee2e6;
    }
    /* Naikkan z-index header utama agar th yang freeze tidak menimpa teks atas */
    .table-freeze-container thead th {
        position: sticky;
        top: 0;
    }
    .table-freeze-container thead tr:nth-child(1) th.freeze-col-1,
    .table-freeze-container thead tr:nth-child(1) th.freeze-col-2 {
        z-index: 5;
    }
    .table-freeze-container thead tr:nth-child(2) th {
        z-index: 2;
    }
</style>';

// Ambil data acuan
$jadwal = jurnalmengajar_get_jadwal_acuan();
$hariurut = jurnalmengajar_get_urutan_hari();
$jam_pelajaran = jurnalmengajar_generate_jam();

/*
=====================================================
1. PROSES DAFTAR HARI & AUTOMATIC FILTER
=====================================================
*/
$daftarhari = [];
foreach ($jadwal as $j) {
    if (!empty($j['hari'])) {
        $daftarhari[$j['hari']] = $j['hari'];
    }
}

uksort($daftarhari, function($a, $b) use ($hariurut) {
    return ($hariurut[$a] ?? 9) <=> ($hariurut[$b] ?? 9);
});

$hari_ini_nama = jurnalmengajar_get_hari_ini(); 
$default_hari = isset($daftarhari[$hari_ini_nama]) ? $hari_ini_nama : array_key_first($daftarhari);
$filterhari = $_GET['hari'] ?? $default_hari;

/*
=====================================================
2. DETEKSI JAM PELAJARAN AKTIF
=====================================================
*/
$now = date('H:i');
$jam_aktif_sekarang = 0;

if ($filterhari === $hari_ini_nama) {
    foreach ($jam_pelajaran as $j => $w) {
        if ($now >= $w['mulai'] && $now <= $w['selesai']) {
            $jam_aktif_sekarang = (int)$j;
            break;
        }
    }
}

/*
=====================================================
3. FORM FILTER UI
=====================================================
*/
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'mb-4 p-3 bg-light rounded border shadow-sm']);
echo html_writer::start_div('row align-items-end');

echo html_writer::start_div('col-md-6 mb-2 mb-md-0');
echo html_writer::tag('label', 'Pilih Hari Analisis', ['class' => 'font-weight-bold mb-1']);
echo html_writer::select($daftarhari, 'hari', $filterhari, null, [
    'class'    => 'form-control form-control-sm',
    'onchange' => 'this.form.submit()'
]);
echo html_writer::end_div();

echo html_writer::start_div('col-md-3');
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => '⚡ Tampilkan Jadwal', 'class' => 'btn btn-primary btn-sm btn-block']);
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_tag('form');

/*
=====================================================
4. PEMBENTUKAN MATRIKS JADWAL
=====================================================
*/
$matriks_jadwal = [];
$semua_kelas = [];

foreach ($jadwal as $j) {
    if ($j['hari'] != $filterhari) {
        continue;
    }
    $kelas = $j['kelas'];
    $jamke = (int)$j['jamke'];
    
    $semua_kelas[$kelas] = $kelas;
    $matriks_jadwal[$kelas][$jamke] = $j['lastname'];
}
asort($semua_kelas);

/*
=====================================================
5. RENDER TABEL DENGAN SELEKTOR FREEZE COLUMN
=====================================================
*/
echo html_writer::start_div('d-flex justify-content-between align-items-center mb-3');
echo html_writer::tag('h5', '📋 Visualisasi Grid Jadwal Hari: <span class="text-primary font-weight-bold">' . s($filterhari) . '</span>', ['class' => 'm-0']);
echo html_writer::end_div();

// Container pembungkus scrollbar
echo '<div class="table-freeze-container">';
echo '<table class="table table-bordered table-hover bg-white shadow-sm align-middle text-center" style="font-size:0.88rem; min-width: 1000px;">'; // Set min-width agar layout th tidak gepeng
echo '<thead class="thead-dark">';
echo '<tr>';
// Pasang class freeze-col-1 dan freeze-col-2 di header
echo '<th rowspan="2" class="freeze-col-1" style="width: 45px; vertical-align: middle; background-color: #343a40 !important; color: #fff;">No</th>';
echo '<th rowspan="2" class="freeze-col-2" style="width: 100px; vertical-align: middle; background-color: #343a40 !important; color: #fff;">Kelas</th>';
echo '<th colspan="11" class="text-center bg-secondary text-white p-1">Jam Pelajaran ke:</th>';
echo '</tr>';
echo '<tr>';

for ($i = 1; $i <= 11; $i++) {
    if ($i === $jam_aktif_sekarang) {
        echo '<th style="width: 80px; font-size:0.85rem;" class="bg-warning text-dark font-weight-bold"><i class="fa fa-play-circle text-danger"></i> ' . $i . '</th>';
    } else {
        echo '<th style="width: 80px; font-size:0.85rem;" class="bg-light text-dark font-weight-bold">' . $i . '</th>';
    }
}

echo '</tr>';
echo '</thead>';
echo '<tbody>';

$no = 1;
foreach ($semua_kelas as $k) {
    echo '<tr>';
    // Pasang class freeze-col-X pada data td row
    echo '<td class="text-center font-weight-bold text-muted align-middle freeze-col-1">' . $no++ . '</td>';
    echo '<td class="text-center font-weight-bold align-middle freeze-col-2"><span class="badge badge-info p-2 d-block" style="font-size:0.85rem;">' . s($k) . '</span></td>';

    for ($jam = 1; $jam <= 11; $jam++) {
        $is_jam_aktif = ($jam === $jam_aktif_sekarang);
        
        if (isset($matriks_jadwal[$k][$jam])) {
            $nama_guru = $matriks_jadwal[$k][$jam];
            $bg_color = $is_jam_aktif ? 'background-color: #fff3cd; font-weight: bold; border: 2px solid #ffc107;' : 'background-color: #ffffff;';
            
            echo '<td class="align-middle p-1 font-weight-normal" style="color: #212529; ' . $bg_color . ' line-height: 1.3; min-width: 90px;">';
            echo s($nama_guru);
            echo '</td>';
        } else {
            if ($is_jam_aktif) {
                echo '<td class="align-middle text-muted font-italic small" style="background-color: #fffdf4; border: 2px solid #ffc107; min-width: 90px;">-</td>';
            } else {
                echo '<td class="align-middle text-muted bg-light font-italic small" style="opacity: 0.5; min-width: 90px;">-</td>';
            }
        }
    }
    echo '</tr>';
}

if (empty($semua_kelas)) {
    echo '<tr><td colspan="13" class="text-center text-muted p-4"><i>Tidak ada entri data jadwal acuan untuk hari ' . s($filterhari) . '.</i></td></tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>'; // End table-freeze-container

echo $OUTPUT->footer();
