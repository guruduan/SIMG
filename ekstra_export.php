<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/ekstra_lib.php');
require_once(__DIR__ . '/vendor/autoload.php');

require_login();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

global $DB, $USER;

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

/*
|--------------------------------------------------------------------------
| IDENTITAS SEKOLAH
|--------------------------------------------------------------------------
*/

$namasekolah = get_config('local_jurnalmengajar', 'nama_sekolah');
$tahunajaran = get_config('local_jurnalmengajar', 'tahun_ajaran');
$tempat      = get_config('local_jurnalmengajar', 'tempat_ttd');
$namakepsek  = get_config('local_jurnalmengajar', 'nama_kepsek');
$nipkepsek   = get_config('local_jurnalmengajar', 'nip_kepsek');

$namapembina = trim($USER->firstname . ' ' . $USER->lastname);

$nipguru = $DB->get_field('user_info_data', 'data', [
    'userid' => $USER->id,
    'fieldid' => $DB->get_field('user_info_field', 'id', [
        'shortname' => 'nip'
    ])
]) ?? '**belum diisi**';

/*
|--------------------------------------------------------------------------
| DATA
|--------------------------------------------------------------------------
*/

$data = $DB->get_records_sql("
    SELECT
        j.*,
        e.namaekstra
    FROM {local_ekstra_jurnal} j
    JOIN {local_jm_ekstra} e
        ON e.id = j.ekstraid
    WHERE j.pembinaid = ?
    ORDER BY j.tanggal DESC, j.id DESC
", [$USER->id]);

$first = reset($data);

$namaekstra = $first
    ? $first->namaekstra
    : '-';

/*
|--------------------------------------------------------------------------
| SPREADSHEET
|--------------------------------------------------------------------------
*/

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Jurnal Ekstra');

/*
|--------------------------------------------------------------------------
| JUDUL
|--------------------------------------------------------------------------
*/

$sheet->mergeCells('A1:G1')
      ->setCellValue('A1', 'JURNAL EKSTRAKURIKULER');

$sheet->mergeCells('A2:G2')
      ->setCellValue('A2', strtoupper($namasekolah));

$sheet->mergeCells('A3:G3')
      ->setCellValue('A3', 'TAHUN AJARAN ' . $tahunajaran);

$sheet->getStyle('A1:G3')
      ->getAlignment()
      ->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->getStyle('A1:G3')
      ->getFont()
      ->setBold(true)
      ->setSize(14);

/*
|--------------------------------------------------------------------------
| IDENTITAS
|--------------------------------------------------------------------------
*/

$sheet->setCellValue('B5', 'Nama Pembina:');
$sheet->setCellValue('C5', $namapembina);

$sheet->setCellValue('B6', 'Nama Ekstrakurikuler:');
$sheet->setCellValue('C6', $namaekstra);

/*
|--------------------------------------------------------------------------
| HEADER TABEL
|--------------------------------------------------------------------------
*/

$headers = [
    'No',
    'Tanggal',
    'Ekstrakurikuler',
    'Materi',
    'Aktivitas',
    'Catatan',
    'Absensi'
];

$sheet->fromArray($headers, NULL, 'A8');

$sheet->freezePane('A9');

$sheet->getStyle('A8:G8')
      ->getFont()
      ->setBold(true);

$sheet->getStyle('A8:G8')
      ->getAlignment()
      ->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->getStyle('A8:G8')
      ->getAlignment()
      ->setWrapText(true);

$sheet->getStyle('A8:G8')
      ->getBorders()
      ->getAllBorders()
      ->setBorderStyle(Border::BORDER_THIN);

$sheet->getStyle('A8:G8')
      ->getFill()
      ->setFillType(Fill::FILL_SOLID)
      ->getStartColor()
      ->setRGB('E0FFFF');

/*
|--------------------------------------------------------------------------
| UKURAN KOLOM
|--------------------------------------------------------------------------
*/

$sheet->getColumnDimension('A')->setWidth(5);
$sheet->getColumnDimension('B')->setWidth(18);
$sheet->getColumnDimension('C')->setWidth(25);
$sheet->getColumnDimension('D')->setWidth(30);
$sheet->getColumnDimension('E')->setWidth(35);
$sheet->getColumnDimension('F')->setWidth(30);
$sheet->getColumnDimension('G')->setWidth(40);

/*
|--------------------------------------------------------------------------
| DATA ISI
|--------------------------------------------------------------------------
*/

$row = 9;
$no  = 1;

foreach ($data as $d) {

    $absensi = strip_tags(
        str_replace('<br>', ', ', ekstra_format_absensi($d->id))
    );

    $sheet->fromArray([
        $no++,
        tanggal_indo($d->tanggal, 'judul'),
        $d->namaekstra,
        $d->materi ?: '-',
        $d->aktivitas ?: '-',
        $d->catatan ?: '-',
        $absensi
    ], NULL, "A{$row}");

    $sheet->getStyle("A{$row}:G{$row}")
          ->getBorders()
          ->getAllBorders()
          ->setBorderStyle(Border::BORDER_THIN);

    $row++;
}

/*
|--------------------------------------------------------------------------
| ALIGNMENT
|--------------------------------------------------------------------------
*/

$lastrow = $row - 1;

$sheet->getStyle("A9:G{$lastrow}")
      ->getAlignment()
      ->setVertical(Alignment::VERTICAL_TOP);

$sheet->getStyle("A9:A{$lastrow}")
      ->getAlignment()
      ->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->getStyle("B9:B{$lastrow}")
      ->getAlignment()
      ->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->getStyle("D9:G{$lastrow}")
      ->getAlignment()
      ->setWrapText(true);

/*
|--------------------------------------------------------------------------
| TANDA TANGAN
|--------------------------------------------------------------------------
*/

$row += 2;

$sheet->setCellValue("B{$row}", 'Mengetahui');
$sheet->setCellValue(
    "F{$row}",
    $tempat . ', ' . tanggal_indo(time(), 'tanggal')
);

$row++;

$sheet->setCellValue(
    "B{$row}",
    'Kepala ' . $namasekolah
);

$sheet->setCellValue(
    "F{$row}",
    'Pembina Ekstrakurikuler'
);

$row += 4;

$sheet->setCellValue("B{$row}", $namakepsek);
$sheet->setCellValue("F{$row}", $namapembina);

$row++;

$sheet->setCellValue("B{$row}", 'NIP ' . $nipkepsek);
$sheet->setCellValue("F{$row}", 'NIP ' . $nipguru);

/*
|--------------------------------------------------------------------------
| OUTPUT
|--------------------------------------------------------------------------
*/

$filename = 'jurnal_ekstra_' . date('Ymd_His') . '.xlsx';

$filename = clean_filename($filename);

ob_clean();
flush();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

exit;
