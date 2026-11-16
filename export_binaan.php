<?php
require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

global $CFG;

$csvfile = $CFG->dataroot . '/binaan.csv';

if (!file_exists($csvfile)) {
    print_error('File binaan.csv tidak ditemukan');
}

$filename = 'murid_binaan_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

// BOM UTF-8 supaya Excel tidak rusak.
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

$fp = fopen($csvfile, 'r');

while (($row = fgetcsv($fp)) !== false) {
    fputcsv($out, $row);
}

fclose($fp);
fclose($out);
exit;
