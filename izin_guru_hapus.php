<?php
require('../../config.php');
require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php'); 
require_once($CFG->dirroot . '/local/jurnalmengajar/classes/form/suratiguru_form.php');
require_once($CFG->libdir.'/pdflib.php');

require_login();
$context = context_system::instance();
require_capability('local/jurnalmengajar:submitsuratizin', $context);

global $DB, $USER, $PAGE, $OUTPUT;

// ================= AMBIL DATA USER =================
$fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => 'nip']);
$nipdata = [];
$choices = ['' => 'Pilih Nama Guru/Pegawai...'];

// role
$sqlroles = "SELECT id FROM {role} WHERE shortname IN (?, ?)";
$roleids = array_keys($DB->get_records_sql($sqlroles, ['gurujurnal', 'pegawaitu']));

// user
$userids = [];
if (!empty($roleids)) {
    list($inrole, $roleparams) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
    $params = $roleparams + ['systemlevel' => CONTEXT_SYSTEM];

    $sql = "SELECT DISTINCT ra.userid
            FROM {role_assignments} ra
            JOIN {context} ctx ON ctx.id = ra.contextid
            WHERE ra.roleid $inrole
            AND ctx.contextlevel = :systemlevel";

    $userrecords = $DB->get_records_sql($sql, $params);
    $userids = array_keys($userrecords);
}

if (!empty($userids) && $fieldid) {
    list($inuser, $params2) = $DB->get_in_or_equal($userids);
    array_unshift($params2, $fieldid);

    $sql = "SELECT u.id, u.lastname, d.data AS nip
            FROM {user} u
            LEFT JOIN {user_info_data} d ON d.userid = u.id AND d.fieldid = ?
            WHERE u.id $inuser
            ORDER BY u.lastname ASC";

    $users = $DB->get_records_sql($sql, $params2);

    foreach ($users as $u) {
        $nipdata[$u->id] = $u->nip ?? '';
        $choices[$u->id] = $u->lastname;
    }
}

// ================= PAGE =================
$PAGE->set_url('/local/jurnalmengajar/input_surat_izin_guru.php');
$PAGE->set_context($context);
$PAGE->set_title('Input Surat Izin Guru/Pegawai');
$PAGE->set_heading('Input Surat Izin Guru/Pegawai');

// ================= FORM =================
$mform = new \local_jurnalmengajar\form\suratiguru_form(null, [
    'nipdata' => $nipdata,
    'choices' => $choices
]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/jurnalmengajar/index.php'));

} else if ($data = $mform->get_data()) {

    $record = new stdClass();
    $record->userid      = $data->userid; // Guru yang izin
    $record->nip         = $data->nip;
    $record->alasan      = $data->alasan;
    $record->keperluan   = $data->keperluan;
    $record->waktuinput  = time();
    $record->userinput   = $USER->id; // ✅ Penginput: Admin / Guru Piket / Diri Sendiri

    $insertid = $DB->insert_record('local_jurnalmengajar_suratizinguru', $record);
redirect(
    new moodle_url('/local/jurnalmengajar/input_surat_izin_guru.php'),
    'Surat izin berhasil disimpan',
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
}

// ================= TAMPILAN =================
echo $OUTPUT->header();

echo html_writer::start_div('container-fluid mt-3');
$mform->display();
echo html_writer::end_div();

// ================= AUTO ISI NIP =================
if (!empty($nipdata)) {
    $jsnip = json_encode($nipdata);
    echo "<script>
    document.addEventListener('DOMContentLoaded', function() {
        const nipMap = $jsnip;
        const select = document.querySelector('select[name=\"userid\"]');
        const nipInput = document.querySelector('input[name=\"nip\"]');
        if (select && nipInput) {
            select.addEventListener('change', () => {
                nipInput.value = nipMap[select.value] || '';
            });
        }
    });
    </script>";
}

// ================= RIWAYAT =================
// ✅ Kembalikan query seperti semula (tanpa join ke u2/penginput)
$riwayat = $DB->get_records_sql("
    SELECT s.*, u.lastname AS namaguru 
    FROM {local_jurnalmengajar_suratizinguru} s
    JOIN {user} u ON u.id = s.userid
    ORDER BY s.waktuinput DESC
");

echo html_writer::start_div('card mt-5 mb-4');
echo html_writer::start_div('card-header text-white bg-primary');
echo html_writer::tag('h4', '📋 Riwayat Surat Izin', ['class' => 'mb-0 text-white']);
echo html_writer::end_div();

echo html_writer::start_div('card-body table-responsive');

$table = new html_table();

// ✅ Header Tabel dengan pengaturan lebar minimum untuk Nama dan NIP
$table->head = [
    'No', 
    html_writer::tag('span', 'Waktu', ['style' => 'min-width: 110px; display: inline-block;']), 
//    'Penginput', 
    html_writer::tag('span', 'Nama Guru', ['style' => 'min-width: 200px; display: inline-block;']), 
    html_writer::tag('span', 'NIP', ['style' => 'min-width: 180px; display: inline-block;']), 
    'Alasan', 
    'Keperluan', 
    'Aksi'
];
$table->attributes['class'] = 'table table-bordered table-striped table-hover text-center align-middle';

$no = 1;

foreach ($riwayat as $r) {
    // ✅ Tanggal format global
    $tanggal = tanggal_indo($r->waktuinput);

    $hapusurl = new moodle_url('/local/jurnalmengajar/hapus_surat_izin_guru.php', [
        'id' => $r->id,
        'sesskey' => sesskey()
    ]);

    $hapus = html_writer::link(
        $hapusurl,
        '🗑 Hapus',
        [
            'onclick' => "return confirm('Yakin ingin menghapus data ini?')",
            'class' => 'btn btn-outline-danger btn-sm'
        ]
    );

    // Jika data lama belum ada ID penginput, tampilkan strip (-)
    $table->data[] = [
        $no++,
        $tanggal, // ✅ Tampil sebagai teks biasa
        html_writer::tag('strong', $r->namaguru),
        $r->nip,
        $r->alasan,
        $r->keperluan,
        $hapus
    ];
}

echo html_writer::table($table);
echo html_writer::end_div(); // end card-body
echo html_writer::end_div(); // end card

// ================= REKAP =================
$exporturl = new moodle_url('/local/jurnalmengajar/rekap_surat_izin_guru.php');

echo html_writer::start_div('card mb-5');
echo html_writer::start_div('card-body bg-light');

echo html_writer::tag('h5', '🖨️ Cetak Rekapitulasi Surat Izin', ['class' => 'card-title mb-3']);

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $exporturl, 'class' => 'd-flex align-items-center gap-3']);

echo html_writer::tag('label', 'Pilih Bulan:', ['class' => 'fw-bold mb-0 mr-2']);

echo html_writer::select([
    '01'=>'Januari', '02'=>'Februari', '03'=>'Maret', '04'=>'April',
    '05'=>'Mei', '06'=>'Juni', '07'=>'Juli', '08'=>'Agustus',
    '09'=>'September', '10'=>'Oktober', '11'=>'November', '12'=>'Desember'
], 'bulan', date('m'), false, ['class' => 'custom-select form-select w-auto mr-3']);

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => 'Download Rekap PDF',
    'class' => 'btn btn-success'
]);

echo html_writer::end_tag('form');

echo html_writer::end_div(); // end card-body
echo html_writer::end_div(); // end card

echo $OUTPUT->footer();
