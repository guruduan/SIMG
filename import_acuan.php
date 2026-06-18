<?php
require('../../config.php');
//require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

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
    [
        'class' => 'btn btn-secondary',
        'style' => 'margin-bottom:10px;'
    ]
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csvfile']['tmp_name'])) {

    global $DB;

    $file = $_FILES['csvfile']['tmp_name'];

    if (($handle = fopen($file, 'r')) !== false) {

        // Hapus seluruh jadwal lama
        $DB->delete_records('local_jurnalmengajar_jadwal');

        // Lewati header
        fgetcsv($handle, 1000, ',');

        while (($data = fgetcsv($handle, 1000, ',')) !== false) {

            if (count($data) < 5) {
                continue;
            }

            // Bersihkan BOM UTF-8 jika ada
            $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0]);

            // Trim semua kolom
            $data = array_map('trim', $data);

            $hari    = $data[0];
            $userid  = $data[1];
            $kelas   = $data[3];
            $jamlist = $data[4];

            // Lewati baris yang tidak lengkap
            if (
                $hari === '' ||
                $userid === '' ||
                $kelas === '' ||
                $jamlist === ''
            ) {
                continue;
            }

            $jamarray = explode(',', $jamlist);

            foreach ($jamarray as $jam) {

                $jam = (int) trim($jam);

                if ($jam <= 0) {
                    continue;
                }

                $record = new stdClass();
                $record->userid      = (int)$userid;
                $record->hari        = $hari;
                $record->kelas       = $kelas;
                $record->jamke       = $jam;
                $record->timecreated = time();

                $DB->insert_record(
                    'local_jurnalmengajar_jadwal',
                    $record
                );
            }
        }

        fclose($handle);

        echo $OUTPUT->notification(
            'Import jadwal berhasil',
            'notifysuccess'
        );
    }
}

echo '<h3>Upload File Jadwal (acuan.csv)</h3>';

echo '<form method="post" enctype="multipart/form-data">';
echo '<input type="file" name="csvfile" accept=".csv" required>';
echo '<br><br>';
echo '<input type="submit" value="Upload CSV" class="btn btn-primary">';
echo '</form>';

echo '<br>';
echo '<b>Format CSV:</b>';

echo '<pre>
hari,userid,lastname,kelas,jamke
Senin,11,"Ahmad Budi, S.Pd",XI-E,"7,8,9"
Selasa,11,"Ahmad Budi, S.Pd",XI-G,"10,11"
Rabu,11,"Ahmad Budi, S.Pd",XB,"6,7,8"

*kelas sesuai nama kelas
</pre>';

echo $OUTPUT->footer();
