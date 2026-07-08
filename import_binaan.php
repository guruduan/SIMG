<?php
require('../../config.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/import_binaan.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Import Data Guru Wali');
$PAGE->set_heading('Import Data Guru Wali');

echo $OUTPUT->header();

echo '<h4>Download dahulu file format_binaan.csv</h4>';
echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/download_format_binaan.php'),
    'Download Format CSV',
    [
        'class' => 'btn btn-secondary',
        'style' => 'margin-bottom:15px;'
    ]
);

echo html_writer::tag(
    'p',
    'Setelah format diisi, silakan import</b>.
    Data yang sudah ada akan diperbarui jika guru walinya berubah.',
    ['class' => 'alert alert-info']
);

global $DB;

if (!optional_param('import', 0, PARAM_BOOL)) {

    echo '<form method="post" enctype="multipart/form-data">';

    echo '<input type="hidden" name="import" value="1">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';

    echo '<input type="file"
                 name="csvfile"
                 accept=".csv"
                 required>';

    echo '<br><br>';

    echo '<input
            type="submit"
            value="Import CSV"
            class="btn btn-primary">';

    echo '</form>';

    echo '<br>';

    echo '<h4>Format CSV</h4>';

// Format file CSV yang digunakan.
echo '<pre>
userid,nama guru,nis
13,Abdullah,1105
13,Abdullah,1957
25,Ahmad Hafie,1834
25,Ahmad Hafie,1842
</pre>';

    echo $OUTPUT->footer();
    exit;
}

require_sesskey();

/* ==========================================================
   PROSES IMPORT
========================================================== */

if (empty($_FILES['csvfile']['tmp_name'])) {

    echo $OUTPUT->notification(
        'File CSV belum dipilih.',
        'notifyproblem'
    );

    echo $OUTPUT->footer();
    exit;
}

$handle = fopen($_FILES['csvfile']['tmp_name'], 'r');

if (!$handle) {

    echo $OUTPUT->notification(
        'Gagal membuka file CSV.',
        'notifyproblem'
    );

    echo $OUTPUT->footer();
    exit;
}

// Lewati header.
$header = fgetcsv($handle);

if (!$header) {

    fclose($handle);

    echo $OUTPUT->notification(
        'File CSV kosong.',
        'notifyproblem'
    );

    echo $OUTPUT->footer();
    exit;
}

// Ambil field id NIS sekali saja.
$fieldid = $DB->get_field(
    'user_info_field',
    'id',
    ['shortname' => 'nis'],
    MUST_EXIST
);

// Statistik.
$insert = 0;
$update = 0;
$skip   = 0;
$gagal  = 0;

$time = time();

// Transaksi database.
$transaction = $DB->start_delegated_transaction();

/* ==========================================================
   BACA CSV
========================================================== */

while (($row = fgetcsv($handle)) !== false) {

// Format CSV harus terdiri dari 3 kolom:
// userid, nama guru, nis
if (count($row) != 3) {
    $gagal++;
    continue;
}

    $guruid     = (int)trim($row[0]);
    $namaguru   = trim($row[1]);
    $nis        = trim($row[2]);

    if (empty($guruid) || empty($nis)) {
        $gagal++;
        continue;
    }

// -------------------------------------------------------
// Pastikan userid guru ada di tabel user.
// -------------------------------------------------------

if (!$DB->record_exists('user', ['id' => $guruid])) {

    echo html_writer::div(
        '❌ Guru userid ' . s($guruid) . ' tidak ditemukan.',
        'alert alert-warning'
    );

    $gagal++;
    continue;
}
    // -------------------------------------------------------
    // Cari murid berdasarkan NIS
    // -------------------------------------------------------

    $muridid = $DB->get_field_sql(
        "
        SELECT userid
        FROM {user_info_data}
        WHERE fieldid = :fieldid
          AND data = :nis
        ",
        [
            'fieldid' => $fieldid,
            'nis'     => $nis
        ]
    );

    if (!$muridid) {

    echo html_writer::div(
        '❌ NIS ' . s($nis) . ' tidak ditemukan.',
        'alert alert-warning'
    );

    $gagal++;
    continue;
}

// Ambil nama murid dari database Moodle.
$murid = $DB->get_record(
    'user',
    ['id' => $muridid],
    'lastname',
    MUST_EXIST
);

$namamurid = format_nama_siswa($murid->lastname);

    // -------------------------------------------------------
    // Apakah murid sudah ada?
    // -------------------------------------------------------

    $existing = $DB->get_record(
        'local_jurnalmengajar_guruwali',
        ['muridid' => $muridid]
    );

    // =======================================================
    // INSERT BARU
    // =======================================================

    if (!$existing) {

        $record = new stdClass();

        $record->guruid       = $guruid;
        $record->muridid      = $muridid;
        $record->timecreated  = $time;
        $record->timemodified = $time;

        try {

            $DB->insert_record(
                'local_jurnalmengajar_guruwali',
                $record
            );

            $insert++;

echo html_writer::div(
    '➕ INSERT : ' .
    s($namamurid) .
    ' (NIS ' .
    s($nis) .
    ')',
    'text-success'
);

        } catch (Throwable $e) {

            $gagal++;

            echo html_writer::div(
                s($e->getMessage()),
                'alert alert-danger'
            );
        }

        continue;
    }

    // =======================================================
    // UPDATE GURU WALI
    // =======================================================

    if ($existing->guruid != $guruid) {

        $existing->guruid = $guruid;
        $existing->timemodified = $time;

        try {

            $DB->update_record(
                'local_jurnalmengajar_guruwali',
                $existing
            );

            $update++;

            echo html_writer::div(
                '🔄 UPDATE : ' .
                s($namamurid),
                'alert alert-info'
            );

        } catch (Throwable $e) {

            $gagal++;

            echo html_writer::div(
                s($e->getMessage()),
                'alert alert-danger'
            );
        }

        continue;
    }

    // =======================================================
    // TIDAK BERUBAH
    // =======================================================

    $skip++;

    echo html_writer::div(
        '✔ SKIP : ' .
        s($namamurid),
        'text-muted'
    );
}

/* ==========================================================
   SELESAI
========================================================== */

fclose($handle);

// Commit transaksi.
$transaction->allow_commit();

echo html_writer::tag('h3', 'Import Selesai');

$table = new html_table();

$table->head = [
    'Keterangan',
    'Jumlah'
];

$table->data[] = [
    'Insert baru',
    $insert
];

$table->data[] = [
    'Update Guru Wali',
    $update
];

$table->data[] = [
    'Tidak berubah (Skip)',
    $skip
];

$table->data[] = [
    'Gagal',
    $gagal
];

echo html_writer::table($table);

echo html_writer::empty_tag('br');

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/guruwali_manage.php'),
    'Kembali ke Manajemen Guru Wali',
    [
        'class' => 'btn btn-primary'
    ]
);

echo $OUTPUT->footer();
