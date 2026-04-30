<?php
require_once(__DIR__ . '/../../config.php');
require_login();
require_sesskey();

global $DB;

$context = context_system::instance();

// =======================
// CEK PERMISSION (WAJIB DI ATAS)
// =======================
if (!has_capability('moodle/site:config', $context)) {
    redirect(
        new moodle_url('/local/jurnalmengajar/riwayat_jurnal_ekstra.php'),
        'Anda tidak memiliki izin menghapus data',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// =======================
// AMBIL PARAM
// =======================
$id = required_param('id', PARAM_INT);

// =======================
// CEK DATA ADA
// =======================
if (!$DB->record_exists('local_jm_ekstra_jurnal', ['id' => $id])) {
    print_error('Data tidak ditemukan');
}

// =======================
// TRANSACTION
// =======================
$transaction = $DB->start_delegated_transaction();

try {

    // hapus child dulu
    $DB->delete_records('local_jm_ekstra_absen', ['jurnalid' => $id]);

    // hapus parent
    $DB->delete_records('local_jm_ekstra_jurnal', ['id' => $id]);

    $transaction->allow_commit();

} catch (Exception $e) {

    $transaction->rollback($e);

    redirect(
        new moodle_url('/local/jurnalmengajar/riwayat_jurnal_ekstra.php'),
        'Gagal menghapus data',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// =======================
// REDIRECT SUKSES
// =======================
redirect(
    new moodle_url('/local/jurnalmengajar/riwayat_jurnal_ekstra.php'),
    'Jurnal berhasil dihapus',
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
