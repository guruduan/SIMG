<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__.'/lib.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

global $DB;

// =====================
// PARAMETER
// =====================
$userid = required_param('userid', PARAM_INT);

// =====================
// DATA USER & SETTING
// =====================
$user = $DB->get_record('user', ['id' => $userid], 'lastname');
if (!$user) {
    print_error('Guru tidak ditemukan.');
}

$nama = ucwords($user->lastname);
$tahunajaran = get_config('local_jurnalmengajar', 'tahun_ajaran') ?: '-';

// =====================
// TANGGAL AWAL MINGGU
// =====================
$tanggalstring = get_config('local_jurnalmengajar', 'tanggalawalminggu');

if (!$tanggalstring) {
    print_error('Tanggal awal minggu belum disetting.');
}
$tanggal_awal = new DateTime($tanggalstring);

// =====================
// DETEKSI SEMESTER
// =====================
$bulan_awal = (int)$tanggal_awal->format('n');
$semester = ($bulan_awal >= 7) ? 'Ganjil' : 'Genap';

// =====================
// HEADER MOODLE
// =====================
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_guru_semester.php', ['userid' => $userid]));
$PAGE->set_title("Rekap Jurnal Mengajar Guru Semester $semester {$user->lastname}");
$PAGE->set_heading("Rekap Jurnal Mengajar Guru");

echo $OUTPUT->header();

// =====================
// PROSES REKAP DATA
// =====================
$rekap_mingguan = [];
$sekarang = time();

$selisih_hari = floor(($sekarang - $tanggal_awal->getTimestamp()) / (60 * 60 * 24));
$minggu_berjalan = floor($selisih_hari / 7) + 1;

// batas aman
if ($minggu_berjalan < 1) $minggu_berjalan = 1;

for ($i = 1; $i <= $minggu_berjalan; $i++) {

    $awal = clone $tanggal_awal;
    $awal->modify('+' . (($i - 1) * 7) . ' days');

    $akhir = clone $awal;
    $akhir->modify('+6 days');

    $start = $awal->getTimestamp();
    $end = $akhir->getTimestamp() + 86399;

    // Ambil jurnal
    $entries = $DB->get_records_select(
        'local_jurnalmengajar',
        'userid = ? AND timecreated BETWEEN ? AND ?',
        [$userid, $start, $end]
    );

    // Hitung jam
    $jumlah = 0;
    foreach ($entries as $e) {
        $jumlah += !empty($e->jamke)
            ? count(array_filter(explode(',', $e->jamke)))
            : 0;
    }

    // Beban jam
    $beban = jurnalmengajar_get_beban_jam_guru_by_date($start);
    $beban_minggu = $beban[$userid] ?? 0;

    $persen = ($beban_minggu > 0)
        ? round(($jumlah / $beban_minggu) * 100)
        : 0;

    // Rentang tanggal
    $awal_ts = $start;
    $akhir_ts = $end;
    $awal_str = tanggal_indo($awal_ts, 'tglbulan');
    $akhir_str = tanggal_indo($akhir_ts, 'tanggal');

    $rentang = $awal_str . ' - ' . $akhir_str;

    $rekap_mingguan[] = [
        'minggu' => $i,
        'jumlah' => $jumlah,
        'beban' => $beban_minggu,
        'persen' => $persen,
        'rentang' => $rentang
    ];
}

// Hitung Ringkasan Statistik
$totaljam = array_sum(array_column($rekap_mingguan, 'jumlah'));
$jumlah_minggu = count($rekap_mingguan);
$avgpersen = $jumlah_minggu > 0 
    ? round(array_sum(array_column($rekap_mingguan, 'persen')) / $jumlah_minggu)
    : 0;


// --- ATAS: Identitas Guru & Tombol Kembali ---
echo html_writer::start_div('d-flex justify-content-between align-items-center mb-4 flex-wrap');
    echo html_writer::start_div();
        echo html_writer::tag('h3', $nama, ['class' => 'mb-1 font-weight-bold text-primary']);
        echo html_writer::tag('div', "Tahun Ajaran $tahunajaran • Semester $semester", ['class' => 'text-muted font-weight-bold']);
    echo html_writer::end_div();
    
    echo html_writer::div(
        html_writer::link(new moodle_url('/local/jurnalmengajar/rekap_perminggu.php'), '⬅ Kembali', [
            'class' => 'btn btn-outline-secondary shadow-sm mt-2 mt-md-0'
        ])
    );
echo html_writer::end_div();


// --- INFO CUT OFF (MULTI KELAS) ---
$daftar_kelas = ['VI', 'IX', 'XII'];
foreach ($daftar_kelas as $kelas_level) {
    $cutoff = jurnalmengajar_get_cutoff_by_kelas($kelas_level);
    if ($cutoff) {
        echo html_writer::div(
            "ℹ️ <strong>Kelas $kelas_level</strong> tidak ada KBM sejak " . tanggal_indo($cutoff, 'tanggal') . ". Beban jam mengajar telah disesuaikan otomatis.",
            'alert alert-info mb-4 shadow-sm'
        );
    }
}


// --- STATISTIK RINGKASAN (CARDS) ---
echo html_writer::start_div('row mb-4');
    // Card 1: Total Mengajar
    echo html_writer::start_div('col-md-6 mb-3 mb-md-0');
        echo html_writer::start_div('card border-0 shadow-sm bg-light');
            echo html_writer::start_div('card-body p-3 text-center');
                echo html_writer::tag('span', 'Total Realisasi Mengajar', ['class' => 'text-muted small text-uppercase font-weight-bold d-block mb-1']);
                echo html_writer::tag('h2', $totaljam . ' <span class="h5 text-muted">JP</span>', ['class' => 'mb-0 font-weight-bold text-dark']);
            echo html_writer::end_div();
        echo html_writer::end_div();
    echo html_writer::end_div();

    // Card 2: Rata-rata Kinerja
    echo html_writer::start_div('col-md-6');
        echo html_writer::start_div('card border-0 shadow-sm bg-light');
            echo html_writer::start_div('card-body p-3 text-center');
                echo html_writer::tag('span', 'Rata-rata Jurnal diisi', ['class' => 'text-muted small text-uppercase font-weight-bold d-block mb-1']);
                
                // Tentukan warna teks rata-rata
                $avg_color = 'text-danger';
                if ($avgpersen >= 80) $avg_color = 'text-success';
                elseif ($avgpersen >= 50) $avg_color = 'text-info';

                echo html_writer::tag('h2', $avgpersen . '%', ['class' => 'mb-0 font-weight-bold ' . $avg_color]);
            echo html_writer::end_div();
        echo html_writer::end_div();
    echo html_writer::end_div();
echo html_writer::end_div();


// =====================
// TABEL DATA SEMESTER
// =====================
echo html_writer::start_div('table-responsive shadow-sm rounded border');
echo html_writer::start_tag('table', ['class' => 'table table-hover table-striped mb-0 text-nowrap']);
echo html_writer::start_tag('thead', ['class' => 'thead-dark text-uppercase small']);
echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'No', ['class' => 'text-center align-middle', 'style' => 'width: 5%']);
    echo html_writer::tag('th', 'Minggu', ['class' => 'text-center align-middle', 'style' => 'width: 10%']);
    echo html_writer::tag('th', 'Rentang Tanggal', ['class' => 'align-middle']);
    echo html_writer::tag('th', 'Realisasi Mengajar', ['class' => 'text-center align-middle']);
    echo html_writer::tag('th', 'Beban Mengajar', ['class' => 'text-center align-middle']);
    echo html_writer::tag('th', 'Persentase', ['class' => 'text-center align-middle', 'style' => 'width: 15%']);
    echo html_writer::tag('th', 'Aksi', ['class' => 'text-center align-middle', 'style' => 'width: 10%']);
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');

$no = 1;
foreach ($rekap_mingguan as $r) {

    // Penentuan Badge Warna & Efek Baris Semu
    $tr_class = '';
    if ($r['persen'] >= 80) {
        $badge_class = 'badge-success';
    } elseif ($r['jumlah'] == 0 && $r['beban'] > 0) {
        $badge_class = 'badge-danger';
        $tr_class = 'table-danger-light';
    } elseif ($r['persen'] < 50) {
        $badge_class = 'badge-warning text-dark';
        $tr_class = 'table-warning-light';
    } else {
        $badge_class = 'badge-info';
    }

    $url = new moodle_url('/local/jurnalmengajar/rekap_perguru.php', [
        'userid' => $userid,
        'mingguke' => $r['minggu']
    ]);

    echo html_writer::start_tag('tr', ['class' => $tr_class]);
        echo html_writer::tag('td', $no++, ['class' => 'text-center align-middle font-weight-bold text-muted']);
        echo html_writer::tag('td', 'Ke-' . $r['minggu'], ['class' => 'text-center align-middle font-weight-bold']);
        echo html_writer::tag('td', $r['rentang'], ['class' => 'align-middle']);
        echo html_writer::tag('td', $r['jumlah'] . ' JP', ['class' => 'text-center align-middle']);
        echo html_writer::tag('td', $r['beban'] . ' JP', ['class' => 'text-center align-middle text-muted']);
        
        // Badge Persentase Kerja
        $badge = html_writer::tag('span', $r['persen'] . '%', ['class' => 'badge ' . $badge_class . ' p-2 w-100', 'style' => 'font-size: 85%']);
        echo html_writer::tag('td', $badge, ['class' => 'text-center align-middle']);
        
        // Tombol Detail
        $btn_detail = html_writer::link($url, '🔍 Detail', ['class' => 'btn btn-xs btn-outline-primary btn-sm block shadow-sm']);
        echo html_writer::tag('td', $btn_detail, ['class' => 'text-center align-middle']);
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_div();


// --- BAWAH: Tombol Kembali Tambahan ---
echo html_writer::start_div('mt-4 mb-2');
    echo html_writer::link(
        new moodle_url('/local/jurnalmengajar/rekap_perminggu.php'),
        '⬅ Kembali ke Rekap Mingguan',
        ['class' => 'btn btn-secondary shadow-sm']
    );
echo html_writer::end_div();


// CSS Kustom Segar untuk highlight baris bermasalah tanpa kontras berlebih
echo '<style>
    .table-danger-light { background-color: rgba(220, 53, 69, 0.05) !important; }
    .table-warning-light { background-color: rgba(255, 193, 7, 0.05) !important; }
    .table th, .table td { vertical-align: middle !important; }
</style>';

echo $OUTPUT->footer();
