<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();

/* =====================================================
   TANGKAP PARAMETER
===================================================== */
$muridid = optional_param('muridid', 0, PARAM_INT);
$filter  = optional_param('filter', '', PARAM_TEXT);

// Parameter khusus untuk pencarian otomatis (AJAX)
$ajax = optional_param('ajax', 0, PARAM_INT);
$q    = optional_param('q', '', PARAM_TEXT);

global $DB, $PAGE, $OUTPUT;

/* =====================================================
   PROSES PENCARIAN AJAX (Dijalankan di Latar Belakang)
===================================================== */
if ($ajax == 1) {
    header('Content-Type: application/json');
    $results = [];

    if (!empty($q)) {
        // Query untuk mencari Tahun Ajaran, Kelas, dan Nama
        $sql = "
            SELECT rk.id,
                   u.id AS userid,
                   u.firstname,
                   u.lastname,
                   rk.tahunajaran,
                   c.name AS kelas
            FROM {user} u
            JOIN {local_jurnalmengajar_riwayatkelas} rk ON u.id = rk.userid
            JOIN {cohort} c ON c.id = rk.cohortid
            WHERE u.firstname LIKE ? OR u.lastname LIKE ?
            ORDER BY rk.tahunajaran DESC, u.firstname ASC
        ";
        
        $records = $DB->get_records_sql($sql, ['%' . $q . '%', '%' . $q . '%'], 0, 20); // Batasi 20 hasil agar ringan

        foreach ($records as $r) {
//            $nama_lengkap = ucwords(strtolower(trim($r->firstname . ' ' . $r->lastname)));
            $nama_lengkap = ucwords(strtolower(trim($r->lastname)));
            // Format: Tahun Ajaran | Kelas | Nama
            $teks = "<span class='badge badge-dark'>{$r->tahunajaran}</span> &nbsp;|&nbsp; " .
                    "<span class='badge badge-primary'>{$r->kelas}</span> &nbsp;|&nbsp; " . 
                    "<strong>{$nama_lengkap}</strong>";

            $results[] = [
                'id'   => $r->userid,
                'text' => $teks,
                'nama' => $nama_lengkap
            ];
        }
    }
    
    echo json_encode($results);
    exit; // Berhenti di sini khusus untuk request AJAX
}

/* =====================================================
   RENDER HALAMAN UTAMA (UI)
===================================================== */
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/riwayat_individu.php'));
$PAGE->set_context($context);
$PAGE->set_title('Riwayat Individu Murid');
$PAGE->set_heading('Riwayat Individu Murid');

echo $OUTPUT->header();

// Cek apakah ada murid yang sedang dipilih (untuk mengisi teks di kolom pencarian)
$murid = null;
$inputval = '';
if ($muridid) {
    $murid = $DB->get_record('user', ['id' => $muridid]);
    if ($murid) {
//        $inputval = trim($murid->firstname . ' ' . $murid->lastname);
$inputval = trim($murid->lastname);
    } else {
        echo $OUTPUT->notification('Murid tidak ditemukan', 'notifyproblem');
    }
}

/* =====================================================
   FORM PENCARIAN REAL-TIME
===================================================== */
echo html_writer::start_tag('form', [
    'method' => 'get',
    'id'     => 'searchform',
    'class'  => 'mb-4 p-4 bg-light rounded border shadow-sm'
]);

echo html_writer::start_div('row justify-content-center');
echo html_writer::start_div('col-md-8 position-relative');

echo html_writer::tag('label', '<i class="fa fa-search text-primary"></i> Cari Riwayat Murid', ['class' => 'font-weight-bold mb-2 h5']);

// Input teks pencarian
echo html_writer::empty_tag('input', [
    'type'        => 'text',
    'id'          => 'searchinput',
    'class'       => 'form-control form-control-lg border-primary shadow-sm',
    'placeholder' => 'Ketik karakter huruf nama (misal: ahm)...',
    'autocomplete'=> 'off',
    'value'       => $inputval
]);

// Kotak hasil dropdown (disembunyikan secara default)
echo html_writer::start_div('', [
    'id'    => 'searchresults',
    'class' => 'list-group shadow-lg w-100 mt-1',
    'style' => 'position: absolute; z-index: 1050; display: none; max-height: 350px; overflow-y: auto;'
]);
echo html_writer::end_div();

// Hidden input untuk menyimpan ID murid yang diklik
echo html_writer::empty_tag('input', [
    'type'  => 'hidden',
    'name'  => 'muridid',
    'id'    => 'muridid_input',
    'value' => $muridid
]);

if ($muridid) {
    echo html_writer::start_div('mt-3 text-right');
    echo html_writer::link(new moodle_url('/local/jurnalmengajar/riwayat_individu.php'), '<i class="fa fa-refresh"></i> Reset Pencarian', ['class' => 'btn btn-secondary btn-sm']);
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_tag('form');

/* =====================================================
   SCRIPT JAVASCRIPT UNTUK PENCARIAN AJAX
===================================================== */
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('searchinput');
    const resultsBox = document.getElementById('searchresults');
    const hiddenInput = document.getElementById('muridid_input');
    const form = document.getElementById('searchform');
    let timeout = null;

    // Saat user mengetik
    input.addEventListener('input', function() {
        clearTimeout(timeout);
        const q = this.value.trim();
        
        // Mulai pencarian jika karakter >= 2
        if (q.length < 2) {
            resultsBox.style.display = 'none';
            return;
        }

        // Tunda sedikit (300ms) agar tidak terlalu berat memanggil database
        timeout = setTimeout(() => {
            fetch('riwayat_individu.php?ajax=1&q=' + encodeURIComponent(q))
            .then(response => response.json())
            .then(data => {
                resultsBox.innerHTML = '';
                
                if (data.length === 0) {
                    resultsBox.innerHTML = '<div class="list-group-item text-danger"><i>Tidak ada data murid yang cocok.</i></div>';
                } else {
                    data.forEach(item => {
                        const a = document.createElement('a');
                        a.href = '#';
                        a.className = 'list-group-item list-group-item-action text-dark';
                        a.innerHTML = item.text; // Format Tahun | Kelas | Nama
                        
                        // Saat nama diklik
                        a.addEventListener('click', function(e) {
                            e.preventDefault();
                            hiddenInput.value = item.id;
                            input.value = item.nama;
                            resultsBox.style.display = 'none';
                            form.submit(); // Submit form otomatis
                        });
                        resultsBox.appendChild(a);
                    });
                }
                resultsBox.style.display = 'block';
            })
            .catch(err => console.error('Error AJAX:', err));
        }, 300);
    });

    // Sembunyikan hasil pencarian jika user klik area di luar form
    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !resultsBox.contains(e.target)) {
            resultsBox.style.display = 'none';
        }
    });
});
</script>
<?php

/* =====================================================
   TAMPILKAN DATA & TIMELINE (JIKA MURID DIPILIH)
===================================================== */
if ($muridid && $murid) {
    $timeline = [];
    $namasiswa = trim($murid->lastname);

    // Kumpulan Counter untuk Statistik
    $count_absen = 0; $count_izin = 0; $count_bk = 0; 
    $count_wali = 0; $count_walikelas = 0; $count_mapel = 0;

    /* 1. ABSEN JURNAL MENGAJAR */
    $rows = $DB->get_records('local_jurnalmengajar');
    foreach ($rows as $r) {
        $match = false;
        $alasan = '-';

        if (!empty($r->absenid)) {
            $absenid = json_decode($r->absenid, true);
            if (is_array($absenid) && array_key_exists($muridid, $absenid)) {
                $match = true; $alasan = $absenid[$muridid];
            }
        } elseif (!empty($r->absen)) {
            $absen = json_decode($r->absen, true);
            if (is_array($absen)) {
                foreach ($absen as $nama => $ket) {
                    if (trim($nama) == $namasiswa) {
                        $match = true; $alasan = $ket; break;
                    }
                }
            }
        }

        if ($match) {
            $guru = $DB->get_record('user', ['id' => $r->userid]);
            $kelas = $r->kelas;
            if (is_numeric($kelas)) {
                $cohort = $DB->get_record('cohort', ['id' => $kelas]);
                if ($cohort) { $kelas = $cohort->name; }
            }
            $timeline[] = ['time' => $r->timecreated, 'kelas' => $kelas, 'jenis' => 'Tidak Hadir', 'catatan' => $alasan, 'guru' => $guru ? $guru->lastname : '-', 'kategori' => 'absen'];
            $count_absen++;
        }
    }

    /* 2. SURAT IZIN */
    $izins = $DB->get_records('local_jurnalmengajar_suratizin', ['userid' => $muridid]);
    foreach ($izins as $r) {
        $penginput = $DB->get_record('user', ['id' => $r->penginput]);
        $kelas = '-';
        if (!empty($r->kelasid)) {
            $cohort = $DB->get_record('cohort', ['id' => $r->kelasid]);
            if ($cohort) { $kelas = $cohort->name; }
        }
        $catatanizin = '<b>Alasan:</b> ' . s($r->alasan) . '<br><b>Keperluan:</b> ' . s($r->keperluan);
        if (!empty($r->catatan)) { $catatanizin .= '<br><b>Catatan Pembinaan:</b> ' . format_string($r->catatan); }
        $timeline[] = ['time' => $r->timecreated, 'kelas' => $kelas, 'jenis' => 'Surat Izin', 'catatan' => $catatanizin, 'guru' => $penginput ? $penginput->lastname : '-', 'kategori' => 'izin'];
        $count_izin++;
    }

    /* 3. LAYANAN BK */
    $bk = $DB->get_records('local_jurnallayananbk');
    foreach ($bk as $r) {
        $match = false;
        if (!empty($r->pesertaid)) {
            $pesertaid = json_decode($r->pesertaid, true);
            if (is_array($pesertaid) && in_array($muridid, $pesertaid)) { $match = true; }
        } elseif (!empty($r->peserta)) {
            $peserta = json_decode($r->peserta, true);
            if (is_array($peserta)) {
                foreach ($peserta as $nama) { if (trim($nama) == $namasiswa) { $match = true; break; } }
            }
        }
        if ($match) {
            $guru = $DB->get_record('user', ['id' => $r->userid]);
            $kelas = $r->kelas;
            if (is_numeric($kelas)) {
                $cohort = $DB->get_record('cohort', ['id' => $kelas]);
                if ($cohort) { $kelas = $cohort->name; }
            }
            $timeline[] = ['time' => $r->timecreated, 'kelas' => $kelas, 'jenis' => 'Layanan BK', 'catatan' => '<b>Topik:</b> ' . $r->topik . '<br><b>Catatan:</b> ' . $r->catatan, 'guru' => $guru ? $guru->lastname : '-', 'kategori' => 'bk'];
            $count_bk++;
        }
    }

    /* 4. PEMBINAAN BK */
    $pembinaan = $DB->get_records('local_jurnalpembinaan');
    foreach ($pembinaan as $r) {
        $match = false;
        if (!empty($r->pesertaid)) {
            $pesertaid = json_decode($r->pesertaid, true);
            if (is_array($pesertaid) && in_array($muridid, $pesertaid)) { $match = true; }
        } elseif (!empty($r->peserta)) {
            $peserta = json_decode($r->peserta, true);
            if (is_array($peserta)) {
                foreach ($peserta as $nama) { if (trim($nama) == $namasiswa) { $match = true; break; } }
            }
        }
        if ($match) {
            $guru = $DB->get_record('user', ['id' => $r->userid]);
            $kelas = $r->kelas;
            if (is_numeric($kelas)) {
                $cohort = $DB->get_record('cohort', ['id' => $kelas]);
                if ($cohort) { $kelas = $cohort->name; }
            }
            $timeline[] = ['time' => $r->timecreated, 'kelas' => $kelas, 'jenis' => 'Pembinaan BK', 'catatan' => '<b>Permasalahan:</b> ' . $r->permasalahan . '<br><b>Tindakan:</b> ' . $r->tindakan, 'guru' => $guru ? $guru->lastname : '-', 'kategori' => 'pembinaan'];
            $count_bk++; // Digabung ke Counter BK
        }
    }

    /* 5. JURNAL GURU WALI */
    $wali = $DB->get_records('local_jurnalguruwali', ['muridid' => $muridid]);
    foreach ($wali as $r) {
        $guru = $DB->get_record('user', ['id' => $r->guruid]);
        $timeline[] = ['time' => $r->timecreated, 'kelas' => $r->kelas, 'jenis' => 'Pendampingan Wali', 'catatan' => '<b>Topik:</b> ' . $r->topik . '<br><b>Tindak Lanjut:</b> ' . $r->tindaklanjut, 'guru' => $guru ? $guru->lastname : '-', 'kategori' => 'wali'];
        $count_wali++;
    }

    /* 6. PEMBINAAN WALI KELAS */
    $walikelas = $DB->get_records_sql("SELECT * FROM {local_jurnalwalikelas} WHERE jenis = 'pembinaan' AND muridid = ? ORDER BY timecreated ASC", [$muridid]);
    foreach ($walikelas as $r) {
        $guru = $DB->get_record('user', ['id' => $r->userid]);
        $kelas = $r->kelas;
        if (is_numeric($kelas)) {
            $cohort = $DB->get_record('cohort', ['id' => $kelas]);
            if ($cohort) { $kelas = $cohort->name; }
        }
        $timeline[] = ['time' => $r->timecreated, 'kelas' => $kelas, 'jenis' => 'Pembinaan Wali Kelas', 'catatan' => '<b>Permasalahan:</b> ' . $r->topik . '<br><b>Tindak Lanjut/Solusi:</b> ' . $r->tindaklanjut, 'guru' => $guru ? $guru->lastname : '-', 'kategori' => 'walikelas'];
        $count_walikelas++;
    }

    /* 7. PEMBINAAN GURU MAPEL */
    $mapel = $DB->get_records_sql("SELECT * FROM {local_jurnalmengajar_pembinaanmapel} WHERE muridid = ? ORDER BY timecreated ASC", [$muridid]);
    foreach ($mapel as $r) {
        $guru = $DB->get_record('user', ['id' => $r->userid]);
        $kelas = $r->kelas;
        if (is_numeric($kelas)) {
            $cohort = $DB->get_record('cohort', ['id' => $kelas]);
            if ($cohort) { $kelas = $cohort->name; }
        }
        $timeline[] = ['time' => $r->timecreated, 'kelas' => $kelas, 'jenis' => 'Pembinaan Guru Mapel', 'catatan' => '<b>Jenis:</b> ' . $r->jenis . '<br><b>Catatan:</b> ' . $r->catatan . '<br><b>Tindak Lanjut:</b> ' . $r->tindaklanjut, 'guru' => $guru ? $guru->lastname : '-', 'kategori' => 'mapel'];
        $count_mapel++;
    }

    /* SORT BY TIME DESCENDING */
    usort($timeline, function($a, $b) { return $b['time'] <=> $a['time']; });

    /* HEADER NAMA MURID */
    echo html_writer::start_div('d-flex justify-content-between align-items-center my-4 pb-2 border-bottom');
    echo html_writer::tag('h3', '<i class="fa fa-user-circle text-muted"></i> ' . format_nama_siswa($murid->lastname), ['class' => 'm-0 font-weight-bold']);
    
    echo html_writer::start_div();
    if (has_capability('moodle/site:config', $context)) {
        echo html_writer::link(new moodle_url('/local/jurnalmengajar/statusakademik.php', ['userid' => $muridid]), '<i class="fa fa-cog"></i> Kelola Status Akademik', ['class' => 'btn btn-success btn-sm mr-2']);
    }
    echo html_writer::tag('span', 'Total Log: ' . count($timeline), ['class' => 'badge badge-dark p-2']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    /* =====================================================
       RIWAYAT KELAS & AKADEMIK
    ===================================================== */
    $riwayatkelas = $DB->get_records_sql("SELECT rk.tahunajaran, c.name AS namakelas FROM {local_jurnalmengajar_riwayatkelas} rk JOIN {cohort} c ON c.id = rk.cohortid WHERE rk.userid = ? ORDER BY rk.tahunajaran ASC", [$muridid]);

    echo html_writer::start_div('row');
    
    // Panel Riwayat Kelas
    echo html_writer::start_div('col-md-6 mb-4');
    echo html_writer::start_div('card shadow-sm h-100');
    echo html_writer::start_div('card-header bg-primary text-white');
    echo html_writer::tag('strong', '<i class="fa fa-list"></i> Riwayat Kelas');
    echo html_writer::end_div();
    echo html_writer::start_div('card-body');
    if ($riwayatkelas) {
        foreach ($riwayatkelas as $r) {
            echo html_writer::tag('div', '<strong>' . format_string($r->tahunajaran) . '</strong> di kelas <strong>' . format_string($r->namakelas) . '</strong>', ['class' => 'mb-2']);
        }
    } else {
        echo html_writer::tag('div', '<i>Belum ada riwayat kelas.</i>', ['class' => 'text-muted']);
    }
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div(); // End Col

    // Panel Riwayat Akademik
    $riwayatakademik = $DB->get_records_sql("SELECT * FROM {local_jurnalmengajar_riwayatakademik} WHERE userid = ? ORDER BY tanggal ASC", [$muridid]);
    
    echo html_writer::start_div('col-md-6 mb-4');
    echo html_writer::start_div('card shadow-sm h-100');
    echo html_writer::start_div('card-header bg-success text-white');
    echo html_writer::tag('strong', '<i class="fa fa-graduation-cap"></i> Riwayat Akademik');
    echo html_writer::end_div();
    echo html_writer::start_div('card-body');
    if ($riwayatakademik) {
        $pertama = true;
        foreach ($riwayatakademik as $r) {
            if (!$pertama) { echo html_writer::empty_tag('hr'); }
            $pertama = false;
            switch ($r->jenis) {
                case 'masukkelas': $teks = 'Masuk kelas <strong>' . format_string($r->keterangan) . '</strong>'; break;
                case 'pindahkelas': $teks = 'Pindah kelas <strong>' . format_string($r->keterangan) . '</strong>'; break;
                case 'mutasi': $teks = 'Mutasi ke <strong>' . format_string($r->keterangan) . '</strong>'; break;
                case 'berhenti': $teks = 'Berhenti' . (!empty($r->keterangan) ? '<br><small>'.format_string($r->keterangan).'</small>' : ''); break;
                case 'lulus': $teks = 'Lulus'; break;
                default: $teks = format_string($r->jenis);
            }
            echo html_writer::tag('div', '<strong>' . tanggal_indo($r->tanggal, 'judul') . '</strong><br>&nbsp;&nbsp;&nbsp;' . $teks);
        }
    } else {
        echo html_writer::tag('div', '<i>Belum ada riwayat akademik.</i>', ['class' => 'text-muted']);
    }
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div(); // End Col
    
    echo html_writer::end_div(); // End Row

    /* =====================================================
       COUNTER STATS CARDS (DASHBOARD MINI)
    ===================================================== */
    echo html_writer::start_div('row mb-4');
    $cards = [
        ['Tidak Hadir', $count_absen, 'bg-danger text-white', 'absen'],
        ['Surat Izin', $count_izin, 'bg-warning text-dark', 'izin'],
        ['Layanan & Pembinaan BK', $count_bk, 'bg-info text-white', 'bk'],
        ['Pendampingan Wali', $count_wali, 'bg-primary text-white', 'wali'],
        ['Pembinaan Wali Kelas', $count_walikelas, 'bg-success text-white', 'walikelas'],
        ['Pembinaan Guru Mapel', $count_mapel, 'text-white', 'mapel']
    ];

    foreach ($cards as $card) {
        echo html_writer::start_div('col-6 col-md mb-2');
        $url = new moodle_url('/local/jurnalmengajar/riwayat_individu.php', ['muridid' => $muridid, 'filter' => ($filter == $card[3]) ? '' : $card[3]]);
        echo html_writer::start_tag('a', ['href' => $url, 'style' => 'text-decoration:none;']);
        $activeclass = ($filter == $card[3]) ? ' border border-dark' : '';
        $extrastyle = ($card[3] == 'mapel') ? 'background:#6f42c1;color:white;' : '';

        echo html_writer::start_div('card text-center shadow-sm h-100 ' . $card[2] . $activeclass, ['style' => $extrastyle]);
        echo html_writer::start_div('card-body p-2 d-flex flex-column justify-content-center');
        echo html_writer::tag('h6', $card[0], ['class' => 'text-uppercase small font-weight-bold m-0']);
        echo html_writer::tag('h2', $card[1], ['class' => 'font-weight-bold my-1']);
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo html_writer::end_tag('a');
        echo html_writer::end_div();
    }
    echo html_writer::end_div();

    /* =====================================================
       TABEL TIMELINE
    ===================================================== */
    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered table-hover bg-white shadow-sm">';
    echo '<thead class="thead-dark"><tr><th style="width: 15%;">Waktu</th><th style="width: 10%;">Kelas</th><th style="width: 15%;">Kategori</th><th>Detail Catatan</th><th style="width: 18%;">Penginput</th></tr></thead>';
    echo '<tbody>';

    $jumlahditampilkan = 0;
    foreach ($timeline as $t) {
        if ($filter) {
            if ($filter == 'bk' && !in_array($t['kategori'], ['bk', 'pembinaan'])) continue;
            elseif ($filter != 'bk' && $t['kategori'] != $filter) continue;
        }

        $badge = '';
        switch ($t['kategori']) {
            case 'absen': $badge = '<span class="badge badge-secondary d-block p-2">Tidak hadir KBM</span>'; break;
            case 'izin': $badge = '<span class="badge badge-warning d-block p-2">Surat Izin</span>'; break;
            case 'bk': $badge = '<span class="badge badge-info d-block p-2">Layanan BK</span>'; break;
            case 'pembinaan': $badge = '<span class="badge badge-danger d-block p-2">Pembinaan BK</span>'; break;
            case 'wali': $badge = '<span class="badge badge-primary d-block p-2">Guru Wali</span>'; break;
            case 'walikelas': $badge = '<span class="badge badge-success d-block p-2">Wali Kelas</span>'; break;
            case 'mapel': $badge = '<span class="badge badge-secondary d-block p-2">Guru Mapel</span>'; break;
        }

        echo '<tr>';
        $jumlahditampilkan++;
        echo '<td class="align-middle" style="font-size: 0.9rem;">' . tanggal_indo($t['time']) . '</td>';
        echo '<td class="align-middle font-weight-bold">' . s($t['kelas']) . '</td>';
        echo '<td class="align-middle">' . $badge . '</td>';
        echo '<td class="align-middle text-justify lh-base">' . format_text($t['catatan']) . '</td>';
        echo '<td class="align-middle"><i class="fa fa-user mr-1 text-primary"></i> ' . format_string($t['guru']) . '</td>';
        echo '</tr>';
    }

    if ($jumlahditampilkan == 0) {
        echo '<tr><td colspan="5" class="text-center text-muted p-4"><i>Belum ada data.</i></td></tr>';
    }

    echo '</tbody></table></div>';
}

echo $OUTPUT->footer();
