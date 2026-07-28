<?php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/lib_notifikasi.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(
    new moodle_url(
        '/local/jurnalmengajar/preview_notifikasi.php'
    )
);

$PAGE->set_title('Preview Notifikasi WhatsApp');
$PAGE->set_heading('Preview Notifikasi WhatsApp');

echo $OUTPUT->header();

/* ==========================================
 * FILTER
 * ========================================== */

$page = optional_param('page', 0, PARAM_INT);

$perpage = 50;
$offset  = $page * $perpage;

/* ==========================================
 * TIMELINE
 * ========================================== */

$timeline = [];

/* ==========================================
 * CACHE USER
 * ========================================== */

$namacache = [];

function jm_preview_nama_user($userid) {

    global $DB, $namacache;

    if (empty($userid)) {
        return '-';
    }

    if (isset($namacache[$userid])) {
        return $namacache[$userid];
    }

    $u = $DB->get_record(
        'user',
        ['id'=>$userid],
        'id,firstname,lastname'
    );

    if (!$u) {

        $namacache[$userid] = '-';

    } else {

        $namacache[$userid] =
            trim($u->firstname . ' ' . $u->lastname);
    }

    return $namacache[$userid];
}

/* ==========================================
 * HELPER KELAS
 * ========================================== */

function jm_preview_kelas($kelas) {

    global $DB;

    if (empty($kelas)) {
        return '-';
    }

    if (is_numeric($kelas)) {

        $cohort = $DB->get_record(
            'cohort',
            ['id'=>$kelas]
        );

        if ($cohort) {
            return $cohort->name;
        }
    }

    return $kelas;
}

/* ==========================================
 * HELPER NOWA LASTNAME
 * ========================================== */
function jm_preview_nama_wa(string $nomorwa): string {

    global $DB;

    $sql = "
        SELECT u.lastname
          FROM {user} u
          JOIN {user_info_data} d
            ON d.userid = u.id
          JOIN {user_info_field} f
            ON f.id = d.fieldid
         WHERE f.shortname = 'nowa'
           AND d.data = ?
         LIMIT 1
    ";

    $nama = $DB->get_field_sql($sql, [$nomorwa]);

    return $nama ?: '';
}
 
/* ==========================================
 * ICON
 * ========================================== */

function jm_preview_icon($jenis) {

    switch ($jenis) {

        case 'jurnal':
            return '📘';

        case 'nilai_harian':
            return '📝';

        case 'guruwali':
            return '👨‍🏫';

        case 'izinmurid':
            return '📄';

        case 'izinguru':
            return '📄';

        case 'layanan_bk':
            return '💬';

        case 'pembinaan':
            return '📋';

        case 'pembinaan_mapel':
            return '📚';

        case 'rekap_reminder':
            return '⏰';

        default:
            return '📌';
    }
}

/* ==========================================
 * WARNA CARD
 * ========================================== */

function jm_preview_border($jenis) {

    switch ($jenis) {

        case 'jurnal':
            return '#0d6efd';

        case 'nilai_harian':
            return '#198754';

        case 'guruwali':
            return '#0dcaf0';

        case 'izinmurid':
            return '#ffc107';

        case 'izinguru':
            return '#6c757d';

        case 'layanan_bk':
            return '#20c997';

        case 'pembinaan':
            return '#dc3545';

        case 'pembinaan_mapel':
            return '#6f42c1';

        default:
            return '#adb5bd';
    }
}

/* ==========================================
 * LINK WA
 * ========================================== */

function jm_preview_link_wa(
    $nomor,
    $pesan
) {

    return
        'https://wa.me/' .
        $nomor .
        '?text=' .
        rawurlencode($pesan);
}

$todaystart = strtotime('today');
$tomorrow   = strtotime('tomorrow');

/* ==========================================
 * MULAI ISI TIMELINE
 * JURNAL MENGAJAR
 * ========================================== */

$records = $DB->get_records_sql("
    SELECT *
    FROM {local_jurnalmengajar}
    WHERE timecreated >= ?
      AND timecreated < ?
    ORDER BY timecreated DESC
", [$todaystart, $tomorrow]);

foreach ($records as $r) {

    $timeline[] = [
        'time'   => $r->timecreated,
        'jenis'  => 'jurnal',
        'judul'  => 'Jurnal Mengajar',
        'userid' => $r->userid,
        'guru'   => jm_preview_nama_user($r->userid),
        'kelas' => jm_preview_kelas($r->kelas),
        'record' => $r
    ];
}

/* ==========================================
 * PEMBINAAN GURU MAPEL
 * ========================================== */

if ($DB->get_manager()->table_exists('local_jurnalmengajar_pembinaanmapel')) {

    $records = $DB->get_records_sql("
        SELECT *
        FROM {local_jurnalmengajar_pembinaanmapel}
        WHERE timecreated >= ?
          AND timecreated < ?
        ORDER BY timecreated DESC
    ", [$todaystart, $tomorrow]);

    foreach ($records as $r) {

        $timeline[] = [
            'time'   => $r->timecreated,
            'jenis'  => 'pembinaan_mapel',
            'judul'  => 'Pembinaan Guru Mapel',
            'userid' => $r->userid,
            'guru'   => jm_preview_nama_user($r->userid),
            'kelas'  => jm_preview_kelas($r->kelas),
            'record' => $r
        ];
    }
}

/* ==========================================
 * SURAT IZIN MURID
 * ========================================== */

if ($DB->get_manager()->table_exists('local_jurnalmengajar_suratizin')) {

    $records = $DB->get_records_sql("
        SELECT *
        FROM {local_jurnalmengajar_suratizin}
    WHERE timecreated >= ?
      AND timecreated < ?
    ORDER BY timecreated DESC
", [$todaystart, $tomorrow]);

    foreach ($records as $r) {

        $timeline[] = [
            'time'   => $r->timecreated,
            'jenis'  => 'izinmurid',
            'judul'  => 'Surat Izin Murid',
            'userid' => $r->userid,
            'guru'   => jm_preview_nama_user($r->userid),
            'kelas' => jm_preview_kelas($r->kelasid),
            'record' => $r
        ];
    }
}



/* ==========================================
 * NILAI HARIAN
 * ========================================== */

if ($DB->get_manager()->table_exists('local_jm_nilaiharian')) {

    $records = $DB->get_records_sql("
        SELECT *
        FROM {local_jm_nilaiharian}
    WHERE timecreated >= ?
      AND timecreated < ?
    ORDER BY timecreated DESC
", [$todaystart, $tomorrow]);

    foreach ($records as $r) {

        $timeline[] = [
            'time'   => $r->timecreated,
            'jenis'  => 'nilai_harian',
            'judul'  => 'Nilai Harian',
            'userid' => $r->userid,
            'guru'   => jm_preview_nama_user($r->userid),
            'kelas'  => jm_preview_kelas($r->cohortid),
            'record' => $r
        ];
    }
}


/* ==========================================
 * GURU WALI
 * ========================================== */

if ($DB->get_manager()->table_exists('local_jurnalguruwali')) {

    $records = $DB->get_records_sql("
        SELECT *
        FROM {local_jurnalguruwali}
    WHERE timecreated >= ?
      AND timecreated < ?
    ORDER BY timecreated DESC
", [$todaystart, $tomorrow]);

    foreach ($records as $r) {

        $timeline[] = [
            'time'   => $r->timecreated,
            'jenis'  => 'guruwali',
            'judul'  => 'Guru Wali',
            'userid' => $r->userid,
            'guru'   => jm_preview_nama_user($r->userid),
            'kelas' => jm_preview_kelas($r->kelas),
            'record' => $r
        ];
    }
}


/* ==========================================
 * LAYANAN BK
 * ========================================== */

if ($DB->get_manager()->table_exists('local_jurnallayananbk')) {

    $records = $DB->get_records_sql("
        SELECT *
        FROM {local_jurnallayananbk}
    WHERE timecreated >= ?
      AND timecreated < ?
    ORDER BY timecreated DESC
", [$todaystart, $tomorrow]);

    foreach ($records as $r) {

        $timeline[] = [
            'time'   => $r->timecreated,
            'jenis'  => 'layanan_bk',
            'judul'  => 'Layanan BK',
            'userid' => $r->userid,
            'guru'   => jm_preview_nama_user($r->userid),
            'kelas' => jm_preview_kelas($r->kelas),
            'record' => $r
        ];
    }
}


/* ==========================================
 * PEMBINAAN
 * ========================================== */

if ($DB->get_manager()->table_exists('local_jurnalpembinaan')) {

    $records = $DB->get_records_sql("
        SELECT *
        FROM {local_jurnalpembinaan}
    WHERE timecreated >= ?
      AND timecreated < ?
    ORDER BY timecreated DESC
", [$todaystart, $tomorrow]);

    foreach ($records as $r) {

        $timeline[] = [
            'time'   => $r->timecreated,
            'jenis'  => 'pembinaan',
            'judul'  => 'Pembinaan',
            'userid' => $r->userid,
            'guru'   => jm_preview_nama_user($r->userid),
            'kelas' => jm_preview_kelas($r->kelas),
            'record' => $r
        ];
    }
}


/* ==========================================
 * URUTKAN TIMELINE
 * ========================================== */

usort($timeline, function($a, $b) {
    return $b['time'] <=> $a['time'];
});


$total = count($timeline);

$timeline = array_slice(
    $timeline,
    $offset,
    $perpage
);
 
 
 /* ==========================================
 * TAMPILKAN TIMELINE
 * ========================================== */

if (empty($timeline)) {

    echo $OUTPUT->notification(
        'Belum ada data notifikasi.',
        'info'
    );

} else {

    foreach ($timeline as $i => $item) {

        $warna = jm_preview_border($item['jenis']);

        echo html_writer::start_div(
            'card mb-3 shadow-sm',
            [
                'style' =>
                    'border-left:6px solid '.$warna
            ]
        );

        echo html_writer::start_div('card-body');

        /* waktu */

        echo html_writer::tag(
            'div',
            userdate(
                $item['time'],
                '%d %B %Y %H:%M'
            ),
            [
                'class'=>'text-muted small mb-2'
            ]
        );

        /* judul */

        echo html_writer::tag(
            'h5',
            jm_preview_icon($item['jenis'])
            .' '
            .$item['judul']
        );

        echo html_writer::empty_tag('hr');
        
        
        /* guru atau murid */
        
        $label_pemohon = ($item['jenis'] === 'izinmurid') ? 'Murid :' : 'Guru :';

        echo html_writer::tag(
            'div',
            '<strong>' . $label_pemohon . '</strong> '
            .s($item['guru'])
        );

        /* kelas */

        echo html_writer::tag(
            'div',
            '<strong>Kelas :</strong> '
            .s($item['kelas'])
        );

        echo html_writer::empty_tag('hr');

        /*
         * Bagian 4
         */

$datawa = jm_build_data_template(
    $item['jenis'],
    $item['record']
);

$pesan = jm_preview_template(
    $item['jenis'],
    $datawa
);

$nomor = jm_get_nomor_tujuan(
    $item['jenis'],
    $datawa
);

echo html_writer::start_div(
    'alert alert-light'
);

echo html_writer::tag(
    'strong',
    'Preview Pesan'
);

echo html_writer::tag(
    'pre',
    s($pesan),
    [
        'style'=>'white-space:pre-wrap'
    ]
);

echo html_writer::end_div();

        /*
         * Tujuan
         */

        echo html_writer::tag(
            'strong',
            'Nomor Tujuan'
        );

if (empty($nomor)) {

    echo html_writer::div(
        '<em>Tidak ada tujuan.</em>',
        'text-muted mb-3'
    );

} else {

foreach ($nomor as $wa) {

    $nama = jm_preview_nama_wa($wa);

    if ($nama !== '') {

        echo html_writer::tag(
            'div',
            '<strong>' . s($nama) . '</strong> (' . s($wa) . ')'
        );

    } else {

        echo html_writer::tag(
            'div',
            s($wa)
        );

    }
}

}
        /*
         * tombol
         */

        echo html_writer::start_div(
            'mt-3'
        );

echo html_writer::tag(
    'button',
    '📋 Copy Pesan',
    [
        'class'   => 'btn btn-primary btn-sm mr-2',
'onclick' =>
    "navigator.clipboard.writeText("
    . json_encode($pesan)
    . ").then(()=>alert('Pesan berhasil disalin'));"
    ]
);

$linkwa = '';

if (!empty($nomor)) {
    $linkwa = jm_preview_link_wa(
        $nomor[0],
        $pesan
    );
}

        echo ' ';

if (!empty($linkwa)) {

echo html_writer::link(
    $linkwa,
    'WhatsApp',
    [
        'class'  => 'btn btn-success btn-sm',
        'target' => '_blank',
        'rel'    => 'noopener'
    ]
);

} else {

    echo html_writer::tag(
        'button',
        'WhatsApp',
        [
            'class'=>'btn btn-success btn-sm',
            'disabled'=>'disabled'
        ]
    );

}

        echo html_writer::end_div();

        echo html_writer::end_div();

        echo html_writer::end_div();

    }

}

/* ==========================================
 * PAGINATION
 * ========================================== */

echo $OUTPUT->paging_bar(
    $total,
    $page,
    $perpage,
    new moodle_url(
        '/local/jurnalmengajar/preview_notifikasi.php'
    )
);
