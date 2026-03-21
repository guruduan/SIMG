<?php
require('../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/import_acuan.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Import Jadwal Acuan');
$PAGE->set_heading('Import Jadwal Acuan');

echo $OUTPUT->header();
echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/download_format_acuan.php'),
    'Download Format CSV',
    ['class' => 'btn btn-secondary', 'style' => 'margin-bottom:10px;']
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES['csvfile']['tmp_name'])) {
        $dest = $CFG->dataroot . '/acuan.csv';

        if (move_uploaded_file($_FILES['csvfile']['tmp_name'], $dest)) {
            echo $OUTPUT->notification('File acuan.csv berhasil diupload', 'notifysuccess');
        } else {
            echo $OUTPUT->notification('Upload gagal', 'notifyproblem');
        }
    }
}

echo "<h3>Upload File Jadwal (acuan.csv)</h3>";

echo "<form method='post' enctype='multipart/form-data'>";
echo "<input type='file' name='csvfile' accept='.csv' required>";
echo "<br><br>";
echo "<input type='submit' value='Upload CSV' class='btn btn-primary'>";
echo "</form>";

echo "<br>";
echo "<b>Format CSV:</b>";
echo "<pre>
hari,userid,lastname,kelas,jamke
Senin,1172,\"Ahmad Hafie, S.Pd\",XI-E,\"7,8,9\"
Selasa,1172,\"Ahmad Hafie, S.Pd\",XI-G,\"10,11\"
Rabu,1172,\"Ahmad Hafie, S.Pd\",XB,\"6,7,8\"
</pre>";

echo $OUTPUT->footer();
