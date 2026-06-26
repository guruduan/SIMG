<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

global $DB, $PAGE, $OUTPUT;

$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/jabatan.php');
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Mapping Jabatan');
$PAGE->set_heading('Mapping Jabatan');

// =========================
// PROSES SIMPAN
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {

    $wakakesis = optional_param(
        'wakasek_kesiswaan',
        0,
        PARAM_INT
    );

    $wakakur = optional_param(
        'wakasek_kurikulum',
        0,
        PARAM_INT
    );

    set_config(
        'wakasek_kesiswaan_userid',
        $wakakesis,
        'local_jurnalmengajar'
    );

    set_config(
        'wakasek_kurikulum_userid',
        $wakakur,
        'local_jurnalmengajar'
    );

    redirect(
        new moodle_url('/local/jurnalmengajar/jabatan.php'),
        '✅ Mapping jabatan berhasil disimpan.',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// =========================
// DATA
// =========================

$wakakesis = get_config(
    'local_jurnalmengajar',
    'wakasek_kesiswaan_userid'
);

$wakakur = get_config(
    'local_jurnalmengajar',
    'wakasek_kurikulum_userid'
);

// Ambil user guru jurnal
$role = $DB->get_record(
    'role',
    ['shortname' => 'gurujurnal']
);

$users = get_role_users(
    $role->id,
    $context
);

$useroptions = [];

foreach ($users as $u) {

    $nama = !empty($u->lastname)
        ? $u->lastname
        : fullname($u);

    $useroptions[$u->id] = $nama;
}

echo $OUTPUT->header();

echo html_writer::start_div('container-fluid mt-3');

// =========================
// CARD
// =========================

echo html_writer::start_div('card shadow-sm');

echo html_writer::start_div(
    'card-header bg-primary text-white'
);

echo html_writer::tag(
    'h4',
    '👥 Mapping Jabatan',
    ['class'=>'mb-0']
);

echo html_writer::end_div();

echo html_writer::start_div('card-body');

echo html_writer::start_tag('form',[
    'method'=>'post'
]);

echo html_writer::empty_tag('input',[
    'type'=>'hidden',
    'name'=>'sesskey',
    'value'=>sesskey()
]);

// Wakasek Kesiswaan

echo html_writer::start_div('mb-4');

echo html_writer::label(
    'Wakil Kepala Sekolah Bidang Kesiswaan',
    'wakasek_kesiswaan',
    ['class'=>'font-weight-bold']
);

echo html_writer::select(
    $useroptions,
    'wakasek_kesiswaan',
    $wakakesis,
    [''=>'Pilih Guru...'],
    [
        'class'=>'form-control custom-select',
        'id'=>'wakasek_kesiswaan'
    ]
);

echo html_writer::end_div();

// Wakasek Kurikulum

echo html_writer::start_div('mb-4');

echo html_writer::label(
    'Wakil Kepala Sekolah Bidang Kurikulum',
    'wakasek_kurikulum',
    ['class'=>'font-weight-bold']
);

echo html_writer::select(
    $useroptions,
    'wakasek_kurikulum',
    $wakakur,
    [''=>'Pilih Guru...'],
    [
        'class'=>'form-control custom-select',
        'id'=>'wakasek_kurikulum'
    ]
);

echo html_writer::end_div();

echo html_writer::tag(
    'button',
    '💾 Simpan Mapping',
    [
        'type'=>'submit',
        'class'=>'btn btn-primary'
    ]
);

echo html_writer::end_tag('form');

echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::end_div();

echo $OUTPUT->footer();
