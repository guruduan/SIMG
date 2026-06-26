<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');
require_once($CFG->dirroot . '/local/jurnalmengajar/lib_notifikasi.php');

require_login();

use local_jurnalmengajar\form\layananbk_form;

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/layananbk.php'));
$PAGE->set_title('Layanan BK');
$PAGE->set_heading('Layanan BK');

$PAGE->requires->jquery();

// ================= JS =================
$PAGE->requires->js_init_code(<<<JS
$(document).ready(function() {
    function updatePesertaField() {
        let hasil = [];
        let hasilid = [];

        $(".siswa-checkbox:checked").each(function() {
            hasil.push($(this).data("nama"));
            hasilid.push(parseInt($(this).data("userid")));
        });

        $("#id_peserta").val(JSON.stringify(hasil));
        $("#id_pesertaid").val(JSON.stringify(hasilid));
    }

    function loadSiswa(kelasid) {
        if (!kelasid) {
            return;
        }

        $.get("/local/jurnalmengajar/get_students_bk.php", {kelas: kelasid}, function(html) {
            $("#siswa-area").html(html);
            $(".siswa-checkbox").on("change", updatePesertaField);
        });
    }

    const awalKelas = $("select[name='kelas']").val();
    if (awalKelas) {
        loadSiswa(awalKelas);
    }

    $("select[name='kelas']").on("change", function() {
        loadSiswa($(this).val());
    });
});
JS
);

// ================= FORM =================
$mform = new layananbk_form();

// ================= PROSES =================
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/jurnalmengajar/layananbk.php'));

} else if ($data = $mform->get_data()) {

    global $DB, $USER;

    $record = new stdClass();
    $record->userid        = $USER->id;
    $record->kelas         = (int)$data->kelas;
    $record->jenislayanan  = $data->jenislayanan;
    $record->topik         = $data->topik;
    $record->peserta       = $data->peserta ?? '[]';
    $record->pesertaid     = $data->pesertaid ?? '[]';
    $record->tindaklanjut  = $data->tindaklanjut ?: '-';
    $record->catatan       = $data->catatan ?: '-';
    $record->timecreated   = time();

    $DB->insert_record('local_jurnallayananbk', $record);

    // ================= WA =================
$guru = $DB->get_record('user', ['id' => $record->userid], 'lastname');
$kelasnama = get_nama_kelas($record->kelas);
$nama = $guru ? $guru->lastname : '-';

$waktu = tanggal_indo($record->timecreated);

$peserta = json_decode($record->peserta, true);
$peserta_str = is_array($peserta) && !empty($peserta)
    ? implode(', ', $peserta)
    : '-';

$datawa = [
    '{waktu}'         => $waktu,
    '{murid}'         => $peserta_str,
    '{kelas}'         => $kelasnama,
    '{jenislayanan}'  => $record->jenislayanan,
    '{topik}'         => $record->topik,
    '{tindaklanjut}'  => $record->tindaklanjut,
    '{catatan}'       => $record->catatan,
    '{gurubk}'        => $nama,

    // dipakai resolver tujuan
    'kelas'           => $record->kelas
];

jm_kirim_template_auto(
    'layanan_bk',
    $datawa
);

    redirect(new moodle_url('/local/jurnalmengajar/layananbk.php'), 'Data berhasil disimpan', null, \core\output\notification::NOTIFY_SUCCESS);
}

// ================= TAMPILAN =================
echo $OUTPUT->header();

// Menampilkan form input (Moodle QuickForm)
$mform->display();

// ================= EXPORT SECTION (Dipercantik dengan Card) =================
$currentmonth = date('m');
$currentyear  = date('Y');
$yearoptions  = array_combine(range(2025, 2030), range(2025, 2030));

echo html_writer::start_tag('div', ['class' => 'card mb-4 mt-4']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h5', 'Ekspor Data Layanan BK', ['class' => 'card-title mb-3']);

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => 'export_layananbk.php',
    'class'  => 'd-flex flex-wrap align-items-center' // Menggunakan flexbox agar sejajar
]);

echo html_writer::label('Bulan:', 'bulan', false, ['class' => 'mr-2 mb-0']);
// Menambahkan div wrapper agar select Moodle lebih rapi
echo html_writer::start_tag('div', ['class' => 'mr-3']);
echo html_writer::select_time('months', 'bulan', $currentmonth);
echo html_writer::end_tag('div');

echo html_writer::label('Tahun:', 'tahun', false, ['class' => 'mr-2 mb-0']);
echo html_writer::select($yearoptions, 'tahun', $currentyear, false, ['class' => 'custom-select mr-3']);

echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => 'Ekspor ke Excel (XLSX)',
    'class' => 'btn btn-success' // Warna hijau untuk Excel
]);

echo html_writer::end_tag('form');
echo html_writer::end_tag('div'); // end card-body
echo html_writer::end_tag('div'); // end card


// ================= TABEL SECTION (Dipercantik) =================
echo html_writer::start_tag('div', ['class' => 'card']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h5', 'Riwayat Jurnal Layanan BK', ['class' => 'card-title mb-3']);

$records = $DB->get_records('local_jurnallayananbk', null, 'timecreated DESC');

if ($records) {
    $table = new html_table();
    // Menambahkan class bootstrap ke tabel
    $table->attributes['class'] = 'table table-striped table-hover table-bordered mt-3';
    $table->head = ['Waktu', 'Kelas', 'Jenis Layanan', 'Topik', 'Peserta', 'Guru BK'];

    foreach ($records as $r) {
        $namakelas = get_nama_kelas($r->kelas);
        
        $peserta = json_decode($r->peserta, true);
        $peserta_str = is_array($peserta) ? implode(', ', $peserta) : '-';
        
        $gurubk = $DB->get_field('user', 'lastname', ['id' => $r->userid]) ?? '-';
        $waktu = tanggal_indo($r->timecreated);

        $table->data[] = [
            $waktu,
            $namakelas,
            $r->jenislayanan,
            $r->topik,
            shorten_text($peserta_str, 50),
            $gurubk
        ];
    }

    // Dibungkus dengan table-responsive agar tabel bisa discroll ke kanan di HP
    echo html_writer::start_div('table-responsive');
    echo html_writer::table($table);
    echo html_writer::end_div();

} else {
    echo $OUTPUT->notification('Belum ada data Jurnal Layanan BK.', 'notifymessage info');
}

echo html_writer::end_tag('div'); // end card-body
echo html_writer::end_tag('div'); // end card

echo $OUTPUT->footer();
