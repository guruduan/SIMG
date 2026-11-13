<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
require_capability('moodle/site:config', $context);

global $DB, $OUTPUT, $PAGE;

// ===== Tahun ajaran berjalan (tahun ajaran mulai Juli)
// November 2026 -> 2026/2027
$bulan_ini = (int)date('n');
$tahun_mulai = ($bulan_ini >= 7) ? (int)date('Y') : (int)date('Y') - 1;
$tahunajaran_berjalan = $tahun_mulai . '/' . ($tahun_mulai + 1);

// Pilihan tahun ajaran di form (2 tahun lalu s.d. 1 tahun depan)
$tahunajaran_options = [];
for ($t = $tahun_mulai - 2; $t <= $tahun_mulai + 1; $t++) {
    $tahunajaran_options[$t . '/' . ($t + 1)] = $t . '/' . ($t + 1);
}

// ===== Ambil mapping lama
// Format BARU (per tahun ajaran): { "2026/2027": { cohortid: userid }, "2025/2026": { ... } }
// Format LAMA: { cohortid: userid } — dimigrasikan ke tahun ajaran berjalan di bawah
$json = get_config('local_jurnalmengajar', 'wali_kelas_mapping');
$mapping = json_decode($json, true);
if (!is_array($mapping)) {
    $mapping = [];
}

// ===== MIGRASI format lama -> format baru (sekali, lalu disimpan)
$migrated = false;
foreach ($mapping as $kunci => $nilai) {
    if (!is_array($nilai)) {
        // Kunci = cohortid, nilai = userid (format lama)
        $mapping[$tahunajaran_berjalan][$kunci] = $nilai;
        unset($mapping[$kunci]);
        $migrated = true;
    }
}
if ($migrated) {
    set_config('wali_kelas_mapping', json_encode($mapping), 'local_jurnalmengajar');
}

// Tahun ajaran yang sudah ada di mapping tetap bisa dipilih di form,
// walaupun sudah lebih dari 2 tahun lalu (supaya riwayat tahun lama
// tetap bisa diisi / diperbaiki di tahun-tahun berikutnya).
foreach (array_keys($mapping) as $tahun_lama) {
    if (is_string($tahun_lama) && preg_match('/^\d{4}\/\d{4}$/', $tahun_lama)) {
        $tahunajaran_options[$tahun_lama] = $tahun_lama;
    }
}
ksort($tahunajaran_options);

// ===== AUTO CLEAN (hapus mapping yang cohort sudah tidak ada)
$changed = false;
foreach ($mapping as $tahunajaran => $daftarkelas) {
    if (!is_array($daftarkelas)) {
        unset($mapping[$tahunajaran]);
        $changed = true;
        continue;
    }
    foreach ($daftarkelas as $kelasid => $userid) {
        if (!$DB->record_exists('cohort', ['id' => $kelasid])) {
            unset($mapping[$tahunajaran][$kelasid]);
            $changed = true;
        }
    }
    if (empty($mapping[$tahunajaran])) {
        unset($mapping[$tahunajaran]);
        $changed = true;
    }
}
if ($changed) {
    set_config('wali_kelas_mapping', json_encode($mapping), 'local_jurnalmengajar');
}

// ===== PROSES POST & DELETE
$action = optional_param('action', '', PARAM_TEXT);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey() && $action === 'save') {
    $tahunajaran = required_param('tahunajaran', PARAM_TEXT);
    $kelasid = required_param('kelas', PARAM_INT);
    $userid  = required_param('userid', PARAM_INT);

    if (!preg_match('/^\d{4}\/\d{4}$/', $tahunajaran) || !$kelasid || !$userid) {
        redirect(new moodle_url('/local/jurnalmengajar/walikelas_manage.php'), '⚠️ Data tidak valid', null, \core\output\notification::NOTIFY_ERROR);
    }

    $mapping[$tahunajaran][$kelasid] = $userid;
    set_config('wali_kelas_mapping', json_encode($mapping), 'local_jurnalmengajar');
    redirect(new moodle_url('/local/jurnalmengajar/walikelas_manage.php'), '✅ Mapping berhasil disimpan', null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey() && $action === 'delete') {
    $tahunajaran = required_param('tahunajaran', PARAM_TEXT);
    $deleteid = required_param('deleteid', PARAM_INT);
    if (isset($mapping[$tahunajaran][$deleteid])) {
        unset($mapping[$tahunajaran][$deleteid]);
        if (empty($mapping[$tahunajaran])) {
            unset($mapping[$tahunajaran]);
        }
        set_config('wali_kelas_mapping', json_encode($mapping), 'local_jurnalmengajar');
        redirect(new moodle_url('/local/jurnalmengajar/walikelas_manage.php'), '🗑️ Mapping berhasil dihapus', null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// ===== SET PAGE
$PAGE->set_url('/local/jurnalmengajar/walikelas_manage.php');
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Mapping Wali Kelas');
$PAGE->set_heading('Mapping Wali Kelas');

echo $OUTPUT->header();

// ===== Ambil Data Cohort & User
$cohorts = $DB->get_records('cohort', null, 'name ASC');
$kelas_options = [];
foreach ($cohorts as $c) {
    $kelas_options[$c->id] = $c->name;
}

$role = $DB->get_record('role', ['shortname' => 'gurujurnal']);
$users = get_role_users($role->id, $context);
$user_options = [];
foreach ($users as $u) {
    $nama = !empty($u->lastname) ? $u->lastname : $u->firstname;
    $user_options[$u->id] = $nama;
}

// =====================================================================
// TAMPILAN UI DIMULAI DI SINI
// =====================================================================

echo html_writer::start_div('container-fluid mt-3');

// --- CARD FORM INPUT ---
echo html_writer::start_div('card mb-4 shadow-sm');
echo html_writer::start_div('card-header bg-primary text-white');
echo html_writer::tag('h4', '➕ Tambah / Update Mapping', ['class' => 'mb-0']);
echo html_writer::end_div(); // end card-header

echo html_writer::start_div('card-body');

echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'm-0']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);

echo html_writer::start_div('row align-items-end'); // Grid row

// Dropdown Tahun Ajaran
echo html_writer::start_div('col-md-3 mb-3');
echo html_writer::label('Tahun Ajaran', 'tahunajaran', ['class' => 'font-weight-bold']);
echo html_writer::select($tahunajaran_options, 'tahunajaran', $tahunajaran_berjalan, [], ['class' => 'form-control custom-select', 'id' => 'tahunajaran', 'required' => 'required']);
echo html_writer::end_div();

// Dropdown Kelas
echo html_writer::start_div('col-md-3 mb-3');
echo html_writer::label('Kelas', 'kelas', ['class' => 'font-weight-bold']);
echo html_writer::select($kelas_options, 'kelas', '', ['' => 'Pilih kelas...'], ['class' => 'form-control custom-select', 'id' => 'kelas', 'required' => 'required']);
echo html_writer::end_div();

// Dropdown Wali Kelas
echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::label('Wali Kelas', 'userid', ['class' => 'font-weight-bold']);
echo html_writer::select($user_options, 'userid', '', ['' => 'Pilih wali kelas...'], ['class' => 'form-control custom-select', 'id' => 'userid', 'required' => 'required']);
echo html_writer::end_div();

// Tombol Simpan
echo html_writer::start_div('col-md-2 mb-3');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => '💾 Simpan',
    'class' => 'btn btn-primary w-100'
]);
echo html_writer::end_div();

echo html_writer::end_div(); // end row
echo html_writer::end_tag('form');
echo html_writer::end_div(); // end card-body
echo html_writer::end_div(); // end card form


// --- CARD TABEL MAPPING ---
echo html_writer::start_div('card shadow-sm');
echo html_writer::start_div('card-header bg-info text-white');
echo html_writer::tag('h4', '📋 Data Mapping Wali Kelas', ['class' => 'mb-0']);
echo html_writer::end_div();

echo html_writer::start_div('card-body');

$table = new html_table();
// Tambahkan class Bootstrap agar tabel tampil modern dan responsif
$table->attributes['class'] = 'table table-bordered table-striped table-hover text-center align-middle';
$table->head = ['No', 'Tahun Ajaran', 'Kelas', 'Wali Kelas', 'Status', 'Aksi'];

krsort($mapping); // Tahun ajaran terbaru dulu

$no = 1;
foreach ($mapping as $tahunajaran => $daftarkelas) {
    if (!is_array($daftarkelas)) {
        continue;
    }
    ksort($daftarkelas);
    foreach ($daftarkelas as $kelasid => $userid) {
        $kelas = $DB->get_field('cohort', 'name', ['id' => $kelasid]);
        $status = html_writer::span('Aktif', 'badge badge-success');

        if (!$kelas) {
            $kelas = html_writer::span("ID $kelasid (dihapus)", 'text-danger');
            $status = html_writer::span('Tidak valid', 'badge badge-warning');
        }

        $user = $DB->get_record('user', ['id' => $userid]);
        $nama = '-';
        if ($user) {
            $nama = !empty($user->lastname) ? $user->lastname : $user->firstname;
        } else {
            $nama = html_writer::span("User tidak ditemukan", 'text-danger');
            $status = html_writer::span('Tidak valid', 'badge badge-warning');
        }

        // Tombol Hapus (Mini Form)
        $delete_btn = html_writer::start_tag('form', ['method' => 'post', 'class' => 'm-0', 'onsubmit' => "return confirm('Yakin ingin menghapus mapping kelas ini?');"]) .
                      html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                      html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'delete']) .
                      html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tahunajaran', 'value' => $tahunajaran]) .
                      html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'deleteid', 'value' => $kelasid]) .
                      html_writer::empty_tag('input', ['type' => 'submit', 'value' => '❌ Hapus', 'class' => 'btn btn-sm btn-outline-danger']) .
                      html_writer::end_tag('form');

        $table->data[] = [
            $no++,
            html_writer::tag('strong', $tahunajaran),
            html_writer::tag('strong', $kelas),
            $nama,
            $status,
            $delete_btn
        ];
    }
}

if (empty($mapping)) {
    echo html_writer::tag('div', 'Belum ada data mapping wali kelas.', ['class' => 'alert alert-info text-center']);
} else {
    echo html_writer::start_div('table-responsive');
    echo html_writer::table($table);
    echo html_writer::end_div();
}

echo html_writer::end_div(); // end card-body
echo html_writer::end_div(); // end card table

echo html_writer::end_div(); // end container-fluid

echo $OUTPUT->footer();
