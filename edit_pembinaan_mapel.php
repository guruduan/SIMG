<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$id = required_param('id', PARAM_INT);

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(
    new moodle_url(
        '/local/jurnalmengajar/edit_pembinaan_mapel.php',
        ['id' => $id]
    )
);
$PAGE->set_title('Edit Pembinaan Murid');
$PAGE->set_heading('Edit Pembinaan Murid');

$sql = "
SELECT
    p.*,
    g.lastname AS namaguru,
    s.lastname AS namamurid,
    c.name AS namakelas
FROM {local_jurnalmengajar_pembinaanmapel} p
JOIN {user} g ON g.id = p.userid
JOIN {user} s ON s.id = p.muridid
LEFT JOIN {cohort} c ON c.id = p.kelas
WHERE p.id = :id
";

$record = $DB->get_record_sql(
    $sql,
    ['id' => $id],
    MUST_EXIST
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {

    $update = new stdClass();

    $update->id = $id;
    $update->jenis = required_param('jenis', PARAM_TEXT);
    $update->catatan = required_param('catatan', PARAM_TEXT);
    $update->tindaklanjut = required_param('tindaklanjut', PARAM_TEXT);

    if (property_exists($record, 'timemodified')) {
        $update->timemodified = time();
    }

    $DB->update_record(
        'local_jurnalmengajar_pembinaanmapel',
        $update
    );

    redirect(
        new moodle_url(
            '/local/jurnalmengajar/all_pembinaan_mapel.php'
        ),
        '✅ Data pembinaan berhasil diperbarui.',
        2
    );
}

echo $OUTPUT->header();

echo $OUTPUT->heading('👨‍🎓 Edit Pembinaan Murid');

echo html_writer::start_div('card');
echo html_writer::start_div('card-body');

echo '<table class="table table-bordered">';
echo '<tr><th width="180">Guru</th><td>' . s($record->namaguru) . '</td></tr>';
echo '<tr><th>Murid</th><td>' . s($record->namamurid) . '</td></tr>';
echo '<tr><th>Kelas</th><td>' . s($record->namakelas) . '</td></tr>';
echo '</table>';

echo html_writer::start_tag('form', ['method' => 'post']);

echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';

echo '<div class="form-group mb-3">';
echo '<label><strong>Jenis Pembinaan</strong></label>';

$opsi = get_jenis_pembinaan_options();

echo html_writer::select(
    $opsi,
    'jenis',
    $record->jenis,
    false,
    ['class' => 'form-control']
);

echo '</div>';

echo '<div class="form-group mb-3">';
echo '<label><strong>Masalah</strong></label>';
echo '<textarea name="catatan" rows="5" class="form-control">'
    . s($record->catatan) .
    '</textarea>';
echo '</div>';

echo '<div class="form-group mb-3">';
echo '<label><strong>Tindak Lanjut / Solusi</strong></label>';
echo '<textarea name="tindaklanjut" rows="5" class="form-control">'
    . s($record->tindaklanjut) .
    '</textarea>';
echo '</div>';

echo '<button type="submit" class="btn btn-primary">';
echo '💾 Simpan Perubahan';
echo '</button>';

echo ' ';
echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/all_pembinaan_mapel.php'),
    'Batal',
    ['class' => 'btn btn-secondary']
);

echo html_writer::end_tag('form');

echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
