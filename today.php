<?php
require_once(__DIR__ . '/../../config.php');
require_login();
require_once(__DIR__ . '/lib.php');

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/today.php'));
$PAGE->set_title('Jurnal Hari Ini');
$PAGE->set_heading('Jurnal Mengajar Hari Ini');

echo $OUTPUT->header();

// Judul Halaman dengan Ikon Modern
echo html_writer::start_div('d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom');
echo html_writer::tag('h3', '<i class="fa fa-calendar-check-o text-primary"></i> Jurnal Hari Ini <small class="text-muted" style="font-size:0.95rem;">(Urut Waktu)</small>', ['class' => 'm-0 font-weight-bold']);
echo html_writer::link(new moodle_url('/my/'), '<i class="fa fa-home"></i> Kembali Ke Beranda', ['class' => 'btn btn-outline-danger btn-sm']);
echo html_writer::end_div();

/*
=====================================================
🔁 TAB SWITCH (MENGGUNAKAN BOOTSTRAP BUTTON GROUP)
=====================================================
*/
echo html_writer::start_div('mb-4 d-flex justify-content-start');
echo html_writer::start_div('btn-group shadow-sm');

echo html_writer::link(new moodle_url('/local/jurnalmengajar/today.php'), '<i class="fa fa-clock-o"></i> Urut Waktu', ['class' => 'btn btn-primary btn-sm px-3']);
echo html_writer::link(new moodle_url('/local/jurnalmengajar/today_perguru.php'), '<i class="fa fa-user"></i> Per Guru', ['class' => 'btn btn-outline-secondary btn-sm px-3']);
echo html_writer::link(new moodle_url('/local/jurnalmengajar/bydate.php'), '<i class="fa fa-calendar"></i> Ke Tanggal', ['class' => 'btn btn-outline-secondary btn-sm px-3']);

echo html_writer::end_div();
echo html_writer::end_div();

global $DB;
$start = strtotime('today midnight');
$end = strtotime('tomorrow midnight') - 1;

$sql = "SELECT j.*, u.lastname
        FROM {local_jurnalmengajar} j
        JOIN {user} u ON j.userid = u.id
        WHERE j.timecreated BETWEEN :start AND :end
        ORDER BY j.timecreated ASC";
$entries = $DB->get_records_sql($sql, ['start' => $start, 'end' => $end]);

/*
=====================================================
PROSES & TAMPILKAN TABEL DATA
=====================================================
*/
if ($entries) {
    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered table-hover bg-white shadow-sm align-middle">';
    echo '<thead class="thead-dark">';
    echo '<tr>';
    echo '<th style="width: 4%;" class="text-center">No</th>';
    echo '<th style="width: 14%;">Nama Guru</th>';
    echo '<th style="width: 8%;" class="text-center">Kelas</th>';
    echo '<th style="width: 8%;" class="text-center">Jam Ke</th>';
    echo '<th style="width: 15%;">Mata Pelajaran</th>';
    echo '<th>Materi Pembelajaran</th>';
    echo '<th style="width: 15%;">Keterangan Absen</th>';
    echo '<th style="width: 13%;" class="text-center">Waktu Isi</th>';
    echo '<th style="width: 12%;">Keterangan</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    $no = 1;
    foreach ($entries as $e) {
        // Pemrosesan Teks Absen
        $abs = json_decode($e->absen, true);
        if (is_array($abs) && !empty($abs)) {
            $abtxt = implode(', ', array_map(fn($n, $a) => "$n ($a)", array_keys($abs), $abs));
            $absen_cell = html_writer::tag('span', shorten_text($abtxt, 30), ['class' => 'text-danger font-weight-bold', 'title' => $abtxt]);
        } else {
            $absen_cell = html_writer::tag('span', '<i class="fa fa-check-circle text-success"></i> Hadir Semua', ['class' => 'text-success small']);
        }

        // Ambil Nama Cohort/Kelas
        $kelas = $DB->get_field('cohort', 'name', ['id' => $e->kelas]) ?? '???';

        echo '<tr>';
        echo '<td class="text-center align-middle font-weight-bold text-muted" style="font-size:0.9rem;">' . $no++ . '</td>';
        echo '<td class="align-middle" style="color: #212529;">' . html_writer::tag('strong', $e->lastname) . '</td>';
        echo '<td class="text-center align-middle"><span class="badge badge-info p-2 d-block" style="font-size:0.85rem;">' . s($kelas) . '</span></td>';
        
        // KOLOM JAM KE: font-weight-bold dihapus, teks diatur menjadi warna biasa (#212529)
        echo '<td class="text-center align-middle" style="font-size:0.95rem; color: #212529;">' . s($e->jamke) . '</td>';
        
        // KOLOM MATA PELAJARAN: font-weight-bold dan text-dark dihapus, teks diatur menjadi warna biasa (#212529)
        echo '<td class="align-middle" style="font-size:0.9rem; color: #212529;">' . s($e->matapelajaran) . '</td>';
        
        // KOLOM MATERI: Menghapus text-secondary dan small, teks diatur menjadi warna biasa (#212529) dengan ukuran standar
echo '<td class="align-middle text-justify" style="font-size: 0.9rem; color: #212529;" title="' . s($e->materi) . '">' . format_text(shorten_text($e->materi, 45)) . '</td>';
        
        // Absen
        echo '<td class="align-middle" style="font-size:0.85rem;">' . $absen_cell . '</td>';
        
        // Waktu created
        echo '<td class="text-center align-middle small" style="color: #212529;"><i class="fa fa-clock-o text-muted mr-1"></i> ' . tanggal_indo($e->timecreated) . '</td>';
        
        // Keterangan Tambahan
        echo '<td class="align-middle text-muted small" title="' . s($e->keterangan) . '">' . s(shorten_text($e->keterangan, 30)) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>'; // End table-responsive
} else {
    echo html_writer::div('<i class="fa fa-info-circle"></i> Belum ada entri jurnal mengajar yang dimasukkan untuk hari ini.', 'alert alert-info shadow-sm p-3 font-weight-bold');
}

echo $OUTPUT->footer();
