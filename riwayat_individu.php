<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

global $DB, $PAGE, $OUTPUT;

$PAGE->set_url(new moodle_url('/local/jurnalmengajar/riwayat_individu.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Riwayat Individu Murid');
$PAGE->set_heading('Riwayat Individu Murid');

echo $OUTPUT->header();

/*
=====================================================
PARAMETER
=====================================================
*/
$cohortid = optional_param('cohortid', 0, PARAM_INT);
$muridid  = optional_param('muridid', 0, PARAM_INT);
$filter = optional_param('filter', '', PARAM_TEXT);
/*
=====================================================
AMBIL COHORT
=====================================================
*/
$cohorts = $DB->get_records_sql("
    SELECT id, name
    FROM {cohort}
    ORDER BY name ASC
");

/*
=====================================================
FORM FILTER (DIPERBAIKI STRUKTUR GRIDNYA)
=====================================================
*/
echo html_writer::start_tag('form', [
    'method' => 'get',
    'class'  => 'mb-4 p-3 bg-light rounded border' // Ditambahkan background box agar filter terlihat fokus
]);

echo html_writer::start_div('row align-items-end'); // Menjajarkan dropdown dan tombol di bawah

/* FILTER KELAS */
echo html_writer::start_div('col-md-4 mb-2 mb-md-0');
echo html_writer::tag('label', 'Kelas', ['class' => 'font-weight-bold mb-1']);

$options = [0 => 'Pilih Kelas'];
foreach ($cohorts as $c) {
    $options[$c->id] = $c->name;
}

echo html_writer::select($options, 'cohortid', $cohortid, false, [
    'class'    => 'form-control form-control-sm',
    'onchange' => 'this.form.submit()'
]);
echo html_writer::end_div();

/* FILTER SISWA */
echo html_writer::start_div('col-md-4 mb-2 mb-md-0');
echo html_writer::tag('label', 'Nama Murid', ['class' => 'font-weight-bold mb-1']);

$siswaoptions = [0 => 'Pilih Murid'];
if ($cohortid) {
    $siswas = $DB->get_records_sql("
        SELECT u.id, u.firstname, u.lastname
        FROM {cohort_members} cm
        JOIN {user} u ON u.id = cm.userid
        WHERE cm.cohortid = ?
        ORDER BY u.lastname ASC
    ", [$cohortid]);

    foreach ($siswas as $s) {
        $siswaoptions[$s->id] = ucwords(strtolower($s->lastname));
    }
}

echo html_writer::select($siswaoptions, 'muridid', $muridid, false, [
    'class' => 'form-control form-control-sm'
]);
echo html_writer::end_div();

/* TOMBOL AKSI */
echo html_writer::start_div('col-md-4 d-flex');
echo html_writer::tag('button', '<i class="fa fa-search"></i> Tampilkan', [
    'type'  => 'submit',
    'class' => 'btn btn-primary btn-sm flex-grow-1 mr-2'
]);
echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/riwayat_individu.php'),
    '<i class="fa fa-refresh"></i> Reset',
    ['class' => 'btn btn-secondary btn-sm']
);
echo html_writer::end_div();

echo html_writer::end_div(); // End row
echo html_writer::end_tag('form');

/*
=====================================================
TAMPILKAN DATA & TIMELINE PROCESSING
=====================================================
*/
if ($muridid) {
    $timeline = [];
    $murid = $DB->get_record('user', ['id' => $muridid]);

    if (!$murid) {
        echo $OUTPUT->notification('Murid tidak ditemukan', 'notifyproblem');
        echo $OUTPUT->footer();
        exit;
    }

    $namasiswa = trim($murid->lastname);

    // --- Kumpulan Counter untuk Statistik ---
    $count_absen = 0;
    $count_izin = 0;
    $count_bk = 0;
    $count_wali = 0;
    $count_walikelas = 0;
    $count_mapel = 0;

    /* 1. ABSEN JURNAL MENGAJAR */
    $rows = $DB->get_records('local_jurnalmengajar');
    foreach ($rows as $r) {
        $match = false;
        $alasan = '-';

        if (!empty($r->absenid)) {
            $absenid = json_decode($r->absenid, true);
            if (is_array($absenid) && array_key_exists($muridid, $absenid)) {
                $match = true;
                $alasan = $absenid[$muridid];
            }
        } elseif (!empty($r->absen)) {
            $absen = json_decode($r->absen, true);
            if (is_array($absen)) {
                foreach ($absen as $nama => $ket) {
                    if (trim($nama) == $namasiswa) {
                        $match = true;
                        $alasan = $ket;
                        break;
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

            $timeline[] = [
                'time'      => $r->timecreated,
                'kelas'     => $kelas,
                'jenis'     => 'Tidak Hadir',
                'catatan'   => $alasan,
                'guru'      => $guru ? $guru->lastname : '-',
                'kategori'  => 'absen'
            ];
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
        $timeline[] = [
            'time'      => $r->timecreated,
            'kelas'     => $kelas,
            'jenis'     => 'Surat Izin',
            'catatan'   => '<b>Alasan:</b> ' . $r->alasan . '<br><b>Keperluan:</b> ' . $r->keperluan,
            'guru'      => $penginput ? $penginput->lastname : '-',
            'kategori'  => 'izin'
        ];
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
                foreach ($peserta as $nama) {
                    if (trim($nama) == $namasiswa) { $match = true; break; }
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
            $timeline[] = [
                'time'      => $r->timecreated,
                'kelas'     => $kelas,
                'jenis'     => 'Layanan BK',
                'catatan'   => '<b>Topik:</b> ' . $r->topik . '<br><b>Catatan:</b> ' . $r->catatan,
                'guru'      => $guru ? $guru->lastname : '-',
                'kategori'  => 'bk'
            ];
            $count_bk++;
        }
    }

    /* 4. PEMBINAAN */
    $pembinaan = $DB->get_records('local_jurnalpembinaan');
    foreach ($pembinaan as $r) {
        $match = false;
        if (!empty($r->pesertaid)) {
            $pesertaid = json_decode($r->pesertaid, true);
            if (is_array($pesertaid) && in_array($muridid, $pesertaid)) { $match = true; }
        } elseif (!empty($r->peserta)) {
            $peserta = json_decode($r->peserta, true);
            if (is_array($peserta)) {
                foreach ($peserta as $nama) {
                    if (trim($nama) == $namasiswa) { $match = true; break; }
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
            $timeline[] = [
                'time'      => $r->timecreated,
                'kelas'     => $kelas,
                'jenis'     => 'Pembinaan BK',
                'catatan'   => '<b>Permasalahan:</b> ' . $r->permasalahan . '<br><b>Tindakan:</b> ' . $r->tindakan,
                'guru'      => $guru ? $guru->lastname : '-',
                'kategori'  => 'pembinaan'
            ];
            $count_bk++; // Digabung ke Counter BK/Kesiswaan
        }
    }

    /* 5. JURNAL GURU WALI */
    $wali = $DB->get_records('local_jurnalguruwali',    ['muridid' => $muridid]);
    foreach ($wali as $r) {
        $guru = $DB->get_record('user', ['id' => $r->guruid]);
        $timeline[] = [
            'time'      => $r->timecreated,
            'kelas'     => $r->kelas,
            'jenis'     => 'Pendampingan Wali',
            'catatan'   => '<b>Topik:</b> ' . $r->topik . '<br><b>Tindak Lanjut:</b> ' . $r->tindaklanjut,
            'guru'      => $guru ? $guru->lastname : '-',
            'kategori'  => 'wali'
        ];
        $count_wali++;
    }
    /* 6. PEMBINAAN WALI KELAS */
$walikelas = $DB->get_records_sql(
    "
    SELECT *
    FROM {local_jurnalwalikelas}
    WHERE jenis = 'pembinaan'
      AND muridid = ?
    ORDER BY timecreated ASC
    ",
    [$muridid]
);

foreach ($walikelas as $r) {

    $guru = $DB->get_record(
        'user',
        ['id' => $r->userid]
    );

    $kelas = $r->kelas;

    if (is_numeric($kelas)) {

        $cohort = $DB->get_record(
            'cohort',
            ['id' => $kelas]
        );

        if ($cohort) {
            $kelas = $cohort->name;
        }
    }

    $timeline[] = [
        'time'      => $r->timecreated,
        'kelas'     => $kelas,
        'jenis'     => 'Pembinaan Wali Kelas',
        'catatan'   =>
            '<b>Topik:</b> ' .
            $r->topik .
            '<br><b>Tindak Lanjut:</b> ' .
            $r->tindaklanjut,
        'guru'      => $guru
            ? $guru->lastname
            : '-',
        'kategori'  => 'walikelas'
    ];

    $count_walikelas++;
}
		/* 7. PEMBINAAN GURU MAPEL */

		$mapel = $DB->get_records_sql(
		    "
		    SELECT *
		    FROM {local_jurnalmengajar_pembinaanmapel}
		    WHERE muridid = ?
		    ORDER BY timecreated ASC
		    ",
		    [$muridid]
		);

		foreach ($mapel as $r) {

		    $guru = $DB->get_record(
			'user',
			['id' => $r->userid]
		    );

		    $kelas = $r->kelas;

		    if (is_numeric($kelas)) {

			$cohort = $DB->get_record(
			    'cohort',
			    ['id' => $kelas]
			);

			if ($cohort) {
			    $kelas = $cohort->name;
			}
		    }

		    $timeline[] = [
			'time'      => $r->timecreated,
			'kelas'     => $kelas,
			'jenis'     => 'Pembinaan Guru Mapel',
			'catatan'   =>
			    '<b>Jenis:</b> ' .
			    $r->jenis .
			    '<br><b>Catatan:</b> ' .
			    $r->catatan .
			    '<br><b>Tindak Lanjut:</b> ' .
			    $r->tindaklanjut,
			'guru'      => $guru
			    ? $guru->lastname
			    : '-',
			'kategori'  => 'mapel'
		    ];

		    $count_mapel++;
		}

    /* SORT BY TIME DESCENDING */
    usort($timeline, function($a, $b) {
        return $b['time'] <=> $a['time'];
    });

    /* HEADER NAMA MURID */
    echo html_writer::start_div('d-flex justify-content-between align-items-center my-4 pb-2 border-bottom');
echo html_writer::tag(
    'h3',
    '<i class="fa fa-user-circle text-muted"></i> ' .  ucwords(strtolower($murid->lastname)),   ['class' => 'm-0 font-weight-bold'] );
    echo html_writer::tag('span', 'Total Log: ' . count($timeline), ['class' => 'badge badge-dark p-2']);
    echo html_writer::end_div();

/* =====================================================
RIWAYAT KELAS SISWA
===================================================== */

$riwayatkelas = $DB->get_records_sql(
    "
    SELECT rk.tahunajaran,
           c.name AS namakelas
    FROM {local_jurnalmengajar_riwayatkelas} rk
    JOIN {cohort} c
         ON c.id = rk.cohortid
    WHERE rk.userid = ?
    ORDER BY rk.tahunajaran ASC
    ",
    [$muridid]
);

if ($riwayatkelas) {

    echo html_writer::start_div(
        'alert alert-info mb-4'
    );

    echo html_writer::tag(
        'h5',
        '📚 Riwayat Kelas',
        ['class' => 'mb-2']
    );

    foreach ($riwayatkelas as $r) {

        echo html_writer::tag(
            'div',
            $r->tahunajaran .
            ' → <strong>' .
            format_string($r->namakelas) .
            '</strong>'
        );
    }

    echo html_writer::end_div();
}

    /* =====================================================
    TAMBAHAN: COUNTER STATS CARDS (DASHBOARD MINI)
    =====================================================
    */
    echo html_writer::start_div('row mb-4');
    
    $cards = [
    ['Tidak Hadir KBM', $count_absen, 'bg-danger text-white', 'absen'],
    ['Izin Keluar/Masuk/Pulang', $count_izin, 'bg-warning text-dark', 'izin'],
    ['Layanan & Pembinaan BK', $count_bk, 'bg-info text-white', 'bk'],
    ['Pendampingan Guru Wali', $count_wali, 'bg-primary text-white', 'wali'],
    ['Pembinaan Wali Kelas', $count_walikelas, 'bg-success text-white', 'walikelas'],
    ['Pembinaan Guru Mapel', $count_mapel, 'text-white', 'mapel']
];

    foreach ($cards as $card) {
        echo html_writer::start_div('col-6 col-md mb-2');

$url = new moodle_url(
    '/local/jurnalmengajar/riwayat_individu.php',
    [
        'cohortid' => $cohortid,
        'muridid'  => $muridid,
        'filter' => ($filter == $card[3]) ? '' : $card[3]
    ]
);

echo html_writer::start_tag('a', [
    'href'  => $url,
    'style' => 'text-decoration:none;'
]);
$activeclass = '';

if ($filter == $card[3]) {
    $activeclass = ' border border-dark';
}

$extrastyle = '';

if ($card[3] == 'mapel') {
    $extrastyle = 'background:#6f42c1;color:white;';
}

echo html_writer::start_div(
    'card text-center shadow-sm ' . $card[2] . $activeclass,
    ['style' => $extrastyle]
);

echo html_writer::start_div('card-body p-2');

echo html_writer::tag(
    'h6',
    $card[0],
    ['class' => 'text-uppercase small font-weight-bold m-0']
);

echo html_writer::tag(
    'h2',
    $card[1],
    ['class' => 'font-weight-bold my-1']
);

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_tag('a');

echo html_writer::end_div();
    }
    
    echo html_writer::end_div(); // End Row Cards

    /*
    =====================================================
    TABEL DENGAN INTERAKSI HOVER
    =====================================================
    */
    echo '<div class="table-responsive">'; // Biar mobile-friendly tidak pecah
    echo '<table class="table table-bordered table-hover bg-white shadow-sm">';
    echo '<thead class="thead-dark">';
    echo '<tr>';
    echo '<th style="width: 15%;">Waktu</th>';
    echo '<th style="width: 10%;">Kelas</th>';
    echo '<th style="width: 15%;">Kategori</th>';
    echo '<th>Detail Catatan Riwayat</th>';
    echo '<th style="width: 18%;">Guru / Penginput</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
$jumlahditampilkan = 0;
foreach ($timeline as $t) {

    if ($filter) {

        if ($filter == 'bk') {

            if (
                $t['kategori'] != 'bk' &&
                $t['kategori'] != 'pembinaan'
            ) {
                continue;
            }

        } elseif ($t['kategori'] != $filter) {

            continue;
        }
    }

    $badge = '';

    switch ($t['kategori']) {

        case 'absen':
            $badge = '<span class="badge badge-secondary d-block p-2">Tidak hadir KBM</span>';
            break;

        case 'izin':
            $badge = '<span class="badge badge-warning d-block p-2">Surat Izin</span>';
            break;

        case 'bk':
            $badge = '<span class="badge badge-info d-block p-2">Layanan BK</span>';
            break;

        case 'pembinaan':
            $badge = '<span class="badge badge-danger d-block p-2">Pembinaan BK</span>';
            break;

        case 'wali':
            $badge = '<span class="badge badge-primary d-block p-2">Guru Wali</span>';
            break;
        case 'walikelas':
	    $badge = '<span class="badge badge-success d-block p-2">Wali Kelas</span>';
	    break;
	case 'mapel':
	    $badge = '<span class="badge badge-secondary d-block p-2">Guru Mapel</span>';
	    break;
    }

    echo '<tr>';
$jumlahditampilkan++;
        // KELOMPOK WAKTU: Menghapus text-muted agar warna teks menjadi hitam/gelap normal dan jelas
        echo '<td class="align-middle" style="font-size: 0.9rem; color: #212529;">' . tanggal_indo($t['time']) . '</td>';
        
        echo '<td class="align-middle font-weight-bold">' . s($t['kelas']) . '</td>';
        echo '<td class="align-middle">' . $badge . '</td>';
        echo '<td class="align-middle text-justify lh-base" style="font-size: 0.95rem;">' . format_text($t['catatan']) . '</td>';
        
        // KELOMPOK GURU: Warna teks gelap kontras, ikon biru solid, teks nama guru berbobot normal (biasa)
echo '<td class="align-middle" style="color: #212529;">' .
        '<i class="fa fa-user mr-1" style="color: #0f6cbf;"></i> ' . format_string($t['guru']) . 
     '</td>';
             
        echo '</tr>';
    }

    if ($jumlahditampilkan == 0) {
        echo '<tr><td colspan="5" class="text-center text-muted p-4"><i>Belum ada data riwayat yang tercatat untuk murid ini.</i></td></tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>'; // End table-responsive
}

echo $OUTPUT->footer();
