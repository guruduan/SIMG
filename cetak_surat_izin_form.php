<?php
// /local/jurnalmengajar/cetak_surat_izin_form.php
require_once('../../config.php');

require_login();
$context = context_system::instance();
// Atur capability sesuai kebijakan:
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_url(new moodle_url('/local/jurnalmengajar/cetak_surat_izin_form.php'));
$PAGE->set_context($context);
$PAGE->set_title('Cetak Banyak Surat Izin');
$PAGE->set_heading('Cetak Banyak Surat Izin');

$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'print' && confirm_sesskey()) {
    $raw = optional_param('ids', '', PARAM_RAW_TRIMMED);

    // Validasi simple: hanya angka, koma, strip, spasi
    if ($raw === '' || !preg_match('/^[0-9,\-\s]+$/', $raw)) {
        \core\notification::error('Masukan tidak valid. Gunakan angka, koma, atau tanda minus. Contoh: 1526-1528 atau 1526,1528');
    } else {
        // Normalisasi ringan: rapikan spasi di sekitar koma dan strip
        $norm = preg_replace('/\s*-\s*/', '-', $raw);
        $norm = preg_replace('/\s*,\s*/', ',', $norm);
        $norm = preg_replace('/\s+/', ',', trim($norm)); // spasi jadi koma

        // Redirect ke pencetak massal
        $target = new moodle_url('/local/jurnalmengajar/cetak_surat_izin_banyak.php', ['ids' => $norm]);
        redirect($target); // Akan menampilkan PDF
        exit;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading('Cetak Surat Izin sesuai ID', 2, 'mb-4');

// Form dibungkus Card agar lebih terfokus dan rapi
echo html_writer::start_div('card shadow-sm mb-4');
echo html_writer::div('🖨️ Cetak Massal Surat Izin', 'card-header bg-light font-weight-bold');
echo html_writer::start_div('card-body');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/jurnalmengajar/cetak_surat_izin_form.php')
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'print']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

// Form Group dengan Bootstrap
echo html_writer::start_div('form-group mb-3');
echo html_writer::tag('label', 'Input ID Surat Izin Murid <span class="text-danger">*</span>', [
    'for' => 'ids', 
    'class' => 'font-weight-bold'
]);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'ids',
    'id' => 'ids',
    'class' => 'form-control form-control-lg', // Form-control-lg membuat baris input lebih besar dan mudah diklik
    'placeholder' => 'Contoh: 1526-1528 atau 1526,1528',
    'required' => 'required'
]);
echo html_writer::end_div();

// Mengubah teks format biasa menjadi alert box info yang menarik perhatian
echo html_writer::start_div('alert alert-info py-2 px-3 mb-4 small shadow-sm d-flex align-items-center gap-2');
echo '<i class="fa fa-info-circle fa-lg text-info"></i> ';
echo '<div><strong>Format didukung:</strong> Menggunakan rentang tanda hubung (mis. <code>1526-1528</code>) dan/atau dipisahkan daftar koma (mis. <code>1526,1528</code>).</div>';
echo html_writer::end_div();

// Tombol Submit Aksi Utama
echo html_writer::tag('button', '<i class="fa fa-print"></i> Mulai Proses Cetak (PDF)', [
    'type' => 'submit', 
    'class' => 'btn btn-primary btn-lg px-4 shadow-sm'
]);

echo html_writer::end_tag('form');
echo html_writer::end_div(); // close card-body
echo html_writer::end_div(); // close card


// ================= BANNER BAWAH: UTILLITAS CEK ID =================
// Menggunakan komponen alert-warning atau light card untuk jembatan menu ke halaman izin_id.php
echo html_writer::start_div('card bg-light border shadow-sm mt-5 mb-4');
echo html_writer::start_div('card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 py-3');

echo html_writer::start_div('d-flex align-items-center gap-3');
echo '<div class="p-2 bg-white rounded shadow-sm text-warning"><i class="fa fa-search-plus fa-2x"></i></div>';
echo html_writer::start_div();
echo html_writer::tag('h5', 'Lupa atau Belum Tahu ID Surat Izin?', ['class' => 'm-0 font-weight-bold text-dark']);
echo html_writer::tag('small', 'Silakan periksa daftar nomor ID log surat izin murid yang telah diterbitkan sebelumnya.', ['class' => 'text-muted']);
echo html_writer::end_div();
echo html_writer::end_div();

// Penyiapan URL tujuan
$cekidurl = new moodle_url('/local/jurnalmengajar/izin_id.php');

// PERBAIKAN DI SINI: Pastikan argumen pertama adalah Teks Judul Tombol, bukan variabel URL
echo html_writer::link(
    $cekidurl, 
    '<i class="fa fa-list-ol"></i> Lihat Log ID Surat Izin', 
    ['class' => 'btn btn-outline-secondary font-weight-bold px-4']
);

echo html_writer::end_div();
echo html_writer::end_div();


echo $OUTPUT->footer();
