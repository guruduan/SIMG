<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/ekstra_lib.php');

require_login();

global $USER;

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/ekstra_riwayat.php'));
$PAGE->set_title('Riwayat Jurnal Ekstra');
$PAGE->set_heading('Riwayat Jurnal Ekstra');

echo $OUTPUT->header();

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/ekstra_input.php'),
    '➕ Input Jurnal',
    ['class' => 'btn btn-primary mb-3']
);

$rows = ekstra_get_riwayat($USER->id);

$table = new html_table();
$table->head = [
    'No',
    'Tanggal',
    'Ekstra',
    'Materi',
    'Aktivitas',
    'Catatan',
    'Absensi',
    'Aksi'
];

$no = 1;

foreach ($rows as $r) {

    $absen = ekstra_format_absensi($r->id);

    $editurl = new moodle_url('/local/jurnalmengajar/ekstra_edit.php', [
        'id' => $r->id
    ]);

    $deleteurl = new moodle_url('/local/jurnalmengajar/ekstra_delete.php', [
        'id' => $r->id,
        'sesskey' => sesskey()
    ]);

    $aksi = html_writer::link($editurl, '✏️ Edit') . ' | ';

    $aksi .= html_writer::link(
        $deleteurl,
        '🗑️ Hapus',
        [
            'onclick' => "return confirm('Yakin hapus jurnal?')",
            'style' => 'color:red;'
        ]
    );

$table->data[] = [
    $no++,
    tanggal_indo($r->tanggal, 'tanggal'),
    s($r->namaekstra),
    shorten_text($r->materi ?: '-', 40),
    shorten_text($r->aktivitas ?: '-', 50),
    shorten_text($r->catatan ?: '-', 50),
    $absen,
    $aksi
];
}

echo html_writer::table($table);

//Tambahkan tombol export di `ekstra_riwayat.php`:

 echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/ekstra_export.php'),
    '📥 Export Excel',
    ['class' => 'btn btn-success mb-3 ml-2']
);

echo $OUTPUT->footer();
