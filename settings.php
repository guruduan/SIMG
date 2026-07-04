<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    // =========================
    // KATEGORI PLUGIN
    // =========================
    $ADMIN->add('localplugins', new admin_category(
        'local_jurnalmengajar_cat',
        'Jurnal Mengajar'
    ));

    // =========================
    // HALAMAN SETTING UTAMA
    // =========================
    $settings = new admin_settingpage(
        'local_jurnalmengajar',
        'Pengaturan Umum'
    );

    $ADMIN->add('local_jurnalmengajar_cat', $settings);

    // =========================
    // PENGATURAN UMUM
    // =========================
    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/tanggalawalminggu',
        'Tanggal Awal Minggu Pertama',
        'Format: YYYY-MM-DD. Contoh: 2025-06-23',
        '2025-06-23',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/mapel_list',
        'Daftar Mapel (pisahkan dengan koma)',
        'Contoh: Fisika,Matematika,Bahasa Indonesia,PPKN',
        'Fisika,Matematika,Kimia,Biologi,Bahasa Indonesia,Bahasa Inggris,PPKN,Sejarah'
    ));

    // =========================
    // IDENTITAS SEKOLAH
    // =========================
    $settings->add(new admin_setting_heading(
        'local_jurnalmengajar/identitas_sekolah',
        'Identitas Sekolah',
        'Digunakan untuk cetak laporan dan tanda tangan'
    ));

    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/nama_sekolah',
        'Nama Sekolah',
        '',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/tahun_ajaran',
        'Tahun Ajaran',
        'Contoh: 2025/2026',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/tempat_ttd',
        'Tempat',
        'Contoh: Kandangan',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/nama_kepsek',
        'Nama Kepala Sekolah',
        '',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/nip_kepsek',
        'NIP',
        '',
        '',
        PARAM_TEXT
    ));
// Upload Logo Sekolah
$settings->add(new admin_setting_configstoredfile(
    'local_jurnalmengajar/logo',
    'Logo Sekolah',
    'Upload logo sekolah (PNG/JPG)',
    'logo'
));

// Upload Stempel
$settings->add(new admin_setting_configstoredfile(
    'local_jurnalmengajar/stempel',
    'Stempel',
    'Upload stempel (PNG transparan)',
    'stempel'
));
// ==============================
// TTD Kepala Sekolah
// ==============================
$settings->add(new admin_setting_configstoredfile(
    'local_jurnalmengajar/ttd',
    'Tanda Tangan',
    'Upload tanda tangan kepala sekolah (PNG)',
    'ttd'
));
$settings->add(new admin_setting_configtext(
    'local_jurnalmengajar/nomor_kepsek',
    'Nomor Kepala Sekolah',
    'Nomor WhatsApp Kepala Sekolah',
    ''
));
    // =========================
    // HARI SEKOLAH & LIBUR
    // =========================
    $settings->add(new admin_setting_heading(
        'local_jurnalmengajar/harisekolah_heading',
        'Hari Sekolah & Libur',
        'Pengaturan hari sekolah dan tanggal libur'
    ));

$settings->add(new admin_setting_configtext(
    'local_jurnalmengajar/harisekolah',
    'Hari Sekolah',
    'Isi hari sekolah dipisahkan koma. Contoh: Senin,Selasa,Rabu,Kamis,Jumat',
    'Senin,Selasa,Rabu,Kamis,Jumat',
    PARAM_TEXT
));

    $settings->add(new admin_setting_configtextarea(
    'local_jurnalmengajar/tanggallibur',
    'Tanggal Libur',
    'Isi tanggal libur, satu baris satu tanggal atau rentang tanggal.
Contoh:
2026-03-21
2026-03-23 s/d 2026-03-25',
    ''
));
     $settings->add(
    new admin_setting_configtextarea(
        'local_jurnalmengajar/jadwal_khusus_tv',
        'Jadwal Khusus TV',
        'Format:
2026-06-02 s/d 2026-06-12|HALAL BIHALAL |Tidak Ada KBM Reguler
2026-03-10 s/d 2026-03-18|ASESMEN AKHIR SEMESTER|KBM Ditiadakan',
        ''
    )
);
$settings->add(
    new admin_setting_configtextarea(
        'local_jurnalmengajar/banner_tv',
        'Banner Hari Khusus TV',
        'Format:

Tanggal tunggal:
2026-06-01|Hari Lahir Pancasila|pancasila.png

Rentang tanggal:
2026-06-02 s/d 2026-06-12|ASESMEN AKHIR SEMESTER|asesmen.jpeg

Nama file harus sama dengan file yang diupload pada menu Kelola Banner TV.',
        ''
    )
);
$settings->add(
    new admin_setting_configtextarea(
        'local_jurnalmengajar/tanggalasesmen',
        'Tanggal Asesmen',
        'Format:
2026-06-02 s/d 2026-06-12',
        ''
    )
);

$settings->add(
    new admin_setting_configtextarea(
        'local_jurnalmengajar/kbm_ditiadakan_kelas',
        'KBM Ditiadakan untuk Kelas Tertentu',
        'Digunakan jika KBM pada kelas tertentu ditiadakan sementara,
misalnya MPLS, class meeting, gladi, ANBK, ujian praktik, atau kegiatan sekolah lainnya.

Format:
KELAS|YYYY-MM-DD
atau
KELAS|YYYY-MM-DD s/d YYYY-MM-DD

Contoh:
X|2026-07-13 s/d 2026-07-17
XI|2026-09-15
XII|2027-03-08 s/d 2027-03-12',
        ''
    )
);

$settings->add(
    new admin_setting_configtext(
        'local_jurnalmengajar/judulasesmen',
        'Judul Asesmen TV',
        'Contoh: ASESMEN AKHIR TAHUN',
        'ASESMEN AKHIR TAHUN',
        PARAM_TEXT
    )
);

// ==============================
// CUT OFF MULTI KELAS
// ==============================
$settings->add(new admin_setting_configtextarea(
    'local_jurnalmengajar/cutoff_kelas',
    'Tanggal Berhenti KBM di Kelas (VI, IX, XII)',
    'Format: KELAS|YYYY-MM-DD (1 baris per data)<br>
    Contoh:<br>
    VI|2026-05-10<br>
    IX|2026-05-05<br>
    XII|2026-04-06',
    '',
    PARAM_TEXT,
    50, // lebar (opsional)
    3   // tinggi (INI YANG NGURANGIN BARIS)
));

    // =========================
    // KONFIGURASI WABLAS
    // =========================
    $settings->add(new admin_setting_heading(
        'local_jurnalmengajar/wablas',
        'Konfigurasi Wablas',
        'Digunakan untuk notifikasi WhatsApp'
    ));

    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/apikey',
        'API Key',
        '',
        'Isi sesuai dashboard Wablas',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/secretkey',
        'Secret Key',
        '',
        'Isi sesuai dashboard Wablas',
        PARAM_RAW_TRIMMED
    ));
    
 $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/wablas_url',
        'Wablas URL',
        '',
        'Isi sesuai dashboard Wablas'
    ));

    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/wablas_group',
        'Group WhatsApp',
        'Group ID atau JID (120xxx@g.us)',
        'Isi sesuai dashboard Wablas'
    ));

//  tv
$settings->add(new admin_setting_configtextarea(
    'local_jurnalmengajar/pengumuman_tv',
    'Pengumuman TV',
    'Satu baris satu pengumuman',
    '',
    PARAM_TEXT
));
// guru piket
$settings->add(new admin_setting_configtextarea(
    'local_jurnalmengajar/guru_piket_senin',
    'Guru Piket Senin',
    'Satu nama per baris',
    '',
    PARAM_TEXT
));

$settings->add(new admin_setting_configtextarea(
    'local_jurnalmengajar/guru_piket_selasa',
    'Guru Piket Selasa',
    'Satu nama per baris',
    '',
    PARAM_TEXT
));

$settings->add(new admin_setting_configtextarea(
    'local_jurnalmengajar/guru_piket_rabu',
    'Guru Piket Rabu',
    'Satu nama per baris',
    '',
    PARAM_TEXT
));

$settings->add(new admin_setting_configtextarea(
    'local_jurnalmengajar/guru_piket_kamis',
    'Guru Piket Kamis',
    'Satu nama per baris',
    '',
    PARAM_TEXT
));


$settings->add(new admin_setting_configtextarea(
    'local_jurnalmengajar/guru_piket_jumat',
    'Guru Piket Jumat',
    'Satu nama per baris',
    '',
    PARAM_TEXT
));

$settings->add(new admin_setting_configtextarea(
    'local_jurnalmengajar/guru_piket_sabtu',
    'Guru Piket Sabtu',
    'Satu nama per baris',
    '',
    PARAM_TEXT
));

    // =========================
    // HALAMAN WALI KELAS
    // =========================
    $ADMIN->add('local_jurnalmengajar_cat', new admin_externalpage(
        'local_jurnalmengajar_walikelas',
        'Manajemen Wali Kelas',
        new moodle_url('/local/jurnalmengajar/wali_kelas.php'),
        'moodle/site:config'
    ));
    // =========================
// TEMPLATE NOTIFIKASI
// =========================
$ADMIN->add('local_jurnalmengajar_cat', new admin_externalpage(
    'local_jurnalmengajar_template',
    'Template Notifikasi',
    new moodle_url('/local/jurnalmengajar/template_notifikasi.php'),
    'moodle/site:config'
));
}
