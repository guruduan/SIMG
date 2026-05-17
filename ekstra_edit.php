<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/ekstra_lib.php');
require_once($CFG->libdir . '/formslib.php');

require_login();

global $DB, $USER;

$id = required_param('id', PARAM_INT);

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$record = $DB->get_record(
    'local_ekstra_jurnal',
    ['id' => $id],
    '*',
    MUST_EXIST
);

if ($record->pembinaid != $USER->id && !is_siteadmin()) {
    throw new required_capability_exception(
        $context,
        'local/jurnalmengajar:submit',
        'nopermissions',
        ''
    );
}

$PAGE->set_context($context);
$PAGE->set_url(
    new moodle_url('/local/jurnalmengajar/ekstra_edit.php', [
        'id' => $id
    ])
);

$PAGE->set_title('Edit Jurnal Ekstrakurikuler');
$PAGE->set_heading('Edit Jurnal Ekstrakurikuler');

class ekstra_edit_form extends moodleform {

    private $record;

    public function __construct($action, $record) {
        $this->record = $record;
        parent::__construct($action);
    }

    public function definition() {

        global $USER, $DB;

        $mform = $this->_form;

        $record = $this->record;

        $ekstra = ekstra_get_pembina_ekstra($USER->id);

        $options = [];

        foreach ($ekstra as $e) {
            $options[$e->id] = $e->namaekstra;
        }

        $mform->addElement(
            'select',
            'ekstraid',
            'Ekstrakurikuler',
            $options
        );

        $mform->setDefault('ekstraid', $record->ekstraid);

        $mform->addElement('date_selector', 'tanggal', 'Tanggal');
        $mform->setDefault('tanggal', $record->tanggal);

        $mform->addElement('text', 'materi', 'Materi', [
            'size' => 80
        ]);
        $mform->setType('materi', PARAM_TEXT);
        $mform->setDefault('materi', $record->materi);

        $mform->addElement('textarea', 'aktivitas', 'Aktivitas', [
            'rows' => 4,
            'cols' => 80
        ]);
        $mform->setType('aktivitas', PARAM_RAW);
        $mform->setDefault('aktivitas', $record->aktivitas);

        $mform->addElement('textarea', 'catatan', 'Catatan', [
            'rows' => 3,
            'cols' => 80
        ]);
        $mform->setType('catatan', PARAM_RAW);
        $mform->setDefault('catatan', $record->catatan);

        /*
        |--------------------------------------------------------------------------
        | Absensi peserta
        |--------------------------------------------------------------------------
        */

        $peserta = ekstra_get_peserta($record->ekstraid);

        $absensi_lama = $DB->get_records(
            'local_ekstra_absen',
            ['jurnalid' => $record->id]
        );

        $statusmap = [];

        foreach ($absensi_lama as $a) {
            $statusmap[$a->userid] = $a->status;
        }

        $html = '<hr>';
        $html .= '<h4>Absensi Peserta</h4>';

        foreach ($peserta as $p) {

            $nama = trim($p->firstname . ' ' . $p->lastname);

            $selected = $statusmap[$p->userid] ?? 'Hadir';

            $html .= '
            <div style="margin-bottom:6px;">
                <strong>' . s($nama) . '</strong>

                <label style="margin-left:10px;">
                    <input type="radio"
                           name="status['.$p->userid.']"
                           value="Hadir"
                           '.($selected == 'Hadir' ? 'checked' : '').'>
                    Hadir
                </label>
                <label style="margin-left:10px;">
                    <input type="radio"
                           name="status['.$p->userid.']"
                           value="Sakit"
                           '.($selected == 'Sakit' ? 'checked' : '').'>
                    Sakit
               </label>

                <label style="margin-left:10px;">
                    <input type="radio"
                           name="status['.$p->userid.']"
                           value="Ijin"
                           '.($selected == 'Ijin' ? 'checked' : '').'>
                    Ijin
                </label>

                <label style="margin-left:10px;">
                    <input type="radio"
                           name="status['.$p->userid.']"
                           value="Alpa"
                           '.($selected == 'Alpa' ? 'checked' : '').'>
                    Alpa
                </label>
                <label style="margin-left:10px;">
		    <input type="radio"
		           name="status['.$p->userid.']"
		           value="Dispensasi"
		           '.($selected == 'Dispensasi' ? 'checked' : '').'>
		    Dispensasi
		</label>
            </div>';
        }

        $mform->addElement('static', 'absensihtml', '', $html);

        $mform->addElement('hidden', 'id', $record->id);
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(true, '💾 Update');
    }
}

$mform = new ekstra_edit_form(null, $record);

if ($data = $mform->get_data()) {

    $update = new stdClass();
    $update->id = $record->id;
    $update->ekstraid = $data->ekstraid;
    $update->tanggal = $data->tanggal;
    $update->materi = $data->materi;
    $update->aktivitas = $data->aktivitas;
    $update->catatan = $data->catatan;

    $DB->update_record('local_ekstra_jurnal', $update);

    /*
    |--------------------------------------------------------------------------
    | Hapus absensi lama
    |--------------------------------------------------------------------------
    */

    $DB->delete_records('local_ekstra_absen', [
        'jurnalid' => $record->id
    ]);

    /*
    |--------------------------------------------------------------------------
    | Simpan absensi baru
    |--------------------------------------------------------------------------
    */

    $statuslist = optional_param_array(
        'status',
        [],
        PARAM_TEXT
    );

    foreach ($statuslist as $userid => $status) {

        $absen = new stdClass();
        $absen->jurnalid = $record->id;
        $absen->userid = $userid;
        $absen->status = $status;

        $DB->insert_record('local_ekstra_absen', $absen);
    }

    redirect(
        new moodle_url('/local/jurnalmengajar/ekstra_riwayat.php'),
        'Jurnal berhasil diperbarui'
    );
}

echo $OUTPUT->header();

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/ekstra_riwayat.php'),
    '⬅️ Kembali',
    ['class' => 'btn btn-secondary mb-3']
);

$mform->display();

echo $OUTPUT->footer();
