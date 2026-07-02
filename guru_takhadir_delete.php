<?php
require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$id = required_param('id', PARAM_INT);

$record = $DB->get_record(
    'local_jurnalmengajar_kehadiran',
    ['id' => $id],
    '*',
    MUST_EXIST
);

require_sesskey();

$DB->delete_records(
    'local_jurnalmengajar_kehadiran',
    ['id' => $id]
);

redirect(
    new moodle_url('/local/jurnalmengajar/guru_takhadir.php'),
    'Data guru tidak hadir berhasil dihapus.',
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
