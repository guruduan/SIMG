<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);
global $DB, $PAGE, $OUTPUT;

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_permurid.php'));
$PAGE->requires->jquery();

$kelasid     = required_param('kelas', PARAM_INT);
$siswaid     = required_param('siswa', PARAM_INT);
$dariParam   = required_param('dari', PARAM_RAW);
$sampaiParam = required_param('sampai', PARAM_RAW);
$mode        = optional_param('mode', 'jam', PARAM_ALPHA); // 'jam' | 'hari'
$onlymine    = optional_param('onlymine', 0, PARAM_BOOL);
$matpel      = optional_param('matpel', '', PARAM_TEXT);

$dari = strtotime($dariParam) ?: time();
$sampai = (strtotime($sampaiParam) ?: time()) + 86399;

// Ambil nama kelas
$kelas = $DB->get_record('cohort', ['id' => $kelasid], 'id, name');
$namakelas = $kelas ? $kelas->name : '(kelas tidak ditemukan)';

$PAGE->set_title("Rekap Kehadiran Murid [$namakelas]");
$PAGE->set_heading("Rekap Kehadiran Murid [$namakelas]");

// Semakin besar nilainya -> semakin dominan jika bentrok dalam satu hari
$priority = [
    'hadir'       => 0,
    'dispensasi'  => 1,
    'sakit'       => 2,
    'ijin'        => 3,
    'alpa'        => 4,
];

// ==== Header ====
echo $OUTPUT->header();
echo $OUTPUT->heading("Rekap Kehadiran Murid [$namakelas]");

// Tombol kembali & cetak (ikut bawa mode + filter)
// Kembali ke rekap per kelas
$back_kelas = new moodle_url('/local/jurnalmengajar/rekap_kehadiran.php', [
    'kelas'    => $kelasid,
    'dari'     => $dariParam,
    'sampai'   => $sampaiParam,
    'mode'     => $mode,
    'onlymine' => $onlymine ? 1 : 0,
    'matpel'   => $matpel
]);

// Kembali ke rekap murid binaan (guru wali)
$back_wali = new moodle_url('/local/jurnalmengajar/rekap_kehadiran_muridwali.php', [
    'dari'   => $dariParam,
    'sampai'=> $sampaiParam,
    'mode'  => $mode
]);

$cetakurl = new moodle_url('/local/jurnalmengajar/cetak_permurid.php', [
    'kelas'    => $kelasid,
    'siswa'    => $siswaid,
    'dari'     => $dariParam,
    'sampai'   => $sampaiParam,
    'mode'     => $mode,
    'onlymine' => $onlymine ? 1 : 0,
    'matpel'   => $matpel
]);

echo html_writer::start_div('mb-3 d-flex gap-2');
echo html_writer::link(
    $back_kelas,
    '← Kembali ke Rekap Kelas',
    ['class' => 'btn btn-secondary']
);
echo html_writer::link(
    $back_wali,
    '👥 Kembali ke Murid Binaan',
    ['class' => 'btn btn-outline-secondary']
);
echo html_writer::link($cetakurl, '🖨️ Cetak ke PDF', ['class' => 'btn btn-danger', 'target' => '_blank']);
echo html_writer::end_div();

// Ambil data siswa
$siswa = $DB->get_record('user', ['id' => $siswaid], 'id, firstname, lastname');
if (!$siswa) {
    echo 'Siswa tidak ditemukan.';
    echo $OUTPUT->footer();
    exit;
}
$namasiswa = ucwords(strtolower($siswa->lastname));

// Info siswa & rentang + mode + filter
echo html_writer::tag('h3', "Siswa: {$namasiswa}");
$rentangTanggal = tanggal_indo($dari, 'judul') . ' sampai ' . tanggal_indo($sampai, 'judul');
echo html_writer::tag('p', "Rentang Tanggal: $rentangTanggal", ['class' => 'mb-1 fw-bold']);
$badges = ["Mode: " . ($mode === 'hari' ? 'Per Hari' : 'Per Jam (jamke)')];
if ($onlymine) { $badges[] = 'Hanya jurnal saya'; }
if ($matpel !== '') { $badges[] = 'Matpel: ' . s($matpel); }
echo html_writer::tag('p', implode(' | ', $badges), ['class' => 'mb-3']);

// Ambil jurnal kelas dalam rentang (hormati filter)
$params = ['kelas' => $kelasid, 'dari' => $dari, 'sampai' => $sampai];
$wheres = ['kelas = :kelas', 'timecreated BETWEEN :dari AND :sampai'];

if ($onlymine) {
    global $USER;
    $wheres[] = 'userid = :uid';
    $params['uid'] = $USER->id;
}
if ($matpel !== '') {
    // exact match; jika mau LIKE, ganti dua baris di bawah
    $wheres[] = 'matapelajaran = :matpel';
    $params['matpel'] = $matpel;
    // LIKE alternatif:
    // $wheres[] = $DB->sql_like('matapelajaran', ':matpel', false, false);
    // $params['matpel'] = "%{$matpel}%";
}

$select  = implode(' AND ', $wheres);
$jurnals = $DB->get_records_select('local_jurnalmengajar', $select, $params, 'timecreated ASC');
$gurucache = [];
// ==============================
// TABEL (beda sesuai mode)
// ==============================
if ($mode === 'hari') {
    // ---------- MODE PER HARI ----------
    // Kumpulkan status per tanggal untuk siswa ini, terapkan prioritas bila bentrok.
    $per_tanggal = [];   // 'Y-m-d' => ['status' => ..., 'rincian' => [..]]
    foreach ($jurnals as $j) {
        $tglKey = date('Y-m-d', $j->timecreated);
        $absen = json_decode($j->absen, true);
if (!is_array($absen)) {
    $absen = [];
}
        $statusJurnal = null;

        foreach ($absen as $nama => $als) {
            if (strcasecmp(trim($nama), trim($siswa->lastname)) == 0) {
                $statusJurnal = strtolower(trim($als));
                break;
            }
        }

        // Inisialisasi container hari
        if (!isset($per_tanggal[$tglKey])) {
            $per_tanggal[$tglKey] = [
                'status_count' => [],   // hitung jam per status
                'rincian' => []
            ];
        }

        // Rincian
        if (!isset($gurucache[$j->userid])) {
    $gurucache[$j->userid] = $DB->get_record(
        'user',
        ['id' => $j->userid],
        'firstname, lastname'
    );
}

$guru = $gurucache[$j->userid];
        $per_tanggal[$tglKey]['rincian'][] = [
            'jamke'  => $j->jamke ?? '-',
            'mapel'  => $j->matapelajaran ?? '-',
            'guru'   => $guru ? $guru->lastname : '(tidak diketahui)'
        ];

// Status dominan per hari
$jamlist = array_filter(array_map('trim', explode(',', $j->jamke ?? '')));
$jumlahjam = count($jamlist) ?: 1;

if ($statusJurnal && isset($priority[$statusJurnal])) {

    if (!isset($per_tanggal[$tglKey]['status_count'][$statusJurnal])) {
        $per_tanggal[$tglKey]['status_count'][$statusJurnal] = 0;
    }

    $per_tanggal[$tglKey]['status_count'][$statusJurnal] += $jumlahjam;

} else {

    // default hadir jika tidak ada di JSON
    if (!isset($per_tanggal[$tglKey]['status_count']['hadir'])) {
        $per_tanggal[$tglKey]['status_count']['hadir'] = 0;
    }

    $per_tanggal[$tglKey]['status_count']['hadir'] += $jumlahjam;
}

}

foreach ($per_tanggal as $tgl => &$info) {

    if (empty($info['status_count'])) {
        $info['status'] = 'hadir';
        continue;
    }

    $hadir = $info['status_count']['hadir'] ?? 0;
    $total = array_sum($info['status_count']);

    if ($total == 0) {
        $info['status'] = 'hadir';
    } else if ($hadir == $total) {
        $info['status'] = 'hadir';
    } else if ($hadir == 0) {
        $statusDominan = 'hadir';
        $maxprio = -1;
        foreach (['dispensasi','sakit','ijin','alpa'] as $st) {
            if (!empty($info['status_count'][$st])) {
                $p = $priority[$st] ?? 0;
                if ($p > $maxprio) {
                    $maxprio = $p;
                    $statusDominan = $st;
                }
            }
        }
        $info['status'] = $statusDominan;
    } else {
        $info['status'] = 'hadir';
    }
}
unset($info); // ✅ TARUH DI SINI (di luar loop)
    // Render tabel: satu baris per tanggal (urut naik)
    ksort($per_tanggal);

    echo html_writer::start_tag('table', ['class' => 'generaltable']);
    echo html_writer::tag('tr',
        html_writer::tag('th', 'No') .
        html_writer::tag('th', 'Hari, tanggal') .
        html_writer::tag('th', 'Absensi (dominan)') .
        html_writer::tag('th', 'Rincian perhari')
    );

    $no = 1;
    $hari_tidak_hadir = 0;
    foreach ($per_tanggal as $tglKey => $info) {
        $tanggalDisplay = tanggal_indo(strtotime($tglKey), 'judul');
        $st = $info['status'];

        if ($st !== 'hadir') { $hari_tidak_hadir++; }

        $chunks = [];
        foreach ($info['rincian'] as $r) {
            $chunks[] = '[' . ($r['jamke'] ?: '-') . '] ' . ($r['mapel'] ?: '-') . ' (' . $r['guru'] . ')';
        }
        $rincian = $chunks ? implode('; ', $chunks) : '-';

        echo html_writer::tag('tr',
            html_writer::tag('td', $no++) .
            html_writer::tag('td', $tanggalDisplay) .
            html_writer::tag('td', ucfirst($st)) .
            html_writer::tag('td', $rincian)
        );
    }
    echo html_writer::end_tag('table');

    echo html_writer::tag('p', '<strong>Jumlah Hari Murid tidak hadir: ' . $hari_tidak_hadir . ' hari</strong>', ['class' => 'mt-3']);

} else {
    // ---------- MODE PER JAM (lama) ----------
    echo html_writer::start_tag('table', ['class' => 'generaltable']);
    echo html_writer::tag('tr',
        html_writer::tag('th', 'No') .
        html_writer::tag('th', 'Hari, tanggal') .
        html_writer::tag('th', 'Jam ke') .
        html_writer::tag('th', 'Mata Pelajaran') .
        html_writer::tag('th', 'Pengajar') .
        html_writer::tag('th', 'Absensi')
    );

    $no = 1;
    $totaljam = 0;

$data_perhari = [];

foreach ($jurnals as $jurnal) {

    $tanggalkey = date('Y-m-d', $jurnal->timecreated);
    $tanggal = tanggal_indo($jurnal->timecreated, 'judul');

    $absen = json_decode($jurnal->absen, true);

    if (!is_array($absen)) {
        $absen = [];
    }

    $jamke = $jurnal->jamke ?? '-';
    $matpelj = $jurnal->matapelajaran ?? '-';

    $alasan = null;

    foreach ($absen as $nama => $als) {
        if (strcasecmp(trim($nama), trim($siswa->lastname)) == 0) {
            $alasan = strtolower(trim($als));
            break;
        }
    }

    // tampilkan absensi
    if (!$alasan) {
    $alasan = 'hadir';
    }

$jamlist = array_filter(array_map('trim', explode(',', $jamke)));

if ($alasan !== 'hadir') {
    $totaljam += count($jamlist);
}

        if (!isset($gurucache[$jurnal->userid])) {
            $gurucache[$jurnal->userid] = $DB->get_record(
                'user',
                ['id' => $jurnal->userid],
                'firstname, lastname'
            );
        }

        $guru = $gurucache[$jurnal->userid];
        $namaguru = $guru ? $guru->lastname : '(tidak diketahui)';

        $data_perhari[$tanggalkey]['tanggal'] = $tanggal;

        $data_perhari[$tanggalkey]['rows'][] = [
            'jamke' => $jamke,
            'mapel' => $matpelj,
            'guru'  => $namaguru,
            'absen' => ucfirst($alasan)
        ];
    
}

$no = 1;
ksort($data_perhari);
foreach ($data_perhari as $hari) {

    $first = true;

    foreach ($hari['rows'] as $row) {
$statuslower = strtolower($row['absen']);

switch ($statuslower) {
    case 'hadir':
        $badgeclass = 'success';
        break;

    case 'sakit':
        $badgeclass = 'warning';
        break;

    case 'ijin':
        $badgeclass = 'info';
        break;

    case 'alpa':
        $badgeclass = 'danger';
        break;

    case 'dispensasi':
        $badgeclass = 'secondary';
        break;

    default:
        $badgeclass = 'light';
        break;
}

$badge = html_writer::tag(
    'span',
    $row['absen'],
    ['class' => 'badge bg-' . $badgeclass]
);
$rowhtml =
    html_writer::tag('td', $first ? $no : '') .
    html_writer::tag('td', $first ? $hari['tanggal'] : '') .
    html_writer::tag('td', $row['jamke']) .
    html_writer::tag('td', $row['mapel']) .
    html_writer::tag('td', $row['guru']) .
    html_writer::tag('td', $badge);

echo html_writer::tag('tr', $rowhtml);

        $first = false;
    }

    $no++;
}

    echo html_writer::end_tag('table');

    echo html_writer::tag('p', '<strong>Jumlah Jam Murid tidak hadir: ' . $totaljam . ' jam</strong>', ['class' => 'mt-3']);
}

echo $OUTPUT->footer();
