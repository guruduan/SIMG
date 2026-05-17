<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/ekstra_lib.php');

require_login();

global $DB;

$context = context_system::instance();

require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(
    new moodle_url('/local/jurnalmengajar/ekstra_semua_riwayat.php')
);

$PAGE->set_title('Semua Riwayat Jurnal Ekstra');
$PAGE->set_heading('Semua Riwayat Jurnal Ekstra');

echo $OUTPUT->header();

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/ekstra_input.php'),
    '➕ Input Jurnal',
    ['class' => 'btn btn-primary mb-3']
);

echo ' ';

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/ekstra_export.php'),
    '📥 Export Excel',
    ['class' => 'btn btn-success mb-3']
);

$rows = $DB->get_recordset_sql("
    SELECT
        j.*,
        e.namaekstra,
        u.firstname,
        u.lastname
    FROM {local_ekstra_jurnal} j
    JOIN {local_jm_ekstra} e
        ON e.id = j.ekstraid
    JOIN {user} u
        ON u.id = j.pembinaid
    ORDER BY j.tanggal DESC, j.id DESC
");

$table = new html_table();

$table->head = [
    'No',
    'Tanggal',
    'Pembina',
    'Ekstra',
    'Materi',
    'Aktivitas',
    'Catatan',
    'Absensi',
    'Aksi'
];

$no = 1;

foreach ($rows as $r) {

    $absensi = ekstra_format_absensi($r->id);

    $editurl = new moodle_url(
        '/local/jurnalmengajar/ekstra_edit.php',
        ['id' => $r->id]
    );

    $deleteurl = new moodle_url(
        '/local/jurnalmengajar/ekstra_delete.php',
        [
            'id' => $r->id,
            'sesskey' => sesskey()
        ]
    );

    $aksi = html_writer::link(
        $editurl,
        '✏️ Edit'
    );

    $aksi .= ' | ';

    $aksi .= html_writer::link(
        $deleteurl,
        '🗑️ Hapus',
        [
            'onclick' => "return confirm('Yakin hapus jurnal ini?')",
            'style' => 'color:red;'
        ]
    );

    $pembina = trim(
        $r->firstname . ' ' . $r->lastname
    );

    $table->data[] = [
        $no++,
        tanggal_indo($r->tanggal, 'tanggal'),
        s($pembina),
        s($r->namaekstra),
        shorten_text($r->materi ?: '-', 40),
        shorten_text($r->aktivitas ?: '-', 60),
        shorten_text($r->catatan ?: '-', 60),
        $absensi,
        $aksi
    ];
}

$rows->close();

echo html_writer::table($table);

echo $OUTPUT->footer();
