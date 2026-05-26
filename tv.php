<?php

require('../../config.php');

require_once(__DIR__.'/jadwal_acuan_lib.php');
require_once(__DIR__.'/jam_pelajaran_lib.php');
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

if ($issimulasi && !empty($_GET['tanggal'])) {

    $tanggalhariini = $_GET['tanggal'];
}

$is_tanggal_libur = false;

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
    =============================================
    CEK CUTOFF KELAS
    =============================================
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

        if (time() >= $tanggalcutoff) {
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

<meta http-equiv="refresh" content="60">

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

    <div class="clock" id="clock">
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
    <?= s($hari_ini); ?><br>
    <?= s($now); ?>
</div>

<?php endif; ?>

<?php if (!$is_hari_sekolah || $is_tanggal_libur): ?>

<div class="libur">
    Hari Ini Libur Sekolah
</div>

<?php endif; ?>

<?php if (!$is_tanggal_libur && $is_hari_sekolah): ?>

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
JAM DIGITAL
=====================================================
*/

function updateClock(){

    const now = new Date();

    const time =
        now.getHours().toString().padStart(2,'0')
        + ':' +
        now.getMinutes().toString().padStart(2,'0')
        + ':' +
        now.getSeconds().toString().padStart(2,'0');

    document.getElementById('clock')
        .innerHTML = time;
}

setInterval(updateClock,1000);

updateClock();

/*
=====================================================
AUTO SCROLL TABLE
=====================================================
*/

const container =
    document.getElementById('tableContainer');

let pause = false;

/*
=====================================================
PAUSE AWAL
=====================================================
*/

let firstPause = true;

function autoScroll(){

    if (pause) {
        return;
    }

    /*
    =============================================
    TAHAN DATA AWAL 5 DETIK
    =============================================
    */

    if (firstPause) {

        setTimeout(() => {

            firstPause = false;

        }, 5000);

        return;
    }

    /*
    =============================================
    SCROLL PERLAHAN
    =============================================
    */

    container.scrollTop += 1;

    /*
    =============================================
    JIKA SUDAH BAWAH
    =============================================
    */

    if (
        container.scrollTop + container.clientHeight
        >= container.scrollHeight
    ){

        pause = true;

        /*
        =========================================
        TAHAN DI BAWAH 5 DETIK
        =========================================
        */

        setTimeout(() => {

            container.scrollTop = 0;

            pause = false;

            /*
            =====================================
            ULANGI PAUSE AWAL
            =====================================
            */

            firstPause = true;

        }, 5000);
    }
}

/*
=====================================================
KECEPATAN SCROLL
=====================================================
*/

setInterval(autoScroll,60);

/*
=====================================================
AUTO SCROLL PANEL
=====================================================
*/

document.querySelectorAll('.panel-scroll')
.forEach(panel => {

    let pos = -40;

    setInterval(() => {

        pos += 1;

        panel.scrollTop = pos;

        if (
            panel.scrollTop + panel.clientHeight
            >= panel.scrollHeight
        ){

            pos = 0;
            panel.scrollTop = 0;
        }

    }, 90);

});

</script>

</body>
</html>
