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

// Tombol Kembali
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

echo $OUTPUT->heading('Rekap Kehadiran Murid Per Bulan', 2, 'mb-4');

// ================= FILTER =================

$kelaslist = $DB->get_records_menu('cohort', null, 'name ASC', 'id, name');

$kelasid  = optional_param('kelas', 0, PARAM_INT);
$bulanraw = optional_param('bulan', date('Y-m'), PARAM_TEXT);
$mode     = optional_param('mode', 'hari', PARAM_ALPHA);

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
            'ijin' => 'ijin', 'izin' => 'ijin',
            'sakit' => 'sakit', 'skt' => 'sakit',
            'alpha' => 'alpa', 'alpa' => 'alpa', 'absen' => 'alpa',
            'disp' => 'dispensasi', 'dispen' => 'dispensasi', 'dispensasi' => 'dispensasi',
            'hadir' => 'hadir'
        ];
        return $map[$s] ?? $s;
    }
}

$priority = [
    'hadir'       => 0,
    'dispensasi'  => 1,
    'sakit'       => 2,
    'ijin'        => 3,
    'alpa'        => 4,
];

// ================= FORM FILTER (DIPERBAIKIKAN UI-NYA) =================

echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'card p-3 mb-4 bg-light']);
echo html_writer::start_div('row g-3 align-items-end');

// Kelas
echo html_writer::start_div('col-md-3');
echo html_writer::tag('label', 'Pilih Kelas', ['class' => 'form-label fw-bold']);
echo html_writer::select($kelaslist, 'kelas', $kelasid ?: '', ['' => '-- Pilih Kelas --'], ['class' => 'form-select']);
echo html_writer::end_div();

// Bulan
echo html_writer::start_div('col-md-3');
echo html_writer::tag('label', 'Bulan', ['class' => 'form-label fw-bold']);
echo html_writer::empty_tag('input', ['type' => 'month', 'name' => 'bulan', 'value' => $bulanraw, 'class' => 'form-control']);
echo html_writer::end_div();

// Mode
echo html_writer::start_div('col-md-3');
echo html_writer::tag('label', 'Mode Hitung', ['class' => 'form-label fw-bold']);
echo html_writer::select(['hari' => 'Per Hari', 'jam' => 'Per Jam'], 'mode', $mode, false, ['class' => 'form-select']);
echo html_writer::end_div();

// Tombol Filter
echo html_writer::start_div('col-md-3');
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => '🔍 Tampilkan', 'class' => 'btn btn-primary w-100']);
echo html_writer::end_div();

echo html_writer::end_div(); // row
echo html_writer::end_tag('form');

// ================= PROSES DATA =================

if ($kelasid && $dari && $sampai) {

// ================= BARIS INFORMASI BULAN & MODE (OPSI 2) =================
echo html_writer::div(
    '<div class="alert alert-info d-inline-flex align-items-center gap-2 px-3 py-2 border-0 shadow-sm mb-0">' .
    '<span>📅 <strong>Bulan:</strong> ' . tanggal_indo($dari, 'bulan') . '</span>' .
    '<span class="text-muted">|</span>' .
    '<span>📊 <strong>Mode Hitung:</strong> ' . ($mode == 'hari' ? 'Per Hari' : 'Per Jam') . '</span>' .
    '</div>',
    'mb-4 d-print-none'
);

    // Get Siswa
    $members = $DB->get_records('cohort_members', ['cohortid' => $kelasid]);
    $userids = array_map(fn($m) => $m->userid, $members);

    if (empty($userids)) {
        echo $OUTPUT->notification('Tidak ada murid di kelas ini.', 'notifyproblem');
        echo $OUTPUT->footer();
        exit;
    }

    list($in_sql, $paramsin) = $DB->get_in_or_equal($userids);
    $users = $DB->get_records_sql("
        SELECT id, firstname, lastname FROM {user} WHERE id $in_sql ORDER BY lastname ASC
    ", $paramsin);

    // Get Jurnal
    $params = ['kelas' => $kelasid, 'dari' => $dari, 'sampai' => $sampai];
    $jurnals = $DB->get_records_select('local_jurnalmengajar', 'kelas = :kelas AND timecreated BETWEEN :dari AND :sampai', $params);

    // Init Data Penampung
    $data = [];
    foreach ($users as $u) {
        $data[$u->id] = ['hadir' => 0, 'sakit' => 0, 'ijin' => 0, 'alpa' => 0, 'dispensasi' => 0];
    }

    if ($mode == 'hari') {
        $perhari = [];
        $all_dates = [];

        foreach ($jurnals as $jurnal) {
            $tgl = date('Y-m-d', $jurnal->timecreated);
            $all_dates[$tgl] = true;

            $jamke = array_filter(array_map('trim', explode(',', (string)($jurnal->jamke ?? ''))));
            $jmljam = count($jamke) ?: 1;

            $absen = json_decode($jurnal->absen, true) ?? [];
            $lookup = [];
            foreach ($absen as $nama => $alasan) {
                $lookup[mb_strtolower(trim($nama), 'UTF-8')] = normalize_status($alasan);
            }

            foreach ($users as $uid => $u) {
                $namasiswa = mb_strtolower(trim($u->lastname), 'UTF-8');

                if (!isset($perhari[$uid][$tgl])) {
                    $perhari[$uid][$tgl] = ['hadir' => 0, 'sakit' => 0, 'ijin' => 0, 'alpa' => 0, 'dispensasi' => 0];
                }

                $status = isset($lookup[$namasiswa]) ? $lookup[$namasiswa] : 'hadir';
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
                    // KOREKSI LOGIKA DI SINI: Default dialihkan ke 'alpa' agar aman jika fallback
                    $statushari = 'alpa'; 
                    $maxprio = -1;

                    foreach (['dispensasi', 'sakit', 'ijin', 'alpa'] as $st) {
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
        // MODE JAM
        foreach ($jurnals as $jurnal) {
            $jamke = array_filter(array_map('trim', explode(',', (string)($jurnal->jamke ?? ''))));
            $jmljam = count($jamke);

            $absen = json_decode($jurnal->absen, true) ?? [];

            foreach ($users as $uid => $u) {
                $namasiswa = trim($u->lastname);
                $found = false;

                foreach ($absen as $nama => $alasan) {
                    if (strcasecmp(trim($nama), $namasiswa) == 0) {
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
            $total_unit = max($total_unit, array_sum($d));
        }
        $unit_label = 'jam';
    }

    // ================= IMPLEMENTASI TABEL UI TERBARU =================
    
    echo html_writer::tag('style', '
        .table-rekap th { text-align: center; vertical-align: middle; background-color: #f8f9fa; font-weight: bold; }
        .table-rekap td { vertical-align: middle; }
        .text-center-val { text-align: center; }
        @media print { .d-print-none { display: none !important; } }
    ');

    echo html_writer::start_tag('table', ['class' => 'table table-bordered table-hover table-striped table-rekap shadow-sm']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'No', ['style' => 'width: 5%;']);
    echo html_writer::tag('th', 'Nama Murid', ['style' => 'width: 35%; text-align: left;']);
    echo html_writer::tag('th', 'Hadir', ['style' => 'width: 8%;']);
    echo html_writer::tag('th', 'Sakit', ['style' => 'width: 8%;']);
    echo html_writer::tag('th', 'Ijin', ['style' => 'width: 8%;']);
    echo html_writer::tag('th', 'Alpa', ['style' => 'width: 8%;']);
    echo html_writer::tag('th', 'Dispensasi', ['style' => 'width: 10%;']);
    echo html_writer::tag('th', 'Persentase Kehadiran', ['style' => 'width: 18%;']);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');

    echo html_writer::start_tag('tbody');

// ================= ISI TABEL (FOREACH) =================
    $no = 1;
    foreach ($data as $uid => $d) {
        if ($total_unit > 0) {
            $p = ($d['hadir'] / $total_unit) * 100;
            
            // Format tampilan: Persentase di baris atas, dan info total di baris bawah
            $persen = '<strong>' . number_format($p, 1, ',', '') . '%</strong>' .
                      '<br><small class="text-muted">dari ' . $total_unit . ' ' . $unit_label . '</small>';
        } else {
            $persen = '-';
            $p = 100;
        }

        $namasiswa = ucwords(strtolower($users[$uid]->lastname));

        $rowclass = '';
        if ($total_unit > 0 && $p < 80) {
            $rowclass = 'table-danger'; 
        }

        // Penataan badge angka ketidakhadiran
        $sakit = $d['sakit'] > 0 ? html_writer::span($d['sakit'], 'badge bg-warning text-dark px-2 py-1') : '<span class="text-muted">0</span>';
        $ijin  = $d['ijin'] > 0 ? html_writer::span($d['ijin'], 'badge bg-info text-dark px-2 py-1') : '<span class="text-muted">0</span>';
        $alpa  = $d['alpa'] > 0 ? html_writer::span($d['alpa'], 'badge bg-danger px-2 py-1') : '<span class="text-muted">0</span>';
        $disp  = $d['dispensasi'] > 0 ? html_writer::span($d['dispensasi'], 'badge bg-secondary px-2 py-1') : '<span class="text-muted">0</span>';

        echo html_writer::start_tag('tr', ['class' => $rowclass]);
        echo html_writer::tag('td', $no++, ['class' => 'text-center-val']);
        echo html_writer::tag('td', $namasiswa);
        echo html_writer::tag('td', $d['hadir'], ['class' => 'text-center-val fw-bold text-success']);
        echo html_writer::tag('td', $sakit, ['class' => 'text-center-val']);
        echo html_writer::tag('td', $ijin, ['class' => 'text-center-val']);
        echo html_writer::tag('td', $alpa, ['class' => 'text-center-val']);
        echo html_writer::tag('td', $disp, ['class' => 'text-center-val']);
        echo html_writer::tag('td', $persen, ['class' => 'text-center-val']); // Variabel persen yang sudah diperbarui
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');

    // Tombol Print Aksi
    echo html_writer::div(
        html_writer::empty_tag('input', [
            'type'    => 'button',
            'value'   => '🖨️ Cetak Rekapitulasi',
            'class'   => 'btn btn-success mt-3 d-print-none text-white',
            'onclick' => 'window.print();'
        ]),
        'text-end'
    );
}

echo $OUTPUT->footer();
