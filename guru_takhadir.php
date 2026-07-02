<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/guru_takhadir.php');
$PAGE->set_title('Guru Tidak Hadir');
$PAGE->set_heading('Guru Tidak Hadir');

echo $OUTPUT->header();

//echo $OUTPUT->heading('Guru Tidak Hadir');

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/guru_takhadir_add.php'),
    '<i class="fa fa-plus"></i> Tambah',
    ['class' => 'btn btn-success mb-3']
);

$sql = "
SELECT
    k.*,
    u.lastname
FROM {local_jurnalmengajar_kehadiran} k
JOIN {user} u
    ON u.id = k.userid
ORDER BY
    k.tanggalmulai DESC,
    FIELD(k.status,
        'sakit',
        'izin',
        'cuti',
        'tugasluar'
    ),
    u.lastname ASC
";

$records = $DB->get_records_sql($sql);

if (empty($records)) {

    echo $OUTPUT->notification(
        'Belum ada data guru tidak hadir.',
        \core\output\notification::NOTIFY_INFO
    );

    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->attributes['class'] = 'generaltable table table-striped';

$table->head = [
    'No',
    'Guru',
    'Status',
    'Periode',
    'Keterangan',
    'Aksi'
];

$no = 1;

foreach ($records as $r) {

    switch ($r->status) {

        case 'sakit':
            $status = '<span class="badge bg-danger">Sakit</span>';
            break;

        case 'izin':
            $status = '<span class="badge bg-warning text-dark">Izin</span>';
            break;

        case 'cuti':
            $status = '<span class="badge bg-info text-dark">Cuti</span>';
            break;

        case 'tugasluar':
            $status = '<span class="badge bg-primary">Tugas Luar</span>';
            break;

        default:
            $status = s($r->status);
    }

$periode = tanggal_indo($r->tanggalmulai, 'tanggal');

if (
    date('Y-m-d', $r->tanggalmulai)
    !=
    date('Y-m-d', $r->tanggalselesai)
) {
    $periode .= ' s/d ' .
        tanggal_indo($r->tanggalselesai, 'tanggal');
}

    $editurl = new moodle_url(
        '/local/jurnalmengajar/guru_takhadir_edit.php',
        ['id' => $r->id]
    );

	$deleteurl = new moodle_url(
	    '/local/jurnalmengajar/guru_takhadir_delete.php',
	    [
		'id' => $r->id,
		'sesskey' => sesskey()
	    ]
	);

    $aksi =
        html_writer::link(
            $editurl,
            '<i class="fa fa-edit"></i>',
            [
                'class' => 'btn btn-sm btn-warning',
                'title' => 'Edit'
            ]
        )
        . ' ' .
        html_writer::link(
            $deleteurl,
            '<i class="fa fa-trash"></i>',
            [
                'class' => 'btn btn-sm btn-danger',
                'title' => 'Hapus',
                'onclick' => "return confirm('Yakin ingin menghapus data ini?')"
            ]
        );

$table->data[] = [
    $no++,
    s($r->lastname),
    $status,
    $periode,
    format_text($r->keterangan, FORMAT_PLAIN),
    $aksi
];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
