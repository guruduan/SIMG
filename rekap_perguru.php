<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

function format_tanggal_indonesia($timestamp) {
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    $d = getdate($timestamp);
    $hari_indo = $hari[$d['wday']];
    $tanggal = $d['mday'];
    $bulan_indo = $bulan[$d['mon']];
    $tahun = $d['year'];

    return "$hari_indo, $tanggal $bulan_indo $tahun";
}

$userid = required_param('userid', PARAM_INT);
$mingguke = required_param('mingguke', PARAM_INT);

if ($mingguke < 1) {
    print_error('Parameter minggu ke tidak valid.');
}

// Tanggal awal minggu pertama
$tanggalstring = get_config('local_jurnalmengajar', 'tanggalawalminggu') ?: '2025-06-23';
$tanggal_awal = new DateTime($tanggalstring);
$tanggal_awal->modify('+' . (($mingguke - 1) * 7) . ' days');
$tanggal_akhir = clone $tanggal_awal;
$tanggal_akhir->modify('+6 days');

$timestart = $tanggal_awal->getTimestamp();
$timeend = $tanggal_akhir->getTimestamp() + 86399;

// Ambil entri jurnal guru untuk minggu ini
global $DB;
$entries = $DB->get_records_select('local_jurnalmengajar',
    'userid = ? AND timecreated BETWEEN ? AND ?',
    [$userid, $timestart, $timeend],
    'timecreated ASC'
);

// Ambil data user
$user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname');
if (!$user) {
    print_error('Guru tidak ditemukan.');
}

// Header Moodle Config
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_perguru.php', ['userid' => $userid, 'mingguke' => $mingguke]));
$PAGE->set_title("Riwayat Mengajar {$user->lastname}");
$PAGE->set_heading("Rekap Mengajar {$user->lastname} - Minggu ke-$mingguke");

echo $OUTPUT->header();

// --- ATAS: Header Dashboard Riwayat Jurnal ---
echo html_writer::start_div('d-flex justify-content-between align-items-center mb-4 flex-wrap');
    echo html_writer::start_div();
        echo html_writer::tag('h3', "Jurnal Mengajar: " . ucwords($user->lastname), ['class' => 'mb-1 font-weight-bold text-primary']);
        echo html_writer::tag('div', 'Minggu Ke-' . $mingguke . ' • ' . format_tanggal_indonesia($tanggal_awal->getTimestamp()) . ' s/d ' . format_tanggal_indonesia($tanggal_akhir->getTimestamp()), ['class' => 'text-muted font-weight-bold']);
    echo html_writer::end_div();
    
    echo html_writer::div(
        html_writer::link(new moodle_url('/local/jurnalmengajar/rekap_perminggu.php', ['mingguke' => $mingguke]), '⬅ Kembali', [
            'class' => 'btn btn-outline-secondary shadow-sm mt-2 mt-md-0'
        ])
    );
echo html_writer::end_div();


// --- TABEL DATA JURNAL ---
echo html_writer::start_div('table-responsive shadow-sm rounded border bg-white');
echo html_writer::start_tag('table', ['class' => 'table table-hover table-striped mb-0 text-wrap']);
echo html_writer::start_tag('thead', ['class' => 'thead-dark text-uppercase small']);
echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'No', ['class' => 'text-center align-middle', 'style' => 'width: 4%']);
    echo html_writer::tag('th', 'Hari, Tanggal', ['class' => 'align-middle', 'style' => 'width: 15%']);
    echo html_writer::tag('th', 'Kelas', ['class' => 'text-center align-middle', 'style' => 'width: 10%']);
    echo html_writer::tag('th', 'Jam', ['class' => 'text-center align-middle', 'style' => 'width: 8%']);
    echo html_writer::tag('th', 'Mata Pelajaran', ['class' => 'align-middle', 'style' => 'width: 15%']);
    echo html_writer::tag('th', 'Materi Pembelajaran', ['class' => 'align-middle', 'style' => 'width: 23%']);
    echo html_writer::tag('th', 'Absensi Siswa', ['class' => 'align-middle', 'style' => 'width: 15%']);
    echo html_writer::tag('th', 'Keterangan', ['class' => 'align-middle', 'style' => 'width: 10%']);
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');

$no = 1;
if (empty($entries)) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag(
        'td',
        'ℹ️ Tidak ada riwayat data jurnal mengajar yang diinput pada minggu ini.',
        ['colspan' => 8, 'class' => 'text-center text-muted py-5 font-italic bg-light']
    );
    echo html_writer::end_tag('tr');
} else {
    foreach ($entries as $entry) {
        $tanggal = format_tanggal_indonesia($entry->timecreated);
        $jamke = $entry->jamke ?: '-';
        
        // Ambil nama Cohort / Kelas
        if (is_numeric($entry->kelas)) {
            $cohort = $DB->get_record('cohort', ['id' => (int)$entry->kelas], 'name');
            $kelas = $cohort ? $cohort->name : '(tidak ditemukan)';
        } else {
            $kelas = $entry->kelas ?: '-';
        }

        $mapel = $entry->matapelajaran ?: '-';
        $materi = $entry->materi ?: '-';
        
        // Pemrosesan visual Absensi Siswa agar tidak monoton teks polos
        $absen_html = '<span class="text-muted small">Nihil (Hadir Semua)</span>';
        $absendata = json_decode($entry->absen, true);
        
        if (is_array($absendata) && !empty($absendata)) {
            $absen_html = '<div class="d-flex flex-wrap gap-1">';
            foreach ($absendata as $nama => $alasan) {
                $reason = strtolower(trim($alasan));
                
                // Default warna untuk Alpa / Sakit / Lainnya (Merah)
                $badge_type = 'badge-danger'; 
                
                if ($reason == 'i' || $reason == 'izin' || $reason == 'ijin') {
                    $badge_type = 'badge-success'; // Hijau Tua
                } elseif ($reason == 'd' || $reason == 'dispensasi' || $reason == 'dispen') {
                    $badge_type = 'badge-info'; // Biru Muda
                } elseif ($reason == 's' || $reason == 'sakit') {
                    $badge_type = 'badge-warning text-dark'; // Kuning
                }
                
                $absen_html .= html_writer::tag('span', ucwords($nama) . ' (' . strtoupper($alasan) . ')', [
                    'class' => 'badge ' . $badge_type . ' m-1 p-1 font-weight-normal'
                ]);
            }
            $absen_html .= '</div>';
        } elseif (!empty($entry->absen) && $entry->absen !== '-') {
            $absen_html = html_writer::tag('span', $entry->absen, ['class' => 'badge badge-secondary p-1 font-weight-normal']);
        }

        $keterangan = $entry->keterangan ?: '-';

        echo html_writer::start_tag('tr');
            echo html_writer::tag('td', $no++, ['class' => 'text-center align-middle text-muted font-weight-bold']);
            echo html_writer::tag('td', $tanggal, ['class' => 'align-middle font-weight-bold text-dark']);
            echo html_writer::tag('td', $kelas, ['class' => 'text-center align-middle font-weight-bold text-info']);
            
            // Jam ke dibungkus badge ringan agar estetik
            $jam_badge = html_writer::tag('span', $jamke, ['class' => 'badge badge-light border text-dark font-weight-bold px-2 py-1']);
            echo html_writer::tag('td', $jam_badge, ['class' => 'text-center align-middle']);
            
            echo html_writer::tag('td', $mapel, ['class' => 'align-middle font-weight-bold']);
            echo html_writer::tag('td', $materi, ['class' => 'align-middle text-justify small', 'style' => 'line-height:1.4']);
            echo html_writer::tag('td', $absen_html, ['class' => 'align-middle']);
            echo html_writer::tag('td', $keterangan, ['class' => 'align-middle text-muted small']);
        echo html_writer::end_tag('tr');
    }
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_div();


// --- BAWAH: Tombol Kembali ---
echo html_writer::start_div('mt-4 mb-2');
    echo html_writer::link(
        new moodle_url('/local/jurnalmengajar/rekap_perminggu.php', ['mingguke' => $mingguke]),
        '⬅ Kembali ke Rekap Mingguan',
        ['class' => 'btn btn-secondary shadow-sm']
    );
echo html_writer::end_div();


// CSS Kustom Tambahan untuk memaksimalkan kerapihan baris jurnal
echo '<style>
    .table th, .table td { vertical-align: middle !important; }
    .badge { display: inline-block; white-space: nowrap; }
    .text-justify { text-align: justify; }
</style>';

echo $OUTPUT->footer();
