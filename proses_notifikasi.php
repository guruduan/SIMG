<?php
// 1. Memanggil config.php dari root Moodle
require_once(__DIR__ . '/../../config.php');

// 2. Memanggil library plugin
require_once(__DIR__ . '/jam_pelajaran_lib.php');
require_once(__DIR__ . '/jadwal_acuan_lib.php');
require_once(__DIR__ . '/lib.php'); 
require_once(__DIR__ . '/lib_notifikasi.php');

// 3. Autentikasi dan Konfigurasi Halaman Moodle
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context); 

// Cek Keamanan Token Form Moodle (Sesskey)
require_sesskey();

$PAGE->set_url(new moodle_url('/local/jurnalmengajar/proses_notifikasi.php'));
$PAGE->set_context($context);
$PAGE->set_title('Proses Rekap & Notifikasi Jurnal');
$PAGE->set_heading('Proses Rekap & Notifikasi Jurnal Mengajar');

echo $OUTPUT->header();
echo $OUTPUT->heading('Log Eksekusi Rekap Jurnal');

global $DB;

// ===== FUNGSI BANTUAN UNTUK WEB =====
$web_logs = [];

function web_trace($message) {
    global $web_logs;
    $web_logs[] = $message;
}

function selesai_proses($message = '') {
    global $OUTPUT, $web_logs;
    if ($message !== '') {
        web_trace($message);
    }
    
    // Tampilkan semua log di dalam box
    echo $OUTPUT->box(implode('<br>', $web_logs), 'generalbox alert alert-info mt-3');
    
    // Tombol untuk kembali ke halaman utama reminder
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/local/jurnalmengajar/reminder_manual.php'),
            '⬅ Kembali ke Preview Reminder',
            ['class' => 'btn btn-secondary mt-3']
        ),
        'mt-3'
    );

    echo $OUTPUT->footer();
    exit;
}
// ====================================

$cohortmap = [];
$cohorts = $DB->get_records('cohort', null, '', 'id,name');
foreach ($cohorts as $c) {
    $cohortmap[$c->id] = $c->name;
}

$today = date('Y-m-d');
$hariIndo = jurnalmengajar_get_hari_ini();
$current = time();
$todayLabel = tanggal_indo(time());

$jamrekap = '19:50';
$jamsekarang = date('H:i');
$isrekap = ($jamsekarang >= $jamrekap);

// ===== Cek hari sekolah =====
$hariSekolah = get_config('local_jurnalmengajar', 'harisekolah');
if (empty($hariSekolah)) {
    $hariSekolah = 'Senin,Selasa,Rabu,Kamis,Jumat';
}
$hariSekolah = array_map('trim', explode(',', $hariSekolah));

if (!in_array($hariIndo, $hariSekolah)) {
    selesai_proses("Hari $hariIndo bukan hari sekolah.");
}

// ===== Cek tanggal libur =====
if (jurnalmengajar_cek_libur($today)) {
    selesai_proses("Hari ini tanggal libur.");
}

// ===== Cek tanggal asesmen =====
$tanggalasesmen = trim((string)get_config('local_jurnalmengajar', 'tanggalasesmen'));
if (!empty($tanggalasesmen)) {
    if (preg_match('/(\d{4}-\d{2}-\d{2})\s*s\/d\s*(\d{4}-\d{2}-\d{2})/i', $tanggalasesmen, $match)) {
        $mulai   = strtotime($match[1]);
        $selesai = strtotime($match[2]);
        $hariini = strtotime($today);

        if ($hariini >= $mulai && $hariini <= $selesai) {
            selesai_proses("Hari ini berada dalam rentang asesmen.");
        }
    }
}

web_trace("=== Notifikasi Jurnal Rekap ===");
web_trace("Hari: $hariIndo");

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
    selesai_proses("Belum ada jam pelajaran yang terlewat.");
}

web_trace("Jam terlewat: " . implode(',', $jam_terlewat));

// ===== Ambil jurnal hari ini =====
$starttoday = strtotime("$today 00:00:00");
$endtoday   = strtotime("$today 23:59:59");

$jurnaltoday = $DB->get_records_sql("
    SELECT id, userid, kelas, jamke
    FROM {local_jurnalmengajar}
    WHERE timecreated BETWEEN :starttoday AND :endtoday
", [
    'starttoday' => $starttoday,
    'endtoday'   => $endtoday
]);

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
        web_trace("FILLED: " . $key);
    }
}

// ===== Ambil jadwal dari database =====
$jadwal_db = $DB->get_records_sql("
    SELECT j.id, j.userid, j.kelas, j.jamke, u.lastname, d.data AS nowa
    FROM {local_jurnalmengajar_jadwal} j
    JOIN {user} u ON u.id = j.userid
    LEFT JOIN {user_info_field} f ON f.shortname = 'nowa'
    LEFT JOIN {user_info_data} d ON d.fieldid = f.id AND d.userid = u.id
    WHERE j.hari = :hari
", ['hari' => $hariIndo]);

$jadwal = [];
foreach ($jadwal_db as $j) {
    $jadwal[] = [
        'userid'   => $j->userid,
        'lastname' => $j->lastname,
        'kelas'    => $j->kelas,
        'jamke'    => $j->jamke,
        'nowa'     => $j->nowa
    ];
}

if (empty($jadwal)) {
    selesai_proses("Tidak ada jadwal di database untuk hari $hariIndo");
}

web_trace("=== JADWAL ===");
foreach ($jadwal as $j) {
    $k = $j['userid'].'-'.$j['kelas'].'-'.$j['jamke'];
    web_trace("JADWAL: " . $k);
}

// ===== Group jurnal yang belum diisi =====
$pending = [];
$tidakhadir = [];
$cutoff_cache = [];

foreach ($jadwal as $j) {
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

    if (jurnalmengajar_is_kbm_ditiadakan($j['kelas'], $today)) {
        web_trace("KBM DITIADAKAN: {$j['lastname']} | {$j['kelas']} | Jam {$j['jamke']}");
        continue;
    }

    if (!in_array((int)$j['jamke'], $jam_terlewat)) {
        continue;
    }

    $status = jurnalmengajar_get_status_takhadir($j['userid'], $today);
    if ($status !== false) {
        if (!isset($tidakhadir[$j['userid']])) {
            $tidakhadir[$j['userid']] = [
                'lastname' => $j['lastname'],
                'status'   => $status
            ];
        }
        web_trace("TAKHADIR: {$j['lastname']} | {$j['kelas']} | " . ucfirst($status));
        continue;
    }

    $key = $j['userid'] . '-' . $j['kelas'] . '-' . (int)$j['jamke'];
    if (isset($filled[$key])) {
        continue;
    }

    if (!isset($pending[$j['userid']])) {
        $pending[$j['userid']] = [
            'lastname' => $j['lastname'],
            'nowa'     => $j['nowa'],
            'kelasjam' => []
        ];
    }
    if (!isset($pending[$j['userid']]['kelasjam'][$j['kelas']])) {
        $pending[$j['userid']]['kelasjam'][$j['kelas']] = [];
    }
    $pending[$j['userid']]['kelasjam'][$j['kelas']][] = (int)$j['jamke'];
}

if (empty($pending) && empty($tidakhadir)) {
    selesai_proses("Semua jurnal sudah diisi.");
}

// ===== Kirim WA per guru =====
$mengirim = 0;

if (!$isrekap) {
    web_trace("Mode: Reminder Guru");

    foreach ($pending as $userid => $info) {

        if (empty($info['nowa'])) {
            web_trace("Tidak ada nomor WA untuk {$info['lastname']}");
            continue;
        }

        $nomor = preg_replace('/[^0-9]/', '', $info['nowa']);

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
            $ringkasParts[] = $kelas . ':' . implode(',', $jamlist);
        }

        $ringkas = implode('; ', $ringkasParts);

        $datawa = [
            '{guru}'     => $info['lastname'],
            '{tanggal}'  => $todayLabel,
            '{kelasjam}' => trim($listkelas)
        ];

        $res = jm_kirim_template('reminder_jurnal', $nomor, $datawa);
        $pending[$userid]['ringkas'] = $ringkas;
        
        web_trace("Kirim ke $nomor ({$info['lastname']}) -> " . ($res ? 'SUCCESS' : 'FAILED'));
        
        if ($res) {
            $mengirim++;
        }

        // ===== Log TXT =====
        $logtxt = __DIR__ . '/notif_log_' . date('Y-m-d') . '.txt';
        $logstatus = $res ? 'BERHASIL' : 'GAGAL';
        $line = date('Y-m-d H:i:s')
              . " | Guru: {$info['lastname']}"
              . " | Nomor: $nomor"
              . " | Kelas/Jam: $ringkas"
              . " | Status: $logstatus"
              . "\n";
        file_put_contents($logtxt, $line, FILE_APPEND);
    }
} else {
    web_trace("Mode: Rekap Admin");

    foreach ($pending as $userid => $info) {
        $urut = [];
        foreach ($info['kelasjam'] as $kelas => $jamlist) {
            $jamlist = array_unique($jamlist);
            sort($jamlist);
            $urut[$kelas] = $jamlist;
        }

        uasort($urut, function($a, $b) {
            return $a[0] <=> $b[0];
        });

        $ringkasParts = [];
        foreach ($urut as $kelas => $jamlist) {
            $ringkasParts[] = $kelas . ':' . implode(',', $jamlist);
        }
        $pending[$userid]['ringkas'] = implode('; ', $ringkasParts);
    }
}

if ($isrekap) {
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

    $datawa = [
        '{tanggal}' => $todayLabel,
        '{daftar}'  => trim($daftar),
        '{jumlah}'  => count($pending),
        '{tidakhadir}'  => trim($daftartakhadir)
    ];

    $res = jm_kirim_template_auto('rekap_reminder', $datawa);

    if ($res) {
        web_trace("Rekap reminder dikirim.");
    } else {
        web_trace("Rekap reminder dilewati (tidak ada tujuan notifikasi atau pengiriman gagal).");
    }
}

// Akhiri proses dan tampilkan semua log
selesai_proses("Selesai. Total notifikasi dikirim: $mengirim");
