<?php
require('../../config.php');

require_once(__DIR__ . '/jadwal_acuan_lib.php');
require_once(__DIR__ . '/jam_pelajaran_lib.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

global $PAGE, $OUTPUT;


// ======================================================
// PARAMETER KELAS
// ======================================================

$kelas = required_param('kelas', PARAM_TEXT);


// ======================================================
// KONFIGURASI TANGGAL AWAL MINGGU
// Sama dengan rekap_perminggu.php
// ======================================================

$tanggalstring = get_config(
    'local_jurnalmengajar',
    'tanggalawalminggu'
) ?: '2025-07-01';

$tanggal_awal = new DateTime($tanggalstring);

// Pastikan awal minggu dimulai pukul 00:00:00.
$tanggal_awal->setTime(0, 0, 0);

$timestart = $tanggal_awal->getTimestamp();


// ======================================================
// TENTUKAN MINGGU BERJALAN
// ======================================================

$param_mingguke = optional_param(
    'mingguke',
    0,
    PARAM_INT
);

if ($param_mingguke > 0) {

    $mingguke = $param_mingguke;

} else {

    $selisih_hari = floor(
        (time() - $timestart) / 86400
    );

    $mingguke = floor(
        $selisih_hari / 7
    ) + 1;

    if ($mingguke < 1) {
        $mingguke = 1;
    }
}


// ======================================================
// TENTUKAN AWAL DAN AKHIR MINGGU DIPILIH
// ======================================================

$awal_minggu = clone $tanggal_awal;

$awal_minggu->modify(
    '+' . (($mingguke - 1) * 7) . ' days'
);

$awal_minggu->setTime(0, 0, 0);


$akhir_minggu = clone $awal_minggu;

$akhir_minggu->modify('+6 days');

$akhir_minggu->setTime(23, 59, 59);


// ======================================================
// DATA IDENTITAS SEKOLAH
// ======================================================

$namasekolah = get_config(
    'local_jurnalmengajar',
    'nama_sekolah'
);

$tahunajaran = get_config(
    'local_jurnalmengajar',
    'tahun_ajaran'
);


// ======================================================
// DETEKSI SEMESTER
// ======================================================

$bulan_awal = (int)$tanggal_awal->format('n');

$semester = ($bulan_awal >= 7)
    ? 'Ganjil'
    : 'Genap';


// ======================================================
// KONFIGURASI HALAMAN
// ======================================================

$PAGE->set_context($context);

$PAGE->set_url(
    new moodle_url(
        '/local/jurnalmengajar/kontrol_kehadiran_guru.php',
        [
            'kelas' => $kelas,
            'mingguke' => $mingguke
        ]
    )
);

$PAGE->set_pagelayout('base');

$judul = 'Kontrol Kehadiran Guru - ' .
    $kelas .
    ' Minggu ke-' .
    $mingguke;

$PAGE->set_title($judul);

$PAGE->set_heading($judul);


// ======================================================
// AMBIL DATA JADWAL
// ======================================================

$jadwal = jurnalmengajar_get_jadwal_acuan();

$hariurut = jurnalmengajar_get_urutan_hari();

$jadwalkelas = [];

foreach ($jadwal as $j) {

    if ($j['kelas'] !== $kelas) {
        continue;
    }

    $jadwalkelas[] = $j;
}


// ======================================================
// URUTKAN DATA JADWAL
// ======================================================

usort($jadwalkelas, function($a, $b) use ($hariurut) {

    $haria = $hariurut[$a['hari']] ?? 99;

    $harib = $hariurut[$b['hari']] ?? 99;


    if ($haria !== $harib) {
        return $haria <=> $harib;
    }


    $jama = (int)$a['jamke'];

    $jamb = (int)$b['jamke'];


    if ($jama !== $jamb) {
        return $jama <=> $jamb;
    }


    return strcmp(
        $a['lastname'] ?? '',
        $b['lastname'] ?? ''
    );
});


// ======================================================
// KELOMPOKKAN JADWAL
//
// Aturan:
// 1. Jam berurutan guru yang sama digabung.
// 2. Jika ada istirahat, kelompok diputus.
// 3. Jika guru berbeda, kelompok diputus.
// ======================================================

$kelompok = [];

$current = null;


foreach ($jadwalkelas as $j) {

    $hari = $j['hari'];

    $userid = (int)$j['userid'];

    $jamke = (int)$j['jamke'];


    $jam_pelajaran =
        jurnalmengajar_generate_jam_hari($hari);


    $gabung = false;


    if ($current !== null) {

        $sama_hari =
            ($current['hari'] === $hari);

        $sama_guru =
            ($current['userid'] === $userid);


        $jam_sebelumnya =
            $current['jam_akhir'];


        $berurutan =
            ($jamke === ($jam_sebelumnya + 1));


        // Cek apakah setelah jam sebelumnya ada istirahat.
        $ada_istirahat = !empty(
            $jam_pelajaran[$jam_sebelumnya]
            ['istirahat_setelah']
        );


        if (
            $sama_hari &&
            $sama_guru &&
            $berurutan &&
            !$ada_istirahat
        ) {

            $gabung = true;
        }
    }


    // Jika dapat digabung.
    if ($gabung) {

        $current['jam'][] = $jamke;

        $current['jam_akhir'] = $jamke;

        continue;
    }


    // Simpan kelompok sebelumnya.
    if ($current !== null) {

        $kelompok[] = $current;
    }


    // Buat kelompok baru.
    $current = [

        'hari' => $hari,

        'hari_no' =>
            $hariurut[$hari] ?? 99,

        'userid' => $userid,

        'guru' =>
            $j['lastname'] ?? '-',

        'jam' => [$jamke],

        'jam_awal' => $jamke,

        'jam_akhir' => $jamke
    ];
}


// Simpan kelompok terakhir.
if ($current !== null) {

    $kelompok[] = $current;
}


// ======================================================
// KELOMPOKKAN BERDASARKAN HARI
// ======================================================

$perhari = [];

foreach ($kelompok as $g) {

    $hari = $g['hari'];


    if (!isset($perhari[$hari])) {

        $perhari[$hari] = [];
    }


    $perhari[$hari][] = $g;
}


// ======================================================
// FUNGSI FORMAT TANGGAL INDONESIA
// ======================================================

function kontrol_format_tanggal_indo($datetime) {

    $bulan = [

        1  => 'Januari',
        2  => 'Februari',
        3  => 'Maret',
        4  => 'April',
        5  => 'Mei',
        6  => 'Juni',
        7  => 'Juli',
        8  => 'Agustus',
        9  => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];


    return
        $datetime->format('j') .
        ' ' .
        $bulan[(int)$datetime->format('n')] .
        ' ' .
        $datetime->format('Y');
}


// ======================================================
// MAP HARI KE OFFSET
// ======================================================

$maphari = [

    'Senin'  => 0,

    'Selasa' => 1,

    'Rabu'   => 2,

    'Kamis'  => 3,

    'Jumat'  => 4,

    'Sabtu'  => 5,

    'Minggu' => 6
];


// ======================================================
// HEADER
// ======================================================

echo $OUTPUT->header();


// ======================================================
// CSS
// ======================================================

echo html_writer::tag(
    'style',
    '

    .kontrol-wrapper {

        max-width: 1500px;

        width: 98%;

        margin: 0 auto;
    }


    .kontrol-header {

        border: 2px solid #000;

        padding: 15px;

        margin-bottom: 20px;

        text-align: center;
    }


    .kontrol-title {

        font-size: 24px;

        font-weight: bold;

        margin-bottom: 5px;

        text-transform: uppercase;
    }


    .kontrol-subtitle {

        font-size: 17px;
    }


    .kontrol-info {

        margin-bottom: 20px;

        font-size: 16px;
    }


    .periode-info {

        font-size: 16px;

        font-weight: bold;

        margin-top: 5px;
    }


    .hari-title {

        border: 2px solid #000;

        padding: 8px;

        text-align: center;

        font-weight: bold;

        margin-top: 25px;

        margin-bottom: 8px;

        font-size: 18px;

        background: #f2f2f2;
    }


    .kontrol-table {

        width: 100%;

        border-collapse: collapse;

        font-size: 14px;

        margin-bottom: 15px;
    }


    .kontrol-table th,

    .kontrol-table td {

        border: 1px solid #000 !important;

        padding: 8px 6px;

        vertical-align: middle;
    }


    .kontrol-table th {

        text-align: center;

        font-weight: bold;

        background: #f2f2f2;
    }


    .istirahat-row td {

        background: #eeeeee !important;

        font-weight: bold;

        text-align: center;

        letter-spacing: 1px;
    }


    .checkbox-cell {

        text-align: center;

        font-size: 20px;
    }


    .catatan-cell {

        min-width: 120px;
    }


    .tanda-tangan {

        margin-top: 35px;

        width: 300px;

        margin-left: auto;

        text-align: center;
    }


    @media print {

        @page {

            size: A4;

            margin: 8mm;
        }


        body {

            background: #fff !important;
        }


        #page-header,

        #page-footer,

        .navbar,

        .breadcrumb,

        .secondary-navigation,

        .drawer,

        .btn,

        .no-print {

            display: none !important;
        }


        .main-inner,

        #page,

        #page-wrapper,

        #region-main,

        .container,

        .container-fluid {

            margin: 0 !important;

            padding: 0 !important;

            max-width: 100% !important;

            width: 100% !important;
        }


        .kontrol-wrapper {

            max-width: 100% !important;

            width: 100% !important;

            margin: 0 !important;
        }


        .kontrol-table {

            font-size: 10px;
        }


        .kontrol-table th,

        .kontrol-table td {

            padding: 4px !important;
        }


        .hari-title {

            margin-top: 15px;

            margin-bottom: 5px;
        }
    }
    '
);


// ======================================================
// WRAPPER
// ======================================================

echo html_writer::start_div(
    'kontrol-wrapper'
);


// ======================================================
// TOMBOL DAN FILTER
// ======================================================

echo html_writer::start_div(
    'card mb-4 shadow-sm no-print'
);


echo html_writer::start_div(
    'card-body'
);


echo html_writer::start_div(
    'row align-items-center'
);


// ------------------------------------------------------
// INFORMASI PERIODE
// ------------------------------------------------------

echo html_writer::start_div(
    'col-md-5 mb-3 mb-md-0'
);


echo html_writer::tag(
    'div',
    'PERIODE FORM KONTROL',
    [
        'class' =>
            'text-muted small font-weight-bold'
    ]
);


echo html_writer::tag(
    'div',
    'Minggu ke-' . $mingguke,
    [
        'class' =>
            'h5 font-weight-bold mb-1'
    ]
);


echo html_writer::tag(
    'div',
    kontrol_format_tanggal_indo($awal_minggu) .
    ' s/d ' .
    kontrol_format_tanggal_indo($akhir_minggu),
    [
        'class' =>
            'text-dark font-weight-bold'
    ]
);


echo html_writer::end_div();


// ------------------------------------------------------
// FORM PILIH MINGGU
// ------------------------------------------------------

echo html_writer::start_div(
    'col-md-7'
);


echo '<form method="get" class="form-inline justify-content-md-end m-0">';


echo '<input type="hidden" name="kelas" value="' .
    s($kelas) .
    '">';


echo html_writer::start_div(
    'form-group mr-3 mb-0'
);


echo '<label for="mingguke" class="mr-2 font-weight-bold small">Minggu:</label>';


echo '<select
    name="mingguke"
    id="mingguke"
    class="custom-select custom-select-sm"
    onchange="this.form.submit()"
>';


$maxminggu = max(
    $mingguke + 10,
    25
);


for ($i = 1; $i <= $maxminggu; $i++) {

    $selected =
        ($i == $mingguke)
        ? 'selected'
        : '';


    echo '<option value="' .
        $i .
        '" ' .
        $selected .
        '>';

    echo 'Minggu ke-' . $i;

    echo '</option>';
}


echo '</select>';


echo html_writer::end_div();


echo '<button
    type="submit"
    class="btn btn-primary btn-sm mr-2"
>
    Tampilkan
</button>';


echo html_writer::link(

    new moodle_url(
        '/local/jurnalmengajar/wali_kelas.php'
    ),

    '← Kembali',

    [
        'class' =>
            'btn btn-secondary btn-sm mr-2'
    ]
);


echo html_writer::tag(

    'button',

    '🖨️ Cetak Form',

    [

        'type' => 'button',

        'class' =>
            'btn btn-success btn-sm',

        'onclick' =>
            'window.print();'
    ]
);


echo '</form>';


echo html_writer::end_div();


echo html_writer::end_div();


echo html_writer::end_div();


echo html_writer::end_div();


// ======================================================
// JUDUL FORM
// ======================================================

echo html_writer::start_div(
    'kontrol-header'
);


if (!empty($namasekolah)) {

    echo html_writer::div(

        strtoupper($namasekolah),

        'font-weight-bold mb-1'
    );
}


echo html_writer::div(

    'KONTROL KEHADIRAN GURU MATA PELAJARAN',

    'kontrol-title'
);


echo html_writer::start_div(
'kontrol-info-satu-baris'
);

echo html_writer::tag(
'span',
'KELAS: ' . s($kelas),
['class' => 'mr-4']
);

echo html_writer::tag(
'span',
'MINGGU KE-' . $mingguke,
['class' => 'mr-4']
);

echo html_writer::tag(
'span',
kontrol_format_tanggal_indo($awal_minggu) .
' s/d ' .
kontrol_format_tanggal_indo($akhir_minggu)
);

echo html_writer::end_div();



if (!empty($tahunajaran)) {

    echo html_writer::div(

        'Tahun Ajaran ' .
        s($tahunajaran) .
        ' - Semester ' .
        s($semester),

        'mt-1'
    );
}


echo html_writer::end_div();


// ======================================================
// TAMPILKAN TABEL PER HARI
// ======================================================

if (empty($perhari)) {

    echo html_writer::div(

        'Tidak ada jadwal untuk kelas ini.',

        'alert alert-warning text-center'
    );

} else {


    foreach (
        $perhari as
        $hari => $daftarhari
    ) {


        // --------------------------------------------------
        // AMBIL TANGGAL HARI TERSEBUT
        // --------------------------------------------------

        $offset =
            $maphari[$hari] ?? 0;


        $tanggal_hari =
            clone $awal_minggu;


        if ($offset > 0) {

            $tanggal_hari->modify(
                '+' . $offset . ' days'
            );
        }


        // --------------------------------------------------
        // AMBIL JAM PELAJARAN
        // --------------------------------------------------

        $jam_pelajaran =
            jurnalmengajar_generate_jam_hari(
                $hari
            );


        // --------------------------------------------------
        // JUDUL HARI + TANGGAL
        // --------------------------------------------------

        echo html_writer::div(

            strtoupper($hari) .
            ', ' .
            strtoupper(
                kontrol_format_tanggal_indo(
                    $tanggal_hari
                )
            ),

            'hari-title'
        );


        // --------------------------------------------------
        // TABEL
        // --------------------------------------------------

        echo '<div class="table-responsive">';

        echo '<table class="kontrol-table">';

        echo '<thead>';

        echo '<tr>';

        echo '<th style="width:5%;">No</th>';

        echo '<th style="width:8%;">Jam Ke</th>';

        echo '<th style="width:11%;">Pukul</th>';

        echo '<th style="width:15%;">Guru Pengajar</th>';

        echo '<th style="width:9%;">Masuk<br>Tepat Waktu</th>';

        echo '<th style="width:9%;">Terlambat</th>';

        echo '<th style="width:9%;">Tidak<br>Masuk</th>';

        echo '<th style="width:10%;">Keluar<br>Lebih Awal</th>';

        echo '<th>Catatan</th>';

        echo '</tr>';

        echo '</thead>';

        echo '<tbody>';


        // Nomor dimulai dari 1 setiap hari.
        $no = 1;


        // ==================================================
        // DATA JADWAL HARI
        // ==================================================

        foreach (
            $daftarhari as
            $index => $g
        ) {


            // ----------------------------------------------
            // JAM
            // ----------------------------------------------

            $jamlist =
                $g['jam'];


            sort($jamlist);


            if (
                count($jamlist) === 1
            ) {

                $jamtext =
                    'Jam ' .
                    $jamlist[0];

            } else {

                $jamtext =
                    'Jam ' .
                    $jamlist[0] .
                    '–' .
                    end($jamlist);
            }


            // ----------------------------------------------
            // WAKTU
            // ----------------------------------------------

            $jamawal =
                min($jamlist);


            $jamakhir =
                max($jamlist);


            $mulai =
                $jam_pelajaran[$jamawal]['mulai']
                ?? '';


            $selesai =
                $jam_pelajaran[$jamakhir]['selesai']
                ?? '';


            $pukul =
                $mulai .
                ' - ' .
                $selesai;


            // ----------------------------------------------
            // BARIS JADWAL
            // ----------------------------------------------

            echo '<tr>';


            echo '<td class="text-center">' .
                $no++ .
                '</td>';


            echo '<td class="text-center">' .
                s($jamtext) .
                '</td>';


            echo '<td class="text-center">' .
                s($pukul) .
                '</td>';


            echo '<td>' .
                s($g['guru']) .
                '</td>';


            echo '<td class="checkbox-cell">□</td>';


            echo '<td class="checkbox-cell">□</td>';


            echo '<td class="checkbox-cell">□</td>';


            echo '<td class="checkbox-cell">□</td>';


            echo '<td class="catatan-cell"></td>';


            echo '</tr>';


            // ----------------------------------------------
            // CEK ISTIRAHAT
            // ----------------------------------------------

            $jamakhir_kelompok =
                $g['jam_akhir'];


            $istirahat =
                $jam_pelajaran[
                    $jamakhir_kelompok
                ]['istirahat_setelah']
                ?? 0;


            // ----------------------------------------------
            // TAMPILKAN ISTIRAHAT
            // ----------------------------------------------

            if (

                $istirahat &&

                isset(
                    $daftarhari[
                        $index + 1
                    ]
                )

            ) {


                echo '<tr class="istirahat-row">';


                echo '<td colspan="9">';


                echo '☕ ISTIRAHAT ' .
                    (int)$istirahat .
                    ' MENIT';


                echo '</td>';


                echo '</tr>';
            }
        }


        echo '</tbody>';

        echo '</table>';

        echo '</div>';
    }
}



// ======================================================
// TANDA TANGAN
// ======================================================

echo html_writer::start_div(
    'tanda-tangan'
);


echo 'Ketua/Wakil Kelas<br><br><br><br>';


echo '_____________________________<br>';


echo 'Nama: ________________________';


echo html_writer::end_div();


// ======================================================
// FOOTER
// ======================================================

echo html_writer::end_div();

echo $OUTPUT->footer();
?>
