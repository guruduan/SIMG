<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');

require_login();

global $DB, $PAGE, $OUTPUT;

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/riwayat_pembinaan.php'));
$PAGE->set_title('Riwayat Pembinaan');
$PAGE->set_heading('Riwayat Pembinaan Murid');

echo $OUTPUT->header();
//echo $OUTPUT->heading('Riwayat Pembinaan Murid');

// ================= FILTER =================

$filterawal   = optional_param('awal', '', PARAM_TEXT);
$filterakhir  = optional_param('akhir', '', PARAM_TEXT);
$filternama   = optional_param('nama', '', PARAM_TEXT);
$filterkelas  = optional_param('kelas', 0, PARAM_INT);
$filterguru   = optional_param('guru', 0, PARAM_INT);

// ================= LIST KELAS =================

$kelaslist = [0 => 'Semua Kelas'];

$cohorts = $DB->get_records('cohort', null, 'name ASC', 'id,name');

foreach ($cohorts as $c) {
    $kelaslist[$c->id] = $c->name;
}

// ================= LIST GURU BK =================

$gurulist = [0 => 'Semua Guru BK'];

$gurus = $DB->get_records_sql("
    SELECT DISTINCT u.id, u.lastname
    FROM {user} u
    JOIN {local_jurnalpembinaan} p ON p.userid = u.id
    ORDER BY u.lastname ASC
");

foreach ($gurus as $g) {
    $gurulist[$g->id] = $g->lastname;
}

// ================= FORM FILTER =================

echo html_writer::start_tag('form', [
    'method' => 'get',
    'style' => 'margin-bottom:20px;'
]);

echo '<div style="
display:flex;
flex-wrap:wrap;
gap:10px;
align-items:end;
background:#f8f9fa;
padding:15px;
border-radius:10px;
margin-bottom:15px;
">';

// tanggal awal
echo '<div>';
echo html_writer::label('Dari Tanggal', 'awal');
echo html_writer::empty_tag('input', [
    'type' => 'date',
    'name' => 'awal',
    'value' => $filterawal,
    'class' => 'form-control'
]);
echo '</div>';

// tanggal akhir
echo '<div>';
echo html_writer::label('Sampai Tanggal', 'akhir');
echo html_writer::empty_tag('input', [
    'type' => 'date',
    'name' => 'akhir',
    'value' => $filterakhir,
    'class' => 'form-control'
]);
echo '</div>';

// nama murid
echo '<div>';
echo html_writer::label('Nama Murid', 'nama');
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'nama',
    'value' => s($filternama),
    'placeholder' => 'Cari nama murid',
    'class' => 'form-control'
]);
echo '</div>';

// kelas
echo '<div>';
echo html_writer::label('Kelas', 'kelas');
echo html_writer::select(
    $kelaslist,
    'kelas',
    $filterkelas,
    false,
    ['class' => 'form-control']
);
echo '</div>';

// guru bk
echo '<div>';
echo html_writer::label('Guru BK', 'guru');
echo html_writer::select(
    $gurulist,
    'guru',
    $filterguru,
    false,
    ['class' => 'form-control']
);
echo '</div>';

// tombol
echo '<div>';
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => 'Filter',
    'class' => 'btn btn-primary'
]);
echo '</div>';

echo '</div>';

echo html_writer::end_tag('form');

// ================= PAGING =================

$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;
$offset = $page * $perpage;

// ================= WHERE =================

$where = [];
$params = [];

// filter tanggal awal
if (!empty($filterawal)) {
    $where[] = "p.timecreated >= :awal";
    $params['awal'] = strtotime($filterawal . ' 00:00:00');
}

// filter tanggal akhir
if (!empty($filterakhir)) {
    $where[] = "p.timecreated <= :akhir";
    $params['akhir'] = strtotime($filterakhir . ' 23:59:59');
}

// filter nama murid
if (!empty($filternama)) {
    $where[] = $DB->sql_like('p.peserta', ':nama', false);
    $params['nama'] = '%' . $DB->sql_like_escape($filternama) . '%';
}

// filter kelas
if (!empty($filterkelas)) {
    $where[] = "p.kelas = :kelas";
    $params['kelas'] = $filterkelas;
}

// filter guru
if (!empty($filterguru)) {
    $where[] = "p.userid = :guru";
    $params['guru'] = $filterguru;
}

$sqlwhere = '';

if ($where) {
    $sqlwhere = 'WHERE ' . implode(' AND ', $where);
}

// ================= TOTAL =================

$total = $DB->count_records_sql("
    SELECT COUNT(*)
    FROM {local_jurnalpembinaan} p
    $sqlwhere
", $params);

// ================= DATA =================

$sql = "
SELECT p.*, u.lastname AS gurubk
FROM {local_jurnalpembinaan} p
LEFT JOIN {user} u ON u.id = p.userid
$sqlwhere
ORDER BY p.timecreated DESC
";

$records = $DB->get_records_sql($sql, $params, $offset, $perpage);

// ================= TABEL =================

if ($records) {

    $table = new html_table();

    $table->head = [
        'No',
        'Waktu',
        'Nama Murid',
        'Kelas',
        'Permasalahan',
        'Upaya',
        'Guru BK'
    ];

    $table->attributes['class'] = 'generaltable table-sm';

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

        $table->data[] = [
            $no++,
            tanggal_indo($r->timecreated),
            shorten_text($peserta_str, 60),
            $namakelas,
            format_string($r->permasalahan),
            format_string($r->tindakan),
            format_string($r->gurubk)
        ];
    }

    echo html_writer::table($table);

} else {

    echo $OUTPUT->notification(
        'Data pembinaan tidak ditemukan.',
        'notifymessage'
    );
}

// ================= PAGING =================

$baseurl = new moodle_url('/local/jurnalmengajar/riwayat_pembinaan.php', [
    'awal'  => $filterawal,
    'akhir' => $filterakhir,
    'nama'  => $filternama,
    'kelas' => $filterkelas,
    'guru'  => $filterguru
]);

echo $OUTPUT->paging_bar(
    $total,
    $page,
    $perpage,
    $baseurl
);

echo $OUTPUT->footer();
