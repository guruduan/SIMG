<?php
// File: local/jurnalmengajar/rekapnilai.php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');
require_once($CFG->libdir . '/phpspreadsheet/vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekapnilai.php'));
$PAGE->set_title('Rekap Nilai Harian');
$PAGE->set_heading('Rekap Nilai Harian');

$config = get_config('local_jurnalmengajar');
$tahunajaran = $config->tahun_ajaran ?? '-';
$namasekolah = $config->nama_sekolah ?? '-';
$tanggalawal = get_config('local_jurnalmengajar', 'tanggalawalminggu');

global $DB, $USER, $OUTPUT;

// ====== Ambil filter ======
$mapel    = optional_param('mapel', '', PARAM_TEXT);
$cohortid = optional_param('cohortid', 0, PARAM_INT);
$export   = optional_param('export', '', PARAM_ALPHA);

// ====== Opsi Mapel: hanya mapel yang pernah diisi oleh user ini ======
$mapelopts = [];
$myMapels = $DB->get_fieldset_sql("
    SELECT DISTINCT mapel
      FROM {local_jm_nilaiharian}
     WHERE userid = :uid
       AND tanggal >= :tanggalawal
       AND mapel <> ''
  ORDER BY mapel ASC
", [
    'uid' => $USER->id,
    'tanggalawal' => $tanggalawal
]);
foreach ($myMapels as $m) {
    $m = trim($m);
    if ($m !== '') { $mapelopts[$m] = $m; }
}
// Jika ?mapel tidak ada di opsi user, kosongkan
if (!array_key_exists($mapel, $mapelopts)) {
    $mapel = '';
}

// ====== Opsi Cohort (nama saja, dedup nama) ======
// ====== Opsi Cohort: hanya kelas yang pernah diinput oleh user ini ======
$cohortopts = [];
$seen = [];

// ambil daftar cohortid yang pernah diinput user ini
$mycohortids = $DB->get_fieldset_sql("
    SELECT DISTINCT cohortid
      FROM {local_jm_nilaiharian}
     WHERE userid = :uid
       AND cohortid > 0
       AND tanggal >= :tanggalawal
  ORDER BY cohortid ASC
", [
    'uid' => $USER->id,
    'tanggalawal' => $tanggalawal
]);

if (!empty($mycohortids)) {
    // ambil nama cohort berdasarkan id yang ditemukan
    $cohorts = $DB->get_records_list('cohort', 'id', $mycohortids, 'name ASC', 'id,name');
    foreach ($cohorts as $c) {
        if (isset($seen[$c->name])) { continue; } // dedup by name (opsional)
        $cohortopts[$c->id] = $c->name;
        $seen[$c->name] = true;
    }
}

// jika cohortid yang dipilih tidak ada di opsi -> reset
if ($cohortid && !array_key_exists($cohortid, $cohortopts)) {
    $cohortid = 0;
}

// ----------------------------------------------------
// Siapkan data (members, entries, matrix) lebih dulu,
// agar EXPORT CSV bisa dikerjakan sebelum ada output.
// ----------------------------------------------------
$students = [];   // userid => name
$attempts = [];   // daftar entri (untuk keterangan)
$matrix   = [];   // [userid][attempt_index] = nilai (0 default)
$N        = 0;    // jumlah kolom Nilai

if (!empty($cohortid)) {
    // Ambil daftar murid cohort
    $members = $DB->get_records_sql("
        SELECT u.id, u.firstname, u.lastname
          FROM {cohort_members} cm
          JOIN {user} u ON u.id = cm.userid
         WHERE cm.cohortid = :cid
      ORDER BY u.lastname, u.firstname
    ", ['cid' => $cohortid]);

    foreach ($members as $u) {
        $students[$u->id] = ucwords(strtolower($u->lastname)); // lastname Proper Case
    }

    // Ambil entri nilai (milik user ini), filter mapel jika dipilih
$where = "cohortid = :cohortid
          AND userid = :uid
          AND tanggal >= :tanggalawal";

$params = [
    'cohortid'    => $cohortid,
    'uid'         => $USER->id,
    'tanggalawal' => $tanggalawal
];
    if (!empty($mapel)) {
        $where .= " AND mapel = :mapel";
        $params['mapel'] = $mapel;
    }

    // Urut kronologis: Nilai 1 = entri awal
    $entries = $DB->get_records_select('local_jm_nilaiharian', $where, $params, 'tanggal ASC, timecreated ASC');

    // Build attempts
$idx = 0;
foreach ($entries as $rec) {

    // Validasi timestamp (anti error 1970)
    $ts = !empty($rec->tanggal) ? strtotime($rec->tanggal) : false;

    $attempts[$idx] = [
        'id'      => $rec->id,
        'idx'     => $idx + 1,
        'judul'   => $rec->judul,
        'mapel'   => $rec->mapel,
        'kelas'   => $rec->kelas,
        'tanggal' => ($ts !== false)
            ? tanggal_indo($ts, 'tanggal')
            : '-',
        'guru'    => $rec->userid
    ];

    $idx++;
}
$N = count($attempts);

    // Inisialisasi matrix 0
    foreach ($students as $uid => $name) {
        for ($i = 0; $i < max(1, $N); $i++) {
            $matrix[$uid][$i] = 0;
        }
    }

    // Isi matrix dari JSON
    $idx = 0;
    foreach ($entries as $rec) {
        $rows = json_decode($rec->nilaijson ?? '[]');
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (!isset($r->userid)) { continue; }
                $uid = (int)$r->userid;
                if (!array_key_exists($uid, $students)) { continue; } // hanya murid di cohort ini
                $matrix[$uid][$idx] = isset($r->nilai) && $r->nilai !== '' ? (int)$r->nilai : 0;
            }
        }
        $idx++;
    }
}

// ====== EXPORT XLSX (harus dieksekusi sebelum output HTML) ======
$mapellabel = $mapel ?: '- Semua Mapel -';
$kelaslabel = $cohortopts[$cohortid] ?? '';
// ====== EXPORT XLSX ======
if ($export === 'xlsx' && !empty($cohortid)) {

    $filename = 'rekap_nilai_per_murid_' . userdate(time(), '%Y%m%d_%H%M%S') . '.xlsx';

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->setCellValue('A1', 'REKAP NILAI HARIAN');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

    $sheet->setCellValue('A3', 'Mata Pelajaran');
    $sheet->setCellValue('B3', $mapellabel);

    $sheet->setCellValue('A4', 'Sekolah');
    $sheet->setCellValue('B4', $namasekolah);

    $sheet->setCellValue('A5', 'Kelas');
    $sheet->setCellValue('B5', $kelaslabel);

    $sheet->setCellValue('A6', 'Tahun');
    $sheet->setCellValue('B6', $tahunajaran);

    $row = 8;

    $sheet->setCellValue('A'.$row, 'No');
    $sheet->setCellValue('B'.$row, 'Nama Murid');
    $sheet->setCellValue('C'.$row, 'Rata-rata');

    $col = 'D';

    if ($N > 0) {
        foreach ($attempts as $a) {
            $sheet->setCellValue($col.$row, $a['judul']);
            $col++;
        }
    } else {
        $sheet->setCellValue('D'.$row, 'Nilai');
    }

    $sheet->getStyle('A8:' . $sheet->getHighestColumn() . '8')
        ->getFont()
        ->setBold(true);

    $row++;

    $no = 1;
    $den = max(1, $N);

    foreach ($students as $uid => $name) {

        $sum = 0;

        for ($i = 0; $i < $den; $i++) {
            $sum += (int)$matrix[$uid][$i];
        }

        $avg = $den > 0 ? round($sum / $den, 2) : 0;

        $sheet->setCellValue('A'.$row, $no++);
        $sheet->setCellValue('B'.$row, $name);
        $sheet->setCellValue('C'.$row, $avg);

        $col = 'D';

        for ($i = 0; $i < $den; $i++) {
            $sheet->setCellValue($col.$row, $matrix[$uid][$i]);
            $col++;
        }

        $row++;
    }

    $lastrow = $sheet->getHighestRow();

    $sheet->getStyle(
        'A8:' . $sheet->getHighestColumn() . $lastrow
    )->getBorders()->getAllBorders()->setBorderStyle(
        \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
    );

    foreach (range('A', $sheet->getHighestColumn()) as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ====== Mulai output HTML ======
echo $OUTPUT->header();
// Tombol kembali
echo html_writer::div(
    html_writer::link(
        '#',
        '⬅ Kembali',
        [
            'class' => 'btn btn-secondary',
            'onclick' => 'history.back(); return false;'
        ]
    ),
    'mb-3'
);
// Form filter (GET)
$url = new moodle_url('/local/jurnalmengajar/rekapnilai.php');
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url, 'class' => 'mform']);
echo html_writer::start_div('filters', ['style' => 'display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;']);

// Mapel (tampil hanya jika user ini pernah input)
if (!empty($mapelopts)) {
    $mapelchoices = ['' => '- Semua Mapel -'] + $mapelopts;
    echo html_writer::div(
        html_writer::label(get_string('matapelajaran', 'local_jurnalmengajar'), 'id_mapel', false) .
        html_writer::select($mapelchoices, 'mapel', $mapel, null, ['id' => 'id_mapel']),
        'fitem'
    );
}

// Kelas (wajib)
$kelaschoices = ['' => '-- Pilih Kelas --'] + $cohortopts;
echo html_writer::div(
    html_writer::label(get_string('kelas', 'local_jurnalmengajar'), 'id_cohortid', false) .
    html_writer::select($kelaschoices, 'cohortid', $cohortid, null, ['id' => 'id_cohortid', 'required' => 'required']),
    'fitem'
);

// Tombol
echo html_writer::div(
    html_writer::empty_tag('input', [
        'type'  => 'submit',
        'value' => 'Tampilkan',
        'class' => 'btn btn-primary'
    ]) . ' ' .
    html_writer::tag('button', '📥 Export XLSX', [
        'type'  => 'submit',
        'name'  => 'export',
        'value' => 'xlsx',
        'class' => 'btn btn-secondary'
    ]),
    'fitem'
);

echo html_writer::end_div();
echo html_writer::end_tag('form');

// Jika belum pilih kelas → info saja
if (empty($cohortid)) {
    echo $OUTPUT->notification('Silakan pilih Kelas terlebih dahulu.', \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

// ====== Tabel hasil ======
$table = new html_table();
$head = ['No', 'Nama Murid', 'Rata-rata'];

if ($N > 0) {
    foreach ($attempts as $a) {
$url = new moodle_url(
    '/local/jurnalmengajar/nilaiharian.php',
    ['id' => $a['id']]
);

$head[] =
    html_writer::div(s($a['judul'])) .
    html_writer::div(
        html_writer::link($url, '✏️ Edit'),
        '',
        ['style' => 'font-size:11px;']
    );
    }
} else {
    $head[] = 'Nilai';
}
$table->head  = $head;
$table->align = array_merge(['center','left','center'], array_fill(0, max(1,$N), 'center'));

$data = [];
$no  = 1;
$den = max(1, $N);
foreach ($students as $uid => $name) {
    $sum = 0;
    for ($i=0; $i < $den; $i++) { $sum += (int)$matrix[$uid][$i]; }
    $avg = $den > 0 ? round($sum / $den, 2) : 0;

    $row = [$no++, s($name), s($avg)];
    for ($i=0; $i < $den; $i++) {
        $row[] = s($matrix[$uid][$i]); // 0 jika kosong
    }
    $data[] = new html_table_row($row);
}
$table->data = $data;

// Render
if ($N === 0) {
    echo $OUTPUT->notification('Belum ada entri nilai untuk filter ini. Tabel menampilkan siswa dengan kolom Nilai kosong (0).', \core\output\notification::NOTIFY_INFO);
}
echo html_writer::table($table);

// Keterangan kolom (opsional)
if ($N > 0) {
    $ket = [];
    for ($i=0; $i<$N; $i++) {
        $guru = fullname(\core_user::get_user($attempts[$i]['guru']));
        $ket[] =
    $attempts[$i]['judul'].' : '.
    $attempts[$i]['mapel'].' • '.
    $attempts[$i]['kelas'].' • '.
    $attempts[$i]['tanggal'].' • '.
    $guru;
    }
    echo $OUTPUT->box(implode('<br>', array_map('s', $ket)), 'generalbox');
}

echo $OUTPUT->footer();
