<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Format tanggal Indonesia
 */
function tanggal_indo($timestamp = null, $mode = 'full') {
    $timestamp = $timestamp ?: time();

    $hari = [
        'Minggu', 'Senin', 'Selasa', 'Rabu',
        'Kamis', 'Jumat', 'Sabtu'
    ];

    $bulan = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];

    $hariIndex = (int) date('w', $timestamp);
    $tgl       = date('d', $timestamp);
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

    if ($mode == 'hari') {
        return $hari[$hariIndex];
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
 * Cek apakah KBM ditiadakan untuk kelas tertentu.
 */
function jurnalmengajar_is_kbm_ditiadakan($kelas, $tanggal = null) {

    if (empty($tanggal)) {
        $tanggal = date('Y-m-d');
    }

    $config = get_config('local_jurnalmengajar', 'kbm_ditiadakan_kelas');

    if (empty($config)) {
        return false;
    }

    if (!preg_match('/^(XII|XI|X|IX|VIII|VII|VI|V|IV|III|II|I)/i', trim($kelas), $m)) {
        return false;
    }

    $jenjang = strtoupper($m[1]);
    $lines = preg_split('/\r\n|\r|\n/', $config);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (!str_contains($line, '|')) continue;

        list($kelasconfig, $tanggalconfig) = explode('|', $line, 2);

        if (strtoupper(trim($kelasconfig)) !== $jenjang) continue;

        $tanggalconfig = trim($tanggalconfig);

        if (stripos($tanggalconfig, 's/d') !== false) {
            list($awal, $akhir) = explode('s/d', $tanggalconfig);
            $awal  = trim($awal);
            $akhir = trim($akhir);

            if (strtotime($tanggal) >= strtotime($awal) && strtotime($tanggal) <= strtotime($akhir)) {
                return true;
            }
        } else {
            if ($tanggal === $tanggalconfig) {
                return true;
            }
        }
    }

    return false;
}

/**
 * CEK JAM TIDAK BELAJAR (KEGIATAN SPESIFIK)
 * Membaca data kegiatan dari tabel agar dihitung sebagai libur / pengurang JP.
 */
function jurnalmengajar_is_jam_kegiatan($tanggal_timestamp, $kelas, $jamke) {
    global $DB;
    static $cache = [];
    static $cohortmap = null;

    if ($cohortmap === null) {
        $cohortmap = [];
        $cohorts = $DB->get_records('cohort', null, '', 'id,name');
        foreach ($cohorts as $c) {
            $cohortmap[$c->id] = $c->name;
        }
    }

    if (!isset($cache[$tanggal_timestamp])) {
        $kegiatan = $DB->get_records('local_jurnalmengajar_kegiatan', ['tanggal' => $tanggal_timestamp]);
        $global_jam = [];
        $kelas_jam = [];

        if ($kegiatan) {
            foreach ($kegiatan as $keg) {
                $jam_batal = array_filter(array_map('trim', explode(',', $keg->jamke)));
                
                if (empty($keg->kelas)) {
                    $global_jam = array_merge($global_jam, $jam_batal);
                } else {
                    $namakelas = isset($cohortmap[$keg->kelas]) ? $cohortmap[$keg->kelas] : $keg->kelas;
                    
                    if (!isset($kelas_jam[$namakelas])) {
                        $kelas_jam[$namakelas] = [];
                    }
                    $kelas_jam[$namakelas] = array_merge($kelas_jam[$namakelas], $jam_batal);
                    
                    // Simpan juga versi ID-nya sebagai fallback
                    if (!isset($kelas_jam[$keg->kelas])) {
                        $kelas_jam[$keg->kelas] = [];
                    }
                    $kelas_jam[$keg->kelas] = array_merge($kelas_jam[$keg->kelas], $jam_batal);
                }
            }
        }
        $cache[$tanggal_timestamp] = [
            'global' => array_unique($global_jam),
            'kelas' => $kelas_jam
        ];
    }

    $jam_string = (string)$jamke;
    
    // Cek kegiatan global
    if (in_array($jam_string, $cache[$tanggal_timestamp]['global'])) {
        return true;
    }

    // Cek kegiatan per kelas
    if (isset($cache[$tanggal_timestamp]['kelas'][$kelas]) && in_array($jam_string, $cache[$tanggal_timestamp]['kelas'][$kelas])) {
        return true;
    }

    return false;
}

/**
 * Ambil cutoff berdasarkan kode kelas
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

// Hitung pengurang target karena libur, asesmen, dan kegiatan spesifik
function jurnalmengajar_get_pengurang_target_libur($userid, $tanggal_awal, $tanggal_akhir) {
    require_once(__DIR__.'/jadwal_acuan_lib.php');

    $pengurang = 0;
    $tanggallibur = get_config('local_jurnalmengajar', 'tanggallibur');
    $tanggalasesmen = get_config('local_jurnalmengajar', 'tanggalasesmen');
    $kbmditiadakan = get_config('local_jurnalmengajar', 'kbm_ditiadakan_kelas');

    $jadwal = jurnalmengajar_get_jadwal_acuan();

    if (!empty($tanggallibur)) {
        $lines = preg_split('/\r\n|\r|\n/', $tanggallibur);
    } else {
        $lines = [];
    }

    // 1. PENGURANG LIBUR UMUM (Konfigurasi)
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line == '') continue;

        $daftar_tanggal = [];

        if (stripos($line, 's/d') !== false) {
            list($start, $end) = explode('s/d', $line);
            $start_ts = strtotime(trim($start));
            $end_ts   = strtotime(trim($end));

            if (!$start_ts || !$end_ts) continue;

            for ($t = $start_ts; $t <= $end_ts; $t += 86400) {
                $daftar_tanggal[] = $t;
            }
        } else {
            $timestamp = strtotime($line);
            if (!$timestamp) continue;
            $daftar_tanggal[] = $timestamp;
        }

        foreach ($daftar_tanggal as $timestamp) {
            if ($timestamp < $tanggal_awal || $timestamp > $tanggal_akhir) continue;

            $hari = jurnalmengajar_get_hari_by_timestamp($timestamp);

            foreach ($jadwal as $j) {
                if ($j['userid'] != $userid) continue;
                if ($j['hari'] != $hari) continue;

                $kelas = isset($j['kelas']) ? trim($j['kelas']) : '';
                $kelas_level = null;

                if (preg_match('/\b(VI|IX|XII)\b/i', $kelas, $match)) {
                    $kelas_level = strtoupper($match[1]);
                }

                $cutoff = null;
                if ($kelas_level) {
                    $cutoff = jurnalmengajar_get_cutoff_by_kelas($kelas_level, $timestamp);
                }

                if (!empty($cutoff) && $timestamp >= $cutoff) continue;
                
                if (jurnalmengajar_is_kbm_ditiadakan($kelas, date('Y-m-d', $timestamp))) continue;

                $pengurang++;
            }
        }
    }

    // 2. PENGURANG ASESMEN
    for ($t = strtotime(date('Y-m-d', $tanggal_awal)); $t <= strtotime(date('Y-m-d', $tanggal_akhir)); $t += 86400) {
        $tanggal = date('Y-m-d', $t);

        if (!jurnalmengajar_is_tanggal_asesmen($tanggal)) continue;

        $hari = jurnalmengajar_get_hari_by_timestamp($t);

        foreach ($jadwal as $j) {
            if ($j['userid'] != $userid) continue;
            if ($j['hari'] != $hari) continue;

            $kelas = isset($j['kelas']) ? trim($j['kelas']) : '';
            $kelas_level = null;

            if (preg_match('/\b(VI|IX|XII)\b/i', $kelas, $match)) {
                $kelas_level = strtoupper($match[1]);
            }

            $cutoff = null;
            if ($kelas_level) {
                $cutoff = jurnalmengajar_get_cutoff_by_kelas($kelas_level, $t);
            }

            if (!empty($cutoff) && $t >= $cutoff) continue;
            if (jurnalmengajar_is_kbm_ditiadakan($kelas, $tanggal)) continue;

            $pengurang++;
        }
    }
    
    // 3. PENGURANG KEGIATAN SPESIFIK (JAM TIDAK BELAJAR)
    for ($t = strtotime(date('Y-m-d', $tanggal_awal)); $t <= strtotime(date('Y-m-d', $tanggal_akhir)); $t += 86400) {
        $tanggal = date('Y-m-d', $t);

        // Jangan hitung ganda jika hari ini sudah libur atau asesmen
        if (jurnalmengajar_cek_libur($tanggal) || jurnalmengajar_is_tanggal_asesmen($tanggal)) {
            continue;
        }

        $hari = jurnalmengajar_get_hari_by_timestamp($t);

        foreach ($jadwal as $j) {
            if ($j['userid'] != $userid) continue;
            if ($j['hari'] != $hari) continue;

            $kelas = isset($j['kelas']) ? trim($j['kelas']) : '';
            $jamke = isset($j['jamke']) ? trim($j['jamke']) : null;
            $kelas_level = null;

            if (preg_match('/\b(VI|IX|XII)\b/i', $kelas, $match)) {
                $kelas_level = strtoupper($match[1]);
            }

            $cutoff = null;
            if ($kelas_level) {
                $cutoff = jurnalmengajar_get_cutoff_by_kelas($kelas_level, $t);
            }
            if (!empty($cutoff) && $t >= $cutoff) continue;

            // Jangan hitung ganda dengan KBM ditiadakan
            if (jurnalmengajar_is_kbm_ditiadakan($kelas, $tanggal)) {
                continue;
            }

            // Jika jam ini masuk di data kegiatan, tambahkan sebagai pengurang
            if ($jamke !== null && jurnalmengajar_is_jam_kegiatan($t, $kelas, $jamke)) {
                $pengurang++;
            }
        }
    }

    return $pengurang;
}

// ===============================
// Fungsi ambil beban mengajar guru harian
// ===============================
function jurnalmengajar_get_beban_jam_guru_by_date($timestamp) {
    require_once(__DIR__.'/jadwal_acuan_lib.php');

    $jadwal = jurnalmengajar_get_jadwal_acuan();
    $beban = [];

    if (empty($timestamp) || !is_numeric($timestamp)) {
        $timestamp = time();
    }

    $maphari = [
        'Senin' => 0, 'Selasa' => 1, 'Rabu' => 2,
        'Kamis' => 3, 'Jumat' => 4, 'Sabtu' => 5, 'Minggu' => 6,
    ];

    foreach ($jadwal as $j) {
        if (empty($j['userid'])) continue;

        $userid = $j['userid'];
        $kelas  = isset($j['kelas']) ? trim($j['kelas']) : '';

        $kelas_level = null;
        if (preg_match('/\b(VI|IX|XII)\b/i', $kelas, $match)) {
            $kelas_level = strtoupper($match[1]);
        }

        $offset = $maphari[$j['hari']] ?? 0;
        $timestampjadwal = strtotime("+$offset day", $timestamp);

$cutoff = null;
        if ($kelas_level) {
            $cutoff = jurnalmengajar_get_cutoff_by_kelas($kelas_level, $timestampjadwal);
            if (!empty($cutoff) && $timestampjadwal >= $cutoff) continue;
        }

        // --- TAMBAHAN PERBAIKAN BUG: Cek Tanggal Libur & Asesmen ---
        $tanggal_format_jadwal = date('Y-m-d', $timestampjadwal);
        if (jurnalmengajar_cek_libur($tanggal_format_jadwal)) {
            continue;
        }
        if (jurnalmengajar_is_tanggal_asesmen($tanggal_format_jadwal)) {
            continue;
        }
        // -----------------------------------------------------------

        if (jurnalmengajar_is_kbm_ditiadakan($kelas, $tanggal_format_jadwal)) {
            continue;
        }
        
        // 🔍 FILTER JAM TIDAK BELAJAR KEGIATAN SPESIFIK
        $jamke = isset($j['jamke']) ? trim($j['jamke']) : null;
        if ($jamke !== null && jurnalmengajar_is_jam_kegiatan($timestampjadwal, $kelas, $jamke)) {
            continue;
        }

        if (!isset($beban[$userid])) {
            $beban[$userid] = 0;
        }
        $beban[$userid]++;
    }

    return $beban;
}

/**
 * Hitung beban mengajar guru selama 1 bulan.
 */
function jurnalmengajar_get_beban_jam_guru_bulan($bulan, $tahun) {
    require_once(__DIR__ . '/jadwal_acuan_lib.php');

    $jadwal = jurnalmengajar_get_jadwal_acuan();
    $beban = [];
    list($awal, $akhir) = jurnalmengajar_get_range_bulan($bulan, $tahun);
    $cutoff_cache = [];

    for ($ts = $awal; $ts <= $akhir; $ts += 86400) {
        $tanggal = date('Y-m-d', $ts);
        $hari = jurnalmengajar_get_hari_by_timestamp($ts);

        if (!array_key_exists($hari, jurnalmengajar_get_hari_sekolah())) continue;
        if (jurnalmengajar_cek_libur($tanggal)) continue;
        if (jurnalmengajar_is_tanggal_asesmen($tanggal)) continue;

        foreach ($jadwal as $j) {
            if ($j['hari'] != $hari) continue;

            $userid = $j['userid'];
            if (empty($userid)) continue;

            $kelas = trim($j['kelas'] ?? '');
            $kelas_level = null;

            if (preg_match('/\b(VI|IX|XII)\b/i', $kelas, $m)) {
                $kelas_level = strtoupper($m[1]);
            }

            if ($kelas_level) {
                if (!isset($cutoff_cache[$kelas_level])) {
                    $cutoff_cache[$kelas_level] = jurnalmengajar_get_cutoff_by_kelas($kelas_level, $ts);
                }
                $cutoff = $cutoff_cache[$kelas_level];
                if (!empty($cutoff) && $ts >= $cutoff) continue;
            }

            if (jurnalmengajar_is_kbm_ditiadakan($kelas, $tanggal)) {
                continue;
            }

            // 🔍 FILTER JAM TIDAK BELAJAR KEGIATAN SPESIFIK
            $jamke = isset($j['jamke']) ? trim($j['jamke']) : null;
            if ($jamke !== null && jurnalmengajar_is_jam_kegiatan($ts, $kelas, $jamke)) {
                continue;
            }

            if (!isset($beban[$userid])) {
                $beban[$userid] = 0;
            }
            $beban[$userid]++;
        }
    }

    return $beban;
}

// ======================================
// Load snapshot beban mengajar semester
// ======================================
function jurnalmengajar_load_beban_snapshot($tahunajaran, $semester) {
    if (empty($tahunajaran) || empty($semester)) return [];
    $tahunajaran_file = str_replace('/', '_', trim($tahunajaran));
    $semester_file = strtolower(trim($semester));
    $filepath = __DIR__ . '/data/beban/beban_' . $tahunajaran_file . '_' . $semester_file . '.json';
    
    if (!file_exists($filepath)) return [];
    
    $json = file_get_contents($filepath);
    if ($json === false) return [];
    
    $data = json_decode($json, true);
    if (!is_array($data)) return [];
    
    return $data;
}

// ======================================
// Generate snapshot beban semester
// ======================================
function jurnalmengajar_generate_beban_snapshot($tahunajaran, $semester) {
    global $CFG;

    $beban = jurnalmengajar_get_beban_jam_guru_by_date(time());
    if (empty($beban)) return false;

    $tahunajaran_file = str_replace('/', '_', trim($tahunajaran));
    $semester_file = strtolower(trim($semester));
    $folder = __DIR__ . '/data/beban';

    if (!file_exists($folder)) mkdir($folder, 0755, true);

    $filepath = $folder . '/beban_' . $tahunajaran_file . '_' . $semester_file . '.json';
    $json = json_encode($beban, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    $result = file_put_contents($filepath, $json);
    if ($result === false) return false;
    
    return true;
}

// ======================================
// Deteksi tahun ajaran dari timestamp
// ======================================
function jurnalmengajar_get_tahunajaran_by_timestamp($timestamp = null) {
    if (empty($timestamp)) $timestamp = time();

    $bulan = (int)date('n', $timestamp);
    $tahun = (int)date('Y', $timestamp);

    if ($bulan >= 7) return $tahun . '/' . ($tahun + 1);
    
    return ($tahun - 1) . '/' . $tahun;
}

// ======================================
// Ambil awal semester dari jurnal pertama
// ======================================
function jurnalmengajar_get_awal_semester_dari_jurnal($tahunajaran, $semester) {
    global $DB;
    $tahun = explode('/', $tahunajaran);

    if ($semester == 'Ganjil') {
        $start = strtotime($tahun[0] . '-07-01 00:00:00');
        $end   = strtotime($tahun[0] . '-12-31 23:59:59');
    } else {
        $start = strtotime($tahun[1] . '-01-01 00:00:00');
        $end   = strtotime($tahun[1] . '-06-30 23:59:59');
    }

    $sql = "SELECT MIN(timecreated) FROM {local_jurnalmengajar} WHERE timecreated BETWEEN ? AND ?";
    $first = $DB->get_field_sql($sql, [$start, $end]);

    return $first ?: $start;
}

// ======================================
// Hitung total minggu semester
// ======================================
function jurnalmengajar_get_total_minggu_semester($tahunajaran, $semester) {
    global $DB;

    $awal = jurnalmengajar_get_awal_semester_dari_jurnal($tahunajaran, $semester);
    $tahun = explode('/', $tahunajaran);

    if ($semester == 'Ganjil') {
        $start = strtotime($tahun[0] . '-07-01 00:00:00');
        $end   = strtotime($tahun[0] . '-12-31 23:59:59');
    } else {
        $start = strtotime($tahun[1] . '-01-01 00:00:00');
        $end   = strtotime($tahun[1] . '-06-30 23:59:59');
    }

    $sql = "SELECT MAX(timecreated) FROM {local_jurnalmengajar} WHERE timecreated BETWEEN ? AND ?";
    $terakhir = $DB->get_field_sql($sql, [$start, $end]);

    if (empty($terakhir)) return 1;

    $selisih_hari = floor(($terakhir - $awal) / 86400);
    $totalminggu = floor($selisih_hari / 7) + 1;

    if ($totalminggu < 1) $totalminggu = 1;

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
    $files = $fs->get_area_files($context->id, 'local_jurnalmengajar', 'stempel', 0, 'itemid, filepath, filename', false);

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
    if (empty($bulan) || empty($tahun)) return [null, null];
    
    $bulan = str_pad($bulan, 2, '0', STR_PAD_LEFT);
    $awal  = strtotime("$tahun-$bulan-01 00:00:00");
    $akhir = strtotime(date("Y-m-t", $awal) . ' 23:59:59');

    return [$awal, $akhir];
}

function jurnalmengajar_get_range($tanggal = null, $bulan = null, $tahun = null) {
    if (!empty($tanggal) && strtotime($tanggal)) {
        return [strtotime("$tanggal 00:00:00"), strtotime("$tanggal 23:59:59")];
    }
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
    $files = $fs->get_area_files($context->id, 'local_jurnalmengajar', 'ttd', 0, 'itemid, filepath, filename', false);

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
    $json = get_config('local_jurnalmengajar', 'wali_kelas_mapping');
    $mapping = json_decode($json, true);

    if (!is_array($mapping)) return 0;

    foreach ($mapping as $cohortid => $waliid) {
        if ((int)$waliid === (int)$userid) return (int)$cohortid;
    }
    return 0;
}

// fungsi wali kelas
function is_wali_kelas($userid) {
    $kelas = jurnalmengajar_get_kelas_wali($userid);
    return !empty($kelas);
}

/// fungsi plugin file
function local_jurnalmengajar_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM) return false;
    if (!in_array($filearea, ['logo', 'banner'])) return false;

    $fs = get_file_storage();
    $filename = array_pop($args);
    $file = $fs->get_file($context->id, 'local_jurnalmengajar', $filearea, 0, '/', $filename);

    if (!$file || $file->is_directory()) send_file_not_found();

    send_stored_file($file, 0, 0, false, $options);
}

/**
 * Daftar jenis pembinaan murid.
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

// MAPPING GURU BK
function is_guru_bk($userid) {
    $json = get_config('local_jurnalmengajar', 'guru_bk_mapping');
    $mapping = json_decode($json, true);

    if (!is_array($mapping)) return false;

    $mapping = array_map('intval', $mapping);
    return in_array((int)$userid, $mapping, true);
}

/**
 * Ambil status guru tidak hadir.
 */
function jurnalmengajar_get_status_takhadir($userid, $tanggal = null) {
    global $DB;
    if (empty($tanggal)) $tanggal = date('Y-m-d');

    $timestamp = strtotime($tanggal . ' 12:00:00');
    $sql = "
        SELECT status
        FROM {local_jurnalmengajar_kehadiran}
        WHERE userid = :userid
          AND tanggalmulai <= :tsmulai
          AND tanggalselesai >= :tsselesai
        ORDER BY id DESC
    ";

    return $DB->get_field_sql($sql, [
        'userid'    => $userid,
        'tsmulai'   => $timestamp,
        'tsselesai' => $timestamp
    ]) ?: false;
}

/**
 * Cek apakah murid termasuk Kristen.
 */
function jurnalmengajar_is_kristen(int $userid): bool {
    global $DB;
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        $records = $DB->get_records('local_jurnalmengajar_kristen');
        foreach ($records as $record) {
            $cache[$record->userid] = true;
        }
    }
    return isset($cache[$userid]);
}

/**
 * Cek apakah murid merupakan peserta mapel.
 */
function jurnalmengajar_is_peserta_mapel(int $userid, string $mapel): bool {
    $iskristen = jurnalmengajar_is_kristen($userid);
    $mapel = core_text::strtolower(trim($mapel));
    $mapel = preg_replace('/\s+/', ' ', $mapel);

    static $mapelislam = ['pendidikan agama islam dan budi pekerti', 'pendidikan al quran'];
    static $mapelkristen = ['pendidikan agama kristen dan budi pekerti', 'pendalaman alkitab'];

    if (in_array($mapel, $mapelkristen, true)) return $iskristen;
    if (in_array($mapel, $mapelislam, true)) return !$iskristen;

    return true;
}

/**
 * Filter daftar peserta sesuai mapel.
 */
function jurnalmengajar_filter_peserta_mapel(array $users, string $mapel): array {
    foreach ($users as $key => $user) {
        if (is_object($user)) {
            if (isset($user->id)) $userid = (int)$user->id;
            else if (isset($user->userid)) $userid = (int)$user->userid;
            else { unset($users[$key]); continue; }
        } else if (is_numeric($user)) {
            $userid = (int)$user;
        } else {
            unset($users[$key]);
            continue;
        }

        if (!jurnalmengajar_is_peserta_mapel($userid, $mapel)) {
            unset($users[$key]);
        }
    }
    return $users;
}
