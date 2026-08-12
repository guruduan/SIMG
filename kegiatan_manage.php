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

// Tombol Menuju Halaman Tambah Data
echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/jurnalmengajar/kegiatan_add.php'),
        '+ Catat Jam Tidak Belajar',
        ['class' => 'btn btn-primary']
    ),
    'mb-3'
);

// Query ambil semua data
$sql = "SELECT k.*, c.name AS namakelas 
        FROM {local_jurnalmengajar_kegiatan} k 
        LEFT JOIN {cohort} c ON k.kelas = c.id 
        ORDER BY k.tanggal ASC, k.id ASC";

$records = $DB->get_records_sql($sql);

// --- PROSES GROUPING DATA ---
$grouped_data = [];
if ($records) {
    foreach ($records as $r) {
        // Membuat kunci penanda berdasarkan kegiatan yang sama
        $key = $r->tanggal . '_' . $r->jamke . '_' . $r->keterangan;
        
        // Jika kegiatan ini belum masuk array, buat wadah baru
        if (!isset($grouped_data[$key])) {
            $grouped_data[$key] = [
                'tanggal' => $r->tanggal,
                'jamke' => $r->jamke,
                'keterangan' => $r->keterangan,
                'kelas' => [] 
            ];
        }
        
        // Masukkan nama kelas ke dalam wadah kegiatan ini jika tidak kosong
        if (!empty($r->namakelas)) {
            // Hindari duplikasi nama kelas dalam satu kegiatan
            if (!in_array($r->namakelas, $grouped_data[$key]['kelas'])) {
                $grouped_data[$key]['kelas'][] = $r->namakelas;
            }
        }
    }
}

// --- MENGURUTKAN DATA (SORTING TANGGAL & JAM) ---
usort($grouped_data, function($a, $b) {
    // 1. Jika tanggalnya persis sama
    if ($a['tanggal'] == $b['tanggal']) {
        // Ambil angka pertama dari string jam (misal: "10, 11" jadi "10") lalu jadikan integer
        $jam_a = (int) trim(explode(',', $a['jamke'])[0]);
        $jam_b = (int) trim(explode(',', $b['jamke'])[0]);
        
        // Urutkan berdasarkan jam dari yang terkecil ke terbesar
        return $jam_a <=> $jam_b;
    }
    
    // 2. Jika tanggalnya berbeda, urutkan berdasarkan tanggal (lama ke baru)
    return $a['tanggal'] <=> $b['tanggal'];
});

// Membuat tabel menggunakan Moodle html_table
$table = new html_table();
$table->head = ['No', 'Hari, Tanggal', 'Kelas', 'Jam Pelajaran', 'Keterangan Kegiatan'];
$table->attributes['class'] = 'table table-bordered table-striped';

$i = 1;
$total_jam = 0; 

if (!empty($grouped_data)) {
    foreach ($grouped_data as $row) {
        
        // --- FORMAT TANGGAL MENGGUNAKAN LIB.PHP ---
        $nama_hari = tanggal_indo($row['tanggal'], 'hari');
        $nama_tanggal = tanggal_indo($row['tanggal'], 'tanggal');
        $tanggalformat = $nama_hari . ', ' . $nama_tanggal; 
        
        // --- LOGIKA MENGHITUNG JAM (Aman untuk PHP 8) ---
        $item_jam = explode(',', $row['jamke']);
        // Membersihkan spasi tiap item lalu menghilangkan yang kosong
        $item_jam_bersih = array_filter(array_map('trim', $item_jam));
        $total_jam += count($item_jam_bersih);
        
        // --- LOGIKA PENAMPILAN KELAS ---
        $jumlah_kelas = count($row['kelas']);
        if ($jumlah_kelas > 2) {
            // Jika lebih dari 2 kelas, anggap seluruh kelas (atau ubah logika sesuai kebutuhan)
            $namakelas_tampil = 'Seluruh Kelas';
        } elseif ($jumlah_kelas > 0) {
            // Jika 1 atau 2 kelas, sebutkan nama kelasnya (misal: "Kelas X, Kelas Y")
            $namakelas_tampil = implode(', ', $row['kelas']);
        } else {
            $namakelas_tampil = 'Kelas Tidak Ditemukan';
        }

        // Memasukkan data ke baris tabel
        $table->data[] = [
            $i++,
            $tanggalformat,
            s($namakelas_tampil),
            s($row['jamke']),
            s($row['keterangan'])
        ];
    }
    
    // Tampilkan rangkuman total jam tepat di atas tabel
    echo html_writer::div(
        '<strong>Total Jam Pelajaran Tidak Ada KBM: </strong> <span class="badge badge-info" style="font-size: 1rem;">' . $total_jam . ' Jam</span>', 
        'alert alert-info mb-3'
    );

    echo html_writer::table($table);
} else {
    echo html_writer::div('Belum ada data jam tidak belajar yang tercatat.', 'alert alert-info');
}

echo $OUTPUT->footer();
