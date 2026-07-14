<?php
require_once(__DIR__ . '/../../config.php');

require_login();

use local_jurnalmengajar\form\pembinaan_form;

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');
require_once($CFG->dirroot . '/local/jurnalmengajar/lib_notifikasi.php');

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/pembinaan.php'));
$PAGE->set_title('Laporan Pembinaan Siswa');
$PAGE->set_heading('Laporan Pembinaan Siswa oleh BK');
$PAGE->requires->jquery();

$PAGE->requires->js_init_code(<<<JS

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

function bindPesertaEvent() {
    $(".siswa-checkbox").on("change", updatePesertaField);
}

function loadSiswa(kelasid) {
    if (!kelasid) return;

    $.get(
        "/local/jurnalmengajar/get_students_bk.php",
        {kelas: kelasid},
        function(html) {
            $("#siswa-area").html(html);
            bindPesertaEvent();
        }
    );
}

$(document).ready(function() {
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
$mform = new pembinaan_form();

// ================= PROSES =================
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/jurnalmengajar/pembinaan.php'));

} else if ($data = $mform->get_data()) {

    global $DB, $USER;

    $record = new stdClass();
    $record->userid        = $USER->id;
    $record->kelas         = (int)$data->kelas; // ✅ pakai ID
    $record->peserta       = $data->peserta ?? '[]';
    $record->pesertaid     = $data->pesertaid ?? '[]';
    $record->permasalahan  = $data->permasalahan ?: '-';
    $record->tindakan      = $data->tindakan ?: '-';
    $record->tempat        = '-';
    $record->timecreated   = time();

    $DB->insert_record('local_jurnalpembinaan', $record);

// ================= WA =================
$guru = $DB->get_record('user', ['id' => $record->userid], 'lastname');
$kelasnama = get_nama_kelas($record->kelas);
$nama = $guru ? $guru->lastname : '-';

$waktu = tanggal_indo($record->timecreated);

$peserta = json_decode($record->peserta, true);

if (is_array($peserta) && !empty($peserta)) {
    $peserta = array_map('format_nama_siswa', $peserta);
    $peserta_str = implode(', ', $peserta);
} else {
    $peserta_str = '-';
}

$datawa = [
    '{waktu}'        => $waktu,
    '{murid}'        => $peserta_str,
    '{kelas}'        => $kelasnama,
    '{permasalahan}' => $record->permasalahan,
    '{upaya}'        => $record->tindakan,
    '{gurubk}'       => $nama,

    // dipakai resolver tujuan
    'kelas'          => $record->kelas,

    // daftar userid siswa yang dibina
    'pesertaid'      => $record->pesertaid
];

jm_kirim_template_auto(
    'pembinaan',
    $datawa
);

    redirect(new moodle_url('/local/jurnalmengajar/pembinaan.php'), 'Data berhasil disimpan', null, \core\output\notification::NOTIFY_SUCCESS);
}

// ================= TAMPILAN =================
echo $OUTPUT->header();

// Menampilkan form input laporan
$mform->display();

// Jarak / Pemisah antara Form dan Data Table
echo html_writer::tag('hr', '', ['class' => 'mt-5 mb-4']);
echo $OUTPUT->heading('Daftar Laporan Pembinaan', 3);

// ================= EXPORT BOX (Dipercantik dengan Card Moodle/Bootstrap) =================
$bulanlist = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
    '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];
$tahunlist = array_combine(range(2025, 2030), range(2025, 2030));

// Card Container
echo html_writer::start_div('card mb-4 bg-light');
echo html_writer::start_div('card-body p-3');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => 'export_pembinaan.php',
    'class'  => 'form-inline d-flex flex-wrap align-items-center'
]);

echo html_writer::tag('strong', '📥 Ekspor Data:', ['class' => 'mr-3 me-3 mb-2 mb-md-0']);

// Input Bulan
echo html_writer::label('Bulan', 'bulan', false, ['class' => 'mr-2 me-2 mb-0 sr-only']);
echo html_writer::select($bulanlist, 'bulan', date('m'), false, ['class' => 'custom-select form-control mr-3 me-3 mb-2 mb-md-0']);

// Input Tahun
echo html_writer::label('Tahun', 'tahun', false, ['class' => 'mr-2 me-2 mb-0 sr-only']);
echo html_writer::select($tahunlist, 'tahun', date('Y'), false, ['class' => 'custom-select form-control mr-3 me-3 mb-2 mb-md-0']);

// Tombol Submit
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => 'Unduh Excel (XLSX)',
    'class' => 'btn btn-success mb-2 mb-md-0'
]);

echo html_writer::end_tag('form');
echo html_writer::end_div(); // end card-body
echo html_writer::end_div(); // end card


// ================= PAGING =================
$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;
$offset = $page * $perpage;

// ================= TOTAL DATA =================
$total = $DB->count_records('local_jurnalpembinaan');

// ================= TABEL DATA =================
$records = $DB->get_records_sql("
    SELECT *
    FROM {local_jurnalpembinaan}
    ORDER BY timecreated DESC
    LIMIT $perpage OFFSET $offset
");

if ($records) {

    $table = new html_table();
    $table->head = ['No', 'Waktu', 'Nama Murid', 'Kelas', 'Permasalahan', 'Upaya', 'Guru BK'];
    
    // Tambahan kelas Bootstrap untuk mempercantik tabel
    $table->attributes['class'] = 'generaltable table table-striped table-hover table-bordered';

    $no = $offset + 1;

    foreach ($records as $r) {
        $namakelas = get_nama_kelas($r->kelas);
        $peserta = json_decode($r->peserta ?? '[]', true);

        if (is_array($peserta) && !empty($peserta)) {
            $peserta = array_map('format_nama_siswa', $peserta);
            $peserta_str = implode(', ', $peserta);
        } else {
            $peserta_str = '-';
        }

        $gurubk = $DB->get_field('user', 'lastname', ['id' => $r->userid]) ?? '-';
        $waktu = tanggal_indo($r->timecreated);

        $table->data[] = [
            $no++,
            $waktu,
            shorten_text($peserta_str, 50),
            $namakelas,
            format_string($r->permasalahan),
            format_string($r->tindakan),
            $gurubk
        ];
    }

    // Dibungkus dengan table-responsive agar tabel bisa digeser/scroll horizontal di layar HP
    echo html_writer::start_div('table-responsive');
    echo html_writer::table($table);
    echo html_writer::end_div();

} else {
    echo $OUTPUT->notification('Belum ada data Laporan Pembinaan.', 'info');
}

// ================= PAGING BAR =================
$baseurl = new moodle_url('/local/jurnalmengajar/pembinaan.php');
echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);

echo $OUTPUT->footer();
