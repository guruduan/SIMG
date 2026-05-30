<?php

require('../../config.php');
require_once(__DIR__.'/lib.php');

require_login();

$context = context_system::instance();

require_capability(
    'local/jurnalmengajar:view',
    $context
);

$PAGE->set_context($context);
$PAGE->set_url(
    '/local/jurnalmengajar/rekap_jurnal_walikelas.php'
);
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Rekap Jurnal Wali Kelas');
$PAGE->set_heading('Rekap Jurnal Wali Kelas');

$kelasid = jurnalmengajar_get_kelas_wali(
    $USER->id
);

if (!$kelasid) {

    echo $OUTPUT->header();

    echo $OUTPUT->notification(
        'Akun Anda belum ditetapkan sebagai wali kelas.',
        'warning'
    );

    echo $OUTPUT->footer();
    exit;
}

$kelasnama = get_nama_kelas($kelasid);

$bulan = optional_param(
    'bulan',
    date('n'),
    PARAM_INT
);

$tahun = optional_param(
    'tahun',
    date('Y'),
    PARAM_INT
);

$awal = strtotime(
    sprintf(
        '%04d-%02d-01 00:00:00',
        $tahun,
        $bulan
    )
);

$akhir = strtotime(
    date(
        'Y-m-t 23:59:59',
        $awal
    )
);

$data = $DB->get_records_sql(
    "
    SELECT *
    FROM {local_jurnalwalikelas}
    WHERE kelas = ?
      AND timecreated BETWEEN ? AND ?
    ORDER BY timecreated DESC
    ",
    [
        $kelasid,
        $awal,
        $akhir
    ]
);

echo $OUTPUT->header();
echo '<h3>Rekap Jurnal Wali Kelas</h3>';

echo '<div class="alert alert-info">';
echo '<strong>Kelas:</strong> ' . s($kelasnama);
echo '</div>';
echo '<form method="get" class="mb-3">';

echo 'Bulan ';
echo '<select name="bulan">';

for ($i = 1; $i <= 12; $i++) {

    $selected = ($bulan == $i)
        ? 'selected'
        : '';

    echo '<option value="' . $i . '" ' . $selected . '>';
    echo tanggal_indo(
        strtotime($tahun . '-' . sprintf('%02d', $i) . '-01'),
        'bulan'
    );
    echo '</option>';
}

echo '</select> ';

echo 'Tahun ';
echo '<input
    type="number"
    name="tahun"
    value="' . $tahun . '"
    style="width:100px;"
> ';

echo '<button type="submit" class="btn btn-primary btn-sm">';
echo 'Filter';
echo '</button>';

echo '</form>';

if (empty($data)) {

    echo $OUTPUT->notification(
        'Belum ada jurnal wali kelas pada periode ini.',
        'info'
    );

    echo $OUTPUT->footer();
    exit;
}
$no = 1;
echo '<table class="table table-striped">';
echo '<thead>';
echo '<tr>';
echo '<th>No</th>';

echo '<th>Tanggal</th>';
echo '<th>Jenis</th>';
echo '<th>Murid</th>';
echo '<th>Kegiatan</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

$jenis = [
    'umum' => 'Kegiatan Umum',
    'pembinaan' => 'Pembinaan Murid'
];

foreach ($data as $row) {

    $murid = '-';

    if (!empty($row->muridid)) {

     $murid = $DB->get_field(
	    'user',
	    'lastname',
    	['id' => $row->muridid]
	);

	if ($murid) {
	    $murid = format_nama_siswa($murid);
	} else {
	    $murid = '-';
	}
}
    $isi = '';

    if ($row->jenis == 'umum') {

        $isi = s($row->uraian);

    } else {

        $isi =
            '<b>Topik:</b> ' .
            s($row->topik) .
            '<br>' .
            '<b>Tindak Lanjut:</b> ' .
            s($row->tindaklanjut);
    }

    echo '<tr>';
  
    echo '<td>' . $no++ . '</td>';
    echo '<td>' .
    tanggal_indo($row->timecreated, 'judul') .
    '<br><small>Pukul ' .
    tanggal_indo($row->timecreated, 'jam') .
    '</small></td>';

    echo '<td>' .
   	 ($jenis[$row->jenis] ?? $row->jenis) .
	 '</td>';

    echo '<td>' .
        $murid .
        '</td>';

    echo '<td>' .
        $isi .
        '</td>';

    echo '</tr>';
}

echo '</tbody>';
echo '</table>';

echo $OUTPUT->footer();

