<?php
require('../../config.php');
require_once(__DIR__.'/lib.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/kegiatan_manage.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Kelola Jam Tidak Belajar');
$PAGE->set_heading('Daftar Jam Tidak Belajar');

global $DB, $OUTPUT, $PAGE;

echo $OUTPUT->header();


// ============================================================
// TOMBOL MENUJU HALAMAN TAMBAH DATA
// ============================================================
echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/jurnalmengajar/kegiatan_add.php'),
        '+ Catat Jam Tidak Belajar',
        ['class' => 'btn btn-primary']
    ),
    'mb-3'
);


// ============================================================
// DAFTAR 21 KELAS SELURUH ROMBEL
// ============================================================
$kelas_seluruh = [
    'X-A',
    'X-B',
    'X-C',
    'X-D',
    'X-E',
    'X-F',
    'X-G',

    'XI-A',
    'XI-B',
    'XI-C',
    'XI-D',
    'XI-E',
    'XI-F',
    'XI-G',

    'XII-A',
    'XII-B',
    'XII-C',
    'XII-D',
    'XII-E',
    'XII-F',
    'XII-G'
];


// ============================================================
// QUERY AMBIL SEMUA DATA
// ============================================================
$sql = "SELECT
            k.*,
            c.name AS namakelas
        FROM {local_jurnalmengajar_kegiatan} k
        LEFT JOIN {cohort} c
            ON k.kelas = c.id
        ORDER BY k.tanggal ASC, k.id ASC";

$records = $DB->get_records_sql($sql);


// ============================================================
// PROSES GROUPING DATA
// ============================================================
$grouped_data = [];

if ($records) {

    foreach ($records as $r) {

        /*
         * Kegiatan dianggap sama jika:
         * - tanggal sama
         * - jamke sama
         * - keterangan sama
         */
        $key = $r->tanggal . '_' . $r->jamke . '_' . $r->keterangan;


        // ----------------------------------------------------
        // Buat wadah kegiatan jika belum ada
        // ----------------------------------------------------
        if (!isset($grouped_data[$key])) {

            $grouped_data[$key] = [
                'tanggal'    => $r->tanggal,
                'jamke'      => $r->jamke,
                'keterangan' => $r->keterangan,
                'kelas'      => []
            ];
        }


        // ----------------------------------------------------
        // Masukkan nama kelas
        // ----------------------------------------------------
        if (!empty($r->namakelas)) {

            /*
             * Hindari kelas yang sama masuk dua kali
             */
            if (!in_array(
                $r->namakelas,
                $grouped_data[$key]['kelas'],
                true
            )) {

                $grouped_data[$key]['kelas'][] =
                    $r->namakelas;
            }
        }
    }
}


// ============================================================
// SORTING TANGGAL & JAM
// ============================================================
usort(
    $grouped_data,
    function($a, $b) {

        // ----------------------------------------------------
        // Jika tanggal sama
        // ----------------------------------------------------
        if ($a['tanggal'] == $b['tanggal']) {

            /*
             * Ambil jam pertama.
             *
             * Contoh:
             * "10, 11" → 10
             * "2, 3"    → 2
             */
            $jam_a = (int) trim(
                explode(',', $a['jamke'])[0]
            );

            $jam_b = (int) trim(
                explode(',', $b['jamke'])[0]
            );

            return $jam_a <=> $jam_b;
        }


        // ----------------------------------------------------
        // Tanggal berbeda
        // ----------------------------------------------------
        return $a['tanggal'] <=> $b['tanggal'];
    }
);


// ============================================================
// MEMBUAT TABEL
// ============================================================
$table = new html_table();

$table->head = [
    'No',
    'Hari, Tanggal',
    'Kelas',
    'Jam Pelajaran',
    'Keterangan Kegiatan'
];

$table->attributes['class'] =
    'table table-bordered table-striped';


// ============================================================
// VARIABEL
// ============================================================
$i = 1;

$total_jam = 0;


// ============================================================
// TAMPILKAN DATA
// ============================================================
if (!empty($grouped_data)) {

    foreach ($grouped_data as $row) {


        // ====================================================
        // FORMAT TANGGAL
        // ====================================================
        $nama_hari = tanggal_indo(
            $row['tanggal'],
            'hari'
        );

        $nama_tanggal = tanggal_indo(
            $row['tanggal'],
            'tanggal'
        );

        $tanggalformat =
            $nama_hari . ', ' . $nama_tanggal;


        // ====================================================
        // BERSIHKAN DATA KELAS
        // ====================================================

        /*
         * Hilangkan spasi dan duplikasi.
         */
        $kelas_kegiatan = array_unique(
            array_map(
                'trim',
                $row['kelas']
            )
        );


        /*
         * Hitung jumlah kelas yang mengikuti kegiatan.
         */
        $jumlah_kelas_kegiatan =
            count($kelas_kegiatan);


        // ====================================================
        // CEK KEGIATAN UNTUK SELURUH 21 KELAS
        // ====================================================

        /*
         * Total hanya dihitung jika kegiatan
         * mempunyai 21 kelas berbeda.
         *
         * Contoh:
         *
         * 21 kelas → DIHITUNG
         *
         * 7 kelas XII → TIDAK DIHITUNG
         *
         * 1 kelas → TIDAK DIHITUNG
         */
        $untuk_semua_kelas =
            ($jumlah_kelas_kegiatan === 21);


        // ====================================================
        // HITUNG JUMLAH JP
        // ====================================================

        /*
         * Contoh:
         *
         * "10, 11"       → 2 JP
         * "1, 2, 3"      → 3 JP
         * "4, 5, 6, 7"   → 4 JP
         */
        $item_jam = explode(
            ',',
            $row['jamke']
        );


        /*
         * Bersihkan spasi dan item kosong.
         */
        $item_jam_bersih = array_filter(
            array_map(
                'trim',
                $item_jam
            )
        );


        $jumlah_jp =
            count($item_jam_bersih);


        // ====================================================
        // TAMBAHKAN KE TOTAL HANYA JIKA 21 KELAS
        // ====================================================
        if ($untuk_semua_kelas) {

            $total_jam += $jumlah_jp;
        }


        // ====================================================
        // FORMAT NAMA KELAS UNTUK TABEL
        // ====================================================
        $jumlah_kelas =
            count($row['kelas']);


        if ($jumlah_kelas > 0) {

            $namakelas_tampil =
                implode(
                    ', ',
                    $row['kelas']
                );

        } else {

            $namakelas_tampil =
                'Kelas Tidak Ditemukan';
        }


        // ====================================================
        // MASUKKAN DATA KE TABEL
        // ====================================================
        $table->data[] = [

            $i++,

            $tanggalformat,

            s($namakelas_tampil),

            s($row['jamke']),

            s($row['keterangan'])
        ];
    }


    // ========================================================
    // TAMPILKAN TOTAL JP
    // ========================================================
    echo html_writer::div(

        '<strong>Total Jam Pelajaran Tidak Ada KBM: </strong>' .

        '<span class="badge badge-info" style="font-size: 1rem;">' .

        $total_jam .

        ' JP</span>',

        'alert alert-info mb-3'
    );


    // ========================================================
    // TAMPILKAN TABEL
    // ========================================================
    echo html_writer::table($table);


} else {


    // ========================================================
    // BELUM ADA DATA
    // ========================================================
    echo html_writer::div(

        'Belum ada data jam tidak belajar yang tercatat.',

        'alert alert-info'
    );
}


// ============================================================
// FOOTER
// ============================================================
echo $OUTPUT->footer();
