<?php
namespace local_jurnalmengajar\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class jurnal_form extends \moodleform {
    public function definition() {
        global $DB;
        $mform = $this->_form;

        // Mode form: input atau edit
        $mode = $this->_customdata['mode'] ?? 'input';

        // Hidden ID
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Kelas
        $cohorts = $DB->get_records_menu('cohort', null, 'name ASC', 'id, name');
        $kelas_options = ['' => '-- Pilih Kelas --'] + $cohorts;

        $mform->addElement('select', 'kelas', 'Kelas', $kelas_options);
        $mform->addRule('kelas', 'Silakan pilih kelas', 'required');
        $mform->setType('kelas', PARAM_TEXT);

        // Jam ke
        $mform->addElement('text', 'jamke', 'Jam Pelajaran Ke');
        $mform->setType('jamke', PARAM_TEXT);
        $mform->addRule('jamke', 'Isian hanya boleh angka dan koma (misal: 2,3)', 'regex', '/^\d+(,\d+)*$/', 'client');
        $mform->addRule('jamke', null, 'required', null, 'client');

        // 🔥 Tanggal hanya untuk admin (mode edit)
        if ($mode === 'edit') {
            $mform->addElement('date_time_selector', 'tanggaldibuat', 'Tanggal & Jam Jurnal');
            $mform->setType('tanggaldibuat', PARAM_INT);
        }

        // Mata Pelajaran dari setting plugin
$mapel_setting = get_config('local_jurnalmengajar', 'mapel_list');

$mapel_options = ['' => '- Pilih Mata Pelajaran -'];

if (!empty($mapel_setting)) {
    $mapel_array = explode(',', $mapel_setting);
} else {
    $mapel_array = ['Fisika', 'Matematika', 'Bahasa Indonesia'];
}

foreach ($mapel_array as $mapel) {
    $mapel = trim($mapel);
    if ($mapel !== '') {
        $mapel_options[$mapel] = $mapel;
    }
}

$mform->addElement('select', 'matapelajaran', 'Mata Pelajaran', $mapel_options);
$mform->setType('matapelajaran', PARAM_TEXT);
$mform->addRule('matapelajaran', null, 'required');

        // Materi
        $mform->addElement('textarea', 'materi', 'Materi', 'rows="3" cols="60"');
        $mform->setType('materi', PARAM_RAW);
        $mform->addRule('materi', null, 'required');

        // Aktivitas
        $mform->addElement('textarea', 'aktivitas', 'Aktivitas KBM', 'rows="3" cols="60"');
        $mform->setType('aktivitas', PARAM_RAW);
        $mform->addRule('aktivitas', null, 'required');

        // Absen
        $mform->addElement('html', '<div id="absen-area"><em>Silakan pilih kelas...</em></div>');
        $mform->addElement('textarea', 'absen', 'Murid Tidak Hadir', 'wrap="virtual" rows="2" cols="50" readonly');
        $mform->setType('absen', PARAM_RAW);

        // Keterangan
        $mform->addElement('textarea', 'keterangan', 'Keterangan Tambahan', 'rows="2" cols="60"');
        $mform->setType('keterangan', PARAM_RAW);

        $this->add_action_buttons(true, 'Simpan Jurnal');

        // Script AJAX
        $mform->addElement('html', <<<HTML
<script>
require(['jquery'], function($) {
    function updateAbsenField() {
        const data = {};
        $('.absen-item input[type=checkbox]').each(function() {
            if (this.checked) {
                const nama = $(this).data('nama');
                const alasan = $(this).closest('.absen-item').find('select').val();
                if (alasan) data[nama] = alasan;
            }
        });
        $('textarea[name=absen]').val(JSON.stringify(data));
    }

    function loadSiswa(kelas) {
        if (!kelas) return;
        $.get('/local/jurnalmengajar/get_students.php', {kelas: kelas}, function(html) {
            $('#absen-area').html(html);

            $('.absen-checkbox').on('change', function() {
                const alasan = $(this).closest('.absen-item').find('select');
                alasan.prop('disabled', !this.checked);
                updateAbsenField();
            });

            $('.absen-alasan').on('change', updateAbsenField);
        });
    }

    $(document).ready(function() {
        $('select[name=kelas]').change(function() {
            loadSiswa($(this).val());
        });
        loadSiswa($('select[name=kelas]').val());
    });
});
</script>
HTML);
    }
}
