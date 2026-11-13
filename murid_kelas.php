<?php
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

global $DB, $PAGE, $OUTPUT;

$cohortid = required_param('cohortid', PARAM_INT);

$cohort = $DB->get_record(
    'cohort',
    ['id' => $cohortid],
    '*',
    MUST_EXIST
);

// ======================================================
// Wali Kelas kelas ini (tahun ajaran berjalan)
// dari mapping wali_kelas_mapping (per tahun ajaran,
// dengan fallback ke format lama {cohortid: userid})
// ======================================================
$tahunajaran_config = get_config('local_jurnalmengajar', 'tahun_ajaran');
if (is_string($tahunajaran_config) && preg_match('/^\d{4}\/\d{4}$/', $tahunajaran_config)) {
    $tahunajaran_berjalan = $tahunajaran_config;
} else {
    $bulan_ini = (int)date('n');
    $tahun_mulai = ($bulan_ini >= 7) ? (int)date('Y') : (int)date('Y') - 1;
    $tahunajaran_berjalan = $tahun_mulai . '/' . ($tahun_mulai + 1);
}

// Tahun ajaran yang ditampilkan: ?tahunajaran=... kalau ada.
// Kalau tahun itu BUKAN tahun berjalan, ini tampilan RIWAYAT:
// daftar murid diambil dari snapshot riwayatkelas tahun itu,
// bukan dari anggota cohort yang aktif sekarang.
$tahunajaran_param = optional_param('tahunajaran', '', PARAM_TEXT);
if (!preg_match('/^\d{4}\/\d{4}$/', $tahunajaran_param)) {
    $tahunajaran_param = '';
}
$tahunajaran_tampil = $tahunajaran_param !== '' ? $tahunajaran_param : $tahunajaran_berjalan;
$is_riwayat = ($tahunajaran_tampil !== $tahunajaran_berjalan);

$mapping_walikelas = json_decode(get_config('local_jurnalmengajar', 'wali_kelas_mapping'), true);
if (!is_array($mapping_walikelas)) {
    $mapping_walikelas = [];
}

$id_walikelas = null;
if (isset($mapping_walikelas[$tahunajaran_tampil][$cohortid])) {
    $id_walikelas = (int)$mapping_walikelas[$tahunajaran_tampil][$cohortid];
} elseif (isset($mapping_walikelas[$cohortid])) {
    // Fallback format lama
    $id_walikelas = (int)$mapping_walikelas[$cohortid];
}

$nama_walikelas = 'Belum ditetapkan';
if ($id_walikelas) {
    $guru_walikelas = $DB->get_record('user', ['id' => $id_walikelas]);
    if ($guru_walikelas) {
        $nama_walikelas = !empty($guru_walikelas->lastname) ? trim($guru_walikelas->lastname) : $guru_walikelas->firstname;
    }
}

$PAGE->set_context($context);
$PAGE->set_url(
    new moodle_url(
        '/local/jurnalmengajar/murid_kelas.php',
        $is_riwayat
            ? ['cohortid' => $cohortid, 'tahunajaran' => $tahunajaran_tampil]
            : ['cohortid' => $cohortid]
    )
);

$PAGE->set_pagelayout('standard');
$PAGE->set_title('Daftar Murid Kelas ' . $cohort->name);
$PAGE->set_heading('DAFTAR MURID');

echo $OUTPUT->header();

// ======================================================
// Ambil Daftar Murid & Guru Wali
// ======================================================

// TAMBAHAN: Mengambil field guru.lastname sebagai namaguruwali
// dan melakukan LEFT JOIN ke tabel guruwali
// Sumber murid: cohort_members (kelas aktif sekarang) atau,
// kalau ini tampilan riwayat, snapshot riwayatkelas tahun itu.
$from_murid = $is_riwayat
    ? "FROM {local_jurnalmengajar_riwayatkelas} rk\n\nJOIN {user} u\n     ON u.id = rk.userid"
    : "FROM {cohort_members} cm\n\nJOIN {user} u\n     ON u.id = cm.userid";
$where_murid = $is_riwayat
    ? "WHERE rk.cohortid = ? AND rk.tahunajaran = ?"
    : "WHERE cm.cohortid = ?";
$params_murid = $is_riwayat ? [$cohortid, $tahunajaran_tampil] : [$cohortid];

$sql = "
SELECT
    u.id,
    u.firstname,
    u.lastname,
    nis.data AS nis,
    jk.data  AS jeniskelamin,
    guru.lastname AS namaguruwali

$from_murid

LEFT JOIN {user_info_data} nis
     ON nis.userid = u.id
    AND nis.fieldid = (
        SELECT id
        FROM {user_info_field}
        WHERE shortname = 'nis'
    )

LEFT JOIN {user_info_data} jk
     ON jk.userid = u.id
    AND jk.fieldid = (
        SELECT id
        FROM {user_info_field}
        WHERE shortname = 'gender'
    )

LEFT JOIN {local_jurnalmengajar_guruwali} gw
     ON gw.muridid = u.id

LEFT JOIN {user} guru
     ON guru.id = gw.guruid

$where_murid

ORDER BY u.lastname ASC
";

$murid = $DB->get_records_sql(
    $sql,
    $params_murid
);

// ======================================================
// TAMPILAN
// ======================================================

echo html_writer::start_div('container-fluid');

// ------------------------------------------------------
// Card Informasi
// ------------------------------------------------------

echo html_writer::start_div('card mb-3 shadow-sm');

// Bagian biru: nama kelasnya. Judul halaman di atas cukup
// "DAFTAR MURID", jadi tidak dobel lagi.
// Kalau ini tampilan riwayat, tahun ajarannya ditulis di sini
// supaya jelas ini daftar murid tahun lalu, bukan yang sekarang.
echo html_writer::start_div('card-header bg-primary text-white');
echo html_writer::tag('h4', '🎓 Kelas ' . s($cohort->name) . ($is_riwayat ? ' — ' . s($tahunajaran_tampil) : ''), ['class' => 'mb-0']);
echo html_writer::end_div();

echo html_writer::start_div('card-body');

echo html_writer::start_div('row');

echo html_writer::start_div('col-md-8 text-nowrap');
echo '<strong>Wali Kelas :</strong> <strong>' . s($nama_walikelas) . '</strong>';
echo html_writer::end_div();

echo html_writer::start_div('col-md-4 text-end');
echo '<strong>Jumlah Murid :</strong> ' . count($murid);
echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::end_div();


// ------------------------------------------------------
// Tabel Murid
// ------------------------------------------------------

echo html_writer::start_div('card shadow-sm');

echo html_writer::start_div(
    'card-header bg-info text-white'
);

echo html_writer::tag(
    'h5',
    '📋 Data Murid',
    ['class' => 'mb-0']
);

echo html_writer::end_div();

echo html_writer::start_div('card-body');


if (empty($murid)) {

    echo html_writer::div(
        $is_riwayat
            ? 'Belum ada murid pada kelas ini untuk tahun ajaran ' . s($tahunajaran_tampil) . '.'
            : 'Belum ada murid pada kelas ini.',
        'alert alert-warning text-center'
    );

} else {

    $table = new html_table();

    $table->attributes['class'] =
        'table table-bordered table-striped table-hover align-middle';

    // TAMBAHAN: Tambah header 'Guru Wali'
    $table->head = [
        'No',
        'NIS',
        'Nama Murid',
        'L/P',
        'Guru Wali'
    ];

    $no = 1;

foreach ($murid as $m) {

    $namaguruwali = !empty($m->namaguruwali)
        ? s($m->namaguruwali)
        : '-';

    // URL menuju halaman Riwayat Individu.
    $riwayaturl = new moodle_url(
        '/local/jurnalmengajar/riwayat_individu.php',
        [
            'muridid' => $m->id
        ]
    );

    // Nama murid dibuat menjadi link.
    $namamurid = html_writer::link(
        $riwayaturl,
        ucwords(strtolower($m->lastname)),
        [
            'class' => 'fw-bold'
        ]
    );

    $table->data[] = [

        $no++,

        s($m->nis),

        $namamurid,

        s($m->jeniskelamin),

        $namaguruwali

    ];
}

    echo html_writer::start_div(
        'table-responsive'
    );

    echo html_writer::table($table);

    echo html_writer::end_div();

}

echo html_writer::end_div();

echo html_writer::end_div();

// ======================================================
// TOMBOL KEMBALI
// ======================================================

echo html_writer::start_div('mt-3');

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/wali_kelas.php'),
    '← Kembali ke Daftar Wali Kelas',
    [
        'class' => 'btn btn-secondary'
    ]
);

echo html_writer::end_div();

// ======================================================
// FOOTER
// ======================================================

echo $OUTPUT->footer();
