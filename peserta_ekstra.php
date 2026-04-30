<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/peserta_ekstra.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Peserta Ekstrakurikuler');
$PAGE->set_heading('Peserta Ekstrakurikuler');

global $DB;

// =======================
// AMBIL PARAMETER (AMAN)
// =======================
$selected_ekstra = optional_param('ekstraid', 0, PARAM_INT);
$selected_cohort = optional_param('cohortid', 0, PARAM_INT);

// =======================
// SIMPAN PESERTA
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_sesskey();

    $ekstraid = required_param('ekstraid', PARAM_INT);
    $cohortid = required_param('cohortid', PARAM_INT);
    $peserta  = optional_param_array('peserta', [], PARAM_INT);

    // transaksi biar aman
    $transaction = $DB->start_delegated_transaction();

    $DB->delete_records('local_jm_ekstra_peserta', [
        'ekstraid' => $ekstraid,
        'cohortid' => $cohortid
    ]);

    foreach ($peserta as $userid) {
        $data = (object)[
            'ekstraid' => $ekstraid,
            'userid'   => $userid,
            'cohortid' => $cohortid
        ];
        $DB->insert_record('local_jm_ekstra_peserta', $data);
    }

    $transaction->allow_commit();

    redirect(
        new moodle_url('/local/jurnalmengajar/peserta_ekstra.php', [
            'ekstraid' => $ekstraid,
            'cohortid' => $cohortid
        ]),
        'Peserta ekstrakurikuler berhasil disimpan',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

// =======================
// AMBIL DATA
// =======================
$ekstra_list = $DB->get_records('local_jm_ekstra');
$cohort_list = $DB->get_records('cohort');

// Ambil siswa dari cohort
$siswa_list = [];
if ($selected_cohort) {
    $sql = "SELECT u.id, u.firstname, u.lastname
            FROM {cohort_members} cm
            JOIN {user} u ON u.id = cm.userid
            WHERE cm.cohortid = ?
            ORDER BY u.lastname";
    $siswa_list = $DB->get_records_sql($sql, [$selected_cohort]);
}

// Ambil peserta terpilih
$peserta_terpilih = [];
if ($selected_ekstra && $selected_cohort) {
    $records = $DB->get_records('local_jm_ekstra_peserta', [
        'ekstraid' => $selected_ekstra,
        'cohortid' => $selected_cohort
    ]);

    foreach ($records as $r) {
        $peserta_terpilih[] = $r->userid;
    }
}

// =======================
// FORM
// =======================
echo '<form method="post">';
echo '<input type="hidden" name="sesskey" value="'.sesskey().'">';

// EKSTRA
echo '<h3>Pilih Ekstrakurikuler</h3>';
echo '<select name="ekstraid" onchange="location.href=\'?ekstraid=\'+this.value+\'&cohortid='.$selected_cohort.'\'">';
echo '<option value="">-- Pilih Ekstra --</option>';

foreach ($ekstra_list as $e) {
    $sel = ($selected_ekstra == $e->id) ? 'selected' : '';
    echo '<option value="'.$e->id.'" '.$sel.'>'.format_string($e->namaekstra).'</option>';
}
echo '</select>';

// COHORT
echo '<h3>Pilih Kelas</h3>';
echo '<select name="cohortid" onchange="location.href=\'?ekstraid='.$selected_ekstra.'&cohortid=\'+this.value">';
echo '<option value="">-- Pilih Cohort --</option>';

foreach ($cohort_list as $c) {
    $sel = ($selected_cohort == $c->id) ? 'selected' : '';
    echo '<option value="'.$c->id.'" '.$sel.'>'.format_string($c->name).'</option>';
}
echo '</select>';

// =======================
// TAMPILKAN SISWA
// =======================
if ($selected_cohort && $selected_ekstra) {

echo '<h4>Peserta Terpilih:</h4>';
echo '<div id="previewPeserta" style="padding:10px; border:1px solid #ccc;"></div>';

    echo '<h3>Daftar Siswa ('.count($siswa_list).')</h3>';

    echo '<button type="button" onclick="checkAll(true)">Pilih Semua</button> ';
    echo '<button type="button" onclick="checkAll(false)">Hapus Semua</button>';

    echo '<table>';
    $i = 0;

    foreach ($siswa_list as $s) {

        if ($i % 3 == 0) echo '<tr>';

        $checked = in_array($s->id, $peserta_terpilih) ? 'checked' : '';

        echo '<td style="padding:5px 25px;">';
        echo '<input type="checkbox" class="cb-peserta" data-nama="'.format_string($s->lastname).'" name="peserta[]" value="'.$s->id.'" '.$checked.'> ';
        echo format_string($s->lastname);
        echo '</td>';

        if ($i % 3 == 2) echo '</tr>';

        $i++;
    }

    echo '</table>';

    echo '<br><button type="submit">Simpan Peserta</button>';
} else {
    echo '<p style="color:red">Silakan pilih ekstrakurikuler dan cohort terlebih dahulu.</p>';
}

echo '</form>';

// JS select all
echo '
<script>
function checkAll(state){
    document.querySelectorAll("input[name=\'peserta[]\']").forEach(cb => cb.checked = state);
    updatePreview();
}

function updatePreview(){
    let list = [];
    document.querySelectorAll(".cb-peserta:checked").forEach(cb => {
        list.push(cb.dataset.nama);
    });

    let box = document.getElementById("previewPeserta");

    if (list.length === 0) {
        box.innerHTML = "<i>Belum ada peserta dipilih</i>";
    } else {
        box.innerHTML = list.join("<br>");
    }
}

// trigger saat klik
document.querySelectorAll(".cb-peserta").forEach(cb => {
    cb.addEventListener("change", updatePreview);
});

// jalankan saat load
updatePreview();
</script>
';

echo $OUTPUT->footer();
