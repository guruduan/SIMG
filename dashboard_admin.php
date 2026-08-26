<?php
// Pastikan path ke config.php sudah benar. 
// Jika file ini ada di /local/jurnalmengajar/admin_dashboard.php, maka path-nya adalah:
require_once('../../config.php');

// 1. Pengecekan Keamanan (Harus Login)
require_login();

// 2. Pengecekan Hak Akses (Hanya Admin yang bisa mengakses)
$context = context_system::instance();
require_capability('moodle/site:config', $context);

// 3. Setup Halaman Moodle
$url = new moodle_url('/local/jurnalmengajar/admin_dashboard.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title('Dashboard Admin Jurnal Mengajar');
$PAGE->set_heading('Panel Admin Jurnal');

// Menambahkan CSS khusus agar tidak bentrok dengan theme Moodle
$custom_css = "
<style>
    .jurnal-dashboard-container {
        max-width: 1000px;
        margin: 20px auto;
        background-color: #ffffff;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
    }
    .jurnal-dashboard-header {
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid #edf2f7;
    }
    .jurnal-dashboard-header h3 {
        color: #2d3748;
        margin-bottom: 5px;
    }
    .jurnal-dashboard-header p {
        color: #718096;
        font-size: 16px;
    }
    .jurnal-menu-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 15px;
    }
    .jurnal-menu-item {
        display: flex;
        align-items: center;
        background-color: #ebf4ff;
        color: #2b6cb0 !important;
        text-decoration: none !important;
        padding: 15px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 15px;
        transition: all 0.2s ease-in-out;
        border: 1px solid #bee3f8;
    }
    .jurnal-menu-item:hover {
        background-color: #3182ce;
        color: #ffffff !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(49, 130, 206, 0.2);
    }
    .jurnal-emoji {
        margin-right: 12px;
        font-size: 20px;
    }
</style>
";

// 4. Mulai Mencetak Output ke Browser
echo $OUTPUT->header();
echo $custom_css;
?>

<div class="jurnal-dashboard-container">
    <div class="jurnal-dashboard-header">
        <h3>⚙️ Panel Admin Jurnal Mengajar</h3>
        <p>Silakan pilih menu di bawah ini untuk mengelola sistem jurnal dan jadwal</p>
    </div>

    <div class="jurnal-menu-grid">
        <!-- Pengaturan Sistem & Jabatan -->
        <a href="<?php echo new moodle_url('/admin/settings.php', array('section' => 'local_jurnalmengajar')); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 1. Pengaturan Awal
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/jabatan.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 2. Penugasan Wakil Kepala Sekolah
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/walikelas_manage.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 3. Penugasan Wali Kelas
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/guru_bk.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 4. Penugasan Guru BK
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/guruwali_manage.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 5. Pengaturan Guru Wali
        </a>

        <!-- Manajemen Jadwal & KBM -->
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/jam_pelajaran.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 6. Pengaturan Jam Pelajaran
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/jadwal_manage.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 7. Manajemen Jadwal Mengajar
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/kegiatan_manage.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 8. JP Tidak Belajar
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/guru_takhadir.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 9. Guru Tidak Hadir
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/izin_guru_hapus.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 10. Hapus Surat Izin Guru
        </a>

        <!-- Riwayat & Rekap Jurnal -->
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/histori_rekap.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 11. Lihat Riwayat KBM Guru Pertahun
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/all_jurnal.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 12. Lihat Semua Jurnal KBM
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/all_pembinaan_mapel.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 13. Lihat Semua Pembinaan Mapel pada KBM
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/all_jurnalguruwali.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 14. Lihat Semua Jurnal Guru Wali
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/surat_izin_murid_all.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 15. Lihat Semua Surat Izin Murid
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/riwayat_layananbk.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 16. Lihat Riwayat Layanan BK
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/riwayat_pramuka.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 17. Lihat Riwayat Kegiatan Pramuka
        </a>

        <!-- Ekstrakurikuler -->
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/ekstra.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 18. Isi Daftar Ekstrakurikuler
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/pembina_ekstra.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 19. Penugasan Pembina Ekstrakurikuler
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/peserta_ekstra.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 20. Isi Daftar Peserta Ekstrakurikuler
        </a>

        <!-- Asesmen & Ujian -->
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/kartu_ujian.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 21. Generate Kartu Ujian dan Daftar Hadir
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/jadwal_asesmen.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 22. Pengaturan Sesi Asesmen
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/jadwal_pengawas.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 23. Import Jadwal Pengawas Asesmen
        </a>

        <!-- Notifikasi & Lain-lain -->
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/template_notifikasi.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 24. Pengaturan Format Notifikasi WA
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/tujuan_notifikasi.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 25. Pengaturan Tujuan Notifikasi WA
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/preview_notifikasi.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 26. Preview Notifikasi Manual
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/reminder_manual.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 27. Preview Reminder Manual
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/banner.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 28. Pengaturan Banner untuk TV
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/export_siswaaktif.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 29. Ekspor Data Murid Aktif
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/mapping_kristen.php'); ?>" class="jurnal-menu-item">
            <span class="jurnal-emoji">🗓️</span> 30. Mapping Murid Agama Kristen
        </a>
        <a href="<?php echo new moodle_url('/local/jurnalmengajar/simpan_riwayatkelas.php'); ?>" class="jurnal-menu-item" style="background-color: #fed7d7; border-color: #feb2b2; color: #c53030 !important;">
            <span class="jurnal-emoji">⚠️</span> 31. Simpan Riwayat Kelas (Akhir Tahun)
        </a>
    </div>
</div>

<?php
// 5. Cetak Footer Moodle
echo $OUTPUT->footer();
?>
