<?php
require_once(__DIR__ . '/../../config.php');
require_login();
require_sesskey(); // 🔐 penting untuk keamanan form

global $DB, $USER;

// =======================
// AMBIL DATA FORM
// =======================
$ekstraid = required_param('ekstraid', PARAM_INT);

// textarea → RAW
$materi   = required_param('materi', PARAM_RAW);
$kegiatan = optional_param('kegiatan', '', PARAM_RAW);
$catatan  = optional_param('catatan', '', PARAM_RAW);

// tanggal (YYYY-MM-DD)
$tanggalp = optional_param('tanggal', '', PARAM_TEXT);

// status array (manual sanitasi)
$status = optional_param_array('status', [], PARAM_TEXT);

// =======================
// VALIDASI
// =======================
if (empty(trim($materi))) {
    redirect(
        new moodle_url('/local/jurnalmengajar/jurnal_ekstra.php', ['ekstraid' => $ekstraid]),
        'Materi tidak boleh kosong',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// =======================
// FORMAT TANGGAL (AMAN)
// =======================
if (!empty($tanggalp)) {
    $timestamp = strtotime($tanggalp);
    $tanggal   = $timestamp ? $timestamp : time();
} else {
    $tanggal = time();
}

$time = time();

// =======================
// TRANSACTION START
// =======================
$transaction = $DB->start_delegated_transaction();

try {

    // =======================
    // SIMPAN JURNAL
    // =======================
    $jurnal = new stdClass();
    $jurnal->ekstraid    = $ekstraid;
    $jurnal->tanggal     = $tanggal;
    $jurnal->pembinaid   = $USER->id;
    $jurnal->materi      = trim($materi);
    $jurnal->kegiatan    = trim($kegiatan);
    $jurnal->catatan     = trim($catatan);
    $jurnal->timecreated = $time;

    $jurnalid = $DB->insert_record('local_jm_ekstra_jurnal', $jurnal);

    // =======================
    // SIMPAN ABSENSI
    // =======================
    foreach ($status as $userid => $st) {

        $userid = (int)$userid;

        // skip kalau user tidak valid
        if ($userid <= 0) {
            continue;
        }

        // ambil cohort (lebih efisien: hanya field perlu)
        $cohortid = $DB->get_field(
            'local_jm_ekstra_peserta',
            'cohortid',
            ['userid' => $userid, 'ekstraid' => $ekstraid]
        );

        $absen = new stdClass();
        $absen->jurnalid   = $jurnalid;
        $absen->userid     = $userid;
        $absen->status     = trim($st);
        $absen->cohortid   = $cohortid ?: 0;
        $absen->keterangan = '';

        $DB->insert_record('local_jm_ekstra_absen', $absen);
    }

    // =======================
    // COMMIT
    // =======================
    $transaction->allow_commit();

} catch (Throwable $e) {

    $transaction->rollback($e);

    redirect(
        new moodle_url('/local/jurnalmengajar/jurnal_ekstra.php', ['ekstraid' => $ekstraid]),
        'Gagal menyimpan jurnal',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// =======================
// REDIRECT SUKSES
// =======================
redirect(
    new moodle_url('/local/jurnalmengajar/jurnal_ekstra.php', ['ekstraid' => $ekstraid]),
    'Jurnal ekstrakurikuler berhasil disimpan',
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
