<?php
require('../../config.php');
require_once(__DIR__.'/lib.php');
require_once(__DIR__.'/jadwal_acuan_lib.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/guruwali_manage.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Manajemen Guru Wali');
$PAGE->set_heading('Manajemen Guru Wali / Murid Binaan');

echo $OUTPUT->header();

global $DB, $USER;

/* ==========================================================
   Tombol Atas
========================================================== */

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/import_binaan.php'),
    'Import CSV',
    ['class' => 'btn btn-primary']
);

echo ' ';

echo html_writer::link(
    new moodle_url(
        '/local/jurnalmengajar/guruwali_cleanup.php',
        ['sesskey' => sesskey()]
    ),
    '🗑 Bersihkan Murid Tanpa Cohort',
    [
        'class' => 'btn btn-danger',
        'onclick' => "return confirm('Seluruh murid binaan yang tidak memiliki cohort akan dihapus. Lanjutkan?')"
    ]
);

echo ' ';

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/guruwali_add.php'),
    'Tambah / Update Murid Binaan',
    ['class' => 'btn btn-success']
);

echo '<br><br>';

/* ==========================================================
   Daftar Guru Wali
========================================================== */

$listguru = [];

$sqlguru = "
SELECT DISTINCT
    gw.guruid,
    u.lastname
FROM {local_jurnalmengajar_guruwali} gw
JOIN {user} u
     ON u.id = gw.guruid
ORDER BY u.lastname
";

$rowsguru = $DB->get_records_sql($sqlguru);

foreach ($rowsguru as $g) {
    $listguru[$g->guruid] = $g->lastname;
}

// ======================================================
// Statistik Guru Wali
// ======================================================

// Jumlah murid yang menjadi anggota cohort.
$jumlahcohort = $DB->count_records_sql("
    SELECT COUNT(DISTINCT userid)
    FROM {cohort_members}
");

// Jumlah murid di cohort yang sudah memiliki Guru Wali.
$jumlahguruwali = $DB->count_records_sql("
    SELECT COUNT(DISTINCT cm.userid)
    FROM {cohort_members} cm
    JOIN {local_jurnalmengajar_guruwali} gw
         ON gw.muridid = cm.userid
");

// Selisih = murid belum memiliki Guru Wali.
$belumguruwali = max(0, $jumlahcohort - $jumlahguruwali);


/* ==========================================================
   Filter Guru
========================================================== */

$filterguru = optional_param('guru', $USER->id, PARAM_INT);

echo html_writer::start_tag('form', [
    'method' => 'get',
    'style' => 'margin-bottom:15px;'
]);

echo 'Filter Guru Wali : ';

echo html_writer::select(
    $listguru,
    'guru',
    $filterguru,
    null,
    [
        'onchange' => 'this.form.submit()'
    ]
);

echo html_writer::end_tag('form');

/* ==========================================================
   Ambil Murid Binaan Guru
========================================================== */

$sql = "
SELECT

    gw.id,
    gw.guruid,
    gw.muridid,

    guru.lastname  AS namaguru,

    murid.lastname AS namamurid,

    d.data         AS nis,

    c.name         AS kelas

FROM {local_jurnalmengajar_guruwali} gw

JOIN {user} guru
     ON guru.id = gw.guruid

JOIN {user} murid
     ON murid.id = gw.muridid

LEFT JOIN {user_info_field} f
       ON f.shortname='nis'

LEFT JOIN {user_info_data} d
       ON d.userid = murid.id
      AND d.fieldid = f.id

LEFT JOIN {cohort_members} cm
       ON cm.userid = murid.id

LEFT JOIN {cohort} c
       ON c.id = cm.cohortid

WHERE gw.guruid = :guruid

ORDER BY

CASE
WHEN c.name IS NULL THEN 1
ELSE 0
END,

c.name,

murid.lastname
";

$rows = $DB->get_records_sql(
    $sql,
    [
        'guruid' => $filterguru
    ]
);

echo html_writer::start_div('alert alert-info');

echo html_writer::tag('h5', 'Statistik Guru Wali');

$table = new html_table();

$table->attributes['class'] = 'table table-sm table-bordered';

$table->data[] = [
    'Murid di Cohort',
    $jumlahcohort
];

$table->data[] = [
    'Sudah Punya Guru Wali',
    $jumlahguruwali
];

$table->data[] = [
    'Belum Punya Guru Wali',
    $belumguruwali == 0
        ? '<span class="text-success"><b>0 ✅</b></span>'
        : '<span class="text-danger"><b>'.$belumguruwali.' ⚠️</b></span>'
];

echo html_writer::table($table);

echo html_writer::end_div();

/* ==========================================================
   Tabel Murid Binaan
========================================================== */

$table = new html_table();

$table->head = [
    'No',
    'NIS',
    'Nama Murid',
    'Kelas',
    'Guru Wali',
    'Aksi'
];

$table->attributes['class'] = 'generaltable';

$no = 1;

foreach ($rows as $r) {

    // Nama murid mengikuti format plugin.
    $namamurid = format_nama_siswa($r->namamurid);

    // Nama guru.
    $namaguru = s($r->namaguru);

    // Kelas mengikuti cohort aktif.
    $kelas = !empty($r->kelas)
        ? s($r->kelas)
        : '<span class="text-danger">Belum ada kelas</span>';

    // NIS.
    $nis = s($r->nis);

    // Tombol hapus.
    $hapusurl = new moodle_url(
        '/local/jurnalmengajar/guruwali_delete.php',
        [
            'id' => $r->id,
            'sesskey' => sesskey()
        ]
    );

    $hapus = html_writer::link(
        $hapusurl,
        'Hapus',
        [
            'class' => 'btn btn-danger btn-sm',
            'onclick' => "return confirm('Hapus murid binaan ini?')"
        ]
    );

    $table->data[] = [
        $no,
        $nis,
        $namamurid,
        $kelas,
        $namaguru,
        $hapus
    ];

    $no++;
}

// Jika belum ada data.
if ($no == 1) {

    $table->data[] = [
        [
            'text' => 'Belum ada murid binaan.',
            'colspan' => 6,
            'style' => 'text-align:center'
        ]
    ];
}

echo html_writer::table($table);
