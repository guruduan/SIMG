<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/all_jurnal.php'));
$PAGE->set_title('Semua Jurnal Mengajar');
$PAGE->set_heading('Semua Jurnal Mengajar');

global $DB, $OUTPUT;

// ===== Filter =====
$guru  = optional_param('guru', '', PARAM_TEXT);
$kelas = optional_param('kelas', '', PARAM_TEXT);
$mapel = optional_param('mapel', '', PARAM_TEXT);
$page  = optional_param('page', 0, PARAM_INT);
$tanggal = optional_param('tanggal', '', PARAM_TEXT);
$bulan = optional_param('bulan', '', PARAM_INT);
$tahun = optional_param('tahun', date('Y'), PARAM_INT);

$perpage = 20;
$offset  = $page * $perpage;

echo $OUTPUT->header();
echo $OUTPUT->heading('📊 Semua Jurnal Guru');

// ===== Dropdown Guru =====
$listguru = $DB->get_records_sql("
    SELECT DISTINCT u.lastname
    FROM {local_jurnalmengajar} j
    JOIN {user} u ON u.id = j.userid
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

// ===== Dropdown Mapel =====
$listmapel = $DB->get_records_sql("
    SELECT DISTINCT matapelajaran
    FROM {local_jurnalmengajar}
    ORDER BY matapelajaran
");

$opsimapel = ['' => 'Semua Mapel'];
foreach ($listmapel as $m) {
    $opsimapel[$m->matapelajaran] = $m->matapelajaran;
}

// ===== Form Filter =====
echo html_writer::start_tag('form', ['method' => 'get']);

echo 'Guru: ';
echo html_writer::select($opsiguru, 'guru', $guru);

echo ' Kelas: ';
echo html_writer::select($opsikelas, 'kelas', $kelas);

echo ' Mapel: ';
echo html_writer::select($opsimapel, 'mapel', $mapel);

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
for ($t = date('Y'); $t >= 2020; $t--) {
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
list($awal, $akhir) = jurnalmengajar_get_range($tanggal, $bulan, $tahun);

echo '<div style="margin:10px 0; padding:8px; background:#f5f5f5; border-left:4px solid orange;">';

if ($tanggal && $awal) {
    echo '<b>Mode:</b> Harian | ' . tanggal_indo(strtotime($tanggal), 'tanggal');
} elseif ($bulan && $awal) {
$ts = strtotime("$tahun-$bulan-01");
echo '<b>Mode:</b> Bulan ' . tanggal_indo($ts, 'tanggal');
} else {
    echo '<b>Mode:</b> Semua Data';
}

echo '</div>';

// ===== WHERE =====
$where = [];
$params = [];

if ($guru) {
    $where[] = "u.lastname = :guru";
    $params['guru'] = $guru;
}

if ($kelas) {
    $where[] = "j.kelas = :kelas";
    $params['kelas'] = $kelas;
}

if ($mapel) {
    $where[] = "j.matapelajaran = :mapel";
    $params['mapel'] = $mapel;
}

if ($awal && $akhir) {
    $where[] = "j.timecreated BETWEEN :awal AND :akhir";
    $params['awal']  = $awal;
    $params['akhir'] = $akhir;
}

$where_sql = '';
if ($where) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}

// ===== Total data =====
$total = $DB->count_records_sql("
    SELECT COUNT(1)
    FROM {local_jurnalmengajar} j
    JOIN {user} u ON u.id = j.userid
    $where_sql
", $params);

// ===== Ambil data =====
$sql = "
SELECT j.*, u.lastname, c.name AS namakelas
FROM {local_jurnalmengajar} j
JOIN {user} u ON u.id = j.userid
LEFT JOIN {cohort} c ON c.id = j.kelas
$where_sql
ORDER BY j.id DESC
";

$entries = $DB->get_records_sql($sql, $params, $offset, $perpage);

// ===== Tabel =====
if ($entries) {

    echo html_writer::start_tag('table', ['class' => 'generaltable']);

    echo html_writer::tag('tr',
        html_writer::tag('th', 'No') .
        html_writer::tag('th', 'Nama Guru') .
        html_writer::tag('th', 'Kelas') .
        html_writer::tag('th', 'Jam Ke') .
        html_writer::tag('th', 'Mapel') .
        html_writer::tag('th', 'Materi') .
        html_writer::tag('th', 'Absen') .
        html_writer::tag('th', 'Waktu') .
        html_writer::tag('th', 'Status') .
        html_writer::tag('th', 'Aksi')
    );

    $no = $offset + 1;

    foreach ($entries as $e) {

        $namaguru  = $e->lastname;
        $namakelas = $e->namakelas ?? '???';

        $absendata = json_decode($e->absen, true);
        $absentext = '';
        if (is_array($absendata)) {
            foreach ($absendata as $nama => $alasan) {
                $absentext .= "$nama ($alasan), ";
            }
            $absentext = rtrim($absentext, ', ');
        } else {
            $absentext = $e->absen;
        }

        $editurl = new moodle_url('/local/jurnalmengajar/edit_jurnal.php', ['id' => $e->id]);
        $deleteurl = new moodle_url('/local/jurnalmengajar/delete.php', [
            'id' => $e->id,
            'sesskey' => sesskey()
        ]);
        
$status = '<span style="color:green;">Normal</span>';

if ($e->timemodified > 0) {
    if (date('Y-m-d', $e->timecreated) != date('Y-m-d', $e->timemodified)) {
        $status = '<span style="color:orange;">Susulan</span>';
    } else {
        $status = '<span style="color:red;">Diedit</span>';
    }
}

        $aksi = html_writer::link($editurl, 'Edit');
        $aksi .= ' | ' . html_writer::link(
            $deleteurl,
            'Hapus',
            [
                'onclick' => "return confirm('Yakin ingin menghapus jurnal ini?')",
                'class' => 'text-danger'
            ]
        );

        echo html_writer::tag('tr',
            html_writer::tag('td', $no) .
            html_writer::tag('td', $namaguru) .
            html_writer::tag('td', $namakelas) .
            html_writer::tag('td', $e->jamke) .
            html_writer::tag('td', $e->matapelajaran) .
            html_writer::tag('td', shorten_text($e->materi, 30), ['title' => $e->materi]) .
            html_writer::tag('td', shorten_text($absentext, 25), ['title' => $absentext]) .
            html_writer::tag('td', tanggal_indo($e->timecreated)) .
            html_writer::tag('td', $status) .
            html_writer::tag('td', $aksi)
        );

        $no++;
    }

    echo html_writer::end_tag('table');

    // ===== Paging =====
    echo $OUTPUT->paging_bar($total, $page, $perpage,
        new moodle_url('/local/jurnalmengajar/all_jurnal.php', [
            'guru' => $guru,
            'kelas' => $kelas,
            'mapel' => $mapel,
            'tanggal' => $tanggal,
            'bulan' => $bulan,
            'tahun' => $tahun
        ])
    );

} else {
    echo 'Belum ada data jurnal.';
}

echo $OUTPUT->footer();
