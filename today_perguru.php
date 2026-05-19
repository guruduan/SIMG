<?php
require_once(__DIR__ . '/../../config.php');
require_login();
require_once(__DIR__ . '/lib.php');

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/today_perguru.php'));
$PAGE->set_title('Jurnal Hari Ini Per Guru');
$PAGE->set_heading('Jurnal Mengajar Hari Ini Per Guru');

echo $OUTPUT->header();

// Judul Halaman dengan Ikon Modern (Konsisten dengan today.php)
echo html_writer::start_div('d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom');
echo html_writer::tag('h3', '<i class="fa fa-users text-primary"></i> Jurnal Hari Ini <small class="text-muted" style="font-size:0.95rem;">(Per Guru)</small>', ['class' => 'm-0 font-weight-bold']);
echo html_writer::link(new moodle_url('/my/'), '<i class="fa fa-home"></i> Kembali Ke Beranda', ['class' => 'btn btn-outline-danger btn-sm']);
echo html_writer::end_div();

/*
=====================================================
🔁 TAB SWITCH (MENGGUNAKAN BOOTSTRAP BUTTON GROUP)
=====================================================
*/
echo html_writer::start_div('mb-4 d-flex justify-content-start');
echo html_writer::start_div('btn-group shadow-sm');

echo html_writer::link(new moodle_url('/local/jurnalmengajar/today.php'), '<i class="fa fa-clock-o"></i> Urut Waktu', ['class' => 'btn btn-outline-secondary btn-sm px-3']);
echo html_writer::link(new moodle_url('/local/jurnalmengajar/today_perguru.php'), '<i class="fa fa-user"></i> Per Guru', ['class' => 'btn btn-primary btn-sm px-3']);
echo html_writer::link(new moodle_url('/local/jurnalmengajar/bydate.php'), '<i class="fa fa-calendar"></i> Ke Tanggal', ['class' => 'btn btn-outline-secondary btn-sm px-3']);

echo html_writer::end_div();
echo html_writer::end_div();

global $DB;
$start = strtotime('today 00:00:00');
$end   = strtotime('today 23:59:59');

$sql = "SELECT j.*, u.lastname
        FROM {local_jurnalmengajar} j
        JOIN {user} u ON j.userid = u.id
        WHERE j.timecreated BETWEEN :start AND :end
        ORDER BY u.lastname ASC, j.timecreated ASC";
$entries = $DB->get_records_sql($sql, ['start' => $start, 'end' => $end]);

// Group by Guru
$grouped = [];
foreach ($entries as $e) {
    $grouped[$e->lastname][] = $e;
}

/*
=====================================================
TAMPILKAN DATA (FORMAT TABEL MINI PER GURU)
=====================================================
*/
if ($grouped) {
    foreach ($grouped as $guru => $list) {
        
        // Blok Nama Guru sebagai Header Kelompok
        echo html_writer::start_div('card mb-4 shadow-sm');
        echo html_writer::start_div('card-header bg-dark text-white p-2 d-flex justify-content-between align-items-center');
        echo html_writer::tag('h5', '<i class="fa fa-user-circle-o mr-2"></i> ' . s($guru), ['class' => 'm-0 font-weight-bold', 'style' => 'font-size: 1.05rem;']);
        echo html_writer::tag('span', count($list) . ' Kelas Diajar', ['class' => 'badge badge-light text-dark font-weight-bold']);
        echo html_writer::end_div(); // End Card Header
        
        echo html_writer::start_div('card-body p-0');
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm table-striped table-hover m-0 bg-white">';
        echo '<thead class="bg-light">';
        echo '<tr>';
        echo '<th style="width: 10%;" class="text-center pl-3">Kelas</th>';
        echo '<th style="width: 10%;" class="text-center">Jam Ke</th>';
        echo '<th style="width: 20%;">Mata Pelajaran</th>';
        echo '<th>Materi Pembelajaran</th>';
        echo '<th style="width: 20%;">Keterangan Absen</th>';
        echo '<th style="width: 15%;" class="text-center">Waktu Isi</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($list as $e) {
            $kelas = $DB->get_field('cohort', 'name', ['id' => $e->kelas]) ?? '???';
            
            // Pemrosesan teks absensi siswa
            $abs = json_decode($e->absen, true);
            if (is_array($abs) && !empty($abs)) {
                $abtxt = implode(', ', array_map(fn($n, $a) => "$n ($a)", array_keys($abs), $abs));
                $absen_cell = html_writer::tag('span', s(shorten_text($abtxt, 35)), ['class' => 'text-danger font-weight-bold', 'title' => $abtxt]);
            } else {
                $absen_cell = html_writer::tag('span', '<i class="fa fa-check-circle text-success"></i> Hadir Semua', ['class' => 'text-success small']);
            }

            echo '<tr>';
            // Kolom Kelas berbentuk badge tipis
            echo '<td class="text-center align-middle pl-3"><span class="badge badge-info p-1 px-2 d-block">' . s($kelas) . '</span></td>';
            // Kolom Jam Ke (Teks biasa normal)
            echo '<td class="text-center align-middle" style="font-size: 0.9rem; color: #212529;">' . s($e->jamke) . '</td>';
            // Kolom Mata Pelajaran (Teks biasa normal)
            echo '<td class="align-middle" style="font-size: 0.9rem; color: #212529;">' . s($e->matapelajaran) . '</td>';
            // Kolom Materi Pembelajaran (Teks biasa normal)
            echo '<td class="align-middle text-justify" style="font-size: 0.9rem; color: #212529;" title="' . s($e->materi) . '">' . format_text(shorten_text($e->materi, 50)) . '</td>';
            // Kolom Absensi
            echo '<td class="align-middle" style="font-size: 0.85rem;">' . $absen_cell . '</td>';
            // Kolom Waktu Isi (Teks biasa normal)
            echo '<td class="text-center align-middle small" style="color: #212529;"><i class="fa fa-clock-o text-muted mr-1"></i> ' . tanggal_indo($e->timecreated) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>'; // End table-responsive
        echo html_writer::end_div(); // End Card Body
        echo html_writer::end_div(); // End Card
    }
} else {
    echo html_writer::div('<i class="fa fa-info-circle"></i> Belum ada entri jurnal mengajar yang dimasukkan untuk hari ini.', 'alert alert-info shadow-sm p-3 font-weight-bold');
}

echo $OUTPUT->footer();
