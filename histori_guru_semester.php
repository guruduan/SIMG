<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__.'/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

global $DB;

// =====================
// PARAMETER
// =====================
$userid = required_param('userid', PARAM_INT);

$tahunajaran = optional_param('tahunajaran', '', PARAM_TEXT);
$semester = optional_param('semester', '', PARAM_TEXT);

// =====================
// TAHUN AJARAN & SEMESTER
// =====================

if (empty($tahunajaran)) {

    $tahunajaran = jurnalmengajar_get_tahunajaran_by_timestamp(
        time()
    );
}

if (empty($semester)) {

    $bulan_sekarang = (int)date('n');

    $semester = ($bulan_sekarang >= 7)
        ? 'Ganjil'
        : 'Genap';
}

// ambil awal semester dari jurnal pertama
$timestart = jurnalmengajar_get_awal_semester_dari_jurnal(
    $tahunajaran,
    $semester
);

$tanggal_awal = new DateTime();
$tanggal_awal->setTimestamp($timestart);
$totalminggu = jurnalmengajar_get_total_minggu_semester(
    $tahunajaran,
    $semester
);

// =====================
// DATA USER
// =====================
$user = $DB->get_record(
    'user',
    ['id' => $userid],
    'id,lastname'
);

if (!$user) {
    print_error('Guru tidak ditemukan.');
}

$nama = ucwords($user->lastname);

// =====================
// LOAD SNAPSHOT BEBAN
// =====================
$beban_snapshot = jurnalmengajar_load_beban_snapshot(
    $tahunajaran,
    $semester
);

// fallback jika snapshot belum ada
if (empty($beban_snapshot)) {

    $beban_snapshot = jurnalmengajar_get_beban_jam_guru_by_date(
        $timestart
    );
}

// =====================
// HEADER
// =====================
$judul = 'Riwayat Jurnal Mengajar Guru Per Semester';

$PAGE->set_context($context);

$PAGE->set_url(
    new moodle_url(
        '/local/jurnalmengajar/histori_guru_semester.php',
        [
            'userid' => $userid,
            'tahunajaran' => $tahunajaran,
            'semester' => $semester
        ]
    )
);

$PAGE->set_title($judul);
$PAGE->set_heading($judul);

echo $OUTPUT->header();

echo html_writer::tag(
    'h3',
    'Tahun Ajaran: ' . $tahunajaran
);

echo html_writer::tag(
    'h3',
    'Semester: ' . $semester
);

echo html_writer::tag(
    'h4',
    'Nama Guru: ' . $nama
);

// =====================
// HITUNG MINGGU
// =====================
$sekarang = time();

$selisih_hari = floor(
    ($sekarang - $timestart) / (60 * 60 * 24)
);

$minggu_berjalan = floor($selisih_hari / 7) + 1;

if ($minggu_berjalan < 1) {
    $minggu_berjalan = 1;
}

if ($minggu_berjalan > $totalminggu) {
    $minggu_berjalan = $totalminggu;
}

// =====================
// REKAP MINGGUAN
// =====================
$rekap_mingguan = [];

for ($i = 1; $i <= $minggu_berjalan; $i++) {

    $awal = clone $tanggal_awal;
    $awal->modify('+' . (($i - 1) * 7) . ' days');

    $akhir = clone $awal;
    $akhir->modify('+6 days');

    $start = $awal->getTimestamp();
    $end = $akhir->getTimestamp() + 86399;

    // ambil jurnal
    $entries = $DB->get_records_select(
        'local_jurnalmengajar',
        'userid = ? AND timecreated BETWEEN ? AND ?',
        [$userid, $start, $end]
    );

    // hitung jumlah jam
    $jumlah = 0;

    foreach ($entries as $e) {

        $jumlah += !empty($e->jamke)
            ? count(array_filter(explode(',', $e->jamke)))
            : 0;
    }

    // beban snapshot
    $beban_minggu = $beban_snapshot[$userid] ?? 0;

    $persen = ($beban_minggu > 0)
        ? round(($jumlah / $beban_minggu) * 100)
        : 0;

    // rentang tanggal
    $awal_str = tanggal_indo($start, 'tglbulan');
    $akhir_str = tanggal_indo($end, 'tanggal');

    $rentang = $awal_str . ' - ' . $akhir_str;

    $rekap_mingguan[] = [
        'minggu' => $i,
        'jumlah' => $jumlah,
        'beban' => $beban_minggu,
        'persen' => $persen,
        'rentang' => $rentang
    ];
}

// =====================
// TABEL
// =====================
echo html_writer::start_tag(
    'table',
    ['class' => 'generaltable']
);

echo html_writer::tag(
    'tr',
    html_writer::tag('th', 'No') .
    html_writer::tag('th', 'Minggu ke') .
    html_writer::tag('th', 'Rentang Tanggal') .
    html_writer::tag('th', 'Jumlah Mengajar') .
//    html_writer::tag('th', 'Beban Jam') .
//    html_writer::tag('th', '% Mingguan') .
    html_writer::tag('th', 'Aksi')
);

$no = 1;

foreach ($rekap_mingguan as $r) {

    $style = '';

    if ($r['persen'] >= 80) {
        $style = 'color:green;font-weight:bold';
    } elseif ($r['jumlah'] == 0 && $r['beban'] > 0) {
        $style = 'color:red;font-weight:bold';
    } elseif ($r['persen'] < 50) {
        $style = 'color:orange;font-weight:bold';
    }

    $url = new moodle_url(
        '/local/jurnalmengajar/histori_perguru.php',
        [
            'userid' => $userid,
            'mingguke' => $r['minggu'],
            'tahunajaran' => $tahunajaran,
            'semester' => $semester
        ]
    );

    echo html_writer::tag(
        'tr',
        html_writer::tag('td', $no++) .
        html_writer::tag('td', $r['minggu']) .
        html_writer::tag('td', $r['rentang']) .
        html_writer::tag('td', $r['jumlah']) .
//        html_writer::tag('td', $r['beban']) .
//        html_writer::tag(
//            'td',
//            $r['persen'] . '%',
//            ['style' => $style]
//        ) .
        html_writer::tag(
            'td',
            html_writer::link($url, '🔍 Detail')
        )
    );
}

echo html_writer::end_tag('table');

// =====================
// KEMBALI
// =====================
echo html_writer::div(
    html_writer::link(
        new moodle_url(
            '/local/jurnalmengajar/histori_rekap.php',
            [
                'tahunajaran' => $tahunajaran,
                'semester' => $semester
            ]
        ),
        '⬅ Kembali ke Histori Rekap'
    ),
    'mt-3'
);

echo $OUTPUT->footer();
