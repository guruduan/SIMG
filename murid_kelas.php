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

$PAGE->set_context($context);
$PAGE->set_url(
    new moodle_url(
        '/local/jurnalmengajar/murid_kelas.php',
        ['cohortid' => $cohortid]
    )
);

$PAGE->set_pagelayout('standard');
$PAGE->set_title('Daftar Murid Kelas ' . $cohort->name);
$PAGE->set_heading('Daftar Murid Kelas ' . $cohort->name);

echo $OUTPUT->header();

// ======================================================
// Ambil Daftar Murid & Guru Wali
// ======================================================

// TAMBAHAN: Mengambil field guru.lastname sebagai namaguruwali 
// dan melakukan LEFT JOIN ke tabel guruwali
$sql = "
SELECT
    u.id,
    u.firstname,
    u.lastname,
    nis.data AS nis,
    jk.data  AS jeniskelamin,
    guru.lastname AS namaguruwali

FROM {cohort_members} cm

JOIN {user} u
     ON u.id = cm.userid

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

WHERE cm.cohortid = ?

ORDER BY u.lastname ASC
";

$murid = $DB->get_records_sql(
    $sql,
    [$cohortid]
);

// ======================================================
// TAMPILAN
// ======================================================

echo html_writer::start_div('container-fluid');

// ------------------------------------------------------
// Card Informasi
// ------------------------------------------------------

echo html_writer::start_div('card mb-3 shadow-sm');

echo html_writer::start_div(
    'card-header bg-primary text-white'
);

echo html_writer::tag(
    'h4',
    '👨‍🎓 Daftar Murid Kelas ' . s($cohort->name),
    ['class' => 'mb-0']
);

echo html_writer::end_div();

echo html_writer::start_div('card-body');

echo html_writer::start_div('row');

echo html_writer::start_div('col-md-6');
echo '<strong>Kelas :</strong> ' . s($cohort->name);
echo html_writer::end_div();

echo html_writer::start_div('col-md-6 text-end');
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
        'Belum ada murid pada kelas ini.',
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

        // TAMBAHAN: Validasi jika tidak ada guru wali yang di set
        //$namaguruwali = !empty($m->namaguruwali) ? format_nama_siswa($m->namaguruwali) : '-';
          $namaguruwali = !empty($m->namaguruwali) ? s($m->namaguruwali) : '-';

        $table->data[] = [

            $no++,

            s($m->nis),

            ucwords(strtolower($m->lastname)),

            s($m->jeniskelamin),

            $namaguruwali // TAMBAHAN: Memasukkan variabel ke baris tabel

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
