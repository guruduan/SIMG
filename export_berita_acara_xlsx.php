<?php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/vendor/autoload.php');

require_login();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$context = context_system::instance();

require_capability(
    'local/jurnalmengajar:submit',
    $context
);

global $DB, $USER;

/*
=========================================
PARAMETER
=========================================
*/

$tanggal    = required_param('tanggal', PARAM_INT);
$sesiaktual = required_param('sesiaktual', PARAM_INT);
$ruang      = required_param('ruang', PARAM_TEXT);

/*
=========================================
AMBIL ASESMEN
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
    print_error('Anda tidak berhak mengakses berita acara ini');
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
PESERTA
=========================================
*/

$sql = "
SELECT
    p.nomeja,
    u.lastname,
    c.name AS kelas,
    d.status
FROM {local_jurnalmengajar_asesmen_peserta} p

JOIN {user} u
     ON u.id = p.userid

LEFT JOIN {cohort} c
       ON c.id = p.kelasid

LEFT JOIN {local_jurnalmengajar_asesmen_detail} d
       ON d.userid = p.userid
      AND d.asesmenid = p.asesmenid
      AND d.tanggal = :tanggal
      AND d.sesiaktual = :sesi

WHERE p.asesmenid = :asesmenid

ORDER BY p.nomeja
";

$peserta = $DB->get_records_sql(
    $sql,
    [
        'tanggal'  => $tanggal,
        'sesi'     => $sesiaktual,
        'asesmenid'=> $asesmen->id
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

foreach ($peserta as $p) {

    $st = $p->status ?: 'H';

    if (isset($rekap[$st])) {
        $rekap[$st]++;
    }
}

/*
=========================================
SPREADSHEET
=========================================
*/

$spreadsheet = new Spreadsheet();

$sheet = $spreadsheet->getActiveSheet();

$sheet->setTitle('Berita Acara');

      
/*
=========================================
HEADER
=========================================
*/

$sheet->mergeCells('A1:E1');

$sheet->setCellValue(
    'A1',
    'BERITA ACARA'
);

$sheet->getStyle('A1')->getFont()
    ->setBold(true)
    ->setSize(16);

$sheet->getStyle('A1')->getAlignment()
    ->setHorizontal(
        Alignment::HORIZONTAL_CENTER
    );

/*
=========================================
NAMA ASESMEN
=========================================
*/

$sheet->mergeCells('A2:E2');

$sheet->setCellValue(
    'A2',
    strtoupper($asesmen->namaasesmen)
);

$sheet->getStyle('A2')->getFont()
    ->setBold(true)
    ->setSize(14);

$sheet->getStyle('A2')->getAlignment()
    ->setHorizontal(
        Alignment::HORIZONTAL_CENTER
    );

/*
=========================================
IDENTITAS
=========================================
*/

$sheet->setCellValue(
    'A4',
    'Tanggal'
);

$sheet->setCellValue(
    'B4',
    ': ' . tanggal_indo($tanggal, 'judul')
);

$sheet->setCellValue(
    'A5',
    'Sesi'
);

$sheet->setCellValue(
    'B5',
    ': ' . $sesiaktual
);

$sheet->setCellValue(
    'A6',
    'Ruang'
);

$sheet->setCellValue(
    'B6',
    ': ' . $ruang
);

$sheet->setCellValue(
    'A7',
    'Pengawas'
);

$sheet->setCellValue(
    'B7',
    ': ' . $USER->lastname
);

$sheet->getStyle('A4:A7')
      ->getFont()
      ->setBold(true);
      
/*
=========================================
HEADER PESERTA
=========================================
*/

$sheet->fromArray(
    [
        'No',
        'Nama',
        'Kelas',
        'Meja',
        'Status'
    ],
    null,
    'A10'
);

$sheet->getStyle('A10:E10')
      ->getFill()
      ->setFillType(Fill::FILL_SOLID)
      ->getStartColor()
      ->setRGB('D9D9D9');

$sheet->getStyle('A10:E10')
      ->getFont()
      ->setBold(true);

$sheet->getStyle('A10:E10')
      ->getAlignment()
      ->setHorizontal(
          Alignment::HORIZONTAL_CENTER
      );
    
$row = 11;
$no  = 1;

$statusmap = [
    'H' => 'Hadir',
    'S' => 'Sakit',
    'I' => 'Izin',
    'D' => 'Dispensasi',
    'A' => 'Alpa'
];

foreach ($peserta as $p) {

    $status = $p->status ?: 'H';

    $sheet->fromArray(
        [
            $no++,
            ucwords(strtolower($p->lastname)),
            $p->kelas,
            $p->nomeja,
            $statusmap[$status] ?? $status
        ],
        null,
        'A' . $row
    );

    $row++;
}

$akhirpeserta = $row - 1;

/*
=========================================
CATATAN
=========================================
*/

$row += 2;

$sheet->setCellValue(
    'A' . $row,
    'Catatan Pelaksanaan'
);

$sheet->getStyle('A'.$row)
      ->getFont()
      ->setBold(true);
      
$row++;

$sheet->mergeCells(
    'A' . $row .
    ':E' . $row
);

$sheet->setCellValue(
    'A' . $row,
    $catatan->catatan ?? ''
);

$sheet->getStyle(
    'A' . $row
)->getAlignment()->setWrapText(true);

$sheet->getRowDimension($row)
      ->setRowHeight(40);

/*
==============
REKAP
==============
*/

$row += 6;

$sheet->setCellValue(
    'A' . $row,
    'REKAP KEHADIRAN'
);

$sheet->getStyle('A'.$row)
      ->getFont()
      ->setBold(true);
      
$row++;

$sheet->setCellValue('A'.$row, 'Hadir');
$sheet->setCellValue('B'.$row, $rekap['H']);

$row++;

$sheet->setCellValue('A'.$row, 'Sakit');
$sheet->setCellValue('B'.$row, $rekap['S']);

$row++;

$sheet->setCellValue('A'.$row, 'Izin');
$sheet->setCellValue('B'.$row, $rekap['I']);

$row++;

$sheet->setCellValue('A'.$row, 'Dispensasi');
$sheet->setCellValue('B'.$row, $rekap['D']);

$row++;

$sheet->setCellValue('A'.$row, 'Alpa');
$sheet->setCellValue('B'.$row, $rekap['A']);

/*
=========================================
BORDER
=========================================
*/

$sheet->getStyle(
    'A10:E' . $akhirpeserta
)->getBorders()->getAllBorders()
 ->setBorderStyle(Border::BORDER_THIN);

$sheet->getColumnDimension('A')->setWidth(10);
$sheet->getColumnDimension('B')->setWidth(32);
$sheet->getColumnDimension('C')->setWidth(12);
$sheet->getColumnDimension('D')->setWidth(8);
$sheet->getColumnDimension('E')->setWidth(15);

/*
===============
bagian paling bawah
===============
*/

$tempatttd = get_config(
    'local_jurnalmengajar',
    'tempat_ttd'
);

if (empty($tempatttd)) {
    $tempatttd = '-';
}

$row += 3;

$sheet->setCellValue(
    'D' . $row,
    $tempatttd . ', ' .
    tanggal_indo($tanggal, 'tanggal')
);

$row++;

$sheet->setCellValue(
    'D' . $row,
    'Pengawas'
);

$row += 4;

$sheet->setCellValue(
    'D' . $row,
    $USER->lastname
);

/*
=========================================
DOWNLOAD
=========================================
*/

$filename =
    'Berita_Acara_' .
    date('Ymd', $tanggal) .
    '_Sesi_' .
    $sesiaktual .
    '_' .
    preg_replace('/[^A-Za-z0-9]/', '_', $ruang) .
    '.xlsx';

header(
    'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
);

header(
    'Content-Disposition: attachment; filename="' . $filename . '"'
);

$writer = new Xlsx($spreadsheet);

$writer->save('php://output');

exit;
