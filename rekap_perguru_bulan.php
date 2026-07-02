<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();

require_capability(
    'local/jurnalmengajar:submit',
    $context
);

$PAGE->set_context($context);

$PAGE->set_url(
    new moodle_url(
        '/local/jurnalmengajar/rekap_perguru_bulan.php'
    )
);

// =====================================
// PARAMETER
// =====================================

$userid = required_param(
    'userid',
    PARAM_INT
);

$bulan = optional_param(
    'bulan',
    date('n'),
    PARAM_INT
);

$tahun = optional_param(
    'tahun',
    date('Y'),
    PARAM_INT
);

// =====================================
// RENTANG BULAN
// =====================================

list(
    $tanggal_awal,
    $tanggal_akhir
) = jurnalmengajar_get_range_bulan(
    $bulan,
    $tahun
);

// =====================================
// DATA GURU
// =====================================

global $DB;

$guru = $DB->get_record(
    'user',
    [
        'id' => $userid
    ],
    '*',
    MUST_EXIST
);

$namaguru =
    fullname($guru);

// =====================================
// TARGET BULAN
// =====================================

$beban =
    jurnalmengajar_get_beban_jam_guru_bulan(
        $bulan,
        $tahun
    );

$targetbulan =
    $beban[$userid] ?? 0;

// =====================================
// JUDUL
// =====================================

$judul =
    'Detail Rekap Bulanan Guru';

$PAGE->set_title($judul);

$PAGE->set_heading($judul);

echo $OUTPUT->header();

// =====================================
// HEADER
// =====================================

echo html_writer::start_div(
    'd-flex justify-content-between align-items-center mb-4'
);

echo html_writer::start_div();

echo html_writer::tag(
    'h3',
    $namaguru,
    [
        'class' =>
        'text-primary mb-1'
    ]
);

echo html_writer::tag(

    'div',

    'Periode : '
    .
    tanggal_indo(
        $tanggal_awal,
        'tanggal'
    )
    .
    ' s/d '
    .
    tanggal_indo(
        $tanggal_akhir,
        'tanggal'
    ),

    [
        'class' =>
        'text-muted'
    ]

);

echo html_writer::end_div();

echo html_writer::link(

    '#',

    '⬅ Kembali',

    [

        'class' =>
        'btn btn-outline-secondary',

        'onclick' =>
        'history.back();return false;'

    ]

);

echo html_writer::end_div();

// =====================================
// AMBIL DATA JURNAL
// =====================================

$entries = $DB->get_records_select(

    'local_jurnalmengajar',

    'userid = ?
     AND timecreated BETWEEN ? AND ?',

    [

        $userid,

        $tanggal_awal,

        $tanggal_akhir

    ],

    'timecreated ASC'

);

// =====================================
// MULAI TABEL
// =====================================

echo html_writer::start_div(
    'table-responsive shadow-sm rounded border'
);

echo '<table class="table table-striped table-hover">';

echo '<thead class="thead-dark">';

echo '<tr>';

echo '<th>No</th>';

echo '<th>Tanggal</th>';

echo '<th>Hari</th>';

echo '<th>Kelas</th>';

echo '<th>Mata Pelajaran</th>';

echo '<th>Jam Ke</th>';

echo '<th>JP</th>';

echo '<th>Materi</th>';

echo '</tr>';

echo '</thead>';

echo '<tbody>';

$totaljp = 0;

$no = 1;

if (empty($entries)) {

    echo html_writer::start_tag('tr');

    echo html_writer::tag(
        'td',
        'Belum ada jurnal pada bulan ini.',
        [
            'colspan' => 8,
            'class' => 'text-center text-muted py-4'
        ]
    );

    echo html_writer::end_tag('tr');

} else {

    foreach ($entries as $e) {

        $jp = 0;

        if (!empty($e->jamke)) {

            $jamlist = array_filter(
                explode(',', $e->jamke)
            );

            $jp = count($jamlist);

            $jamke = implode(', ', $jamlist);

        } else {

            $jamke = '-';

        }

        $totaljp += $jp;

        echo html_writer::start_tag('tr');

        echo html_writer::tag(
            'td',
            $no++,
            ['class' => 'text-center']
        );

	echo html_writer::tag(
	    'td',
	    tanggal_indo(
		$e->timecreated,
		'tanggal'
	    )
	);

	echo html_writer::tag(
	    'td',
	    tanggal_indo(
		$e->timecreated,
		'hari'
	    )
	);

echo html_writer::tag(
    'td',
    s(get_nama_kelas((int)$e->kelas))
);

        echo html_writer::tag(
            'td',
            s($e->matapelajaran)
        );

        echo html_writer::tag(
            'td',
            $jamke,
            [
                'class' => 'text-center'
            ]
        );

        echo html_writer::tag(
            'td',
            $jp,
            [
                'class' => 'text-center font-weight-bold'
            ]
        );

        echo html_writer::tag(
	    'td',
	    shorten_text(
		format_string($e->materi),
		100
	    )
	);

        echo html_writer::end_tag('tr');

    }

}

echo html_writer::end_tag('tbody');

echo html_writer::end_tag('table');

echo html_writer::end_div();

// =====================================
// RINGKASAN
// =====================================

$persen = null;

if ($targetbulan > 0) {

    $persen = round(
        ($totaljp / $targetbulan) * 100
    );

    $persen = min(
        100,
        $persen
    );
}

$badge = 'secondary';

if ($persen !== null) {

    if ($persen >= 80) {

        $badge = 'success';

    } elseif ($persen >= 50) {

        $badge = 'info';

    } else {

        $badge = 'danger';

    }

}

echo html_writer::start_div(
    'card shadow-sm mt-4'
);

echo html_writer::div(
    '<strong>Ringkasan Bulan</strong>',
    'card-header bg-primary text-white'
);

echo html_writer::start_div(
    'card-body'
);

echo html_writer::start_tag(
    'table',
    [
        'class' =>
        'table table-bordered mb-0'
    ]
);

echo html_writer::start_tag('tbody');

echo html_writer::tag(

    'tr',

    html_writer::tag(
        'th',
        'Target Bulan',
        ['style'=>'width:30%']
    )
    .
    html_writer::tag(
        'td',
        $targetbulan . ' JP'
    )

);

echo html_writer::tag(

    'tr',

    html_writer::tag(
        'th',
        'Realisasi'
    )
    .
    html_writer::tag(
        'td',
        $totaljp . ' JP'
    )

);

$badgehtml = html_writer::tag(

    'span',

    $persen === null
        ? '-'
        : $persen . '%',

    [
        'class' =>
        'badge badge-' . $badge,
        'style' =>
        'font-size:100%;padding:8px 20px;'
    ]

);

echo html_writer::tag(

    'tr',

    html_writer::tag(
        'th',
        'Persentase'
    )
    .
    html_writer::tag(
        'td',
        $badgehtml
    )

);

echo html_writer::end_tag('tbody');

echo html_writer::end_tag('table');

echo html_writer::end_div();

echo html_writer::end_div();


// =====================================
// TOMBOL
// =====================================

echo html_writer::start_div(
    'mt-4 text-center'
);

echo html_writer::link(

    new moodle_url(
        '/local/jurnalmengajar/rekap_perbulan.php',
        [
            'bulan' => $bulan,
            'tahun' => $tahun
        ]
    ),

    '⬅ Kembali ke Rekap Bulanan',

    [
        'class' =>
        'btn btn-secondary'
    ]

);

echo html_writer::end_div();


// =====================================
// CSS
// =====================================

echo '
<style>

.table th,
.table td{

    vertical-align:
    middle
    !important;

}

.card{

    border-radius:
    10px;

}

.badge{

    min-width:
    80px;

}

</style>';

echo $OUTPUT->footer();
