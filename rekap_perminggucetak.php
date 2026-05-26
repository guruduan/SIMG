<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__.'/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(
    new moodle_url('/local/jurnalmengajar/rekap_perminggucetak.php')
);

// =====================
// PARAMETER
// =====================

$tanggalstring =
    get_config('local_jurnalmengajar', 'tanggalawalminggu')
    ?: '2025-07-01';

$tanggal_awal = new DateTime($tanggalstring);
$timestart = $tanggal_awal->getTimestamp();

$param_mingguke =
    optional_param('mingguke', 0, PARAM_INT);

$mingguawal =
    optional_param(
        'mingguawal',
        $param_mingguke ?: 1,
        PARAM_INT
    );

$mingguakhir =
    optional_param(
        'mingguakhir',
        $param_mingguke ?: 1,
        PARAM_INT
    );

$print =
    optional_param('print', 0, PARAM_INT);

$filter_userid =
    optional_param('userid', 0, PARAM_INT);

// =====================
// DETEKSI MINGGU
// =====================

if ($param_mingguke > 0) {

    $mingguke = $param_mingguke;

} else {

    $selisih_hari =
        floor((time() - $timestart) / (60 * 60 * 24));

    $mingguke =
        floor($selisih_hari / 7) + 1;

    if ($mingguke < 1) {
        $mingguke = 1;
    }
}

if ($mingguakhir < $mingguawal) {
    $mingguakhir = $mingguawal;
}

// =====================
// IDENTITAS
// =====================

$bulan_awal = (int)$tanggal_awal->format('n');

$semester =
    ($bulan_awal >= 7)
    ? 'Ganjil'
    : 'Genap';

$namasekolah =
    get_config('local_jurnalmengajar', 'nama_sekolah');

$tahunajaran =
    get_config('local_jurnalmengajar', 'tahun_ajaran');

$judul =
    "Rekap Mingguan Jurnal Mengajar Guru";

if ($print) {

    $judul =
        'Cetak Rekap Minggu '
        . $mingguawal .
        ' - ' .
        $mingguakhir;
}

$PAGE->set_title($judul);
$PAGE->set_heading($judul);

// =====================
// HEADER
// =====================

echo $OUTPUT->header();

// =====================
// HEADER NORMAL
// =====================

if (!$print) {

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
                    . $tahunajaran .
                    ' • Semester '
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
                        'btn btn-outline-secondary shadow-sm mt-2 mt-md-0',

                    'onclick' =>
                        'history.back(); return false;'
                ]
            )
        );

    echo html_writer::end_div();

    // =====================
    // FILTER
    // =====================

    echo html_writer::start_div(
        'card mb-4 shadow-sm border-0 bg-light'
    );

    echo html_writer::start_div('card-body p-3');

    echo html_writer::start_div(
        'row align-items-center'
    );

        echo html_writer::start_div(
            'col-md-12'
        );

            echo '<form method="get"
                class="form-inline m-0">';

            echo html_writer::start_div(
                'form-group mr-3 mb-2'
            );

                echo '
                <label class="mr-2 font-weight-bold small">
                    Minggu Awal
                </label>';

                echo '
                <input
                    type="number"
                    name="mingguawal"
                    value="' . $mingguawal . '"
                    min="1"
                    max="21"
                    class="form-control form-control-sm"
                    style="width:90px;">';

            echo html_writer::end_div();

            echo html_writer::start_div(
                'form-group mr-3 mb-2'
            );

                echo '
                <label class="mr-2 font-weight-bold small">
                    Minggu Akhir
                </label>';

                echo '
                <input
                    type="number"
                    name="mingguakhir"
                    value="' . $mingguakhir . '"
                    min="1"
                    max="21"
                    class="form-control form-control-sm"
                    style="width:90px;">';

            echo html_writer::end_div();

            echo html_writer::start_div(
                'form-group mr-3 mb-2'
            );

                echo '
                <label class="mr-2 font-weight-bold small">
                    Guru
                </label>';

                echo '
                <select
                    name="userid"
                    class="custom-select custom-select-sm">';

                echo '<option value="0">Semua Guru</option>';

                global $DB;

                $allguru =
                    $DB->get_records(
                        'user',
                        ['deleted' => 0],
                        'lastname ASC',
                        'id, lastname'
                    );

                foreach ($allguru as $g) {

                    $selected =
                        ($filter_userid == $g->id)
                        ? 'selected'
                        : '';

                    echo '
                    <option
                        value="' . $g->id . '"
                        ' . $selected . '>
                        '
                        . ucwords(
                            strtolower($g->lastname)
                        ) .
                    '
                    </option>';
                }

                echo '</select>';

            echo html_writer::end_div();

            echo '
            <button
                type="submit"
                class="btn btn-primary btn-sm mr-2 mb-2">
                Tampilkan
            </button>';

            $urlprint = new moodle_url(
                '/local/jurnalmengajar/rekap_perminggucetak.php',
                [
                    'mingguawal' => $mingguawal,
                    'mingguakhir' => $mingguakhir,
                    'userid' => $filter_userid,
                    'print' => 1
                ]
            );

            echo html_writer::link(
                $urlprint,
                '🖨 Cetak PDF',
                [
                    'class' =>
                        'btn btn-danger btn-sm mb-2',
                    'target' => '_blank'
                ]
            );

            echo '</form>';

        echo html_writer::end_div();

    echo html_writer::end_div(); // row
    echo html_writer::end_div(); // card-body
    echo html_writer::end_div(); // card
}

// =====================
// LOOP MINGGU
// =====================

for (
    $mingguke = $mingguawal;
    $mingguke <= $mingguakhir;
    $mingguke++
) {

    // =====================
    // RENTANG MINGGU
    // =====================

    $awal_minggu = clone $tanggal_awal;

    $awal_minggu->modify(
        '+' . (($mingguke - 1) * 7) . ' days'
    );

    $tanggal_awal_minggu_ini =
        $awal_minggu->getTimestamp();

    $akhir_minggu = clone $awal_minggu;

    $akhir_minggu->modify('+6 days');

    $tanggal_akhir_minggu_ini =
        $akhir_minggu->getTimestamp() + 86399;

    // =====================
    // JUDUL PRINT
    // =====================

    if ($print) {

        echo '
        <div style="
            text-align:center;
            margin-top:20px;
            margin-bottom:20px;
        ">

            <h2>
                REKAP MINGGU KE-' . $mingguke . '
            </h2>

            <div>
                '
                . tanggal_indo(
                    $tanggal_awal_minggu_ini,
                    'tanggal'
                )
                . ' s/d '
                . tanggal_indo(
                    $tanggal_akhir_minggu_ini,
                    'tanggal'
                ) .
            '
            </div>

        </div>';
    }

    // =====================
    // AMBIL DATA
    // =====================

    $entries = $DB->get_records_select(
        'local_jurnalmengajar',
        'timecreated BETWEEN ? AND ?',
        [
            $tanggal_awal_minggu_ini,
            $tanggal_akhir_minggu_ini
        ]
    );

    $beban =
        jurnalmengajar_get_beban_jam_guru_by_date(
            $tanggal_awal_minggu_ini
        );

    $all_userids = array_keys($beban);

    $all_users = [];

    if (!empty($all_userids)) {

        list($in_sql, $params) =
            $DB->get_in_or_equal($all_userids);

        $all_users =
            $DB->get_records_select_menu(
                'user',
                "id $in_sql",
                $params,
                'lastname',
                'id, lastname'
            );
    }

    // =====================
    // REKAP
    // =====================

    $rekap = [];

    foreach ($beban as $userid => $beban_minggu) {

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

        $userid = $e->userid;

        if (!isset($rekap[$userid])) {
            continue;
        }

        $jam =
            !empty($e->jamke)
            ? count(
                array_filter(
                    explode(',', $e->jamke)
                )
            )
            : 0;

        $rekap[$userid] += $jam;
    }

    uksort(
        $rekap,
        function($a, $b) use ($all_users) {

            return strcmp(
                strtolower($all_users[$a] ?? ''),
                strtolower($all_users[$b] ?? '')
            );
        }
    );

    // =====================
    // TABEL
    // =====================

    echo html_writer::start_div(
        'table-responsive shadow-sm rounded border mb-4'
    );

    echo html_writer::start_tag(
        'table',
        [
            'class' =>
                'table table-hover table-striped mb-0 text-nowrap'
        ]
    );

    echo html_writer::start_tag(
        'thead',
        [
            'class' =>
                'thead-dark text-uppercase small'
        ]
    );

    echo html_writer::start_tag('tr');

    echo html_writer::tag(
        'th',
        'No',
        [
            'class' =>
                'text-center align-middle'
        ]
    );

    echo html_writer::tag(
        'th',
        'Nama Guru'
    );

    echo html_writer::tag(
        'th',
        'Realisasi'
    );

    echo html_writer::tag(
        'th',
        'Beban'
    );

    echo html_writer::tag(
        'th',
        'Persentase'
    );

    echo html_writer::end_tag('tr');

    echo html_writer::end_tag('thead');

    echo html_writer::start_tag('tbody');

    if (empty($rekap)) {

        echo html_writer::start_tag('tr');

        echo html_writer::tag(
            'td',
            'Tidak ada data',
            [
                'colspan' => 5,
                'class' =>
                    'text-center text-muted'
            ]
        );

        echo html_writer::end_tag('tr');

    } else {

        $no = 1;

        foreach ($rekap as $userid => $jumlahjam) {

            $lastname =
                $all_users[$userid]
                ?? '(Tidak Ditemukan)';

            $nama = ucwords($lastname);

            $beban_minggu =
                $beban[$userid] ?? 0;

            $pengurang_libur =
                jurnalmengajar_get_pengurang_target_libur(
                    $userid,
                    $tanggal_awal_minggu_ini,
                    $tanggal_akhir_minggu_ini
                );

            $target_final =
                max(
                    0,
                    $beban_minggu
                    - $pengurang_libur
                );

            $persen =
                ($target_final > 0)
                ? round(
                    ($jumlahjam / $target_final)
                    * 100
                )
                : 0;

            $persen = min($persen, 100);

            if ($persen >= 80) {

                $badge_class = 'badge-success';

            } elseif ($persen >= 50) {

                $badge_class = 'badge-info';

            } else {

                $badge_class = 'badge-danger';
            }

            echo html_writer::start_tag('tr');

            echo html_writer::tag(
                'td',
                $no++,
                [
                    'class' =>
                        'text-center'
                ]
            );

            echo html_writer::tag(
                'td',
                $nama
            );

            echo html_writer::tag(
                'td',
                $jumlahjam . ' JP',
                [
                    'class' =>
                        'text-center'
                ]
            );

            echo html_writer::tag(
                'td',
                $target_final . ' JP',
                [
                    'class' =>
                        'text-center'
                ]
            );

            $badge =
                html_writer::tag(
                    'span',
                    $persen . '%',
                    [
                        'class' =>
                            'badge ' . $badge_class
                    ]
                );

            echo html_writer::tag(
                'td',
                $badge,
                [
                    'class' =>
                        'text-center'
                ]
            );

            echo html_writer::end_tag('tr');
        }
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();

    // =====================
    // PAGE BREAK
    // =====================

    if (
        $print
        &&
        $mingguke < $mingguakhir
    ) {

        echo '
        <div style="
            page-break-after:always;
        "></div>';
    }
}

// =====================
// CSS
// =====================

echo '
<style>

.table th,
.table td {
    vertical-align: middle !important;
}

@media print {

    .btn,
    form,
    .navbar,
    .drawer,
    .secondary-navigation,
    #page-header {
        display:none !important;
    }

    #region-main-box,
    #page,
    .pagelayout-standard #page.drawers {
        margin:0 !important;
        padding:0 !important;
    }

    body {
        font-size:11px;
    }

    table {
        width:100%;
    }

    tr {
        page-break-inside:avoid;
    }

    @page {
        size: A4 portrait;
        margin: 10mm;
    }
}

</style>';

// =====================
// AUTO PRINT
// =====================

if ($print) {

    echo '
    <script>
        window.onload = function() {
            window.print();
        }
    </script>';
}

// =====================
// FOOTER
// =====================

echo $OUTPUT->footer();
