<?php
require('../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$id = required_param('id', PARAM_INT);

global $DB;

// Pastikan data ada.
if (!$DB->record_exists(
    'local_jurnalmengajar_guruwali',
    ['id' => $id]
)) {

    throw new moodle_exception(
        'Data Guru Wali tidak ditemukan.'
    );
}

// Hapus relasi guru wali.
$DB->delete_records(
    'local_jurnalmengajar_guruwali',
    ['id' => $id]
);

// Kembali ke halaman daftar.
redirect(
    new moodle_url('/local/jurnalmengajar/guruwali_manage.php'),
    'Data Guru Wali berhasil dihapus.',
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
