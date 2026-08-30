<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

global $DB, $PAGE, $OUTPUT, $USER;


/* =====================================================
   PARAMETER
===================================================== */

$page = optional_param('page', 0, PARAM_INT);

// Default: guru yang sedang login.
$filterguru = optional_param(
    'guru',
    $USER->id,
    PARAM_INT
);

$perpage = 50;
$offset = $page * $perpage;


/* =====================================================
   SETUP PAGE
===================================================== */

$PAGE->set_context($context);

$PAGE->set_url(
    new moodle_url(
        '/local/jurnalmengajar/riwayat_terbaru_muridbinaan.php',
        [
            'guru' => $filterguru
        ]
    )
);

//$PAGE->set_pagelayout('standard');

$PAGE->set_title(
    'Riwayat Terbaru Murid Binaan'
);

$PAGE->set_heading(
    'Riwayat Terbaru Murid Binaan Guru Wali'
);


/* =====================================================
   AMBIL LIST GURU WALI
===================================================== */

$listguru = [];

$sqlguru = "
    SELECT DISTINCT
        gw.guruid,
        u.lastname
    FROM {local_jurnalmengajar_guruwali} gw
    JOIN {user} u
        ON u.id = gw.guruid
    ORDER BY u.lastname
";

$rowsguru = $DB->get_records_sql($sqlguru);

foreach ($rowsguru as $g) {

$listguru[$g->guruid] = $g->lastname;
}


/* =====================================================
   VALIDASI GURU
===================================================== */

if (
    empty($filterguru) ||
    !isset($listguru[$filterguru])
) {

    $filterguru = $USER->id;
}


/* =====================================================
   AMBIL MURID BINAAN GURU
===================================================== */

$sqlmurid = "
    SELECT DISTINCT
        muridid
    FROM {local_jurnalmengajar_guruwali}
    WHERE guruid = ?
";

$rowsmurid = $DB->get_records_sql(
    $sqlmurid,
    [$filterguru]
);

$muridbinaan = [];

foreach ($rowsmurid as $r) {

    $muridbinaan[] =
        (int)$r->muridid;
}


/* =====================================================
   HELPER NAMA MURID
===================================================== */

$namamuridcache = [];

function jm_binaan_get_namamurid($userid) {

    global $DB, $namamuridcache;

    if (isset($namamuridcache[$userid])) {

        return $namamuridcache[$userid];
    }

    if (!$userid) {

        return '-';
    }

    $u = $DB->get_record(
        'user',
        ['id' => $userid],
        'id, firstname, lastname'
    );

    $namamuridcache[$userid] =
        $u
        ? format_nama_siswa($u->lastname)
        : '-';

    return $namamuridcache[$userid];
}


/* =====================================================
   HELPER NAMA GURU
===================================================== */

$namagurucache = [];

function jm_binaan_get_namaguru($userid) {

    global $DB, $namagurucache;

    if (isset($namagurucache[$userid])) {
        return $namagurucache[$userid];
    }

    if (!$userid) {
        return '-';
    }

    $u = $DB->get_record(
        'user',
        ['id' => $userid],
        'id, firstname, lastname'
    );

    $namagurucache[$userid] =
        $u ? $u->lastname : '-';

    return $namagurucache[$userid];
}


/* =====================================================
   HELPER CEK MURID BINAAN
===================================================== */

function jm_is_murid_binaan(
    $muridid,
    $muridbinaan
) {

    return in_array(
        (int)$muridid,
        $muridbinaan,
        true
    );
}


/* =====================================================
   MULAI TIMELINE
===================================================== */

$timeline = [];


/* =====================================================
   JIKA BELUM ADA MURID BINAAN
===================================================== */

echo $OUTPUT->header();

echo html_writer::start_div(
    'card mb-3 shadow-sm'
);

echo html_writer::start_div(
    'card-body'
);


/* =====================================================
   FILTER GURU WALI
===================================================== */

echo html_writer::start_tag(
    'form',
    [
        'method' => 'get',
        'class' => 'mb-3'
    ]
);

echo html_writer::start_div(
    'row align-items-center'
);

echo html_writer::start_div(
    'col-md-6'
);

echo html_writer::tag(
    'strong',
    'Filter Guru Wali'
);

echo html_writer::end_div();


echo html_writer::start_div(
    'col-md-6'
);

echo html_writer::select(
    $listguru,
    'guru',
    $filterguru,
    false,
    [
        'class' => 'form-control',
        'onchange' =>
            'this.form.submit();'
    ]
);

echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::end_tag(
    'form'
);


$namagurufilter =
    $listguru[$filterguru]
    ?? '-';


echo html_writer::div(
    '<strong>Guru Wali:</strong> ' .
    s($namagurufilter) .
    '<br>' .
    '<strong>Jumlah Murid Binaan:</strong> ' .
    count($muridbinaan),
    'alert alert-primary mb-0'
);


echo html_writer::end_div();
echo html_writer::end_div();


/* =====================================================
   JIKA TIDAK ADA MURID BINAAN
===================================================== */

if (empty($muridbinaan)) {

    echo html_writer::div(
        'Belum ada murid binaan untuk guru wali ini.',
        'alert alert-warning'
    );

    echo $OUTPUT->footer();

    exit;
}


/* =====================================================
   1. SURAT IZIN
===================================================== */

list(
    $in_sql,
    $in_params
) = $DB->get_in_or_equal(
    $muridbinaan,
    SQL_PARAMS_QM
);

$sql = "
    SELECT *
    FROM {local_jurnalmengajar_suratizin}
    WHERE userid $in_sql
    ORDER BY timecreated DESC
    LIMIT 200
";

$rows = $DB->get_records_sql(
    $sql,
    $in_params
);

foreach ($rows as $r) {

    $murid =
        jm_binaan_get_namamurid(
            $r->userid
        );

    $kelas = '-';

    if (!empty($r->kelasid)) {

        $cohort = $DB->get_record(
            'cohort',
            ['id' => $r->kelasid]
        );

        if ($cohort) {

            $kelas =
                $cohort->name;
        }
    }


    $ringkasan =
        '<div><b>Keperluan:</b> ' .
        s($r->keperluan) .
        '</div>' .

        '<div><b>Alasan:</b> ' .
        s($r->alasan) .
        '</div>';


    if (!empty($r->catatan)) {

        $ringkasan .=
            '<div><b>Pembinaan:</b> ' .
            s($r->catatan) .
            '</div>';
    }


    $timeline[] = [

        'time' =>
            $r->timecreated,

        'muridid' =>
            $r->userid,

        'murid' =>
            $murid,

        'kelas' =>
            $kelas,

        'kategori' =>
            'izin',

        'ringkasan' =>
            $ringkasan,

        'guru' =>
            jm_binaan_get_namaguru(
                $r->penginput
            )
    ];
}


/* =====================================================
   2. PEMBINAAN GURU MAPEL
===================================================== */

$sql = "
    SELECT *
    FROM {local_jurnalmengajar_pembinaanmapel}
    WHERE muridid $in_sql
    ORDER BY timecreated DESC
    LIMIT 200
";

$rows = $DB->get_records_sql(
    $sql,
    $in_params
);

foreach ($rows as $r) {

    $timeline[] = [

        'time' =>
            $r->timecreated,

        'muridid' =>
            $r->muridid,

        'murid' =>
            jm_binaan_get_namamurid(
                $r->muridid
            ),

        'kelas' =>
            get_nama_kelas(
                $r->kelas
            ),

        'kategori' =>
            'mapel',

        'ringkasan' =>
            $r->catatan,

        'guru' =>
            jm_binaan_get_namaguru(
                $r->userid
            )
    ];
}


/* =====================================================
   3. PEMBINAAN WALI KELAS
===================================================== */

$sql = "
    SELECT *
    FROM {local_jurnalwalikelas}
    WHERE jenis = 'pembinaan'
      AND muridid $in_sql
    ORDER BY timecreated DESC
    LIMIT 200
";

$rows = $DB->get_records_sql(
    $sql,
    $in_params
);

foreach ($rows as $r) {

    $timeline[] = [

        'time' =>
            $r->timecreated,

        'muridid' =>
            $r->muridid,

        'murid' =>
            jm_binaan_get_namamurid(
                $r->muridid
            ),

        'kelas' =>
            get_nama_kelas(
                $r->kelas
            ),

        'kategori' =>
            'walikelas',

        'ringkasan' =>

            '<div><b>Permasalahan:</b> ' .
            s($r->topik) .
            '</div>' .

            '<div><b>Tindak Lanjut/Solusi:</b> ' .
            s($r->tindaklanjut) .
            '</div>',

        'guru' =>
            jm_binaan_get_namaguru(
                $r->userid
            )
    ];
}


/* =====================================================
   4. JURNAL GURU WALI
===================================================== */

$sql = "
    SELECT *
    FROM {local_jurnalguruwali}
    WHERE muridid $in_sql
    ORDER BY timecreated DESC
    LIMIT 200
";

$rows = $DB->get_records_sql(
    $sql,
    $in_params
);

foreach ($rows as $r) {

    $timeline[] = [

        'time' =>
            $r->timecreated,

        'muridid' =>
            $r->muridid,

        'murid' =>
            jm_binaan_get_namamurid(
                $r->muridid
            ),

        'kelas' =>
            $r->kelas,

        'kategori' =>
            'wali',

        'ringkasan' =>

            '<div><b>Topik:</b> ' .
            s($r->topik) .
            '</div>' .

            '<div><b>Tindak Lanjut:</b> ' .
            s($r->tindaklanjut) .
            '</div>',

        'guru' =>
            jm_binaan_get_namaguru(
                $r->guruid
            )
    ];
}


/* =====================================================
   5. LAYANAN BK
===================================================== */

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


    $peserta =
        json_decode(
            $r->pesertaid,
            true
        );


    if (!is_array($peserta)) {

        continue;
    }


    foreach (
        $peserta
        as
        $muridid
    ) {

        if (
            !jm_is_murid_binaan(
                $muridid,
                $muridbinaan
            )
        ) {

            continue;
        }


        $timeline[] = [

            'time' =>
                $r->timecreated,

            'muridid' =>
                $muridid,

            'murid' =>
                jm_binaan_get_namamurid(
                    $muridid
                ),

            'kelas' =>
                get_nama_kelas(
                    $r->kelas
                ),

            'kategori' =>
                'bk',

            'ringkasan' =>
                s($r->topik),

            'guru' =>
                jm_binaan_get_namaguru(
                    $r->userid
                )
        ];
    }
}


/* =====================================================
   6. PEMBINAAN BK
===================================================== */

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


    $peserta =
        json_decode(
            $r->pesertaid,
            true
        );


    if (!is_array($peserta)) {

        continue;
    }


    foreach (
        $peserta
        as
        $muridid
    ) {

        if (
            !jm_is_murid_binaan(
                $muridid,
                $muridbinaan
            )
        ) {

            continue;
        }


        $timeline[] = [

            'time' =>
                $r->timecreated,

            'muridid' =>
                $muridid,

            'murid' =>
                jm_binaan_get_namamurid(
                    $muridid
                ),

            'kelas' =>
                get_nama_kelas(
                    $r->kelas
                ),

            'kategori' =>
                'pembinaan',

            'ringkasan' =>

                '<div><b>Permasalahan:</b> ' .
                s($r->permasalahan) .
                '</div>' .

                '<div><b>Tindakan:</b> ' .
                s($r->tindakan) .
                '</div>',

            'guru' =>
                jm_binaan_get_namaguru(
                    $r->userid
                )
        ];
    }
}


/* =====================================================
   7. TIDAK HADIR KBM
===================================================== */

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


    $absenid =
        json_decode(
            $r->absenid,
            true
        );


    if (!is_array($absenid)) {

        continue;
    }


    $kelas =
        $r->kelas;


    if (is_numeric($kelas)) {

        $cohort =
            $DB->get_record(
                'cohort',
                ['id' => $kelas]
            );


        if ($cohort) {

            $kelas =
                $cohort->name;
        }
    }


    foreach (
        $absenid
        as
        $muridid => $status
    ) {

        if (
            !jm_is_murid_binaan(
                $muridid,
                $muridbinaan
            )
        ) {

            continue;
        }


        $timeline[] = [

            'time' =>
                $r->timecreated,

            'muridid' =>
                (int)$muridid,

            'murid' =>
                jm_binaan_get_namamurid(
                    $muridid
                ),

            'kelas' =>
                $kelas,

            'kategori' =>
                'absen',

            'ringkasan' =>
                'Tidak hadir (' .
                s($status) .
                ')',

            'guru' =>
                jm_binaan_get_namaguru(
                    $r->userid
                )
        ];
    }
}


/* =====================================================
   SORTING
===================================================== */

usort(
    $timeline,
    function($a, $b) {

        return
            $b['time']
            <=>
            $a['time'];
    }
);


/* =====================================================
   TOTAL SEBELUM PAGINATION
===================================================== */

$total =
    count($timeline);


/* =====================================================
   PAGINATION
===================================================== */

$timeline =
    array_slice(
        $timeline,
        $offset,
        $perpage
    );


/* =====================================================
   INFO
===================================================== */

echo html_writer::div(
    '📡 Menampilkan aktivitas terbaru murid binaan. ' .
    'Maksimal ' .
    $perpage .
    ' aktivitas per halaman.',
    'alert alert-info'
);


/* =====================================================
   TABEL
===================================================== */

echo
    '<div class="table-responsive">';


echo
    '<table class="table table-bordered table-hover bg-white shadow-sm">';


echo
    '<thead class="thead-dark">';


echo
    '<tr>';

echo '<th>Waktu</th>';
echo '<th>Murid</th>';
echo '<th>Kelas</th>';
echo '<th>Kategori</th>';
echo '<th>Ringkasan</th>';
echo '<th>Guru</th>';

echo
    '</tr>';


echo
    '</thead>';


echo
    '<tbody>';


foreach ($timeline as $t) {


    /* LINK RIWAYAT INDIVIDU */

    $url =
        new moodle_url(
            '/local/jurnalmengajar/riwayat_individu.php',
            [
                'muridid' =>
                    $t['muridid']
            ]
        );


    /* BADGE KATEGORI */

    $badge = '';


    switch (
        $t['kategori']
    ) {


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
                '<span
                    class="badge"
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


    echo
        '<tr>';


    echo
        '<td>' .
        tanggal_indo(
            $t['time']
        ) .
        '</td>';


    echo
        '<td>' .

        html_writer::link(
            $url,
            format_string(
                $t['murid']
            ),
            [
                'class' =>
                    'font-weight-bold'
            ]
        ) .

        '</td>';


    echo
        '<td>' .

        format_string(
            $t['kelas']
        ) .

        '</td>';


    echo
        '<td>' .
        $badge .
        '</td>';


    echo
        '<td>' .

        format_text(
            $t['ringkasan'],
            FORMAT_HTML
        ) .

        '</td>';


    echo
        '<td>' .

        format_string(
            $t['guru']
        ) .

        '</td>';


    echo
        '</tr>';
}


/* =====================================================
   DATA KOSONG
===================================================== */

if (empty($timeline)) {


    echo
        '<tr>';


    echo
        '<td
            colspan="6"
            class="text-center text-muted p-4">';


    echo
        'Belum ada aktivitas untuk murid binaan.';


    echo
        '</td>';


    echo
        '</tr>';
}


echo
    '</tbody>';


echo
    '</table>';


echo
    '</div>';


/* =====================================================
   PAGING
===================================================== */

$pagingurl =
    new moodle_url(
        '/local/jurnalmengajar/riwayat_terbaru_muridbinaan.php',
        [
            'guru' =>
                $filterguru
        ]
    );


echo
    $OUTPUT->paging_bar(
        $total,
        $page,
        $perpage,
        $pagingurl
    );


/* =====================================================
   FOOTER
===================================================== */

echo
    $OUTPUT->footer();
