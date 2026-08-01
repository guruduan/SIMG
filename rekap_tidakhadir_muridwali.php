<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_tidakhadir_muridwali.php'));

/* =====================================================
   PERIODE 
===================================================== */
// Menggunakan periode bawaan dari rekap_tidakhadir
$tanggalawal = get_config('local_jurnalmengajar', 'tanggalawalminggu');

if (empty($tanggalawal)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        'Setting tanggalawalminggu belum diisi.',
        'notifyproblem'
    );
    echo $OUTPUT->footer();
    exit;
}

$dari = strtotime($tanggalawal . ' 00:00:00');
$sampai = time();
$judul = 'Rekap Murid Binaan GuruWali Tidak Hadir s.d. ' . tanggal_indo($sampai, 'tanggal');

$PAGE->set_title($judul);
$PAGE->set_heading($judul);

echo $OUTPUT->header();
echo $OUTPUT->heading($judul, 2);

echo html_writer::div(
    '<strong>Periode :</strong><br>' .
    tanggal_indo($dari, 'tanggal') .
    ' s.d. ' .
    tanggal_indo($sampai, 'tanggal'),
    'alert alert-info'
);

/* ==========================
 * INFO GURU WALI
 * ========================== */
echo html_writer::start_div('alert alert-info d-flex justify-content-between align-items-center mb-4 shadow-sm');
echo html_writer::span('<strong><i class="fa fa-user-circle"></i> Guru Wali:</strong> ' . s($USER->lastname));
echo html_writer::end_div();

/* ==========================
 * AMBIL MURID BINAAN
 * ========================== */
$sql = "
SELECT muridid
FROM {local_jurnalmengajar_guruwali}
WHERE guruid = :guruid
ORDER BY muridid
";

$rows = $DB->get_records_sql($sql, ['guruid' => $USER->id]);
$userids = [];

foreach ($rows as $r) {
    $userids[] = $r->muridid;
}

if (empty($userids)) {
    echo html_writer::div(
        html_writer::tag('h4', '<i class="fa fa-exclamation-triangle"></i> Peringatan', ['class'=>'alert-heading']).
        html_writer::tag('p', 'Anda belum memiliki murid binaan.', ['class'=>'mb-0']),
        'alert alert-warning shadow-sm mt-3'
    );
    echo $OUTPUT->footer();
    exit;
}

list($in_sql, $paramsin) = $DB->get_in_or_equal($userids);
$users = $DB->get_records_sql("
SELECT
    u.id,
    u.firstname,
    u.lastname,
    c.id AS kelasid,
    c.name AS kelas
FROM {user} u
LEFT JOIN {cohort_members} cm ON cm.userid=u.id
LEFT JOIN {cohort} c ON c.id=cm.cohortid
WHERE u.id $in_sql
ORDER BY c.name, u.lastname
", $paramsin);

// Penampung data hasil per kelas
$hasil_per_kelas = [];

foreach ($users as $u) {
    $kelas = $u->kelas ?: 'Belum ada kelas';
    $kelasid = $u->kelasid ?: 0;

    if (!isset($hasil_per_kelas[$kelas])) {
        $hasil_per_kelas[$kelas] = [];
    }

    $hasil_per_kelas[$kelas][$u->id] = [
        'nama' => trim($u->lastname),
        'sakit' => 0,
        'ijin' => 0,
        'alpa' => 0,
        'dispensasi' => 0,
        'jumlah' => 0,
        'kelasid' => $kelasid
    ];
}

/* ==========================
 * HELPER & STATUS
 * ========================== */
$priority = [
    'hadir'      => 0,
    'dispensasi' => 1,
    'sakit'      => 2,
    'ijin'       => 3,
    'alpa'       => 4,
];

function normalize_status(string $s): string {
    $s = strtolower(trim($s));
    $map = [
        'ijin'=>'ijin','izin'=>'ijin',
        'sakit'=>'sakit',
        'alpha'=>'alpa','alpa'=>'alpa',
        'disp'=>'dispensasi','dispensasi'=>'dispensasi',
        'hadir'=>'hadir'
    ];
    return $map[$s] ?? $s;
}

/* ============================================
   PROSES DATA JURNAL (MODE PER HARI)
============================================ */
// Ambil ID kelas dari murid-murid binaan
$kelasids = array_unique(array_column($users, 'kelasid'));
$kelasids = array_filter($kelasids);

$jurnals = [];
if (!empty($kelasids)) {
    list($in_kelas, $param_kelas) = $DB->get_in_or_equal($kelasids);
    $param_jurnal = array_merge($param_kelas, [$dari, $sampai]);

    $jurnals = $DB->get_records_sql("
        SELECT id, timecreated, jamke, matapelajaran, absen, kelas
        FROM {local_jurnalmengajar}
        WHERE kelas $in_kelas
          AND timecreated BETWEEN ? AND ?
        ORDER BY timecreated ASC
    ", $param_jurnal);
}

$perhari = [];
$semuatanggal = [];

if (!empty($jurnals)) {
    foreach ($jurnals as $jurnal) {
        $tgl = date('Y-m-d', $jurnal->timecreated);
        $semuatanggal[$tgl] = true;

        $jamke = array_filter(array_map('trim', explode(',', (string)$jurnal->jamke)));
        $jmljam = count($jamke) ?: 1;

        $absen = json_decode($jurnal->absen, true);
        if (!is_array($absen)) {
            continue;
        }

        $lookup = [];
        foreach ($absen as $nama => $alasan) {
            $lookup[mb_strtolower(trim($nama), 'UTF-8')] = normalize_status($alasan);
        }

        foreach ($users as $u) {
            if (empty($u->kelasid) || (int)$jurnal->kelas !== (int)$u->kelasid) {
                continue;
            }
            if (!jurnalmengajar_is_peserta_mapel($u->id, $jurnal->matapelajaran)) {
                continue;
            }

            $namasiswa = mb_strtolower(trim($u->lastname), 'UTF-8');
            $status = $lookup[$namasiswa] ?? 'hadir';

            if (!isset($perhari[$u->id][$tgl])) {
                $perhari[$u->id][$tgl] = [
                    'hadir'=>0, 'sakit'=>0, 'ijin'=>0, 'alpa'=>0, 'dispensasi'=>0
                ];
            }
            $perhari[$u->id][$tgl][$status] += $jmljam;
        }
    }

    // Kalkulasi rekap harian
    $alltanggal = array_keys($semuatanggal);
    sort($alltanggal);

    foreach ($users as $u) {
        $userid = $u->id;
        $kelas = $u->kelas ?: 'Belum ada kelas';

        foreach ($alltanggal as $tgl) {
            if (empty($perhari[$userid][$tgl])) {
                continue;
            }

            $h = $perhari[$userid][$tgl]['hadir'];
            $tot = array_sum($perhari[$userid][$tgl]);

            if ($tot == 0) {
                continue;
            }

            $nonhadir = $tot - $h;
            if ($nonhadir == 0) {
                $statushari = 'hadir';
            } elseif ($h == 0) {
                $statushari = 'hadir';
                $max = -1;

                foreach (['dispensasi','sakit','ijin','alpa'] as $st) {
                    if (!empty($perhari[$userid][$tgl][$st]) && $priority[$st] > $max) {
                        $max = $priority[$st];
                        $statushari = $st;
                    }
                }
            } else {
                $statushari = 'hadir';
            }

            if ($statushari != 'hadir') {
                $hasil_per_kelas[$kelas][$userid][$statushari]++;
            }
        }
    }
}

/* ============================================
   TAMPILKAN KELAS & MURID (HANYA YANG TIDAK HADIR)
============================================ */
$has_data = false;

foreach ($hasil_per_kelas as $namakelas => $murids) {
    $filtered = [];

    // Hitung total ketidakhadiran & saring murid
    foreach ($murids as $id => $h) {
        $h['jumlah'] = $h['sakit'] + $h['ijin'] + $h['alpa'] + $h['dispensasi'];
        if ($h['jumlah'] > 0) {
            $filtered[] = $h;
        }
    }

    if (empty($filtered)) {
        continue;
    }
    
    $has_data = true;

    // Sorting berdasarkan jumlah terbanyak, lalu abjad
    usort($filtered, function($a, $b) {
        if ($a['jumlah'] == $b['jumlah']) {
            return strcmp($a['nama'], $b['nama']);
        }
        return $b['jumlah'] <=> $a['jumlah'];
    });

    // Output per kelas
    echo html_writer::tag('hr', '', ['style' => 'margin-top:30px;margin-bottom:20px;']);
    echo html_writer::div('<strong>Kelas '.$namakelas.'</strong>', 'alert alert-secondary mb-2');
    echo html_writer::start_tag('ul', ['class'=>'list-unstyled']);
    
    $no = 1;
    foreach ($filtered as $h) {
        echo html_writer::tag(
            'li',
            $no.'. <strong>'.$h['nama'].'</strong> &nbsp; '.
            'Sakit: '.$h['sakit'].
            ', Ijin: '.$h['ijin'].
            ', Alpa: '.$h['alpa'].
            ', Dispensasi: '.$h['dispensasi'].
            ', <strong>Jumlah Tidak Hadir: '.$h['jumlah'].'</strong>'
        );
        $no++;
    }
    echo html_writer::end_tag('ul');
}

if (!$has_data) {
    echo html_writer::div('Tidak ada murid binaan yang tidak hadir pada periode ini.', 'alert alert-success mt-4');
}

/* =====================================================
   TOMBOL KEMBALI
===================================================== */
$tombolkembali = html_writer::link(
    '#',
    '⬅ Kembali',
    [
        'class' => 'btn btn-secondary',
        'onclick' => 'history.back();return false;'
    ]
);

echo html_writer::div($tombolkembali, 'mt-4 mb-3');

echo $OUTPUT->footer();
