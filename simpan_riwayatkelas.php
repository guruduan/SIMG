<?php
require('../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/simpan_riwayatkelas.php'));
$PAGE->set_title('Simpan Riwayat Kelas');
$PAGE->set_heading('Simpan Riwayat Kelas');

global $DB, $OUTPUT;

echo $OUTPUT->header();
echo $OUTPUT->heading('📸 Snapshot Riwayat Kelas');

// Ambil tahun ajaran dari setting plugin.
$tahunajaran = get_config('local_jurnalmengajar', 'tahun_ajaran');

if (empty($tahunajaran)) {
    echo $OUTPUT->notification(
        'Tahun ajaran belum diatur pada pengaturan plugin.',
        'notifyproblem'
    );

    echo $OUTPUT->footer();
    exit;
}

if (optional_param('proses', 0, PARAM_INT)) {

    require_sesskey();

    $jumlah = 0;

    // Semua cohort = kelas siswa.
    $cohorts = $DB->get_records('cohort');

    foreach ($cohorts as $cohort) {

        $members = $DB->get_records(
            'cohort_members',
            ['cohortid' => $cohort->id]
        );

        foreach ($members as $member) {

            $exists = $DB->record_exists(
                'local_jurnalmengajar_riwayatkelas',
                [
                    'userid'      => $member->userid,
                    'cohortid'    => $cohort->id,
                    'tahunajaran' => $tahunajaran
                ]
            );

            if ($exists) {
                continue;
            }

            $record = new stdClass();
            $record->userid = $member->userid;
            $record->cohortid = $cohort->id;
            $record->tahunajaran = $tahunajaran;
            $record->timecreated = time();

            $DB->insert_record(
                'local_jurnalmengajar_riwayatkelas',
                $record
            );

            $jumlah++;
        }
    }

    echo $OUTPUT->notification(
        $jumlah . ' riwayat kelas berhasil disimpan.',
        'notifysuccess'
    );

} else {

    echo html_writer::div(
        'Tahun ajaran aktif: <b>' . s($tahunajaran) . '</b>',
        'alert alert-info'
    );

    echo html_writer::tag(
        'p',
        'Proses ini akan menyimpan hubungan siswa dan kelas untuk tahun ajaran aktif. Data yang sudah pernah disimpan tidak akan digandakan.'
    );

    $url = new moodle_url(
        '/local/jurnalmengajar/simpan_riwayatkelas.php',
        [
            'proses' => 1,
            'sesskey' => sesskey()
        ]
    );

    echo html_writer::link(
        $url,
        '📸 Buat Snapshot Riwayat Kelas',
        [
            'class' => 'btn btn-primary',
            'onclick' => "return confirm('Simpan riwayat kelas untuk tahun ajaran $tahunajaran ?')"
        ]
    );
}

echo $OUTPUT->footer();
