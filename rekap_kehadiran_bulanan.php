<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_kehadiran_bulanan.php'));
$PAGE->set_title('Rekap Kehadiran Murid Per Bulan');
$PAGE->set_heading('Rekap Kehadiran Murid Per Bulan');

echo $OUTPUT->header();

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

echo $OUTPUT->heading('Rekap Kehadiran Murid Per Bulan');

// ================= FILTER =================

$kelaslist = $DB->get_records_menu('cohort', null, 'name ASC', 'id, name');

$kelasid  = optional_param('kelas', 0, PARAM_INT);
$bulanraw = optional_param('bulan', date('Y-m'), PARAM_TEXT);
$mode     = optional_param('mode', 'hari', PARAM_ALPHA);

// Validasi format YYYY-MM
if (!preg_match('/^\d{4}-\d{2}$/', $bulanraw)) {
    $bulanraw = date('Y-m');
}

$dari = strtotime($bulanraw . '-01 00:00:00');
$sampai = strtotime(date('Y-m-t', $dari) . ' 23:59:59');

// ================= NORMALISASI STATUS =================

if (!function_exists('normalize_status')) {
    function normalize_status($s) {

        $s = strtolower(trim($s));

        $map = [
            'ijin' => 'ijin',
            'izin' => 'ijin',

            'sakit' => 'sakit',
            'skt' => 'sakit',

            'alpha' => 'alpa',
            'alpa' => 'alpa',
            'absen' => 'alpa',

            'disp' => 'dispensasi',
            'dispen' => 'dispensasi',
            'dispensasi' => 'dispensasi',

            'hadir' => 'hadir'
        ];

        return $map[$s] ?? $s;
    }
}

// Prioritas mode harian
$priority = [
    'hadir'       => 0,
    'dispensasi'  => 1,
    'sakit'       => 2,
    'ijin'        => 3,
    'alpa'        => 4,
];

// ================= FORM =================

echo html_writer::start_tag('form', [
    'method' => 'get',
    'class' => 'mb-4'
]);

echo html_writer::start_div('d-flex gap-3 align-items-end flex-wrap');

// Kelas
echo html_writer::start_div();

echo html_writer::tag(
    'label',
    'Pilih Kelas',
    ['class' => 'form-label']
);

echo html_writer::select(
    $kelaslist,
    'kelas',
    $kelasid ?: '',
    ['' => '-- Pilih Kelas --'],
    ['class' => 'form-select']
);

echo html_writer::end_div();

// Bulan
echo html_writer::start_div();

echo html_writer::tag(
    'label',
    'Bulan',
    ['class' => 'form-label']
);

echo html_writer::empty_tag('input', [
    'type'  => 'month',
    'name'  => 'bulan',
    'value' => $bulanraw,
    'class' => 'form-control'
]);

echo html_writer::end_div();

// Mode
echo html_writer::start_div();

echo html_writer::tag(
    'label',
    'Mode Hitung',
    ['class' => 'form-label']
);

echo html_writer::select(
    [
        'hari' => 'Per Hari',
        'jam'  => 'Per Jam'
    ],
    'mode',
    $mode,
    false,
    ['class' => 'form-select']
);

echo html_writer::end_div();

// Tombol
echo html_writer::start_div();

echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => 'Tampilkan',
    'class' => 'btn btn-primary'
]);

echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::end_tag('form');

// ================= PROSES =================

if ($kelasid && $dari && $sampai) {

    echo html_writer::div(
        '<strong>Bulan:</strong> ' . tanggal_indo($dari, 'bulan')
        . '<br>'
        . '<strong>Mode:</strong> ' . ($mode == 'hari' ? 'Per Hari' : 'Per Jam'),
        'mb-3'
    );

    // ================= SISWA =================

    $members = $DB->get_records('cohort_members', [
        'cohortid' => $kelasid
    ]);

    $userids = array_map(fn($m) => $m->userid, $members);

    if (empty($userids)) {

        echo $OUTPUT->notification(
            'Tidak ada murid di kelas ini.',
            'notifyproblem'
        );

        echo $OUTPUT->footer();
        exit;
    }

    list($in_sql, $paramsin) = $DB->get_in_or_equal($userids);

    $users = $DB->get_records_sql("
        SELECT id, firstname, lastname
        FROM {user}
        WHERE id $in_sql
        ORDER BY lastname ASC
    ", $paramsin);

    // ================= JURNAL =================

    $params = [
        'kelas'  => $kelasid,
        'dari'   => $dari,
        'sampai' => $sampai
    ];

    $jurnals = $DB->get_records_select(
        'local_jurnalmengajar',
        'kelas = :kelas AND timecreated BETWEEN :dari AND :sampai',
        $params
    );

    // ================= DATA AWAL =================

    $data = [];

    foreach ($users as $u) {

        $data[$u->id] = [
            'hadir'      => 0,
            'sakit'      => 0,
            'ijin'       => 0,
            'alpa'       => 0,
            'dispensasi' => 0
        ];
    }

    // =========================================================
    // MODE HARI
    // =========================================================

    if ($mode == 'hari') {

        $perhari = [];
        $all_dates = [];

        foreach ($jurnals as $jurnal) {

            $tgl = date('Y-m-d', $jurnal->timecreated);

            $all_dates[$tgl] = true;

            $jamke = array_filter(
                array_map(
                    'trim',
                    explode(',', (string)($jurnal->jamke ?? ''))
                )
            );

            $jmljam = count($jamke);

            if ($jmljam == 0) {
                $jmljam = 1;
            }

            $absen = json_decode($jurnal->absen, true) ?? [];

            $lookup = [];

            foreach ($absen as $nama => $alasan) {

                $lookup[
                    mb_strtolower(trim($nama), 'UTF-8')
                ] = normalize_status($alasan);
            }

            foreach ($users as $uid => $u) {

                $namasiswa = mb_strtolower(
                    trim($u->lastname),
                    'UTF-8'
                );

                if (!isset($perhari[$uid][$tgl])) {

                    $perhari[$uid][$tgl] = [
                        'hadir'      => 0,
                        'sakit'      => 0,
                        'ijin'       => 0,
                        'alpa'       => 0,
                        'dispensasi' => 0
                    ];
                }

                if (isset($lookup[$namasiswa])) {
                    $status = $lookup[$namasiswa];
                } else {
                    $status = 'hadir';
                }

                if (!isset($perhari[$uid][$tgl][$status])) {
                    $status = 'hadir';
                }

                $perhari[$uid][$tgl][$status] += $jmljam;
            }
        }

        $uniqdates = array_keys($all_dates);

        sort($uniqdates);

        foreach ($users as $uid => $u) {

            foreach ($uniqdates as $tgl) {

                if (empty($perhari[$uid][$tgl])) {
                    continue;
                }

                $h = $perhari[$uid][$tgl]['hadir'];

                $tot = array_sum($perhari[$uid][$tgl]);

                if ($tot == 0) {
                    continue;
                }

                $nonhadir = $tot - $h;

                if ($nonhadir == 0) {

                    $statushari = 'hadir';

                } else if ($h == 0) {

                    $statushari = 'hadir';

                    $maxprio = -1;

                    foreach ([
                        'dispensasi',
                        'sakit',
                        'ijin',
                        'alpa'
                    ] as $st) {

                        if (!empty($perhari[$uid][$tgl][$st])) {

                            $p = $priority[$st] ?? 0;

                            if ($p > $maxprio) {

                                $maxprio = $p;
                                $statushari = $st;
                            }
                        }
                    }

                } else {

                    $statushari = 'hadir';
                }

                $data[$uid][$statushari] += 1;
            }
        }

        $total_unit = count($uniqdates);
        $unit_label = 'hari';

    } else {

        // =====================================================
        // MODE JAM
        // =====================================================

        foreach ($jurnals as $jurnal) {

            $jamke = array_filter(
                array_map(
                    'trim',
                    explode(',', (string)($jurnal->jamke ?? ''))
                )
            );

            $jmljam = count($jamke);

            $absen = json_decode($jurnal->absen, true) ?? [];

            foreach ($users as $uid => $u) {

                $namasiswa = trim($u->lastname);

                $found = false;

                foreach ($absen as $nama => $alasan) {

                    if (
                        strcasecmp(
                            trim($nama),
                            $namasiswa
                        ) == 0
                    ) {

                        $alasan = normalize_status($alasan);

                        if (isset($data[$uid][$alasan])) {
                            $data[$uid][$alasan] += $jmljam;
                        }

                        $found = true;

                        break;
                    }
                }

                if (!$found) {
                    $data[$uid]['hadir'] += $jmljam;
                }
            }
        }

        $total_unit = 0;

        foreach ($data as $d) {

            $total_unit = max(
                $total_unit,
                $d['hadir']
                + $d['sakit']
                + $d['ijin']
                + $d['alpa']
                + $d['dispensasi']
            );
        }

        $unit_label = 'jam';
    }

    // ================= TABEL =================

    echo html_writer::start_tag('table', [
        'class' => 'generaltable'
    ]);

    echo html_writer::start_tag('thead');

    echo html_writer::tag(
        'tr',

        html_writer::tag('th', 'No') .
        html_writer::tag('th', 'Nama Murid') .
        html_writer::tag('th', 'Hadir') .
        html_writer::tag('th', 'Sakit') .
        html_writer::tag('th', 'Ijin') .
        html_writer::tag('th', 'Alpa') .
        html_writer::tag('th', 'Dispensasi') .
        html_writer::tag('th', 'Persentase')
    );

    echo html_writer::end_tag('thead');

    echo html_writer::start_tag('tbody');

    $no = 1;

    foreach ($data as $uid => $d) {

        if ($total_unit > 0) {

            $p = ($d['hadir'] / $total_unit) * 100;

            $persen =
                number_format($p, 1, ',', '')
                . '% dari '
                . $total_unit . ' '
                . $unit_label;

        } else {

            $persen = '-';
        }

        $namasiswa = ucwords(
            strtolower($users[$uid]->lastname)
        );

        $rowstyle = '';

if ($total_unit > 0 && $p < 80) {
    $rowstyle = 'background-color:#f8d7da;';
}

echo html_writer::tag(
    'tr',

    html_writer::tag('td', $no++) .
    html_writer::tag('td', $namasiswa) .
    html_writer::tag('td', $d['hadir']) .
    html_writer::tag('td', $d['sakit']) .
    html_writer::tag('td', $d['ijin']) .
    html_writer::tag('td', $d['alpa']) .
    html_writer::tag('td', $d['dispensasi']) .
    html_writer::tag('td', $persen),

    ['style' => $rowstyle]
);
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

echo $OUTPUT->footer();
