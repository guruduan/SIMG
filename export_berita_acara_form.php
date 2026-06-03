<?php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();

require_capability(
    'local/jurnalmengajar:submit',
    $context
);

$PAGE->set_context($context);

$PAGE->set_url(
    new moodle_url(
        '/local/jurnalmengajar/export_berita_acara_form.php'
    )
);

$PAGE->set_title('Export Berita Acara Asesmen');

$PAGE->set_heading('Export Berita Acara Asesmen');

echo $OUTPUT->header();

echo $OUTPUT->heading(
    'Export Berita Acara Asesmen'
);

/*
=========================================
FILTER MILIK PENGAWAS LOGIN
=========================================
*/

$tanggals = $DB->get_records_sql("
    SELECT DISTINCT d.tanggal
    FROM {local_jurnalmengajar_asesmen_detail} d
    WHERE d.pengawasid = ?
    ORDER BY d.tanggal DESC
", [
    $USER->id
]);

$sesis = $DB->get_records_sql("
    SELECT DISTINCT d.sesiaktual
    FROM {local_jurnalmengajar_asesmen_detail} d
    WHERE d.pengawasid = ?
    ORDER BY d.sesiaktual
", [
    $USER->id
]);

$ruangs = $DB->get_records_sql("
    SELECT DISTINCT a.ruang
    FROM {local_jurnalmengajar_asesmen_detail} d
    JOIN {local_jurnalmengajar_asesmen} a
         ON a.id = d.asesmenid
    WHERE d.pengawasid = ?
    ORDER BY a.ruang
", [
    $USER->id
]);

echo '<form method="get"
            action="export_berita_acara_xlsx.php">';

echo '<table class="table table-bordered"
             style="max-width:700px;">';

/*
=========================================
TANGGAL
=========================================
*/

echo '<tr>';

echo '<td width="180">Tanggal</td>';

echo '<td>';

echo '<select
        name="tanggal"
        class="form-control"
        required>';

echo '<option value="">-- Pilih Tanggal --</option>';

foreach ($tanggals as $t) {

    echo '<option value="' .
        $t->tanggal .
        '">';

    echo tanggal_indo(
        $t->tanggal,
        'tanggal'
    );

    echo '</option>';
}

echo '</select>';

echo '</td>';

echo '</tr>';

/*
=========================================
SESI
=========================================
*/

echo '<tr>';

echo '<td>Sesi Aktual</td>';

echo '<td>';

echo '<select
        name="sesiaktual"
        class="form-control"
        required>';

echo '<option value="">-- Pilih Sesi --</option>';

foreach ($sesis as $s) {

    echo '<option value="' .
        $s->sesiaktual .
        '">';

    echo 'Sesi ' .
        $s->sesiaktual;

    echo '</option>';
}

echo '</select>';

echo '</td>';

echo '</tr>';

/*
=========================================
RUANG
=========================================
*/

echo '<tr>';

echo '<td>Ruang</td>';

echo '<td>';

echo '<select
        name="ruang"
        class="form-control"
        required>';

echo '<option value="">-- Pilih Ruang --</option>';

foreach ($ruangs as $r) {

    echo '<option value="' .
        s($r->ruang) .
        '">';

    echo s($r->ruang);

    echo '</option>';
}

echo '</select>';

echo '</td>';

echo '</tr>';

echo '</table>';

echo '<button
        type="submit"
        class="btn btn-success">
        Export Berita Acara Excel
      </button>';

echo '</form>';

echo $OUTPUT->footer();
