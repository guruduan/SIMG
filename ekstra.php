<?php
require_once(__DIR__ . '/../../config.php');
require_login();
$context = context_system::instance();

require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/ekstra.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Data Ekstrakurikuler');
$PAGE->set_heading('Data Ekstrakurikuler');

global $DB;

// =======================
// HANDLE EDIT MODE
// =======================
$editid = optional_param('edit', 0, PARAM_INT);
$editdata = null;

if ($editid) {
    $editdata = $DB->get_record('local_jm_ekstra', ['id' => $editid]);
}

// =======================
// SIMPAN DATA (INSERT / UPDATE)
// =======================
if (isset($_POST['namaekstra'])) {
    require_sesskey();

    $data = new stdClass();
    $data->namaekstra = required_param('namaekstra', PARAM_TEXT);

    $id = optional_param('id', 0, PARAM_INT);

    if ($id) {
        // UPDATE
        $data->id = $id;
        $DB->update_record('local_jm_ekstra', $data);
    } else {
        // INSERT
        $DB->insert_record('local_jm_ekstra', $data);
    }

    redirect(new moodle_url('/local/jurnalmengajar/ekstra.php'));
}

// =======================
// TAMPILKAN HALAMAN
// =======================
echo $OUTPUT->header();

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/pembina_ekstra.php'),
    'Kelola Pembina Ekstra',
    ['class' => 'btn btn-primary', 'style' => 'margin-bottom:10px; display:inline-block;']
);

// =======================
// FORM INPUT / EDIT
// =======================
echo '<h3>'.($editdata ? 'Edit Ekstrakurikuler' : 'Tambah Ekstrakurikuler').'</h3>';

echo '<form method="post">';
echo '<input type="hidden" name="sesskey" value="'.sesskey().'">';

if ($editdata) {
    echo '<input type="hidden" name="id" value="'.$editdata->id.'">';
}

$nama = $editdata ? $editdata->namaekstra : '';

echo '<input type="text" name="namaekstra" value="'.$nama.'" required>';
echo '<button type="submit">'.($editdata ? 'Update' : 'Simpan').'</button>';

if ($editdata) {
    echo ' <a href="ekstra.php">Batal</a>';
}

echo '</form>';

// =======================
// TABEL DATA
// =======================
$data = $DB->get_records('local_jm_ekstra');

echo '<h3>Daftar Ekstrakurikuler</h3>';
echo '<table border="1" cellpadding="5">';
echo '<tr><th>No</th><th>Nama Ekstra</th><th>Aksi</th></tr>';

$no = 1;
foreach ($data as $d) {
    $editurl = new moodle_url('/local/jurnalmengajar/ekstra.php', ['edit' => $d->id]);

    echo '<tr>';
    echo '<td>'.$no++.'</td>';
    echo '<td>'.$d->namaekstra.'</td>';
    echo '<td><a href="'.$editurl.'">Edit</a></td>';
    echo '</tr>';
}

echo '</table>';

echo $OUTPUT->footer();
