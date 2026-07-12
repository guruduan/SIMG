<?php
require('../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

global $DB, $PAGE, $OUTPUT, $USER;

$PAGE->set_context($context);
$PAGE->set_url(
    new moodle_url('/local/jurnalmengajar/statuslulus.php')
);
$PAGE->set_title('Status Kelulusan');
$PAGE->set_heading('Status Kelulusan');

echo $OUTPUT->header();
echo $OUTPUT->heading('🎓 Status Kelulusan');

/*
=====================================================
SETTING
=====================================================
*/

$records = $DB->get_records_sql("
    SELECT DISTINCT tahunajaran
    FROM {local_jurnalmengajar_riwayatkelas}
    ORDER BY tahunajaran DESC
");

$tahunlist = [];

foreach ($records as $r) {
    $tahunlist[$r->tahunajaran] = $r->tahunajaran;
}

if (empty($tahunlist)) {

    echo $OUTPUT->notification(
        'Belum ada data riwayat kelas.',
        'notifyproblem'
    );

    echo $OUTPUT->footer();
    exit;
}

$tahunajaran = array_key_first($tahunlist);

$tanggaldefault = date('Y-m-d');

/*
=====================================================
PROSES
=====================================================
*/

if (optional_param('proses', 0, PARAM_BOOL)) {

    require_sesskey();

    $tahunajaran = required_param(
        'tahunajaran',
        PARAM_TEXT
    );

    $tanggaltext = required_param(
        'tanggal',
        PARAM_TEXT
    );

    $tanggal = strtotime($tanggaltext);

    if ($tanggal === false) {
        throw new moodle_exception(
            'Tanggal tidak valid.'
        );
    }

    $sql = "
        SELECT
            rk.userid,
            c.name AS kelas
        FROM
            {local_jurnalmengajar_riwayatkelas} rk
            JOIN {cohort} c
                ON c.id = rk.cohortid
        WHERE
            rk.tahunajaran = ?
            AND c.name LIKE 'XII-%'
        ORDER BY
            c.name
    ";

    $records = $DB->get_records_sql(
        $sql,
        [$tahunajaran]
    );

    $jumlah = 0;
    $lewati = 0;
    $now = time();

    $transaction = $DB->start_delegated_transaction();

    foreach ($records as $r) {

        $ada = $DB->record_exists(
            'local_jurnalmengajar_riwayatakademik',
            [
                'userid'      => $r->userid,
                'tahunajaran' => $tahunajaran,
                'jenis'       => 'lulus'
            ]
        );

        if ($ada) {
            $lewati++;
            continue;
        }

        $data = new stdClass();
        $data->userid       = $r->userid;
        $data->tahunajaran  = $tahunajaran;
        $data->jenis        = 'lulus';
        $data->tanggal      = $tanggal;
        $data->keterangan   = '';
        $data->useridinput  = $USER->id;
        $data->timecreated  = $now;
        $data->timemodified = $now;

        $DB->insert_record(
            'local_jurnalmengajar_riwayatakademik',
            $data
        );

        $jumlah++;
    }

    $transaction->allow_commit();

    echo $OUTPUT->notification(
        $jumlah .
        ' siswa berhasil diberi status Lulus.<br>' .
        $lewati .
        ' siswa dilewati (sudah lulus).',
        'notifysuccess'
    );
}

/*
=====================================================
FORM
=====================================================
*/

echo html_writer::start_div('card shadow-sm');

echo html_writer::start_div(
    'card-header bg-success text-white'
);

echo html_writer::tag(
    'strong',
    'Proses Kelulusan Massal'
);

echo html_writer::end_div();

echo html_writer::start_div('card-body');

echo html_writer::start_tag('form', [
    'method' => 'post'
]);

echo html_writer::empty_tag('input', [
    'type'  => 'hidden',
    'name'  => 'sesskey',
    'value' => sesskey()
]);

echo html_writer::empty_tag('input', [
    'type'  => 'hidden',
    'name'  => 'proses',
    'value' => 1
]);

$options = [];
foreach ($tahunlist as $tahun) {
    $options[$tahun] = $tahun;
}

echo html_writer::start_div('row');

echo html_writer::start_div('col-md-6');

echo html_writer::tag('label', 'Tahun Ajaran');

echo html_writer::select(
    $options,
    'tahunajaran',
    $tahunajaran,
    null,
    ['class' => 'form-control']
);

echo html_writer::end_div();

echo html_writer::start_div('col-md-6');

echo html_writer::tag('label', 'Tanggal Kelulusan');

echo html_writer::empty_tag('input', [
    'type'  => 'date',
    'name'  => 'tanggal',
    'value' => $tanggaldefault,
    'class' => 'form-control'
]);

echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::empty_tag('br');

echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => '🎓 Proses Kelulusan',
    'class' => 'btn btn-success',
    'onclick' =>
        "return confirm('Proses kelulusan seluruh siswa kelas XII?')"
]);

echo html_writer::end_tag('form');

echo html_writer::end_div();

echo html_writer::end_div();

echo $OUTPUT->footer();
