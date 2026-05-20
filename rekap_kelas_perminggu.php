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

// === Hitung rentang minggu ke-1 s.d. ke-20 ===
$mingguoptions = [];
for ($i = 0; $i < 20; $i++) {
    $start = strtotime($tanggalawalminggu . " +{$i} week");
    $end   = strtotime("+6 day", $start);
    $label = 'Minggu Ke-' . ($i+1) . ' (' 
    . tanggal_indo($start, 'tanggal') 
    . ' s/d ' 
    . tanggal_indo($end, 'tanggal') . ')';
    $mingguoptions[$i+1] = $label;
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
$minggu_berjalan = ($diff >= 0 && $diff < 20) ? $diff + 1 : 1;

// === Ambil input dengan default kelas pertama & minggu berjalan ===
$kelas = optional_param('kelas', key($kelasoptions), PARAM_INT); 
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
            //$lastname = ucwords(strtolower($lastname));

            // Format Jam (Badge)
            $jam_html = html_writer::tag('span', $r->jamke, ['class' => 'badge badge-info p-1 font-weight-normal']);
            
// Waktu Input (Muted Text dihapus)
            $waktu_input = html_writer::tag('span', tanggal_indo($r->timecreated, 'jam'), ['class' => 'small']);

            $rows[] = [
                $jam_html,
                html_writer::tag('strong', format_string($r->matapelajaran)),
                $lastname,
                html_writer::tag('div', format_text($r->materi), ['class' => 'text-justify small', 'style' => 'line-height:1.4']),
                $waktu_input
            ];
        }
    }

    // Buat Kotak (Card) per Hari
    echo html_writer::start_div('card mb-4 shadow-sm border-0');
        
        // Header Card (Hari & Tanggal)
        $judul_hari = html_writer::tag('span', strtoupper($indo), ['class' => 'font-weight-bold text-dark mr-2']);
$sub_tanggal = $tanggalhari ? html_writer::tag('span', $tanggalhari, ['class' => 'small']) : html_writer::tag('span', '(Belum ada kegiatan)', ['class' => 'small font-italic']);
        
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
            // Terapkan kelas Bootstrap ke tabel Moodle
            $table->attributes['class'] = 'table table-hover table-striped mb-0 text-nowrap';
            $table->head = [
                html_writer::tag('span', 'Jam', ['class' => 'text-uppercase small']), 
                html_writer::tag('span', 'Mata Pelajaran', ['class' => 'text-uppercase small']), 
                html_writer::tag('span', 'Guru', ['class' => 'text-uppercase small']), 
                html_writer::tag('span', 'Materi', ['class' => 'text-uppercase small']), 
                html_writer::tag('span', 'Waktu Input', ['class' => 'text-uppercase small'])
            ];
            
            // Atur lebar kolom untuk Materi agar leluasa, sisanya menyesuaikan (nowrap)
            $table->colclasses = ['text-center align-middle', 'align-middle', 'align-middle', 'align-middle text-wrap w-50', 'text-center align-middle'];
            $table->data = $rows;
            
            echo html_writer::table($table);
        }
        
        echo html_writer::end_div(); // End card-body
    echo html_writer::end_div(); // End card
}

// Tambahan style untuk text justify
echo '<style>
    .text-justify { text-align: justify; }
    .table-responsive { overflow-x: auto; }
</style>';

echo $OUTPUT->footer();
