<?php
require_once(__DIR__ . '/../../config.php');
require_login();
require_once(__DIR__ . '/lib.php');

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/bydate.php'));
$PAGE->set_title('Jurnal Mengajar per Tanggal');
$PAGE->set_heading('Jurnal Mengajar per Tanggal');

echo $OUTPUT->header();

// Judul Halaman dengan Ikon Modern (Konsisten)
echo html_writer::start_div('d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom');
echo html_writer::tag('h3', '<i class="fa fa-calendar text-primary"></i> Jurnal Mengajar <small class="text-muted" style="font-size:0.95rem;">(Ke Tanggal)</small>', ['class' => 'm-0 font-weight-bold']);
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
echo html_writer::link(new moodle_url('/local/jurnalmengajar/today_perguru.php'), '<i class="fa fa-user"></i> Per Guru', ['class' => 'btn btn-outline-secondary btn-sm px-3']);
echo html_writer::link(new moodle_url('/local/jurnalmengajar/bydate.php'), '<i class="fa fa-calendar"></i> Ke Tanggal', ['class' => 'btn btn-primary btn-sm px-3']);

echo html_writer::end_div();
echo html_writer::end_div();

global $DB;

// Tangani input tanggal
$tanggal = optional_param('tanggal', date('Y-m-d'), PARAM_TEXT);
$timestamp = strtotime($tanggal) ?: time();
$start = strtotime(date('Y-m-d 00:00:00', $timestamp));
$end   = strtotime(date('Y-m-d 23:59:59', $timestamp));

/*
=====================================================
FORM FILTER TANGGAL (STRUKTUR HORIZONTAL RAPI)
=====================================================
*/
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'mb-4 p-3 bg-light rounded border shadow-sm']);
echo html_writer::start_div('row align-items-end');

// Kolom Input Tanggal
echo html_writer::start_div('col-md-4 mb-2 mb-md-0');
echo html_writer::label('Pilih Tanggal', 'tanggal', ['class' => 'font-weight-bold mb-1']);
echo html_writer::empty_tag('input', [
    'type' => 'date',
    'name' => 'tanggal',
    'value' => $tanggal,
    'class' => 'form-control form-control-sm',
    'id' => 'tanggal'
]);
echo html_writer::end_div();

// Kolom Info Hari (Akan diisi oleh JavaScript secara dinamis)
echo html_writer::start_div('col-md-5 mb-2 mb-md-0 align-self-center text-secondary');
echo html_writer::div('', '', ['id' => 'hari-terpilih', 'style' => 'font-size: 1rem; color: #212529;']);
echo html_writer::end_div();

// Kolom Tombol Submit
echo html_writer::start_div('col-md-3');
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Tampilkan Jurnal', 'class' => 'btn btn-primary btn-sm btn-block']);
echo html_writer::end_div();

echo html_writer::end_div(); // End Row
echo html_writer::end_tag('form');

// Tampilkan teks tanggal terpilih di atas tabel
if (!empty($tanggal)) {
    echo html_writer::div(
        '<i class="fa fa-check-square-o text-success mr-1"></i> Menampilkan Data Tanggal: <strong style="color: #212529;">' . tanggal_indo($timestamp, 'judul') . '</strong>',
        'alert alert-secondary bg-white border p-2 mb-3 shadow-sm inline-block'
    );
}

// Ambil entri dari Database
$sql = "SELECT j.*, u.lastname, c.name as namakelas
        FROM {local_jurnalmengajar} j
        JOIN {user} u ON j.userid = u.id
        LEFT JOIN {cohort} c ON j.kelas = c.id
        WHERE j.timecreated BETWEEN :start AND :end
        ORDER BY j.timecreated ASC";
$entries = $DB->get_records_sql($sql, ['start' => $start, 'end' => $end]);

/*
=====================================================
TAMPILKAN TABEL DATA JURNAL
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
        $abs = json_decode($e->absen, true);
        if (is_array($abs) && !empty($abs)) {
            $abtxt = implode(', ', array_map(fn($n, $a) => "$n ($a)", array_keys($abs), $abs));
            $absen_cell = html_writer::tag('span', s(shorten_text($abtxt, 30)), ['class' => 'text-danger font-weight-bold', 'title' => format_string($abtxt)]);
        } else {
            $absen_cell = html_writer::tag('span', '<i class="fa fa-check-circle text-success"></i> Hadir Semua', ['class' => 'text-success small']);
        }
        
        $kelas = $e->namakelas ?? '???';

        echo '<tr>';
        echo '<td class="text-center align-middle font-weight-bold text-muted" style="font-size:0.9rem;">' . $no++ . '</td>';
        echo '<td class="align-middle" style="color: #212529;">' . html_writer::tag('strong', format_string($e->lastname)) . '</td>';
        echo '<td class="text-center align-middle"><span class="badge badge-info p-2 d-block" style="font-size:0.85rem;">' . format_string($kelas) . '</span></td>';
        
        // TEKS BIASA/NORMAL: Kolom Jam Ke, Mapel, dan Materi diatur seragam tanpa efek pudar/tebal berlebih
        echo '<td class="text-center align-middle" style="font-size:0.95rem; color: #212529;">' . s($e->jamke) . '</td>';
        echo '<td class="align-middle" style="font-size:0.9rem; color: #212529;">' . format_string($e->matapelajaran) . '</td>';
        echo '<td class="align-middle text-justify" style="font-size: 0.9rem; color: #212529;" title="' . format_string($e->materi) . '">' . format_text(shorten_text($e->materi, 45)) . '</td>';
        
        echo '<td class="align-middle" style="font-size:0.85rem;">' . $absen_cell . '</td>';
        echo '<td class="text-center align-middle small" style="color: #212529;"><i class="fa fa-clock-o text-muted mr-1"></i> ' . tanggal_indo($e->timecreated) . '</td>';
        echo '<td class="align-middle text-muted small" title="' . format_string($e->keterangan) . '">' . format_string(shorten_text($e->keterangan, 30)) . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>'; // End table-responsive
} else {
    echo html_writer::div('<i class="fa fa-exclamation-circle"></i> Tidak ada entri jurnal mengajar pada tanggal tersebut.', 'alert alert-warning shadow-sm p-3 font-weight-bold');
}

// Tampilkan nama hari dengan JS (Disesuaikan output styling-nya agar serasi)
echo html_writer::script("
    document.addEventListener('DOMContentLoaded', function () {
        const inputTanggal = document.getElementById('tanggal');
        const divHari = document.getElementById('hari-terpilih');

        const hariIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        function tampilkanHari(dateStr) {
            const date = new Date(dateStr);
            if (!isNaN(date.getTime())) {
                const hari = hariIndo[date.getDay()];
                divHari.innerHTML = '<span class=\"badge badge-dark p-2\"><i class=\"fa fa-calendar-o\"></i> Hari ' + hari + '</span>';
            } else {
                divHari.innerHTML = '';
            }
        }

        inputTanggal.addEventListener('change', function () {
            tampilkanHari(this.value);
        });

        if (inputTanggal.value) {
            tampilkanHari(inputTanggal.value);
        }
    });
");

echo $OUTPUT->footer();
