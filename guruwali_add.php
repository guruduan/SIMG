<?php
require('../../config.php');
require_once(__DIR__.'/lib.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/guruwali_add.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Tambah Murid Binaan');
$PAGE->set_heading('Tambah Murid Binaan');

global $DB;

// ======================
// Ambil daftar guru
// ======================
$role = $DB->get_record('role', ['shortname' => 'gurujurnal']);

$sql = "SELECT u.id, u.lastname
        FROM {role_assignments} ra
        JOIN {user} u ON u.id = ra.userid
        WHERE ra.roleid = :roleid
        ORDER BY u.lastname";

$dataguru = $DB->get_records_sql($sql, ['roleid' => $role->id]);

$listguru = [];
foreach ($dataguru as $g) {
    $listguru[$g->id] = $g->lastname;
}

// ======================
// Ambil kelas
// ======================
$listkelas = jurnalmengajar_get_all_kelas();

// ======================
// Ambil parameter
// ======================
$kelas  = optional_param('kelas', '', PARAM_TEXT);
$userid = optional_param('userid', 0, PARAM_INT);

// ======================
// Ambil siswa dari kelas
// ======================
$listsiswa = [];
if ($kelas) {
    $siswa = jurnalmengajar_get_siswa_by_kelas($kelas);
    foreach ($siswa as $s) {
        $listsiswa[$s->id] = $s->lastname;
    }
}
// ======================
// Simpan ke Database
// ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_sesskey();

    $guruid  = required_param('userid', PARAM_INT);
    $muridid = required_param('muridid', PARAM_INT);

    $existing = $DB->get_record(
        'local_jurnalmengajar_guruwali',
        ['muridid' => $muridid]
    );

    $time = time();

    if ($existing) {

        // Update guru wali.
        $existing->guruid = $guruid;
        $existing->timemodified = $time;

        $DB->update_record(
            'local_jurnalmengajar_guruwali',
            $existing
        );

    } else {

        // Tambah relasi baru.
        $record = new stdClass();

        $record->guruid       = $guruid;
        $record->muridid      = $muridid;
        $record->timecreated  = $time;
        $record->timemodified = $time;

        $DB->insert_record(
            'local_jurnalmengajar_guruwali',
            $record
        );
    }

    redirect(
        new moodle_url('/local/jurnalmengajar/guruwali_manage.php'),
        'Data Guru Wali berhasil disimpan.',
        2
    );
}

echo $OUTPUT->header();

// ======================
// FORM PILIH KELAS
// ======================
echo html_writer::start_tag('form', ['method'=>'get']);

echo html_writer::label('Guru Wali', 'userid');
echo html_writer::select($listguru, 'userid', $userid, null, [
    'onchange' => 'this.form.submit()'
]);
echo "<br><br>";

echo html_writer::label('Kelas', 'kelas');
echo html_writer::select($listkelas, 'kelas', $kelas, null, [
    'onchange' => 'this.form.submit()'
]);


echo html_writer::end_tag('form');

echo "<br>";

// ======================
// FORM SIMPAN
// ======================
if ($kelas && $listsiswa && $userid) {

    echo html_writer::start_tag('form', ['method'=>'post']);

    echo html_writer::empty_tag('input', [
        'type'=>'hidden',
        'name'=>'sesskey',
        'value'=>sesskey()
    ]);

    echo html_writer::empty_tag('input', [
        'type'=>'hidden',
        'name'=>'userid',
        'value'=>$userid
    ]);

    echo html_writer::label('Murid', 'muridid');
    echo html_writer::select($listsiswa, 'muridid');
    echo "<br><br>";

    echo html_writer::empty_tag('input', [
        'type'=>'submit',
        'value'=>'Simpan',
        'class'=>'btn btn-success'
    ]);

    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
