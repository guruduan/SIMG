<?php
require('../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

global $DB, $PAGE, $OUTPUT, $USER;

$PAGE->set_context($context);
$PAGE->set_url(
    new moodle_url('/local/jurnalmengajar/statusakademik.php')
);
$PAGE->set_title('Status Akademik');

$tahunajaran = get_config(
    'local_jurnalmengajar',
    'tahun_ajaran'
);

/*
=====================================================
PARAMETER
=====================================================
*/

$userid = optional_param('userid', 0, PARAM_INT);
$edit  = optional_param('edit', 0, PARAM_INT);
$hapus = optional_param('hapus', 0, PARAM_INT);

/*
=====================================================
SIMPAN DATA
=====================================================
*/

if (optional_param('simpan', 0, PARAM_BOOL)) {

    require_sesskey();

    $userid      = required_param('userid', PARAM_INT);
    $jenis       = required_param('jenis', PARAM_ALPHA);
    $jenisvalid = [
    'masukkelas',
    'pindahkelas',
    'mutasi',
    'berhenti',
    'lulus'
];

if (!in_array($jenis, $jenisvalid, true)) {
    throw new moodle_exception('Jenis riwayat tidak valid.');
}
    $tanggaltext = required_param('tanggal', PARAM_TEXT);
    $keterangan  = optional_param('keterangan', '', PARAM_TEXT);

    if (empty($tanggaltext)) {
        throw new moodle_exception('Tanggal harus diisi.');
    }

    $tanggal = strtotime($tanggaltext);

    if ($tanggal === false) {
        throw new moodle_exception('Format tanggal tidak valid.');
    }

    $record = new stdClass();
    $record->userid       = $userid;
    $record->tahunajaran  = $tahunajaran;
    $record->jenis        = $jenis;
    $record->tanggal      = $tanggal;
    $record->keterangan   = trim($keterangan);
    $record->useridinput  = $USER->id;
    $record->timecreated  = time();
    $record->timemodified = time();

    $DB->insert_record(
        'local_jurnalmengajar_riwayatakademik',
        $record
    );

    redirect(
        new moodle_url(
            '/local/jurnalmengajar/statusakademik.php',
            ['userid' => $userid]
        ),
        'Riwayat akademik berhasil disimpan.',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

/*
=====================================================
HAPUS DATA
=====================================================
*/

if ($hapus) {

    require_sesskey();

    $riwayat = $DB->get_record(
        'local_jurnalmengajar_riwayatakademik',
        ['id' => $hapus],
        '*',
        MUST_EXIST
    );

    $DB->delete_records(
        'local_jurnalmengajar_riwayatakademik',
        ['id' => $hapus]
    );

    redirect(
        new moodle_url(
            '/local/jurnalmengajar/statusakademik.php',
            ['userid' => $riwayat->userid]
        ),
        'Riwayat akademik berhasil dihapus.',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$PAGE->set_heading('Status Akademik');

echo $OUTPUT->header();
echo $OUTPUT->heading('🎓 Status Akademik');

/*
=====================================================
JIKA DIPANGGIL DARI RIWAYAT INDIVIDU
=====================================================
*/

if ($userid) {

    $murid = $DB->get_record(
        'user',
        ['id' => $userid],
        '*',
        MUST_EXIST
    );

    echo html_writer::tag(
        'h4',
        format_nama_siswa($murid->lastname)
    );

$field = $DB->get_record(
    'user_info_field',
    ['shortname' => 'nis']
);

$nis = '';

if ($field) {
    $nis = $DB->get_field(
        'user_info_data',
        'data',
        [
            'userid'  => $userid,
            'fieldid' => $field->id
        ]
    );
}

echo html_writer::tag(
    'div',
    '<strong>NIS :</strong> ' . s($nis),
    ['class' => 'text-muted']
);

}

echo html_writer::tag(
    'div',
    '<strong>Tahun Ajaran :</strong> ' .
    format_string($tahunajaran),
    ['class'=>'mb-3']
);

/*
=====================================================
FORM
=====================================================
*/

echo html_writer::start_div('card shadow-sm');

echo html_writer::start_div(
    'card-header bg-success text-white'
);

echo html_writer::tag(
    'strong',
    'Tambah Riwayat Akademik'
);

echo html_writer::end_div();

echo html_writer::start_div('card-body');

echo html_writer::start_tag('form', [
    'method' => 'post'
]);

echo html_writer::empty_tag('input', [
    'type'  => 'hidden',
    'name'  => 'userid',
    'value' => $userid
]);

echo html_writer::empty_tag('input', [
    'type'  => 'hidden',
    'name'  => 'sesskey',
    'value' => sesskey()
]);

echo html_writer::empty_tag('input', [
    'type'  => 'hidden',
    'name'  => 'simpan',
    'value' => 1
]);

echo html_writer::tag(
    'label',
    'Jenis Riwayat'
);

$jenis = [
    'masukkelas'  => 'Masuk Kelas',
    'pindahkelas' => 'Pindah Kelas',
    'mutasi'      => 'Mutasi',
    'berhenti'    => 'Berhenti',
    'lulus'       => 'Lulus'
];

echo html_writer::select(
    $jenis,
    'jenis',
    '',
    ['' => '-- Pilih Jenis Riwayat --'],
    [
        'class' => 'form-control mb-3'
    ]
);

echo html_writer::tag(
    'label',
    'Tanggal'
);

echo html_writer::empty_tag('input', [
    'type'  => 'date',
    'name'  => 'tanggal',
    'value' => date('Y-m-d'),
    'class' => 'form-control mb-3'
]);

echo html_writer::tag(
    'label',
    'Keterangan (opsional)'
);

echo html_writer::tag(
    'textarea',
    '',
    [
        'name'  => 'keterangan',
        'rows'  => 3,
        'class' => 'form-control mb-3'
    ]
);

// tombol
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => '💾 Simpan',
    'class' => 'btn btn-success'
]);

echo html_writer::end_tag('form');

echo html_writer::end_div();

echo html_writer::end_div();

/*
=====================================================
DAFTAR RIWAYAT AKADEMIK
=====================================================
*/

if ($userid) {

    $riwayat = $DB->get_records(
        'local_jurnalmengajar_riwayatakademik',
        ['userid' => $userid],
        'tanggal DESC'
    );

    echo html_writer::start_div('card shadow-sm mt-4');

    echo html_writer::start_div(
        'card-header bg-primary text-white'
    );

    echo html_writer::tag(
        'strong',
        'Riwayat Akademik'
    );

    echo html_writer::end_div();

    echo html_writer::start_div('card-body');

    if ($riwayat) {

        foreach ($riwayat as $r) {

            switch ($r->jenis) {

                case 'masukkelas':
                    $teks = 'Masuk kelas <strong>' .
                        format_string($r->keterangan) .
                        '</strong>';
                    break;

case 'pindahkelas':

    $kelas = explode('|', $r->keterangan);

    if (count($kelas) == 2) {

        $teks =
            'Pindah kelas <strong>' .
            format_string($kelas[0]) .
            '</strong> → <strong>' .
            format_string($kelas[1]) .
            '</strong>';

    } else {

        $teks =
            'Pindah kelas <strong>' .
            format_string($r->keterangan) .
            '</strong>';
    }

    break;

                case 'mutasi':
                    $teks = 'Mutasi ke <strong>' .
                        format_string($r->keterangan) .
                        '</strong>';
                    break;

                case 'berhenti':
                    $teks = 'Berhenti';

                    if (!empty($r->keterangan)) {
                        $teks .= '<br><small>' .
                            format_string($r->keterangan) .
                            '</small>';
                    }
                    break;

case 'lulus':

    $teks = 'Lulus';

    if (!empty($r->tahunajaran)) {

        $teks .=
            '<br><small>Tahun Ajaran ' .
            format_string($r->tahunajaran) .
            '</small>';
    }

    break;

                default:
                    $teks = format_string($r->jenis);
            }

            echo html_writer::start_div('mb-3');

            echo html_writer::tag(
                'div',
                '<strong>' .
                tanggal_indo($r->tanggal) .
                '</strong>'
            );

            echo html_writer::tag(
                'div',
                $teks,
                ['style' => 'margin-left:20px']
            );

            echo html_writer::tag(
                'div',
                html_writer::link(
                    new moodle_url(
                        '/local/jurnalmengajar/statusakademik.php',
                        [
                            'edit' => $r->id,
                            'userid' => $userid
                        ]
                    ),
                    '✏ Edit'
                )
                .
                ' | ' .
                html_writer::link(
    new moodle_url(
        '/local/jurnalmengajar/statusakademik.php',
        [
            'hapus' => $r->id,
            'userid' => $userid,
            'sesskey' => sesskey()
        ]
    ),
    '🗑 Hapus',
    [
        'onclick' =>
            "return confirm('Hapus riwayat akademik ini?')"
    ]
),
                ['class' => 'text-muted small']
            );

            echo html_writer::empty_tag('hr');

            echo html_writer::end_div();

        }

    } else {

        echo html_writer::tag(
            'div',
            '<i>Belum ada riwayat akademik.</i>',
            ['class' => 'text-muted']
        );

    }

    echo html_writer::end_div();

    echo html_writer::end_div();
}

echo $OUTPUT->footer();
