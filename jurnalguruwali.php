<?php
require('../../config.php');
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/local/jurnalmengajar/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/jurnalguruwali.php'));
$PAGE->set_title('Jurnal Guru Wali');
$PAGE->set_heading('Jurnal Guru Wali');

global $DB, $USER, $OUTPUT, $PAGE;

// Tangkap parameter edit jika ada
$editid = optional_param('editid', 0, PARAM_INT);

/* ======================= HELPERS ======================= */

function jw_load_binaan_csv(): array {
    global $CFG;
    $csvpath = $CFG->dataroot . '/binaan.csv';
    if (!file_exists($csvpath)) return [];

    $content = file_get_contents($csvpath);
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

    $lines = preg_split("/\r\n|\n|\r/", trim($content));
    if (count($lines) < 2) return [];

    $delimiter = (substr_count($lines[0], ';') > substr_count($lines[0], ',')) ? ';' : ',';

    $header = str_getcsv(array_shift($lines), $delimiter);

    $idx = [
        'userid' => array_search('userid', $header),
        'nis'    => array_search('nis', $header),
        'murid'  => array_search('murid', $header),
        'kelas'  => array_search('kelas', $header),
    ];

    $rows = [];
    foreach ($lines as $line) {
        $r = str_getcsv($line, $delimiter);
        if (count($r) < max($idx)) continue;
        $rows[] = [
            'guruid' => (int)$r[$idx['userid']],
            'nis'    => $r[$idx['nis']],
            'murid'  => $r[$idx['murid']],
            'kelas'  => $r[$idx['kelas']],
        ];
    }
    return $rows;
}

function jw_find_user_by_nis($nis) {
    global $DB;
    return $DB->get_field_sql("
        SELECT u.id FROM {user} u
        JOIN {user_info_data} d ON d.userid=u.id
        JOIN {user_info_field} f ON f.id=d.fieldid
        WHERE f.shortname='nis' AND d.data=?
    ", [$nis]);
}

function jw_get_murid_options_from_csv($guruid): array {
    global $DB;

    $rows = jw_load_binaan_csv();
    $ids = [];

    foreach ($rows as $r) {
        if ($r['guruid'] != $guruid) continue;

        $id = jw_find_user_by_nis($r['nis']);
        if ($id) $ids[$id] = true;
    }

    if (!$ids) return [];

    list($in, $params) = $DB->get_in_or_equal(array_keys($ids));

    $users = $DB->get_records_select('user', "id $in", $params, 'lastname ASC', 'id,firstname,lastname');

    $opts = [];
    foreach ($users as $u) {
        $opts[$u->id] = trim($u->firstname.' '.$u->lastname);
    }

    return $opts;
}

function jw_get_kelas_siswa($userid) {
    global $DB;

    return $DB->get_field_sql("
        SELECT c.name
        FROM {cohort} c
        JOIN {cohort_members} cm ON cm.cohortid=c.id
        WHERE cm.userid=?
        ORDER BY c.name ASC
    ", [$userid]);
}

/* ======================= FORM ======================= */

class jw_form extends moodleform {
    public function definition() {
        $m = $this->_form;

        // Kirim ID edit lewat form jika sedang mode edit
        $m->addElement('hidden', 'editid', 0);
        $m->setType('editid', PARAM_INT);

        $now = time();
        $m->addElement('static', 'waktu', 'Waktu', tanggal_indo($now));

        // Dapatkan data pilihan murid
        $muridopts = $this->_customdata['murid_options'];

        if (!empty($muridopts)) {
            $m->addElement('html', '<div class="form-group row">');
            $m->addElement('html', '<div class="col-md-3 text-md-right font-weight-bold"><label>Pilih Murid</label></div>');
            $m->addElement('html', '<div class="col-md-9">');
            $m->addElement('html', '<div class="row">');

            // Bagi menjadi 2 kolom seimbang
            $chunks = array_chunk($muridopts, ceil(count($muridopts) / 2), true);
            $no = 1;

            foreach ($chunks as $chunk) {
                $m->addElement('html', '<div class="col-md-6 d-flex flex-column" style="gap: 6px;">');
                foreach ($chunk as $id => $name) {
                    $m->addElement('html', '<div class="custom-control custom-checkbox text-left">');
                    $m->addElement('html', '<input type="checkbox" class="custom-control-input" id="murid_'.$id.'" name="muridids[]" value="'.$id.'">');
                    $m->addElement('html', '<label class="custom-control-label font-weight-normal" for="murid_'.$id.'"><span class="text-muted mr-1">'.$no++.'.</span> '.$name.'</label>');
                    $m->addElement('html', '</div>');
                }
                $m->addElement('html', '</div>'); // Tutup col-md-6
            }

            $m->addElement('html', '</div>'); // Tutup row dalam
            $m->addElement('html', '</div>'); // Tutup col-md-9
            $m->addElement('html', '</div>'); // Tutup form-group row
        } else {
            $m->addElement('static', 'emptymurid', 'Pilih Murid', '<span class="text-danger">Tidak ada data murid binaan ditemukan di binaan.csv</span>');
        }

// Hapus class CSS manual dan gunakan atribut standar form Moodle
        $m->addElement('text', 'topik', 'Topik', ['size' => 80]);
        $m->setType('topik', PARAM_TEXT);
        $m->addRule('topik', 'Topik tidak boleh kosong', 'required', null, 'client');

        $m->addElement('textarea', 'tindaklanjut', 'Tindak Lanjut', ['rows' => 3, 'cols' => 80]);
        $m->setType('tindaklanjut', PARAM_TEXT);

        $m->addElement('textarea', 'keterangan', 'Keterangan', ['rows' => 3, 'cols' => 80]);
        $m->setType('keterangan', PARAM_TEXT);

        $this->add_action_buttons(true, 'Simpan Data');
    }
}

/* ======================= PROCESS CRUD ======================= */

$murid_options = jw_get_murid_options_from_csv($USER->id);
$mform = new jw_form(null, ['murid_options' => $murid_options]);

// Set default data jika dalam mode EDIT
if ($editid > 0) {
    $existing = $DB->get_record('local_jurnalguruwali', ['id' => $editid, 'guruid' => $USER->id]);
    if ($existing) {
        $mform->set_data([
            'editid' => $existing->id,
            'topik' => $existing->topik,
            'tindaklanjut' => $existing->tindaklanjut,
            'keterangan' => $existing->keterangan,
        ]);
        // Script inject sederhana untuk otomatis mencentang murid yang sedang diedit
        $PAGE->requires->js_init_code("
            var checkbox = document.getElementById('murid_".$existing->userid."');
            if(checkbox) checkbox.checked = true;
        ");
    }
}

if ($data = $mform->get_data()) {
    require_sesskey();
    $muridids = optional_param_array('muridids', [], PARAM_INT);

    if (empty($muridids)) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification('Pilih minimal satu murid.', 'error');
        $mform->display();
        echo $OUTPUT->footer();
        exit;
    }

    $now = time();

    // Jika bernilai editid > 0, kita perbarui record tunggal saja
    if ($data->editid > 0) {
        foreach ($muridids as $muridid) { // Umumnya hanya 1 yang dicentang saat edit
            $kelas = jw_get_kelas_siswa($muridid);
            $update = new stdClass();
            $update->id = $data->editid;
            $update->userid = $muridid;
            $update->kelas = $kelas;
            $update->topik = $data->topik;
            $update->tindaklanjut = $data->tindaklanjut;
            $update->keterangan = $data->keterangan;
            
            $DB->update_record('local_jurnalguruwali', $update);
        }
        redirect($PAGE->url, 'Data berhasil diperbarui', null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        // Mode Insert Baru (Bisa banyak murid sekaligus)
        foreach ($muridids as $muridid) {
            $murid = $DB->get_record('user', ['id' => $muridid], 'lastname');
            $kelas = jw_get_kelas_siswa($muridid);
            
            $record = new stdClass();
            $record->guruid = $USER->id;
            $record->userid = $muridid;
            $record->kelas = $kelas;
            $record->topik = $data->topik;
            $record->tindaklanjut = $data->tindaklanjut;
            $record->keterangan = $data->keterangan;
            $record->timecreated = $now;

            $DB->insert_record('local_jurnalguruwali', $record);

            // ===== KIRIM NOTIFIKASI WA =====
            $pesan = "*📋 Jurnal Guru Wali*\n\n"
                   . "📅 Waktu: ".tanggal_indo($now)."\n"
                   . "👤 Murid: ".format_nama_siswa($murid->lastname)."\n"
                   . "🏫 Kelas: ".$kelas."\n"
                   . "🧩 Topik: ".$data->topik."\n"
                   . "💡 Tindak lanjut: ".$data->tindaklanjut."\n"
                   . "📝 Keterangan: ".$data->keterangan."\n"
                   . "👨‍🏫 Guru Wali: ".$USER->lastname;

            $tujuan = [get_nomor_wali_kelas($kelas)];
            jurnalmengajar_kirim_wa($tujuan, $pesan);
        }
        redirect($PAGE->url, 'Data berhasil disimpan', null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

/* ======================= TAMPILAN PAGE ======================= */

echo $OUTPUT->header();

// Tampilkan Form Input/Edit
echo html_writer::start_div('card mb-4 shadow-sm');
echo html_writer::div($editid > 0 ? '✏️ Edit Jurnal Guru Wali' : '📝 Input Jurnal Guru Wali', 'card-header bg-light font-weight-bold');
echo html_writer::start_div('card-body');
$mform->display();
echo html_writer::end_div();
echo html_writer::end_div();

// Bagian Tabel Riwayat
echo html_writer::tag('h3', '📋 Riwayat Jurnal Terakhir', ['class' => 'mt-4 mb-3']);

$rows = $DB->get_records_sql("
    SELECT j.*, u.lastname
    FROM {local_jurnalguruwali} j
    JOIN {user} u ON u.id=j.userid
    WHERE j.guruid=?
    ORDER BY j.timecreated DESC
", [$USER->id], 0, 10);

$table = new html_table();
$table->attributes['class'] = 'table table-bordered table-striped table-hover generic_table';
$table->head = ['No', 'Waktu', 'Murid', 'Kelas', 'Topik', 'Tindak Lanjut', 'Keterangan', 'Aksi'];

$no = 1;
foreach ($rows as $r) {
    $editurl = new moodle_url('/local/jurnalmengajar/jurnalguruwali.php', [
        'editid' => $r->id
    ]);

    $aksi = html_writer::link($editurl, '✏️ Edit', ['class' => 'btn btn-sm btn-outline-primary']);

    $table->data[] = [
        $no++,
        tanggal_indo($r->timecreated),
        html_writer::tag('strong', s(format_nama_siswa($r->lastname))),
        s($r->kelas),
        s($r->topik),
        s($r->tindaklanjut),
        s($r->keterangan),
        $aksi
    ];
}

if (empty($rows)) {
    echo $OUTPUT->notification('Belum ada riwayat jurnal yang diisi.', 'info');
} else {
    echo html_writer::table($table);
}

// Tombol Navigasi Bawah (Ekspor dan Kembali)
echo html_writer::start_div('d-flex justify-content-between align-items-center mt-4');

// Tombol Kembali
echo html_writer::link(
    '#',
    '⬅ Kembali',
    [
        'class' => 'btn btn-secondary',
        'onclick' => 'history.back(); return false;',
        'title' => 'Kembali ke halaman sebelumnya'
    ]
);

// Tombol Ekspor
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => (new moodle_url('/local/jurnalmengajar/exportguruwali_form.php'))->out(false),
    'class'  => 'm-0'
]);
echo html_writer::tag('button', '💾 Ekspor Jurnal per Bulan', [
    'type'  => 'submit',
    'class' => 'btn btn-success font-weight-bold'
], false);
echo html_writer::end_tag('form');

echo html_writer::end_div();

echo $OUTPUT->footer();
