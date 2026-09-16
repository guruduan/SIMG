<?php
require('../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
jurnalmengajar_log_page_access();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

global $DB, $PAGE, $OUTPUT;

$PAGE->set_context($context);
$PAGE->set_url(
    new moodle_url('/local/jurnalmengajar/riwayat_nonaktif.php')
);
$PAGE->set_title('Riwayat Murid Nonaktif');
$PAGE->set_heading('Riwayat Murid Nonaktif');

echo $OUTPUT->header();
echo $OUTPUT->heading('📚 Riwayat Murid Nonaktif');

/*
========================================================
AMBIL DATA
========================================================
*/

$sql = "
SELECT
    r.id,
    r.userid,
    r.kelas,
    u.lastname AS nama,
    d.data AS nis,
    r.jenis,
    r.tanggal,
    r.keterangan,

    COALESCE(
        c.name,
        (
            SELECT r2.keterangan
            FROM {local_jurnalmengajar_riwayatakademik} r2
            WHERE r2.userid = r.userid
              AND r2.jenis = 'masukkelas'
              AND r2.tanggal <= r.tanggal
            ORDER BY r2.tanggal DESC, r2.id DESC
            LIMIT 1
        )
    ) AS namakelas

FROM {local_jurnalmengajar_riwayatakademik} r

JOIN {user} u
    ON u.id = r.userid

LEFT JOIN {cohort} c
    ON c.id = r.kelas

LEFT JOIN {user_info_field} f
    ON f.shortname = 'nis'

LEFT JOIN {user_info_data} d
    ON d.userid = u.id
   AND d.fieldid = f.id

WHERE r.jenis IN ('berhenti', 'mutasi')

AND NOT EXISTS (
    SELECT 1
    FROM {local_jurnalmengajar_riwayatakademik} r3
    WHERE r3.userid = r.userid
      AND r3.jenis IN ('berhenti', 'mutasi')
      AND (
            r3.tanggal > r.tanggal
            OR (
                r3.tanggal = r.tanggal
                AND r3.id > r.id
            )
      )
)

ORDER BY
    r.tanggal DESC,
    namakelas,
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
    'Kelas',
    'Status',
    'Tanggal',
    'Keterangan'
];

$table->attributes['class'] =
    'table table-striped table-bordered';

$no = 1;

foreach ($data as $row) {

    $status = ucfirst($row->jenis);

    // Jika data lama belum memiliki kelas.
    $kelas = !empty($row->namakelas)
        ? $row->namakelas
        : '-';

    $table->data[] = [
        $no++,
        s($row->nis ?? '-'),
        format_nama_siswa($row->nama),
        s($kelas),
        $status,
        tanggal_indo($row->tanggal, 'judul'),
        format_text($row->keterangan, FORMAT_PLAIN)
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
