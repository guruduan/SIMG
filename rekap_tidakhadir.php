<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_tidakhadir.php'));


/* =====================================================
   PERIODE
===================================================== */

$tanggalawal = get_config('local_jurnalmengajar', 'tanggalawalminggu');

if (empty($tanggalawal)) {
    echo $OUTPUT->notification(
        'Setting tanggalawalminggu belum diisi.',
        'notifyproblem'
    );
    echo $OUTPUT->footer();
    exit;
}

$dari = strtotime($tanggalawal . ' 00:00:00');
$sampai = time();
$judul = 'Rekap Murid Tidak Hadir s.d. ' . tanggal_indo($sampai, 'tanggal');


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

/* =====================================================
   AMBIL SEMUA KELAS
===================================================== */

$kelaslist = $DB->get_records(
    'cohort',
    null,
    'name ASC'
);

if (!$kelaslist) {

    echo $OUTPUT->notification(
        'Belum ada data kelas.',
        'notifyproblem'
    );

    echo $OUTPUT->footer();
    exit;
}

/* =====================================================
   STATUS YANG DIHITUNG
===================================================== */

$priority = [
    'hadir'      => 0,
    'dispensasi' => 1,
    'sakit'      => 2,
    'ijin'       => 3,
    'alpa'       => 4,
];

/* =====================================================
   LOOP SETIAP KELAS
===================================================== */

foreach ($kelaslist as $kelas) {

    // Ambil anggota kelas
    $members = $DB->get_records(
        'cohort_members',
        [
            'cohortid' => $kelas->id
        ]
    );

    if (!$members) {
        continue;
    }

    $userids = [];

    foreach ($members as $m) {
        $userids[] = $m->userid;
    }

    list($insql, $params) = $DB->get_in_or_equal($userids);

    $users = $DB->get_records_sql("
        SELECT id,
               firstname,
               lastname
        FROM {user}
        WHERE id $insql
        ORDER BY lastname ASC
    ", $params);

    if (!$users) {
        continue;
    }

    // Siapkan penampung hasil tiap murid
    $hasil = [];

    foreach ($users as $u) {

        $hasil[$u->id] = [
            'nama' => trim($u->lastname),
            'sakit' => 0,
            'ijin' => 0,
            'alpa' => 0,
            'dispensasi' => 0,
            'jumlah' => 0
        ];
    }
/* ============================================
   LOOKUP NAMA MURID
============================================ */

// Ambil semua jurnal kelas sejak awal tahun
$jurnals = $DB->get_records_sql(
    "
SELECT id,
       timecreated,
       jamke,
       absen
    FROM {local_jurnalmengajar}
    WHERE kelas = ?
      AND timecreated BETWEEN ? AND ?
    ORDER BY timecreated ASC
    ",
    [
        $kelas->id,
        $dari,
        $sampai
    ]
);

if (empty($jurnals)) {
    continue;
}

/* ============================================
   MODE PER HARI
============================================ */

$perhari = [];
$semuatanggal = [];

foreach ($jurnals as $jurnal) {

    $tgl = date('Y-m-d', $jurnal->timecreated);
    $semuatanggal[$tgl] = true;

    $jamke = array_filter(
        array_map(
            'trim',
            explode(',', (string)$jurnal->jamke)
        )
    );

    $jmljam = count($jamke);
    if ($jmljam == 0) {
        $jmljam = 1;
    }

    $absen = json_decode($jurnal->absen, true);

    if (!is_array($absen)) {
        continue;
    }

    $lookup = [];

    foreach ($absen as $nama => $alasan) {
        $lookup[
            mb_strtolower(trim($nama), 'UTF-8')
        ] = strtolower(trim($alasan));
    }

    foreach ($users as $u) {

        $userid = $u->id;

        $namasiswa = mb_strtolower(
            trim($u->lastname),
            'UTF-8'
        );

        if (!isset($perhari[$userid][$tgl])) {

            $perhari[$userid][$tgl] = [
                'hadir' => 0,
                'sakit' => 0,
                'ijin' => 0,
                'alpa' => 0,
                'dispensasi' => 0
            ];

        }

        $status = $lookup[$namasiswa] ?? 'hadir';

        if (!isset($perhari[$userid][$tgl][$status])) {
            $status = 'hadir';
        }

        $perhari[$userid][$tgl][$status] += $jmljam;
    }
}
/* ============================================
   REKAP PER HARI
============================================ */
$alltanggal = array_keys($semuatanggal);
sort($alltanggal);

foreach ($users as $u) {

    $userid = $u->id;

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

            foreach ([
                'dispensasi',
                'sakit',
                'ijin',
                'alpa'
            ] as $st) {

                if (!empty($perhari[$userid][$tgl][$st])) {

                    if ($priority[$st] > $max) {

                        $max = $priority[$st];
                        $statushari = $st;

                    }

                }

            }

        } else {

            $statushari = 'hadir';

        }

        if ($statushari != 'hadir') {

            $hasil[$userid][$statushari]++;

        }

    }

}


    /* ============================================
       HITUNG JUMLAH TIDAK HADIR
    ============================================ */

    foreach ($hasil as $id => $h) {

        $hasil[$id]['jumlah'] =
            $h['sakit']
            + $h['ijin']
            + $h['alpa']
            + $h['dispensasi'];
    }

    /* ============================================
       HANYA MURID YANG PERNAH TIDAK HADIR
    ============================================ */

    $hasil = array_filter($hasil, function($h) {

        return $h['jumlah'] > 0;

    });

    if (empty($hasil)) {
        continue;
    }

    /* ============================================
       SORTING
    ============================================ */

    usort($hasil, function($a, $b) {

        if ($a['jumlah'] == $b['jumlah']) {

            return strcmp($a['nama'], $b['nama']);
        }

        return $b['jumlah'] <=> $a['jumlah'];

    });

    /* ============================================
       TAMPILKAN KELAS
    ============================================ */

    echo html_writer::tag(
        'hr',
        '',
        [
            'style' => 'margin-top:30px;margin-bottom:20px;'
        ]
    );

echo html_writer::div(
    '<strong>Kelas '.$kelas->name.'</strong>',
    'alert alert-secondary mb-2'
);

echo html_writer::start_tag('ul',[
    'class'=>'list-unstyled'
]);

    $no = 1;

foreach ($hasil as $h) {

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

/* =====================================================
   TOMBOL
===================================================== */

$tombolkembali = html_writer::link(
    '#',
    '⬅ Kembali',
    [
        'class' => 'btn btn-secondary',
        'onclick' => 'history.back();return false;'
    ]
);

echo html_writer::div($tombolkembali, 'mb-3');

echo $OUTPUT->footer();

