<?php
require('../../config.php');
require_once(__DIR__.'/lib.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/kegiatan_add.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Jam Tidak Belajar');
$PAGE->set_heading('Catat Jam Tidak Belajar');

global $DB, $USER, $OUTPUT;

$listkelas = [];
$cohorts = $DB->get_records('cohort', null, 'name ASC');
foreach ($cohorts as $c) {
    $listkelas[$c->id] = $c->name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    // Diubah menjadi timestamp
    $tanggal = strtotime(required_param('tanggal', PARAM_TEXT)); 
    $keterangan = required_param('keterangan', PARAM_TEXT);
    
    // Membersihkan input jam agar rapi dari spasi berlebih
    $jamke_raw = required_param('jamke', PARAM_TEXT);
    $jam_array = array_filter(array_map('trim', explode(',', $jamke_raw)));
    $jamke_bersih = implode(', ', $jam_array);
    
    $kelasarray = optional_param_array('kelas', [], PARAM_INT);

    if (empty($kelasarray)) {
        redirect(
            new moodle_url('/local/jurnalmengajar/kegiatan_add.php'),
            'Gagal menyimpan: Silakan centang minimal satu kelas!',
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $waktu_sekarang = time();

    // Looping menyimpan data kelas
    foreach ($kelasarray as $kelasid) {
        $record = new stdClass();
        $record->tanggal = $tanggal;
        $record->kelas = $kelasid;
        $record->jamke = $jamke_bersih;
        $record->keterangan = $keterangan;
        $record->userid = $USER->id;
        $record->timecreated = $waktu_sekarang;
        $record->timemodified = $waktu_sekarang;

        $DB->insert_record('local_jurnalmengajar_kegiatan', $record);
    }

    redirect(
        new moodle_url('/local/jurnalmengajar/kegiatan_manage.php'),
        'Data Jam Tidak Belajar berhasil disimpan.',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

echo html_writer::start_tag('form', ['method'=>'post']);
echo html_writer::empty_tag('input', ['type'=>'hidden', 'name'=>'sesskey', 'value'=>sesskey()]);

// 1. INPUT TANGGAL
echo html_writer::label('Tanggal', 'tanggal');
echo html_writer::empty_tag('input', [
    'type'=>'date',
    'name'=>'tanggal',
    'value'=>date('Y-m-d'), 
    'required'=>'required',
    'class'=>'form-control mb-3'
]);

// 2. INPUT KELAS (CHECKBOX)
echo html_writer::label('Kelas', 'kelas');
echo '<div class="card mb-3"><div class="card-body">';

echo html_writer::checkbox('checkall', '1', false, '<strong>Pilih Semua Kelas</strong>', ['id' => 'checkall']);
echo '<hr style="margin: 10px 0;">';

echo '<div class="row">';
foreach ($listkelas as $id => $name) {
    echo '<div class="col-md-4 col-sm-6 mb-2">';
    echo html_writer::checkbox('kelas[]', $id, false, $name, ['class' => 'kelas-checkbox']);
    echo '</div>';
}
echo '</div>'; 
echo '</div></div>'; 

// 3. INPUT JAM KE
echo html_writer::label('Jam Pelajaran (contoh: 1,2,3 atau 5,6)', 'jamke');
echo html_writer::empty_tag('input', [
    'type'=>'text',
    'name'=>'jamke',
    'placeholder'=>'1,2,3',
    'required'=>'required',
    'class'=>'form-control mb-3'
]);

// 4. INPUT KETERANGAN
echo html_writer::label('Keterangan Kegiatan', 'keterangan');
echo html_writer::tag(
    'textarea',
    '',
    [
        'name'=>'keterangan',
        'rows'=>4,
        'class'=>'form-control mb-3',
        'placeholder'=>'Contoh: Rapat guru, persiapan lomba, dll.'
    ]
);

// TOMBOL SUBMIT & BATAL
echo html_writer::empty_tag('input', [
    'type'=>'submit',
    'value'=>'Simpan',
    'class'=>'btn btn-primary'
]);
echo ' ';
echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/kegiatan_manage.php'),
    'Batal',
    ['class'=>'btn btn-secondary']
);

echo html_writer::end_tag('form');

?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var checkAllBtn = document.getElementById('checkall');
    var checkboxes = document.querySelectorAll('.kelas-checkbox');

    if(checkAllBtn) {
        checkAllBtn.addEventListener('change', function() {
            var isChecked = this.checked;
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = isChecked;
            });
        });
    }

    checkboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            var allChecked = document.querySelectorAll('.kelas-checkbox:checked').length === checkboxes.length;
            checkAllBtn.checked = allChecked;
        });
    });
});
</script>
<?php
echo $OUTPUT->footer();
