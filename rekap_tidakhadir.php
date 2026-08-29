<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_tidakhadir.php'));

/* =====================================================
   PERIODE
===================================================== */
$tanggalawal = get_config('local_jurnalmengajar', 'tanggalawalminggu');

if (empty($tanggalawal)) {
    echo $OUTPUT->notification('Setting tanggalawalminggu belum diisi.', 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

$dari = strtotime($tanggalawal . ' 00:00:00');
// PERBAIKAN: Menyamakan batas waktu akhir dengan rekap_kehadiran.php (hingga ujung hari)
$sampai = strtotime(date('Y-m-d') . ' 23:59:59');
$judul = 'Rekap Murid Tidak Hadir s.d. ' . tanggal_indo($sampai, 'tanggal');

$PAGE->set_title($judul);
$PAGE->set_heading($judul);

echo $OUTPUT->header();
echo $OUTPUT->heading($judul, 2);

echo html_writer::div(
    '<strong>Periode :</strong><br>' .
    tanggal_indo($dari, 'tanggal') .
    ' s.d. ' .
    tanggal_indo($sampai, 'tanggal'),
    'alert alert-info'
);

/* =====================================================
   AMBIL SEMUA KELAS
===================================================== */
$kelaslist = $DB->get_records('cohort', null, 'name ASC');

if (!$kelaslist) {
    echo $OUTPUT->notification('Belum ada data kelas.', 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

global $USER;
$my_cohortid = 0;
$json_mapping = get_config('local_jurnalmengajar', 'wali_kelas_mapping');
$mapping = json_decode($json_mapping, true);

if (is_array($mapping)) {
    foreach ($mapping as $cohortid => $userid) {
        if ($userid == $USER->id && isset($kelaslist[$cohortid])) {
            $my_cohortid = $cohortid;
            break;
        }
    }
}

if ($my_cohortid) {
    $kelas_wali = $kelaslist[$my_cohortid];
    unset($kelaslist[$my_cohortid]);
    $kelaslist = [$my_cohortid => $kelas_wali] + $kelaslist;
}

$priority = [
    'hadir'      => 0,
    'dispensasi' => 1,
    'sakit'      => 2,
    'ijin'       => 3,
    'alpa'       => 4,
];

/* =====================================================
   LOOP SETIAP KELAS
===================================================== */
foreach ($kelaslist as $kelas) {
    $members = $DB->get_records('cohort_members', ['cohortid' => $kelas->id]);
    if (!$members) {
        continue;
    }

    $userids = [];
    foreach ($members as $m) {
        $userids[] = $m->userid;
    }

    list($insql, $params) = $DB->get_in_or_equal($userids);
    $users = $DB->get_records_sql("
        SELECT id, firstname, lastname
        FROM {user}
        WHERE id $insql
        ORDER BY lastname ASC
    ", $params);

    if (!$users) {
        continue;
    }

    $hasil = [];
    foreach ($users as $u) {
        $hasil[$u->id] = [
            'nama' => trim($u->lastname),
            'sakit' => 0,
            'ijin' => 0,
            'alpa' => 0,
            'dispensasi' => 0,
            'jumlah' => 0
        ];
    }

    $jurnals = $DB->get_records_sql("
        SELECT id, timecreated, jamke, matapelajaran, absen
        FROM {local_jurnalmengajar}
        WHERE kelas = ? AND timecreated BETWEEN ? AND ?
        ORDER BY timecreated ASC
    ", [$kelas->id, $dari, $sampai]);

    if (empty($jurnals)) {
        continue;
    }

    /* ============================================
       MODE PER HARI (SINKRONISASI TOTAL DGN REKAP_KEHADIRAN)
    ============================================ */
    $perhari = [];
    $semuatanggal = [];

    foreach ($jurnals as $jurnal) {
        $tgl = date('Y-m-d', $jurnal->timecreated);
        $semuatanggal[$tgl] = true;

        $jamke  = array_filter(array_map('trim', explode(',', (string)($jurnal->jamke ?? ''))));
        $jmljam = count($jamke) ?: 1;

        $absen = json_decode($jurnal->absen, true) ?? [];
        $lookup = [];
        foreach ($absen as $nama => $alasan) {
            $lookup[mb_strtolower(trim($nama), 'UTF-8')] = strtolower(trim($alasan));
        }

        foreach ($users as $uid => $u) {
            $namasiswa = mb_strtolower(trim($u->lastname), 'UTF-8');

            if (!isset($perhari[$uid][$tgl])) {
                $perhari[$uid][$tgl] = ['hadir' => 0, 'sakit' => 0, 'ijin' => 0, 'alpa' => 0, 'dispensasi' => 0];
            }
            if (!jurnalmengajar_is_peserta_mapel($uid, $jurnal->matapelajaran)) {
                continue;
            }
            
            $status = isset($lookup[$namasiswa]) ? $lookup[$namasiswa] : 'hadir';
            if (!isset($perhari[$uid][$tgl][$status])) {
                $status = 'hadir';
            }

            $perhari[$uid][$tgl][$status] += $jmljam;
        }
    }

    /* ============================================
       REKAP PER HARI
    ============================================ */
    $alltanggal = array_keys($semuatanggal);
    sort($alltanggal);

    foreach ($users as $uid => $u) {
        foreach ($alltanggal as $tgl) {
            if (empty($perhari[$uid][$tgl])) { continue; }

            $h = $perhari[$uid][$tgl]['hadir'];
            $tot = array_sum($perhari[$uid][$tgl]);
            if ($tot == 0) { continue; }

            $nonhadir = $tot - $h;

            if ($nonhadir == 0) {
                $statushari = 'hadir';
            } else if ($h == 0) {
                $statushari = 'hadir';
                $maxprio = -1;
                foreach (['dispensasi','sakit','ijin','alpa'] as $st) {
                    if (!empty($perhari[$uid][$tgl][$st])) {
                        $p = $priority[$st] ?? 0;
                        if ($p > $maxprio) {
                            $maxprio = $p;
                            $statushari = $st;
                        }
                    }
                }
            } else {
                $statushari = 'hadir';
            }

            if ($statushari != 'hadir') {
                $hasil[$uid][$statushari]++;
            }
        }
    }

    /* ============================================
       HITUNG JUMLAH TIDAK HADIR & FILTERING
    ============================================ */
    foreach ($hasil as $id => $h) {
        $hasil[$id]['jumlah'] = $h['sakit'] + $h['ijin'] + $h['alpa'] + $h['dispensasi'];
    }

    $hasil = array_filter($hasil, function($h) {
        return $h['jumlah'] > 0;
    });

    if (empty($hasil)) {
        continue;
    }

    usort($hasil, function($a, $b) {
        if ($a['jumlah'] == $b['jumlah']) {
            return strcmp($a['nama'], $b['nama']);
        }
        return $b['jumlah'] <=> $a['jumlah'];
    });

    /* ============================================
       TAMPILKAN KELAS & TOMBOL COPY (WALI KELAS)
    ============================================ */
    echo html_writer::tag('hr', '', ['style' => 'margin-top:30px;margin-bottom:20px;']);

    $copy_html = '';
    
    if (!empty($my_cohortid) && $kelas->id == $my_cohortid) {
        $text_to_copy = "Rekap Murid Tidak Hadir s.d. " . tanggal_indo($sampai, 'tanggal') . "\n";
        $text_to_copy .= "Periode :\n";
        $text_to_copy .= tanggal_indo($dari, 'tanggal') . " s.d. " . tanggal_indo($sampai, 'tanggal') . "\n";
        $text_to_copy .= "Kelas " . $kelas->name . "\n";
        
        $no_copy = 1;
        foreach ($hasil as $h) {
            $text_to_copy .= $no_copy . ". " . $h['nama'] . "   Sakit: " . $h['sakit'] . ", Ijin: " . $h['ijin'] . ", Alpa: " . $h['alpa'] . ", Dispensasi: " . $h['dispensasi'] . ", Jumlah Tidak Hadir: " . $h['jumlah'] . "\n";
            $no_copy++;
        }

        $textarea_id = 'copytext_' . $kelas->id;
        
        $copy_html .= html_writer::tag('textarea', htmlspecialchars($text_to_copy), [
            'id' => $textarea_id,
            'style' => 'display:none;'
        ]);
        
        // PERBAIKAN: Hapus float-right, gunakan class utilitas agar ramah mobile
        $copy_html .= html_writer::tag('button', '📋 Copy Data Kelas', [
            'class' => 'btn btn-sm btn-success mt-2 mt-md-0',
            'onclick' => "copyRekapToClipboard('$textarea_id')"
        ]);
    }

    // PERBAIKAN: Gunakan Flexbox (d-flex) agar layout turun ke bawah di HP, sejajar di PC
    echo html_writer::div(
        '<strong class="mb-2 mb-md-0 d-block d-md-inline">Kelas '.$kelas->name.'</strong>' . $copy_html,
        'alert alert-secondary mb-2 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center'
    );

    /* ============================================
       TABEL DAFTAR SISWA TIDAK HADIR
    ============================================ */
    echo html_writer::start_div('table-responsive card mb-4 mt-3');
    echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover m-0 table-bordered align-middle text-nowrap']);
    echo html_writer::start_tag('thead', ['class' => 'table-dark text-center']);
    echo html_writer::start_tag('tr');
    
    // PERBAIKAN: Menghapus style width tetap (hardcoded) agar bisa menyesuaikan otomatis
    echo html_writer::tag('th', 'No');
    echo html_writer::tag('th', 'Nama Murid', ['class' => 'text-start']);
    echo html_writer::tag('th', 'Sakit');
    echo html_writer::tag('th', 'Ijin');
    echo html_writer::tag('th', 'Alpa');
    echo html_writer::tag('th', 'Dispensasi');
    echo html_writer::tag('th', 'Jumlah Tidak Hadir');
    
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    $no_tabel = 1;

    foreach ($hasil as $h) {
        $namasiswa = ucwords(strtolower($h['nama']));
        echo html_writer::start_tag('tr', ['class' => 'text-center']);
        echo html_writer::tag('td', $no_tabel++);
        echo html_writer::tag('td', $namasiswa, ['class' => 'text-start fw-bold text-wrap']); // text-wrap agar nama panjang bisa turun
        echo html_writer::tag('td', $h['sakit'] ?: '<span class="text-muted">0</span>');
        echo html_writer::tag('td', $h['ijin'] ?: '<span class="text-muted">0</span>');
        echo html_writer::tag('td', $h['alpa'] ? '<span class="text-danger fw-bold">' . $h['alpa'] . '</span>' : '<span class="text-muted">0</span>');
        echo html_writer::tag('td', $h['dispensasi'] ?: '<span class="text-muted">0</span>');
        echo html_writer::tag('td', html_writer::tag('strong', $h['jumlah']));
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();
}

/* =====================================================
   TOMBOL & SCRIPT COPY
===================================================== */
$tombolkembali = html_writer::link('#', '⬅ Kembali', [
    'class' => 'btn btn-secondary w-100', // PERBAIKAN: Lebar tombol kembali penuh di HP
    'onclick' => 'history.back();return false;'
]);

echo html_writer::div($tombolkembali, 'mb-3 d-md-inline-block'); // Kembali normal di Desktop

echo html_writer::script("
    function copyRekapToClipboard(elementId) {
        var text = document.getElementById(elementId).value;
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Data rekap kelas berhasil disalin!');
            });
        } else {
            var textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                alert('Data rekap kelas berhasil disalin!');
            } catch (err) {
                alert('Gagal menyalin data');
            }
            document.body.removeChild(textArea);
        }
    }
");

echo $OUTPUT->footer();
