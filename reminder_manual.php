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
$tanggalasesmen = trim((string)get_config('local_jurnalmengajar', 'tanggalasesmen'));
if (!empty($tanggalasesmen)) {
    if (preg_match('/(\d{4}-\d{2}-\d{2})\s*s\/d\s*(\d{4}-\d{2}-\d{2})/i', $tanggalasesmen, $match)) {
        $mulai   = strtotime($match[1]);
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
    jm_berhenti_dengan_pesan("Tidak ada jadwal KBM di database untuk hari $hariIndo.", 'warning');
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


/* =======================================================
 * OPTIMASI LOGIKA BLOK MENGAJAR (JAM BERURUTAN)
 * ======================================================= */
$jadwal_guru_raw = [];
foreach ($jadwal as $j) {
    $jadwal_guru_raw[$j['userid']][] = (int)$j['jamke'];
}

$jam_boleh_diingatkan = [];
foreach ($jadwal_guru_raw as $uid => $jamlist) {
    $jamlist = array_unique($jamlist);
    sort($jamlist); // Urutkan jam dari terkecil ke terbesar

    $blocks = [];
    $current_block = [];
    $prev_jam = -1;

    // Deteksi deret angka berurutan sebagai 1 blok
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
        
        // Jika jam terakhir di blok ini sudah lewat, maka keseluruhan blok boleh dikirim notif
        if (in_array($jam_terakhir_di_blok, $jam_terlewat)) {
            $jam_boleh_diingatkan[$uid] = array_merge($jam_boleh_diingatkan[$uid], $block);
        }
    }
}


// ===== Group jurnal yang belum diisi =====
$pending = [];
$tidakhadir = [];

$cutoff_cache = [];
$cache_status_takhadir = []; 
$kelas_akhir_regex = get_config('local_jurnalmengajar', 'kelas_cutoff_regex') ?: '\b(VI|IX|XII)\b';

foreach ($jadwal as $j) {
    $uid = $j['userid'];
    $jam_ini = (string)$j['jamke'];

    // 🔥 FILTER JAM TIDAK BELAJAR (KEGIATAN GLOBAL)
    if (in_array($jam_ini, $jam_kegiatan_global)) {
        continue;
    }

    // 🔥 FILTER JAM TIDAK BELAJAR (KEGIATAN PER KELAS)
    if (isset($jam_kegiatan_kelas[$j['kelas']]) && in_array($jam_ini, $jam_kegiatan_kelas[$j['kelas']])) {
        continue;
    }

    // FILTER UTAMA: Lewati jika jam ini belum termasuk dalam blok ngajar yang sudah SELESAI
    if (!isset($jam_boleh_diingatkan[$uid]) || !in_array((int)$j['jamke'], $jam_boleh_diingatkan[$uid])) {
        continue;
    }

    // Filter cutoff multi kelas
    $kelas_level = null;
    if (preg_match('/' . $kelas_akhir_regex . '/i', $j['kelas'], $match)) {
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

    // Cek Guru Tidak Hadir (Optimasi N+1 Cache)
    if (!isset($cache_status_takhadir[$uid])) {
        $cache_status_takhadir[$uid] = jurnalmengajar_get_status_takhadir($uid, $today);
    }
    $status = $cache_status_takhadir[$uid];

    if ($status !== false) {
        if (!isset($tidakhadir[$uid])) {
            $tidakhadir[$uid] = [
                'lastname' => $j['lastname'],
                'status'   => $status
            ];
        }
        continue;
    }

    // Cek di array apakah jurnal sudah diisi
    $key = $uid . '-' . $j['kelas'] . '-' . (int)$j['jamke'];
    if (isset($filled[$key])) {
        continue;
    }

    // Jika belum diisi dan lolos semua filter, masukkan ke daftar pending
    if (!isset($pending[$uid])) {
        $pending[$uid] = [
            'lastname' => $j['lastname'],
            'nowa'     => $j['nowa'],
            'kelasjam' => []
        ];
    }

    if (!isset($pending[$uid]['kelasjam'][$j['kelas']])) {
        $pending[$uid]['kelasjam'][$j['kelas']] = [];
    }

    $pending[$uid]['kelasjam'][$j['kelas']][] = (int)$j['jamke'];
}

// ===== Hentikan jika tidak ada data pending / tidak hadir =====
if (empty($pending) && empty($tidakhadir)) {
    jm_berhenti_dengan_pesan("Semua guru dengan blok mengajar yang sudah selesai telah mengisi jurnal (Atau ditiadakan karena Kegiatan).", 'success');
}

// ===== TAMPILKAN HEADER & TOMBOL EKSEKUSI =====
echo html_writer::div('Jam yang sudah lewat secara sistem: <strong>' . implode(', ', $jam_terlewat) . '</strong><br><small><em>*Guru yang masih melangsungkan sisa jam berurutan tidak akan ditampilkan agar tidak terganggu.</em></small>', 'alert alert-info mb-4');

$url_proses = new moodle_url('/local/jurnalmengajar/proses_notifikasi.php');
$tombol_eksekusi = $OUTPUT->single_button(
    $url_proses, 
    '🚀 Kirim Semua Notifikasi WA via Gateway', 
    'post', 
    [
        'class' => 'btn-warning btn-lg font-weight-bold mb-4',
        'onclick' => "return confirm('Apakah Anda yakin ingin mengirim notifikasi otomatis melalui Gateway?');"
    ]
);

echo html_writer::div($tombol_eksekusi, 'mb-4');

/* ==========================================
 * TAMPILKAN UI KARTU REMINDER (PER GURU)
 * ========================================== */

echo html_writer::tag('h3', 'Daftar Reminder Guru', ['class' => 'mt-4 mb-3']);

$warna = '#ffc107'; // Warna border kuning (warning)
$icon  = '⏰'; // Ikon jam

foreach ($pending as $userid => $info) {

    // Standardisasi nomor 0 menjadi 62
    $nomor = !empty($info['nowa']) ? preg_replace('/[^0-9]/', '', $info['nowa']) : '';
    if (substr($nomor, 0, 1) === '0') {
        $nomor = '62' . substr($nomor, 1);
    }

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
        $ringkasParts[] = 'Kelas ' . $kelas . ': Jamke ' . implode(',', $jamlist);
    }
    
    $pending[$userid]['ringkas'] = implode('; ', $ringkasParts);

    $datawa = [
        '{guru}'     => $info['lastname'],
        '{tanggal}'  => $todayLabel,
        '{kelasjam}' => trim($listkelas)
    ];

    $pesan = jm_preview_template('reminder_jurnal', $datawa);

    echo html_writer::start_div('card mb-3 shadow-sm', ['style' => 'border-left:6px solid '.$warna]);
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h5', $icon . ' Reminder: ' . s($info['lastname']));
    echo html_writer::empty_tag('hr');
    echo html_writer::tag('div', '<strong>Kelas belum diisi:</strong><br>' . nl2br(s(trim($listkelas))));
    echo html_writer::empty_tag('hr');
    echo html_writer::start_div('alert alert-light');
    echo html_writer::tag('strong', 'Preview Pesan');
    echo html_writer::tag('pre', s($pesan), ['style'=>'white-space:pre-wrap']);
    echo html_writer::end_div();

    echo html_writer::tag('strong', 'Nomor Tujuan');
    if (empty($nomor)) {
        echo html_writer::div('<em>Tidak ada nomor WA di profil.</em>', 'text-danger mb-3');
    } else {
        echo html_writer::tag('div', '<strong>' . s($info['lastname']) . '</strong> (+' . s($nomor) . ')', ['class' => 'mb-3']);
    }

    echo html_writer::start_div('mt-3');
    echo html_writer::tag('button', '📋 Copy Pesan', [
        'class'   => 'btn btn-primary btn-sm mr-2',
        'onclick' => "navigator.clipboard.writeText(".json_encode($pesan).").then(()=>alert('Pesan berhasil disalin'));"
    ]);
    echo ' ';

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

    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
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
$warna_rekap = '#0d6efd'; 
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
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
