<?php

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();

require_capability(
    'local/jurnalmengajar:submit',
    $context
);

$PAGE->set_context($context);

$PAGE->set_url(
    new moodle_url(
        '/local/jurnalmengajar/riwayat_berita_acara.php'
    )
);

$PAGE->set_title('Riwayat Berita Acara');

$PAGE->set_heading('Riwayat Berita Acara');

require_once(__DIR__ . '/lib.php');

echo $OUTPUT->header();

echo $OUTPUT->heading(
    'Riwayat Berita Acara Asesmen'
);

/*
=========================================
BATASI SAMPAI HARI INI
=========================================
*/

$hariini = strtotime(date('Y-m-d'));

$tanggalterakhir = $DB->get_field_sql("
    SELECT MAX(tanggal)
    FROM {local_jurnalmengajar_asesmen_jadwal}
");

if ($hariini <= $tanggalterakhir) {

    $where = "
        WHERE j.tanggal <= :hariini
    ";

    $params = [
        'hariini' => $hariini
    ];

} else {

    $where = '';

    $params = [];
}

/*
=========================================
QUERY DATA RIWAYAT
=========================================
*/

$sql = "
SELECT
    CONCAT(
        a.id,
        '_',
        j.tanggal,
        '_',
        j.sesiaktual
    ) AS id,

    a.id AS asesmenid,

    a.namaasesmen,
    a.ruang,

    j.tanggal,
    j.sesiaktual,

    u.lastname AS pengawas

FROM
    {local_jurnalmengajar_asesmen} a

JOIN
    {local_jurnalmengajar_asesmen_jadwal} j
        ON j.asesmenid = a.id

LEFT JOIN
    {local_jurnalmengajar_asesmen_detail} d
        ON d.asesmenid = a.id
       AND d.tanggal = j.tanggal
       AND d.sesiaktual = j.sesiaktual

LEFT JOIN
    {user} u
        ON u.id = d.pengawasid

$where

GROUP BY
    a.id,
    j.tanggal,
    j.sesiaktual

ORDER BY
    j.tanggal ASC,
    a.ruang ASC,
    j.sesiaktual ASC
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

echo '<th>Hadir</th>';

echo '<th>Sakit</th>';

echo '<th>Izin</th>';

echo '<th>Alpa</th>';

echo '<th>Dispensasi</th>';

echo '<th>Pengawas</th>';

echo '<th>Download</th>';

echo '</tr>';

$no = 1;
foreach ($rows as $r) {

    echo '<tr>';
    $hadir = $DB->count_records_select(
    'local_jurnalmengajar_asesmen_detail',
    'asesmenid = ? AND tanggal = ? AND sesiaktual = ? AND status = ?',
    [
        $r->asesmenid,
        $r->tanggal,
        $r->sesiaktual,
        'H'
    ]
);

$sakit = $DB->count_records_select(
    'local_jurnalmengajar_asesmen_detail',
    'asesmenid = ? AND tanggal = ? AND sesiaktual = ? AND status = ?',
    [
        $r->asesmenid,
        $r->tanggal,
        $r->sesiaktual,
        'S'
    ]
);

$izin = $DB->count_records_select(
    'local_jurnalmengajar_asesmen_detail',
    'asesmenid = ? AND tanggal = ? AND sesiaktual = ? AND status = ?',
    [
        $r->asesmenid,
        $r->tanggal,
        $r->sesiaktual,
        'I'
    ]
);

$alpa = $DB->count_records_select(
    'local_jurnalmengajar_asesmen_detail',
    'asesmenid = ? AND tanggal = ? AND sesiaktual = ? AND status = ?',
    [
        $r->asesmenid,
        $r->tanggal,
        $r->sesiaktual,
        'A'
    ]
);

$dispensasi = $DB->count_records_select(
    'local_jurnalmengajar_asesmen_detail',
    'asesmenid = ? AND tanggal = ? AND sesiaktual = ? AND status = ?',
    [
        $r->asesmenid,
        $r->tanggal,
        $r->sesiaktual,
        'D'
    ]
);

   echo '<td>' .
    $no++ .
    '</td>';
    echo '<td>' .
        tanggal_indo($r->tanggal, 'tanggal') .
        '</td>';

    echo '<td>' .
        $r->sesiaktual .
        '</td>';

    echo '<td>' .
    s($r->ruang) .
    '</td>';

	echo '<td>' . $hadir . '</td>';

	echo '<td>' . $sakit . '</td>';

	echo '<td>' . $izin . '</td>';

	echo '<td>' . $alpa . '</td>';
	
	echo '<td>' . $dispensasi . '</td>';

	echo '<td>' .
    s($r->pengawas ?? '-') .
    '</td>';

echo '<td>';

echo html_writer::link(
    new moodle_url(
        '/local/jurnalmengajar/export_berita_acara_pdf.php',
        [
            'tanggal'    => $r->tanggal,
            'sesiaktual' => $r->sesiaktual,
            'ruang'      => $r->ruang
        ]
    ),
    '📄 PDF',
    [
        'target' => '_blank'
    ]
);

echo '</td>';
    echo '</tr>';

}
    $rows->close();

echo '</table>';

echo $OUTPUT->footer();
