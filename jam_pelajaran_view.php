<?php
require('../../config.php');
require_once(__DIR__.'/jam_pelajaran_lib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);

$PAGE->set_url(new moodle_url('/local/jurnalmengajar/jam_pelajaran_view.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Jam Pelajaran');
$PAGE->set_heading('Alokasi Waktu Jam Pelajaran');

echo $OUTPUT->header();

// Ambil data
$jam = jurnalmengajar_generate_jam();

// Table
$table = new html_table();
$table->head = ['Jam', 'Mulai', 'Selesai'];
$table->attributes['class'] = 'generaltable';
$table->align = ['center', 'center', 'center'];
$table->data = [];

$now = date('H:i');

foreach ($jam as $j => $w) {

    $mulai   = $w['mulai'];
    $selesai = $w['selesai'];

    $label_jam = $j;

    if ($now >= $mulai && $now <= $selesai) {
        $label_jam = $j . '*';
    }

    $table->data[] = [
        $label_jam, // ❗ jangan pakai format_string biar bisa custom
        format_string($mulai),
        format_string($selesai)
    ];

    // Baris istirahat
    if (!empty($w['istirahat_setelah'])) {
        $cell = new html_table_cell(
            'ISTIRAHAT ' . (int)$w['istirahat_setelah'] . ' MENIT'
        );
        $cell->colspan = 3;
        $cell->attributes['class'] = 'text-center';
        $cell->attributes['style'] = 'background:#ffeeba; font-weight:bold;';

        $table->data[] = [$cell];
    }
}

echo html_writer::table($table);

// Tombol kembali
echo html_writer::div(
    html_writer::link(
        new moodle_url('/my/'),
        '⬅ Kembali ke Dashboard',
        ['class' => 'btn btn-primary']
    ),
    'mt-3'
);

echo $OUTPUT->footer();
