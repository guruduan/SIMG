<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__.'/lib.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_perminggu.php'));

// Ambil tanggal awal minggu
$tanggalstring = get_config('local_jurnalmengajar', 'tanggalawalminggu') ?: '2025-07-01';
$tanggal_awal = new DateTime($tanggalstring);
$timestart = $tanggal_awal->getTimestamp();

// DETEKSI SEMESTER
$bulan_awal = (int)$tanggal_awal->format('n');
$semester = ($bulan_awal >= 7) ? 'Ganjil' : 'Genap';

// Hitung minggu berjalan (default)
$param_mingguke = optional_param('mingguke', 0, PARAM_INT);
if ($param_mingguke > 0) {
    $mingguke = $param_mingguke;
} else {
    $selisih_hari = floor((time() - $timestart) / (60 * 60 * 24));
    $mingguke = floor($selisih_hari / 7) + 1;
    if ($mingguke < 1) $mingguke = 1;
}
$namasekolah = get_config('local_jurnalmengajar', 'nama_sekolah');
$tahunajaran = get_config('local_jurnalmengajar', 'tahun_ajaran');

$judul = "Rekap Mingguan Jurnal Mengajar Guru Minggu ke-$mingguke";

$PAGE->set_title($judul);
$PAGE->set_heading($judul);

// Filter tambahan
$filter_userid = optional_param('userid', 0, PARAM_INT);

// Tentukan rentang waktu minggu ke-$mingguke
$awal_minggu = clone $tanggal_awal;
$awal_minggu->modify('+' . (($mingguke - 1) * 7) . ' days');
$tanggal_awal_minggu_ini = $awal_minggu->getTimestamp();

$akhir_minggu = clone $awal_minggu;
$akhir_minggu->modify('+6 days');
$tanggal_akhir_minggu_ini = $akhir_minggu->getTimestamp() + 86399;

// Ambil entri jurnal hanya minggu ke-N
global $DB;
$entries = $DB->get_records_select(
    'local_jurnalmengajar',
    'timecreated BETWEEN ? AND ?',
    [$tanggal_awal_minggu_ini, $tanggal_akhir_minggu_ini]
);

// Ambil beban guru
$beban = jurnalmengajar_get_beban_jam_guru_by_date($tanggal_awal_minggu_ini);

// Siapkan user
$all_userids = array_keys($beban);
$all_users = [];
if (!empty($all_userids)) {
    list($in_sql, $params) = $DB->get_in_or_equal($all_userids);
    $all_users = $DB->get_records_select_menu('user', "id $in_sql", $params, 'lastname', 'id, lastname');
}

// Tampilkan header Moodle
echo $OUTPUT->header();

// --- ATAS: Header Informasi Sekolah & Tombol Kembali ---
echo html_writer::start_div('d-flex justify-content-between align-items-center mb-4 flex-wrap');
    echo html_writer::start_div();
        if (!empty($namasekolah)) {
            echo html_writer::tag('h3', strtoupper($namasekolah), ['class' => 'mb-1 font-weight-bold text-primary']);
        }
        if (!empty($tahunajaran)) {
            echo html_writer::tag('div', 'Tahun Ajaran ' . $tahunajaran . ' • Semester ' . $semester, ['class' => 'text-muted font-weight-bold']);
        }
    echo html_writer::end_div();
    
    echo html_writer::div(
        html_writer::link('#', '⬅ Kembali', [
            'class' => 'btn btn-outline-secondary shadow-sm mt-2 mt-md-0',
            'onclick' => 'history.back(); return false;'
        ])
    );
echo html_writer::end_div();

// --- WARNING CUTOFF KBM ---
$daftar_kelas = ['VI', 'IX', 'XII'];
$ada_yang_sudah_set = false;
foreach ($daftar_kelas as $kelas_level) {
    if (jurnalmengajar_get_cutoff_by_kelas($kelas_level, $tanggal_awal_minggu_ini)) {
        $ada_yang_sudah_set = true;
        break;
    }
}
if (!$ada_yang_sudah_set) {
    echo html_writer::div(
        '⚠️ <strong>Perhatian:</strong> Tanggal berhenti KBM di kelas VI, IX, atau XII belum diatur di pengaturan awal.',
        'alert alert-warning mb-4 shadow-sm'
    );
}

// --- CARD FILTER & PERIODE ---
echo html_writer::start_div('card mb-4 shadow-sm border-0 bg-light');
echo html_writer::start_div('card-body p-3');
echo html_writer::start_div('row align-items-center');
    
    // Sisi Kiri: Info Periode
    echo html_writer::start_div('col-md-5 mb-3 mb-md-0');
        echo html_writer::tag('span', 'Periode Minggu Ini:', ['class' => 'text-muted small d-block text-uppercase font-weight-bold']);
        echo html_writer::tag('span', tanggal_indo($tanggal_awal_minggu_ini, 'tanggal') . ' s/d ' . tanggal_indo($tanggal_akhir_minggu_ini, 'tanggal'), ['class' => 'font-weight-bold text-dark h5 mb-0']);
    echo html_writer::end_div();

    // Sisi Kanan: Form Filter inline
    echo html_writer::start_div('col-md-7');
        echo '<form method="get" class="form-inline justify-content-md-end m-0">';
        
        echo html_writer::start_div('form-group mr-3 mb-0');
            echo '<label for="mingguke" class="mr-2 font-weight-bold small">Minggu:</label>';
            echo '<select name="mingguke" class="custom-select custom-select-sm" onchange="this.form.submit()">';
            $maxminggu = max($mingguke, 20);

            for ($i = 1; $i <= $maxminggu; $i++) {
                $selected = ($i == $mingguke) ? 'selected' : '';
                echo "<option value=\"$i\" $selected>Minggu ke-$i</option>";
            }
            echo '</select>';
        echo html_writer::end_div();

        echo html_writer::start_div('form-group mr-2 mb-0');
            echo '<label for="userid" class="mr-2 font-weight-bold small">Guru:</label>';
            echo '<select name="userid" class="custom-select custom-select-sm" onchange="this.form.submit()">';
            echo '<option value="0">Semua Guru</option>';
            foreach ($all_users as $id => $ln) {
                $formatted_ln = ucwords(strtolower($ln));
                $selected = ($filter_userid == $id) ? 'selected' : '';
                echo "<option value=\"$id\" $selected>$formatted_ln</option>";
            }
            echo '</select>';
        echo html_writer::end_div();

        echo '<input type="submit" value="Tampilkan" class="btn btn-primary btn-sm px-3 shadow-sm">';
        echo '</form>';
    echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();


// --- PROSES REKAP DATA ---
$rekap = [];
foreach ($beban as $userid => $beban_minggu) {
    if ($filter_userid && $userid != $filter_userid) continue;
    $rekap[$userid] = 0;
}

foreach ($entries as $e) {
    $userid = $e->userid;
    if (!isset($rekap[$userid])) continue;

    $jam = !empty($e->jamke) ? count(array_filter(explode(',', $e->jamke))) : 0;
    $rekap[$userid] += $jam;
}

// Urutkan berdasarkan nama guru
uksort($rekap, function($a, $b) use ($all_users) {
    return strcmp(strtolower($all_users[$a] ?? ''), strtolower($all_users[$b] ?? ''));
});


// --- TABEL DATA UTAMA ---
echo html_writer::start_div('table-responsive shadow-sm rounded border');
echo html_writer::start_tag('table', ['class' => 'table table-hover table-striped mb-0 text-nowrap']);
echo html_writer::start_tag('thead', ['class' => 'thead-dark text-uppercase small']);
echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'No', ['class' => 'text-center align-middle', 'style' => 'width: 5%']);
    echo html_writer::tag('th', 'Nama Guru', ['class' => 'align-middle']);
    echo html_writer::tag('th', 'Realisasi / Jam Mengajar', ['class' => 'text-center align-middle']);
    echo html_writer::tag('th', 'Beban Mengajar', ['class' => 'text-center align-middle']);
    echo html_writer::tag('th', 'Persentase', ['class' => 'text-center align-middle', 'style' => 'width: 15%']);
    echo html_writer::tag('th', 'Aksi / Detail', ['class' => 'text-center align-middle', 'style' => 'width: 10%']);
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');

if (empty($rekap)) {
    // Tentukan pesan kesalahan kontekstual jika data kosong
    $sekarang = time();
    if ($tanggal_awal_minggu_ini > $sekarang) {
        $pesan = 'Minggu ini belum dimulai.';
    } elseif ($tanggal_akhir_minggu_ini > $sekarang) {
        $pesan = 'Minggu ini sedang berjalan, guru belum mengisi data jurnal.';
    } else {
        $pesan = 'Tidak ditemukan data entri mengajar untuk minggu ini.';
    }

    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', 'ℹ️ ' . $pesan, [
        'colspan' => 6, 
        'class' => 'text-center text-muted py-4 font-italic bg-light'
    ]);
    echo html_writer::end_tag('tr');
} else {
    $no = 1;
    foreach ($rekap as $userid => $jumlahjam) {
        $lastname = $all_users[$userid] ?? '(Tidak Ditemukan)';
        $nama = ucwords($lastname);
        $beban_minggu = $beban[$userid] ?? 0;

$pengurang_libur =
    jurnalmengajar_get_pengurang_target_libur(
        $userid,
        $tanggal_awal_minggu_ini,
        $tanggal_akhir_minggu_ini
    );

$target_final =
    max(0, $beban_minggu - $pengurang_libur);

if ($target_final > 0) {

    $persen = round(
        ($jumlahjam / $target_final) * 100
    );

    $persen = min($persen, 100);

} else {

    $persen = null;
}

        // Atur warna Badge Persentase & Soft warning di Baris Tabel
	$tr_class = '';

	if ($persen === null) {

	    $badge_class = 'badge-secondary';

	} elseif ($persen >= 80) {

	    $badge_class = 'badge-success';

	} elseif ($persen >= 50) {

	    $badge_class = 'badge-info';

	} else {

	    $badge_class = 'badge-danger';
	    $tr_class = 'table-danger-light';
	} // Membutuhkan CSS kustom di bawah agar soft warnanya
        

        echo html_writer::start_tag('tr', ['class' => $tr_class]);
            echo html_writer::tag('td', $no++, ['class' => 'text-center align-middle font-weight-bold text-muted']);
            
            // Link ke Rekap Guru Semester
            $urlguru = new moodle_url('/local/jurnalmengajar/rekap_guru_semester.php', ['userid' => $userid]);
            echo html_writer::tag('td', html_writer::link($urlguru, $nama, ['class' => 'font-weight-bold text-dark text-decoration-none']));
            
            echo html_writer::tag('td', $jumlahjam . ' JP', ['class' => 'text-center align-middle']);
            $teks_target = $beban_minggu . ' JP';

if ($pengurang_libur > 0) {

    $teks_target .=
    '<br><small class="text-danger">-'
    . $pengurang_libur .
    ' JP tanpa KBM</small>';

    $teks_target .=
    '<br><small class="text-success font-weight-bold">'
    . 'Target Akhir: '
    . $target_final .
    ' JP</small>';
}

echo html_writer::tag(
    'td',
    $teks_target,
    ['class' => 'text-center align-middle text-muted']
);
            
	// Badge Persentase
	$badge_text = ($persen === null)
	    ? '-'
	    : $persen . '%';

	$badge = html_writer::tag('span', $badge_text, [
	    'class' => 'badge ' . $badge_class . ' p-2 w-100',
	    'style' => 'font-size: 85%'
	]);
            echo html_writer::tag('td', $badge, ['class' => 'text-center align-middle']);

            // Tombol Lihat Detail
            $url = new moodle_url('/local/jurnalmengajar/rekap_perguru.php', [
                'userid' => $userid,
                'mingguke' => $mingguke
            ]);
            $btn_detail = html_writer::link($url, '🔍 Detail', ['class' => 'btn btn-xs btn-outline-primary btn-sm block shadow-sm']);
            echo html_writer::tag('td', $btn_detail, ['class' => 'text-center align-middle']);
        echo html_writer::end_tag('tr');
    }
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_div();

// Tambahkan CSS Kustom (soft color untuk baris di bawah 50%)
echo '<style>
    .table-danger-light { background-color: rgba(220, 53, 69, 0.06) !important; }
    .table-hover tbody tr.table-danger-light:hover { background-color: rgba(220, 53, 69, 0.12) !important; }
    .table th, .table td { vertical-align: middle !important; }
</style>';

echo $OUTPUT->footer();
