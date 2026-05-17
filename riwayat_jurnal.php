<?php
require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $USER, $PAGE, $OUTPUT;

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/riwayat_jurnal.php'));
$PAGE->set_title('Riwayat Jurnal');
$PAGE->set_heading('Riwayat Jurnal Mengajar');

require_once(__DIR__ . '/lib.php');

echo $OUTPUT->header();

// Menggunakan utilitas margin Bootstrap bawaan Moodle
echo $OUTPUT->heading('Riwayat Jurnal Saya', 2, 'mb-4');

$bulan = optional_param('bulan', date('n'), PARAM_INT);
$tahun = optional_param('tahun', date('Y'), PARAM_INT);

$bulanopsi = [
    1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April',
    5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus',
    9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'
];

// ================= BAR ATAS: FILTER & TOMBOL AKSI =================
echo html_writer::start_div('row align-items-center mb-4');

// Sisi Kiri: Form Filter Bulan & Tahun
echo html_writer::start_div('col-md-8 col-sm-12 mb-3 mb-md-0');
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'form-inline bg-light p-3 rounded shadow-sm gap-2']);

echo html_writer::start_div('form-group mb-0');
echo html_writer::tag('label', 'Bulan: ', ['class' => 'mr-2 font-weight-bold']);
echo html_writer::select($bulanopsi, 'bulan', $bulan, false, ['class' => 'form-control custom-select']);
echo html_writer::end_div();

echo html_writer::start_div('form-group mb-0 ml-md-2');
echo html_writer::tag('label', ' Tahun: ', ['class' => 'mr-2 font-weight-bold']);
$tahunopsi = [];
for ($t = date('Y'); $t >= date('Y')-5; $t--) {
    $tahunopsi[$t] = $t;
}
echo html_writer::select($tahunopsi, 'tahun', $tahun, false, ['class' => 'form-control custom-select']);
echo html_writer::end_div();

echo html_writer::tag('button', '<i class="fa fa-search"></i> Tampilkan', [
    'type' => 'submit',
    'class' => 'btn btn-primary ml-md-3'
]);

echo html_writer::end_tag('form');
echo html_writer::end_div(); // close col-md-8

// Sisi Kanan: Tombol Aksi Ekspor & Kembali
echo html_writer::start_div('col-md-4 col-sm-12 text-md-right d-flex d-md-block justify-content-between gap-2');

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/index.php'),
    '<i class="fa fa-arrow-left"></i> Kembali',
    ['class' => 'btn btn-secondary mr-md-2']
);

// Tombol ekspor disatukan secara sejajar (inline-block)
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/jurnalmengajar/export_form.php'),
    'style' => 'display:inline-block;'
]);
echo html_writer::tag('button', '🌏 Ekspor Bulanan', [
    'type' => 'submit',
    'class' => 'btn btn-success shadow-sm'
]);
echo html_writer::end_tag('form');

echo html_writer::end_div(); // close col-md-4
echo html_writer::end_div(); // close row


// ================= PROSES DATA & TABEL =================
$awalbulan = strtotime("$tahun-$bulan-01 00:00:00");
$akhirbulan = strtotime("+1 month", $awalbulan);

$sql = "SELECT *
          FROM {local_jurnalmengajar}
         WHERE userid = :userid
           AND timecreated >= :awal
           AND timecreated < :akhir
      ORDER BY id DESC";

$params = [
    'userid' => $USER->id,
    'awal' => $awalbulan,
    'akhir' => $akhirbulan
];

$entries = $DB->get_records_sql($sql, $params);

// Sub-heading nama bulan pencarian
echo html_writer::tag('h4', '🗓️ Riwayat Bulan: ' . $bulanopsi[$bulan] . ' ' . $tahun, ['class' => 'text-muted mb-3']);

if ($entries) {
    // Membuka kontainer responsif tabel agar aman diakses via smartphone
    echo html_writer::start_div('table-responsive shadow-sm rounded');
    echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover generaltable mb-0']);
    echo html_writer::start_tag('thead', ['class' => 'thead-dark']);
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', '#', ['scope' => 'col', 'style' => 'width: 5%;']);
    echo html_writer::tag('th', 'Kelas', ['scope' => 'col']);
    echo html_writer::tag('th', 'Jam Ke', ['scope' => 'col']);
    echo html_writer::tag('th', 'Mapel', ['scope' => 'col']);
    echo html_writer::tag('th', 'Materi', ['scope' => 'col']);
    echo html_writer::tag('th', 'Absen', ['scope' => 'col']);
    echo html_writer::tag('th', 'Waktu', ['scope' => 'col']);
    echo html_writer::tag('th', 'Aksi', ['scope' => 'col', 'class' => 'text-center']);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    $no = 1;

    foreach ($entries as $e) {
        $absendata = json_decode($e->absen, true);
        $absentext = '';

        if (is_array($absendata)) {
            foreach ($absendata as $nama => $alasan) {
                $absentext .= "$nama ($alasan), ";
            }
            $absentext = rtrim($absentext, ', ');
        }

        $namakelas = get_nama_kelas($e->kelas);
        $editurl = new moodle_url('/local/jurnalmengajar/edit.php', ['id' => $e->id]);
        
        // Custom button kecil (sm) dengan ikon pensil untuk tombol edit
        $editicon = $OUTPUT->pix_icon('t/edit', 'Edit');
        $editlink = html_writer::link($editurl, $editicon . ' Edit', ['class' => 'btn btn-outline-primary btn-sm']);

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $no++);
        echo html_writer::tag('td', html_writer::tag('strong', $namakelas));
        echo html_writer::tag('td', $e->jamke);
        echo html_writer::tag('td', $e->matapelajaran);
        echo html_writer::tag('td', shorten_text($e->materi, 40), ['title' => $e->materi]);
        echo html_writer::tag('td', $absentext ? shorten_text($absentext, 30) : '-', ['title' => $absentext]);
        echo html_writer::tag('td', html_writer::tag('small', tanggal_indo($e->timecreated)));
        echo html_writer::tag('td', $editlink, ['class' => 'text-center']);
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div(); // close table-responsive

} else {
    // Diganti dengan Box Alert Bootstrap agar terlihat profesional jika data kosong
    echo html_writer::div('Kedua parameter filter tidak menemukan data jurnal mengajar Anda pada periode bulan ini.', 'alert alert-info shadow-sm py-3');
}

echo $OUTPUT->footer();
