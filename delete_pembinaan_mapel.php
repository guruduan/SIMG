<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$id = required_param('id', PARAM_INT);
require_sesskey();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

// Ambil data pembinaan
$record = $DB->get_record(
    'local_jurnalmengajar_pembinaanmapel',
    ['id' => $id],
    '*',
    MUST_EXIST
);

// Hapus data
$DB->delete_records(
    'local_jurnalmengajar_pembinaanmapel',
    ['id' => $id]
);

// Kembali ke halaman daftar
redirect(
    new moodle_url('/local/jurnalmengajar/all_pembinaan_mapel.php'),
    '✅ Data pembinaan berhasil dihapus.',
    2
);
