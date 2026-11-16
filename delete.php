<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$id = required_param('id', PARAM_INT);
$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

global $DB, $USER;

$record = $DB->get_record('local_jurnalmengajar', [
    'id' => $id
], '*', MUST_EXIST);
$DB->delete_records('local_jurnalmengajar', ['id' => $id]);

redirect(new moodle_url('/local/jurnalmengajar/all_jurnal.php', [
    'guru' => optional_param('guru', '', PARAM_TEXT),
    'kelas' => optional_param('kelas', '', PARAM_TEXT),
    'mapel' => optional_param('mapel', '', PARAM_TEXT),
    'tanggal' => optional_param('tanggal', '', PARAM_TEXT),
    'bulan' => optional_param('bulan', '', PARAM_INT),
    'tahun' => optional_param('tahun', date('Y'), PARAM_INT),
    'page' => optional_param('page', 0, PARAM_INT)
]), 'Entri berhasil dihapus.', 2);
