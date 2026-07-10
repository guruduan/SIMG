<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

global $DB, $PAGE, $OUTPUT;

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/wali_kelas.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Daftar Wali Kelas');
$PAGE->set_heading('Daftar Wali Kelas');

echo $OUTPUT->header();

// ======================================================
// Ambil Mapping Wali Kelas
// ======================================================

$json = get_config(
    'local_jurnalmengajar',
    'wali_kelas_mapping'
);

$mapping = json_decode($json, true);

if (!is_array($mapping)) {
    $mapping = [];
}

// ======================================================
// Susun Data
// ======================================================

$data = [];

foreach ($mapping as $cohortid => $userid) {

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
        'Belum ada data wali kelas.',
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
        'Aksi'
    ];

    $no = 1;

    foreach ($data as $row) {

        // ------------------------------------------------
        // Tombol (sementara)
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
        'class' => 'btn btn-sm btn-outline-success'
    ]
);

$aksi = $jadwal . ' ' . $murid;

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
