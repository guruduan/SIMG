<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

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
    'local/jurnalmengajar:view',
    $context
);

global $DB, $USER;

/* =========================
   Kelas Wali
========================= */

$kelasid = jurnalmengajar_get_kelas_wali(
    $USER->id
);

if (!$kelasid) {
    die('Anda bukan wali kelas.');
}

$kelasnama = get_nama_kelas($kelasid);

/* =========================
   Parameter
========================= */

$bulan = required_param(
    'bulan',
    PARAM_TEXT
);

$tahun = required_param(
    'tahun',
    PARAM_INT
);

$bulanmap = [
    '01'=>'Januari',
    '02'=>'Februari',
    '03'=>'Maret',
    '04'=>'April',
    '05'=>'Mei',
    '06'=>'Juni',
    '07'=>'Juli',
    '08'=>'Agustus',
    '09'=>'September',
    '10'=>'Oktober',
    '11'=>'November',
    '12'=>'Desember'
];

$namabulan = $bulanmap[$bulan] ?? '';

$filename =
    'Jurnal_Wali_Kelas_' .
    $kelasnama . '_' .
    $namabulan . '_' .
    $tahun . '.xlsx';

/* =========================
   Setting Sekolah
========================= */

$namasekolah = get_config(
    'local_jurnalmengajar',
    'nama_sekolah'
);

$tahunajaran = get_config(
    'local_jurnalmengajar',
    'tahun_ajaran'
);

$tempat = get_config(
    'local_jurnalmengajar',
    'tempat_ttd'
);

$namakepsek = get_config(
    'local_jurnalmengajar',
    'nama_kepsek'
);

$nipkepsek = get_config(
    'local_jurnalmengajar',
    'nip_kepsek'
);

/* =========================
   Data Wali Kelas
========================= */

$namawali = $USER->lastname;

$fieldidnip = $DB->get_field(
    'user_info_field',
    'id',
    ['shortname' => 'nip']
);

$nipwali = $DB->get_field(
    'user_info_data',
    'data',
    [
        'userid' => $USER->id,
        'fieldid' => $fieldidnip
    ]
) ?? '-';

/* =========================
   Ambil Data
========================= */

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

$entries = $DB->get_records_sql(
    "
    SELECT *
    FROM {local_jurnalwalikelas}
    WHERE kelas = ?
      AND timecreated BETWEEN ? AND ?
    ORDER BY timecreated ASC
    ",
    [
        $kelasid,
        $awal,
        $akhir
    ]
);

/* =========================
   Spreadsheet
========================= */

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

/* =========================
   Judul
========================= */

$sheet->mergeCells('A1:G1');
$sheet->setCellValue(
    'A1',
    'JURNAL WALI KELAS'
);

$sheet->mergeCells('A2:G2');
$sheet->setCellValue(
    'A2',
    strtoupper($namasekolah)
);

$sheet->mergeCells('A3:G3');
$sheet->setCellValue(
    'A3',
    'TAHUN AJARAN ' . $tahunajaran
);

$sheet->getStyle('A1:G3')
    ->getFont()
    ->setBold(true);

$sheet->getStyle('A1:G3')
    ->getAlignment()
    ->setHorizontal(
        Alignment::HORIZONTAL_CENTER
    );

/* =========================
   Identitas
========================= */

$sheet->setCellValue(
    'B5',
    'Nama Wali Kelas:'
);

$sheet->setCellValue(
    'C5',
    $namawali
);

$sheet->setCellValue(
    'B6',
    'Kelas:'
);

$sheet->setCellValue(
    'C6',
    $kelasnama
);

$sheet->setCellValue(
    'B7',
    'Bulan:'
);

$sheet->setCellValue(
    'C7',
    $namabulan . ' ' . $tahun
);

/* =========================
   Header Tabel
========================= */

$header = [
    'No',
    'Tanggal',
    'Jenis',
    'Murid',
    'Topik / Permasalahan',
    'Tindak Lanjut',
    'Uraian Kegiatan'
];

$sheet->fromArray(
    $header,
    null,
    'A9'
);

$sheet->getStyle('A9:G9')
    ->getFont()
    ->setBold(true);
$sheet->getStyle('A9:G9')
    ->getAlignment()
    ->setHorizontal(
        Alignment::HORIZONTAL_CENTER
    );

$sheet->getStyle('A9:G9')
    ->getAlignment()
    ->setVertical(
        Alignment::VERTICAL_CENTER
    );
$sheet->getStyle('A9:G9')
    ->getBorders()
    ->getAllBorders()
    ->setBorderStyle(
        Border::BORDER_THIN
    );

$sheet->getStyle('A9:G9')
    ->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()
    ->setRGB('E0FFFF');

/* =========================
   Isi Data
========================= */

$row = 10;
$no = 1;

foreach ($entries as $e) {

    $murid = 'Kelas ' . $kelasnama;

if (!empty($e->muridid)) {

    $murid = $DB->get_field(
        'user',
        'lastname',
        ['id' => $e->muridid]
    );

    if ($murid) {
        $murid = format_nama_siswa($murid);
    } else {
        $murid = 'Kelas ' . $kelasnama;
    }
}

    $sheet->fromArray([
        $no++,
        tanggal_indo($e->timecreated, 'judul'),
        ucfirst($e->jenis),
        $murid,
        $e->topik,
        $e->tindaklanjut,
        $e->uraian
    ], null, "A{$row}");

    $sheet->getStyle(
        "A{$row}:G{$row}"
    )->getBorders()
     ->getAllBorders()
     ->setBorderStyle(
         Border::BORDER_THIN
     );

    $row++;
}

/* =========================
   Format Isi Tabel
========================= */

$sheet->getStyle(
    'E10:G' . ($row - 1)
)->getAlignment()->setWrapText(true);

$sheet->getStyle(
    'A10:G' . ($row - 1)
)->getAlignment()->setVertical(
    Alignment::VERTICAL_TOP
);

$sheet->getStyle(
    'A10:C' . ($row - 1)
)->getAlignment()->setHorizontal(
    Alignment::HORIZONTAL_CENTER
);

/* =========================
   Lebar Kolom
========================= */

$sheet->getColumnDimension('A')->setWidth(5);   // No
$sheet->getColumnDimension('B')->setWidth(22);  // Tanggal
$sheet->getColumnDimension('C')->setWidth(18);  // Jenis
$sheet->getColumnDimension('D')->setWidth(25);  // Murid
$sheet->getColumnDimension('E')->setWidth(30);  // Topik
$sheet->getColumnDimension('F')->setWidth(40);  // Tindak lanjut
$sheet->getColumnDimension('G')->setWidth(45);  // Uraian

/* =========================
   Tanda Tangan
========================= */

$row += 2;

$sheet->setCellValue(
    "B{$row}",
    'Mengetahui'
);

$sheet->setCellValue(
    "F{$row}",
    $tempat . ', ' .
    tanggal_indo(time(), 'tanggal')
);

$row++;

$sheet->setCellValue(
    "B{$row}",
    'Kepala Sekolah'
);

$sheet->setCellValue(
    "F{$row}",
    'Wali Kelas'
);

$row += 4;

$sheet->setCellValue(
    "B{$row}",
    $namakepsek
);

$sheet->setCellValue(
    "F{$row}",
    $namawali
);

$row++;

$sheet->setCellValue(
    "B{$row}",
    'NIP ' . $nipkepsek
);

$sheet->setCellValue(
    "F{$row}",
    'NIP ' . $nipwali
);

/* =========================
   Output
========================= */

header(
    'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
);

header(
    'Content-Disposition: attachment; filename="' .
    $filename . '"'
);

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
