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

$jam_pelajaran =
    jurnalmengajar_generate_jam();

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
        $now <= $w['selesai']
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
            'sekarang' => '-',
            'berikut'  => '-'
        ];
    }

    /*
    =============================================
    GURU SEKARANG
    =============================================
    */

    if ((int)$j['jamke'] === $jamaktif) {

        $data[$kelas]['sekarang'] =
            $j['lastname'];
    }

    /*
    =============================================
    GURU BERIKUTNYA
    =============================================
    */

    if ((int)$j['jamke'] === $jamberikut) {

        $data[$kelas]['berikut'] =
            $j['lastname'];
    }
}

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

body{
    margin:0;
    background:#0f172a;
    color:white;
    font-family:Arial,sans-serif;
    overflow:hidden;
}

/*
=====================================================
HEADER
=====================================================
*/

.header{
    height:100px;
    background:linear-gradient(to right,#065f46,#047857);
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
}

.subtitle{
    font-size:18px;
}

.clock{
    font-size:56px;
    font-weight:bold;
}

.tanggalindo{
    font-size:22px;
    margin-top:5px;
    opacity:0.95;
    text-align:right;
}

.datetime-box{
    display:flex;
    flex-direction:column;
    align-items:flex-end;
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
    background:#1e293b;
    border-radius:18px;
    padding:14px 18px;
    height:130px;
    overflow:hidden;
    position:relative;
}

.panel-title{
    font-size:22px;
    font-weight:bold;
    color:#facc15;
    margin-bottom:8px;
}

.panel-scroll{
    height:100px;
    overflow:hidden;
}

.panel-item{
    font-size:20px;
    margin-bottom:6px;
    line-height:1.25;
}

.panel-empty{
    opacity:0.7;
    font-size:20px;
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

    height:calc(100vh - 350px);
    overflow:hidden;
    background:#1e293b;
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
    background:#047857;
    padding:18px 24px;
    font-size:26px;
    text-align:left;
}
.center{
    text-align:center !important;
}
td{
    padding:18px;
    font-size:28px;
    border-bottom:1px solid #334155;
}

tr:nth-child(even){
    background:#273549;
}

.kelas{
    color:#facc15;
    font-weight:bold;
}

.sekarang{
    color:#4ade80;
    font-weight:bold;
}

.berikut{
    color:#facc15;
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
    background:#047857;
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
    background:#1e293b;
    text-align:center;
}

.asesmen-header-title{
    font-size:42px;
    font-weight:bold;
    color:#facc15;
    line-height:1.2;
}

.asesmen-header-subtitle{
    margin-top:8px;
    font-size:24px;
    font-weight:bold;
    color:#ffffff;
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
    background:#1e293b;
    border-radius:20px;
    padding:18px;
}

.asesmen-title{
    font-size:38px;
    font-weight:bold;
    color:#facc15;
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
    color:#cbd5e1;
}

.asesmen-value{
    font-weight:bold;
}

.ruang-list{
    display:flex;
    flex-direction:column;
    gap:14px;
}

.ruang-item{
    display:grid;
    grid-template-columns:100px 1fr;
    background:#0f172a;
    border-radius:14px;
    overflow:hidden;
}

.ruang-label{
    background:#14b8a6;
    padding:18px;
    font-size:36px;
    font-weight:bold;
    text-align:center;
}

.ruang-guru{
    padding:14px 18px;
    font-size:36px;
    font-weight:bold;
}

.countdown-box{
    margin:20px;
    background:#1e293b;
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
    color:#facc15;
    margin-bottom:10px;
    font-weight:bold;
}

.countdown{
    font-size:72px;
    font-weight:bold;
    letter-spacing:4px;
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

            <?php if (!empty($pengumuman)): ?>

                <?php foreach ($pengumuman as $p): ?>

                    <div class="panel-item">
                        • <?= s($p); ?>
                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="panel-empty">
                    Belum ada pengumuman
                </div>

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
            <th>Guru Sekarang</th>
            <th class="center">Jam ke</th>
            <th>Guru Berikutnya</th>

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

            <td class="center">
                <?= $jamaktif ?: '-'; ?>
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

function autoScrollTable() {
    if (!container || tablePause) return;

    if (tableFirstPause) {
        tablePause = true;
        setTimeout(() => {
            tableFirstPause = false;
            tablePause = false;
        }, 5000);
        return;
    }

    container.scrollTop += 1;

    if (container.scrollTop + container.clientHeight >= container.scrollHeight - 1) {
        tablePause = true;
        setTimeout(() => {
            container.scrollTo({ top: 0, behavior: 'smooth' });
            setTimeout(() => {
                tablePause = false;
                tableFirstPause = true;
            }, 1500); 
        }, 5000);
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
4. AUTO REFRESH (ANTI-STUCK)
=====================================================
*/
<?php if ($mode_tv === 'KBM'): ?>

setTimeout(() => {
    location.reload();
}, 900000); // 15 menit

<?php endif; ?>

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
