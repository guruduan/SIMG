<?php
require('../../config.php');

require_once(__DIR__.'/vendor/autoload.php');
require_once(__DIR__.'/jadwal_acuan_lib.php');
require_once(__DIR__.'/jam_pelajaran_lib.php');
require_once(__DIR__.'/lib.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

/*
=====================================================
1. AMBIL DATA HARI
=====================================================
*/

$jadwal = jurnalmengajar_get_jadwal_acuan();
$hariurut = jurnalmengajar_get_urutan_hari();

$daftarhari = [];

foreach ($jadwal as $j) {

    if (!empty($j['hari'])) {
        $daftarhari[$j['hari']] = $j['hari'];
    }
}

uksort($daftarhari, function($a, $b) use ($hariurut) {

    return ($hariurut[$a] ?? 9)
        <=>
        ($hariurut[$b] ?? 9);

});

$hari_ini = jurnalmengajar_get_hari_ini();

$default_hari = isset($daftarhari[$hari_ini])
    ? $hari_ini
    : array_key_first($daftarhari);

$filterhari = optional_param(
    'hari',
    $default_hari,
    PARAM_TEXT
);

/*
=====================================================
2. JAM PELAJARAN
=====================================================
*/

$jam_pelajaran = jurnalmengajar_generate_jam_hari(
    $filterhari
);

$jumlahjp = count($jam_pelajaran);

if ($jumlahjp == 0) {
    print_error(
        'Tidak ada konfigurasi jam pelajaran.'
    );
}

/*
=====================================================
3. BANGUN MATRIKS JADWAL
=====================================================
*/

$matriks_jadwal = [];
$semua_kelas = [];

foreach ($jadwal as $j) {

    if ($j['hari'] != $filterhari) {
        continue;
    }

    $kelas = trim($j['kelas']);
    $jamke = (int)$j['jamke'];

    $semua_kelas[$kelas] = $kelas;

    $matriks_jadwal[$kelas][$jamke] = $j['lastname'];
}

asort($semua_kelas);

/*
=====================================================
4. IDENTITAS SEKOLAH
=====================================================
*/

$namasekolah = get_config(
    'local_jurnalmengajar',
    'nama_sekolah'
);

if (empty($namasekolah)) {
    $namasekolah = 'Sekolah';
}

$tahunajaran = get_config(
    'local_jurnalmengajar',
    'tahunajaran'
);

/*
=====================================================
5. BUAT SPREADSHEET
=====================================================
*/

$spreadsheet = new Spreadsheet();

$sheet = $spreadsheet->getActiveSheet();

$sheet->setTitle('Jadwal '.$filterhari);

/*
=====================================================
6. JUDUL LAPORAN
=====================================================
*/

$lastcolumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($jumlahjp + 2);

$sheet->mergeCells('A1:'.$lastcolumn.'1');
$sheet->mergeCells('A2:'.$lastcolumn.'2');
$sheet->mergeCells('A3:'.$lastcolumn.'3');

$sheet->setCellValue('A1', $namasekolah);

$sheet->setCellValue(
    'A2',
    'JADWAL MENGAJAR PER HARI'
);

$sheet->setCellValue(
    'A3',
    'Hari : '.$filterhari.
    (!empty($tahunajaran)
        ? ' | Tahun Ajaran : '.$tahunajaran
        : '')
);

/*
=====================================================
7. HEADER TABEL
=====================================================
*/

$rowheader = 5;

$sheet->setCellValue(
    'A'.$rowheader,
    'No'
);

$sheet->setCellValue(
    'B'.$rowheader,
    'Kelas'
);

$col = 3;

for ($jp = 1; $jp <= $jumlahjp; $jp++) {

    $huruf =
        \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);

    $sheet->setCellValue(
        $huruf.$rowheader,
        "Jam\nke ".$jp
    );

    $col++;
}

/*
=====================================================
8. ISI DATA
=====================================================
*/

$row = $rowheader + 1;

$no = 1;

foreach ($semua_kelas as $kelas) {

    $sheet->setCellValue(
        'A'.$row,
        $no++
    );

    $sheet->setCellValue(
        'B'.$row,
        $kelas
    );

    $col = 3;

    for ($jp = 1; $jp <= $jumlahjp; $jp++) {

        $huruf =
            \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);

        $guru = '';

        if (isset($matriks_jadwal[$kelas][$jp])) {
            $guru = $matriks_jadwal[$kelas][$jp];
        }

        $sheet->setCellValue(
            $huruf.$row,
            $guru
        );

        $col++;
    }

    $row++;
}

if (empty($semua_kelas)) {

    $sheet->mergeCells(
        'A'.$row.':'.$lastcolumn.$row
    );

    $sheet->setCellValue(
        'A'.$row,
        'Tidak ada data jadwal.'
    );

    $row++;
}

$lastrow = $row - 1;

/*
=====================================================
6. JUDUL LAPORAN
=====================================================
*/

$lastcolumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($jumlahjp + 2);

$sheet->mergeCells('A1:'.$lastcolumn.'1');
$sheet->mergeCells('A2:'.$lastcolumn.'2');
$sheet->mergeCells('A3:'.$lastcolumn.'3');

$sheet->setCellValue('A1', $namasekolah);

$sheet->setCellValue(
    'A2',
    'JADWAL MENGAJAR PER HARI'
);

$sheet->setCellValue(
    'A3',
    'Hari : '.$filterhari.
    (!empty($tahunajaran)
        ? ' | Tahun Ajaran : '.$tahunajaran
        : '')
);

/*
=====================================================
7. HEADER TABEL
=====================================================
*/

$rowheader = 5;

$sheet->setCellValue(
    'A'.$rowheader,
    'No'
);

$sheet->setCellValue(
    'B'.$rowheader,
    'Kelas'
);

$col = 3;

for ($jp = 1; $jp <= $jumlahjp; $jp++) {

    $huruf =
        \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);

    $sheet->setCellValue(
        $huruf.$rowheader,
        'Jam ke '.$jp
    );

    $col++;
}

/*
=====================================================
8. ISI DATA
=====================================================
*/

$row = $rowheader + 1;

$no = 1;

foreach ($semua_kelas as $kelas) {

    $sheet->setCellValue(
        'A'.$row,
        $no++
    );

    $sheet->setCellValue(
        'B'.$row,
        $kelas
    );

    $col = 3;

    for ($jp = 1; $jp <= $jumlahjp; $jp++) {

        $huruf =
            \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);

        $guru = '';

        if (isset($matriks_jadwal[$kelas][$jp])) {
            $guru = $matriks_jadwal[$kelas][$jp];
        }

        $sheet->setCellValue(
            $huruf.$row,
            $guru
        );

        $col++;
    }

    $row++;
}

if (empty($semua_kelas)) {

    $sheet->mergeCells(
        'A'.$row.':'.$lastcolumn.$row
    );

    $sheet->setCellValue(
        'A'.$row,
        'Tidak ada data jadwal.'
    );

    $row++;
}

$lastrow = $row - 1;

/*
=====================================================
9. STYLE JUDUL
=====================================================
*/

$sheet->getStyle('A1')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 16
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER
    ]
]);

$sheet->getStyle('A2')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 14
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER
    ]
]);

$sheet->getStyle('A3')->applyFromArray([
    'font' => [
        'italic' => true,
        'size' => 11
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER
    ]
]);

/*
=====================================================
10. STYLE HEADER
=====================================================
*/

$sheet->getStyle(
    'A'.$rowheader.':'.$lastcolumn.$rowheader
)->applyFromArray([

    'font' => [
        'bold' => true,
        'color' => [
            'rgb' => 'FFFFFF'
        ]
    ],

    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => [
            'rgb' => '4472C4'
        ]
    ],

    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
        'wrapText'   => true
    ]

]);

/*
=====================================================
11. BORDER TABEL
=====================================================
*/

$sheet->getStyle(
    'A'.$rowheader.':'.$lastcolumn.$lastrow
)->applyFromArray([

    'borders' => [

        'allBorders' => [

            'borderStyle' => Border::BORDER_THIN

        ]

    ]

]);

/*
=====================================================
12. RATA TENGAH KOLOM
=====================================================
*/

$sheet->getStyle(
    'A'.$rowheader.':'.$lastcolumn.$lastrow
)->getAlignment()->setVertical(
    Alignment::VERTICAL_CENTER
);

$sheet->getStyle(
    'A'.$rowheader.':B'.$lastrow
)->getAlignment()->setHorizontal(
    Alignment::HORIZONTAL_CENTER
);

/*
=====================================================
12A. FORMAT KOLOM GURU
=====================================================
*/

$sheet->getStyle(
    'C'.($rowheader + 1).':'.$lastcolumn.$lastrow
)->applyFromArray([
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
        'wrapText'   => true
    ]
]);


/*
=====================================================
13. AUTO WIDTH
=====================================================
*/

// Kolom No
$sheet->getColumnDimension('A')->setWidth(6);

// Kolom Kelas
$sheet->getColumnDimension('B')->setWidth(12);

// Kolom Jam ke
for ($i = 3; $i <= ($jumlahjp + 2); $i++) {

    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);

    $sheet->getColumnDimension($col)
        ->setWidth(17);   // sekitar setengah AutoSize
}

/*
=====================================================
14. FREEZE PANE
=====================================================
*/

$sheet->freezePane('C6');

/*
=====================================================
15. TINGGI HEADER
=====================================================
*/

$sheet->getRowDimension($rowheader)
    ->setRowHeight(24);

/*
=====================================================
16. SET PRINT
=====================================================
*/

$sheet->getPageSetup()
    ->setOrientation(
        \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE
    );

$sheet->getPageSetup()
    ->setPaperSize(
        \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3
    );

$sheet->getPageSetup()
    ->setFitToWidth(1);

$sheet->getPageSetup()
    ->setFitToHeight(0);

$sheet->getPageMargins()->setTop(0.4);
$sheet->getPageMargins()->setBottom(0.4);
$sheet->getPageMargins()->setLeft(0.3);
$sheet->getPageMargins()->setRight(0.3);

/*
=====================================================
17. AREA CETAK
=====================================================
*/

$sheet->getPageSetup()->setPrintArea(
    'A1:'.$lastcolumn.$lastrow
);

/*
=====================================================
18. HEADER CETAK BERULANG
=====================================================
*/

$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(
    $rowheader,
    $rowheader
);

/*
=====================================================
19. NAMA FILE
=====================================================
*/

$namafile = 'jadwal_perhari_' .
    strtolower(str_replace(' ', '_', $filterhari)) .
    '_' .
    date('Ymd_His') .
    '.xlsx';

/*
=====================================================
20. PROPERTI DOKUMEN
=====================================================
*/

$spreadsheet->getProperties()
    ->setCreator(fullname($USER))
    ->setLastModifiedBy(fullname($USER))
    ->setTitle('Jadwal Mengajar Per Hari')
    ->setSubject('Jadwal Mengajar')
    ->setDescription(
        'Export Jadwal Mengajar Per Hari'
    );

/*
=====================================================
21. AKHIRI OUTPUT BUFFER
=====================================================
*/

while (ob_get_level()) {
    ob_end_clean();
}

/*
=====================================================
22. HEADER DOWNLOAD
=====================================================
*/

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$namafile.'"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

/*
=====================================================
23. OUTPUT XLSX
=====================================================
*/

$writer = new Xlsx($spreadsheet);

$writer->setPreCalculateFormulas(false);

$writer->save('php://output');

$spreadsheet->disconnectWorksheets();

unset($spreadsheet);

exit;

