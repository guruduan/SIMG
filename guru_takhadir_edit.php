<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$id = required_param('id', PARAM_INT);

$record = $DB->get_record(
    'local_jurnalmengajar_kehadiran',
    ['id' => $id],
    '*',
    MUST_EXIST
);

$PAGE->set_context($context);
$PAGE->set_url(
    '/local/jurnalmengajar/guru_takhadir_edit.php',
    ['id' => $id]
);
$PAGE->set_title('Edit Guru Tidak Hadir');
$PAGE->set_heading('Edit Guru Tidak Hadir');

$userid         = optional_param('userid', $record->userid, PARAM_INT);
$status         = optional_param('status', $record->status, PARAM_ALPHA);
$tanggalmulai   = optional_param(
    'tanggalmulai',
    date('Y-m-d', $record->tanggalmulai),
    PARAM_TEXT
);
$tanggalselesai = optional_param(
    'tanggalselesai',
    date('Y-m-d', $record->tanggalselesai),
    PARAM_TEXT
);
$keterangan = optional_param(
    'keterangan',
    $record->keterangan,
    PARAM_TEXT
);

$error = '';

if (optional_param('save', 0, PARAM_BOOL) && confirm_sesskey()) {

    $mulai   = strtotime($tanggalmulai . ' 00:00:00');
    $selesai = strtotime($tanggalselesai . ' 23:59:59');

    if (empty($userid)) {

        $error = 'Guru belum dipilih.';

    } else if (empty($status)) {

        $error = 'Status belum dipilih.';

    } else if ($mulai > $selesai) {

        $error = 'Tanggal mulai tidak boleh melebihi tanggal selesai.';

    } else {

        $sql = "
            SELECT id
            FROM {local_jurnalmengajar_kehadiran}
            WHERE userid = :userid
              AND id <> :id
              AND tanggalmulai <= :selesai
              AND tanggalselesai >= :mulai
        ";

        $params = [
            'userid'  => $userid,
            'id'      => $id,
            'mulai'   => $mulai,
            'selesai' => $selesai
        ];

        if ($DB->record_exists_sql($sql, $params)) {

            $error =
                'Guru tersebut sudah memiliki data pada rentang tanggal tersebut.';
        }
    }

    if (empty($error)) {

        $record->userid         = $userid;
        $record->status         = $status;
        $record->tanggalmulai   = $mulai;
        $record->tanggalselesai = $selesai;
        $record->keterangan     = trim($keterangan);
        $record->timemodified   = time();

        $DB->update_record(
            'local_jurnalmengajar_kehadiran',
            $record
        );

        redirect(
            new moodle_url('/local/jurnalmengajar/guru_takhadir.php'),
            'Data berhasil diperbarui.',
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$guru = get_users_by_capability(
    $context,
    'local/jurnalmengajar:submit',
    'u.id,u.lastname',
    'u.lastname ASC'
);

echo $OUTPUT->header();

echo $OUTPUT->heading('Edit Guru Tidak Hadir');

if (!empty($error)) {
    echo $OUTPUT->notification(
        $error,
        \core\output\notification::NOTIFY_ERROR
    );
}
?>

<form method="post">

<input type="hidden" name="sesskey" value="<?= sesskey() ?>">
<input type="hidden" name="save" value="1">

<div class="mb-3">

<label class="form-label">Guru</label>

<select name="userid" class="form-select" required>

<option value="">-- Pilih Guru --</option>

<?php foreach ($guru as $g) { ?>

<option
    value="<?= $g->id ?>"
    <?= $userid == $g->id ? 'selected' : '' ?>>

<?= format_string($g->lastname) ?>

</option>

<?php } ?>

</select>

</div>

<div class="mb-3">

<label class="form-label">Status</label>

<select name="status" class="form-select" required>

<option value="sakit" <?= $status=='sakit'?'selected':'' ?>>Sakit</option>
<option value="izin" <?= $status=='izin'?'selected':'' ?>>Izin</option>
<option value="cuti" <?= $status=='cuti'?'selected':'' ?>>Cuti</option>
<option value="tugasluar" <?= $status=='tugasluar'?'selected':'' ?>>Tugas Luar</option>

</select>

</div>

<div class="row">

<div class="col-md-6">

<label class="form-label">Tanggal Mulai</label>

<input
    type="date"
    name="tanggalmulai"
    class="form-control"
    value="<?= s($tanggalmulai) ?>"
    required>

</div>

<div class="col-md-6">

<label class="form-label">Tanggal Selesai</label>

<input
    type="date"
    name="tanggalselesai"
    class="form-control"
    value="<?= s($tanggalselesai) ?>"
    required>

</div>

</div>

<div class="mt-3">

<label class="form-label">Keterangan</label>

<textarea
    name="keterangan"
    class="form-control"
    rows="3"><?= s($keterangan) ?></textarea>

</div>

<div class="mt-4">

<button
    type="submit"
    class="btn btn-success">

<i class="fa fa-save"></i> Simpan

</button>

<a
    href="<?= new moodle_url('/local/jurnalmengajar/guru_takhadir.php') ?>"
    class="btn btn-secondary">

Kembali

</a>

</div>

</form>

<?php
echo $OUTPUT->footer();
