<?php
define('CLI_SCRIPT', true);
require_once(__DIR__.'/../../../config.php');

require_once(__DIR__.'/../jam_pelajaran_lib.php');
require_once(__DIR__.'/../jadwal_acuan_lib.php');
require_once(__DIR__.'/../lib.php'); 
require_once(__DIR__.'/../lib_notifikasi.php');// fungsi kirim WA

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

$jamrekap = '19:50';
$jamsekarang = date('H:i');
$isrekap = ($jamsekarang >= $jamrekap);
//$isrekap = true; //test mode rekap

$dryrun = in_array('--dry-run', $argv);
// $dryrun = true; // untuk test

// ===== Cek hari sekolah =====
$hariSekolah = get_config('local_jurnalmengajar', 'harisekolah');

if (empty($hariSekolah)) {
    $hariSekolah = 'Senin,Selasa,Rabu,Kamis,Jumat';
}

$hariSekolah = array_map('trim', explode(',', $hariSekolah));

if (!in_array($hariIndo, $hariSekolah)) {
    mtrace("Hari $hariIndo bukan hari sekolah.");
    exit(0);
}

// ===== Cek tanggal libur =====
if (jurnalmengajar_cek_libur($today)) {
    mtrace("Hari ini tanggal libur.");
    exit(0);
}

// ===== Cek tanggal asesmen =====
$tanggalasesmen = trim(get_config('local_jurnalmengajar', 'tanggalasesmen'));

if (!empty($tanggalasesmen)) {

    if (preg_match('/(\d{4}-\d{2}-\d{2})\s*s\/d\s*(\d{4}-\d{2}-\d{2})/i',
        $tanggalasesmen,
        $match)) {

        $mulai  = strtotime($match[1]);
        $selesai = strtotime($match[2]);
        $hariini = strtotime($today);

        if ($hariini >= $mulai && $hariini <= $selesai) {
            mtrace("Hari ini berada dalam rentang asesmen.");
            exit(0);
        }
    }
}

mtrace("=== Notifikasi Jurnal Rekap ===");
mtrace("Hari: $hariIndo");

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
    mtrace("Belum ada jam pelajaran yang terlewat.");
    exit(0);
}

mtrace("Jam terlewat: " . implode(',', $jam_terlewat));

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

        // Samakan kelas dengan jadwal
        $kelas = $row->kelas;
        if (isset($cohortmap[$kelas])) {
            $kelas = $cohortmap[$kelas];
        }

        $key = $row->userid . '-' . $kelas . '-' . $j;
        $filled[$key] = true;

        // Debug
        // mtrace("FILLED: " . $key);
    }
}

// ===== Ambil jadwal dari database =====
$jadwal_db = $DB->get_records_sql("
    SELECT j.id, j.userid, j.kelas, j.jamke, u.lastname
    FROM {local_jurnalmengajar_jadwal} j
    JOIN {user} u ON u.id = j.userid
    WHERE j.hari = :hari
", [
    'hari' => $hariIndo
]);

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
    mtrace("Tidak ada jadwal di database untuk hari $hariIndo");
    exit(0);
}

// =======================================================
// CEK DATA KEGIATAN (JAM TIDAK BELAJAR) HARI INI
// =======================================================
$today_timestamp = strtotime($today); 
$kegiatan_hari_ini = $DB->get_records_sql("
    SELECT id, kelas, jamke
    FROM {local_jurnalmengajar_kegiatan}
    WHERE tanggal = :today
", ['today' => $today_timestamp]);

$jam_kegiatan_global = []; 
$jam_kegiatan_kelas = [];  

foreach ($kegiatan_hari_ini as $keg) {
    $jam_batal = array_filter(array_map('trim', explode(',', $keg->jamke)));
    
    if (empty($keg->kelas)) {
        $jam_kegiatan_global = array_merge($jam_kegiatan_global, $jam_batal);
    } else {
        // Ambil nama kelas jika $keg->kelas berupa ID cohort
        $namakelas = isset($cohortmap[$keg->kelas]) ? $cohortmap[$keg->kelas] : $keg->kelas;
        
        // Simpan berdasarkan NAMA KELAS (misal: "X-A")
        if (!isset($jam_kegiatan_kelas[$namakelas])) {
            $jam_kegiatan_kelas[$namakelas] = [];
        }
        $jam_kegiatan_kelas[$namakelas] = array_merge($jam_kegiatan_kelas[$namakelas], $jam_batal);
        
        // Simpan juga berdasarkan ID KELAS (misal: "75") untuk jaga-jaga
        if (!isset($jam_kegiatan_kelas[$keg->kelas])) {
            $jam_kegiatan_kelas[$keg->kelas] = [];
        }
        $jam_kegiatan_kelas[$keg->kelas] = array_merge($jam_kegiatan_kelas[$keg->kelas], $jam_batal);
    }
}
$jam_kegiatan_global = array_unique($jam_kegiatan_global);

// =======================================================
// OPTIMASI LOGIKA BLOK MENGAJAR (JAM BERURUTAN)
// =======================================================
$jadwal_guru_raw = [];
foreach ($jadwal as $j) {
    $jadwal_guru_raw[$j['userid']][] = (int)$j['jamke'];
}

$jam_boleh_diingatkan = [];
foreach ($jadwal_guru_raw as $uid => $jamlist) {
    $jamlist = array_unique($jamlist);
    sort($jamlist);

    $blocks = [];
    $current_block = [];
    $prev_jam = -1;

    foreach ($jamlist as $jam) {
        if ($prev_jam === -1 || $jam == $prev_jam + 1) {
            $current_block[] = $jam;
        } else {
            $blocks[] = $current_block;
            $current_block = [$jam];
        }
        $prev_jam = $jam;
    }
    if (!empty($current_block)) {
        $blocks[] = $current_block;
    }

    $jam_boleh_diingatkan[$uid] = [];
    foreach ($blocks as $block) {
        $jam_terakhir_di_blok = max($block);
        
        if (in_array($jam_terakhir_di_blok, $jam_terlewat)) {
            $jam_boleh_diingatkan[$uid] = array_merge($jam_boleh_diingatkan[$uid], $block);
        }
    }
}
// =======================================================

// ===== Group jurnal yang belum diisi =====
$pending = [];
$tidakhadir = [];
$cutoff_cache = [];
foreach ($jadwal as $j) {

    $uid = $j['userid'];
    $jam_ini = (string)$j['jamke'];

    // 🔥 FILTER JAM TIDAK BELAJAR (KEGIATAN GLOBAL)
    if (in_array($jam_ini, $jam_kegiatan_global)) {
        mtrace("SKIP (Kegiatan Global): {$j['lastname']} | Kelas: {$j['kelas']} | Jam: {$jam_ini}");
        continue;
    }

    // 🔥 FILTER JAM TIDAK BELAJAR (KEGIATAN PER KELAS)
    if (isset($jam_kegiatan_kelas[$j['kelas']]) && in_array($jam_ini, $jam_kegiatan_kelas[$j['kelas']])) {
        mtrace("SKIP (Kegiatan Spesifik): {$j['lastname']} | Kelas: {$j['kelas']} | Jam: {$jam_ini}");
        continue;
    }

    // 🔥 FILTER UTAMA BLOK MENGAJAR
    if (!isset($jam_boleh_diingatkan[$uid]) || !in_array((int)$j['jamke'], $jam_boleh_diingatkan[$uid])) {
        continue;
    }

    // 🔥 FILTER CUT OFF MULTI KELAS
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

    // ===== FILTER KBM DITIADAKAN =====
    if (jurnalmengajar_is_kbm_ditiadakan($j['kelas'], $today)) {
        mtrace("KBM DITIADAKAN: {$j['lastname']} | {$j['kelas']} | Jam {$j['jamke']}");
        continue;
    }

    // ===== Cek Guru Tidak Hadir =====
    $status = jurnalmengajar_get_status_takhadir($j['userid'], $today);

    if ($status !== false) {
        if (!isset($tidakhadir[$j['userid']])) {
            $tidakhadir[$j['userid']] = [
                'lastname' => $j['lastname'],
                'status'   => $status
            ];
        }
        mtrace("TAKHADIR: {$j['lastname']} | {$j['kelas']} | " . ucfirst($status));
        continue;
    }
    
    $key = $uid . '-' . $j['kelas'] . '-' . (int)$j['jamke'];

    if (isset($filled[$key])) {
        continue;
    }

    if (!isset($pending[$uid])) {
        $pending[$uid] = [
            'lastname' => $j['lastname'],
            'kelasjam' => []
        ];
    }

    if (!isset($pending[$uid]['kelasjam'][$j['kelas']])) {
        $pending[$uid]['kelasjam'][$j['kelas']] = [];
    }

    $pending[$uid]['kelasjam'][$j['kelas']][] = (int)$j['jamke'];
}

if (empty($pending) && empty($tidakhadir)) {
    mtrace("Semua jurnal yang jamnya sudah selesai telah diisi (atau dibatalkan karena kegiatan).");
    exit(0);
}

// ===== Kirim WA per guru =====
$mengirim = 0;

if (!$isrekap) {
    mtrace("Mode: Reminder Guru");

    foreach ($pending as $userid => $info) {

        // Ambil nomor WA
        $nowa = $DB->get_field_sql("
            SELECT d.data
            FROM {user_info_data} d
            JOIN {user_info_field} f ON f.id = d.fieldid
            WHERE d.userid = :userid AND f.shortname = 'nowa'
        ", ['userid' => $userid]);

        if (empty($nowa)) {
            mtrace("Tidak ada nomor WA untuk {$info['lastname']}");
            continue;
        }

        $nomor = preg_replace('/[^0-9]/', '', $nowa);

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

        if ($dryrun) {
            $pesan = "Notifikasi SiM ❗\n\n";
            $pesan .= "Bpk/Ibu Guru {$info['lastname']},\n";
            $pesan .= "mohon mengisi jurnal mengajar hari ini ({$todayLabel}) untuk:\n\n";
            $pesan .= trim($listkelas);
            $pesan .= "\n\nTerima kasih.";

            mtrace("");
            mtrace("========================================");
            mtrace("TEST REMINDER");
            mtrace("Nomor : $nomor");
            mtrace("----------------------------------------");
            mtrace($pesan);
            mtrace("========================================");
            continue;
        } else {
            $res = jm_kirim_template('reminder_jurnal', $nomor, $datawa);
        }

        $pending[$userid]['ringkas'] = $ringkas;
        
        mtrace("Kirim ke $nomor ({$info['lastname']}) -> " . (int)$res);
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
    mtrace("Mode: Rekap Admin");

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
        '{tanggal}'    => $todayLabel,
        '{daftar}'     => trim($daftar),
        '{jumlah}'     => count($pending),
        '{tidakhadir}' => trim($daftartakhadir)
    ];

    $res = jm_kirim_template_auto('rekap_reminder', $datawa);

    if ($res) {
        mtrace("Rekap reminder dikirim.");
    } else {
        mtrace("Rekap reminder dilewati (tidak ada tujuan notifikasi atau pengiriman gagal).");
    }
}

mtrace("Selesai. Total notifikasi dikirim: $mengirim");
