<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

global $DB, $USER, $PAGE, $OUTPUT;

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/absensi_harian.php'));
$PAGE->set_pagelayout('base');
$PAGE->set_title('Absensi Harian');
$PAGE->set_heading('Absensi Harian');

/* =========================================================
 * PARAMETER
 * ========================================================= */

$tanggal = optional_param('tanggal', date('Y-m-d'), PARAM_RAW);
$bulan   = optional_param('bulan', date('Y-m'), PARAM_RAW);
$kelasid = optional_param('kelas', 0, PARAM_INT);
$mode    = optional_param('mode', 'harian', PARAM_ALPHA);

/* =========================================================
 * VALIDASI TANGGAL
 * ========================================================= */

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    $tanggal = date('Y-m-d');
}

if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) {
    $bulan = date('Y-m');
}

/* =========================================================
 * DETEKSI WALI KELAS
 * mapping: cohortid => userid
 * ========================================================= */

$iswalikelas = false;
$kelaswaliid = 0;

$jsonwalikelas = get_config(
    'local_jurnalmengajar',
    'wali_kelas_mapping'
);

$mappingwalikelas = json_decode(
    $jsonwalikelas,
    true
);

if (is_array($mappingwalikelas)) {

    foreach ($mappingwalikelas as $cohortid => $useridwali) {

        if ((int)$useridwali === (int)$USER->id) {

            $iswalikelas = true;
            $kelaswaliid = (int)$cohortid;

            break;
        }
    }
}

/*
 * Wali kelas otomatis menggunakan kelasnya.
 */
if ($iswalikelas) {
    $kelasid = $kelaswaliid;
}

/* =========================================================
 * DAFTAR COHORT
 * ========================================================= */

$cohorts = $DB->get_records(
    'cohort',
    null,
    'name ASC',
    'id,name'
);

/* =========================================================
 * FUNGSI STATUS
 * ========================================================= */

function absensi_harian_label($status) {

    $status = strtolower(trim((string)$status));

    switch ($status) {

        case 'sakit':
            return 'Sakit';

        case 'ijin':
        case 'izin':
            return 'Ijin';

        case 'alpa':
        case 'alpha':
            return 'Alpa';

        case 'dispensasi':
            return 'Dispensasi';

        default:
            return 'Hadir';
    }
}

function absensi_harian_class($status) {

    $status = strtolower(trim((string)$status));

    switch ($status) {

        case 'sakit':
            return 'status-sakit';

        case 'ijin':
        case 'izin':
            return 'status-ijin';

        case 'alpa':
        case 'alpha':
            return 'status-alpa';

        case 'dispensasi':
            return 'status-dispensasi';

        default:
            return 'status-hadir';
    }
}

/* =========================================================
 * FUNGSI AMBIL SISWA
 * ========================================================= */

function absensi_harian_get_siswa($kelasid) {

    global $DB;

    $sql = "
        SELECT
            u.id,
            u.firstname,
            u.lastname
        FROM {cohort_members} cm
        JOIN {user} u
            ON u.id = cm.userid
        WHERE cm.cohortid = :cohortid
          AND u.deleted = 0
        ORDER BY u.lastname ASC, u.firstname ASC
    ";

    return $DB->get_records_sql(
        $sql,
        ['cohortid' => $kelasid]
    );
}

/* =========================================================
 * FUNGSI AMBIL JURNAL SATU TANGGAL
 * ========================================================= */

function absensi_harian_get_jurnal($kelasid, $tanggal) {

    global $DB;

    $starttime = strtotime($tanggal . ' 00:00:00');
    $endtime   = strtotime($tanggal . ' 23:59:59');

    $sql = "
        SELECT *
        FROM {local_jurnalmengajar}
        WHERE kelas = :kelas
          AND timecreated BETWEEN :starttime AND :endtime
        ORDER BY timecreated ASC, id ASC
    ";

    return $DB->get_records_sql(
        $sql,
        [
            'kelas'     => $kelasid,
            'starttime' => $starttime,
            'endtime'   => $endtime
        ]
    );
}

/* =========================================================
 * FUNGSI BENTUK DATA ABSENSI
 * ========================================================= */

function absensi_harian_build_data($jurnals) {

    $jam = [];
    $absensi = [];
    $absensinama = [];

    foreach ($jurnals as $jurnal) {

        $daftarjam = explode(
            ',',
            (string)$jurnal->jamke
        );

        /* ---------------------------------------------
         * ABSENID
         * --------------------------------------------- */

        $dataabsenid = [];

        if (!empty($jurnal->absenid)) {

            $decoded = json_decode(
                $jurnal->absenid,
                true
            );

            if (is_array($decoded)) {
                $dataabsenid = $decoded;
            }
        }

        /* ---------------------------------------------
         * ABSEN NAMA
         * fallback jurnal lama
         * --------------------------------------------- */

        $dataabsen = [];

        if (!empty($jurnal->absen)) {

            $decoded = json_decode(
                $jurnal->absen,
                true
            );

            if (is_array($decoded)) {
                $dataabsen = $decoded;
            }
        }

        /* ---------------------------------------------
         * MASUKKAN KE SETIAP JAM
         * --------------------------------------------- */

        foreach ($daftarjam as $j) {

            $j = trim($j);

            if ($j === '' || !ctype_digit($j)) {
                continue;
            }

            $nomorjam = (int)$j;

            if ($nomorjam <= 0) {
                continue;
            }

            $jam[$nomorjam] = true;

            if (!isset($absensi[$nomorjam])) {
                $absensi[$nomorjam] = [];
            }

            if (!isset($absensinama[$nomorjam])) {
                $absensinama[$nomorjam] = [];
            }

            /*
             * Karena jurnal ASC,
             * data jurnal terakhir akan menggantikan
             * data sebelumnya jika jam sama.
             */

            foreach ($dataabsenid as $userid => $status) {

                $userid = (int)$userid;

                if ($userid <= 0) {
                    continue;
                }

                $absensi[$nomorjam][$userid] =
                    strtolower(trim((string)$status));
            }

            foreach ($dataabsen as $nama => $status) {

                $nama = trim((string)$nama);

                if ($nama === '') {
                    continue;
                }

                $absensinama[$nomorjam][$nama] =
                    strtolower(trim((string)$status));
            }
        }
    }

    $jam = array_keys($jam);

    sort($jam, SORT_NUMERIC);

    return [
        'jam'         => $jam,
        'absensi'     => $absensi,
        'absensinama' => $absensinama
    ];
}

/* =========================================================
 * HEADER
 * ========================================================= */

echo $OUTPUT->header();

?>

<style>

.absensi-container {
    width: 100%;
}

.filter-box {
    background: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 20px;
}

.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: end;
}

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-item label {
    font-weight: bold;
    font-size: 13px;
}

.filter-item input,
.filter-item select {
    min-width: 180px;
    padding: 7px 9px;
    border: 1px solid #bbb;
    border-radius: 4px;
}

.btn-tampilkan,
.btn-cetak {
    padding: 8px 18px;
    border: 0;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
}

.btn-tampilkan {
    background: #1976d2;
    color: white;
}

.btn-cetak {
    background: #388e3c;
    color: white;
}

.btn-tampilkan:hover {
    background: #125ca5;
}

.btn-cetak:hover {
    background: #27682b;
}

.info-jadwal {
    margin-bottom: 15px;
    font-size: 14px;
}

.absensi-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.absensi-table th,
.absensi-table td {
    border: 1px solid #999;
    padding: 8px 6px;
    text-align: center;
}

.absensi-table th {
    background: #eeeeee;
    font-weight: bold;
}

.absensi-table th.nama,
.absensi-table td.nama {
    text-align: left;
}

.absensi-table th.no,
.absensi-table td.no {
    width: 45px;
}

.absensi-table th.jam {
    min-width: 70px;
}

.status-hadir {
    font-weight: bold;
}

.status-sakit {
    color: #1976d2;
    font-weight: bold;
}

.status-ijin {
    color: #e65100;
    font-weight: bold;
}

.status-alpa {
    color: #c62828;
    font-weight: bold;
}

.status-dispensasi {
    color: #6a1b9a;
    font-weight: bold;
}

.keterangan-status {
    margin-top: 15px;
    font-size: 13px;
}

.keterangan-status span {
    margin-right: 20px;
}

.tidak-ada {
    padding: 20px;
    background: #fff3cd;
    border: 1px solid #ffe69c;
    border-radius: 5px;
    margin-top: 15px;
}

/* =====================================================
 * CETAK
 * ===================================================== */

@media print {

    .no-print,
    .filter-box {
        display: none !important;
    }

    .absensi-table {
        font-size: 11px;
    }

    .absensi-table th,
    .absensi-table td {
        padding: 5px 4px;
    }

    .print-page {
        page-break-after: always;
        break-after: page;
    }

    .print-page:last-child {
        page-break-after: auto;
        break-after: auto;
    }

    .print-header {
        text-align: center;
        margin-bottom: 15px;
    }

@page {
    size: A4 portrait;
    margin: 10mm;
}
}

</style>

<div class="absensi-container">

<h2>Absensi Harian</h2>

<!-- =====================================================
     FILTER
     ===================================================== -->

<div class="filter-box no-print">

<form method="get">

<div class="filter-row">

    <?php if (!$iswalikelas): ?>

        <!-- =================================================
             KELAS
             ================================================= -->

        <div class="filter-item">

            <label for="kelas">
                Kelas
            </label>

            <select
                name="kelas"
                id="kelas"
            >

                <option value="0">
                    -- Pilih Kelas --
                </option>

                <?php foreach ($cohorts as $cohort): ?>

                    <option
                        value="<?php echo (int)$cohort->id; ?>"
                        <?php
                        echo ($kelasid == $cohort->id)
                            ? 'selected'
                            : '';
                        ?>
                    >
                        <?php echo s($cohort->name); ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

    <?php else: ?>

        <!-- =================================================
             KELAS WALI
             ================================================= -->

        <input
            type="hidden"
            name="kelas"
            value="<?php echo (int)$kelasid; ?>"
        >

        <div class="filter-item">

            <label>
                Kelas
            </label>

            <select disabled>

                <?php if (isset($cohorts[$kelasid])): ?>

                    <option selected>
                        <?php
                        echo s(
                            $cohorts[$kelasid]->name
                        );
                        ?>
                    </option>

                <?php endif; ?>

            </select>

        </div>

    <?php endif; ?>


    <!-- =================================================
         TANGGAL
         ================================================= -->

    <div class="filter-item">

        <label for="tanggal">
            Tanggal
        </label>

        <input
            type="date"
            name="tanggal"
            id="tanggal"
            value="<?php echo s($tanggal); ?>"
        >

    </div>


    <!-- =================================================
         TAMPILKAN TANGGAL
         ================================================= -->

    <div class="filter-item">

        <label>&nbsp;</label>

        <button
            type="submit"
            name="mode"
            value="harian"
            class="btn-tampilkan"
        >
            Tampilkan Tanggal
        </button>

    </div>


    <!-- =================================================
         BULAN UNTUK CETAK
         ================================================= -->

    <div class="filter-item">

        <label for="bulan">
            Bulan untuk Cetak
        </label>

        <input
            type="month"
            name="bulan"
            id="bulan"
            value="<?php echo s($bulan); ?>"
        >

    </div>


    <!-- =================================================
         CETAK PER BULAN
         ================================================= -->

    <div class="filter-item">

        <label>&nbsp;</label>

        <button
            type="submit"
            name="mode"
            value="bulanan"
            class="btn-cetak"
        >
            🖨 Cetak Per Bulan
        </button>

    </div>

</div>

</form>

</div>


<?php

/* =========================================================
 * VALIDASI KELAS
 * ========================================================= */

if (!$kelasid) {

    echo html_writer::div(
        'Silakan pilih kelas terlebih dahulu.',
        'tidak-ada'
    );

    echo $OUTPUT->footer();
    exit;
}

$cohort = $DB->get_record(
    'cohort',
    ['id' => $kelasid],
    'id,name'
);

if (!$cohort) {

    echo html_writer::div(
        'Kelas tidak ditemukan.',
        'tidak-ada'
    );

    echo $OUTPUT->footer();
    exit;
}

/* =========================================================
 * MODE BULANAN
 * ========================================================= */

if ($mode === 'bulanan') {

    /*
     * Awal dan akhir bulan.
     */

    $startbulan = strtotime(
        $bulan . '-01 00:00:00'
    );

    $endbulan = strtotime(
        date(
            'Y-m-t 23:59:59',
            $startbulan
        )
    );

    /*
     * Ambil semua jurnal bulan tersebut.
     */

    $sql = "
        SELECT *
        FROM {local_jurnalmengajar}
        WHERE kelas = :kelas
          AND timecreated BETWEEN :awal AND :akhir
        ORDER BY timecreated ASC, id ASC
    ";

    $jurnalsbulan = $DB->get_records_sql(
        $sql,
        [
            'kelas' => $kelasid,
            'awal'  => $startbulan,
            'akhir' => $endbulan
        ]
    );

    /*
     * Kelompokkan berdasarkan tanggal.
     */

    $jurnalpertanggal = [];

    foreach ($jurnalsbulan as $jurnal) {

        $tg = date(
            'Y-m-d',
            $jurnal->timecreated
        );

        if (!isset($jurnalpertanggal[$tg])) {
            $jurnalpertanggal[$tg] = [];
        }

        $jurnalpertanggal[$tg][] = $jurnal;
    }

    ksort($jurnalpertanggal);

    if (empty($jurnalpertanggal)) {

        echo html_writer::div(
            'Tidak ada jurnal KBM pada bulan '
            . s($bulan)
            . ' untuk kelas '
            . s($cohort->name)
            . '.',
            'tidak-ada'
        );

        echo $OUTPUT->footer();
        exit;
    }

    /*
     * Ambil siswa satu kali.
     */

    $siswa = absensi_harian_get_siswa(
        $kelasid
    );

    /*
     * =====================================================
     * CETAK SEMUA TANGGAL
     * =====================================================
     */

    foreach ($jurnalpertanggal as $tg => $jurnaltanggal) {

        $dataabsensi =
            absensi_harian_build_data(
                $jurnaltanggal
            );

        $jam =
            $dataabsensi['jam'];

        $absensi =
            $dataabsensi['absensi'];

        $absensinama =
            $dataabsensi['absensinama'];

        $timestamp =
            strtotime($tg . ' 00:00:00');

        ?>

        <div class="print-page">

            <div class="print-header">

                <h2>
                    ABSENSI HARIAN MURID
                </h2>

                <strong>
                    <?php
                    echo s($cohort->name);
                    ?>
                </strong>

                <br>

                <?php
echo s(tanggal_indo($timestamp, 'judul'));
?>

            </div>

            <?php

            if (empty($jam)) {

                echo html_writer::div(
                    'Tidak ada jam pelajaran pada tanggal ini.',
                    'tidak-ada'
                );

                echo '</div>';

                continue;
            }

            ?>

            <table class="absensi-table">

                <thead>

                <tr>

                    <th class="no">
                        No
                    </th>

                    <th class="nama">
                        Nama Murid
                    </th>

                    <?php foreach ($jam as $nomorjam): ?>

                        <th class="jam">
                            Jam <?php echo (int)$nomorjam; ?>
                        </th>

                    <?php endforeach; ?>

                </tr>

                </thead>

                <tbody>

                <?php

                $no = 1;

                foreach ($siswa as $murid):

                    $namasiswa = '';

                    if (function_exists('format_nama_siswa')) {

                        $namasiswa =
                            format_nama_siswa(
                                $murid->lastname
                            );

                    } else {

                        $namasiswa =
                            trim(
                                $murid->firstname
                                . ' '
                                . $murid->lastname
                            );
                    }

                    ?>

                    <tr>

                        <td>
                            <?php echo $no++; ?>
                        </td>

                        <td class="nama">
                            <?php
                            echo s($namasiswa);
                            ?>
                        </td>

                        <?php foreach ($jam as $nomorjam): ?>

                            <?php

                            $status = 'hadir';

                            /*
                             * Utamakan userid.
                             */

                            if (
                                isset($absensi[$nomorjam]) &&
                                array_key_exists(
                                    $murid->id,
                                    $absensi[$nomorjam]
                                )
                            ) {

                                $status =
                                    $absensi[$nomorjam][$murid->id];

                            } elseif (
                                isset($absensinama[$nomorjam]) &&
                                array_key_exists(
                                    $namasiswa,
                                    $absensinama[$nomorjam]
                                )
                            ) {

                                /*
                                 * Fallback nama.
                                 */

                                $status =
                                    $absensinama[$nomorjam][$namasiswa];
                            }

                            $label =
                                absensi_harian_label(
                                    $status
                                );

                            $class =
                                absensi_harian_class(
                                    $status
                                );

                            ?>

                            <td class="<?php echo s($class); ?>">

                                <?php
                                if ($label === 'Hadir') {
                                    echo '✓';
                                } else {
                                    echo s($label);
                                }
                                ?>

                            </td>

                        <?php endforeach; ?>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

            <div class="keterangan-status">

                <strong>Keterangan:</strong>

                <span class="status-hadir">
                    ✓ Hadir
                </span>

                <span class="status-sakit">
                    Sakit
                </span>

                <span class="status-ijin">
                    Ijin
                </span>

                <span class="status-alpa">
                    Alpa
                </span>

                <span class="status-dispensasi">
                    Dispensasi
                </span>

            </div>

        </div>

        <?php
    }

    /*
     * Saat mode cetak bulanan, langsung munculkan
     * dialog print browser.
     */

    ?>

    <script>

    window.addEventListener(
        'load',
        function() {
            window.print();
        }
    );

    </script>

    <?php

    echo $OUTPUT->footer();
    exit;
}

/* =========================================================
 * MODE HARIAN
 * ========================================================= */

$jurnals =
    absensi_harian_get_jurnal(
        $kelasid,
        $tanggal
    );

$siswa =
    absensi_harian_get_siswa(
        $kelasid
    );

echo '<div class="info-jadwal">';

echo '<strong>Kelas:</strong> '
    . s($cohort->name);

echo '<br>';

echo '<strong>Tanggal:</strong> '
    . s(
        tanggal_indo(
            strtotime($tanggal),
            'judul'
        )
    );

echo '<br>';

echo '<strong>Jumlah murid:</strong> '
    . count($siswa);

echo '</div>';

/* =========================================================
 * TIDAK ADA JURNAL
 * ========================================================= */

if (empty($jurnals)) {

    echo html_writer::div(
        'Belum ada jurnal mengajar untuk kelas ini pada tanggal tersebut.',
        'tidak-ada'
    );

    echo $OUTPUT->footer();
    exit;
}

/* =========================================================
 * DATA ABSENSI
 * ========================================================= */

$dataabsensi =
    absensi_harian_build_data(
        $jurnals
    );

$jam =
    $dataabsensi['jam'];

$absensi =
    $dataabsensi['absensi'];

$absensinama =
    $dataabsensi['absensinama'];

/* =========================================================
 * TABEL HARIAN
 * ========================================================= */

?>

<table class="absensi-table">

<thead>

<tr>

    <th class="no">
        No
    </th>

    <th class="nama">
        Nama Murid
    </th>

    <?php foreach ($jam as $nomorjam): ?>

        <th class="jam">
            Jam <?php echo (int)$nomorjam; ?>
        </th>

    <?php endforeach; ?>

</tr>

</thead>

<tbody>

<?php

$no = 1;

foreach ($siswa as $murid):

    if (function_exists('format_nama_siswa')) {

        $namasiswa =
            format_nama_siswa(
                $murid->lastname
            );

    } else {

        $namasiswa =
            trim(
                $murid->firstname
                . ' '
                . $murid->lastname
            );
    }

    ?>

    <tr>

        <td>
            <?php echo $no++; ?>
        </td>

        <td class="nama">
            <?php
            echo s($namasiswa);
            ?>
        </td>

        <?php foreach ($jam as $nomorjam): ?>

            <?php

            $status = 'hadir';

            if (
                isset($absensi[$nomorjam]) &&
                array_key_exists(
                    $murid->id,
                    $absensi[$nomorjam]
                )
            ) {

                $status =
                    $absensi[$nomorjam][$murid->id];

            } elseif (
                isset($absensinama[$nomorjam]) &&
                array_key_exists(
                    $namasiswa,
                    $absensinama[$nomorjam]
                )
            ) {

                $status =
                    $absensinama[$nomorjam][$namasiswa];
            }

            $label =
                absensi_harian_label(
                    $status
                );

            $class =
                absensi_harian_class(
                    $status
                );

            ?>

            <td class="<?php echo s($class); ?>">

                <?php

                if ($label === 'Hadir') {
                    echo '✓';
                } else {
                    echo s($label);
                }

                ?>

            </td>

        <?php endforeach; ?>

    </tr>

<?php endforeach; ?>

</tbody>

</table>

<div class="keterangan-status">

    <strong>Keterangan:</strong>

    <span class="status-hadir">
        ✓ Hadir
    </span>

    <span class="status-sakit">
        Sakit
    </span>

    <span class="status-ijin">
        Ijin
    </span>

    <span class="status-alpa">
        Alpa
    </span>

    <span class="status-dispensasi">
        Dispensasi
    </span>

</div>

</div>

<?php

echo $OUTPUT->footer();
