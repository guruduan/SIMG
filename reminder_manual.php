<?php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/jam_pelajaran_lib.php');
require_once(__DIR__ . '/jadwal_acuan_lib.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/lib_notifikasi.php');

// Konfigurasi Halaman Moodle
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/reminder_manual.php'));
$PAGE->set_title('Reminder Manual Jurnal');
$PAGE->set_heading('Reminder Manual Jurnal Mengajar');

echo $OUTPUT->header();

// Fungsi bantuan untuk menghentikan proses dengan tampilan UI Moodle
function jm_berhenti_dengan_pesan($pesan, $tipe = 'info') {
    global $OUTPUT;
    echo $OUTPUT->notification($pesan, $tipe);
    echo $OUTPUT->footer();
    exit;
}

global $DB;

$cohortmap = [];
$cohorts = $DB->get_records('cohort', null, '', 'id,name');
foreach ($cohorts as $c) {
    $cohortmap[$c->id] = $c->name;
}

$today = date('Y-m-d');
$hariIndo = jurnalmengajar_get_hari_ini();
$current = time();
$todayLabel = tanggal_indo(time());

// ===== Cek hari sekolah =====
$hariSekolah = get_config('local_jurnalmengajar', 'harisekolah');
if (empty($hariSekolah)) {
    $hariSekolah = 'Senin,Selasa,Rabu,Kamis,Jumat';
}
$hariSekolah = array_map('trim', explode(',', $hariSekolah));

if (!in_array($hariIndo, $hariSekolah)) {
    jm_berhenti_dengan_pesan("Hari <strong>$hariIndo</strong> bukan hari sekolah.");
}

// ===== Cek tanggal libur =====
if (jurnalmengajar_cek_libur($today)) {
    jm_berhenti_dengan_pesan("Hari ini tercatat sebagai <strong>Tanggal Libur</strong>.");
}

// ===== Cek tanggal asesmen =====
$tanggalasesmen = trim(get_config('local_jurnalmengajar', 'tanggalasesmen'));
if (!empty($tanggalasesmen)) {
    if (preg_match('/(\d{4}-\d{2}-\d{2})\s*s\/d\s*(\d{4}-\d{2}-\d{2})/i', $tanggalasesmen, $match)) {
        $mulai  = strtotime($match[1]);
        $selesai = strtotime($match[2]);
        $hariini = strtotime($today);

        if ($hariini >= $mulai && $hariini <= $selesai) {
            jm_berhenti_dengan_pesan("Hari ini berada dalam rentang <strong>Asesmen/Ujian</strong>. KBM ditiadakan.");
        }
    }
}

// ===== Ambil jam pelajaran =====
$jam_pelajaran = jurnalmengajar_generate_jam();

// ===== Tentukan jam yang sudah selesai =====
$jam_terlewat = [];
foreach ($jam_pelajaran as $jamke => $jam) {
    $selesai = $jam['selesai'];
    if ($current > strtotime("$today $selesai")) {
        $jam_terlewat[] = $jamke;
    }
}

if (empty($jam_terlewat)) {
    jm_berhenti_dengan_pesan("Belum ada jam pelajaran yang selesai / terlewat hari ini.");
}

echo html_writer::div('Jam yang sudah lewat: <strong>' . implode(', ', $jam_terlewat) . '</strong>', 'alert alert-info mb-4');

// ===== Ambil jurnal hari ini =====
$starttoday = strtotime("$today 00:00:00");
$endtoday   = strtotime("$today 23:59:59");

$jurnaltoday = $DB->get_records_sql("
    SELECT id, userid, kelas, jamke
    FROM {local_jurnalmengajar}
    WHERE timecreated BETWEEN :starttoday AND :endtoday
", ['starttoday' => $starttoday, 'endtoday' => $endtoday]);

$filled = [];
foreach ($jurnaltoday as $row) {
    foreach (explode(',', $row->jamke) as $j) {
        $j = (int)trim($j);
        $kelas = $row->kelas;
        if (isset($cohortmap[$kelas])) {
            $kelas = $cohortmap[$kelas];
        }
        $key = $row->userid . '-' . $kelas . '-' . $j;
        $filled[$key] = true;
    }
}

// ===== Ambil jadwal dari database =====
$jadwal_db = $DB->get_records_sql("
    SELECT j.id, j.userid, j.kelas, j.jamke, u.lastname
    FROM {local_jurnalmengajar_jadwal} j
    JOIN {user} u ON u.id = j.userid
    WHERE j.hari = :hari
", ['hari' => $hariIndo]);

$jadwal = [];
foreach ($jadwal_db as $j) {
    $jadwal[] = [
        'userid'   => $j->userid,
        'lastname' => $j->lastname,
        'kelas'    => $j->kelas,
        'jamke'    => $j->jamke
    ];
}

if (empty($jadwal)) {
    jm_berhenti_dengan_pesan("Tidak ada jadwal KBM di database untuk hari $hariIndo.", 'warning');
}

// ===== Group jurnal yang belum diisi =====
$pending = [];
$tidakhadir = [];
$cutoff_cache = [];

foreach ($jadwal as $j) {
    // Filter cutoff multi kelas
    $kelas_level = null;
    if (preg_match('/\b(VI|IX|XII)\b/i', $j['kelas'], $match)) {
        $kelas_level = strtoupper($match[1]);
    }

    if ($kelas_level) {
        if (!isset($cutoff_cache[$kelas_level])) {
            $cutoff_cache[$kelas_level] = jurnalmengajar_get_cutoff_by_kelas($kelas_level, $current);
        }
        $cutoff = $cutoff_cache[$kelas_level];
        if ($cutoff && $current >= $cutoff) {
            continue;
        }
    }

    // Filter KBM Ditiadakan
    if (jurnalmengajar_is_kbm_ditiadakan($j['kelas'], $today)) {
        continue;
    }

    // Lewati jika jam belum selesai
    if (!in_array((int)$j['jamke'], $jam_terlewat)) {
        continue;
    }

    // Cek Guru Tidak Hadir
    $status = jurnalmengajar_get_status_takhadir($j['userid'], $today);
    if ($status !== false) {
        if (!isset($tidakhadir[$j['userid']])) {
            $tidakhadir[$j['userid']] = [
                'lastname' => $j['lastname'],
                'status'   => $status
            ];
        }
        continue;
    }

    $key = $j['userid'] . '-' . $j['kelas'] . '-' . (int)$j['jamke'];
    if (isset($filled[$key])) {
        continue;
    }

    if (!isset($pending[$j['userid']])) {
        $pending[$j['userid']] = [
            'lastname' => $j['lastname'],
            'kelasjam' => []
        ];
    }

    if (!isset($pending[$j['userid']]['kelasjam'][$j['kelas']])) {
        $pending[$j['userid']]['kelasjam'][$j['kelas']] = [];
    }

    $pending[$j['userid']]['kelasjam'][$j['kelas']][] = (int)$j['jamke'];
}


/* ==========================================
 * TAMPILKAN UI KARTU REMINDER (PER GURU)
 * ========================================== */

if (empty($pending) && empty($tidakhadir)) {
    jm_berhenti_dengan_pesan("Selamat! Semua jurnal untuk jam yang terlewat sudah diisi.", 'success');
}

echo html_writer::tag('h3', 'Daftar Reminder Guru', ['class' => 'mt-4 mb-3']);

$warna = '#ffc107'; // Warna border kuning (warning)
$icon  = '⏰'; // Ikon jam

foreach ($pending as $userid => $info) {

    // 1. Ambil nomor WA
    $nowa = $DB->get_field_sql("
        SELECT d.data
        FROM {user_info_data} d
        JOIN {user_info_field} f ON f.id = d.fieldid
        WHERE d.userid = :userid AND f.shortname = 'nowa'
    ", ['userid' => $userid]);

    $nomor = !empty($nowa) ? preg_replace('/[^0-9]/', '', $nowa) : '';

    // 2. Format kelas dan jam yang belum diisi
    $urut = [];
    foreach ($info['kelasjam'] as $kelas => $jamlist) {
        $jamlist = array_unique($jamlist);
        sort($jamlist);
        $urut[$kelas] = $jamlist;
    }
    uasort($urut, function($a, $b) {
        return $a[0] <=> $b[0];
    });

$listkelas = "";
    $ringkasParts = [];
    foreach ($urut as $kelas => $jamlist) {
        $listkelas .= "$kelas jam ke " . implode(',', $jamlist) . "\n";
        
        // FORMAT BARU SESUAI PERMINTAAN
        $ringkasParts[] = 'Kelas ' . $kelas . ': Jamke ' . implode(',', $jamlist);
    }
    
    // Simpan ringkasan untuk rekap admin nanti
    $pending[$userid]['ringkas'] = implode('; ', $ringkasParts);

    // 3. Susun Pesan
    $datawa = [
        '{guru}'     => $info['lastname'],
        '{tanggal}'  => $todayLabel,
        '{kelasjam}' => trim($listkelas)
    ];

    // Kita gunakan jm_preview_template untuk memanggil template text
    $pesan = jm_preview_template('reminder_jurnal', $datawa);

    // 4. Render Card UI
    echo html_writer::start_div('card mb-3 shadow-sm', ['style' => 'border-left:6px solid '.$warna]);
    echo html_writer::start_div('card-body');

    // Judul & Guru
    echo html_writer::tag('h5', $icon . ' Reminder: ' . s($info['lastname']));
    echo html_writer::empty_tag('hr');

    // Info Kelas
    echo html_writer::tag('div', '<strong>Kelas belum diisi:</strong><br>' . nl2br(s(trim($listkelas))));
    echo html_writer::empty_tag('hr');

    // Box Preview Pesan
    echo html_writer::start_div('alert alert-light');
    echo html_writer::tag('strong', 'Preview Pesan');
    echo html_writer::tag('pre', s($pesan), ['style'=>'white-space:pre-wrap']);
    echo html_writer::end_div();

    // Nomor Tujuan
    echo html_writer::tag('strong', 'Nomor Tujuan');
    if (empty($nomor)) {
        echo html_writer::div('<em>Tidak ada nomor WA di profil.</em>', 'text-danger mb-3');
    } else {
        echo html_writer::tag('div', '<strong>' . s($info['lastname']) . '</strong> (' . s($nomor) . ')', ['class' => 'mb-3']);
    }

    // Tombol Aksi (Copy & WA)
    echo html_writer::start_div('mt-3');
    
    // Tombol Copy
    echo html_writer::tag('button', '📋 Copy Pesan', [
        'class'   => 'btn btn-primary btn-sm mr-2',
        'onclick' => "navigator.clipboard.writeText(".json_encode($pesan).").then(()=>alert('Pesan berhasil disalin'));"
    ]);

    echo ' '; // spasi antar tombol

    // Tombol Kirim WhatsApp
    if (!empty($nomor)) {
        $linkwa = 'https://wa.me/' . $nomor . '?text=' . rawurlencode($pesan);
        echo html_writer::link($linkwa, 'Buka WhatsApp', [
            'class'  => 'btn btn-success btn-sm',
            'target' => '_blank',
            'rel'    => 'noopener'
        ]);
    } else {
        echo html_writer::tag('button', 'Buka WhatsApp', [
            'class'    => 'btn btn-success btn-sm',
            'disabled' => 'disabled'
        ]);
    }

    echo html_writer::end_div(); // End mt-3 (Tombol)
    echo html_writer::end_div(); // End card-body
    echo html_writer::end_div(); // End card
}


/* ==========================================
 * TAMPILKAN UI KARTU REKAP (UNTUK ADMIN)
 * ========================================== */

echo html_writer::tag('h3', 'Preview Rekap Admin', ['class' => 'mt-5 mb-3']);

$daftar = '';
$daftartakhadir = '';

if (empty($tidakhadir)) {
    $daftartakhadir = '-';
}

foreach ($pending as $info) {
    $daftar .= "• {$info['lastname']} - {$info['ringkas']}\n";
}
foreach ($tidakhadir as $info) {
    $daftartakhadir .= "• {$info['lastname']} - " . ucfirst($info['status']) . "\n";
}

$datawa_rekap = [
    '{tanggal}'    => $todayLabel,
    '{daftar}'     => trim($daftar),
    '{jumlah}'     => count($pending),
    '{tidakhadir}' => trim($daftartakhadir)
];

$pesan_rekap = jm_preview_template('rekap_reminder', $datawa_rekap);
$warna_rekap = '#0d6efd'; // Warna border biru
$icon_rekap  = '📋';

echo html_writer::start_div('card mb-4 shadow-sm', ['style' => 'border-left:6px solid '.$warna_rekap]);
echo html_writer::start_div('card-body');

echo html_writer::tag('h5', $icon_rekap . ' Pesan Rekapitulasi Harian');
echo html_writer::empty_tag('hr');

echo html_writer::start_div('alert alert-light');
echo html_writer::tag('strong', 'Preview Pesan Rekap');
echo html_writer::tag('pre', s($pesan_rekap), ['style'=>'white-space:pre-wrap']);
echo html_writer::end_div();

echo html_writer::start_div('mt-3');
echo html_writer::tag('button', '📋 Copy Pesan Rekap', [
    'class'   => 'btn btn-primary btn-sm',
    'onclick' => "navigator.clipboard.writeText(".json_encode($pesan_rekap).").then(()=>alert('Pesan Rekap berhasil disalin'));"
]);
echo html_writer::end_div();

echo html_writer::end_div(); // End card-body
echo html_writer::end_div(); // End card

echo $OUTPUT->footer();
