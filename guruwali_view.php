<?php
require('../../config.php');
require_once(__DIR__.'/lib.php');
require_once(__DIR__.'/jadwal_acuan_lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/guruwali_view.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Data Murid Binaan Guru Wali');
$PAGE->set_heading('Data Murid Binaan Guru Wali');

echo $OUTPUT->header();

global $USER;

// ============================
// Load binaan.csv
// ============================
$binaanfile = $CFG->dataroot . '/binaan.csv';
$data = [];

if (file_exists($binaanfile)) {
    if (($handle = fopen($binaanfile, 'r')) !== false) {
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== false) {
            // Cek jumlah kolom untuk mencegah error 'undefined offset' pada baris kosong
            if (count($row) >= 5) {
                $data[] = [
                    'userid'   => $row[0],
                    'lastname' => $row[1],
                    'nis'      => $row[2],
                    'murid'    => $row[3],
                    'kelas'    => $row[4]
                ];
            }
        }
        fclose($handle);
    }
}

// ============================
// List guru wali
// ============================
$listguru = [];
foreach ($data as $d) {
    $listguru[$d['userid']] = $d['lastname'];
}
// Hapus duplikat dan urutkan abjad
$listguru = array_unique($listguru);
asort($listguru);

// Default = guru login
$filterguru = optional_param('guru', $USER->id, PARAM_INT);

// ============================
// Filter data
// ============================
$filtered = [];
foreach ($data as $d) {
    if ($d['userid'] == $filterguru) {
        $filtered[] = $d;
    }
}

// ============================
// UI: Tampilan Card Bootstrap
// ============================
echo html_writer::start_div('card mb-3');
echo html_writer::start_div('card-body');

// ============================
// Filter dropdown (Form)
// ============================
echo html_writer::start_tag('form', [
    'method' => 'get',
    'class'  => 'mb-4'
]);

echo html_writer::start_div('d-flex align-items-center flex-wrap gap-2');

echo html_writer::tag('strong', 'Filter Guru Wali: ', ['class' => 'mr-2']);

echo html_writer::select(
    $listguru,
    'guru',
    $filterguru,
    false,
    [
        'class'    => 'custom-select form-select w-auto',
        'onchange' => 'this.form.submit();'
    ]
);

echo html_writer::end_div();

echo html_writer::end_tag('form');

// ============================
// Tabel binaan
// ============================
if (!empty($filtered)) {
    // Gunakan html_table bawaan Moodle
    $table = new html_table();
    $table->head = ['No', 'NIS', 'Nama Murid', 'Kelas', 'Guru Wali'];
    // Tambahkan class tabel bawaan Bootstrap agar ada efek garis & hover
    $table->attributes['class'] = 'generaltable table table-striped table-hover table-bordered mt-3';

    $no = 1;
    foreach ($filtered as $d) {
        $row = new html_table_row([
            $no,
            $d['nis'],
            $d['murid'],
            $d['kelas'],
            $d['lastname']
        ]);
        $table->data[] = $row;
        $no++;
    }
    
    // Render tabel
    echo html_writer::table($table);
} else {
    // Tampilkan notifikasi biru (Alert) jika tidak ada murid yang ditemukan
    echo html_writer::start_div('alert alert-info mt-3', ['role' => 'alert']);
    echo "Tidak ada data murid binaan untuk guru yang dipilih.";
    echo html_writer::end_div();
}

echo html_writer::end_div(); // End card-body
echo html_writer::end_div(); // End card

echo $OUTPUT->footer();
