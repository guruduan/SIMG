<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/exportguruwali_form.php'));
$PAGE->set_title('Ekspor Jurnal Guru Wali per Bulan');
$PAGE->set_heading('Ekspor Jurnal Guru Wali per Bulan');

echo $OUTPUT->header();
echo $OUTPUT->heading('Pilih bulan dan tahun yang mau diunduh');

// ==========================
// Data bulan
// ==========================
$bulan_list = [
    '01' => 'Januari',
    '02' => 'Februari',
    '03' => 'Maret',
    '04' => 'April',
    '05' => 'Mei',
    '06' => 'Juni',
    '07' => 'Juli',
    '08' => 'Agustus',
    '09' => 'September',
    '10' => 'Oktober',
    '11' => 'November',
    '12' => 'Desember',
];

// ==========================
// Ambil default (aman)
// ==========================
$bulan_sekarang = optional_param('bulan', date('m'), PARAM_INT);
$tahun_ini      = optional_param('tahun', date('Y'), PARAM_INT);

// Normalisasi bulan (2 digit)
$bulan_sekarang = str_pad($bulan_sekarang, 2, '0', STR_PAD_LEFT);

// ==========================
// Preview periode (pakai lib.php)
// ==========================
$timestamp_preview = strtotime($tahun_ini . '-' . $bulan_sekarang . '-01');
$preview = tanggal_indo($timestamp_preview, 'bulan');

// ==========================
// UI
// ==========================
echo $OUTPUT->box_start();

// Preview periode
echo html_writer::tag('p',
    'Periode yang dipilih: <strong>' . s($preview) . '</strong>',
    ['style' => 'margin-bottom:10px;']
);

// Form
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => 'exportguruwali_xlsx.php'
]);

// ==========================
// BULAN
// ==========================
echo html_writer::label('Pilih Bulan:', 'bulan');

$options_bulan = [];
foreach ($bulan_list as $num => $nama) {
    $options_bulan[$num] = $nama;
}

echo html_writer::select(
    $options_bulan,
    'bulan',
    $bulan_sekarang,
    false,
    ['id' => 'bulan', 'class' => 'custom-select']
);

// ==========================
// TAHUN
// ==========================
echo html_writer::label(' Tahun:', 'tahun');

$options_tahun = [];
for ($t = date('Y') - 1; $t <= date('Y') + 3; $t++) {
    $options_tahun[$t] = $t;
}

echo html_writer::select(
    $options_tahun,
    'tahun',
    $tahun_ini,
    false,
    ['id' => 'tahun', 'class' => 'custom-select']
);

// ==========================
// Tombol submit
// ==========================
echo html_writer::empty_tag('br');
echo html_writer::empty_tag('br');

echo html_writer::tag('button',
    '📥 Ekspor ke Excel',
    ['type' => 'submit', 'class' => 'btn btn-primary']
);

echo html_writer::end_tag('form');

// ==========================
// Tombol kembali
// ==========================
echo html_writer::empty_tag('br');

echo html_writer::link(
    '#',
    '⬅ Kembali',
    [
        'class' => 'btn btn-secondary',
        'onclick' => 'history.back(); return false;'
    ]
);

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
