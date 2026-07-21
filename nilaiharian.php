<?php
// =====================================================
// File : nilaiharian.php
// =====================================================

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/form/nilai_form.php');

require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/lib_notifikasi.php');

require_login();

use local_jurnalmengajar\form\nilai_form;

$context = context_system::instance();

require_capability(
    'local/jurnalmengajar:submit',
    $context
);

$PAGE->set_context($context);

global $DB, $USER, $OUTPUT;

$id = optional_param('id', 0, PARAM_INT);

$urlparams = [];

if ($id) {
    $urlparams['id'] = $id;
}

$PAGE->set_url(
    new moodle_url(
        '/local/jurnalmengajar/nilaiharian.php',
        $urlparams
    )
);


$PAGE->set_pagelayout('standard');

if ($id) {

    $PAGE->set_title('Edit Nilai Harian');
    $PAGE->set_heading('Edit Nilai Harian');

} else {

    $PAGE->set_title('Input Nilai Harian');
    $PAGE->set_heading('Input Nilai Harian');

}


$record = null;

if ($id) {

    $record = $DB->get_record(
        'local_jm_nilaiharian',
        ['id' => $id],
        '*',
        MUST_EXIST
    );

    if ((int)$record->userid !== (int)$USER->id) {
        throw new moodle_exception(
            'nopermissions',
            'error',
            '',
            'Anda hanya dapat mengubah nilai yang Anda input sendiri.'
        );
    }
}


/* ======================================================
   HELPER
====================================================== */

/**
 * Ambil nama kelas dari cohort
 */
function jm_get_cohort_label($cohortid) {

    global $DB;

    $cohort = $DB->get_record(
        'cohort',
        [
            'id' => $cohortid
        ]
    );

    if (!$cohort) {

        return [
            'kelas' => '',
            'name'  => ''
        ];
    }

    return [

        'kelas' => $cohort->name,

        'name'  => $cohort->name

    ];
}


/**
 * Format nama siswa
 */
function jm_format_nama($nama) {

    return ucwords(
        strtolower(
            trim($nama)
        )
    );
}


/* ======================================================
   FORM
====================================================== */

$mform = new nilai_form(null, [
    'record' => $record
]);

if ($record) {

    $record->tanggal = strtotime($record->tanggal);

    $mform->set_data($record);
}

if ($mform->is_cancelled()) {

    redirect(
        new moodle_url(
            '/local/jurnalmengajar/index.php'
        )
    );

}


/* ======================================================
   PROSES SIMPAN
====================================================== */

if ($data = $mform->get_data()) {

    $mapel = trim($data->mapel);

    $judul = trim($data->judul);

    $cohortid = (int)$data->cohortid;

    $kelasinfo = jm_get_cohort_label(
        $cohortid
    );

    $kelas = $kelasinfo['kelas'];

    $tanggal = date(
        'Y-m-d',
        $data->tanggal
    );

    /*
    =====================================
    Ambil anggota cohort
    =====================================
    */

    $members = $DB->get_records_sql(
        "
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
        ",
        [
            'cid' => $cohortid
        ]
    );

    /*
    =====================================
    Ambil nilai dari POST
    =====================================
    */

    $nilai = optional_param_array(
        'nilai',
        [],
        PARAM_INT
    );

    $rows = [];

    $no = 1;
    
    /*
    =====================================
    Susun data nilai
    =====================================
    */

    foreach ($members as $u) {

        $val = (
            isset($nilai[$u->id]) &&
            $nilai[$u->id] !== ''
        )
        ? max(
            0,
            min(
                100,
                (int)$nilai[$u->id]
            )
        )
        : null;

        // Hanya simpan nilai yang diisi.
        if ($val === null) {
            continue;
        }

        $nama = trim($u->lastname);

        if ($nama === '') {
            $nama = fullname($u);
        }

        $rows[] = (object)[

            'no'     => $no++,

            'userid' => (int)$u->id,

            'name'   => jm_format_nama($nama),

            'nilai'  => $val

        ];
    }

    /*
    =====================================
    Minimal ada satu nilai
    =====================================
    */

    if (empty($rows)) {

        \core\notification::warning(
            'Tidak ada nilai yang diisi.'
        );

        echo $OUTPUT->header();

        $mform->display();

        echo $OUTPUT->footer();

        exit;
    }

/*
=====================================
Simpan database
=====================================
*/

$savedata = new stdClass();

if (!$id) {
    $savedata->userid = $USER->id;
}

$savedata->mapel = $mapel;
$savedata->cohortid = $cohortid;
$savedata->kelas = $kelas;
$savedata->tanggal = $tanggal;
$savedata->judul = $judul;

$savedata->nilaijson = json_encode(
    array_values($rows),
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
);

if ($id) {

    $savedata->id = $id;
    $savedata->timemodified = time();

    $DB->execute(
        "UPDATE {local_jm_nilaiharian}
            SET
                mapel = ?,
                cohortid = ?,
                kelas = ?,
                tanggal = ?,
                judul = ?,
                nilaijson = ?,
                timemodified = ?
          WHERE id = ?",
        [
            $savedata->mapel,
            $savedata->cohortid,
            $savedata->kelas,
            $savedata->tanggal,
            $savedata->judul,
            $savedata->nilaijson,
            $savedata->timemodified,
            $savedata->id
        ]
    );

} else {

    $savedata->timecreated = time();
    $savedata->timemodified = time();

    $DB->insert_record(
        'local_jm_nilaiharian',
        $savedata
    );

}

/*
=====================================
Kirim Notifikasi WhatsApp
(hanya saat insert)
=====================================
*/

if (!$id) {

    // Siapkan daftar nilai untuk template.
    $daftarnilai = '';

    foreach ($rows as $r) {

        $daftarnilai .=
            $r->no .
            '. ' .
            $r->name .
            ' : ' .
            $r->nilai .
            "\n";

    }

    // Format daftar nilai.
    $daftarnilai = trim($daftarnilai);

    // Format tanggal.
    $tanggallabel = tanggal_indo(
        strtotime($tanggal),
        'judul'
    );

    // Data placeholder template.
    $template = [

        '{guru}'        => fullname($USER),

        '{mapel}'       => $mapel,

        '{kelas}'       => $kelas,

        '{judul}'       => $judul,

        '{tanggal}'     => $tanggallabel,

        '{daftarnilai}' => $daftarnilai,

        // Data internal.
        'userid' => $USER->id,

        // Dipakai tujuan_notifikasi untuk wali kelas.
        'kelas'  => $cohortid

    ];

    // Kirim notifikasi.
    jm_kirim_template_auto(
        'nilai_harian',
        $template
    );
}

    /*
    =====================================
    Selesai
    =====================================
    */

if ($id) {

    redirect(
        new moodle_url('/local/jurnalmengajar/rekapnilai.php'),
        'Nilai berhasil diperbarui.',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );

} else {

    redirect(
        new moodle_url('/local/jurnalmengajar/nilaiharian.php'),
        'Nilai harian berhasil disimpan.',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );

}

}

/*
=====================================
TAMPILKAN FORM
=====================================
*/

echo $OUTPUT->header();

$mform->display();

echo html_writer::link(
    '#',
    '⬅ Kembali',
    [
        'class'   => 'btn btn-secondary',
        'onclick' => 'history.back(); return false;'
    ]
);

echo $OUTPUT->footer();
