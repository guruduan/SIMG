<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Format tanggal Indonesia
 */
function tanggal_indo($timestamp = null, $mode = 'full') {
    $timestamp = $timestamp ?: time();

    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

$hariIndex = (int) date('w', $timestamp);
$tgl = date('d', $timestamp);
$bulanIdx  = (int) date('m', $timestamp);
$tahun     = date('Y', $timestamp);

    if ($mode == 'judul') {
        return $hari[$hariIndex] . ' ' . $tgl . ' ' . $bulan[$bulanIdx] . ' ' . $tahun;
    }

    if ($mode == 'bulan') {
        return $bulan[$bulanIdx] . ' ' . $tahun;
    }

    if ($mode == 'tanggal') {
        return $tgl . ' ' . $bulan[$bulanIdx] . ' ' . $tahun;
    }

    if ($mode == 'jam') {
        return date('H:i', $timestamp);
}

if ($mode == 'tglbulan') {
    return $tgl . ' ' . $bulan[$bulanIdx];
}
    return $hari[$hariIndex] . ', ' .
           $tgl . ' ' .
           $bulan[$bulanIdx] . ' ' .
           $tahun .
           ' Pukul ' . date('H:i', $timestamp);
}

// nama murid
function format_nama_siswa($nama) {
    return ucwords(strtolower(trim($nama)));
}

/**
 * Ambil nama kelas dari ID cohort
 */
function get_nama_kelas($id) {
    global $DB;
    return $DB->get_field('cohort', 'name', ['id' => $id]) ?? "Kelas #$id";
}

/**
 * Ambil nomor WA user dari profile field nowa
 */
function get_user_nowa($userid) {
    global $DB;

    $sql = "SELECT d.data
              FROM {user_info_data} d
              JOIN {user_info_field} f ON f.id = d.fieldid
             WHERE d.userid = :userid
               AND f.shortname = :shortname";

    $nowa = $DB->get_field_sql($sql, [
        'userid' => $userid,
        'shortname' => 'nowa'
    ]);

    if (empty($nowa)) {
        return null; // jangan debugging
    }

    return preg_replace('/[^0-9]/', '', $nowa);
}

/**
 * Ambil nomor wali kelas dari mapping
 */
function get_nomor_wali_kelas($kelasid) {
    $json = get_config('local_jurnalmengajar', 'wali_kelas_mapping');
    $mapping = json_decode($json, true);

    if (empty($mapping[$kelasid])) {
        return null;
    }

    return get_user_nowa($mapping[$kelasid]);
}

/**
 * Ambil nomor kepala sekolah dari setting plugin
 */
function get_nomor_kepala_sekolah() {
    $nowa = get_config('local_jurnalmengajar', 'nomor_kepsek');

    if (empty($nowa)) {
        return null;
    }

    // bersihkan selain angka
    return preg_replace('/[^0-9]/', '', $nowa);
}

// fungsi hari sekolah
function jurnalmengajar_get_hari_sekolah() {
    $hari = get_config('local_jurnalmengajar', 'harisekolah');

    if (empty($hari)) {
        $hari = 'Senin,Selasa,Rabu,Kamis,Jumat';
    }

    $hari_array = explode(',', $hari);

    $result = [];
    foreach ($hari_array as $h) {
        $h = trim($h);
        $result[$h] = $h;
    }

    return $result;
}

//Fungsi urutan hari
function jurnalmengajar_get_urutan_hari() {
    $hari = get_config('local_jurnalmengajar', 'harisekolah');

    if (empty($hari)) {
        $hari = 'Senin,Selasa,Rabu,Kamis,Jumat';
    }

    $hari_array = explode(',', $hari);

    $urut = [];
    $no = 1;

    foreach ($hari_array as $h) {
        $urut[trim($h)] = $no;
        $no++;
    }

    return $urut;
}

//Fungsi hari ini
function jurnalmengajar_get_hari_ini() {
    $map = [
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
        'Sunday' => 'Minggu'
    ];

    $hari = date('l');
return $map[$hari] ?? '';
}

// Konversi timestamp → nama hari Indonesia
function jurnalmengajar_get_hari_by_timestamp($timestamp) {

    $map = [
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
        'Sunday' => 'Minggu'
    ];

    $hari = date('l', $timestamp);

    return $map[$hari] ?? '';
}

/**
 * Cek tanggal libur
 */
function jurnalmengajar_cek_libur($tanggal) {
    $tanggallibur = get_config('local_jurnalmengajar', 'tanggallibur');

    if (empty($tanggallibur)) return false;

    $lines = preg_split('/\r\n|\r|\n/', $tanggallibur);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line == '') continue;

        if (stripos($line, 's/d') !== false) {
            list($start, $end) = explode('s/d', $line);
            $start = trim($start);
            $end   = trim($end);

            if (strtotime($tanggal) >= strtotime($start) && strtotime($tanggal) <= strtotime($end)) {
                return true;
            }
        } else {
            if ($tanggal == $line) {
                return true;
            }
        }
    }

    return false;
}

// Hitung pengurang target karena libur
function jurnalmengajar_get_pengurang_target_libur(
    $userid,
    $tanggal_awal,
    $tanggal_akhir
) {

    require_once(__DIR__.'/jadwal_acuan_lib.php');

    $pengurang = 0;

    $tanggallibur = get_config(
        'local_jurnalmengajar',
        'tanggallibur'
    );

    if (empty($tanggallibur)) {
        return 0;
    }

    $jadwal = jurnalmengajar_get_jadwal_acuan();

    $lines = preg_split(
        '/\r\n|\r|\n/',
        $tanggallibur
    );

    foreach ($lines as $line) {

        $line = trim($line);

        if ($line == '') {
            continue;
        }

        // ==========================
        // SUPPORT RANGE TANGGAL
        // ==========================

        $daftar_tanggal = [];

        if (stripos($line, 's/d') !== false) {

            list($start, $end) = explode('s/d', $line);

            $start = trim($start);
            $end   = trim($end);

            $start_ts = strtotime($start);
            $end_ts   = strtotime($end);

            if (!$start_ts || !$end_ts) {
                continue;
            }

            for (
                $t = $start_ts;
                $t <= $end_ts;
                $t += 86400
            ) {
                $daftar_tanggal[] = $t;
            }

        } else {

            $timestamp = strtotime($line);

            if (!$timestamp) {
                continue;
            }

            $daftar_tanggal[] = $timestamp;
        }

        // ==========================
        // PROSES SETIAP TANGGAL
        // ==========================

        foreach ($daftar_tanggal as $timestamp) {

            // hanya minggu aktif
            if (
                $timestamp < $tanggal_awal
                ||
                $timestamp > $tanggal_akhir
            ) {
                continue;
            }

            $hari =
                jurnalmengajar_get_hari_by_timestamp(
                    $timestamp
                );

            foreach ($jadwal as $j) {

                if ($j['userid'] != $userid) {
                    continue;
                }

                if ($j['hari'] != $hari) {
                    continue;
                }

                // ==========================
                // CEK CUTOFF
                // ==========================

                $kelas = isset($j['kelas'])
                    ? trim($j['kelas'])
                    : '';

                $kelas_level = null;

                if (
                    preg_match(
                        '/\b(VI|IX|XII)\b/i',
                        $kelas,
                        $match
                    )
                ) {
                    $kelas_level = strtoupper($match[1]);
                }

                $cutoff = null;

                if ($kelas_level) {

                    $cutoff =
                        jurnalmengajar_get_cutoff_by_kelas(
                            $kelas_level,
                            $timestamp
                        );
                }

                // jika sudah cutoff
                if (
                    !empty($cutoff)
                    &&
                    $timestamp >= $cutoff
                ) {
                    continue;
                }

                // ==========================
                // HITUNG PENGURANG
                // ==========================

                $pengurang++;
            }
        }
    }

    return $pengurang;
}

// cek asesmen
function jurnalmengajar_is_tanggal_asesmen($tanggal = null) {
    if (!$tanggal) {
        $tanggal = date('Y-m-d');
    }

    $config = get_config('local_jurnalmengajar');
    $data = trim($config->tanggalasesmen ?? '');

    if (empty($data)) {
        return false;
    }

    $lines = explode("\n", $data);

    foreach ($lines as $line) {

        $line = trim($line);

        if (preg_match('/(\d{4}-\d{2}-\d{2})\s+s\/d\s+(\d{4}-\d{2}-\d{2})/', $line, $m)) {

            $mulai = strtotime($m[1]);
            $selesai = strtotime($m[2]);
            $cek = strtotime($tanggal);

            if ($cek >= $mulai && $cek <= $selesai) {
                return true;
            }
        }
    }

    return false;
}
/**
 * Ambil cutoff berdasarkan kode kelas (VI, IX, XII)
 */
function jurnalmengajar_get_cutoff_by_kelas($kelas_target, $timestamp = null) {

    $config = get_config('local_jurnalmengajar', 'cutoff_kelas');

    if (empty($config)) return null;

    if ($timestamp === null) {
        $timestamp = time();
    }

    $tahun = date('Y', $timestamp);

    $lines = preg_split('/\r\n|\r|\n/', $config);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line == '') continue;

        // Format: XII|2026-04-06
        if (!preg_match('/^([A-Z0-9]+)\|(\d{4}-\d{2}-\d{2})$/', $line, $m)) {
            continue;
        }

        $kelas = strtoupper($m[1]);
        $tanggal = $m[2];

        if ($kelas === strtoupper($kelas_target) && strpos($tanggal, $tahun . '-') === 0) {
            return strtotime($tanggal);
        }
    }

    return null;
}
/**
 * Cek boleh kirim WA atau tidak
 */
function jurnalmengajar_boleh_kirim_wa() {

    $hariIndoList = [
        1=>'Senin',2=>'Selasa',3=>'Rabu',
        4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'
    ];

$hariSekolah = get_config('local_jurnalmengajar', 'harisekolah');

if (empty($hariSekolah)) {
    $hariSekolah = 'Senin,Selasa,Rabu,Kamis,Jumat';
}

$hariSekolah = array_map('trim', explode(',', $hariSekolah));

    if (!in_array($hariIndoList[(int)date('N')], $hariSekolah)) {
        return false;
    }

    if (jurnalmengajar_cek_libur(date('Y-m-d'))) {
        return false;
    }

    return true;
}

/**
 * Kirim WhatsApp via Wablas
 */
function jurnalmengajar_kirim_wa($tujuan, $pesan) {
    global $CFG;

    // Pastikan array
    if (!is_array($tujuan)) {
        $tujuan = [$tujuan];
    }

    // Hapus duplikat
    $tujuan = array_unique($tujuan);

    // Siapkan log
    $logdir = $CFG->dataroot . '/logs';
    if (!file_exists($logdir)) {
        mkdir($logdir, 0755, true);
    }

    $logfile = $logdir . '/wa_debug.log';

    // Cek boleh kirim WA
    if (!jurnalmengajar_boleh_kirim_wa()) {
        file_put_contents($logfile,
            date('Y-m-d H:i:s') . " | DIBATALKAN: Hari libur / bukan hari sekolah\n",
            FILE_APPEND
        );
        return false;
    }

    // Ambil config Wablas
    $apikey = get_config('local_jurnalmengajar', 'apikey');
    $secret = get_config('local_jurnalmengajar', 'secretkey');
    $wablas_url = get_config('local_jurnalmengajar', 'wablas_url');

    if (empty($apikey) || empty($secret)) {
        file_put_contents($logfile,
            date('Y-m-d H:i:s') . " | ERROR API KEY\n",
            FILE_APPEND
        );
        return false;
    }

    $token = $apikey . '.' . $secret;
file_put_contents($logfile,
    "----------------------------------------\n" .
    date('Y-m-d H:i:s') . " | Mulai kirim notifikasi\n",
    FILE_APPEND
);
file_put_contents($logfile,
    date('Y-m-d H:i:s') . " | Pesan: " . str_replace("\n"," | ",$pesan) . "\n",
    FILE_APPEND
);
foreach ($tujuan as $nomor) {

    if (empty($nomor)) {
        file_put_contents($logfile,
            date('Y-m-d H:i:s') . " | Nomor kosong, dilewati\n",
            FILE_APPEND
        );
        continue;
    }

    file_put_contents($logfile,
        date('Y-m-d H:i:s') . " | Kirim WA ke $nomor\n",
        FILE_APPEND
    );

    $data = [
        'data' => [[
            'phone' => $nomor,
            'message' => $pesan,
            'secret' => false,
            'priority' => false
        ]]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
    CURLOPT_URL => $wablas_url,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            "Authorization: $token",
            "Content-Type: application/json"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

        file_put_contents($logfile,
        date('Y-m-d H:i:s') . " | Response: $response\n",
        FILE_APPEND
    );
}

return true;
} // <-- PENUTUP fungsi jurnalmengajar_kirim_wa()


// ===============================
// Fungsi ambil beban mengajar guru
// ===============================
function jurnalmengajar_get_beban_jam_guru_by_date($timestamp) {
    require_once(__DIR__.'/jadwal_acuan_lib.php');

    $jadwal = jurnalmengajar_get_jadwal_acuan();
    $beban = [];

    if (empty($timestamp) || !is_numeric($timestamp)) {
        $timestamp = time();
    }

    // 👉 TARUH DI SINI (sekali saja)
    $cutoff_cache = [];

    foreach ($jadwal as $j) {

        if (empty($j['userid'])) {
            continue;
        }

        $userid = $j['userid'];
        $kelas  = isset($j['kelas']) ? trim($j['kelas']) : '';

        // 🔍 Ambil level kelas
        $kelas_level = null;
        if (preg_match('/\b(VI|IX|XII)\b/i', $kelas, $match)) {
            $kelas_level = strtoupper($match[1]);
        }

        // 🔥 GANTI bagian cutoff LAMA dengan ini
        $cutoff = null;
        if ($kelas_level) {
            if (!isset($cutoff_cache[$kelas_level])) {
                $cutoff_cache[$kelas_level] = jurnalmengajar_get_cutoff_by_kelas($kelas_level, $timestamp);
            }
            $cutoff = $cutoff_cache[$kelas_level];
        }

        // 🔥 Filter
        if (!empty($cutoff) && $timestamp >= $cutoff) {
            continue;
        }

        if (!isset($beban[$userid])) {
            $beban[$userid] = 0;
        }

        $beban[$userid]++;
    }

    return $beban;
}
// ======================================
// Load snapshot beban mengajar semester
// ======================================
function jurnalmengajar_load_beban_snapshot($tahunajaran, $semester) {

    // Validasi
    if (empty($tahunajaran) || empty($semester)) {
        return [];
    }

    // Format nama file
    $tahunajaran_file = str_replace('/', '_', trim($tahunajaran));
    $semester_file = strtolower(trim($semester));

    // Lokasi file snapshot
    $filepath = __DIR__ . '/data/beban/beban_' .
        $tahunajaran_file . '_' .
        $semester_file . '.json';

    // Jika file tidak ada
    if (!file_exists($filepath)) {
        debugging('File snapshot beban tidak ditemukan: ' . $filepath);
        return [];
    }

    // Ambil isi file
    $json = file_get_contents($filepath);

    if ($json === false) {
        debugging('Gagal membaca file snapshot beban.');
        return [];
    }

    // Decode JSON
    $data = json_decode($json, true);

    if (!is_array($data)) {
        debugging('Format JSON snapshot beban tidak valid.');
        return [];
    }

    return $data;
}
// ======================================
// Generate snapshot beban semester
// ======================================
function jurnalmengajar_generate_beban_snapshot($tahunajaran, $semester) {

    global $CFG;

    // Ambil beban aktif sekarang
    $beban = jurnalmengajar_get_beban_jam_guru_by_date(time());

    if (empty($beban)) {
        return false;
    }

    // Format nama file
    $tahunajaran_file = str_replace('/', '_', trim($tahunajaran));
    $semester_file = strtolower(trim($semester));

    // Folder snapshot
    $folder = __DIR__ . '/data/beban';

    // Buat folder jika belum ada
    if (!file_exists($folder)) {
        mkdir($folder, 0755, true);
    }

    // Nama file
    $filepath = $folder . '/beban_' .
        $tahunajaran_file . '_' .
        $semester_file . '.json';

    // Encode JSON
    $json = json_encode(
    $beban,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
);

    // Simpan file
$result = file_put_contents($filepath, $json);

if ($result === false) {
    debugging('Gagal menulis file snapshot: ' . $filepath);
    return false;
}

return true;
}
// ======================================
// Deteksi tahun ajaran dari timestamp
// ======================================
function jurnalmengajar_get_tahunajaran_by_timestamp($timestamp = null) {

    if (empty($timestamp)) {
        $timestamp = time();
    }

    $bulan = (int)date('n', $timestamp);
    $tahun = (int)date('Y', $timestamp);

    // Juli-Desember
    if ($bulan >= 7) {
        return $tahun . '/' . ($tahun + 1);
    }

    // Januari-Juni
    return ($tahun - 1) . '/' . $tahun;
}
// ======================================
// Ambil awal semester dari jurnal pertama
// ======================================
function jurnalmengajar_get_awal_semester_dari_jurnal(
    $tahunajaran,
    $semester
) {

    global $DB;

    $tahun = explode('/', $tahunajaran);

    if ($semester == 'Ganjil') {

        $start = strtotime($tahun[0] . '-07-01 00:00:00');
        $end   = strtotime($tahun[0] . '-12-31 23:59:59');

    } else {

        $start = strtotime($tahun[1] . '-01-01 00:00:00');
        $end   = strtotime($tahun[1] . '-06-30 23:59:59');
    }

    $sql = "
        SELECT MIN(timecreated)
        FROM {local_jurnalmengajar}
        WHERE timecreated BETWEEN ? AND ?
    ";

    $first = $DB->get_field_sql($sql, [$start, $end]);

    return $first ?: $start;
}
// ======================================
// Hitung total minggu semester
// ======================================
function jurnalmengajar_get_total_minggu_semester(
    $tahunajaran,
    $semester
) {

    global $DB;

    // awal semester dari jurnal pertama
    $awal = jurnalmengajar_get_awal_semester_dari_jurnal(
        $tahunajaran,
        $semester
    );

    $tahun = explode('/', $tahunajaran);

    // rentang semester
    if ($semester == 'Ganjil') {

        $start = strtotime($tahun[0] . '-07-01 00:00:00');
        $end   = strtotime($tahun[0] . '-12-31 23:59:59');

    } else {

        $start = strtotime($tahun[1] . '-01-01 00:00:00');
        $end   = strtotime($tahun[1] . '-06-30 23:59:59');
    }

    // jurnal terakhir semester
    $sql = "
        SELECT MAX(timecreated)
        FROM {local_jurnalmengajar}
        WHERE timecreated BETWEEN ? AND ?
    ";

    $terakhir = $DB->get_field_sql(
        $sql,
        [$start, $end]
    );

    // jika belum ada jurnal
    if (empty($terakhir)) {
        return 1;
    }

    // hitung selisih hari
    $selisih_hari = floor(
        ($terakhir - $awal) / 86400
    );

    $totalminggu = floor($selisih_hari / 7) + 1;

    // minimal 1 minggu
    if ($totalminggu < 1) {
        $totalminggu = 1;
    }

    return $totalminggu;
}

// ===============================
// Ambil semua kelas (cohort)
// ===============================
function jurnalmengajar_get_all_kelas() {
    global $DB;

    $sql = "SELECT name FROM {cohort} ORDER BY name ASC";
    $records = $DB->get_records_sql($sql);

    $kelas = [];
    foreach ($records as $r) {
        $kelas[$r->name] = $r->name;
    }

    return $kelas;
}

// ===============================
// Ambil siswa dari kelas (cohort)
// ===============================
function jurnalmengajar_get_siswa_by_kelas($kelas) {
    global $DB;

    return $DB->get_records_sql("
        SELECT u.id, u.firstname, u.lastname
        FROM {user} u
        JOIN {cohort_members} cm ON cm.userid = u.id
        JOIN {cohort} c ON c.id = cm.cohortid
        WHERE c.name = ?
        ORDER BY u.lastname
    ", [$kelas]);
}
function jurnalmengajar_get_stempel_path() {
    global $CFG;

    $context = context_system::instance();
    $fs = get_file_storage();

    $files = $fs->get_area_files(
        $context->id,
        'local_jurnalmengajar',
        'stempel',
        0,
        'itemid, filepath, filename',
        false
    );

    foreach ($files as $file) {
        $tempfile = $CFG->tempdir . '/' . $file->get_filename();
        $file->copy_content_to($tempfile);
        return $tempfile;
    }

    return '';
}
// ===============================
// Ambil NIS user dari profile field
// ===============================
function jurnalmengajar_get_nis_user($userid) {
    global $DB;

    return $DB->get_field_sql("
        SELECT d.data
        FROM {user_info_data} d
        JOIN {user_info_field} f ON f.id = d.fieldid
        WHERE f.shortname = 'nis' AND d.userid = ?
    ", [$userid]);
}
// ===============================
// Ambil range timestamp 1 bulan
// ===============================
function jurnalmengajar_get_range_bulan($bulan, $tahun) {
    if (empty($bulan) || empty($tahun)) {
        return [null, null];
    }
    
    $bulan = str_pad($bulan, 2, '0', STR_PAD_LEFT);

    $awal  = strtotime("$tahun-$bulan-01 00:00:00");
    $akhir = strtotime(date("Y-m-t", $awal) . ' 23:59:59');

    return [$awal, $akhir];
}
function jurnalmengajar_get_range($tanggal = null, $bulan = null, $tahun = null) {

    // PRIORITAS 1: tanggal spesifik
    if (!empty($tanggal) && strtotime($tanggal)) {
        return [
            strtotime("$tanggal 00:00:00"),
            strtotime("$tanggal 23:59:59")
        ];
    }

    // PRIORITAS 2: bulan
    if (!empty($bulan) && !empty($tahun)) {
        return jurnalmengajar_get_range_bulan($bulan, $tahun);
    }

    return [null, null];
}
// =================================
// Ambil ttd tandatangan kepsek
// =================================
function jurnalmengajar_get_ttd_path() {
    global $CFG;

    $context = context_system::instance();
    $fs = get_file_storage();

    $files = $fs->get_area_files(
        $context->id,
        'local_jurnalmengajar',
        'ttd',
        0,
        'itemid, filepath, filename',
        false
    );

    if ($files) {
        $file = reset($files);
        $temp = $CFG->tempdir . '/' . $file->get_filename();
        $file->copy_content_to($temp);
        return $temp;
    }

    return '';
}

// fungsi ambil kelas dari mapping wali kelas
function jurnalmengajar_get_kelas_wali($userid) {

    $json = get_config(
        'local_jurnalmengajar',
        'wali_kelas_mapping'
    );

    $mapping = json_decode($json, true);

    if (!is_array($mapping)) {
        return 0;
    }

    foreach ($mapping as $cohortid => $waliid) {
        if ((int)$waliid === (int)$userid) {
            return (int)$cohortid;
        }
    }

    return 0;
}
/// fungsi plugin file

function local_jurnalmengajar_pluginfile(
    $course,
    $cm,
    $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = []
) {

    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    /*
    =============================================
    HANYA FILEAREA YANG DIIZINKAN
    =============================================
    */

    if (!in_array($filearea, ['logo', 'banner'])) {
        return false;
    }

    $fs = get_file_storage();

    $filename = array_pop($args);

    $file = $fs->get_file(
        $context->id,
        'local_jurnalmengajar',
        $filearea,
        0,
        '/',
        $filename
    );

    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file(
        $file,
        0,
        0,
        false,
        $options
    );
}

/**
 * Daftar jenis pembinaan murid.
 *
 * Digunakan oleh:
 * - jurnal_form.php
 * - edit_pembinaan_mapel.php
 * - all_pembinaan_mapel.php (filter)
 */
function get_jenis_pembinaan_options() {
    return [
        'Kedisiplinan'     => 'Kedisiplinan',
        'Sikap & Karakter' => 'Sikap & Karakter',
        'Akademik'         => 'Akademik',
        'Kerapian'         => 'Kerapian',
        'Lainnya'          => 'Lainnya'
    ];
}
