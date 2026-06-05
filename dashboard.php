<?php
require('../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_url(new moodle_url('/local/jurnalmengajar/dashboard.php'));
$PAGE->set_context($context);
$PAGE->set_title('Dashboard Guru');
$PAGE->set_heading('');

global $DB, $USER;

// Awal bulan sampai hari ini.
$awalbulan = strtotime(date('Y-m-01 00:00:00'));

// Hitung statistik bulan berjalan.
$jurnal = $DB->count_records_select(
    'local_jurnalmengajar',
    'userid = ? AND timecreated >= ?',
    [$USER->id, $awalbulan]
);

$guruwali = $DB->count_records_select(
    'local_jurnalguruwali',
    'guruid = ? AND timecreated >= ?',
    [$USER->id, $awalbulan]
);

$bk = 0;
if ($DB->get_manager()->table_exists('local_jurnallayananbk')) {
    $bk = $DB->count_records_select(
        'local_jurnallayananbk',
        'userid = ? AND timecreated >= ?',
        [$USER->id, $awalbulan]
    );
}

$pembinaan = 0;
if ($DB->get_manager()->table_exists('local_jurnalpembinaan')) {
    $pembinaan = $DB->count_records_select(
        'local_jurnalpembinaan',
        'userid = ? AND timecreated >= ?',
        [$USER->id, $awalbulan]
    );
}

$jurnalwalikelas = $DB->count_records_select(
    'local_jurnalwalikelas',
    'userid = ? AND timecreated >= ?',
    [$USER->id, $awalbulan]
);

echo $OUTPUT->header();

?>

<div class="container-fluid">

<div class="alert alert-info">
    <h4>Selamat Datang, <?php echo format_string($USER->lastname); ?></h4>
    <p><?php echo tanggal_indo(time(), 'judul'); ?></p>
</div>

<div class="card mb-3 shadow-sm">

    <div class="card-header bg-primary text-white">
        <strong>📊 Statistik Bulan <?php echo tanggal_indo(time(), 'bulan'); ?></strong>
    </div>

    <div class="card-body">

        <div class="row">

        <div class="col">
            <div class="card text-center">
                <div class="card-body">
                    <h1><?php echo $jurnal; ?></h1>
                    <h5>📖 Jurnal Mengajar Guru</h5>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card text-center">
                <div class="card-body">
                    <h1><?php echo $guruwali; ?></h1>
                    <h5>👨‍🏫 Pendampingan Guru Wali</h5>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card text-center">
                <div class="card-body">
                    <h1><?php echo $bk; ?></h1>
                    <h5>🧠 Layanan Guru BK</h5>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card text-center">
                <div class="card-body">
                    <h1><?php echo $pembinaan; ?></h1>
                    <h5>📝 Pembinaan Guru BK</h5>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
	    <div class="card text-center">
		<div class="card-body">
		    <h1><?php echo $jurnalwalikelas; ?></h1>
		    <h5>📋 Jurnal Wali Kelas</h5>
		</div>
	    </div>
	</div>
    </div>

    <div class="row">


    </div>

    <hr>

    <h3>Menu Cepat</h3>

    <div class="row">

        <div class="col-md-3 mb-2">
            <a class="btn btn-primary btn-block w-100"
               href="index.php">
                📖 Input Jurnal Mengajar
            </a>
        </div>

        <div class="col-md-3 mb-2">
            <a class="btn btn-secondary btn-block w-100"
               href="jurnalguruwali.php">
                👨‍🏫 Input Jurnal Guru Wali
            </a>
        </div>

        <div class="col-md-3 mb-2">
            <a class="btn btn-info btn-block w-100"
               href="layananbk.php">
                🧠 Input Layanan BK
            </a>
        </div>

        <div class="col-md-3 mb-2">
            <a class="btn btn-warning btn-block w-100"
               href="pembinaan.php">
                📝 Input Pembinaan BK
            </a>
        </div>

        <div class="col-md-3 mb-2 mt-2">
            <a class="btn btn-success btn-block w-100"
               href="jurnal_walikelas.php">
                📄 Input Jurnal Wali Kelas
            </a>
        </div>

        <div class="col-md-3 mb-2 mt-2">
            <a class="btn btn-dark btn-block w-100"
               href="log_perkembangan.php">
                👥 Log Perkembangan Murid
            </a>
        </div>

        <div class="col-md-3 mb-2 mt-2">
            <a class="btn btn-outline-primary btn-block w-100"
               href="rekap_perminggu.php">
                📊 Rekap Mingguan KBM Guru
            </a>
        </div>
        
        <div class="col-md-3 mb-2 mt-2">
            <a class="btn btn-danger btn-block w-100"
               href="izin_murid.php">
                📝 Isi Surat Izin Murid
            </a>
        </div>

    </div>

</div>

<?php
echo $OUTPUT->footer();
