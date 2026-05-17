<?php

defined('MOODLE_INTERNAL') || die();

function ekstra_get_pembina_ekstra($userid) {
    global $DB;

    return $DB->get_records_sql("
        SELECT e.*
        FROM {local_jm_ekstra} e
        JOIN {local_jm_ekstra_pembina} p
            ON p.ekstraid = e.id
        WHERE p.userid = ?
        ORDER BY e.namaekstra ASC
    ", [$userid]);
}

function ekstra_get_riwayat($userid, $limit = 20) {
    global $DB;

    return $DB->get_records_sql("
        SELECT j.*, e.namaekstra
        FROM {local_ekstra_jurnal} j
        JOIN {local_jm_ekstra} e
            ON e.id = j.ekstraid
        WHERE j.pembinaid = ?
        ORDER BY j.tanggal DESC, j.id DESC
    ", [$userid], 0, $limit);
}
function ekstra_get_peserta($ekstraid) {
    global $DB;

    return $DB->get_records_sql("
        SELECT
            p.userid,
            u.firstname,
            u.lastname
        FROM {local_jm_ekstra_peserta} p
        JOIN {user} u
            ON u.id = p.userid
        WHERE p.ekstraid = ?
        ORDER BY u.lastname ASC
    ", [$ekstraid]);
}

function ekstra_format_absensi($jurnalid) {
    global $DB;

    $rows = $DB->get_records_sql("
        SELECT
	    a.id,
	    a.status,
            u.firstname,
            u.lastname
        FROM {local_ekstra_absen} a
        JOIN {user} u
            ON u.id = a.userid
        WHERE a.jurnalid = ?
          AND a.status <> 'Hadir'
        ORDER BY a.status, u.lastname ASC
    ", [$jurnalid]);

    if (!$rows) {
        return 'Hadir semua';
    }

    $grouped = [];

    foreach ($rows as $r) {

        $nama = trim($r->firstname . ' ' . $r->lastname);

        $grouped[$r->status][] = $nama;
    }

    $hasil = [];

    foreach ($grouped as $status => $nama) {

        $hasil[] = $status . ': ' . implode(', ', $nama);
    }

    return implode('<br>', $hasil);
}
