<?php
require('../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

global $DB, $PAGE, $OUTPUT;

$PAGE->set_context($context);
$PAGE->set_url(
    new moodle_url('/local/jurnalmengajar/riwayat_nonaktif.php')
);
$PAGE->set_title('Riwayat Siswa Nonaktif');
$PAGE->set_heading('Riwayat Siswa Nonaktif');

echo $OUTPUT->header();
echo $OUTPUT->heading('📚 Riwayat Siswa Nonaktif');

/*
========================================================
AMBIL DATA
========================================================
*/

$sql = "
SELECT
    r.id,
    r.userid,
    u.lastname AS nama,
    d.data AS nis,
    r.jenis,
    r.tanggal,
    r.keterangan

FROM {local_jurnalmengajar_riwayatakademik} r

JOIN {user} u
    ON u.id = r.userid

LEFT JOIN {user_info_field} f
    ON f.shortname = 'nis'

LEFT JOIN {user_info_data} d
    ON d.userid = u.id
   AND d.fieldid = f.id

JOIN (
    SELECT
        userid,
        MAX(tanggal) AS maxtanggal
    FROM {local_jurnalmengajar_riwayatakademik}
    GROUP BY userid
) x
ON x.userid = r.userid
AND x.maxtanggal = r.tanggal

WHERE r.jenis IN ('berhenti','mutasi')

ORDER BY
    r.tanggal DESC,
    u.lastname
";

$data = $DB->get_records_sql($sql);

/*
========================================================
STATISTIK
========================================================
*/

$jumlahberhenti = 0;
$jumlahmutasi   = 0;

foreach ($data as $d) {

    if ($d->jenis == 'berhenti') {
        $jumlahberhenti++;
    }

    if ($d->jenis == 'mutasi') {
        $jumlahmutasi++;
    }
}

echo html_writer::start_div('alert alert-info');

echo '<b>Berhenti :</b> ' . $jumlahberhenti;
echo ' &nbsp; | &nbsp; ';
echo '<b>Mutasi :</b> ' . $jumlahmutasi;
echo ' &nbsp; | &nbsp; ';
echo '<b>Total :</b> ' . count($data);

echo html_writer::end_div();

/*
========================================================
TABEL
========================================================
*/

$table = new html_table();

$table->head = [
    'No',
    'NIS',
    'Nama',
    'Status',
    'Tanggal',
    'Keterangan'
];

$table->attributes['class'] = 'table table-striped table-bordered';

$no = 1;

foreach ($data as $row) {

$status = ucfirst($row->jenis);

    $table->data[] = [
        $no++,
        s($row->nis),
        format_nama_siswa($row->nama),
        $status,
        tanggal_indo($row->tanggal, 'judul'), // Tambahkan parameter 'judul' di sini
        format_text($row->keterangan, FORMAT_PLAIN)
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
