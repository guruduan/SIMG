<?php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/pdflib.php');

require_login();

$context = context_system::instance();

require_capability(
    'local/jurnalmengajar:submit',
    $context
);

$tanggal    = required_param('tanggal', PARAM_INT);
$sesiaktual = required_param('sesiaktual', PARAM_INT);
$ruang      = required_param('ruang', PARAM_TEXT);

/*
=========================================
AMBIL DATA ASESMEN
=========================================
*/

$sql = "
SELECT a.*
FROM {local_jurnalmengajar_asesmen} a
JOIN {local_jurnalmengajar_asesmen_jadwal} j
     ON j.asesmenid = a.id
WHERE j.tanggal = :tanggal
  AND j.sesiaktual = :sesi
  AND a.ruang = :ruang
";

$asesmen = $DB->get_record_sql(
    $sql,
    [
        'tanggal' => $tanggal,
        'sesi'    => $sesiaktual,
        'ruang'   => $ruang
    ],
    MUST_EXIST
);

$nip = $DB->get_field_sql(
    "
    SELECT d.data
    FROM {user_info_data} d
    JOIN {user_info_field} f
         ON f.id = d.fieldid
    WHERE d.userid = ?
      AND f.shortname = ?
    ",
    [
        $USER->id,
        'nip'
    ]
);

if (empty($nip)) {
    $nip = '-';
}

/*
=========================================
CEK HAK AKSES
=========================================
*/

$cek = $DB->record_exists(
    'local_jurnalmengajar_asesmen_detail',
    [
        'asesmenid'  => $asesmen->id,
        'tanggal'    => $tanggal,
        'sesiaktual' => $sesiaktual,
        'pengawasid' => $USER->id
    ]
);

if (!$cek) {
throw new Exception(
    'Anda tidak berhak mengakses berita acara ini'
);
}

/*
=========================================
CATATAN
=========================================
*/

$catatan = $DB->get_record(
    'local_jurnalmengajar_asesmen_catatan',
    [
        'asesmenid'  => $asesmen->id,
        'tanggal'    => $tanggal,
        'sesiaktual' => $sesiaktual
    ]
);

/*
=========================================
REKAP
=========================================
*/

$rekap = [
    'H' => 0,
    'S' => 0,
    'I' => 0,
    'D' => 0,
    'A' => 0
];

$details = $DB->get_records(
    'local_jurnalmengajar_asesmen_detail',
    [
        'asesmenid'  => $asesmen->id,
        'tanggal'    => $tanggal,
        'sesiaktual' => $sesiaktual
    ]
);

foreach ($details as $d) {

    if (isset($rekap[$d->status])) {
        $rekap[$d->status]++;
    }
}

$jumlahpeserta =
    $rekap['H'] +
    $rekap['S'] +
    $rekap['I'] +
    $rekap['D'] +
    $rekap['A'];
/*
=========================================
MURID TIDAK MENGERJAKAN
=========================================
*/

$namatidakmengerjakan = [];

foreach ($details as $d) {

    if ($d->status == 'H') {
        continue;
    }

    $sql = "
    SELECT
        u.lastname,
        c.name AS kelas
    FROM {user} u
    LEFT JOIN {local_jurnalmengajar_asesmen_peserta} p
           ON p.userid = u.id
          AND p.asesmenid = ?
    LEFT JOIN {cohort} c
           ON c.id = p.kelasid
    WHERE u.id = ?
    ";

    $data = $DB->get_record_sql(
        $sql,
        [
            $asesmen->id,
            $d->userid
        ]
    );

    if ($data) {

        $namatidakmengerjakan[] =
            $data->lastname .
            ' (Kelas ' .
            $data->kelas .
            ')';
    }
}

/*
=========================================
JAM SESI
=========================================
*/

require_once(__DIR__ . '/jadwal_asesmen_lib.php');

$jadwalsesi =
    jurnalmengajar_generate_sesi_asesmen();

$jammulai =
    $jadwalsesi[$sesiaktual]['mulai'] ?? '-';

$jamselesai =
    $jadwalsesi[$sesiaktual]['selesai'] ?? '-';

//ambil nama sekolah
$namasekolah = get_config(
    'local_jurnalmengajar',
    'nama_sekolah'
);

if (empty($namasekolah)) {
    $namasekolah = '';
}
//ambil tahun ajaran
$tahunajaran = get_config(
    'local_jurnalmengajar',
    'tahun_ajaran'
);

if (empty($tahunajaran)) {
    $tahunajaran = '';

}
$namasekolahcetak = str_replace(
    'Sman',
    'SMAN',
    ucwords(strtolower($namasekolah))
);

/*
=========================================
LOGO SEKOLAH
=========================================
*/

$logo = '';

$context = context_system::instance();

$fs = get_file_storage();

$files = $fs->get_area_files(
    $context->id,
    'local_jurnalmengajar',
    'logo',
    0,
    'itemid, filepath, filename',
    false
);

if ($files) {

    $file = reset($files);

    $logo = $CFG->dataroot .
        '/temp/logo_jurnalmengajar_' .
        time() .
        '.' .
        pathinfo(
            $file->get_filename(),
            PATHINFO_EXTENSION
        );

    file_put_contents(
        $logo,
        $file->get_content()
    );
}

/*
=========================================
PDF
=========================================
*/

$pdf = new pdf();
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->SetCreator('Moodle');
$pdf->SetAuthor(fullname($USER));
$pdf->SetTitle('Berita Acara Asesmen');

$pdf->AddPage();
if (!empty($logo) && file_exists($logo)) {

    $pdf->Image(
        $logo,
        15, // X
        15, // Y
        16  // lebar mm
    );
}

$pdf->SetY(15);

$pdf->SetFont('helvetica', 'B', 14);

$pdf->Cell(
    0,
    8,
    'BERITA ACARA',
    0,
    1,
    'C'
);

$pdf->SetFont('helvetica', 'B', 12);

$pdf->Cell(
    0,
    8,
    strtoupper($asesmen->namaasesmen),
    0,
    1,
    'C'
);

$pdf->Cell(
    0,
    8,
    'TAHUN AJARAN ' . $tahunajaran,
    0,
    1,
    'C'
);

$pdf->Line(
    15,
    $pdf->GetY(),
    195,
    $pdf->GetY()
);

$pdf->Ln(12);

$pdf->SetFont('helvetica', '', 11);

$pdf->MultiCell(
    0,
    6,
    'Pada hari ini ' .
    tanggal_indo($tanggal, 'judul') .
    ' di ' .
    $namasekolahcetak .
    ' telah dilaksanakan ' .
    ucwords(strtolower($asesmen->namaasesmen)) .
    ' dari pukul ' .
    substr($jammulai, 0, 5) .
    ' sampai pukul ' .
    substr($jamselesai, 0, 5) .
    ' dengan rincian sebagai berikut:',
    0,
    'L'
);

$pdf->Ln(3);

$tidakmengerjakan =
    $rekap['S'] +
    $rekap['I'] +
    $rekap['A'] +
    $rekap['D'];

$pdf->Cell(70, 6, 'Ruang');
$pdf->Cell(5, 6, ':');
$pdf->Cell(0, 6, $ruang, 0, 1);

$pdf->Cell(70, 6, 'Sesi');
$pdf->Cell(5, 6, ':');
$pdf->Cell(0, 6, $sesiaktual, 0, 1);

$pdf->Cell(70, 6, 'Jumlah Peserta Seharusnya');
$pdf->Cell(5, 6, ':');
$pdf->Cell(0, 6, $jumlahpeserta, 0, 1);

$pdf->Cell(70, 6, 'Jumlah Hadir (Ikut Ujian)');
$pdf->Cell(5, 6, ':');
$pdf->Cell(0, 6, $rekap['H'], 0, 1);

$pdf->Cell(70, 6, 'Jumlah Tidak Mengerjakan');
$pdf->Cell(5, 6, ':');
$pdf->Cell(0, 6, $tidakmengerjakan, 0, 1);

$daftarnama = empty($namatidakmengerjakan)
    ? '-'
    : implode(', ', $namatidakmengerjakan);

$x = $pdf->GetX();
$y = $pdf->GetY();

$pdf->Cell(70, 6, 'Murid Tidak Mengerjakan');
$pdf->Cell(5, 6, ':');

$pdf->SetXY($x + 75, $y);

$pdf->MultiCell(
    110,
    6,
    $daftarnama,
    0,
    'L'
);

$pdf->Ln(5);

$pdf->SetFont('helvetica', 'B', 11);

$pdf->Cell(
    0,
    6,
    'Catatan Pelaksanaan',
    0,
    1
);

$pdf->SetFont('helvetica', '', 11);

$pdf->MultiCell(
    0,
    25,
    $catatan->catatan ?? '-',
    1,
    'L'
);

$tempatttd = get_config(
    'local_jurnalmengajar',
    'tempat_ttd'
);

$pdf->Ln(10);

$pdf->Cell(
    110,
    6,
    ''
);

$pdf->Cell(
    0,
    6,
    $tempatttd . ', ' .
    tanggal_indo($tanggal, 'tanggal'),
    0,
    1
);

$pdf->Cell(
    110,
    6,
    ''
);

$pdf->Cell(
    0,
    6,
    'Pengawas',
    0,
    1
);

$pdf->Ln(20);

$pdf->Cell(
    110,
    6,
    ''
);

$pdf->Cell(
    0,
    6,
    $USER->lastname,
    0,
    1
);

$pdf->Cell(
    110,
    6,
    ''
);

$pdf->Cell(
    0,
    6,
    'NIP. ' . $nip,
    0,
    1
);

$namafile =
    'Berita_Acara_' .
    str_replace(' ', '_', strtolower(tanggal_indo($tanggal, 'judul'))) .
    '_Sesi_' .
    $sesiaktual .
    '_Ruang_' .
    preg_replace('/[^A-Za-z0-9]/', '_', $ruang) .
    '.pdf';
$pdf->Output(
    $namafile,
    'I'
);

exit;
