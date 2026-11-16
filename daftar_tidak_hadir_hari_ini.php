<?php

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
jurnalmengajar_log_page_access();

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(
    new moodle_url('/local/jurnalmengajar/daftar_tidak_hadir_hari_ini.php')
);
$PAGE->set_pagelayout('report');
$PAGE->set_title('Daftar murid tidak hadir hari ini');
$PAGE->set_heading('Daftar murid tidak hadir hari ini');

global $DB;

/* =====================================================
   RENTANG HARI INI
===================================================== */

$start = strtotime(date('Y-m-d 00:00:00'));
$end   = strtotime(date('Y-m-d 23:59:59'));


/* =====================================================
   UTILITAS ALASAN ABSEN
===================================================== */

function classify_absent_reason(?string $raw): string {

    if ($raw === null) {
        return '';
    }

    $v = trim(mb_strtolower($raw, 'UTF-8'));

    $v = str_replace('izin', 'ijin', $v);

    if (preg_match('/^\s*sakit\b/u', $v)) {
        return 'sakit';
    }

    if (preg_match('/^\s*ijin\b/u', $v)) {
        return 'ijin';
    }

    if (preg_match('/^\s*alpa\b/u', $v)) {
        return 'alpa';
    }

    if (preg_match('/^\s*dispensasi\b/u', $v)) {
        return 'dispensasi';
    }

    return '';
}


/* =====================================================
   PARSER JAMKE
   Contoh:
   8
   8,9,10
   8-11
   1-3,5,7-9
===================================================== */

function parse_jamke($raw): array {

    $out = [];

    if ($raw === null) {
        return $out;
    }

    if (is_int($raw)) {

        if ($raw > 0) {
            $out[$raw] = true;
        }

        return array_keys($out);
    }

    $s = trim((string)$raw);

    if ($s === '') {
        return $out;
    }

    $s = preg_replace('/[^0-9,\-\s]/', '', $s);

    foreach (explode(',', $s) as $tok) {

        $tok = trim($tok);

        if ($tok === '') {
            continue;
        }

        if (strpos($tok, '-') !== false) {

            [$a, $b] = array_pad(
                array_map('trim', explode('-', $tok, 2)),
                2,
                ''
            );

            if ($a === '' || $b === '') {
                continue;
            }

            $ia = (int)$a;
            $ib = (int)$b;

            if ($ia <= 0 || $ib <= 0) {
                continue;
            }

            if ($ia <= $ib) {

                for ($j = $ia; $j <= $ib; $j++) {
                    $out[$j] = true;
                }

            } else {

                for ($j = $ib; $j <= $ia; $j++) {
                    $out[$j] = true;
                }
            }

        } else {

            $n = (int)$tok;

            if ($n > 0) {
                $out[$n] = true;
            }
        }
    }

    $nums = array_keys($out);

    sort($nums, SORT_NUMERIC);

    return $nums;
}


/* =====================================================
   KONVERSI KELAS -> NAMA COHORT
===================================================== */

$cohort_cache = [];

function cohort_name_from_any(
    $kelasraw,
    array &$cache,
    \moodle_database $DB
): string {

    $kelas = trim((string)$kelasraw);

    if ($kelas === '') {
        return '';
    }

    $kelas = preg_replace('/\s+/', ' ', $kelas);

    if (ctype_digit($kelas)) {

        if (isset($cache[$kelas])) {
            return $cache[$kelas];
        }

        if (
            $rec = $DB->get_record(
                'cohort',
                ['id' => (int)$kelas],
                'id, name, idnumber'
            )
        ) {

            $label = '';

            if (!empty($rec->name)) {

                $label = (string)$rec->name;

            } else if (!empty($rec->idnumber)) {

                $label = (string)$rec->idnumber;

            } else {

                $label = (string)$rec->id;
            }

            $cache[$kelas] = $label;

            return $label;
        }

        return $kelas;
    }

    return $kelas;
}


/* =====================================================
   AMBIL JURNAL HARI INI
===================================================== */

$select = '
    timecreated BETWEEN :s AND :e
    AND absen IS NOT NULL
    AND absen <> :empty
';

$params = [
    's'     => $start,
    'e'     => $end,
    'empty' => ''
];

$fields = '
    id,
    kelas,
    jamke,
    absen,
    absenid,
    timecreated
';

$journals = $DB->get_records_select(
    'local_jurnalmengajar',
    $select,
    $params,
    'timecreated ASC',
    $fields
);


/* =====================================================
   BANGUN DATA ABSENSI
=====================================================

   Prioritas:
   1. absenid = userid => alasan
   2. absen   = lastname => fallback data lama

===================================================== */

$data = [];

foreach ($journals as $jr) {

    $kelaslabel = cohort_name_from_any(
        $jr->kelas,
        $cohort_cache,
        $DB
    );

    if ($kelaslabel === '') {
        continue;
    }

    $jamlist = parse_jamke($jr->jamke);

    /* =================================================
       PRIORITAS 1
       GUNAKAN ABSENID
    ================================================= */

    $absenid = [];

    if (!empty($jr->absenid)) {

        $decoded = json_decode(
            $jr->absenid,
            true
        );

        if (is_array($decoded)) {
            $absenid = $decoded;
        }
    }

    if (!empty($absenid)) {

        foreach ($absenid as $userid => $alasanraw) {

            $userid = (int)$userid;

            if ($userid <= 0) {
                continue;
            }

            $cls = classify_absent_reason(
                is_null($alasanraw)
                    ? ''
                    : (string)$alasanraw
            );

            if ($cls === '') {
                continue;
            }

            /* -----------------------------------------
               Ambil murid berdasarkan ID
            ----------------------------------------- */

            $murid = $DB->get_record(
                'user',
                ['id' => $userid],
                'id, lastname'
            );

            if (!$murid) {
                continue;
            }

            if (!isset($data[$kelaslabel])) {
                $data[$kelaslabel] = [];
            }

            if (!isset($data[$kelaslabel][$userid])) {

                $data[$kelaslabel][$userid] = [

                    'userid' => $userid,

                    'lastname' => trim(
                        $murid->lastname
                    ),

                    'reasons' => [
                        'sakit'      => 0,
                        'ijin'       => 0,
                        'alpa'       => 0,
                        'dispensasi' => 0
                    ],

                    'jam' => [],

                    'firsttime' =>
                        $jr->timecreated,
                ];

            } else {

                if (
                    $jr->timecreated <
                    $data[$kelaslabel][$userid]['firsttime']
                ) {

                    $data[$kelaslabel][$userid]['firsttime']
                        = $jr->timecreated;
                }
            }

            $data[$kelaslabel][$userid]['reasons'][$cls]++;

            foreach ($jamlist as $j) {

                $data[$kelaslabel][$userid]['jam'][$j]
                    = true;
            }
        }

        /*
         * Sudah diproses menggunakan userid.
         */
        continue;
    }


    /* =================================================
       PRIORITAS 2
       DATA LAMA YANG HANYA MEMILIKI ABSEN
    ================================================= */

    $json = json_decode(
        $jr->absen,
        true
    );

    if (!is_array($json)) {
        continue;
    }

    foreach ($json as $lastname => $alasanraw) {

        $lastname = trim((string)$lastname);

        if ($lastname === '') {
            continue;
        }

        $cls = classify_absent_reason(
            is_null($alasanraw)
                ? ''
                : (string)$alasanraw
        );

        if ($cls === '') {
            continue;
        }

        /*
         * Cari semua user dengan lastname tersebut.
         *
         * TIDAK menggunakan get_record(),
         * karena lastname tidak unik.
         */
        $users = $DB->get_records(
            'user',
            ['lastname' => $lastname],
            'id ASC',
            'id, lastname'
        );

        /*
         * Hanya dianggap aman jika satu user saja.
         */
        $userid = 0;

        if (count($users) === 1) {

            $user = reset($users);

            $userid = (int)$user->id;
        }

        /*
         * Gunakan userid sebagai key jika tersedia.
         * Jika tidak, gunakan nama sebagai fallback.
         */
        $datakey = $userid > 0
            ? (string)$userid
            : 'nama_' . md5($lastname);

        if (!isset($data[$kelaslabel])) {
            $data[$kelaslabel] = [];
        }

        if (!isset($data[$kelaslabel][$datakey])) {

            $data[$kelaslabel][$datakey] = [

                'userid' => $userid,

                'lastname' => $lastname,

                'reasons' => [
                    'sakit'      => 0,
                    'ijin'       => 0,
                    'alpa'       => 0,
                    'dispensasi' => 0
                ],

                'jam' => [],

                'firsttime' =>
                    $jr->timecreated,
            ];

        } else {

            if (
                $jr->timecreated <
                $data[$kelaslabel][$datakey]['firsttime']
            ) {

                $data[$kelaslabel][$datakey]['firsttime']
                    = $jr->timecreated;
            }
        }

        $data[$kelaslabel][$datakey]['reasons'][$cls]++;

        foreach ($jamlist as $j) {

            $data[$kelaslabel][$datakey]['jam'][$j]
                = true;
        }
    }
}


/* =====================================================
   FLATTEN & URUTKAN
===================================================== */

$rows = [];

foreach ($data as $kelas => $students) {

    foreach ($students as $info) {

        arsort(
            $info['reasons'],
            SORT_NUMERIC
        );

        $absen = key(
            $info['reasons']
        );

        $jams = array_keys(
            $info['jam']
        );

        sort(
            $jams,
            SORT_NUMERIC
        );

        $rows[] = (object)[

            'kelas' =>
                $kelas,

            'userid' =>
                (int)$info['userid'],

            'lastname' =>
                $info['lastname'],

            'absen' =>
                $absen,

            'jamke' =>
                implode(',', $jams),

            'timeinput' =>
                $info['firsttime'],
        ];
    }
}


/* =====================================================
   URUT KELAS + NAMA
===================================================== */

usort(
    $rows,
    function($a, $b) {

        $c = strnatcasecmp(
            $a->kelas,
            $b->kelas
        );

        if ($c !== 0) {
            return $c;
        }

        return strnatcasecmp(
            $a->lastname,
            $b->lastname
        );
    }
);


/* =====================================================
   OUTPUT
===================================================== */

echo $OUTPUT->header();


/* Tombol kembali */

echo html_writer::div(

    html_writer::link(
        '#',
        '⬅ Kembali',
        [
            'class' =>
                'btn btn-secondary',

            'onclick' =>
                'history.back(); return false;'
        ]
    ),

    'mb-3'
);


/* =====================================================
   TAB SWITCH
===================================================== */

echo html_writer::start_div(
    'mb-3 text-start'
);

echo html_writer::link(

    new moodle_url(
        '/local/jurnalmengajar/daftar_tidak_hadir_hari_ini.php'
    ),

    '⏰ Hari ini',

    [
        'class' =>
            'btn btn-primary me-1'
    ]
);

echo html_writer::link(

    new moodle_url(
        '/local/jurnalmengajar/daftar_tidak_hadir_tgl.php'
    ),

    '📅 Ke Tanggal',

    [
        'class' =>
            'btn btn-outline-secondary'
    ]
);

echo html_writer::end_div();


/* =====================================================
   TANGGAL
===================================================== */

echo html_writer::tag(

    'p',

    html_writer::tag(
        'strong',
        'Hari/Tanggal: '
    )
    .
    tanggal_indo(
        time(),
        'judul'
    ),

    [
        'class' =>
            'mb-3'
    ]
);


/* =====================================================
   JIKA TIDAK ADA DATA
===================================================== */

if (empty($rows)) {

    echo $OUTPUT->notification(

        'Tidak ada murid yang tercatat tidak hadir ' .
        '(sakit/ijin/alpa/dispensasi) hari ini.',

        'notifymessage'
    );

    echo $OUTPUT->footer();

    exit;
}


/* =====================================================
   TABEL
===================================================== */

$table = new html_table();

$table->head = [
    'No',
    'Kelas',
    'Nama Murid',
    'Absen',
    'Jamke',
    'Waktu Input'
];

$table->attributes[
    'class'
] = 'generaltable boxaligncenter';

$table->data = [];


$no = 1;

foreach ($rows as $r) {

    /*
     * Jika userid tersedia,
     * nama menjadi link ke riwayat individu.
     */
    if (!empty($r->userid)) {

        $namamurid = html_writer::link(

            new moodle_url(
                '/local/jurnalmengajar/riwayat_individu.php',
                [
                    'muridid' =>
                        $r->userid
                ]
            ),

            ucwords(
                strtolower(
                    $r->lastname
                )
            ),

            [
                'class' =>
                    'font-weight-bold text-primary',

                'title' =>
                    'Lihat riwayat individu murid'
            ]
        );

    } else {

        /*
         * Data lama yang userid-nya tidak
         * dapat dipastikan tetap ditampilkan,
         * tetapi tidak dibuat link.
         */
        $namamurid = ucwords(
            strtolower(
                $r->lastname
            )
        );
    }


    $table->data[] = [

        $no++,

        format_string(
            $r->kelas
        ),

        $namamurid,

        format_string(
            $r->absen
        ),

        format_string(
            $r->jamke === ''
                ? '-'
                : $r->jamke
        ),

        format_string(
            tanggal_indo(
                $r->timeinput,
                'jam'
            )
        ),
    ];
}


echo html_writer::table(
    $table
);

echo $OUTPUT->footer();
