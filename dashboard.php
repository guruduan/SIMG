<?php
require('../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_url(new moodle_url('/local/jurnalmengajar/dashboard.php'));
$PAGE->set_context($context);
$PAGE->set_title('Beranda Guru');
$PAGE->set_heading('');

global $DB, $USER;
$iswali = is_wali_kelas($USER->id);
$isbk   = is_guru_bk($USER->id);

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
		<div class="card-body"
		     style="background:#7fc7f5;color:#000;">
		    <h1><?php echo $jurnal; ?></h1>
		    <h5>📖 Jurnal Mengajar Guru</h5>
		</div>
	    </div>
	</div>

	<div class="col">
	    <div class="card text-center">
		<div class="card-body"
		     style="background:#be9ee2;color:#000;">
		    <h1><?php echo $guruwali; ?></h1>
		    <h5>📖 Jurnal Guru Wali</h5>
		</div>
	    </div>
	</div>

	<?php if ($isbk): ?>
	<div class="col">
	    <div class="card text-center">
		<div class="card-body text-white"
		     style="background:#17a2b8;">
		    <h1><?php echo $bk; ?></h1>
		    <h5>📖 Layanan Guru BK</h5>
		</div>
	    </div>
	</div>
	<div class="col">
	    <div class="card text-center">
		<div class="card-body text-white"
		     style="background:#007bff;">
		    <h1><?php echo $pembinaan; ?></h1>
		    <h5>📖 Pembinaan Guru BK</h5>
		</div>
	    </div>
	</div>
	<?php endif; ?>

	<?php if ($iswali): ?>
	<div class="col">
	    <div class="card text-center">
		<div class="card-body text-white"
		     style="background:#0a1347;">
		    <h1><?php echo $jurnalwalikelas; ?></h1>
		    <h5>📖 Jurnal Wali Kelas</h5>
		</div>
	    </div>
	</div>
	<?php endif; ?>

</div> <!-- row statistik -->

</div> <!-- card-body -->

</div> <!-- card statistik -->

<h3>Menu Cepat</h3>

<div class="row">

	<div class="col-lg-3 col-md-4 col-sm-6 mb-2">
	    <a class="btn fw-bold w-100"
	       style="background:#7fc7f5;color:#000;"
	       href="index.php">
		📖 Input Jurnal Mengajar
	    </a>
	</div>

	<div class="col-lg-3 col-md-4 col-sm-6 mb-2">
	    <a class="btn fw-bold w-100"
	       style="background:#7fc7f5;color:#000;"
	       href="export_form.php">
		📤 Ekspor Jurnal Mengajar
	    </a>
	</div>
	
	<div class="col-lg-3 col-md-4 col-sm-6 mb-2">
	    <a class="btn fw-bold w-100"
	       style="background:#be9ee2;color:#000;"
	       href="jurnalguruwali.php">
		👨‍🏫 Input Jurnal Guru Wali
	    </a>
	</div>

	<div class="col-lg-3 col-md-4 col-sm-6 mb-2">
	    <a class="btn fw-bold w-100"
	       style="background:#be9ee2;color:#000;"
	       href="exportguruwali_form.php">
		📤 Ekspor Jurnal Guru Wali
	    </a>
	</div>

	<div class="col-lg-3 col-md-4 col-sm-6 mb-2">
	    <a class="btn fw-bold w-100"
	       style="background:#9adca6;color:#000;"
	       href="riwayat_terbaru.php">
		🚸 Riwayat Murid Terbaru
	    </a>
	</div>
	
	<?php if ($isbk): ?>

	<div class="col-lg-3 col-md-4 col-sm-6 mb-2">
	    <a class="btn btn-info btn-block w-100"
	       href="layananbk.php">
		🧠 Input Layanan BK
	    </a>
	</div>

	<div class="col-lg-3 col-md-4 col-sm-6 mb-2">
	    <a class="btn btn-primary btn-block w-100"
	       href="pembinaan.php">
		📝 Input Pembinaan BK
	    </a>
	</div>

	<?php endif; ?>

	<?php if ($iswali): ?>

	<div class="col-lg-3 col-md-4 col-sm-6 mb-2">
	    <a class="btn text-white fw-bold w-100"
	       style="background:#0a1347;"
	       href="jurnal_walikelas.php">
		📄 Input Jurnal Wali Kelas
	    </a>
	</div>

	<?php endif; ?>


        <div class="col-lg-3 col-md-4 col-sm-6 mb-2">
            <a class="btn fw-bold w-100" style="background:#eb7734;color:#000;"
               href="jadwal_pengawas_view.php">
                Jadwal Pengawas Asesmen
            </a>
        </div>

        <div class="col-lg-3 col-md-4 col-sm-6 mb-2">
            <a class="btn fw-bold w-100" style="background:#eb7734;color:#000;"
               href="berita_acara.php">
                📊 Isi Berita Acara Asesmen
            </a>
        </div>
        
        <div class="col-lg-3 col-md-4 col-sm-6 mb-2">
            <a class="btn fw-bold w-100" style="background:#eb7734;color:#000;"
               href="riwayat_berita_acara.php">
                Riwayat Berita Acara Asesmen
            </a>
        </div>

  </div> <!-- row menu -->

</div> <!-- container-fluid -->

<?php
echo $OUTPUT->footer();
