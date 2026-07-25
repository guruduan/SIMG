<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/mapping_kristen.php'));
$PAGE->set_title('Mapping Murid Kristen');
$PAGE->set_heading('Mapping Murid Kristen');

echo $OUTPUT->header();
echo $OUTPUT->heading('Mapping Murid Kristen');

// ======================
// Ambil daftar kelas
// ======================

$kelasoptions = [];

$cohorts = $DB->get_records_sql("
    SELECT id, name
    FROM {cohort}
    ORDER BY name ASC
");

foreach ($cohorts as $c) {
    $kelasoptions[$c->id] = format_string($c->name);
}

$kelasid = optional_param('kelas', 0, PARAM_INT);

// ======================
// Form pilih kelas
// ======================

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/jurnalmengajar/mapping_kristen.php')
]);

echo html_writer::start_div('mb-3');

echo html_writer::tag(
    'label',
    'Kelas',
    [
        'for' => 'kelas'
    ]
);

echo html_writer::select(
    $kelasoptions,
    'kelas',
    $kelasid,
    ['' => '-- Pilih Kelas --'],
    [
        'id' => 'kelas',
        'class' => 'form-control',
        'onchange' => 'this.form.submit();'
    ]
);

echo html_writer::end_div();

echo html_writer::end_tag('form');

// ======================
// Belum memilih kelas
// ======================

if (empty($kelasid)) {

    echo $OUTPUT->footer();
    exit;
}

// ======================
// Ambil seluruh siswa
// ======================

$members = $DB->get_records_sql("
    SELECT
        u.id,
        u.firstname,
        u.lastname
    FROM {cohort_members} cm
    JOIN {user} u
        ON u.id = cm.userid
    WHERE cm.cohortid = ?
    ORDER BY u.lastname ASC
", [$kelasid]);

if (!$members) {

    echo $OUTPUT->notification(
        'Tidak ada murid pada kelas ini.',
        'notifyinfo'
    );

    echo $OUTPUT->footer();
    exit;
}

// ======================
// Ambil mapping Kristen
// ======================

$mapping = $DB->get_records(
    'local_jurnalmengajar_kristen'
);

$mappinguserid = [];

foreach ($mapping as $m) {
    $mappinguserid[$m->userid] = true;
}

// ======================
// Form simpan
// ======================

echo html_writer::start_tag('form', [
    'method' => 'post'
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'kelas',
    'value' => $kelasid
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'sesskey',
    'value' => sesskey()
]);

echo html_writer::start_div('card');

echo html_writer::start_div('card-body');

echo html_writer::tag(
    'h4',
    'Daftar Murid'
);

foreach ($members as $user) {

    $checked = isset($mappinguserid[$user->id]);

    echo html_writer::start_div(
        'form-check'
    );

    echo html_writer::checkbox(
        'userid[]',
        $user->id,
        $checked,
        format_string($user->lastname),
        [
            'class' => 'form-check-input'
        ]
    );

    echo html_writer::end_div();
}

echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

echo html_writer::empty_tag('br');

echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'name'  => 'simpan',
    'value' => 'Simpan',
    'class' => 'btn btn-primary'
]);

echo html_writer::end_tag('form');

// ======================
// PROSES SIMPAN
// ======================

if (optional_param('simpan', '', PARAM_TEXT)) {

    require_sesskey();

    $useriddipilih = optional_param_array(
        'userid',
        [],
        PARAM_INT
    );

    // Ambil seluruh murid pada kelas.
    $members = $DB->get_records_sql("
        SELECT u.id
        FROM {cohort_members} cm
        JOIN {user} u
            ON u.id = cm.userid
        WHERE cm.cohortid = ?
    ", [$kelasid]);

    $userkelas = [];

    foreach ($members as $m) {
        $userkelas[] = $m->id;
    }

    // Hapus mapping lama hanya untuk murid di kelas ini.
    if (!empty($userkelas)) {

        list($sqlin, $params) = $DB->get_in_or_equal($userkelas);

        $DB->delete_records_select(
            'local_jurnalmengajar_kristen',
            "userid $sqlin",
            $params
        );
    }

    // Simpan mapping baru.
    foreach ($useriddipilih as $userid) {

        $record = new stdClass();
        $record->userid       = $userid;
        $record->timecreated  = time();
        $record->timemodified = time();

        $DB->insert_record(
            'local_jurnalmengajar_kristen',
            $record
        );
    }

    redirect(
        new moodle_url(
            '/local/jurnalmengajar/mapping_kristen.php',
            ['kelas' => $kelasid]
        ),
        'Mapping murid Kristen berhasil disimpan.',
        2
    );
}

echo $OUTPUT->footer();

