<?php
namespace local_jurnalmengajar\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class nilai_form extends \moodleform {
    public function definition() {
        global $DB;

        $mform = $this->_form;

        // ====== Sumber daftar mapel ======
        $mapelraw = \get_config('local_jurnalmengajar', 'mapel_list');
        $mapels = [];
        if (!empty($mapelraw)) {
            foreach (explode(',', $mapelraw) as $m) {
                $m = trim($m);
                if ($m !== '') { $mapels[$m] = $m; }
            }
        }
        if (empty($mapels)) {
            $defaults = ['Fisika','Matematika','Kimia','Biologi','Bahasa Indonesia','Bahasa Inggris','PPKN','Sejarah'];
            foreach ($defaults as $d) { $mapels[$d] = $d; }
        }

        // ====== Daftar cohort: pakai NAME saja, dedup by name ======
        $cohorts = [];
        $seen = [];
        $rs = $DB->get_records('cohort', null, 'name ASC', 'id,name');
        foreach ($rs as $c) {
            if (isset($seen[$c->name])) { continue; }
            $cohorts[$c->id] = $c->name;
            $seen[$c->name] = true;
        }

        // ====== Header form ======
        $mform->addElement('header', 'hdr', \get_string('inputnilaiharian', 'local_jurnalmengajar'));

$record = $this->_customdata['record'] ?? null;

$mform->addElement('hidden', 'id');
$mform->setType('id', PARAM_INT);

if ($record) {
    $mform->setDefault('id', $record->id);
}

        // ====== Mata Pelajaran — dengan placeholder ======
        $mapelops = ['' => '- Pilih Mata Pelajaran -'] + $mapels;
        $mform->addElement('select', 'mapel', \get_string('matapelajaran', 'local_jurnalmengajar'), $mapelops);
        $mform->setType('mapel', PARAM_TEXT);
        $mform->setDefault('mapel', '');
        $mform->addRule('mapel', \get_string('required'), 'required', null, 'client');

// ====== Judul Penilaian ======
$mform->addElement(
    'text',
    'judul',
    'Nama Penilaian',
    ['size' => 60]
);
$mform->setType('judul', PARAM_TEXT);
$mform->setDefault('judul', '');
$mform->addRule(
    'judul',
    \get_string('required'),
    'required',
    null,
    'client'
);

        // ====== Kelas (cohort) — placeholder + auto-submit ======
        $cohortops = ['' => '-- Pilih Kelas --'] + $cohorts;
        $mform->addElement('select', 'cohortid', \get_string('kelas', 'local_jurnalmengajar'), $cohortops);
        $mform->setType('cohortid', PARAM_INT);
        $mform->setDefault('cohortid', '');
        $mform->addRule('cohortid', \get_string('required'), 'required', null, 'client');
        $mform->getElement('cohortid')->updateAttributes(['onchange' => 'this.form.submit();']);

        // ====== Hari/Tanggal (default: hari ini) ======
        $mform->addElement('date_selector', 'tanggal', 'Hari/Tanggal', [
            'startyear' => date('Y') - 1,
            'stopyear'  => date('Y') + 1
        ]);
        $mform->setDefault('tanggal', time());

        // Placeholder tabel siswa + nilai akan di-render di definition_after_data().
        $mform->addElement('html', \html_writer::div('', 'nilai-table-placeholder'));

        // Placeholder untuk pesan error "minimal satu nilai"
        $mform->addElement('static', 'nilaihint', '', '');

        // Tombol
        $this->add_action_buttons(true, 'Simpan Nilai');
    }

public function definition_after_data() {
    global $DB;

    $mform = $this->_form;

    $data = $this->get_data();

$record = $this->_customdata['record'] ?? null;


    // Ambil cohort yang dipilih.
    $cohortid = \optional_param('cohortid', 0, PARAM_INT);

if (empty($cohortid)) {

    if (!empty($data) && !empty($data->cohortid)) {

        $cohortid = $data->cohortid;

    } else if ($record && !empty($record->cohortid)) {

        $cohortid = $record->cohortid;

    }

}
    if ($cohortid) {

        // Ambil anggota cohort
        $members = $DB->get_records_sql("
            SELECT
                u.id,
                u.firstname,
                u.lastname
            FROM {cohort_members} cm
            JOIN {user} u
                ON u.id = cm.userid
            WHERE cm.cohortid = :cid
            ORDER BY
                u.lastname,
                u.firstname
        ", ['cid' => $cohortid]);
        $nilaimap = [];

if ($record && !empty($record->nilaijson)) {

    $items = json_decode($record->nilaijson, true);

    if (is_array($items)) {

        foreach ($items as $item) {

if (isset($item['userid'], $item['nilai'])) {
    $nilaimap[(int)$item['userid']] = $item['nilai'];
}

        }

    }

}
            // Render tabel input nilai
            $html  = '<div class="table-responsive">';
            $html .= '<table class="generaltable">';
            $html .= '<thead><tr><th style="width:60px;">No</th><th>Nama Murid</th><th style="width:160px;">Nilai</th></tr></thead><tbody>';

            $no = 1;
            foreach ($members as $u) {
                $lastname = ucwords(strtolower($u->lastname)); // lastname Proper Case
                $html .= '<tr>';
                $html .= '<td>' . $no++ . '</td>';
                $html .= '<td>' . \s($lastname) . '</td>';
                $value = '';

if (isset($nilaimap[$u->id])) {
    $value = (int)$nilaimap[$u->id];
}

$html .= '<td><input type="number"
    name="nilai['.$u->id.']"
    value="'.$value.'"
    step="1"
    min="0"
    max="100"
    style="width:140px;" /></td>';
                $html .= '</tr>';
            }

            if ($no === 1) {
                $html .= '<tr><td colspan="3"><em>Tidak ada anggota cohort.</em></td></tr>';
            }

            $html .= '</tbody></table></div>';

            // Sisipkan HTML tabel ke form (sebelum tombol).
            $mform->insertElementBefore($mform->createElement('html', $html), 'buttonar');
        } else {
            $mform->insertElementBefore(
                $mform->createElement('html', \html_writer::div(
                    'Pilih Kelas terlebih dahulu, untuk menampilkan daftar murid.',
                    'alert alert-info'
                )),
                'buttonar'
            );
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Hanya cek ketika tombol Simpan ditekan.
        $pressed = \optional_param('submitbutton', null, PARAM_RAW);
        if ($pressed !== null && !empty($data['cohortid'])) {
            // Ambil nilai langsung dari POST, karena bukan elemen QuickForm.
            $nilai = \optional_param_array('nilai', [], PARAM_RAW);
            $hasvalue = false;
            foreach ($nilai as $v) {
                if ($v !== '' && $v !== null) { $hasvalue = true; break; }
            }
            if (!$hasvalue) {
                $errors['nilaihint'] = 'Isi minimal satu nilai.';
            }
        }

        return $errors;
    }
}
