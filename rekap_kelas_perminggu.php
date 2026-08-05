<?php
require_once(__DIR__ . '/../../config.php');
require_login();
require_once($CFG->dirroot.'/local/jurnalmengajar/lib.php');

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_kelas_perminggu.php'));
$PAGE->set_title('Rekap Pekanan KBM Kelas');
$PAGE->set_heading('Rekap Pekanan KBM Kelas');

global $DB, $OUTPUT;

// === Ambil setting tanggal awal minggu ===
$tanggalawalminggu = get_config('local_jurnalmengajar', 'tanggalawalminggu'); // format: YYYY-MM-DD
if (empty($tanggalawalminggu)) {
    throw new moodle_exception('Tanggal awal minggu belum diset di pengaturan plugin.');
}

// === Hitung rentang minggu dinamis ===
$mingguoptions = [];

$hariini = strtotime(date('Y-m-d'));
$selisihhari = floor(($hariini - strtotime($tanggalawalminggu)) / (60 * 60 * 24));

$jumlahminggu = floor($selisihhari / 7) + 1;

if ($jumlahminggu < 1) {
    $jumlahminggu = 1;
}

for ($i = 0; $i < $jumlahminggu; $i++) {

    $start = strtotime($tanggalawalminggu . " +{$i} week");
    $end   = strtotime("+6 day", $start);

    $label = 'Minggu Ke-' . ($i + 1) . ' (' 
        . tanggal_indo($start, 'tanggal') 
        . ' s/d ' 
        . tanggal_indo($end, 'tanggal') . ')';

    $mingguoptions[$i + 1] = $label;
}

// === Ambil cohort (kelas) ===
$kelasrecords = $DB->get_records('cohort', null, 'name ASC', 'id, name');
$kelasoptions = [];
foreach ($kelasrecords as $k) {
    $kelasoptions[$k->id] = $k->name;
}

// === Hitung minggu berjalan (default) ===
$hariini = strtotime(date('Y-m-d'));
$diff = floor(($hariini - strtotime($tanggalawalminggu)) / (7 * 24 * 60 * 60));
$minggu_berjalan = ($diff >= 0) ? $diff + 1 : 1;

// === Cek apakah user login adalah Wali Kelas ===
global $USER;
$default_kelas = key($kelasoptions); // Default awal jika bukan wali kelas (kelas pertama)

// Ambil data mapping wali kelas dari config
$json_mapping = get_config('local_jurnalmengajar', 'wali_kelas_mapping');
$mapping = json_decode($json_mapping, true);

if (is_array($mapping)) {
    foreach ($mapping as $cohortid => $userid) {
        // Jika ID user login cocok dengan data mapping, dan kelasnya terdaftar di sistem
        if ($userid == $USER->id && isset($kelasoptions[$cohortid])) {
            $default_kelas = $cohortid; // Timpa default kelas dengan kelas binaannya
            break;
        }
    }
}

// === Ambil input dengan default kelas (wali kelas / kelas pertama) & minggu berjalan ===
$kelas = optional_param('kelas', $default_kelas, PARAM_INT); 
$minggu = optional_param('minggu', $minggu_berjalan, PARAM_INT);

// === Hitung tanggal filter ===
$startdate = strtotime($tanggalawalminggu . " +" . ($minggu-1) . " week");
$enddate   = strtotime("+6 day", $startdate);

// range waktu dalam timestamp
$starttime = $startdate;
$endtime   = strtotime("+1 day", $enddate) - 1;

// === Ambil data jurnal ===
$jurnalrecords = [];
if ($kelas) {
    $jurnalrecords = $DB->get_records_select('local_jurnalmengajar',
        "kelas = :kelas AND timecreated >= :start AND timecreated <= :end",
        ['kelas' => $kelas, 'start' => $starttime, 'end' => $endtime],
        "timecreated ASC, jamke ASC"
    );
}

// === TAMPILAN HALAMAN (HEADER & FILTER) ===
echo $OUTPUT->header();

// Header Halaman & Tombol Kembali
echo html_writer::start_div('d-flex justify-content-between align-items-center mb-4 flex-wrap');
    echo html_writer::tag('h3', 'Rekap KBM di Kelas Per Minggu', ['class' => 'mb-0 font-weight-bold text-primary']);
    echo html_writer::link('#', '⬅ Kembali', [
        'class' => 'btn btn-outline-secondary shadow-sm mt-2 mt-md-0',
        'onclick' => 'history.back(); return false;'
    ]);
echo html_writer::end_div();

// Card Filter Form
echo html_writer::start_div('card mb-4 shadow-sm border-0 bg-light');
echo html_writer::start_div('card-body p-3');
    echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'form-inline m-0 align-items-center']);
        
echo html_writer::start_div('form-group mr-4 mb-2 mb-md-0');
            echo html_writer::tag('label', 'Kelas:', ['for' => 'kelas', 'class' => 'mr-2 font-weight-bold small text-uppercase']);
            echo html_writer::select($kelasoptions, 'kelas', $kelas, false, ['class' => 'custom-select custom-select-sm']);
        echo html_writer::end_div();
        
        echo html_writer::start_div('form-group mr-3 mb-2 mb-md-0');
            echo html_writer::tag('label', 'Periode:', ['for' => 'minggu', 'class' => 'mr-2 font-weight-bold small text-uppercase']);
            echo html_writer::select($mingguoptions, 'minggu', $minggu, false, ['class' => 'custom-select custom-select-sm']);
        echo html_writer::end_div();
        
        echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Tampilkan Data', 'class' => 'btn btn-primary btn-sm px-4 shadow-sm']);
    
    echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();


// === PROSES & TAMPILKAN DATA PER HARI (Senin-Jumat) ===
$hari = [
    'Monday'    => 'Senin',
    'Tuesday'   => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday'  => 'Kamis',
    'Friday'    => 'Jumat'
];

$userids = array_unique(array_column($jurnalrecords, 'userid'));
$users = [];

if (!empty($userids)) {
    list($in_sql, $paramsin) = $DB->get_in_or_equal($userids);
    $users = $DB->get_records_sql(
        "SELECT id, lastname FROM {user} WHERE id $in_sql",
        $paramsin
    );
}

// Loop untuk setiap hari
foreach ($hari as $eng => $indo) {
    $rows = [];
    $tanggalhari = '';
    
    foreach ($jurnalrecords as $r) {
        $haridata = date('l', $r->timecreated);
        if ($haridata == $eng) {
            $tanggalhari = tanggal_indo($r->timecreated, 'tanggal');
            
            // Ambil lastname pengajar
            $lastname = $users[$r->userid]->lastname ?? '-';

            // Format Jam (Badge)
            $jam_html = html_writer::tag('span', $r->jamke, ['class' => 'badge badge-info p-1 font-weight-normal']);
            
            // === LOGIKA BARU: Ambil data absensi (Menggantikan Materi) ===
            $abs = json_decode($r->absen ?? '', true);
            if (is_array($abs) && !empty($abs)) {
                $abtxt = implode(', ', array_map(fn($n, $a) => "$n ($a)", array_keys($abs), $abs));
                // Tampilkan teks absen dengan format warna merah jika ada siswa absen
                $absen_cell = html_writer::tag('div', s($abtxt), ['class' => 'text-danger font-weight-bold small', 'style' => 'line-height:1.4']);
            } else {
                // Tampilkan indikator hijau jika hadir semua
                $absen_cell = html_writer::tag('span', '<i class="fa fa-check-circle text-success"></i> Hadir Semua', ['class' => 'text-success small']);
            }
            // =============================================================

            // Waktu Input
            $waktu_input = html_writer::tag('span', tanggal_indo($r->timecreated, 'jam'), ['class' => 'small']);

            $rows[] = [
                $jam_html,
                html_writer::tag('strong', format_string($r->matapelajaran)),
                $lastname,
                $absen_cell, // <--- Variabel Absen dipanggil di sini
                $waktu_input
            ];
        }
    }

// Ambil nama kelas yang dipilih
    $namakelas_terpilih = $kelasoptions[$kelas] ?? '';

    // Buat Kotak (Card) per Hari
    echo html_writer::start_div('card mb-4 shadow-sm border-0');
        
        // Format Teks Kelas & Hari
        $teks_kelas = $namakelas_terpilih ? 'Kelas ' . $namakelas_terpilih . ' - ' : '';
        $teks_hari  = 'Hari ' . ucfirst(strtolower($indo)); 
        
        // Gabungkan Kelas dan Hari
        $judul_hari = html_writer::tag('span', $teks_kelas . $teks_hari, ['class' => 'font-weight-bold text-dark']);
        
        // Format Tanggal (Contoh: ", 04 Agustus 2026")
        if ($tanggalhari) {
            $sub_tanggal = html_writer::tag('span', ', ' . $tanggalhari, ['class' => 'font-weight-bold text-dark']);
        } else {
            $sub_tanggal = html_writer::tag('span', ' (Belum ada kegiatan)', ['class' => 'small font-italic text-muted']);
        }
        
        echo html_writer::start_div('card-header bg-white border-bottom-0 pt-3 pb-2');
            echo html_writer::tag('h5', $judul_hari . $sub_tanggal, ['class' => 'mb-0']);
        echo html_writer::end_div();

        // Isi Card (Tabel / Pesan Kosong)
        echo html_writer::start_div('card-body p-0 table-responsive');
        
        if (empty($rows)) {
            echo html_writer::div(
                'ℹ️ Tidak ada data kegiatan belajar mengajar (KBM) yang tercatat pada hari ini.', 
                'text-center text-muted py-4 bg-light font-italic border-top'
            );
        } else {
            $table = new html_table();
            $table->attributes['class'] = 'table table-hover table-striped mb-0 text-nowrap';
            $table->head = [
                html_writer::tag('span', 'Jam', ['class' => 'text-uppercase small']), 
                html_writer::tag('span', 'Mata Pelajaran', ['class' => 'text-uppercase small']), 
                html_writer::tag('span', 'Guru', ['class' => 'text-uppercase small']), 
                html_writer::tag('span', 'Keterangan Absen', ['class' => 'text-uppercase small']), // <--- Header diubah jadi Absen
                html_writer::tag('span', 'Waktu Input', ['class' => 'text-uppercase small'])
            ];
            
            // Atur lebar kolom (w-50 tetap dipertahankan untuk kolom absensi agar leluasa jika nama absen panjang)
            $table->colclasses = ['text-center align-middle', 'align-middle', 'align-middle', 'align-middle text-wrap w-50', 'text-center align-middle'];
            $table->data = $rows;
            
            echo html_writer::table($table);
        }
        
        echo html_writer::end_div(); // End card-body
    echo html_writer::end_div(); // End card
}

echo '<style>
    .table-responsive { overflow-x: auto; }
</style>';

echo $OUTPUT->footer();
