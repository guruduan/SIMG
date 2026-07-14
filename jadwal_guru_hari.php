<?php
require('../../config.php');
require_once(__DIR__ . '/jadwal_acuan_lib.php');
require_once(__DIR__ . '/jam_pelajaran_lib.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/jadwal_guru_hari.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Jadwal Mengajar Hari Ini');
$PAGE->set_heading('Jadwal Mengajar Hari Ini');

echo $OUTPUT->header();

global $DB, $USER;

/* ==========================================================
 * FILTER GURU
 * ========================================================== */

$listguru = [];

$role = $DB->get_record(
    'role',
    ['shortname' => 'gurujurnal']
);

if ($role) {

    $sql = "
        SELECT DISTINCT
            u.id,
            u.lastname
        FROM {role_assignments} ra
        JOIN {user} u
             ON u.id = ra.userid
        WHERE ra.roleid = :roleid
        ORDER BY u.lastname
    ";

    $guru = $DB->get_records_sql(
        $sql,
        [
            'roleid' => $role->id
        ]
    );

    foreach ($guru as $g) {
        $listguru[$g->id] = $g->lastname;
    }
}

$filterguru = optional_param(
    'guru',
    $USER->id,
    PARAM_INT
);

/* ==========================================================
 * HARI
 * ========================================================== */

$hari = jurnalmengajar_get_hari_ini();

/* ==========================================================
 * DATA
 * ========================================================== */

$jadwal = jurnalmengajar_get_jadwal_acuan();

$jamhari = jurnalmengajar_generate_jam_hari($hari);

if (empty($jamhari)) {

    echo $OUTPUT->notification(
        'Konfigurasi jam pelajaran belum tersedia.',
        \core\output\notification::NOTIFY_WARNING
    );

    echo $OUTPUT->footer();
    exit;
}

/* ==========================================================
 * FORM FILTER
 * ========================================================== */

echo html_writer::start_tag('form', [
    'method' => 'get',
    'class'  => 'mb-3'
]);

echo html_writer::start_div('row');

echo html_writer::start_div('col-md-5');

echo html_writer::label(
    'Guru',
    'guru',
    [
        'class' => 'font-weight-bold'
    ]
);

echo html_writer::select(
    $listguru,
    'guru',
    $filterguru,
    false,
    [
        'class' => 'form-control',
        'onchange' => 'this.form.submit();'
    ]
);

echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::end_tag('form');

/* ==========================================================
 * TIMELINE AWAL
 * ========================================================== */

$timeline = [];

foreach ($jamhari as $jam => $info) {

    $timeline[$jam] = [
        'kelas'  => '',
        'status' => 'Kosong'
    ];
}

/* ==========================================================
 * ISI TIMELINE DARI JADWAL
 * ========================================================== */

foreach ($jadwal as $j) {

    if ($j['userid'] != $filterguru) {
        continue;
    }

    if ($j['hari'] != $hari) {
        continue;
    }

    $listjam = array_map('trim', explode(',', $j['jamke']));

    foreach ($listjam as $jam) {

        $jam = (int)$jam;

        if (!isset($timeline[$jam])) {
            continue;
        }

        $timeline[$jam] = [
            'kelas'  => $j['kelas'],
            'status' => 'Mengajar'
        ];
    }
}

/* ==========================================================
 * TAMPILAN
 * ========================================================== */

$totalmengajar = 0;

$namaguru = $listguru[$filterguru] ?? '-';

echo html_writer::start_div('card shadow-sm');

echo html_writer::start_div('card-body');

echo html_writer::tag(
    'h4',
    'Jadwal Mengajar Hari ' . $hari
);

echo html_writer::div(
    '<strong>Guru :</strong> ' . s($namaguru),
    'alert alert-primary'
);

echo html_writer::start_div('table-responsive');

echo "<table class='table table-bordered table-striped table-hover'>";

echo "
<thead class='thead-dark'>
<tr>
    <th width='8%' class='text-center'>Jam</th>
    <th width='22%'>Pukul</th>
    <th>Status</th>
</tr>
</thead>
<tbody>
";

foreach ($timeline as $jam => $data) {

    $mulai   = $jamhari[$jam]['mulai'] ?? '-';
    $selesai = $jamhari[$jam]['selesai'] ?? '-';

    if ($data['status'] == 'Mengajar') {

        $status = "<span class='badge badge-success p-2'>Mengajar : {$data['kelas']}</span>";

        $totalmengajar++;

    } else {

        $status = "<span class='badge badge-light border'>Tidak Mengajar</span>";
    }

    echo "<tr>";

    echo "<td class='text-center'><strong>{$jam}</strong></td>";

    echo "<td>{$mulai} - {$selesai}</td>";

    echo "<td>{$status}</td>";

    echo "</tr>";

    // =============================
    // BARIS ISTIRAHAT
    // =============================
    if (!empty($jamhari[$jam]['istirahat_setelah'])) {

        $durasi = (int)$jamhari[$jam]['istirahat_setelah'];

        $mulaiist = strtotime(date('Y-m-d') . " {$selesai}");

        $akhirist = strtotime("+{$durasi} minutes", $mulaiist);

        echo "
        <tr class='table-warning'>
            <td></td>
            <td><strong>"
            . date('H:i', $mulaiist)
            . " - "
            . date('H:i', $akhirist)
            . "</strong></td>
            <td>
                ☕ <strong>Istirahat ({$durasi} menit)</strong>
            </td>
        </tr>";
    }
}

echo "</tbody>";

echo "</table>";

echo html_writer::end_div();

echo html_writer::div(
    "<strong>Total Mengajar Hari Ini :</strong>
    <span class='badge badge-primary p-2'>{$totalmengajar} JP</span>",
    'alert alert-info'
);

echo html_writer::end_div();

echo html_writer::end_div();

echo "<br>";

echo html_writer::link(
    '#',
    '<i class="fa fa-arrow-left"></i> Kembali',
    [
        'class' => 'btn btn-secondary',
        'onclick' => 'history.back(); return false;'
    ]
);

echo $OUTPUT->footer();
