<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_local_jurnalmengajar_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // =====================================================
    // 2026032501 - Update schema ke Final Production
    // =====================================================
    if ($oldversion < 2026032501) {

        // BEBAN MENGAJAR - rename jumlahjam -> jam_perminggu
        $table = new xmldb_table('local_jurnalmengajar_beban');

        $oldfield = new xmldb_field('jumlahjam');
        $newfield = new xmldb_field('jam_perminggu', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, 0, 'userid');

        if ($dbman->field_exists($table, $oldfield)) {
            $dbman->rename_field($table, $oldfield, 'jam_perminggu');
        } else {
            if (!$dbman->field_exists($table, $newfield)) {
                $dbman->add_field($table, $newfield);
            }
        }

        // SURAT IZIN SISWA - tambah index
        $table = new xmldb_table('local_jurnalmengajar_suratizin');

        $indexes = [
            'userid_idx' => 'userid',
            'kelasid_idx' => 'kelasid',
            'guru_pengajar_idx' => 'guru_pengajar',
            'penginput_idx' => 'penginput',
            'timecreated_idx' => 'timecreated'
        ];

        foreach ($indexes as $name => $fieldname) {
            $index = new xmldb_index($name, XMLDB_INDEX_NOTUNIQUE, [$fieldname]);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        upgrade_plugin_savepoint(true, 2026032501, 'local', 'jurnalmengajar');
    }

    // =====================================================
    // 2026032701 - Buat tabel Jadwal Mengajar
    // =====================================================
    if ($oldversion < 2026032701) {

        $table = new xmldb_table('local_jurnalmengajar_jadwal');

        if (!$dbman->table_exists($table)) {

            $table->add_field('id', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
            $table->add_field('hari', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('kelas', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('jamke', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
            $table->add_field('ruang', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '19', null, null, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '19', null, null, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026032701, 'local', 'jurnalmengajar');
    }

    return true;
}
