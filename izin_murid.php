<?php
require_once('../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_url(new moodle_url('/local/jurnalmengajar/izin_murid.php'));
$PAGE->set_context($context);
$PAGE->set_title('Surat Izin Keluar/Masuk');
$PAGE->set_heading('Surat Izin Keluar/Masuk');

global $DB, $USER, $CFG;
$pengawas = $USER->lastname;

require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');
require_once($CFG->dirroot . '/local/jurnalmengajar/lib_notifikasi.php');

// ================= DATA AWAL =================
$cohorts = $DB->get_records_menu('cohort', null, 'name ASC', 'id, name');

// Ambil guru (role gurujurnal)
$guruoptions = [];
$roleid = $DB->get_field('role', 'id', ['shortname' => 'gurujurnal']);

if ($roleid) {
    $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname
              FROM {user} u
              JOIN {role_assignments} ra ON ra.userid = u.id
             WHERE ra.roleid = :roleid
          ORDER BY u.lastname ASC";

    $gurus = $DB->get_records_sql($sql, ['roleid' => $roleid]);

    foreach ($gurus as $g) {
        $guruoptions[$g->id] = !empty($g->lastname) ? $g->lastname : $g->firstname;
    }
}

$kelasid = optional_param('kelasid', 0, PARAM_INT);

// ================= HAPUS =================
if ($hapusid = optional_param('hapusid', 0, PARAM_INT)) {
    require_sesskey();
    $DB->delete_records('local_jurnalmengajar_suratizin', ['id' => $hapusid]);

    redirect(
        new moodle_url('/local/jurnalmengajar/izin_murid.php', ['kelasid' => $kelasid]),
        'Data berhasil dihapus.',
        1
    );
}

// ================= SISWA =================
$siswaoptions = [];

if ($kelasid) {
    $members = $DB->get_records('cohort_members', ['cohortid' => $kelasid]);

    foreach ($members as $m) {
        $u = $DB->get_record('user', ['id' => $m->userid], 'id, firstname, lastname');
        if ($u) {
            $siswaoptions[$u->id] = !empty($u->lastname) ? $u->lastname : $u->firstname;
        }
    }
}

// ================= PROSES =================
$action = optional_param('action', '', PARAM_ALPHA);
$do_submit = ($action === 'print');
$do_save = ($action === 'save');

if (($do_submit || $do_save) && confirm_sesskey()) {

    $record = new stdClass();
    $record->userid        = required_param('siswaid', PARAM_INT);
    $record->kelasid       = required_param('kelasid', PARAM_INT);
    $record->kelas         = get_nama_kelas($record->kelasid);
    $record->guru_pengajar = required_param('guru_pengajar', PARAM_INT);
    $record->alasan        = required_param('alasan', PARAM_TEXT);
    $record->keperluan     = required_param('keperluan', PARAM_TEXT);
    $record->catatan       = optional_param('catatan', '', PARAM_TEXT);
    $record->penginput     = $USER->id;
    $record->timecreated   = time();

    // Anti duplikat (10 menit)
    $cek = $DB->get_record_sql("
        SELECT id
          FROM {local_jurnalmengajar_suratizin}
         WHERE userid = :userid
           AND kelasid = :kelas
           AND timecreated >= :waktu
         LIMIT 1
    ", [
        'userid' => $record->userid,
        'kelas' => $record->kelasid,
        'waktu' => time() - 600
    ]);

    if ($cek) {
        $id = $cek->id;
    } else {

$id = $DB->insert_record('local_jurnalmengajar_suratizin', $record);

    }

    // ================= KIRIM WA =================
    $siswa = $DB->get_record('user', ['id' => $record->userid]);
    if ($siswa) {
        $kelas = get_nama_kelas($record->kelasid);
        $nama  = ucwords(strtolower($siswa->lastname));
        $gurunama = $DB->get_field('user', 'lastname', ['id' => $record->guru_pengajar]);
        $waktu_full = tanggal_indo($record->timecreated);

	$datawa = [
	    '{waktu}'     => $waktu_full,
	    '{nama}'      => $nama,
	    '{kelas}'     => $kelas,
	    '{guru}'      => $gurunama,
	    '{alasan}'    => $record->alasan,
	    '{keperluan}' => $record->keperluan,
	    '{pengawas}'  => $pengawas,

	    // dipakai resolver tujuan
	    'kelas'       => $record->kelasid
	];

	jm_kirim_template_auto(
	    'izin_murid',
	    $datawa
	);
    }

    // ================= REDIRECT =================
    if ($do_save) {
        redirect(
            new moodle_url('/local/jurnalmengajar/izin_murid.php', ['kelasid' => $record->kelasid]),
            'Data berhasil disimpan.',
            1
        );
    } else {
        redirect(
            new moodle_url('/local/jurnalmengajar/cetak_surat_izin.php', ['id' => $id])
        );
    }
}

// ================= TAMPILAN MCOODLE =================
echo $OUTPUT->header();
echo $OUTPUT->heading('Input Surat Izin Murid', 2, 'mb-4');

// STEP 1: Pilih Kelas (Dibuat menyerupai mini dashboard card)
echo html_writer::start_div('card bg-light mb-4 shadow-sm');
echo html_writer::start_div('card-body py-3 form-inline gap-3');
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'd-flex align-items-center w-100 gap-2']);
echo html_writer::tag('label', 'Pilih Kelas Terlebih Dahulu', ['class' => 'font-weight-bold mr-3', 'for' => 'kelasid']);
echo html_writer::select($cohorts, 'kelasid', $kelasid, ['' => 'Pilih kelas...'], ['onchange' => 'this.form.submit()', 'class' => 'form-control custom-select bg-white', 'id' => 'kelasid']);
echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();

// ================= FORM UTAMA =================
if ($kelasid) {
    echo html_writer::start_div('card mb-5 shadow-sm border-primary');
    echo html_writer::div('📋 Formulir Detail Perizinan Murid', 'card-header bg-primary text-white font-weight-bold');
    echo html_writer::start_div('card-body');

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new moodle_url('/local/jurnalmengajar/izin_murid.php'))->out(false)
    ]);

    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'kelasid', 'value' => $kelasid]);

    // Row 1: Baris Nama Murid & Guru Pengajar sejajar (Dua Kolom)
    echo html_writer::start_div('row mb-3');
    
    echo html_writer::start_div('col-md-6 col-sm-12');
    echo html_writer::tag('label', 'Nama Murid <span class="text-danger">*</span>', ['class' => 'font-weight-bold', 'for' => 'siswaid']);
    echo html_writer::select($siswaoptions, 'siswaid', '', ['' => 'Pilih Murid...'], ['required' => 'required', 'class' => 'form-control custom-select', 'id' => 'siswaid']);
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-6 col-sm-12 mt-3 mt-md-0');
    echo html_writer::tag('label', 'Guru Pengajar Saat Ini <span class="text-danger">*</span>', ['class' => 'font-weight-bold', 'for' => 'guru_pengajar']);
    echo html_writer::select($guruoptions, 'guru_pengajar', '', ['' => 'Pilih Guru...'], ['required' => 'required', 'class' => 'form-control custom-select', 'id' => 'guru_pengajar']);
    echo html_writer::end_div();

    echo html_writer::end_div(); // End Row 1

    // Row 2: Keperluan
    echo html_writer::start_div('form-group mb-3');
    echo html_writer::tag('label', 'Jenis Keperluan <span class="text-danger">*</span>', ['class' => 'font-weight-bold', 'for' => 'keperluan']);
    echo html_writer::select([
        'Izin Masuk' => 'Izin Masuk',
        'Izin Keluar' => 'Izin Keluar',
        'Izin Pulang' => 'Izin Pulang'
    ], 'keperluan', '', ['' => 'Pilih Jenis Keperluan...'], ['required' => 'required', 'class' => 'form-control custom-select', 'id' => 'keperluan']);
    echo html_writer::end_div();

    // Row 3: Alasan
    echo html_writer::start_div('form-group mb-4');
    echo html_writer::tag('label', 'Alasan / Keterangan Detail <span class="text-danger">*</span>', ['class' => 'font-weight-bold', 'for' => 'alasan']);
    echo html_writer::tag('textarea', '', [
        'name' => 'alasan',
        'id' => 'alasan',
        'rows' => 3,
        'class' => 'form-control',
        'required' => 'required',
        'placeholder' => 'Contoh: Sakit kepala berobat ke Puskesmas, Orang tua menjemput karena acara keluarga, dll...'
    ]);
    echo html_writer::end_div();

	// Row 4: Catatan Pembinaan
	echo html_writer::start_div('form-group mb-4');
	echo html_writer::tag(
	    'label',
	    'Catatan Pembinaan',
	    ['class' => 'font-weight-bold', 'for' => 'catatan']
	);

	echo html_writer::tag('textarea', '', [
	    'name' => 'catatan',
	    'id' => 'catatan',
	    'rows' => 3,
	    'class' => 'form-control',
	    'placeholder' => 'Catatan pembinaan atau tindak lanjut oleh Guru Pengawas khusus bagi murid yang terlambat (izin masuk)'
	]);

	echo html_writer::end_div();

    // Baris Tombol Submit Aksi
    echo html_writer::start_div('d-flex gap-2');
    echo html_writer::tag('button', '<i class="fa fa-print"></i> Cetak Surat', [
        'type' => 'submit',
        'name' => 'action',
        'value' => 'print',
        'class' => 'btn btn-success shadow-sm px-4'
    ]);

    echo html_writer::tag('button', '<i class="fa fa-save"></i> Simpan Surat', [
        'type' => 'submit',
        'name' => 'action',
        'value' => 'save',
        'class' => 'btn btn-outline-secondary px-4'
    ]);
    echo html_writer::end_div();

    echo html_writer::end_tag('form');
    echo html_writer::end_div(); // close card-body
    echo html_writer::end_div(); // close card
}

// ================= SECTION RIWAYAT SURAT IZIN =================
echo html_writer::start_div('d-flex justify-content-between align-items-center mt-5 mb-3 border-bottom pb-2');
echo html_writer::tag('h3', '📋 Log Riwayat Surat Izin');

$rekapurl = new moodle_url('/local/jurnalmengajar/rekap_surat_izin.php');
echo html_writer::link($rekapurl, '<i class="fa fa-book"></i> Buka Rekap Surat Izin', [
    'class' => 'btn btn-primary shadow-sm'
]);
echo html_writer::end_div();

// ================= FILTER TABEL RIWAYAT =================
$riwayatkelasid = optional_param('riwayat_kelasid', 0, PARAM_INT);

echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'form-inline bg-light p-3 rounded mb-3 gap-2 border shadow-sm']);
echo html_writer::tag('label', 'Saring Berdasarkan Kelas: ', ['class' => 'font-weight-bold mr-2', 'for' => 'riwayat_kelasid']);
echo html_writer::select($cohorts, 'riwayat_kelasid', $riwayatkelasid, ['' => 'Semua Kelas...'], ['class' => 'form-control custom-select bg-white mr-2', 'id' => 'riwayat_kelasid']);
echo html_writer::tag('button', '<i class="fa fa-filter"></i> Filter', ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

// ================= QUERY RIWAYAT =================
$params = [];
$where = '';

if ($riwayatkelasid) {
    $where = "WHERE s.kelasid = :kelasid";
    $params['kelasid'] = $riwayatkelasid;
}

$sql = "SELECT s.*, 
               u.lastname AS siswa_nama, 
               g.lastname AS guru_nama, 
               p.lastname AS pengawas_nama
          FROM {local_jurnalmengajar_suratizin} s
          JOIN {user} u ON u.id = s.userid
          JOIN {user} g ON g.id = s.guru_pengajar
          JOIN {user} p ON p.id = s.penginput
          $where
      ORDER BY s.timecreated DESC
         LIMIT 30";

$riwayatsurat = $DB->get_records_sql($sql, $params);

// ================= PROSES GENERATE TABEL =================
if ($riwayatsurat) {
    $table = new html_table();
    
    // Memberikan style class Bootstrap modern ke komponen table Moodle core
    $table->attributes['class'] = 'table table-striped table-hover generaltable mb-0';

    $table->head = ['No', 'Tanggal & Waktu', 'Nama Murid', 'Kelas', 'Guru Pengajar', 'Alasan', 'Keperluan', 'Catatan Pembinaan', 'Guru Pengawas'];
    $table->align = ['center', 'left', 'left', 'center', 'left', 'left', 'left', 'left', 'left'];

    $no = 1;
    foreach ($riwayatsurat as $s) {
        $kelasnama = $s->kelas;
        $tgl_display = tanggal_indo($s->timecreated);

        // Memberikan badge warna dinamis sesuai jenis izin murid
        $badge_class = 'badge badge-info';
        if ($s->keperluan === 'Izin Keluar') { $badge_class = 'badge badge-warning'; }
        if ($s->keperluan === 'Izin Pulang') { $badge_class = 'badge badge-danger'; }

        $keperluan_badge = html_writer::tag('span', $s->keperluan, ['class' => $badge_class . ' p-1 px-2']);

        $table->data[] = [
            $no++,
            html_writer::tag('small', $tgl_display),
            html_writer::tag('strong', ucwords(strtolower($s->siswa_nama))),
            $kelasnama,
            $s->guru_nama,
            shorten_text($s->alasan, 40),
            $keperluan_badge,
            shorten_text($s->catatan ?? '', 40),
            $s->pengawas_nama
        ];
    }

    // Membungkus framework objek tabel Moodle ke div responsif agar aman di HP
    echo html_writer::start_div('table-responsive shadow-sm rounded border border-secondary');
    echo html_writer::table($table);
    echo html_writer::end_div();

} else {
    echo html_writer::div('Belum ada data riwayat surat izin yang dicatat untuk kriteria filter ini.', 'alert alert-info shadow-sm py-3');
}

echo $OUTPUT->footer();
