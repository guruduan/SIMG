<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot.'/local/jurnalmengajar/lib.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_kehadiran.php'));
$PAGE->set_title('Rekap Kehadiran Murid');
$PAGE->set_heading('Rekap Kehadiran Murid');
$PAGE->requires->jquery();

echo $OUTPUT->header();

// --- Tombol Aksi Atas ---
$tombol_kembali = html_writer::link(
    '#',
    '⬅ Kembali',
    [
        'class' => 'btn btn-secondary me-2', 
        'onclick' => 'history.back(); return false;'
    ]
);

$tombol_bulanan = html_writer::link(
    new moodle_url('/local/jurnalmengajar/rekap_kehadiran_bulanan.php'),
    '📅 Rekap Kehadiran Bulanan',
    ['class' => 'btn btn-info']
);

echo html_writer::div($tombol_kembali . $tombol_bulanan, 'mb-4');
echo $OUTPUT->heading('Rekap Kehadiran Murid Per Kelas', 3, 'mb-4');

// Ambil daftar kelas
$kelaslist = $DB->get_records_menu('cohort', null, 'name ASC', 'id, name');

// Ambil parameter
$kelasid     = optional_param('kelas', 0, PARAM_INT);
$dari_raw    = optional_param('dari', '', PARAM_RAW);
$sampai_raw  = optional_param('sampai', '', PARAM_RAW);
$mode        = optional_param('mode', 'hari', PARAM_ALPHA); 
$onlymine    = optional_param('onlymine', 0, PARAM_BOOL);
$matpel      = optional_param('matpel', '', PARAM_TEXT);

$dari   = $dari_raw   ? strtotime($dari_raw . ' 00:00:00') : 0;
$sampai = $sampai_raw ? strtotime($sampai_raw . ' 23:59:59') : 0;

$priority = [
    'hadir'       => 0,
    'dispensasi'  => 1,
    'sakit'       => 2,
    'ijin'        => 3,
    'alpa'        => 4,
];

// ===== FORM FILTER RESPONSIVE (BOOTSTRAP GRID) =====
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'card card-body bg-light mb-4']);

echo html_writer::start_div('row g-3 align-items_end mb-3');

// Baris 1: Kelas & Mode Hitung
echo html_writer::start_div('col-md-6');
echo html_writer::tag('label', 'Pilih Kelas:', ['for' => 'kelas', 'class' => 'form-label fw-bold']);
echo html_writer::select($kelaslist, 'kelas', $kelasid ?: '', ['' => '-- Pilih Kelas --'], ['class' => 'form-control form-select', 'id' => 'kelas']);
echo html_writer::end_div();

echo html_writer::start_div('col-md-6');
echo html_writer::tag('label', 'Mode Hitung:', ['for' => 'mode', 'class' => 'form-label fw-bold']);
$optionsmode = ['hari' => 'Per Hari', 'jam' => 'Per Jam'];
echo html_writer::select($optionsmode, 'mode', in_array($mode, ['hari','jam']) ? $mode : 'hari', false, ['class' => 'form-control form-select', 'id' => 'mode']);
echo html_writer::end_div();

echo html_writer::end_div(); // End Row 1


echo html_writer::start_div('row g-3 align-items-end mb-3');

// Baris 2: Rentang Tanggal
echo html_writer::start_div('col-md-6');
echo html_writer::tag('label', 'Dari Tanggal:', ['for' => 'dari', 'class' => 'form-label fw-bold']);
echo html_writer::empty_tag('input', ['type' => 'date', 'name' => 'dari', 'id' => 'dari', 'value' => s($dari_raw), 'class' => 'form-control', 'required' => 'required']);
echo html_writer::end_div();

echo html_writer::start_div('col-md-6');
echo html_writer::tag('label', 'Sampai Tanggal:', ['for' => 'sampai', 'class' => 'form-label fw-bold']);
echo html_writer::empty_tag('input', ['type' => 'date', 'name' => 'sampai', 'id' => 'sampai', 'value' => s($sampai_raw), 'class' => 'form-control', 'required' => 'required']);
echo html_writer::end_div();

echo html_writer::end_div(); // End Row 2


echo html_writer::start_div('row g-3 align-items-center');

// Baris 3: Mata Pelajaran & Checkbox & Tombol
echo html_writer::start_div('col-md-5');
echo html_writer::tag('label', 'Mata Pelajaran:', ['for' => 'matpel', 'class' => 'form-label fw-bold']);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'matpel', 'id' => 'matpel', 'value' => s($matpel), 'class' => 'form-control', 'placeholder' => 'Semua mata pelajaran']);
echo html_writer::end_div();

echo html_writer::start_div('col-md-4 pt-4'); // pt-4 mendorong agar sejajar input text
echo html_writer::start_div('form-check py-2');
echo html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'onlymine', 'id' => 'onlymine', 'value' => 1, 'class' => 'form-check-input', 'checked' => $onlymine ? 'checked' : null]);
echo html_writer::tag('label', 'Hanya Jurnal Saya', ['for' => 'onlymine', 'class' => 'form-check-label fw-bold']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('col-md-3 pt-4 text-end');
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => '🔍 Tampilkan', 'class' => 'btn btn-primary w-100']);
echo html_writer::end_div();

echo html_writer::end_div(); // End Row 3

echo html_writer::end_tag('form');


// ===== RINGKASAN FILTER AKTIF =====
if ($kelasid && $dari && $sampai) {
    echo html_writer::start_div('alert alert-info py-2 px-3 mb-4 d-flex flex-wrap gap-3 align-items-center justify-content-between');
    echo '<div>';
    echo '<strong>Rentang:</strong> ' . tanggal_indo($dari, 'tanggal') . ' s/d ' . tanggal_indo($sampai, 'tanggal') . ' | ';
    echo '<strong>Mode:</strong> ' . ($mode === 'hari' ? 'Per Hari' : 'Per Jam');
    if ($onlymine || $matpel !== '') {
        $badge = [];
        if ($onlymine) { $badge[] = 'Internal (Saya)'; }
        if ($matpel !== '') { $badge[] = 'Mapel: '.s($matpel); }
        echo ' | <span class="badge bg-secondary">' . implode('</span> <span class="badge bg-secondary">', $badge) . '</span>';
    }
    echo '</div>';
    echo html_writer::end_div();
}

// ===== PROSES DATA & TABEL =====
if ($kelasid && $dari && $sampai) {
    $members = $DB->get_records('cohort_members', ['cohortid' => $kelasid]);
    $userids = array_map(fn($m) => $m->userid, $members);

    if (empty($userids)) {
        echo html_writer::div('Tidak ada murid dalam kelas ini.', 'alert alert-warning text-center fw-bold mt-3');
        echo $OUTPUT->footer();
        exit;
    }

    list($in_sql, $paramsin) = $DB->get_in_or_equal($userids);
    $users = $DB->get_records_sql("
        SELECT id, firstname, lastname
        FROM {user}
        WHERE id $in_sql
        ORDER BY lastname ASC, firstname ASC
    ", $paramsin);

    $params = ['kelas' => $kelasid, 'dari' => $dari, 'sampai' => $sampai];
    $wheres = ['kelas = :kelas', 'timecreated BETWEEN :dari AND :sampai'];

    if ($onlymine) {
        global $USER;
        $wheres[] = 'userid = :uid';
        $params['uid'] = $USER->id;
    }
    if ($matpel !== '') {
        $wheres[] = 'matapelajaran = :matpel';
        $params['matpel'] = $matpel;
    }

    $selectsql = implode(' AND ', $wheres);
    $jurnals = $DB->get_records_select('local_jurnalmengajar', $selectsql, $params);

    $data = [];
    foreach ($users as $u) {
        $data[$u->id] = ['hadir' => 0, 'sakit' => 0, 'ijin' => 0, 'alpa' => 0, 'dispensasi' => 0];
    }

    if ($mode === 'hari') {
        $perhari = [];
        $all_dates = [];

        foreach ($jurnals as $jurnal) {
            $tgl = date('Y-m-d', $jurnal->timecreated);
            $all_dates[$tgl] = true;

            $jamke  = array_filter(array_map('trim', explode(',', (string)($jurnal->jamke ?? ''))));
            $jmljam = count($jamke) ?: 1;

            $absen = json_decode($jurnal->absen, true) ?? [];
            $lookup = [];
            foreach ($absen as $nama => $alasan) {
                $lookup[mb_strtolower(trim($nama), 'UTF-8')] = strtolower(trim($alasan));
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
                if (empty($perhari[$uid][$tgl])) { continue; }

                $h = $perhari[$uid][$tgl]['hadir'];
                $tot = array_sum($perhari[$uid][$tgl]);
                if ($tot == 0) { continue; }

                $nonhadir = $tot - $h;

                if ($nonhadir == 0) {
                    $statushari = 'hadir';
                } else if ($h == 0) {
                    $statushari = 'hadir';
                    $maxprio = -1;
                    foreach (['dispensasi','sakit','ijin','alpa'] as $st) {
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
        foreach ($jurnals as $jurnal) {
            $jamke  = array_filter(array_map('trim', explode(',', (string)($jurnal->jamke ?? ''))));
            $jmljam = count($jamke);
            $absen  = json_decode($jurnal->absen, true) ?? [];

            foreach ($users as $uid => $u) {
                $namasiswa = trim($u->lastname);
                $found = false;

                foreach ($absen as $nama => $alasan) {
                    if (strcasecmp(trim($nama), $namasiswa) == 0) {
                        $alasan = strtolower(trim($alasan));
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
            $total_unit = max($total_unit, $d['hadir'] + $d['sakit'] + $d['ijin'] + $d['alpa'] + $d['dispensasi']);
        }
        $unit_label = 'jam';
    }

    // ====== DESAIN TABEL TERBARU ======
    echo html_writer::start_div('table-responsive card mb-4');
    echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover m-0 table-bordered align-middle']);
    echo html_writer::start_tag('thead', ['class' => 'table-dark text-center']);
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'No', ['style' => 'width: 50px;']);
    echo html_writer::tag('th', 'Nama Murid', ['class' => 'text-start']);
    echo html_writer::tag('th', 'Hadir', ['style' => 'width: 90px;']);
    echo html_writer::tag('th', 'Sakit', ['style' => 'width: 90px;']);
    echo html_writer::tag('th', 'Ijin', ['style' => 'width: 90px;']);
    echo html_writer::tag('th', 'Alpa', ['style' => 'width: 90px;']);
    echo html_writer::tag('th', 'Dispensasi', ['style' => 'width: 100px;']);
    echo html_writer::tag('th', 'Persentase');
    echo html_writer::tag('th', 'Aksi', ['style' => 'width: 220px;']);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    $no = 1;
    foreach ($data as $uid => $d) {
        $total = $total_unit;
        if ($total > 0) {
            $p = ($d['hadir'] / $total) * 100;
            $p1 = round($p, 1);
            $is_int = abs($p1 - round($p1)) < 0.00001;
            $pstr = $is_int ? (string)round($p1) : number_format($p1, 1, ',', '');
            
            // Pewarnaan teks persentase biar stand-out
            $text_color = ($p1 >= 85) ? 'text-success fw-bold' : (($p1 >= 75) ? 'text-warning fw-bold' : 'text-danger fw-bold');
            $persen = '<span class="'.$text_color.'">' . $pstr . '%</span> <small class="text-muted">dari ' . $total . ' ' . $unit_label . '</small>';
        } else {
            $persen = '<span class="text-muted">-</span>';
        }

        $namasiswa = ucwords(strtolower($users[$uid]->lastname));

        $link = new moodle_url('/local/jurnalmengajar/rekap_permurid.php', [
            'siswa'  => $uid,
            'kelas'  => $kelasid,
            'dari'   => date('Y-m-d', $dari),
            'sampai' => date('Y-m-d', $sampai),
            'mode'   => $mode,
            'onlymine' => $onlymine ? 1 : 0,
            'matpel'   => $matpel
        ]);
        
        $aksi = html_writer::link($link, '🔍 Lihat Detail', ['class' => 'btn btn-outline-primary btn-sm rounded-pill px-3']);

        echo html_writer::start_tag('tr', ['class' => 'text-center']);
        echo html_writer::tag('td', $no++);
        echo html_writer::tag('td', $namasiswa, ['class' => 'text-start fw-bold']);
        echo html_writer::tag('td', '<span class="badge bg-success-light text-success fw-bold">' . $d['hadir'] . '</span>');
        echo html_writer::tag('td', $d['sakit'] ?: '<span class="text-muted">0</span>');
        echo html_writer::tag('td', $d['ijin'] ?: '<span class="text-muted">0</span>');
        echo html_writer::tag('td', $d['alpa'] ? '<span class="text-danger fw-bold">' . $d['alpa'] . '</span>' : '<span class="text-muted">0</span>');
        echo html_writer::tag('td', $d['dispensasi'] ?: '<span class="text-muted">0</span>');
        echo html_writer::tag('td', $persen);
        echo html_writer::tag('td', $aksi);
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div(); // End Table Responsive

    // ====== TOMBOL EKSPOR BAWAH ======
    if (!empty($data)) {
        $exportbase = new moodle_url('/local/jurnalmengajar/rekap_kehadiran_export.php', [
            'kelas'  => $kelasid,
            'dari'   => date('Y-m-d', $dari),
            'sampai' => date('Y-m-d', $sampai),
            'mode'   => $mode,
            'onlymine' => $onlymine ? 1 : 0,
            'matpel'   => $matpel
        ]);
        echo html_writer::start_div('d-flex gap-2 justify-content-start mb-4 shadow-sm p-3 bg-white rounded border');
        echo html_writer::link(new moodle_url($exportbase, ['format' => 'xlsx']), '📥 Ekspor ke XLSX', ['class' => 'btn btn-success px-4 fw-bold']);
        echo html_writer::link(new moodle_url($exportbase, ['format' => 'ods']), '📥 Ekspor ke ODS', ['class' => 'btn btn-outline-success px-4']);
        echo html_writer::end_div();
    }
}

echo $OUTPUT->footer();
