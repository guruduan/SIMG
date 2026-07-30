<?php
require('../../config.php');
require_once(__DIR__.'/lib.php');
require_once(__DIR__.'/jadwal_acuan_lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/guruwali_view.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Data Murid Binaan Guru Wali');
$PAGE->set_heading('Data Murid Binaan Guru Wali');

echo $OUTPUT->header();

global $DB, $USER;

// ============================
// Ambil Data Mapping Wali Kelas
// ============================
$json_mapping = get_config('local_jurnalmengajar', 'wali_kelas_mapping');
$walimapping = json_decode($json_mapping, true);
if (!is_array($walimapping)) {
    $walimapping = [];
}

// Pre-fetch nama-nama Wali Kelas agar tidak perlu query berulang di dalam loop
$walinames = [];
if (!empty($walimapping)) {
    $wali_userids = array_unique(array_values($walimapping));
    if (!empty($wali_userids)) {
        list($in_sql, $in_params) = $DB->get_in_or_equal($wali_userids);
        $wali_users = $DB->get_records_select('user', "id $in_sql", $in_params, '', 'id, firstname, lastname');
        foreach ($wali_users as $wu) {
            $walinames[$wu->id] = !empty($wu->lastname) ? $wu->lastname : $wu->firstname;
        }
    }
}


// ============================
// List guru wali
// ============================
$listguru = [];

$sqlguru = "
SELECT DISTINCT
    gw.guruid,
    u.lastname
FROM {local_jurnalmengajar_guruwali} gw
JOIN {user} u
     ON u.id = gw.guruid
ORDER BY u.lastname
";

$rowsguru = $DB->get_records_sql($sqlguru);

foreach ($rowsguru as $g) {
    $listguru[$g->guruid] = $g->lastname;
}

// Default = guru login
$filterguru = optional_param('guru', $USER->id, PARAM_INT);

// Query utama tanpa join ke tabel guru karena kolom Guru Wali dihilangkan
$sql = "
SELECT

    gw.id,

    murid.lastname AS namamurid,

    uid.data AS nis,
    
    c.id AS cohortid,
    c.name AS kelas

FROM {local_jurnalmengajar_guruwali} gw

JOIN {user} murid
ON murid.id=gw.muridid

LEFT JOIN {user_info_field} uif
ON uif.shortname='nis'

LEFT JOIN {user_info_data} uid
ON uid.userid=murid.id
AND uid.fieldid=uif.id

LEFT JOIN {cohort_members} cm
ON cm.userid=murid.id

LEFT JOIN {cohort} c
ON c.id=cm.cohortid

WHERE gw.guruid=:guruid

ORDER BY

c.name,
murid.lastname
";

$filtered = $DB->get_records_sql(
    $sql,
    [
        'guruid' => $filterguru
    ]
);

// ============================
// UI: Tampilan Card Bootstrap
// ============================
echo html_writer::start_div('card mb-3');
echo html_writer::start_div('card-body');

// ============================
// Filter dropdown (Form)
// ============================
echo html_writer::start_tag('form', [
    'method' => 'get',
    'class'  => 'mb-4'
]);

echo html_writer::start_div('d-flex align-items-center flex-wrap gap-2');

echo html_writer::tag('strong', 'Filter Guru Wali: ', ['class' => 'mr-2']);

echo html_writer::select(
    $listguru,
    'guru',
    $filterguru,
    false,
    [
        'class'    => 'custom-select form-select w-auto',
        'onchange' => 'this.form.submit();'
    ]
);

echo html_writer::end_div();

echo html_writer::end_tag('form');

// ============================
// Tabel Murid Binaan
// ============================
if (!empty($filtered)) {

    $table = new html_table();

    // Kolom 'Guru Wali' sudah dihapus dari header
    $table->head = [
        'No',
        'NIS',
        'Nama Murid',
        'Kelas',
        'Wali Kelas'
    ];

    $table->attributes['class'] =
        'generaltable table table-striped table-hover table-bordered mt-3';

    $no = 1;

    foreach ($filtered as $r) {

        $kelas = !empty($r->kelas)
            ? s($r->kelas)
            : 'Belum ada kelas';
            
        // Mencocokkan Wali Kelas dari id cohort
        $nama_walikelas = '-';
        if (!empty($r->cohortid) && isset($walimapping[$r->cohortid])) {
            $wali_id = $walimapping[$r->cohortid];
            if (isset($walinames[$wali_id])) {
                $nama_walikelas = $walinames[$wali_id];
            }
        }

        // Variabel s($r->namaguru) sudah dihapus dari row
        $row = new html_table_row([
            $no,
            s($r->nis),
            format_nama_siswa($r->namamurid),
            $kelas,
            $nama_walikelas
        ]);

        $table->data[] = $row;

        $no++;
    }

    echo html_writer::table($table);

} else {

    echo html_writer::start_div(
        'alert alert-info mt-3',
        ['role' => 'alert']
    );

    echo 'Tidak ada data murid binaan untuk guru yang dipilih.';

    echo html_writer::end_div();
}


echo html_writer::end_div(); // End card-body
echo html_writer::end_div(); // End card

echo $OUTPUT->footer();
