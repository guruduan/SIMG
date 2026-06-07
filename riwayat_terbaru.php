<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(
    new moodle_url(
        '/local/jurnalmengajar/riwayat_terbaru.php'
    )
);

$PAGE->set_title('Riwayat Murid Terbaru');
$PAGE->set_heading('Riwayat Murid Terbaru');

echo $OUTPUT->header();

$page = optional_param('page', 0, PARAM_INT);

$perpage = 50;
$offset = $page * $perpage;

$timeline = [];

//HELPER NAMA MURID
$namamuridcache = [];

function jm_get_namamurid($userid) {
    global $DB, $namamuridcache;

    if (isset($namamuridcache[$userid])) {
        return $namamuridcache[$userid];
    }

    if (!$userid) {
        return '-';
    }

    $u = $DB->get_record(
        'user',
        ['id' => $userid]
    );

    $namamuridcache[$userid] =
        $u
        ? ucwords(strtolower($u->lastname))
        : '-';

    return $namamuridcache[$userid];
}
// HELPER NAMA GURU
$namagurucache = [];

function jm_get_namaguru($userid) {
    global $DB, $namagurucache;

    if (isset($namagurucache[$userid])) {
        return $namagurucache[$userid];
    }

    $u = $DB->get_record('user', ['id' => $userid]);

    $namagurucache[$userid] =
        $u ? $u->lastname : '-';

    return $namagurucache[$userid];
}

//1 SURAT IZIN
$rows = $DB->get_records_sql(
  "
    SELECT *
    FROM {local_jurnalmengajar_suratizin}
    ORDER BY timecreated DESC
    LIMIT 200
    "
);

foreach ($rows as $r) {

    $murid = jm_get_namamurid($r->userid);

    $kelas = '-';

    if (!empty($r->kelasid)) {

        $cohort = $DB->get_record(
            'cohort',
            ['id' => $r->kelasid]
        );

        if ($cohort) {
            $kelas = $cohort->name;
        }
    }

    $timeline[] = [
        'time' => $r->timecreated,
        'muridid' => $r->userid,
        'murid' => $murid,
        'kelas' => $kelas,
        'kategori' => 'izin',
        'ringkasan' => $r->keperluan,
        'guru' => jm_get_namaguru($r->penginput)
    ];
}

// 2 PEMBINAAN GURU MAPEL
$rows = $DB->get_records_sql(
  "
    SELECT *
    FROM {local_jurnalmengajar_pembinaanmapel}
    ORDER BY timecreated DESC
    LIMIT 200
    "
);

foreach ($rows as $r) {

    $timeline[] = [
        'time' => $r->timecreated,
        'muridid' => $r->muridid,
        'murid' => jm_get_namamurid($r->muridid),
        'kelas' => get_nama_kelas($r->kelas),
        'kategori' => 'mapel',
        'ringkasan' => $r->catatan,
        'guru' => jm_get_namaguru($r->userid)
    ];
}

// 3 PEMBINAAN WALI KELAS
$rows = $DB->get_records_sql(
    "
    SELECT *
    FROM {local_jurnalwalikelas}
    WHERE jenis = 'pembinaan'
    ORDER BY timecreated DESC
    LIMIT 200
    "
);

foreach ($rows as $r) {

    $timeline[] = [
        'time' => $r->timecreated,
        'muridid' => $r->muridid,
        'murid' => jm_get_namamurid($r->muridid),
        'kelas' => get_nama_kelas($r->kelas),
        'kategori' => 'walikelas',
        'ringkasan' => $r->topik,
        'guru' => jm_get_namaguru($r->userid)
    ];
}

// 4 GURU WALI
$rows = $DB->get_records_sql(
    "
    SELECT *
    FROM {local_jurnalguruwali}
    ORDER BY timecreated DESC
    LIMIT 200
    "
);

foreach ($rows as $r) {

    $timeline[] = [
        'time' => $r->timecreated,
        'muridid' => $r->muridid,
        'murid' => jm_get_namamurid($r->muridid),
        'kelas' => $r->kelas,
        'kategori' => 'wali',
        'ringkasan' => $r->topik,
        'guru' => jm_get_namaguru($r->guruid)
    ];
}

//5 LAYANAN BK
$rows = $DB->get_records_sql(
    "
    SELECT *
    FROM {local_jurnallayananbk}
    ORDER BY timecreated DESC
    LIMIT 200
    "
);

foreach ($rows as $r) {

    if (empty($r->pesertaid)) {
    continue;
	}

	$peserta = json_decode(
	    $r->pesertaid,
	    true
	);

    if (!is_array($peserta)) {
        continue;
    }

    foreach ($peserta as $muridid) {

        $timeline[] = [
            'time' => $r->timecreated,
            'muridid' => $muridid,
            'murid' => jm_get_namamurid($muridid),
            'kelas' => $r->kelas,
            'kategori' => 'bk',
            'ringkasan' => $r->topik,
            'guru' => jm_get_namaguru($r->userid)
        ];
    }
}


//6 PEMBINAAN BK

$rows = $DB->get_records_sql(
    "
    SELECT *
    FROM {local_jurnalpembinaan}
    ORDER BY timecreated DESC
    LIMIT 200
    "
);

foreach ($rows as $r) {

    if (empty($r->pesertaid)) {
    continue;
	}

	$peserta = json_decode(
	    $r->pesertaid,
	    true
	);

    if (!is_array($peserta)) {
        continue;
    }

    foreach ($peserta as $muridid) {

        $timeline[] = [
            'time' => $r->timecreated,
            'muridid' => $muridid,
            'murid' => jm_get_namamurid($muridid),
            'kelas' => $r->kelas,
            'kategori' => 'pembinaan',
            'ringkasan' => shorten_text(
                $r->permasalahan,
                80
            ),
            'guru' => jm_get_namaguru($r->userid)
        ];
    }
}

/* 7. TIDAK HADIR KBM */

$rows = $DB->get_records_sql(
    "
    SELECT *
    FROM {local_jurnalmengajar}
    ORDER BY timecreated DESC
    LIMIT 200
    "
);

foreach ($rows as $r) {

    if (empty($r->absenid)) {
        continue;
    }

    $absenid = json_decode(
        $r->absenid,
        true
    );

    if (!is_array($absenid)) {
        continue;
    }

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

    foreach ($absenid as $muridid => $status) {

        $timeline[] = [
            'time'      => $r->timecreated,
            'muridid'   => $muridid,
            'murid'     => jm_get_namamurid($muridid),
            'kelas'     => $kelas,
            'kategori'  => 'absen',
            'ringkasan' => 'Tidak hadir (' . $status . ')',
            'guru' => jm_get_namaguru($r->userid)
        ];
    }
}

// SORTING DAN PAGINATION
usort(
    $timeline,
    function($a, $b) {
        return $b['time'] <=> $a['time'];
    }
);

$total = count($timeline);

$timeline = array_slice(
    $timeline,
    $offset,
    $perpage
);

echo html_writer::div(
    '📡 Menampilkan 50 aktivitas murid terbaru',
    'alert alert-info'
);

echo '<div class="table-responsive">';

echo '<table class="table table-bordered table-hover bg-white shadow-sm">';

echo '<thead class="thead-dark">';

echo '<tr>';
echo '<th>Waktu</th>';
echo '<th>Murid</th>';
echo '<th>Kelas</th>';
echo '<th>Kategori</th>';
echo '<th>Ringkasan</th>';
echo '<th>Guru</th>';
echo '</tr>';

echo '</thead>';
echo '<tbody>';

foreach ($timeline as $t) {

    $url = new moodle_url(
        '/local/jurnalmengajar/riwayat_individu.php',
        [
            'muridid' => $t['muridid']
        ]
    );

    $badge = '';

    switch ($t['kategori']) {

        case 'absen':
	    $badge =
		'<span class="badge badge-secondary">
		Tidak Hadir
		</span>';
	    break;
        
        case 'izin':
            $badge =
                '<span class="badge badge-warning">
                Surat Izin
                </span>';
            break;

        case 'bk':
            $badge =
                '<span class="badge badge-info">
                Layanan BK
                </span>';
            break;

        case 'pembinaan':
            $badge =
                '<span class="badge badge-danger">
                Pembinaan BK
                </span>';
            break;

        case 'wali':
            $badge =
                '<span class="badge badge-primary">
                Guru Wali
                </span>';
            break;

        case 'walikelas':
            $badge =
                '<span class="badge badge-success">
                Wali Kelas
                </span>';
            break;

        case 'mapel':
            $badge =
                '<span class="badge"
                style="background:#6f42c1;color:white;">
                Guru Mapel
                </span>';
            break;
   
        default:
            $badge =
                '<span class="badge badge-secondary">
                Lainnya
                </span>';
    }

    echo '<tr>';

    echo '<td>' .
        tanggal_indo($t['time']) .
        '</td>';

    echo '<td>' .
        html_writer::link(
            $url,
            format_string($t['murid'])
        ) .
        '</td>';

    echo '<td>' .
        format_string($t['kelas']) .
        '</td>';

    echo '<td>' .
        $badge .
        '</td>';

    echo '<td>' .
        format_string($t['ringkasan']) .
        '</td>';

    echo '<td>' .
        format_string($t['guru']) .
        '</td>';

    echo '</tr>';
}

if (empty($timeline)) {

    echo '<tr>';

    echo '<td colspan="6"
        class="text-center text-muted">';

    echo 'Belum ada aktivitas murid.';

    echo '</td>';

    echo '</tr>';
}

echo '</tbody>';
echo '</table>';

echo '</div>';

//PAGING
echo $OUTPUT->paging_bar(
    $total,
    $page,
    $perpage,
    new moodle_url(
        '/local/jurnalmengajar/riwayat_terbaru.php'
    )
);

//TERAKHIR
echo $OUTPUT->footer();
