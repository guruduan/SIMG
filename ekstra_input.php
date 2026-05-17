<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/ekstra_lib.php');
require_once($CFG->libdir . '/formslib.php');

require_login();

global $DB, $USER;

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/ekstra_input.php'));
$PAGE->set_title('Input Jurnal Ekstrakurikuler');
$PAGE->set_heading('Input Jurnal Ekstrakurikuler');

class ekstra_form extends moodleform {

    public function definition() {
        global $USER;

        $mform = $this->_form;

        $ekstra = ekstra_get_pembina_ekstra($USER->id);

        $options = [];

        foreach ($ekstra as $e) {
            $options[$e->id] = $e->namaekstra;
        }

        $mform->addElement('select', 'ekstraid', 'Ekstrakurikuler', $options);
        $mform->addRule('ekstraid', 'Wajib dipilih', 'required');

        $mform->addElement('date_selector', 'tanggal', 'Tanggal');

        $mform->addElement('text', 'materi', 'Materi', ['size' => 80]);
        $mform->setType('materi', PARAM_TEXT);

        $mform->addElement(
    'textarea',
    'aktivitas',
    'Aktivitas',
    [
        'rows' => 4,
        'cols' => 80
    ]
);
        $mform->setType('aktivitas', PARAM_RAW);

        $mform->addElement('textarea', 'catatan', 'Catatan', ['rows' => 3]);
        $mform->setType('catatan', PARAM_RAW);
$firstekstra = array_key_first($options);

if ($firstekstra) {

    $peserta = ekstra_get_peserta($firstekstra);

    $html = '<hr>';
    $html .= '<h4>Absensi Peserta</h4>';

    foreach ($peserta as $p) {

        $nama = trim($p->firstname . ' ' . $p->lastname);

        $html .= '
        <div style="margin-bottom:6px;">
            <strong>' . s($nama) . '</strong>

            <label style="margin-left:10px;">
                <input type="radio" name="status['.$p->userid.']" value="Sakit">
                Sakit
            </label>
 
            <label style="margin-left:10px;">
                <input type="radio" name="status['.$p->userid.']" value="Ijin">
                Ijin
            </label>

            <label style="margin-left:10px;">
                <input type="radio" name="status['.$p->userid.']" value="Alpa">
                Alpa
            </label>

            <label style="margin-left:10px;">
                <input type="radio" name="status['.$p->userid.']" value="Dispensasi">
                Dispensasi
            </label>
        </div>';
    }

    $mform->addElement('static', 'absensihtml', '', $html);
}
        $this->add_action_buttons(true, 'Simpan');
    }
}

$mform = new ekstra_form();

if ($data = $mform->get_data()) {

$record = new stdClass();
$record->ekstraid = (int)$data->ekstraid;
$record->pembinaid = (int)$USER->id;
$record->tanggal = (int)$data->tanggal;
$record->materi = trim((string)$data->materi);
$record->aktivitas = trim((string)$data->aktivitas);
$record->catatan = trim((string)$data->catatan);
$record->timecreated = time();

$jurnalid = $DB->insert_record(
    'local_ekstra_jurnal',
    $record
);

$statuslist = optional_param_array('status', [], PARAM_TEXT);

foreach ($statuslist as $userid => $status) {

    $absen = new stdClass();
    $absen->jurnalid = $jurnalid;
    $absen->userid = $userid;
    $absen->status = $status;

    $DB->insert_record('local_ekstra_absen', $absen);
}

    redirect(new moodle_url('/local/jurnalmengajar/ekstra_riwayat.php'), 'Jurnal berhasil disimpan');
}

echo $OUTPUT->header();

$mform->display();

echo $OUTPUT->footer();
