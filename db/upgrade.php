<?php
// This file keeps track of upgrades to the jurnalmengajar plugin.

defined('MOODLE_INTERNAL') || die();

/**
 * Execute local_jurnalmengajar upgrade from the given old version
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_jurnalmengajar_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // === Buat tabel layanan BK (sudah ada sebelumnya) ===
    if ($oldversion < 2025073100) {
        // Define table local_jurnallayananbk to be created.
        $table = new xmldb_table('local_jurnallayananbk');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('kelas', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('jenislayanan', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('topik', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('peserta', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('tindaklanjut', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('catatan', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2025073100, 'local', 'jurnalmengajar');
    }

    // === Buat tabel pembinaan ===
    if ($oldversion < 2025073200) {
        $table = new xmldb_table('local_jurnalpembinaan');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('kelas', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('peserta', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('permasalahan', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('tindakan', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('tempat', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2025073200, 'local', 'jurnalmengajar');
    }

    // === Buat tabel jurnal guru wali ===
if ($oldversion < 2025083100) {
    $table = new xmldb_table('local_jurnalguruwali');

    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);

    // guru pengisi jurnal
    $table->add_field('guruid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    // murid yang dibina
    $table->add_field('muridid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

    $table->add_field('topik', XMLDB_TYPE_TEXT, null, null, null, null, null);
    $table->add_field('tindaklanjut', XMLDB_TYPE_TEXT, null, null, null, null, null);
    $table->add_field('keterangan', XMLDB_TYPE_TEXT, null, null, null, null, null);

    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

    // keys
    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_key('guruid_fk', XMLDB_KEY_FOREIGN, ['guruid'], 'user', ['id']);
    $table->add_key('muridid_fk', XMLDB_KEY_FOREIGN, ['muridid'], 'user', ['id']);

    // index opsional yang tidak tumpang tindih dengan FK
    $table->add_index('idx_timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }

    upgrade_plugin_savepoint(true, 2025083100, 'local', 'jurnalmengajar');
}
    // === Buat tabel Nilai Harian ===
    if ($oldversion < 2025091100) {
        // Tabel header + payload nilai (JSON).
        $table = new xmldb_table('local_jm_nilaiharian');

        $table->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);

        // Guru penginput nilai.
        $table->add_field('userid',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);

        // Metadata entri.
        $table->add_field('mapel',        XMLDB_TYPE_CHAR,    '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('cohortid',     XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, 0);
        $table->add_field('kelas',        XMLDB_TYPE_CHAR,    '100', null, XMLDB_NOTNULL, null, '');   // cache nama cohort
        $table->add_field('tanggal',      XMLDB_TYPE_CHAR,    '10',  null, XMLDB_NOTNULL, null, '');   // YYYY-MM-DD (WITA)

        // Payload nilai: [{"no":1,"userid":123,"name":"Nama","nilai":88}, ...]
        $table->add_field('nilaijson',    XMLDB_TYPE_TEXT,    null,  null, null, null, null);

        // Keys & index.
        $table->add_key('primary',    XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_fk',  XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('cohortid_idx', XMLDB_INDEX_NOTUNIQUE, ['cohortid']);
        $table->add_index('tanggal_idx',  XMLDB_INDEX_NOTUNIQUE, ['tanggal']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2025091100, 'local', 'jurnalmengajar');
    }
// === Buat tabel Jadwal Mengajar ===
if ($oldversion < 2026030201) {

    $table = new xmldb_table('local_jurnalmengajar_jadwal');

    $table->add_field('id', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('userid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
    $table->add_field('hari', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
    $table->add_field('kelas', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
    $table->add_field('jamke', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
    $table->add_field('ruang', XMLDB_TYPE_CHAR, '20', null, null, null, null);
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '19', null, null, null, null);
    $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '19', null, null, null, null);

    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }

    upgrade_plugin_savepoint(true, 2026030201, 'local', 'jurnalmengajar');
}
// =====================================================
// 2026033001 - Tabel Jurnal Ekstrakurikuler
// =====================================================
if ($oldversion < 2026033001) {

    // Tabel Ekstrakurikuler
    $table = new xmldb_table('local_jm_ekstra');

    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('namaekstra', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($table);
    }

    // Mapping Pembina
    $table = new xmldb_table('local_jm_ekstra_pembina');

    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('ekstraid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('ekstraid_idx', XMLDB_INDEX_NOTUNIQUE, ['ekstraid']);
        $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        $dbman->create_table($table);
    }

    // Peserta Ekstra
    $table = new xmldb_table('local_jm_ekstra_peserta');

    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('ekstraid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
        $table->add_field('kelas', XMLDB_TYPE_CHAR, '20', null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $dbman->create_table($table);
    }

    // Jurnal Ekstra
    $table = new xmldb_table('local_jm_ekstra_jurnal');

    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('ekstraid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
        $table->add_field('tanggal', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
        $table->add_field('pembinaid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
        $table->add_field('materi', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('catatan', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '19', null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $dbman->create_table($table);
    }

    // Absensi Ekstra
    $table = new xmldb_table('local_jm_ekstra_absen');

    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('jurnalid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('keterangan', XMLDB_TYPE_TEXT, null, null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $dbman->create_table($table);
    }

    upgrade_plugin_savepoint(true, 2026033001, 'local', 'jurnalmengajar');
}


// =====================================================
// 2026033002 - Tambah cohortid di peserta ekstra
// =====================================================
if ($oldversion < 2026033002) {

    $table = new xmldb_table('local_jm_ekstra_peserta');
    $field = new xmldb_field('cohortid', XMLDB_TYPE_INTEGER, '19', null, null, null, null, 'userid');

    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    upgrade_plugin_savepoint(true, 2026033002, 'local', 'jurnalmengajar');
}
// =====================================================
// 2026053001 - Tabel Jurnal Wali Kelas
// =====================================================
if ($oldversion < 2026053001) {

    $table = new xmldb_table('local_jurnalwalikelas');

    if (!$dbman->table_exists($table)) {

        // Fields
        $table->add_field(
            'id',
            XMLDB_TYPE_INTEGER,
            '19',
            null,
            XMLDB_NOTNULL,
            XMLDB_SEQUENCE,
            null
        );

        $table->add_field(
            'userid',
            XMLDB_TYPE_INTEGER,
            '19',
            null,
            XMLDB_NOTNULL,
            null,
            null
        );

        $table->add_field(
            'kelas',
            XMLDB_TYPE_CHAR,
            '20',
            null,
            XMLDB_NOTNULL,
            null,
            null
        );

        $table->add_field(
            'jenis',
            XMLDB_TYPE_CHAR,
            '50',
            null,
            XMLDB_NOTNULL,
            null,
            null
        );

        $table->add_field(
            'muridid',
            XMLDB_TYPE_INTEGER,
            '19',
            null,
            XMLDB_NOTNULL,
            null,
            0
        );

        $table->add_field(
            'topik',
            XMLDB_TYPE_TEXT,
            null,
            null,
            null,
            null,
            null
        );

        $table->add_field(
            'tindaklanjut',
            XMLDB_TYPE_TEXT,
            null,
            null,
            null,
            null,
            null
        );

        $table->add_field(
            'uraian',
            XMLDB_TYPE_TEXT,
            null,
            null,
            null,
            null,
            null
        );

        $table->add_field(
            'timecreated',
            XMLDB_TYPE_INTEGER,
            '19',
            null,
            XMLDB_NOTNULL,
            null,
            null
        );

        $table->add_field(
            'timemodified',
            XMLDB_TYPE_INTEGER,
            '19',
            null,
            null,
            null,
            null
        );

        // Keys
        $table->add_key(
            'primary',
            XMLDB_KEY_PRIMARY,
            ['id']
        );

        // Indexes
        $table->add_index(
            'userid_idx',
            XMLDB_INDEX_NOTUNIQUE,
            ['userid']
        );

        $table->add_index(
            'muridid_idx',
            XMLDB_INDEX_NOTUNIQUE,
            ['muridid']
        );

        $table->add_index(
            'kelas_idx',
            XMLDB_INDEX_NOTUNIQUE,
            ['kelas']
        );

        $table->add_index(
            'jenis_idx',
            XMLDB_INDEX_NOTUNIQUE,
            ['jenis']
        );

        $dbman->create_table($table);
    }

    upgrade_plugin_savepoint(
        true,
        2026053001,
        'local',
        'jurnalmengajar'
    );
}
 
// =====================================================
// 2026053101 - Riwayat Kelas Siswa
// =====================================================
if ($oldversion < 2026053101) {

    $table = new xmldb_table('local_jurnalmengajar_riwayatkelas');

    if (!$dbman->table_exists($table)) {

        $table->add_field('id', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cohortid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
        $table->add_field('tahunajaran', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('cohortid_idx', XMLDB_INDEX_NOTUNIQUE, ['cohortid']);
        $table->add_index('tahunajaran_idx', XMLDB_INDEX_NOTUNIQUE, ['tahunajaran']);

        $dbman->create_table($table);
    }

    upgrade_plugin_savepoint(
        true,
        2026053101,
        'local',
        'jurnalmengajar'
    );
} 
    return true;
}
