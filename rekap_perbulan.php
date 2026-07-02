<?php
//require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(
    new moodle_url(
        '/local/jurnalmengajar/rekap_perbulan.php'
    )
);

// ===============================
// FILTER
// ===============================

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

$filter_userid = optional_param(
    'userid',
    0,
    PARAM_INT
);

// ===============================
// RENTANG BULAN
// ===============================

list(
    $tanggal_awal_bulan,
    $tanggal_akhir_bulan
) = jurnalmengajar_get_range_bulan(
    $bulan,
    $tahun
);

// ===============================
// DETEKSI SEMESTER
// ===============================

$semester =
    ($bulan >= 7)
        ? 'Ganjil'
        : 'Genap';

$namasekolah = get_config(
    'local_jurnalmengajar',
    'nama_sekolah'
);

$tahunajaran = get_config(
    'local_jurnalmengajar',
    'tahun_ajaran'
);

$judul = 'Rekap Bulanan Jurnal Mengajar Guru';

$PAGE->set_title($judul);
$PAGE->set_heading($judul);

// ===============================
// AMBIL DATA
// ===============================

global $DB;

$entries = $DB->get_records_select(
    'local_jurnalmengajar',
    'timecreated BETWEEN ? AND ?',
    [
        $tanggal_awal_bulan,
        $tanggal_akhir_bulan
    ]
);

$beban =
    jurnalmengajar_get_beban_jam_guru_bulan(
        $bulan,
        $tahun
    );

$all_userids = array_keys($beban);

$all_users = [];

if (!empty($all_userids)) {

    list($in_sql, $params)
        = $DB->get_in_or_equal($all_userids);

    $all_users =
        $DB->get_records_select_menu(
            'user',
            "id $in_sql",
            $params,
            'lastname',
            'id,lastname'
        );
}

// ===============================
// HEADER
// ===============================

echo $OUTPUT->header();

echo html_writer::start_div(
    'd-flex justify-content-between align-items-center mb-4 flex-wrap'
);

echo html_writer::start_div();

if (!empty($namasekolah)) {

    echo html_writer::tag(
        'h3',
        strtoupper($namasekolah),
        [
            'class' =>
                'mb-1 font-weight-bold text-primary'
        ]
    );
}

if (!empty($tahunajaran)) {

    echo html_writer::tag(
        'div',
        'Tahun Ajaran '
        . $tahunajaran
        . ' • Semester '
        . $semester,
        [
            'class' =>
            'text-muted font-weight-bold'
        ]
    );
}

echo html_writer::end_div();

echo html_writer::div(

    html_writer::link(
        '#',
        '⬅ Kembali',
        [
            'class' =>
                'btn btn-outline-secondary shadow-sm',

            'onclick' =>
                'history.back();return false;'
        ]
    )

);

echo html_writer::end_div();

// ===============================
// WARNING CUTOFF
// ===============================

$daftar_kelas = [
    'VI',
    'IX',
    'XII'
];

$ada_yang_sudah_set = false;

foreach ($daftar_kelas as $kelas_level) {

    if (
        jurnalmengajar_get_cutoff_by_kelas(
            $kelas_level,
            $tanggal_awal_bulan
        )
    ) {

        $ada_yang_sudah_set = true;
        break;
    }
}

if (!$ada_yang_sudah_set) {

    echo html_writer::div(

        '⚠️ <strong>Perhatian:</strong>
        Tanggal berhenti KBM
        belum diatur.',

        'alert alert-warning mb-4'

    );
}

// ===============================
// CARD FILTER
// ===============================

echo html_writer::start_div(
    'card mb-4 shadow-sm border-0 bg-light'
);

echo html_writer::start_div('card-body');

echo html_writer::start_div(
    'row align-items-center'
);

// kiri

echo html_writer::start_div(
    'col-md-5'
);

echo html_writer::tag(
    'span',
    'Periode Bulan',
    [
        'class' =>
        'text-muted small d-block'
    ]
);

echo html_writer::tag(

    'span',

    tanggal_indo(
        $tanggal_awal_bulan,
        'tanggal'
    )
    . ' s/d '
    .
    tanggal_indo(
        $tanggal_akhir_bulan,
        'tanggal'
    ),

    [
        'class' =>
        'font-weight-bold h5'
    ]

);

echo html_writer::end_div();

// kanan

echo html_writer::start_div(
    'col-md-7'
);

echo '<form method="get"
class="form-inline justify-content-md-end">';

echo '<label class="mr-2">Bulan</label>';

echo '<select
name="bulan"
class="custom-select custom-select-sm mr-2">';

for ($i = 1; $i <= 12; $i++) {

    $selected =
        ($bulan == $i)
        ? 'selected'
        : '';

    echo '<option value="' . $i . '" '
        . $selected . '>'
        . tanggal_indo(
strtotime("$tahun-$i-01"),
            'bulan'
        )
        . '</option>';
}

echo '</select>';

echo '<label class="mr-2">
Tahun
</label>';

echo '<select
name="tahun"
class="custom-select custom-select-sm mr-2">';

for (
    $t = date('Y') - 2;
    $t <= date('Y') + 1;
    $t++
) {

    $selected =
        ($tahun == $t)
        ? 'selected'
        : '';

    echo "<option
value=\"$t\"
$selected>$t</option>";
}

echo '</select>';

echo '<label class="mr-2">Guru</label>';

echo '<select
name="userid"
class="custom-select custom-select-sm mr-2">';

echo '<option value="0">
Semua Guru
</option>';

foreach ($all_users as $id => $ln) {

    $selected =
        ($filter_userid == $id)
        ? 'selected'
        : '';

    echo '<option value="'
        . $id
        . '" '
        . $selected
        . '>'
        . ucwords(strtolower($ln))
        . '</option>';
}

echo '</select>';

echo '<button
class="btn btn-primary btn-sm">
Tampilkan
</button>';

echo '</form>';

echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::end_div();


// ===============================
// REKAP
// ===============================

$rekap = [];

foreach ($beban as $userid => $target) {

    if (
        $filter_userid
        &&
        $userid != $filter_userid
    ) {
        continue;
    }

    $rekap[$userid] = 0;
}

foreach ($entries as $e) {

    if (!isset($rekap[$e->userid])) {
        continue;
    }

    $jp = 0;

    if (!empty($e->jamke)) {

        $jp = count(
            array_filter(
                explode(',', $e->jamke)
            )
        );
    }

    $rekap[$e->userid] += $jp;
}

// urut nama

uksort(

    $rekap,

    function($a, $b) use ($all_users) {

        return strcmp(

            strtolower(
                $all_users[$a] ?? ''
            ),

            strtolower(
                $all_users[$b] ?? ''
            )

        );

    }

);


// ===============================
// TABEL
// ===============================

echo html_writer::start_div(
    'table-responsive shadow-sm rounded border'
);

echo '<table class="table
table-hover
table-striped
mb-0
text-nowrap">';

echo '<thead class="thead-dark">';

echo '<tr>';

echo '<th>No</th>';

echo '<th>Nama Guru</th>';

echo '<th class="text-center">
Realisasi
</th>';

echo '<th class="text-center">
Target
</th>';

echo '<th class="text-center">
Persentase
</th>';

echo '<th class="text-center">
Detail
</th>';

echo '</tr>';

echo '</thead>';

echo '<tbody>';

if (empty($rekap)) {

    echo '<tr>';

    echo '<td
colspan="6"
class="text-center
text-muted
py-4">';

    echo 'Belum ada jurnal pada bulan ini.';

    echo '</td>';

    echo '</tr>';

} else {

    $no = 1;

    foreach ($rekap as $userid => $jumlahjp) {

        $nama =
            ucwords(
                $all_users[$userid]
                ?? ''
            );

        $bebanbulan =
            $beban[$userid]
            ?? 0;

$targetakhir = $bebanbulan;

        if ($targetakhir > 0) {

            $persen = round(

                (
                    $jumlahjp
                    /
                    $targetakhir
                )
                *
                100

            );

        } else {

            $persen = null;

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

        echo '<tr>';

        echo '<td class="text-center">'
            . $no++
            . '</td>';

echo html_writer::tag(
    'td',
    $nama
);

        echo '<td class="text-center">'
            . $jumlahjp
            . ' JP</td>';
            
$teks_target = $targetakhir . ' JP';

            echo '<td class="text-center">';
            echo $teks_target;
            echo '</td>';

            $badge_text =
                ($persen === null)
                ? '-'
                : $persen . '%';

            echo '<td class="text-center">';

            echo html_writer::tag(

                'span',

                $badge_text,

                [
                    'class' =>
                        'badge badge-'
                        . $badge
                        . ' p-2 w-100',

                    'style' =>
                        'font-size:85%'
                ]

            );

            echo '</td>';

            $url =
                new moodle_url(

                    '/local/jurnalmengajar/rekap_perguru_bulan.php',

                    [
                        'userid' => $userid,
                        'bulan'  => $bulan,
                        'tahun'  => $tahun
                    ]

                );

            echo '<td class="text-center">';

            echo html_writer::link(

                $url,

                '🔍 Detail',

                [
                    'class' =>
                        'btn btn-sm btn-outline-primary'
                ]

            );

            echo '</td>';

            echo '</tr>';
    }
}

echo '</tbody>';

echo '</table>';

echo html_writer::end_div();

echo '
<style>

.table-danger-light{

    background:
    rgba(
        220,
        53,
        69,
        0.06
    );

}

.table-hover tbody tr.table-danger-light:hover{

    background:
    rgba(
        220,
        53,
        69,
        0.12
    );

}

.table th,
.table td{

    vertical-align:
    middle
    !important;

}

</style>';

echo $OUTPUT->footer();
