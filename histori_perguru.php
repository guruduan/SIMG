<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__.'/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

global $DB;

// =====================
// FUNCTION FORMAT TANGGAL
// =====================
function format_tanggal_indonesia($timestamp) {

    $hari = [
        'Minggu','Senin','Selasa',
        'Rabu','Kamis','Jumat','Sabtu'
    ];

    $bulan = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];

    $d = getdate($timestamp);

    $hari_indo = $hari[$d['wday']];
    $tanggal = $d['mday'];
    $bulan_indo = $bulan[$d['mon']];
    $tahun = $d['year'];

    return "$hari_indo, $tanggal $bulan_indo $tahun";
}

// =====================
// PARAMETER
// =====================
$userid = required_param('userid', PARAM_INT);
$mingguke = required_param('mingguke', PARAM_INT);

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
$awal_semester = jurnalmengajar_get_awal_semester_dari_jurnal(
    $tahunajaran,
    $semester
);

$tanggal_awal = new DateTime();
$tanggal_awal->setTimestamp($awal_semester);
$totalminggu = jurnalmengajar_get_total_minggu_semester(
    $tahunajaran,
    $semester
);

if ($mingguke < 1 || $mingguke > $totalminggu) {
    print_error('Parameter minggu ke tidak valid.');
}

// =====================
// HITUNG RENTANG MINGGU
// =====================
$tanggal_awal->modify('+' . (($mingguke - 1) * 7) . ' days');

$tanggal_akhir = clone $tanggal_awal;
$tanggal_akhir->modify('+6 days');

$timestart = $tanggal_awal->getTimestamp();
$timeend = $tanggal_akhir->getTimestamp() + 86399;

// =====================
// AMBIL DATA USER
// =====================
$user = $DB->get_record(
    'user',
    ['id' => $userid],
    'id,firstname,lastname'
);

if (!$user) {
    print_error('Guru tidak ditemukan.');
}

$nama = ucwords($user->lastname);

// =====================
// AMBIL ENTRI JURNAL
// =====================
$entries = $DB->get_records_select(
    'local_jurnalmengajar',
    'userid = ? AND timecreated BETWEEN ? AND ?',
    [$userid, $timestart, $timeend],
    'timecreated ASC'
);

// =====================
// HEADER
// =====================
$PAGE->set_context($context);

$PAGE->set_url(
    new moodle_url(
        '/local/jurnalmengajar/histori_perguru.php',
        [
            'userid' => $userid,
            'mingguke' => $mingguke,
            'tahunajaran' => $tahunajaran,
            'semester' => $semester
        ]
    )
);

$PAGE->set_title("Riwayat Mengajar $nama");

$PAGE->set_heading(
    "Riwayat Mengajar $nama - Minggu ke-$mingguke"
);

echo $OUTPUT->header();

echo html_writer::tag(
    'h3',
    "Tahun Ajaran: $tahunajaran"
);

echo html_writer::tag(
    'h3',
    "Semester: $semester"
);

echo html_writer::tag(
    'h4',
    "Rekap Mengajar $nama"
);

echo html_writer::tag(
    'div',
    format_tanggal_indonesia($tanggal_awal->getTimestamp()) .
    ' s.d. ' .
    format_tanggal_indonesia($tanggal_akhir->getTimestamp()),
    [
        'style' => 'margin-bottom:15px;font-weight:bold'
    ]
);

// =====================
// TABEL
// =====================
echo html_writer::start_tag(
    'table',
    ['class' => 'generaltable']
);

echo html_writer::start_tag('thead');

echo html_writer::tag(
    'tr',
    html_writer::tag('th', 'No') .
    html_writer::tag('th', 'Hari, Tanggal') .
    html_writer::tag('th', 'Kelas') .
    html_writer::tag('th', 'Jamke') .
    html_writer::tag('th', 'Mata Pelajaran') .
    html_writer::tag('th', 'Materi') .
    html_writer::tag('th', 'Absen') .
    html_writer::tag('th', 'Keterangan')
);

echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');

$no = 1;

if (empty($entries)) {

    echo html_writer::start_tag('tr');

    echo html_writer::tag(
        'td',
        'Tidak ada data jurnal mengajar pada minggu ini',
        [
            'colspan' => 8,
            'style' => 'text-align:center;font-style:italic;'
        ]
    );

    echo html_writer::end_tag('tr');
}

foreach ($entries as $entry) {

    $tanggal = format_tanggal_indonesia(
        $entry->timecreated
    );

    $jamke = $entry->jamke ?: '-';

    // kelas
    if (is_numeric($entry->kelas)) {

        $cohort = $DB->get_record(
            'cohort',
            ['id' => (int)$entry->kelas],
            'name'
        );

        $kelas = $cohort
            ? $cohort->name
            : '(tidak ditemukan)';

    } else {

        $kelas = $entry->kelas ?: '-';
    }

    $mapel = $entry->matapelajaran ?: '-';
    $materi = $entry->materi ?: '-';

    // absen JSON
    $absen = '-';

    $absendata = json_decode($entry->absen, true);

    if (is_array($absendata)) {

        $absen = '';

        foreach ($absendata as $nama_siswa => $alasan) {
            $absen .= "$nama_siswa ($alasan), ";
        }

        $absen = rtrim($absen, ', ');

    } elseif (!empty($entry->absen)) {

        $absen = $entry->absen;
    }

    $keterangan = $entry->keterangan ?: '-';

    echo html_writer::start_tag('tr');

    echo html_writer::tag('td', $no++);
    echo html_writer::tag('td', $tanggal);
    echo html_writer::tag('td', $kelas);
    echo html_writer::tag('td', $jamke);
    echo html_writer::tag('td', $mapel);
    echo html_writer::tag('td', $materi);
    echo html_writer::tag('td', $absen);
    echo html_writer::tag('td', $keterangan);

    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');

echo html_writer::end_tag('table');

// =====================
// KEMBALI
// =====================
echo html_writer::div(
    html_writer::link(
        new moodle_url(
            '/local/jurnalmengajar/histori_guru_semester.php',
            [
                'userid' => $userid,
                'tahunajaran' => $tahunajaran,
                'semester' => $semester
            ]
        ),
        '⬅ Kembali ke Histori Semester Guru'
    ),
    'mt-3'
);

echo $OUTPUT->footer();
