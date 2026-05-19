<?php
require('../../config.php');
require_once(__DIR__.'/jam_pelajaran_lib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);

$PAGE->set_url(new moodle_url('/local/jurnalmengajar/jam_pelajaran_view.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Jam Pelajaran');
$PAGE->set_heading('Alokasi Waktu Jam Pelajaran');

echo $OUTPUT->header();

// Judul Halaman Tematik dengan Ikon Penanda Waktu
echo html_writer::start_div('d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom');
echo html_writer::tag('h3', '<i class="fa fa-clock-o text-primary"></i> Alokasi Waktu Jam Pelajaran', ['class' => 'm-0 font-weight-bold']);
echo html_writer::link(new moodle_url('/local/jurnalmengajar/jadwal_view.php'), '<i class="fa fa-calendar"></i> Lihat Jadwal', ['class' => 'btn btn-outline-secondary btn-sm']);
echo html_writer::end_div();

// Ambil data alokasi jam
$jam = jurnalmengajar_generate_jam();

// Inisialisasi Moodle HTML Table dengan kelas Bootstrap modern
$table = new html_table();
$table->head = ['Jam Ke', 'Waktu Mulai', 'Waktu Selesai'];
$table->attributes['class'] = 'table table-bordered table-hover bg-white shadow-sm align-middle text-center';
$table->data = [];

$now = date('H:i');

foreach ($jam as $j => $w) {
    $mulai   = $w['mulai'];
    $selesai = $w['selesai'];
    
    // Teks jam pelajaran default berbentuk badge kecil agar terlihat rapi
    $label_jam = html_writer::tag('span', 'Jam ' . $j, ['class' => 'badge badge-dark p-2', 'style' => 'font-size: 0.9rem; min-width: 70px;']);

    // Membuat objek baris (row) baru untuk tabel agar bisa diberi atribut kustom
    $row = new html_table_row();
    
    // LOGIKA HIGHLIGHT: Jika waktu sekarang berada di dalam jam pelajaran aktif
    if ($now >= $mulai && $now <= $selesai) {
        // Berikan badge penanda khusus aktif pada teks Jam
        $label_jam = html_writer::tag('span', '<i class="fa fa-play-circle mr-1"></i> Jam ' . $j . ' (Aktif)', ['class' => 'badge badge-success p-2', 'style' => 'font-size: 0.9rem;']);
        // Warnai latar belakang seluruh baris menjadi hijau/biru tipis penanda aktif
        $row->attributes['class'] = 'table-success font-weight-bold';
        $row->attributes['title'] = 'Sesi Jam Pelajaran Sedang Berlangsung Saat Ini';
    }

    // Set data kolom pada baris ini (Warna teks biasa/normal kontras #212529)
    $row->cells = [
        $label_jam,
        html_writer::tag('span', format_string($mulai), ['style' => 'color: #212529; font-size: 1rem;']),
        html_writer::tag('span', format_string($selesai), ['style' => 'color: #212529; font-size: 1rem;'])
    ];
    
    $table->data[] = $row;

    // BARIS ISTIRAHAT
    if (!empty($w['istirahat_setelah'])) {
        $cell = new html_table_cell(
            '<i class="fa fa-coffee text-warning mr-2"></i> ISTIRAHAT ' . (int)$w['istirahat_setelah'] . ' MENIT'
        );
        $cell->colspan = 3;
        $cell->attributes['class'] = 'text-center align-middle font-weight-bold table-warning';
        $cell->attributes['style'] = 'color: #856404; font-size: 0.95rem; letter-spacing: 1px;';

        $table->data[] = new html_table_row([$cell]);
    }
}

// Render tabel ke halaman
echo '<div class="table-responsive">';
echo html_writer::table($table);
echo '</div>';

// Tombol kembali dengan gaya yang selaras
echo html_writer::start_div('mt-4 d-flex justify-content-between');
echo html_writer::link(
    new moodle_url('/my/'),
    '<i class="fa fa-arrow-left"></i> Kembali ke Dashboard',
    ['class' => 'btn btn-secondary btn-sm']
);
echo html_writer::end_div();

echo $OUTPUT->footer();
