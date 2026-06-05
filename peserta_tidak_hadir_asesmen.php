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
        '/local/jurnalmengajar/peserta_tidak_hadir_asesmen.php'
    )
);

$PAGE->set_title(
    'Peserta Tidak Hadir Asesmen'
);

$PAGE->set_heading(
    'Peserta Tidak Hadir Asesmen'
);

echo $OUTPUT->header();

//echo $OUTPUT->heading(
//    'Peserta Tidak Hadir Asesmen'
//);


/*
=========================================
QUERY
=========================================
*/

$where = "
d.status IN ('S','I','A','D')
";

$params = [];

$sql = "
SELECT
    CONCAT(
        d.id,
        '_',
        d.userid
    ) AS id,

    d.tanggal,
    d.sesiaktual,

    a.ruang,

    u.lastname,

    c.name AS kelas,

    d.status,

    pu.lastname AS pengawas

FROM
    {local_jurnalmengajar_asesmen_detail} d

JOIN
    {user} u
        ON u.id = d.userid

JOIN
    {local_jurnalmengajar_asesmen} a
        ON a.id = d.asesmenid

LEFT JOIN
    {local_jurnalmengajar_asesmen_peserta} p
        ON p.userid = d.userid
       AND p.asesmenid = d.asesmenid

LEFT JOIN
    {cohort} c
        ON c.id = p.kelasid

LEFT JOIN
    {user} pu
        ON pu.id = d.pengawasid

WHERE
    $where

ORDER BY
    d.tanggal ASC,
    d.sesiaktual ASC,
    a.ruang ASC,
    u.lastname ASC
";

$rows = $DB->get_recordset_sql(
    $sql,
    $params
);

echo '<table class="table table-bordered table-striped">';

echo '<tr>';

echo '<th width="60">No</th>';

echo '<th>Tanggal</th>';

echo '<th>Sesi</th>';

echo '<th>Ruang</th>';

echo '<th>Nama Murid</th>';

echo '<th>Kelas</th>';

echo '<th>Status</th>';

echo '<th>Pengawas</th>';

echo '</tr>';

$no = 1;

foreach ($rows as $r) {

    switch ($r->status) {

        case 'S':
            $status = 'Sakit';
            $warna = '#fff3cd';
            break;

        case 'I':
            $status = 'Izin';
            $warna = '#d1ecf1';
            break;

        case 'A':
            $status = 'Alpa';
            $warna = '#f8d7da';
            break;

        case 'D':
            $status = 'Dispensasi';
            $warna = '#d4edda';
            break;

        default:
            $status = $r->status;
            $warna = '#ffffff';
    }

    echo '<tr>';

    echo '<td>' .
        $no++ .
        '</td>';

    echo '<td>' .
        tanggal_indo(
            $r->tanggal,
            'tanggal'
        ) .
        '</td>';

    echo '<td>' .
        $r->sesiaktual .
        '</td>';

    echo '<td>' .
        s($r->ruang) .
        '</td>';

    echo '<td>' .
        s(
            ucwords(
                strtolower(
                    $r->lastname
                )
            )
        ) .
        '</td>';

    echo '<td>' .
        s($r->kelas ?? '-') .
        '</td>';

    echo '<td style="background:' .
        $warna .
        ';font-weight:bold;">' .
        $status .
        '</td>';

    echo '<td>' .
        s($r->pengawas ?? '-') .
        '</td>';

    echo '</tr>';
}

$rows->close();

echo '</table>';

echo $OUTPUT->footer();
?>
