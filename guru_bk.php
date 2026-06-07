<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
require_capability('moodle/site:config', $context);

global $DB, $OUTPUT, $PAGE;

// ===== Ambil mapping lama
$json = get_config('local_jurnalmengajar', 'guru_bk_mapping');
$mapping = json_decode($json, true);
if (!is_array($mapping)) {
    $mapping = [];
}

// ===== AUTO CLEAN (hapus user yang sudah tidak ada)
$changed = false;

foreach ($mapping as $key => $userid) {
    if (!$DB->record_exists('user', ['id' => $userid])) {
        unset($mapping[$key]);
        $changed = true;
    }
}
if ($changed) {
    $mapping = array_values($mapping);

    set_config(
        'guru_bk_mapping',
        json_encode($mapping),
        'local_jurnalmengajar'
    );
}

// ===== PROSES POST & DELETE
$action = optional_param('action', '', PARAM_TEXT);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey() && $action === 'save') {
    $userid = required_param('userid', PARAM_INT);

    if (!$userid) {
        redirect(new moodle_url('/local/jurnalmengajar/guru_bk.php'), '⚠️ Data tidak valid', null, \core\output\notification::NOTIFY_ERROR);
    }

	if (!in_array($userid, $mapping, true)) {
	    $mapping[] = $userid;
	}

	set_config(
	    'guru_bk_mapping',
	    json_encode(array_values($mapping)),
	    'local_jurnalmengajar'
	);

	redirect(
	    new moodle_url('/local/jurnalmengajar/guru_bk.php'),
	    '✅ Mapping berhasil disimpan',
	    null,
	    \core\output\notification::NOTIFY_SUCCESS
	);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    confirm_sesskey() &&
    $action === 'delete') {

    $deleteid = required_param('deleteid', PARAM_INT);

    $mapping = array_values(
        array_diff($mapping, [$deleteid])
    );

    set_config(
        'guru_bk_mapping',
        json_encode($mapping),
        'local_jurnalmengajar'
    );

    redirect(
        new moodle_url('/local/jurnalmengajar/guru_bk.php'),
        '🗑️ Mapping berhasil dihapus',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ===== SET PAGE
$PAGE->set_url('/local/jurnalmengajar/guru_bk.php');
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Mapping Guru BK');
$PAGE->set_heading('Mapping Guru BK');

echo $OUTPUT->header();

// ===== Ambil User

$role = $DB->get_record('role', ['shortname' => 'gurujurnal']);

if (!$role) {
    throw new moodle_exception('Role gurujurnal tidak ditemukan');
}

$users = get_role_users($role->id, $context);

$user_options = [];
foreach ($users as $u) {

    $nama = !empty($u->lastname) ? $u->lastname : $u->firstname;
    $user_options[$u->id] = $nama;
}
asort($user_options);
// =====================================================================
// TAMPILAN UI DIMULAI DI SINI
// =====================================================================

echo html_writer::start_div('container-fluid mt-3');

// --- CARD FORM INPUT ---
echo html_writer::start_div('card mb-4 shadow-sm');
echo html_writer::start_div('card-header bg-primary text-white');
echo html_writer::tag('h4', '➕ Tambah Guru BK', ['class' => 'mb-0']);
echo html_writer::end_div(); // end card-header

echo html_writer::start_div('card-body');

echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'm-0']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);

echo html_writer::start_div('row align-items-end'); // Grid row

// Dropdown Guru BK
echo html_writer::start_div('col-md-5 mb-3');
echo html_writer::label('Guru BK', 'userid', ['class' => 'font-weight-bold']);
echo html_writer::select($user_options, 'userid', '', ['' => 'Pilih Guru BK...'], ['class' => 'form-control custom-select', 'id' => 'userid', 'required' => 'required']);
echo html_writer::end_div();

// Tombol Simpan
echo html_writer::start_div('col-md-2 mb-3');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => '💾 Simpan',
    'class' => 'btn btn-primary w-100'
]);
echo html_writer::end_div();

echo html_writer::end_div(); // end row
echo html_writer::end_tag('form');
echo html_writer::end_div(); // end card-body
echo html_writer::end_div(); // end card form


// --- CARD TABEL MAPPING ---
echo html_writer::start_div('card shadow-sm');
echo html_writer::start_div('card-header bg-info text-white');
echo html_writer::tag('h4', '📋 Data Mapping Guru BK', ['class' => 'mb-0']);
echo html_writer::end_div();

echo html_writer::start_div('card-body');

$table = new html_table();
// Tambahkan class Bootstrap agar tabel tampil modern dan responsif
$table->attributes['class'] = 'table table-bordered table-striped table-hover text-center align-middle';

$table->head = ['No', 'Nama Guru BK', 'Aksi'];

$no = 1;

foreach ($mapping as $userid) {

    $user = $DB->get_record('user', ['id' => $userid]);

    if (!$user) {
        continue;
    }

    $nama = !empty($user->lastname)
        ? $user->lastname
        : $user->firstname;

    $delete_btn =
        html_writer::start_tag('form', [
            'method' => 'post',
            'class' => 'm-0',
            'onsubmit' => "return confirm('Yakin ingin menghapus Guru BK ini?');"
        ]) .
        html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey()
        ]) .
        html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'action',
            'value' => 'delete'
        ]) .
        html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'deleteid',
            'value' => $userid
        ]) .
        html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => '❌ Hapus',
            'class' => 'btn btn-sm btn-outline-danger'
        ]) .
        html_writer::end_tag('form');

    $table->data[] = [
        $no++,
        $nama,
        $delete_btn
    ];
}


if (empty($mapping)) {
    echo html_writer::tag('div', 'Belum ada data mapping Guru BK.', ['class' => 'alert alert-info text-center']);
} else {
    echo html_writer::start_div('table-responsive');
    echo html_writer::table($table);
    echo html_writer::end_div();
}

echo html_writer::end_div(); // end card-body
echo html_writer::end_div(); // end card table

echo html_writer::end_div(); // end container-fluid

echo $OUTPUT->footer();
