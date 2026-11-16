<?php

require('../../config.php');

require_once(__DIR__.'/jadwal_acuan_lib.php');
require_once(__DIR__.'/jam_pelajaran_lib.php');
require_once(__DIR__.'/jadwal_asesmen_lib.php');
require_once(__DIR__.'/lib.php');
require_once($CFG->libdir . '/filelib.php');

$PAGE->set_url(new moodle_url('/local/jurnalmengajar/tv.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('embedded');
$PAGE->set_title('TV');
$PAGE->set_heading('TV');

/*
=====================================================
IDENTITAS SEKOLAH
=====================================================
*/

$namasekolah =
    get_config(
        'local_jurnalmengajar',
        'nama_sekolah'
    );

$tahunajaran =
    get_config(
        'local_jurnalmengajar',
        'tahun_ajaran'
    );

$judulasesmen = get_config(
    'local_jurnalmengajar',
    'judulasesmen'
    );

/*
=====================================================
LOGO SEKOLAH
=====================================================
*/

$context = context_system::instance();

$fs = get_file_storage();

$files = $fs->get_area_files(
    $context->id,
    'local_jurnalmengajar',
    'logo',
    0,
    'itemid, filepath, filename',
    false
);

$logourl = '';

foreach ($files as $file) {

    $logourl = moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        $file->get_component(),
        $file->get_filearea(),
        $file->get_itemid(),
        $file->get_filepath(),
        $file->get_filename()
    );

    break;
}

/*
=====================================================
HARI SEKOLAH
=====================================================
*/

$harisekolah =
    get_config(
        'local_jurnalmengajar',
        'harisekolah'
    );

$daftarhari = array_filter(
    array_map(
        'trim',
        explode(',', $harisekolah)
    )
);

/*
=====================================================
DATA DASAR
=====================================================
*/

$jadwal =
    jurnalmengajar_get_jadwal_acuan();

$hari_ini =
    jurnalmengajar_get_hari_ini();

$now = date('H:i:s');
$tanggalindo =
    tanggal_indo(time(), 'judul');

// JAM IKUT SERVER
$server_h = date('H');
$server_i = date('i');
$server_s = date('s');
/*
=====================================================
MODE SIMULASI
=====================================================
*/

$issimulasi = false;

if (!empty($_GET['hari'])) {

    $hari_ini = $_GET['hari'];
    $issimulasi = true;
}

if (!empty($_GET['jam'])) {

    $now = $_GET['jam'];
    $issimulasi = true;
}

$jam_pelajaran =
    jurnalmengajar_generate_jam_hari($hari_ini);

/*
=====================================================
VALIDASI HARI SEKOLAH
=====================================================
*/

$is_hari_sekolah =
    in_array($hari_ini, $daftarhari);

/*
=====================================================
TANGGAL LIBUR DARI SETTING PLUGIN
Format:
2026-06-01
2026-06-17
2026-12-25
=====================================================
*/

$tanggallibur =
    get_config(
        'local_jurnalmengajar',
        'tanggallibur'
    );

$daftarlibur = array_filter(
    array_map(
        'trim',
        preg_split(
            '/\r\n|\r|\n/',
            $tanggallibur
        )
    )
);

/*
=====================================================
TANGGAL HARI INI
=====================================================
*/

$tanggalhariini = date('Y-m-d');

/*
=====================================================
MODE SIMULASI TANGGAL
=====================================================
*/

if (!empty($_GET['tanggal'])) {

    $tanggalhariini = $_GET['tanggal'];

    $issimulasi = true;
}

$is_tanggal_libur = false;

$mode_tv = 'KBM';

/*
=====================================================
DETEKSI BANNER
SUPPORT:
2026-06-01|Hari Lahir Pancasila|pancasila

2026-06-02 s/d 2026-06-12|ASESMEN AKHIR SEMESTER|asesmen
=====================================================
*/

$banneraktif = '';
$judulbanner = '';
$bannerurls = [];

$bannercfg = get_config(
    'local_jurnalmengajar',
    'banner_tv'
);

if (!empty($bannercfg)) {

    foreach (explode("\n", $bannercfg) as $line) {

        $line = trim($line);

        if (empty($line)) {
            continue;
        }

        $parts = explode('|', $line);

        if (count($parts) != 3) {
            continue;
        }

        $rentang = trim($parts[0]);

        $judul = trim($parts[1]);

        $prefixbanner = trim($parts[2]);

        $bannerurls = [];

        /*
        =============================================
        RENTANG TANGGAL
        =============================================
        */

        if (stripos($rentang, 's/d') !== false) {

            $tanggal = explode('s/d', $rentang);

            if (count($tanggal) == 2) {

                $mulai = trim($tanggal[0]);

                $selesai = trim($tanggal[1]);

                if (
                    $tanggalhariini >= $mulai
                    &&
                    $tanggalhariini <= $selesai
                ) {

                    $allfiles = $fs->get_area_files(
                        $context->id,
                        'local_jurnalmengajar',
                        'banner',
                        0,
                        'filename',
                        false
                    );

                    foreach ($allfiles as $storedfile) {

                        $filename =
                            $storedfile->get_filename();

                        if (
                            stripos(
                                $filename,
                                $prefixbanner
                            ) === 0
                        ) {

                            $bannerurls[] =
                                moodle_url::make_pluginfile_url(
                                    $storedfile->get_contextid(),
                                    $storedfile->get_component(),
                                    $storedfile->get_filearea(),
                                    $storedfile->get_itemid(),
                                    $storedfile->get_filepath(),
                                    $storedfile->get_filename()
                                )->out(false);
                        }
                    }
                    sort($bannerurls);
                    if (!empty($bannerurls)) {

                        $judulbanner = $judul;

                        $banneraktif =
                            $bannerurls[0];

                        break;
                    }
                }
            }

        } else {

            /*
            =============================================
            TANGGAL TUNGGAL
            =============================================
            */

            if ($rentang == $tanggalhariini) {

                $allfiles = $fs->get_area_files(
                    $context->id,
                    'local_jurnalmengajar',
                    'banner',
                    0,
                    'filename',
                    false
                );

                foreach ($allfiles as $storedfile) {

                    $filename =
                        $storedfile->get_filename();

                    if (
                        stripos(
                            $filename,
                            $prefixbanner
                        ) === 0
                    ) {

                        $bannerurls[] =
                            moodle_url::make_pluginfile_url(
                                $storedfile->get_contextid(),
                                $storedfile->get_component(),
                                $storedfile->get_filearea(),
                                $storedfile->get_itemid(),
                                $storedfile->get_filepath(),
                                $storedfile->get_filename()
                            )->out(false);
                    }
                }
                sort($bannerurls);
                if (!empty($bannerurls)) {

                    $judulbanner = $judul;

                    $banneraktif =
                        $bannerurls[0];

                    break;
                }
            }
        }
    }
}


/*
=====================================================
MODE ASESMEN
=====================================================
*/

$is_asesmen = false;

$tanggalasesmen =
    get_config(
        'local_jurnalmengajar',
        'tanggalasesmen'
    );

if (!empty($tanggalasesmen)) {

    $lines = explode("\n", $tanggalasesmen);

    foreach ($lines as $line) {

        $line = trim($line);

        if (empty($line)) {
            continue;
        }

        if (stripos($line, 's/d') !== false) {

            $parts = explode('s/d', $line);

            if (count($parts) == 2) {

                $mulai = trim($parts[0]);

                $selesai = trim($parts[1]);

                if (
                    $tanggalhariini >= $mulai
                    &&
                    $tanggalhariini <= $selesai
                ) {

                    $is_asesmen = true;
                    break;
                }
            }

        } else {

            if ($tanggalhariini == $line) {

                $is_asesmen = true;
                break;
            }
        }
    }
}

/*
=====================================================
JADWAL KHUSUS TV
Format:
2026-06-02 s/d 2026-06-12|ASESMEN AKHIR TAHUN|Tidak Ada KBM Reguler
=====================================================
*/

$jadwalkhusus_text =
    get_config(
        'local_jurnalmengajar',
        'jadwal_khusus_tv'
    );

$jadwalkhusus = [];

$is_jadwal_khusus = false;

$judulkhusus = '';
$subjudulkhusus = '';

if (!empty($jadwalkhusus_text)) {

    $lines = explode("\n", $jadwalkhusus_text);

    foreach ($lines as $line) {

        $line = trim($line);

        if (empty($line)) {
            continue;
        }

        $parts = explode('|', $line);

        if (count($parts) < 3) {
            continue;
        }

        $rentang = trim($parts[0]);

        $judul = trim($parts[1]);

        $subjudul = trim($parts[2]);

        if (stripos($rentang, 's/d') !== false) {

            $tanggal = explode('s/d', $rentang);

            if (count($tanggal) == 2) {

                $mulai = trim($tanggal[0]);

                $selesai = trim($tanggal[1]);

                if (
                    $tanggalhariini >= $mulai
                    &&
                    $tanggalhariini <= $selesai
                ) {

                    $is_jadwal_khusus = true;

                    $judulkhusus = $judul;

                    $subjudulkhusus = $subjudul;

                    break;
                }
            }
        }
    }
}

/*
=====================================================
CEK LIBUR
SUPPORT:
2026-05-27
2026-05-27 s/d 2026-05-28
=====================================================
*/

foreach ($daftarlibur as $libur) {

    /*
    =============================================
    RENTANG TANGGAL
    =============================================
    */

    if (stripos($libur, 's/d') !== false) {

        $parts = explode('s/d', $libur);

        if (count($parts) == 2) {

            $mulai =
                trim($parts[0]);

            $selesai =
                trim($parts[1]);

            if (
                $tanggalhariini >= $mulai
                &&
                $tanggalhariini <= $selesai
            ) {

                $is_tanggal_libur = true;
                break;
            }
        }

    } else {

        /*
        =============================================
        TANGGAL TUNGGAL
        =============================================
        */

        if ($tanggalhariini == $libur) {

            $is_tanggal_libur = true;
            break;
        }
    }
}


/*
=====================================================
DATA ASESMEN
=====================================================
*/

/*
=============================================
KHUSUS JUMAT
=============================================
*/

$hari_en =
    strtolower(
        date(
            'l',
            strtotime($tanggalhariini)
        )
    );

if ($hari_en == 'friday') {

    $mulaipukul =
        get_config(
            'local_jurnalmengajar',
            'asesmen_mulai_jumat'
        ) ?: '07:30';

    $jumlahsesi =
        get_config(
            'local_jurnalmengajar',
            'asesmen_jumlah_sesi_jumat'
        ) ?: 5;

} else {

    $mulaipukul =
        get_config(
            'local_jurnalmengajar',
            'asesmen_mulai'
        ) ?: '08:00';

    $jumlahsesi =
        get_config(
            'local_jurnalmengajar',
            'asesmen_jumlah_sesi'
        ) ?: 10;
}

$sesiasesmen =
    jurnalmengajar_generate_sesi_asesmen(
        $mulaipukul,
        $jumlahsesi
    );

$jadwalpengawas = [];

$jsonfile =
    __DIR__.'/jadwal_pengawas.json';

if (file_exists($jsonfile)) {

    $json =
        file_get_contents($jsonfile);

    $jadwalpengawas =
        json_decode($json, true);
}

/*
=====================================================
DETEKSI SESI AKTIF
=====================================================
*/

$sesiaktif = null;
$sesiberikut = null;

foreach ($sesiasesmen as $nomor => $sesi) {

    if (
        $now >= $sesi['mulai']
        &&
        $now < $sesi['selesai']
    ) {

        $sesiaktif = $nomor;

        if (
    !empty($sesiasesmen[$nomor + 1])
    ) {

    $sesiberikut = $nomor + 1;

    } else {

    $sesiberikut = null;
    }

        break;
    }
}

/*
=====================================================
JIKA BELUM MASUK SESI PERTAMA
=====================================================
*/

$sebelumsesipertama = false;

if (!$sesiaktif) {

    foreach ($sesiasesmen as $nomor => $sesi) {

        if ($now < $sesi['mulai']) {

            $sesiberikut = $nomor;

            /*
            =====================================
            SEBELUM SESI PERTAMA
            =====================================
            */

            if ($nomor == 1) {

                $sebelumsesipertama = true;
            }

            break;
        }
    }
}

/*
=====================================================
PENGAWAS AKTIF
=====================================================
*/

$pengawasaktif = [];

if (
    !empty($jadwalpengawas[$tanggalhariini][$sesiaktif])
) {

    $pengawasaktif =
        $jadwalpengawas[$tanggalhariini][$sesiaktif];
}

/*
=====================================================
PENGAWAS BERIKUTNYA
=====================================================
*/

$pengawasberikut = [];

if (
    !empty($jadwalpengawas[$tanggalhariini][$sesiberikut])
) {

    $pengawasberikut =
        $jadwalpengawas[$tanggalhariini][$sesiberikut];
}

$countdowntitle =
    'SISA WAKTU MULAI SESI BERIKUTNYA';
    
/*
=====================================================
COUNTDOWN ASESMEN
=====================================================
*/

$sisadetikasesmen = 0;

if (
    (
        $sesiaktif
        &&
        !empty($sesiasesmen[$sesiaktif])
    )
    ||
    (
        !$sesiaktif
        &&
        !empty($sesiasesmen[$sesiberikut])
    )
) {

    $targetjam = null;

    /*
    =============================================
    SEBELUM SESI PERTAMA
    =============================================
    */

    if (
        $sebelumsesipertama
        &&
        !empty($sesiasesmen[$sesiberikut])
    ) {

        $targetjam =
            $sesiasesmen[$sesiberikut]['mulai'];

    } else {

        /*
        =========================================
        JIKA ADA SESI BERIKUTNYA
        =========================================
        */

        if (
            !empty($sesiasesmen[$sesiberikut])
        ) {

            $targetjam =
                $sesiasesmen[$sesiberikut]['mulai'];

        } else {

            /*
            =====================================
            JIKA SESI TERAKHIR
            =====================================
            */

            $targetjam =
                $sesiasesmen[$sesiaktif]['selesai'];
        }
    }

    $timestamp_target =
        strtotime(
            $tanggalhariini . ' ' . $targetjam
        );

    $timestamp_now =
        strtotime(
            $tanggalhariini . ' ' . $now
        );

    $sisadetikasesmen =
        max(
            0,
            $timestamp_target - $timestamp_now
        );
}

/*
=====================================================
SESI TERAKHIR
=====================================================
*/

if (
    $sesiaktif
    &&
    empty($sesiasesmen[$sesiberikut])
) {

    $countdowntitle =
        'SISA WAKTU MENUJU SELESAI ASESMEN';
}

/*
=====================================================
MODE TV
=====================================================
*/

if ($is_tanggal_libur || !$is_hari_sekolah) {

    $mode_tv = 'LIBUR';

} else if ($is_jadwal_khusus) {

    $mode_tv = 'KHUSUS';

} else if ($is_asesmen) {

    $mode_tv = 'ASESMEN';

} else {

    $mode_tv = 'KBM';
}

/*
=====================================================
GURU PIKET
=====================================================
*/

$hari_key = strtolower($hari_ini);

$config_piket =
    'guru_piket_' . $hari_key;

$gurupiket_text =
    get_config(
        'local_jurnalmengajar',
        $config_piket
    );

$gurupiket = [];

if (!empty($gurupiket_text)) {

    $gurupiket = array_filter(
        array_map(
            'trim',
            explode("\n", $gurupiket_text)
        )
    );
}

/*
=====================================================
PENGUMUMAN
=====================================================
*/

$pengumuman_text =
    get_config(
        'local_jurnalmengajar',
        'pengumuman_tv'
    );

$pengumuman = [];

if (!empty($pengumuman_text)) {

    $pengumuman = array_filter(
        array_map(
            'trim',
            explode("\n", $pengumuman_text)
        )
    );
}

/*
=====================================================
CUTOFF KBM KELAS
Format:
XII|2026-04-06
XI|2026-05-01
=====================================================
*/

$cutoff_text =
    get_config(
        'local_jurnalmengajar',
        'cutoff_kelas'
    );

$cutoffkelas = [];

if (!empty($cutoff_text)) {

    $lines = explode("\n", $cutoff_text);

    foreach ($lines as $line) {

        $line = trim($line);

        if (empty($line)) {
            continue;
        }

        $parts = explode('|', $line);

        if (count($parts) != 2) {
            continue;
        }

        $tingkat = trim($parts[0]);
        $tanggal = trim($parts[1]);

        $cutoffkelas[$tingkat] = $tanggal;
    }
}

/*
=====================================================
DETEKSI JAM AKTIF
=====================================================
*/

$jamaktif = 0;
$jamberikut = 0;

foreach ($jam_pelajaran as $j => $w) {

    if (
        $now >= $w['mulai']
        &&
        $now < $w['selesai']
    ) {

        $jamaktif = (int)$j;
        $jamberikut = $jamaktif + 1;

        break;
    }
}

/*
=====================================================
JIKA SEDANG ISTIRAHAT
AMBIL JAM BERIKUTNYA
=====================================================
*/

if (!$jamaktif) {

    foreach ($jam_pelajaran as $j => $w) {

        if ($now < $w['mulai']) {

            $jamberikut = (int)$j;
            break;
        }
    }
}

/*
=====================================================
JAM TERAKHIR: TIDAK ADA JAM BERIKUTNYA
Jam pelajaran hanya sampai jam ke 11, jadi kalau
jam aktif sudah 11, $jamberikut hasil hitungan
($jamaktif + 1 = 12) tidak ada di $jam_pelajaran.
Kembalikan ke 0 supaya header menampilkan
'Guru Berikutnya Jam ke -' bukan 'Jam ke 12'.
=====================================================
*/

if ($jamberikut && empty($jam_pelajaran[$jamberikut])) {
    $jamberikut = 0;
}

/*
=====================================================
HITUNG WAKTU AUTO RELOAD & SISA DETIK KBM
=====================================================
*/

// Gunakan waktu yang sedang dipakai TV
$nowtimestamp = strtotime(
    $tanggalhariini . ' ' . $now
);

$reloadseconds = 0; // default: tidak reload
$kbm_status_label = 'PERGANTIAN JAM DALAM';

if ($jamaktif && !empty($jam_pelajaran[$jamaktif])) {

    $target = strtotime(
        $tanggalhariini . ' ' .
        $jam_pelajaran[$jamaktif]['selesai']
    );

    $reloadseconds = max(
        1,
        $target - $nowtimestamp + 1
    );

    // Cek apakah ada jam pelajaran berikutnya
    if (!empty($jam_pelajaran[$jamberikut])) {
        $kbm_status_label = 'GANTI JAM DALAM';
    } else {
        // Jika sudah jam terakhir
        $kbm_status_label = 'KBM SELESAI DALAM';
    }

} else if ($jamberikut && !empty($jam_pelajaran[$jamberikut])) {

    // Sedang istirahat / sebelum jam pertama
    $target = strtotime(
        $tanggalhariini . ' ' .
        $jam_pelajaran[$jamberikut]['mulai']
    );

    $reloadseconds = max(
        1,
        $target - $nowtimestamp + 1
    );
    $kbm_status_label = 'JAM MASUK KBM DALAM';

} else {

    // Jika seluruh jam pelajaran sudah selesai (setelah KBM berakhir)
    $kbm_status_label = 'KBM HARI INI TELAH SELESAI';
}

// Sisa detik untuk hitung mundur KBM
$sisadetik_kbm = $reloadseconds;

/*
=====================================================
DATA PER KELAS
=====================================================
*/

$data = [];

foreach ($jadwal as $j) {

    /*
    =============================================
    FILTER HARI
    =============================================
    */

    if ($j['hari'] != $hari_ini) {
        continue;
    }

    $kelas = trim($j['kelas']);

    /*
    ============================================
    CEK CUTOFF KELAS
    ============================================
    */

    preg_match('/^(XII|XI|X)/', $kelas, $match);

    $tingkatkelas = $match[1] ?? '';

    if (!empty($cutoffkelas[$tingkatkelas])) {

        $tanggalcutoff =
            strtotime($cutoffkelas[$tingkatkelas]);

        /*
        =========================================
        JIKA SUDAH LEWAT CUTOFF
        =========================================
        */

        if (
            strtotime($tanggalhariini)
            >=
            $tanggalcutoff
        ) {
            continue;
        }
    }

    /*
    =============================================
    INISIALISASI DATA KELAS
    =============================================
    */

    if (!isset($data[$kelas])) {

        $data[$kelas] = [
            'sekarang' => [],
            'berikut'  => []
        ];
    }

    /*
    =============================================
    GURU SEKARANG
    =============================================
    */

    if ((int)$j['jamke'] === $jamaktif) {

        $guru = trim($j['lastname']);

        if ($guru !== '' &&
            !in_array(
                $guru,
                $data[$kelas]['sekarang'],
                true
            )
        ) {

            $data[$kelas]['sekarang'][] = $guru;
        }
    }

    /*
    =============================================
    GURU BERIKUTNYA
    =============================================
    */

    if ((int)$j['jamke'] === $jamberikut) {

        $guru = trim($j['lastname']);

        if ($guru !== '' &&
            !in_array(
                $guru,
                $data[$kelas]['berikut'],
                true
            )
        ) {

            $data[$kelas]['berikut'][] = $guru;
        }
    }
}

/*
=====================================================
UBAH ARRAY GURU MENJADI TEKS
=====================================================
*/

foreach ($data as $kelas => &$d) {

    $d['sekarang'] = !empty($d['sekarang'])
        ? implode(' / ', $d['sekarang'])
        : '-';

    $d['berikut'] = !empty($d['berikut'])
        ? implode(' / ', $d['berikut'])
        : '-';
}

unset($d);

/*
=====================================================
SORTING KELAS
=====================================================
*/

uksort($data, function($a, $b) {

    $order = [
        'X'   => 1,
        'XI'  => 2,
        'XII' => 3
    ];

    preg_match('/^(XII|XI|X)/', $a, $ma);
    preg_match('/^(XII|XI|X)/', $b, $mb);

    $oa = $order[$ma[1] ?? ''] ?? 99;
    $ob = $order[$mb[1] ?? ''] ?? 99;

    if ($oa !== $ob) {
        return $oa <=> $ob;
    }

    return strnatcasecmp($a, $b);
});

?>
<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<title>TV</title>

<!-- <meta http-equiv="refresh" content="60"> -->

<style>

/* TEMA ADIWIYATA — 14 Nov 2026 (permintaan Edho, dari foto poster)
   Dinding: #b9d2e6 biru muda. Kertas: #f8f8f3. Kotak hijau: #aec098.
   Teks: #1a1a1a hitam. Aksen: #2e7d32 hijau pohon, #2b6cb0 biru buku.
   Hanya warna yang diubah dari tv-4.php; logika tidak disentuh.
   Revisi 14 Nov: panel jadwal bg #b4e0e7 + bingkai hitam, teks hitam/navy.
   Revisi 14 Nov: top bar, strip sholat, panel pengawas/pengumuman bg navy #071e48, teks emas #e7b425/putih.
   Revisi 14 Nov: running text bawah bg navy #071e48, teks emas, garis atas hijau.
   Revisi 14 Nov: baris jadwal selang-seling navy #071e48 / biru muda #b4e0e8;
   teks baris navy: kelas emas, sekarang putih, berikut biru muda, jam putih.
   Revisi 14 Nov: kotak ganti jam bg #b4e0e8 (bukan putih).
   Revisi 14 Nov: header tabel (Kelas/Guru Sekarang/Jam ke/Guru Berikutnya) bg navy #071e48, teks emas, garis bawah hijau.
   Revisi 14 Nov: baris ganjil navy -> biru buku #2b6cb0 (lebih lembut); berikut di baris gelap jadi putih.
   Revisi 14 Nov: kolom Jam ke dihapus; nomor jam pindah ke header (Jam ke $jamaktif di atas Guru Sekarang,
   Jam ke $jamberikut di bawah Guru Berikutnya) — 3 kolom: Kelas / Guru Sekarang / Guru Berikutnya.
   Revisi 14 Nov: header jadi SATU baris: 'Jam ke N Guru Sekarang' dan 'Guru Berikutnya Jam ke N+1'.
   Revisi 14 Nov: 'Jam ke N' di header warna biru muda #b4e0e8, label utama tetap emas.
   Revisi 14 Nov: scroll tabel KBM jadi loop mulus tanpa henti (baris tbody digandakan,
   scrollTop dikurangi tinggi satu set baris saat melewatinya) — tidak ada lagi jeda + lompat ke awal.
   Revisi 16 Nov (tv-5.php): $jamberikut dikembalikan ke 0 kalau tidak ada di $jam_pelajaran
   (jam aktif 11 -> bukan 'Jam ke 12', header menampilkan 'Jam ke -').
   Catatan: tv-5.php = tv-4-adiwiyata.php + teks tombol suara '🔊 Suara Aktif' (tanpa '— klik untuk tes'). */

body{
    margin:0;
    background:#b9d2e6;
    color:#1a1a1a;
    font-family:Arial,sans-serif;
    overflow:hidden;
}

/*
=====================================================
HEADER
=====================================================
*/

.header{
    min-height:108px;
    background:#071e48;
    border-bottom:3px solid #aec098;
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:0 30px;
}

.logo-area{
    display:flex;
    align-items:center;
    gap:20px;
}

.logo-img{
    width:70px;
    height:70px;
    background:white;
    border-radius:12px;
    object-fit:contain;
    padding:5px;
}

.logo{
    font-size:38px;
    font-weight:bold;
    color:#e7b425;
}

.subtitle{
    font-size:18px;
    color:#ffffff;
}

.clock{
    font-size:56px;
    font-weight:bold;
    line-height:1.05;
    color:#e7b425;
}

.tanggalindo{
    font-size:22px;
    margin-top:5px;
    opacity:0.95;
    text-align:right;
    color:#ffffff;
}

.datetime-box{
    display:flex;
    flex-direction:column;
    align-items:flex-end;
}

.strip-sholat{
    background:#071e48;
    color:#e7b425;
    text-align:center;
    font-size:22px;
    font-weight:bold;
    padding:9px 12px;
    letter-spacing:0.5px;
    border-top:2px solid #aec098;
}

/*
=====================================================
SIMULASI
=====================================================
*/

.simulasi{
    position:fixed;
    top:160px;
    right:20px;
    background:#dc2626;
    color:white;
    padding:15px 20px;
    border-radius:12px;
    font-size:20px;
    font-weight:bold;
    z-index:999;
}

/*
=====================================================
LIBUR
=====================================================
*/

.libur{
    margin:20px;
    background:#991b1b;
    color:#ffffff;
    padding:40px;
    border-radius:20px;
    text-align:center;
    font-size:42px;
    font-weight:bold;
}

/*
=====================================================
TOP PANELS
=====================================================
*/

.top-panels{
    display:grid;
    grid-template-columns:1.3fr 1.7fr;
    gap:20px;
    padding:20px;
}

.panel{
    background:#071e48;
    border-radius:18px;
    padding:14px 18px;
    height:130px;
    overflow:hidden;
    position:relative;
}

.panel-title{
    font-size:22px;
    font-weight:bold;
    color:#e7b425;
    margin-bottom:8px;
}

.panel-scroll{
    height:100px;
    overflow:hidden;
}

.panel-item{
    font-size:28px;
    margin-bottom:6px;
    line-height:1.25;
    color:#ffffff;
}

.panel-empty{
    opacity:0.7;
    font-size:20px;
    color:#ffffff;
}

/* DESAIN COUNTDOWN PENGUMUMAN (UKURAN DIPERBESAR) */
.countdown-announcement-box {
    background: #b4e0e8;
    border: 2px solid #ef4444;
    border-left: 8px solid #ef4444;
    padding: 10px 18px;
    border-radius: 12px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.countdown-announcement-title {
    font-size: 32px; /* Sebelumnya 16px */
    color: #1a1a1a;
    font-weight: bold;
    letter-spacing: 0.5px;
}

.countdown-announcement-time {
    font-size: 54px; /* Sebelumnya 22px */
    font-weight: bold;
    color: #ef4444;
    letter-spacing: 2px;
}

/*
=====================================================
BANNER
=====================================================
*/
.banner-fullscreen{
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100vh;
    background:#000;
    display:none;
    justify-content:center;
    align-items:center;
    z-index:99999;
}

.banner-fullscreen img{
    width:100%;
    height:100%;
    object-fit:contain;
}

.banner-fullscreen video{
    width:100%;
    height:100%;
    object-fit:contain;
}


/*
=====================================================
TABLE
=====================================================
*/

.table-container{
    margin:0 20px 20px 20px;

    height:calc(100vh - 398px);
    overflow:hidden;
    background:#b4e0e7;
    border:3px solid #1a1a1a;
    border-radius:20px;
}

table{
    width:100%;
    border-collapse:collapse;
}

thead{
    position:sticky;
    top:0;
    z-index:100;
}

th{
    background:#071e48;
    padding:18px 24px;
    font-size:26px;
    text-align:left;
    color:#e7b425;
    border-bottom:3px solid #aec098;
}

/* Nomor jam di header tabel: biru muda, beda dari label utama yang emas. */
.th-jam{
    color:#b4e0e8;
}

.center{
    text-align:center !important;
}
td{
    padding:18px;
    font-size:36px;
    color:#1a1a1a;
    border-bottom:1px solid rgba(26,26,26,0.15);
}

tr:nth-child(odd){
    background:#2b6cb0;
}

tr:nth-child(even){
    background:#b4e0e8;
}

/* Teks di baris gelap (ganjil): warna terang supaya kontras. */
tr:nth-child(odd) .kelas{ color:#e7b425; }
tr:nth-child(odd) .sekarang{ color:#ffffff; }
tr:nth-child(odd) .berikut{ color:#ffffff; }
tr:nth-child(odd) td{ color:#ffffff; }

.kelas{
    color:#071e48;
    font-weight:bold;
}

.sekarang{
    color:#1a1a1a;
    font-weight:bold;
}

.berikut{
    color:#071e48;
    font-weight:bold;
}

/*
=====================================================
RUNNING TEXT
=====================================================
*/

.running{
    position:fixed;
    bottom:0;
    width:100%;
    background:#071e48;
    color:#e7b425;
    border-top:3px solid #aec098;
    padding:14px;
    overflow:hidden;
    white-space:nowrap;
    font-size:24px;
}

.running span{
    display:inline-block;
    padding-left:100%;
    animation:run 30s linear infinite;
}

@keyframes run{

    from{
        transform:translateX(0);
    }

    to{
        transform:translateX(-100%);
    }
}

</style>

</head>
<body>

<?php if (!empty($banneraktif)): ?>
<div
    id="specialBanner"
    class="banner-fullscreen"
>

    <img
        id="bannerImage"
        style="display:none;"
    >

    <video
        id="bannerVideo"
        style="display:none;"
        autoplay
        playsinline
    ></video>

</div>

<?php endif; ?>
<div class="header">

    <div class="logo-area">

        <?php if ($logourl): ?>

            <img
                src="<?= $logourl; ?>"
                class="logo-img"
            >

        <?php endif; ?>

        <div>

            <div class="logo">
                <?= format_string($namasekolah); ?>
            </div>

            <div class="subtitle">
                SiM TV • Tahun Ajaran <?= s($tahunajaran); ?>
            </div>

        </div>

    </div>

<?php
/* =====================================================
   JADWAL SHOLAT — KAB. HULU SUNGAI SELATAN (Kandangan)
   -----------------------------------------------------
   Sumber: API MyQuran (data Kemenag), id kota 2104.
   Jadwal diambil 1x per hari lalu disimpan ke cache
   jadwal_sholat_cache.json di folder ini, supaya
   halaman tidak bergantung internet setiap reload.
   Bila internet gagal, cache terakhir tetap dipakai
   (jam sholat bergeser hanya ±1-2 menit per hari).
   $pengingat_sholat_menit = bunyi pengingat sekian
   menit SEBELUM masuk waktu (ubah di sini bila perlu).
===================================================== */
$pengingat_sholat_menit = 10;
$jadwal_sholat = null;
$cache_sholat = __DIR__ . '/jadwal_sholat_cache.json';
$hari_ini_sholat = date('Y-m-d');
$data_cache_sholat = null;
if (is_readable($cache_sholat)) {
    $data_cache_sholat = json_decode((string) file_get_contents($cache_sholat), true);
}
if (is_array($data_cache_sholat)
    && ($data_cache_sholat['tanggal'] ?? '') === $hari_ini_sholat
    && !empty($data_cache_sholat['jadwal'])
) {
    $jadwal_sholat = $data_cache_sholat['jadwal'];
} else {
    $url_sholat = 'https://api.myquran.com/v2/sholat/jadwal/2104/' . date('Y/m/d');
    $ctx_sholat = stream_context_create(['http' => ['timeout' => 5]]);
    $json_sholat = @file_get_contents($url_sholat, false, $ctx_sholat);
    $hasil_sholat = $json_sholat ? json_decode($json_sholat, true) : null;
    if (!empty($hasil_sholat['data']['jadwal'])) {
        $jadwal_sholat = $hasil_sholat['data']['jadwal'];
        @file_put_contents(
            $cache_sholat,
            json_encode(['tanggal' => $hari_ini_sholat, 'jadwal' => $jadwal_sholat])
        );
    } elseif (is_array($data_cache_sholat) && !empty($data_cache_sholat['jadwal'])) {
        $jadwal_sholat = $data_cache_sholat['jadwal'];
    }
}
$sholat_untuk_js = [];
if (is_array($jadwal_sholat)) {
    $peta_sholat = [
        'subuh'   => ['Subuh', 'subuh'],
        'dzuhur'  => ['Zuhur', 'zuhur'],
        'ashar'   => ['Asar', 'asar'],
        'maghrib' => ['Magrib', 'magrib'],
        'isya'    => ['Isya', 'isya'],
    ];
    foreach ($peta_sholat as $kunci_sholat => $peta) {
        if (!empty($jadwal_sholat[$kunci_sholat])) {
            $sholat_untuk_js[] = [
                'label' => $peta[0],
                'slug'  => $peta[1],
                'jam'   => $jadwal_sholat[$kunci_sholat],
            ];
        }
    }
}
?>

    <div class="datetime-box">

        <div
            class="clock"
            id="clock"
        >
            00:00:00
        </div>

        <div class="tanggalindo">
            <?= s($tanggalindo); ?>
        </div>


    </div>

</div>

<?php if (!empty($sholat_untuk_js)): ?>
<div class="strip-sholat">
    🕌 <?= implode(' &nbsp;·&nbsp; ', array_map(function ($j) {
        return s($j['label']) . ' ' . s($j['jam']);
    }, $sholat_untuk_js)); ?>
</div>
<?php endif; ?>

<?php if ($issimulasi): ?>

<div class="simulasi">
    MODE SIMULASI<br>

    <?= s($hari_ini); ?>,
    <?= s($tanggalhariini); ?><br>

    <?= s($now); ?>
</div>

<?php endif; ?>

<?php if (
    $mode_tv === 'LIBUR'
    ||
    $mode_tv === 'KHUSUS'
): ?>

<div class="libur">

<?php if ($is_jadwal_khusus): ?>

    <div style="font-size:56px;">
        <?= s($judulkhusus); ?>
    </div>

    <div style="margin-top:20px;font-size:32px;">
        <?= s($subjudulkhusus); ?>
    </div>

<?php else: ?>

    Hari Ini Libur Sekolah

<?php endif; ?>

</div>

<?php endif; ?>

<?php if ($mode_tv === 'ASESMEN'): ?>

<div class="asesmen-header">

    <div class="asesmen-header-title">
        <?= s($judulasesmen); ?>
    </div>

    <div class="asesmen-header-subtitle">
        <?= format_string($namasekolah); ?>
    </div>

</div>

<style>

.asesmen-header{
    margin:15px 20px;
    padding:15px;
    border-radius:16px;
    background:#f8f8f3;
    text-align:center;
}

.asesmen-header-title{
    font-size:42px;
    font-weight:bold;
    color:#1a1a1a;
    line-height:1.2;
}

.asesmen-header-subtitle{
    margin-top:8px;
    font-size:24px;
    font-weight:bold;
    color:#2e7d32;
}

.asesmen-wrapper{
    padding:20px;
}

.asesmen-grid{
    display:grid;
    grid-template-columns:1fr;
    gap:20px;
}

.asesmen-card{
    background:#f8f8f3;
    border-radius:20px;
    padding:18px;
}

.asesmen-title{
    font-size:38px;
    font-weight:bold;
    color:#1a1a1a;
    margin-bottom:24px;
}

.asesmen-info{
    display:grid;
    grid-template-columns:150px 1fr;
    row-gap:18px;
    column-gap:10px;
    font-size:30px;
    margin-bottom:24px;
}

.asesmen-label{
    color:#2e7d32;
}

.asesmen-value{
    font-weight:bold;
    color:#1a1a1a;
}

.ruang-list{
    display:flex;
    flex-direction:column;
    gap:14px;
}

.ruang-item{
    display:grid;
    grid-template-columns:100px 1fr;
    background:rgba(174,192,152,0.25);
    border-radius:14px;
    overflow:hidden;
}

.ruang-label{
    background:#aec098;
    color:#1a1a1a;
    padding:18px;
    font-size:36px;
    font-weight:bold;
    text-align:center;
}

.ruang-guru{
    padding:14px 18px;
    font-size:36px;
    font-weight:bold;
    color:#1a1a1a;
}

.countdown-box{
    margin:20px;
    background:#f8f8f3;
    border-radius:20px;
    padding:30px;
    text-align:center;
}

.countdown-box{
    position:fixed;
    left:20px;
    right:20px;
    bottom:60px;
}

.countdown-title{
    font-size:34px;
    color:#1a1a1a;
    margin-bottom:10px;
    font-weight:bold;
}

.countdown{
    font-size:72px;
    font-weight:bold;
    letter-spacing:4px;
    color:#1a1a1a;
}

</style>

<div class="asesmen-wrapper">

    <div class="asesmen-grid">

        <!-- ================================= -->
        <!-- SESI AKTIF -->
        <!-- ================================= -->

        <div class="asesmen-card">

            <div class="asesmen-title">
                PENGAWAS SESI AKTIF
            </div>

            <div class="asesmen-info">

                <div class="asesmen-label">
                    SESI KE
                </div>

                <div class="asesmen-value">
                    <?= $sesiaktif ?: '-'; ?>
                </div>

                <div class="asesmen-label">
                    WAKTU
                </div>

                <div class="asesmen-value">

                    <?php if (!empty($sesiasesmen[$sesiaktif])): ?>

                        <?= substr($sesiasesmen[$sesiaktif]['mulai'],0,5); ?>
                        -
                        <?= substr($sesiasesmen[$sesiaktif]['selesai'],0,5); ?>

                    <?php else: ?>

                        -

                    <?php endif; ?>

                </div>

            </div>

            <div class="ruang-list">

        <?php foreach ($pengawasaktif as $ruang => $data): ?>

            <div class="ruang-item">

            <div class="ruang-label">
                <?= s($ruang); ?>
            </div>

            <div class="ruang-guru">
                <?= s($data['guru'] ?? '-'); ?>
            </div>

            </div>

        <?php endforeach; ?>

            </div>

        </div>

        <!-- ================================= -->
        <!-- SESI BERIKUTNYA -->
        <!-- ================================= -->

        <div class="asesmen-card">

            <div class="asesmen-title">
                PENGAWAS SESI BERIKUTNYA
            </div>

            <div class="asesmen-info">

                <div class="asesmen-label">
                    SESI KE
                </div>

                <div class="asesmen-value">
                    <?= $sesiberikut ?: '-'; ?>
                </div>

                <div class="asesmen-label">
                    WAKTU
                </div>

                <div class="asesmen-value">

                    <?php if (!empty($sesiasesmen[$sesiberikut])): ?>

                        <?= substr($sesiasesmen[$sesiberikut]['mulai'],0,5); ?>
                        -
                        <?= substr($sesiasesmen[$sesiberikut]['selesai'],0,5); ?>

                    <?php else: ?>

                        -

                    <?php endif; ?>

                </div>

            </div>

            <div class="ruang-list">

        <?php foreach ($pengawasberikut as $ruang => $data): ?>

            <div class="ruang-item">

            <div class="ruang-label">
                <?= s($ruang); ?>
            </div>

            <div class="ruang-guru">
                <?= s($data['guru'] ?? '-'); ?>
            </div>

            </div>

        <?php endforeach; ?>

            </div>

        </div>

    </div>

</div>

<div class="countdown-box">

    <div class="countdown-title">
    <?= s($countdowntitle); ?>
    </div>

    <div
        class="countdown"
        id="countdownasesmen"
    >
        00:00:00
    </div>

</div>

<script>

let sisaasesmen =
    <?= (int)$sisadetikasesmen; ?>;

function updateCountdownAsesmen(){

    if (sisaasesmen < 0) {
        sisaasesmen = 0;
    }

    let jam =
        String(Math.floor(sisaasesmen / 3600))
        .padStart(2,'0');

    let menit =
        String(Math.floor((sisaasesmen % 3600) / 60))
        .padStart(2,'0');

    let detik =
        String(sisaasesmen % 60)
        .padStart(2,'0');

    document.getElementById(
        'countdownasesmen'
    ).innerHTML =
        jam + ':' + menit + ':' + detik;

    if (sisaasesmen > 0) {

        sisaasesmen--;

        if (sisaasesmen === 0) {

            setTimeout(() => {

                location.reload();

            }, 1000);
        }
    }
}

setInterval(
    updateCountdownAsesmen,
    1000
);

updateCountdownAsesmen();

</script>

<?php endif; ?>

<?php if ($mode_tv === 'KBM'): ?>

<div class="top-panels">

    <!-- ===================================== -->
    <!-- GURU PIKET -->
    <!-- ===================================== -->

    <div class="panel">

        <div class="panel-title">
            👨‍🏫 Guru Pengawas Hari Ini
        </div>

        <div class="panel-scroll">

            <?php if (!empty($gurupiket)): ?>

                <?php foreach ($gurupiket as $g): ?>

                    <div class="panel-item">
                        • <?= s($g); ?>
                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="panel-empty">
                    Tidak ada data guru piket
                </div>

            <?php endif; ?>

        </div>

    </div>

    <!-- ===================================== -->
    <!-- PENGUMUMAN -->
    <!-- ===================================== -->

    <div class="panel">

        <div class="panel-title">
            📢 Pengumuman
        </div>

        <div class="panel-scroll">

            <?php if ($sisadetik_kbm > 0): ?>

                <!-- COUNTDOWN DIPINDAHKAN KE SINI -->
                <div class="countdown-announcement-box">
                    <span class="countdown-announcement-title">
                        ⏳ <?= s($kbm_status_label); ?>
                    </span>
                    <span class="countdown-announcement-time" id="kbmCountdownPanel">
                        00:00
                    </span>
                </div>

            <?php endif; ?>

            <?php if (!empty($pengumuman)): ?>

                <?php foreach ($pengumuman as $p): ?>

                    <div class="panel-item">
                        • <?= s($p); ?>
                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <?php if ($sisadetik_kbm <= 0): ?>
                    <div class="panel-empty">
                        Belum ada pengumuman
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>

    </div>

</div>

<div
    class="table-container"
    id="tableContainer"
>

<table>

    <thead>

        <tr>

            <th>Kelas</th>
            <th><span class="th-jam">Jam ke <?= $jamaktif ?: '-'; ?></span> Guru Sekarang</th>
            <th>Guru Berikutnya <span class="th-jam">Jam ke <?= $jamberikut ?: '-'; ?></span></th>

        </tr>

    </thead>

    <tbody>

    <?php foreach ($data as $kelas => $d): ?>

        <tr>

            <td class="kelas">
                <?= s($kelas); ?>
            </td>

            <td class="sekarang">
                <?= s($d['sekarang']); ?>
            </td>

            <td class="berikut">
                <?= s($d['berikut']); ?>
            </td>

        </tr>

    <?php endforeach; ?>

    </tbody>

</table>

</div>
<?php endif; ?>

<!-- TOMBOL AKTIVASI SUARA TV -->
<style>
#tombolSuara{
    position:fixed;
    right:20px;
    bottom:60px;
    z-index:100000;
    background:#dc2626;
    color:#ffffff;
    border:3px solid #1a1a1a;
    border-radius:14px;
    padding:14px 22px;
    font-size:20px;
    font-weight:bold;
    cursor:pointer;
    box-shadow:0 4px 16px rgba(0,0,0,0.5);
}
</style>
<button id="tombolSuara" type="button">🔊 AKTIFKAN SUARA TV</button>
<audio id="audioJam" preload="auto"></audio>

<!-- OVERLAY AKTIVASI SUARA (muncul hanya bila putar otomatis gagal/diblokir) -->
<style>
#overlaySuara{
    position:fixed;
    top:0; left:0; right:0; bottom:0;
    background:rgba(0,0,0,0.55);
    display:none;
    justify-content:center;
    align-items:center;
    z-index:200000;
    cursor:pointer;
}
#overlaySuara .kotak{
    background:#dc2626;
    border:4px solid #1a1a1a;
    border-radius:20px;
    padding:34px 44px;
    text-align:center;
    color:#ffffff;
    font-size:36px;
    font-weight:bold;
    line-height:1.4;
    max-width:80%;
}
#overlaySuara .kecil{
    font-size:22px;
    font-weight:normal;
    margin-top:10px;
}
</style>
<div id="overlaySuara">
    <div class="kotak">
        🔊 AKTIFKAN SUARA TV
        <div class="kecil">Klik di mana saja pada layar ini, atau tekan tombol OK pada remote</div>
    </div>
</div>

<div class="running">

    <span>

        Selamat datang di SiM TV •
        Jadwal kegiatan belajar mengajar realtime •
        <?= format_string($namasekolah); ?> •
        Hari <?= s($hari_ini); ?>

    </span>

</div>

<script>
/*
=====================================================
1. JAM DIGITAL
=====================================================
*/
let jam   = <?= (int)$server_h ?>;
let menit = <?= (int)$server_i ?>;
let detik = <?= (int)$server_s ?>;

function updateClock() {

    document.getElementById('clock').innerHTML =
        String(jam).padStart(2,'0') + ':' +
        String(menit).padStart(2,'0') + ':' +
        String(detik).padStart(2,'0');

    detik++;

    if (detik >= 60) {
        detik = 0;
        menit++;
    }

    if (menit >= 60) {
        menit = 0;
        jam++;
    }

    if (jam >= 24) {
        jam = 0;
    }
}

updateClock();
setInterval(updateClock, 1000);

/*
=====================================================
2. AUTO SCROLL TABLE (JADWAL KBM)
=====================================================
*/
const container = document.getElementById('tableContainer');
let tablePause = false;
let tableFirstPause = true;

/*
=====================================================
SCROLL TABEL TANPA HENTI (loop mulus)
Caranya: baris tabel digandakan sekali, jadi isinya 2x.
Saat scroll sudah melewati satu set baris asli, posisi
dikurangi tinggi satu set baris itu — karena baris
gandanya identik, lompatannya tidak terlihat sama sekali.
Thead tetap satu (sticky), hanya baris tbody yang diganda.
Kalau semua baris muat di layar, scroll dimatikan.
=====================================================
*/
let tableLoopHeight = 0;
let tableSeamless = false;

if (container) {
    const tbody = container.querySelector('tbody');
    if (tbody && container.scrollHeight > container.clientHeight + 1) {
        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.forEach(row => { tableLoopHeight += row.offsetHeight; });
        rows.forEach(row => { tbody.appendChild(row.cloneNode(true)); });
        tableSeamless = true;
    }
}

function autoScrollTable() {
    if (!container || tablePause || !tableSeamless) return;

    if (tableFirstPause) {
        tablePause = true;
        setTimeout(() => {
            tableFirstPause = false;
            tablePause = false;
        }, 5000);
        return;
    }

    container.scrollTop += 3;

    if (container.scrollTop >= tableLoopHeight) {
        container.scrollTop -= tableLoopHeight;
    }
}

if (container) {
    setInterval(autoScrollTable, 50);
}

/*
=====================================================
3. AUTO SCROLL PANEL (GURU PIKET & PENGUMUMAN)
=====================================================
*/
document.querySelectorAll('.panel-scroll').forEach(panel => {
    let panelPause = false;
    setInterval(() => {
        if (panelPause) return;
        if (panel.scrollHeight > panel.clientHeight) {
            panel.scrollTop += 1;
            if (panel.scrollTop + panel.clientHeight >= panel.scrollHeight) {
                panelPause = true;
                setTimeout(() => {
                    panel.scrollTop = 0;
                    panelPause = false;
                }, 3000);
            }
        }
    }, 100);
});

/*
=====================================================
4. AUTO REFRESH & COUNTDOWN PANEL PENGUMUMAN
=====================================================
*/

<?php if ($mode_tv === 'KBM' && $sisadetik_kbm > 0): ?>

let sisaKBM = <?= (int)$sisadetik_kbm; ?>;

function updateCountdownKBM() {
    if (sisaKBM < 0) sisaKBM = 0;

    let m = String(Math.floor(sisaKBM / 60)).padStart(2, '0');
    let s = String(sisaKBM % 60).padStart(2, '0');

    const countdownEl = document.getElementById('kbmCountdownPanel');
    if (countdownEl) {
        countdownEl.innerHTML = `${m}:${s}`;
    }

    if (sisaKBM > 0) {
        sisaKBM--;
        if (sisaKBM === 0) {
            setTimeout(() => {
                location.reload();
            }, 1000);
        }
    }
}

updateCountdownKBM();
setInterval(updateCountdownKBM, 1000);

<?php endif; ?>

</script>

<script>
/*
=====================================================
5. SUARA PENGUMUMAN GANTI JAM — VERSI LG webOS (MP3)
-----------------------------------------------------
- Browser bawaan TV LG (webOS) TIDAK punya
  speechSynthesis / text-to-speech, jadi versi ini
  memutar file MP3 siap pakai dari folder suara/ :
      suara/jam-1.mp3 ... suara/jam-12.mp3
      suara/suara-aktif.mp3  (bunyi tes tombol)
- TIDAK ada lagi suara cadangan robot/TTS. Dulu
  fallback speechSynthesis inilah yang bersuara
  laki-laki asing di TV saat MP3 gagal diputar
  otomatis — dihapus pada perbaikan 2026-11-09.
  Sekarang: MP3 gagal -> coba sekali lagi -> bila
  tetap gagal, muncul overlay untuk diklik operator.
- Halaman SUDAH reload otomatis tepat di pergantian
  jam, jadi MP3 cukup diputar 1x saat halaman dimuat
  pada jam yang baru. sessionStorage mencegah bunyi
  ulang bila halaman di-refresh manual di jam sama.
- Tombol AKTIFKAN SUARA TV diklik 1x dengan remote
  untuk membuka izin audio browser; pilihan disimpan
  di localStorage browser TV.
=====================================================
*/
(function () {

    const MODE_TV   = <?= json_encode($mode_tv); ?>;
    const JAM_AKTIF = <?= (int)$jamaktif; ?>;
    const TANGGAL   = <?= json_encode($tanggalhariini); ?>;
    const VERSI_SUARA = '20261109-7'; // naikkan angka ini setiap mengganti file MP3, agar cache browser TV kalah
    const FILE_MP3  = JAM_AKTIF > 0 ? 'suara/jam-' + JAM_AKTIF + '.mp3?v=' + VERSI_SUARA : '';

    const btn     = document.getElementById('tombolSuara');
    const audio   = document.getElementById('audioJam');
    const overlay = document.getElementById('overlaySuara');

    if (!btn || !audio) {
        return;
    }

    function tampilkanOverlay() {
        if (overlay) {
            overlay.style.display = 'flex';
        }
    }

    function sembunyikanOverlay() {
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    let tokenPutar = 0;

    function putar(file, sudahUlang) {
        if (!file) {
            return;
        }
        const token = ++tokenPutar;
        let ditangani = false;
        function gagal() {
            if (ditangani || token !== tokenPutar) {
                return;
            }
            ditangani = true;
            if (!sudahUlang) {
                // Coba sekali lagi setelah 2 detik (alamat diberi
                // penanda ulang agar tidak kena cache gagal)
                setTimeout(function () {
                    putar(file + (file.indexOf('?') >= 0 ? '&' : '?') + 'ulang=1', true);
                }, 2000);
            } else {
                // Tetap gagal: jangan bersuara robot — tampilkan
                // overlay agar operator mengklik / menekan OK
                tampilkanOverlay();
            }
        }
        audio.onerror = gagal;
        try {
            audio.src = file;
            const janji = audio.play();
            if (janji && typeof janji.catch === 'function') {
                janji.catch(gagal);
            }
        } catch (e) {
            gagal();
        }
    }

    function bunyikanJam(paksa) {
        const kunci = 'simtv_suara_terakhir';
        const idKunci = TANGGAL + '|jamke-' + JAM_AKTIF;
        let terakhir = null;
        try {
            terakhir = sessionStorage.getItem(kunci);
        } catch (e) {}
        if (!paksa && terakhir === idKunci) {
            return;
        }
        try {
            sessionStorage.setItem(kunci, idKunci);
        } catch (e) {}
        putar(FILE_MP3, false);
    }

    function suaraAktif() {
        try {
            return localStorage.getItem('simtv_suara_aktif') === '1';
        } catch (e) {
            return false;
        }
    }

    function perbaruiTombol() {
        if (suaraAktif()) {
            btn.textContent = '🔊 Suara Aktif';
            btn.style.opacity = '0.65';
            btn.style.fontSize = '16px';
            btn.style.padding = '8px 14px';
        } else {
            btn.textContent = '🔊 AKTIFKAN SUARA TV';
        }
    }

    btn.addEventListener('click', function () {
        try {
            localStorage.setItem('simtv_suara_aktif', '1');
        } catch (e) {}
        perbaruiTombol();
        sembunyikanOverlay();
        if (JAM_AKTIF > 0) {
            bunyikanJam(true);
        } else {
            putar('suara/suara-aktif.mp3?v=' + VERSI_SUARA, false);
        }
    });

    // Overlay: klik di mana saja / tombol remote apa saja = aktifkan
    // dan putar pengumuman jam yang sedang berjalan.
    function aktifkanDariOverlay() {
        try {
            localStorage.setItem('simtv_suara_aktif', '1');
        } catch (e) {}
        sembunyikanOverlay();
        perbaruiTombol();
        if (JAM_AKTIF > 0) {
            bunyikanJam(true);
        } else {
            putar('suara/suara-aktif.mp3?v=' + VERSI_SUARA, false);
        }
    }

    if (overlay) {
        overlay.addEventListener('click', aktifkanDariOverlay);
    }

    document.addEventListener('keydown', function () {
        if (overlay && overlay.style.display === 'flex') {
            aktifkanDariOverlay();
        }
    });

    perbaruiTombol();

    if (suaraAktif() && MODE_TV === 'KBM' && JAM_AKTIF > 0) {
        setTimeout(function () {
            bunyikanJam(false);
        }, 1200);
    }
})();
</script>

<script>
/*
=====================================================
5C. SUARA JADWAL SHOLAT
-----------------------------------------------------
- Setiap waktu sholat berbunyi 2x:
  (1) pengingat SEBELUM_MENIT menit sebelumnya
      -> suara/ingat-<slug>.mp3
  (2) tepat saat masuk waktu
      -> suara/masuk-<slug>.mp3
- slug: subuh, zuhur, asar, magrib, isya.
- Pengecekan tiap 1 detik memakai patokan jam SERVER.
- localStorage mencegah bunyi ulang setelah reload
  di menit yang sama. Tanpa fallback suara robot:
  gagal putar = diam.
=====================================================
*/
(function () {

    const DAFTAR = <?= json_encode($sholat_untuk_js); ?>;
    const SEBELUM_MENIT = <?= (int) $pengingat_sholat_menit; ?>;
    const VERSI = '20261109-7';
    // Patokan waktu = JAM SERVER (bukan jam TV!). Jam TV bisa
    // meleset/salah zona waktu — itu sebabnya pengingat pernah
    // bunyi hampir 1 jam terlambat pada 2026-11-11. Jam TV di
    // sini hanya dipakai sebagai stopwatch sejak halaman dimuat;
    // halaman reload tiap pergantian jam sehingga patokan ini
    // selalu segar.
    const EPOCH_SERVER = <?= (int) (time() * 1000); ?>;
    const T0 = Date.now();

    if (!DAFTAR || !DAFTAR.length) {
        return;
    }

    const audio = document.getElementById('audioJam');
    if (!audio) {
        return;
    }

    function sudahBunyi(id) {
        try {
            return localStorage.getItem('simtv_sholat_terakhir') === id;
        } catch (e) {
            return false;
        }
    }

    function tandaiBunyi(id) {
        try {
            localStorage.setItem('simtv_sholat_terakhir', id);
        } catch (e) {}
    }

    function putarSholat(file) {
        try {
            audio.src = file;
            const p = audio.play();
            if (p && typeof p.catch === 'function') {
                p.catch(function () {});
            }
        } catch (e) {}
    }

    function dua(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function cekSholat() {
        const sekarang = new Date(EPOCH_SERVER + (Date.now() - T0));
        const tgl = sekarang.getFullYear() + '-' + dua(sekarang.getMonth() + 1) + '-' + dua(sekarang.getDate());
        const detikIni = sekarang.getHours() * 3600 + sekarang.getMinutes() * 60 + sekarang.getSeconds();

        DAFTAR.forEach(function (item) {
            const bagian = item.jam.split(':');
            const target = (+bagian[0]) * 3600 + (+bagian[1]) * 60;
            const targetIngat = target - SEBELUM_MENIT * 60;

            if (detikIni >= targetIngat && detikIni < targetIngat + 45) {
                const id = tgl + '|ingat-' + item.slug;
                if (!sudahBunyi(id)) {
                    tandaiBunyi(id);
                    putarSholat('suara/ingat-' + item.slug + '.mp3?v=' + VERSI);
                }
            }

            if (detikIni >= target && detikIni < target + 45) {
                const id = tgl + '|masuk-' + item.slug;
                if (!sudahBunyi(id)) {
                    tandaiBunyi(id);
                    putarSholat('suara/masuk-' + item.slug + '.mp3?v=' + VERSI);
                }
            }
        });
    }

    cekSholat();
    setInterval(cekSholat, 1000);
})();
</script>

<?php if (!empty($banneraktif)): ?>

<script>

const bannerImages =
<?= json_encode($bannerurls); ?>;

document.addEventListener(
    'DOMContentLoaded',
    function(){

        const container =
            document.getElementById(
                'specialBanner'
            );

        const image =
            document.getElementById(
                'bannerImage'
            );

        const video =
            document.getElementById(
                'bannerVideo'
            );

        if (
            !container ||
            bannerImages.length === 0
        ) {
            return;
        }

        let current = 0;

        function showBanner(){

    const file =
        bannerImages[current];

    container.style.display =
        'flex';

    if (
        file.toLowerCase()
        .endsWith('.mp4')
    ) {

        image.style.display =
            'none';

        video.style.display =
            'block';

        video.onended = null;
        
        video.src = file;

        video.load();

        video.currentTime = 0;

        video.onended = function(){

    container.style.display =
        'none';

    video.pause();

    video.currentTime = 0;
    };

        video.play().catch(() => {});

    } else {

        video.pause();

	video.currentTime = 0;

	video.removeAttribute('src');

	video.style.display =
	    'none';

        image.style.display =
            'block';

        image.src = file;

        setTimeout(
    function(){

        container.style.display =
            'none';

        image.src = '';

        },
        10000
      );
    }

    current++;

    if (
        current >=
        bannerImages.length
    ) {
        current = 0;
    }
}

        showBanner();

        setInterval(
            showBanner,
            600000
        );
    }
);

</script>

<?php endif; ?>
</body>
</html>
