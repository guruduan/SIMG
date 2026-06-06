<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/all_pembinaan_mapel.php'));
$PAGE->set_title('Semua Pembinaan Murid');
$PAGE->set_heading('Semua Pembinaan Murid');

global $DB, $OUTPUT;

// ===== Filter =====
$guru    = optional_param('guru', '', PARAM_TEXT);
$kelas   = optional_param('kelas', '', PARAM_INT);
$jenis   = optional_param('jenis', '', PARAM_TEXT);
$page    = optional_param('page', 0, PARAM_INT);

$tanggal = optional_param('tanggal', '', PARAM_TEXT);
$bulan   = optional_param('bulan', '', PARAM_INT);
$tahun   = optional_param('tahun', date('Y'), PARAM_INT);

$perpage = 20;
$offset = $page * $perpage;

echo $OUTPUT->header();
echo $OUTPUT->heading('👨‍🎓 Semua Pembinaan Murid');

// ===== Dropdown Guru =====
$listguru = $DB->get_records_sql("
    SELECT DISTINCT u.lastname
    FROM {local_jurnalmengajar_pembinaanmapel} p
    JOIN {user} u ON u.id = p.userid
    ORDER BY u.lastname
");

$opsiguru = ['' => 'Semua Guru'];

foreach ($listguru as $g) {
    $opsiguru[$g->lastname] = $g->lastname;
}

// ===== Dropdown Kelas =====
$listkelas = $DB->get_records('cohort', null, 'name');

$opsikelas = ['' => 'Semua Kelas'];

foreach ($listkelas as $k) {
    $opsikelas[$k->id] = $k->name;
}

// ===== Dropdown Jenis =====
$listjenis = $DB->get_records_sql("
    SELECT DISTINCT jenis
    FROM {local_jurnalmengajar_pembinaanmapel}
    ORDER BY jenis
");

$opsijenis = ['' => 'Semua Jenis'];

foreach ($listjenis as $j) {
    $opsijenis[$j->jenis] = $j->jenis;
}

// ===== Form Filter =====
echo html_writer::start_tag('form', ['method' => 'get']);

echo 'Guru: ';
echo html_writer::select($opsiguru, 'guru', $guru);

echo ' Kelas: ';
echo html_writer::select($opsikelas, 'kelas', $kelas);

echo ' Jenis: ';
echo html_writer::select($opsijenis, 'jenis', $jenis);

echo ' Tanggal: ';
echo html_writer::empty_tag('input', [
    'type' => 'date',
    'name' => 'tanggal',
    'value' => $tanggal
]);

echo ' Bulan: ';
$opsibulan = [
    '' => 'Semua Bulan',
    '01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr',
    '05'=>'Mei','06'=>'Jun','07'=>'Jul','08'=>'Agu',
    '09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Des'
];
echo html_writer::select($opsibulan, 'bulan', $bulan);

echo ' Tahun: ';
$opsitahun = [];
for ($t = date('Y'); $t >= 2026; $t--) {
    $opsitahun[$t] = $t;
}
echo html_writer::select($opsitahun, 'tahun', $tahun);

echo ' ';
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => 'Filter'
]);

echo html_writer::end_tag('form');

echo html_writer::empty_tag('hr');

// ===== Rentang Tanggal =====
list($awal, $akhir) = jurnalmengajar_get_range(
    $tanggal,
    $bulan,
    $tahun
);

// ===== WHERE =====
$where = [];
$params = [];

if ($guru) {
    $where[] = "g.lastname = :guru";
    $params['guru'] = $guru;
}

if ($kelas) {
    $where[] = "p.kelas = :kelas";
    $params['kelas'] = $kelas;
}

if ($jenis) {
    $where[] = "p.jenis = :jenis";
    $params['jenis'] = $jenis;
}

if ($awal && $akhir) {
    $where[] = "p.timecreated BETWEEN :awal AND :akhir";
    $params['awal'] = $awal;
    $params['akhir'] = $akhir;
}

$where_sql = '';

if ($where) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}

// ===== Total =====
$total = $DB->count_records_sql("
SELECT COUNT(1)
FROM {local_jurnalmengajar_pembinaanmapel} p
JOIN {user} g ON g.id = p.userid
$where_sql
", $params);

// ===== Data =====
$sql = "
SELECT
    p.*,
    g.lastname AS namaguru,
    s.lastname AS namamurid,
    c.name AS namakelas,
    j.matapelajaran
FROM {local_jurnalmengajar_pembinaanmapel} p
JOIN {user} g ON g.id = p.userid
JOIN {user} s ON s.id = p.muridid
LEFT JOIN {cohort} c ON c.id = p.kelas
LEFT JOIN {local_jurnalmengajar} j ON j.id = p.jurnalid
$where_sql
ORDER BY p.id DESC
";

$records = $DB->get_records_sql(
    $sql,
    $params,
    $offset,
    $perpage
);

// ===== Tabel =====
if ($records) {

    echo html_writer::start_tag(
        'table',
        ['class' => 'generaltable']
    );

    echo html_writer::tag('tr',
        html_writer::tag('th', 'No') .
        html_writer::tag('th', 'Guru') .
        html_writer::tag('th', 'Murid') .
        html_writer::tag('th', 'Kelas') .
        html_writer::tag('th', 'Mapel') .
        html_writer::tag('th', 'Jenis') .
        html_writer::tag('th', 'Masalah') .
        html_writer::tag('th', 'Tindak Lanjut/Solusi') .
        html_writer::tag('th', 'Waktu') .
        html_writer::tag('th', 'Aksi')
    );

    $no = $offset + 1;

    foreach ($records as $r) {
$editurl = new moodle_url(
    '/local/jurnalmengajar/edit_pembinaan_mapel.php',
    ['id' => $r->id]
);

$deleteurl = new moodle_url(
    '/local/jurnalmengajar/delete_pembinaan_mapel.php',
    [
        'id' => $r->id,
        'sesskey' => sesskey()
    ]
);

$aksi =
    html_writer::link($editurl, 'Edit') .
    ' | ' .
    html_writer::link(
        $deleteurl,
        'Hapus',
        [
            'onclick' => "return confirm('Yakin ingin menghapus data pembinaan ini?')",
            'class' => 'text-danger'
        ]
    );
    
        echo html_writer::tag('tr',
            html_writer::tag('td', $no++) .
            html_writer::tag('td', $r->namaguru) .
            html_writer::tag('td', $r->namamurid) .
            html_writer::tag('td', $r->namakelas) .
            html_writer::tag('td', $r->matapelajaran) .
            html_writer::tag('td', $r->jenis) .
            html_writer::tag(
                'td',
                shorten_text($r->catatan, 50),
                ['title' => $r->catatan]
            ) .
            html_writer::tag(
                'td',
                shorten_text($r->tindaklanjut, 50),
                ['title' => $r->tindaklanjut]
            ) .
            html_writer::tag(
                'td',
                tanggal_indo($r->timecreated)
            ) .
            html_writer::tag('td', $aksi)
        );
    }

    echo html_writer::end_tag('table');

    echo $OUTPUT->paging_bar(
        $total,
        $page,
        $perpage,
        new moodle_url(
            '/local/jurnalmengajar/all_pembinaan_mapel.php',
            [
                'guru' => $guru,
                'kelas' => $kelas,
                'jenis' => $jenis,
                'tanggal' => $tanggal,
                'bulan' => $bulan,
                'tahun' => $tahun
            ]
        )
    );

} else {

    echo html_writer::div(
        'Belum ada data pembinaan.',
        'alert alert-info'
    );
}

echo $OUTPUT->footer();
