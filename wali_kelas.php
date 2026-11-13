<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
jurnalmengajar_log_page_access();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

global $DB, $PAGE, $OUTPUT;

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/wali_kelas.php'));
$PAGE->set_pagelayout('base');
$PAGE->set_title('Daftar Wali Kelas');
$PAGE->set_heading('Daftar Wali Kelas');

echo $OUTPUT->header();

// ======================================================
// Ambil Mapping Wali Kelas
// Format BARU (per tahun ajaran, sejak walikelas_manage.php
// diubah 2026-11-13):
//   { "2026/2027": { cohortid: userid }, "2025/2026": { ... } }
// Format LAMA { cohortid: userid } tetap didukung.
// ======================================================

$json = get_config(
    'local_jurnalmengajar',
    'wali_kelas_mapping'
);

$mapping = json_decode($json, true);

if (!is_array($mapping)) {
    $mapping = [];
}

// Tahun ajaran berjalan: pakai setting plugin 'tahun_ajaran'
// (yang dipakai simpan_riwayatkelas.php); kalau belum diatur,
// hitung dari tanggal (tahun ajaran mulai Juli).
$tahunajaran_config = get_config('local_jurnalmengajar', 'tahun_ajaran');
if (is_string($tahunajaran_config) && preg_match('/^\d{4}\/\d{4}$/', $tahunajaran_config)) {
    $tahunajaran_berjalan = $tahunajaran_config;
} else {
    $bulan_ini = (int)date('n');
    $tahun_mulai = ($bulan_ini >= 7) ? (int)date('Y') : (int)date('Y') - 1;
    $tahunajaran_berjalan = $tahun_mulai . '/' . ($tahun_mulai + 1);
}

// Tahun ajaran yang ditampilkan (bisa dipilih lewat ?tahunajaran=)
$tahunajaran_pilih = optional_param('tahunajaran', '', PARAM_TEXT);
if (!preg_match('/^\d{4}\/\d{4}$/', $tahunajaran_pilih)) {
    $tahunajaran_pilih = $tahunajaran_berjalan;
}

// Ambil mapping untuk tahun ajaran yang dipilih saja
$mapping_tahun_ini = [];
if (isset($mapping[$tahunajaran_pilih]) && is_array($mapping[$tahunajaran_pilih])) {
    $mapping_tahun_ini = $mapping[$tahunajaran_pilih];
} elseif (!empty($mapping)) {
    // Fallback format lama: cek apakah nilai-nilainya userid (bukan array)
    $is_flat = true;
    foreach ($mapping as $nilai) {
        if (is_array($nilai)) {
            $is_flat = false;
            break;
        }
    }
    if ($is_flat) {
        $mapping_tahun_ini = $mapping;
    }
}

// Daftar tahun ajaran yang ada di mapping (untuk dropdown filter)
$tahunajaran_tersedia = [];
foreach ($mapping as $kunci => $nilai) {
    if (is_array($nilai) && preg_match('/^\d{4}\/\d{4}$/', (string)$kunci)) {
        $tahunajaran_tersedia[$kunci] = $kunci;
    }
}
$tahunajaran_tersedia[$tahunajaran_berjalan] = $tahunajaran_berjalan;
krsort($tahunajaran_tersedia);

// ======================================================
// Susun Data
// ======================================================

$data = [];

foreach ($mapping_tahun_ini as $cohortid => $userid) {

    // Ambil data kelas
    $cohort = $DB->get_record(
        'cohort',
        ['id' => $cohortid]
    );

    if (!$cohort) {
        continue;
    }

    // Ambil data guru
    $guru = $DB->get_record(
        'user',
        ['id' => $userid]
    );

    if (!$guru) {
        continue;
    }

    // Hitung jumlah murid dalam cohort
    $jumlahmurid = $DB->count_records(
        'cohort_members',
        ['cohortid' => $cohortid]
    );

    $data[] = [
        'cohortid'    => $cohortid,
        'kelas'       => $cohort->name,
        'userid'      => $userid,
        'walikelas'   => !empty($guru->lastname)
                            ? $guru->lastname
                            : fullname($guru),
        'jumlahmurid' => $jumlahmurid
    ];
}

// ======================================================
// Urutkan berdasarkan nama kelas
// ======================================================

usort($data, function($a, $b) {
    return strnatcasecmp(
        $a['kelas'],
        $b['kelas']
    );
});

// ======================================================
// TAMPILAN
// ======================================================

echo html_writer::start_div('container-fluid');

// Filter tahun ajaran
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'mb-3 form-inline']);
echo html_writer::label('Tahun Ajaran', 'tahunajaran', ['class' => 'font-weight-bold mr-2']);
echo html_writer::select($tahunajaran_tersedia, 'tahunajaran', $tahunajaran_pilih, [], ['class' => 'form-control custom-select mr-2', 'id' => 'tahunajaran', 'onchange' => 'this.form.submit()']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Tampilkan', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

echo html_writer::start_div('card shadow-sm');

echo html_writer::start_div(
    'card-header bg-primary text-white'
);

echo html_writer::tag(
    'h4',
    '📋 Daftar Wali Kelas',
    ['class' => 'mb-0']
);

echo html_writer::end_div(); // card-header

echo html_writer::start_div('card-body');

// ======================================================
// Jika belum ada mapping
// ======================================================

if (empty($data)) {

    echo html_writer::div(
        'Belum ada data wali kelas untuk tahun ajaran ' . s($tahunajaran_pilih) . '.',
        'alert alert-info text-center'
    );

} else {

    $table = new html_table();

    $table->attributes['class'] =
        'table table-bordered table-striped table-hover text-center align-middle';

    $table->head = [
        'No',
        'Kelas',
        'Wali Kelas',
        'Jumlah Murid',
        'Lihat'
    ];

    $no = 1;

    foreach ($data as $row) {

        // ------------------------------------------------
        // Tombol
        // ------------------------------------------------

$jadwal = html_writer::link(
    new moodle_url(
        '/local/jurnalmengajar/jadwal_kelas.php',
        [
            'kelas' => $row['kelas']
        ]
    ),
    '📅 Jadwal',
    [
        'class' => 'btn btn-sm btn-outline-primary me-1'
    ]
);

$murid = html_writer::link(
    new moodle_url(
        '/local/jurnalmengajar/murid_kelas.php',
        [
            'cohortid' => $row['cohortid']
        ]
    ),
    '👨‍🎓 Murid',
    [
        'class' => 'btn btn-sm btn-outline-success me-1'
    ]
);

$kontrol = html_writer::link(
    new moodle_url(
        '/local/jurnalmengajar/kontrol_kehadiran_guru.php',
        [
            'kelas' => $row['kelas']
        ]
    ),
    '📝 Kontrol Guru',
    [
        'class' => 'btn btn-sm btn-outline-danger'
    ]
);

$aksi = $jadwal . ' ' . $murid . ' ' . $kontrol;

        $table->data[] = [
            $no++,
            html_writer::tag(
                'strong',
                s($row['kelas'])
            ),
            s($row['walikelas']),
            html_writer::tag(
                'span',
                $row['jumlahmurid'],
                [
                    'class' =>
                    'badge badge-info'
                ]
            ),
            $aksi
        ];
    }

    echo html_writer::start_div(
        'table-responsive'
    );

    echo html_writer::table($table);

    echo html_writer::end_div();
}

echo html_writer::end_div(); // card-body

echo html_writer::end_div(); // card

echo html_writer::end_div(); // container

// ======================================================
// FOOTER
// ======================================================

echo $OUTPUT->footer();
