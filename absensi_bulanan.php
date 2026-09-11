<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
jurnalmengajar_log_page_access();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

global $DB, $PAGE, $OUTPUT, $USER;

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/absensi_bulanan.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Daftar Hadir Siswa');
$PAGE->set_heading('Daftar Hadir Siswa');
$PAGE->requires->jquery();


// ======================================================
// DAFTAR KELAS
// ======================================================

$kelaslist = $DB->get_records_menu(
    'cohort',
    null,
    'name ASC',
    'id, name'
);


// ======================================================
// MAPPING WALI KELAS
// Sumber: setting wali_kelas_mapping
// Format: cohortid => userid
// ======================================================

$json_mapping = get_config(
    'local_jurnalmengajar',
    'wali_kelas_mapping'
);

$mapping = json_decode($json_mapping, true);

if (!is_array($mapping)) {
    $mapping = [];
}


// ======================================================
// DEFAULT KELAS
// Jika user login adalah wali kelas,
// otomatis pilih kelasnya.
// ======================================================

$default_kelas = 0;

foreach ($mapping as $cohortid => $userid) {
    if (
        (int)$userid === (int)$USER->id &&
        isset($kelaslist[$cohortid])
    ) {
        $default_kelas = (int)$cohortid;
        break;
    }
}


// ======================================================
// DEFAULT BULAN
// ======================================================

$default_bulan = date('Y-m');


// ======================================================
// PARAMETER
// ======================================================

$kelasid = optional_param(
    'kelas',
    $default_kelas,
    PARAM_INT
);

$bulan = optional_param(
    'bulan',
    $default_bulan,
    PARAM_RAW
);
$onlymine = optional_param(
    'onlymine',
    0,
    PARAM_BOOL
);


// Validasi format bulan.
if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) {
    $bulan = $default_bulan;
}


// ======================================================
// TANGGAL AWAL DAN AKHIR BULAN
// ======================================================

$tanggalawal = strtotime($bulan . '-01 00:00:00');

$tahun = (int)date('Y', $tanggalawal);
$bulanangka = (int)date('m', $tanggalawal);

$jumlahhari = cal_days_in_month(
    CAL_GREGORIAN,
    $bulanangka,
    $tahun
);

$tanggalakhir = strtotime(
    $bulan . '-' . sprintf('%02d', $jumlahhari) . ' 23:59:59'
);


// ======================================================
// FUNGSI STATUS
// ======================================================

function absensi_bulanan_status_huruf($status) {
    $status = strtolower(trim((string)$status));

    switch ($status) {
        case 'sakit':
            return 'S';

        case 'ijin':
        case 'izin':
            return 'I';

        case 'alpa':
            return 'A';

        case 'dispensasi':
            return 'D';

        case 'hadir':
        default:
            return 'H';
    }
}


// ======================================================
// FUNGSI NAMA BULAN
// ======================================================

function absensi_bulanan_nama_bulan($bulan) {
    $nama = [
        1  => 'Januari',
        2  => 'Februari',
        3  => 'Maret',
        4  => 'April',
        5  => 'Mei',
        6  => 'Juni',
        7  => 'Juli',
        8  => 'Agustus',
        9  => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];

    return $nama[(int)$bulan] ?? '';
}


// ======================================================
// FUNGSI NAMA HARI
// ======================================================

function absensi_bulanan_nama_hari($timestamp) {
    $hari = [
        'Minggu',
        'Senin',
        'Selasa',
        'Rabu',
        'Kamis',
        'Jumat',
        'Sabtu'
    ];

    return $hari[(int)date('w', $timestamp)] ?? '';
}


// ======================================================
// OUTPUT HEADER
// ======================================================

echo $OUTPUT->header();


// ======================================================
// CSS
// ======================================================

echo '
<style>
@media print {
    /* Header layar dan info filter tidak ikut tercetak. */
    .absensi-header,
    .info-kelas,
    .absensi-filter,
    .no-print {
        display: none !important;
    }

    .judul-cetak {
        display: block !important;
        text-align: center;
        margin: 0 0 12px 0;
        line-height: 1.25;
    }

    .judul-cetak .judul-utama {
        margin: 0;
        font-size: 17px;
        font-weight: bold;
    }

    .judul-cetak .nama-sekolah {
        margin: 2px 0 0 0;
        font-size: 15px;
        font-weight: bold;
    }

    .judul-cetak .tahun-pelajaran {
        margin: 1px 0 7px 0;
        font-size: 13px;
    }

    .judul-cetak .info-cetak {
        margin: 1px 0;
        font-size: 12px;
        text-align: left;
        margin-left: 8px;
    }
}

@media screen {
    .judul-cetak {
        display: none;
    }
}
</style>
<style>

.absensi-wrapper {
    width: 100%;
}

.absensi-filter {
    margin-bottom: 20px;
}

.absensi-scroll {
    width: 100%;
    overflow-x: auto;
    background: #fff;
    border: 1px solid #ddd;
}

.absensi-table {
    border-collapse: collapse;
    width: max-content;
    min-width: 100%;
    font-size: 12px;
}

.absensi-table th,
.absensi-table td {
    border: 1px solid #000 !important;
    padding: 5px 6px;
    text-align: center;
    vertical-align: middle;
    white-space: nowrap;
}

.absensi-table thead th {
    background: #e9ecef;
    font-weight: bold;
}

.absensi-table tfoot th,
.absensi-table tfoot td {
    background: #f1f1f1;
    font-weight: bold;
}

.absensi-table .judul-kolom {
    background: #d9eaf7;
}

.absensi-table .nama-siswa {
    min-width: 220px;
    text-align: left;
}

.absensi-table .nis {
    min-width: 90px;
}

.absensi-table .lp {
    min-width: 45px;
}

.absensi-table .tanggal {
    width: 30px;
    min-width: 30px;
    max-width: 30px;
    padding-left: 3px;
    padding-right: 3px;
}

.absensi-table .hari {
    font-size: 9px;
    font-weight: normal;
}

.absensi-table .rekap {
    min-width: 38px;
}

.status-hadir {
    font-weight: bold;
}

.status-sakit {
    font-weight: bold;
}

.status-ijin {
    font-weight: bold;
}

.status-alpa {
    font-weight: bold;
}

.status-dispensasi {
    font-weight: bold;
}

.absensi-header {
    text-align: center;
    margin-bottom: 15px;
}

.absensi-header h3,
.absensi-header h4,
.absensi-header h5 {
    margin: 2px 0;
}

.info-kelas {
    margin-top: 12px;
    margin-bottom: 10px;
}

.info-kelas table {
    border-collapse: collapse;
    width: 100%;
}

.info-kelas td {
    padding: 2px 5px;
    vertical-align: top;
}

.keterangan-status {
    margin-top: 15px;
    font-size: 12px;
}

.ttd-wrapper {
    margin-top: 25px;
    width: 100%;
}

.tanggal-ttd {
    text-align: center !important;
    vertical-align: bottom !important;
    padding: 0 0 5px 0 !important;
    font-size: 12px;
}

.tanda-tangan {
    margin-top: 0;
    width: 100%;
    border-collapse: collapse;
}

.tanda-tangan td {
    text-align: center;
    vertical-align: top;
    width: 50%;
    border: none !important;
}

.spasi-ttd {
    height: 70px;
}


/* =====================================================
   PRINT
   ===================================================== */

@media print {

    @page {
        size: A4 landscape;
        margin: 8mm;
    }

    html,
    body {
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }

    #page-header,
    #page-footer,
    .navbar,
    .drawer,
    .secondary-navigation,
    .breadcrumb,
    .absensi-filter,
    .btn,
    .no-print,
    .moodle-actionmenu,
    footer {
        display: none !important;
    }

    #page,
    #page-content,
    .container-fluid,
    .container,
    .absensi-wrapper {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .absensi-scroll {
        overflow: visible !important;
        border: none !important;
    }

    .absensi-table {
        width: 100% !important;
        min-width: 0 !important;
        font-size: 8px !important;
    }

    .absensi-table th,
    .absensi-table td {
        border: 1px solid #000 !important;
        padding: 2px 3px !important;
    }

    .absensi-table .nama-siswa {
        min-width: 0 !important;
        width: auto !important;
    }

    .absensi-table .nis {
        min-width: 0 !important;
    }

    .absensi-table .tanggal {
        width: auto !important;
        min-width: 0 !important;
        max-width: none !important;
        padding: 1px 2px !important;
    }

    .absensi-table .hari {
        font-size: 6px !important;
    }

    .absensi-header {
        margin-bottom: 5px !important;
    }

    .absensi-header h3 {
        font-size: 14px !important;
    }

    .absensi-header h4 {
        font-size: 12px !important;
    }

    .absensi-header h5 {
        font-size: 10px !important;
    }

    .info-kelas {
        font-size: 9px !important;
        margin-top: 5px !important;
    }

    .keterangan-status {
        font-size: 8px !important;
    }

    .ttd-wrapper {
        margin-top: 12px !important;
    }

    .tanggal-ttd {
        font-size: 9px !important;
        text-align: center !important;
        padding-bottom: 3px !important;
    }

    .tanda-tangan {
        font-size: 9px !important;
        margin-top: 0 !important;
    }

    .spasi-ttd {
        height: 35px !important;
    }

    tr {
        page-break-inside: avoid;
    }
}

</style>
';


// ======================================================
// WRAPPER
// ======================================================

echo html_writer::start_div(
    'container-fluid absensi-wrapper'
);


// ======================================================
// TOMBOL ATAS
// ======================================================

echo html_writer::start_div(
    'mb-3 no-print'
);

echo html_writer::link(
    '#',
    '⬅ Kembali',
    [
        'class' => 'btn btn-secondary me-2',
        'onclick' => 'history.back(); return false;'
    ]
);

echo html_writer::link(
    new moodle_url(
        '/local/jurnalmengajar/rekap_kehadiran.php'
    ),
    '📊 Rekap Kehadiran',
    [
        'class' => 'btn btn-info me-2'
    ]
);

echo html_writer::tag(
    'button',
    '🖨️ Cetak',
    [
        'type' => 'button',
        'class' => 'btn btn-success',
        'onclick' => "cetakAbsensi();"
    ]
);

echo html_writer::end_div();


// ======================================================
// FORM FILTER
// ======================================================

echo html_writer::start_tag(
    'form',
    [
        'method' => 'get',
        'class' => 'card card-body bg-light absensi-filter no-print'
    ]
);

echo html_writer::start_div(
    'row g-3 align-items-end'
);


// Kelas.
echo html_writer::start_div('col-md-4');

echo html_writer::tag(
    'label',
    'Pilih Kelas',
    [
        'for' => 'kelas',
        'class' => 'form-label fw-bold'
    ]
);

echo html_writer::select(
    $kelaslist,
    'kelas',
    $kelasid ?: '',
    ['' => '-- Pilih Kelas --'],
    [
        'class' => 'form-select',
        'id' => 'kelas'
    ]
);

echo html_writer::end_div();


// Bulan.
echo html_writer::start_div('col-md-3');

echo html_writer::tag(
    'label',
    'Bulan',
    [
        'for' => 'bulan',
        'class' => 'form-label fw-bold'
    ]
);

echo html_writer::empty_tag(
    'input',
    [
        'type' => 'month',
        'name' => 'bulan',
        'id' => 'bulan',
        'value' => s($bulan),
        'class' => 'form-control',
        'required' => 'required'
    ]
);

echo html_writer::end_div();


// Tombol.
echo html_writer::start_div('col-md-3');

echo html_writer::empty_tag(
    'input',
    [
        'type' => 'submit',
        'value' => '🔍 Tampilkan',
        'class' => 'btn btn-primary w-100'
    ]
);

echo html_writer::end_div();

echo html_writer::end_div();


// Hanya jurnal saya.
echo html_writer::start_div(
    'form-check mt-3'
);

echo html_writer::empty_tag(
    'input',
    [
        'type' => 'checkbox',
        'name' => 'onlymine',
        'id' => 'onlymine',
        'value' => 1,
        'class' => 'form-check-input',
        'checked' => $onlymine ? 'checked' : null
    ]
);

echo html_writer::tag(
    'label',
    'Hanya Jurnal Saya',
    [
        'for' => 'onlymine',
        'class' => 'form-check-label fw-bold'
    ]
);

echo html_writer::end_div();

echo html_writer::end_tag('form');


// ======================================================
// VALIDASI KELAS
// ======================================================

if (!$kelasid) {

    echo html_writer::div(
        'Silakan pilih kelas dan bulan terlebih dahulu.',
        'alert alert-info text-center'
    );

    echo html_writer::end_div();

    echo $OUTPUT->footer();

    exit;
}

if (!isset($kelaslist[$kelasid])) {

    echo html_writer::div(
        'Kelas tidak ditemukan.',
        'alert alert-danger text-center'
    );

    echo html_writer::end_div();

    echo $OUTPUT->footer();

    exit;
}

$namakelas = $kelaslist[$kelasid];


// ======================================================
// AMBIL WALI KELAS DARI MAPPING
// ======================================================

$namaguruwali = '-';

if (isset($mapping[$kelasid])) {

    $walikelasid = (int)$mapping[$kelasid];

    $guru = $DB->get_record(
        'user',
        ['id' => $walikelasid],
        'id, firstname, lastname'
    );

    if ($guru) {

        // Gunakan nama wali kelas persis seperti yang tersimpan
        // di database, tanpa ucwords(), strtolower(), atau fullname().
        if (!empty($guru->lastname)) {
            $namaguruwali = $guru->lastname;
        } else {
            $namaguruwali = $guru->firstname;
        }
    }
}


// ======================================================
// DATA TANDA TANGAN
// ======================================================

// NIP wali kelas dari User Profile Field "nip"
// pada akun guru yang ditunjuk oleh wali_kelas_mapping.
$nipwalikelas = '';

if (isset($mapping[$kelasid])) {

    $walikelasid = (int)$mapping[$kelasid];

    $fieldnip = $DB->get_record(
        'user_info_field',
        ['shortname' => 'nip'],
        'id'
    );

    if ($fieldnip) {

        $datanipwali = $DB->get_record(
            'user_info_data',
            [
                'userid' => $walikelasid,
                'fieldid' => $fieldnip->id
            ],
            'data'
        );

        if ($datanipwali) {
            $nipwalikelas = trim((string)$datanipwali->data);
        }
    }
}

// Kepala sekolah dari setting plugin.
$tempat_ttd = trim((string)get_config(
    'local_jurnalmengajar',
    'tempat_ttd'
));

$nama_kepsek = trim((string)get_config(
    'local_jurnalmengajar',
    'nama_kepsek'
));

$nip_kepsek = trim((string)get_config(
    'local_jurnalmengajar',
    'nip_kepsek'
));

// Tanggal tanda tangan = tanggal terakhir bulan yang dipilih.
$tanggal_ttd = strtotime(
    $bulan . '-' . sprintf('%02d', $jumlahhari) . ' 12:00:00'
);

$tanggal_ttd_text =
    ($tempat_ttd !== '' ? $tempat_ttd : 'Hulu Sungai Selatan') .
    ', ' .
    (int)date('j', $tanggal_ttd) . ' ' .
    absensi_bulanan_nama_bulan((int)date('m', $tanggal_ttd)) . ' ' .
    (int)date('Y', $tanggal_ttd);


// ======================================================
// AMBIL DAFTAR SISWA
// NIS dan L/P dari user profile field
// ======================================================

$sql = "
SELECT
    u.id,
    u.firstname,
    u.lastname,
    nis.data AS nis,
    jk.data AS jeniskelamin

FROM {cohort_members} cm

JOIN {user} u
     ON u.id = cm.userid

LEFT JOIN {user_info_data} nis
     ON nis.userid = u.id
    AND nis.fieldid = (
        SELECT id
        FROM {user_info_field}
        WHERE shortname = 'nis'
    )

LEFT JOIN {user_info_data} jk
     ON jk.userid = u.id
    AND jk.fieldid = (
        SELECT id
        FROM {user_info_field}
        WHERE shortname = 'gender'
    )

WHERE cm.cohortid = :cohortid

ORDER BY
    u.lastname ASC,
    u.firstname ASC
";

$users = $DB->get_records_sql(
    $sql,
    [
        'cohortid' => $kelasid
    ]
);


// ======================================================
// FILTER PESERTA MAPEL
// ======================================================

if (empty($users)) {

    echo html_writer::div(
        'Tidak ada murid dalam kelas ini.',
        'alert alert-warning text-center'
    );

    echo html_writer::end_div();

    echo $OUTPUT->footer();

    exit;
}


// ======================================================
// AMBIL JURNAL PADA BULAN TERPILIH
// ======================================================

$params = [
    'kelas' => $kelasid,
    'dari' => $tanggalawal,
    'sampai' => $tanggalakhir
];

$wheres = [
    'kelas = :kelas',
    'timecreated BETWEEN :dari AND :sampai'
];

if ($onlymine) {

    $wheres[] = 'userid = :userid';

    $params['userid'] = $USER->id;
}
$sqlwhere = implode(
    ' AND ',
    $wheres
);

$jurnals = $DB->get_records_select(
    'local_jurnalmengajar',
    $sqlwhere,
    $params,
    'timecreated ASC'
);


// ======================================================
// STRUKTUR ABSENSI
// ======================================================

$absensi = [];

foreach ($users as $uid => $user) {

    $absensi[$uid] = [];

    for ($hari = 1; $hari <= $jumlahhari; $hari++) {
        $absensi[$uid][$hari] = null;
    }
}


// ======================================================
// PROSES JURNAL PER HARI
// ======================================================

$perhari = [];

$priority = [
    'dispensasi' => 1,
    'sakit'      => 2,
    'ijin'       => 3,
    'alpa'       => 4
];


foreach ($jurnals as $jurnal) {

    $hari = (int)date(
        'j',
        $jurnal->timecreated
    );

    if (
        $hari < 1 ||
        $hari > $jumlahhari
    ) {
        continue;
    }


    // Jumlah jam pada jurnal.
    $jamke = array_filter(
        array_map(
            'trim',
            explode(
                ',',
                (string)($jurnal->jamke ?? '')
            )
        )
    );

    $jmljam = count($jamke);

    if (!$jmljam) {
        $jmljam = 1;
    }


    // Absensi JSON.
    $absen = json_decode(
        $jurnal->absen,
        true
    );

    if (!is_array($absen)) {
        $absen = [];
    }


    // Lookup nama => status.
    $lookup = [];

    foreach ($absen as $nama => $alasan) {

        $key = mb_strtolower(
            trim($nama),
            'UTF-8'
        );

        $status = strtolower(
            trim($alasan)
        );

        if ($status === 'izin') {
            $status = 'ijin';
        }

        $lookup[$key] = $status;
    }


    foreach ($users as $uid => $user) {
        if (!isset($perhari[$uid][$hari])) {

            $perhari[$uid][$hari] = [
                'hadir'      => 0,
                'sakit'      => 0,
                'ijin'       => 0,
                'alpa'       => 0,
                'dispensasi' => 0
            ];
        }


        $namasiswa = mb_strtolower(
            trim($user->lastname),
            'UTF-8'
        );


        $status = $lookup[$namasiswa]
            ?? 'hadir';


        if (
            !isset(
                $perhari[$uid][$hari][$status]
            )
        ) {
            $status = 'hadir';
        }


        $perhari[$uid][$hari][$status]
            += $jmljam;
    }
}


// ======================================================
// TENTUKAN STATUS HARIAN
// ======================================================
//
// Semua jam hadir             = H
// Semua jam tidak hadir       = status nonhadir prioritas
// Ada hadir + tidak hadir     = H
//
// Prioritas nonhadir:
// D < S < I < A
// ======================================================

foreach ($users as $uid => $user) {

    for (
        $hari = 1;
        $hari <= $jumlahhari;
        $hari++
    ) {

        if (
            empty(
                $perhari[$uid][$hari]
            )
        ) {
            continue;
        }


        $datahari =
            $perhari[$uid][$hari];

        $hadir =
            $datahari['hadir'];

        $total =
            array_sum($datahari);


        if ($total <= 0) {
            continue;
        }


        $nonhadir =
            $total - $hadir;


        if ($nonhadir == 0) {

            $statushari = 'hadir';

        } else if ($hadir == 0) {

            $statushari = 'hadir';

            $maxpriority = -1;

            foreach (
                [
                    'dispensasi',
                    'sakit',
                    'ijin',
                    'alpa'
                ] as $status
            ) {

                if (
                    !empty(
                        $datahari[$status]
                    )
                ) {

                    $p =
                        $priority[$status]
                        ?? 0;

                    if (
                        $p > $maxpriority
                    ) {

                        $maxpriority = $p;

                        $statushari =
                            $status;
                    }
                }
            }

        } else {

            $statushari = 'hadir';
        }


        $absensi[$uid][$hari] =
            absensi_bulanan_status_huruf(
                $statushari
            );
    }
}


// ======================================================
// JUDUL
// ======================================================

echo html_writer::start_div(
    'absensi-header'
);

echo html_writer::tag(
    'h3',
    'DAFTAR HADIR SISWA'
);

echo html_writer::tag(
    'h4',
    'SMAN 2 KANDANGAN'
);

echo html_writer::tag(
    'h5',
    'TAHUN PELAJARAN ' .
    (
        $bulanangka >= 7
        ? $tahun . '/' . ($tahun + 1)
        : ($tahun - 1) . '/' . $tahun
    )
);

echo html_writer::end_div();


// ======================================================
// INFORMASI KELAS
// ======================================================

$namabulan =
    absensi_bulanan_nama_bulan(
        $bulanangka
    );

echo html_writer::start_div(
    'info-kelas'
);

echo '
<table>
<tr>
    <td style="width:100px;"><strong>Kelas</strong></td>
    <td>: ' . s($namakelas) . '</td>
    <td style="width:100px;"><strong>Bulan</strong></td>
    <td>: ' . s($namabulan . ' ' . $tahun) . '</td>
</tr>
<tr>
    <td><strong>Wali Kelas</strong></td>
    <td colspan="3">: ' . s($namaguruwali) . '</td>
</tr>
</table>
';

echo html_writer::end_div();


// ======================================================
// TABEL
// ======================================================

echo html_writer::start_div('judul-cetak');

echo html_writer::tag(
    'div',
    'DAFTAR HADIR SISWA',
    ['class' => 'judul-utama']
);

echo html_writer::tag(
    'div',
    'SMAN 2 KANDANGAN',
    ['class' => 'nama-sekolah']
);

echo html_writer::tag(
    'div',
    'TAHUN PELAJARAN ' .
    (
        $bulanangka >= 7
        ? $tahun . '/' . ($tahun + 1)
        : ($tahun - 1) . '/' . $tahun
    ),
    ['class' => 'tahun-pelajaran']
);

echo html_writer::tag(
    'div',
    '<strong>Kelas</strong> : ' . s($namakelas),
    ['class' => 'info-cetak']
);

echo html_writer::tag(
    'div',
    '<strong>Bulan</strong> : ' . s($namabulan . ' ' . $tahun),
    ['class' => 'info-cetak']
);

echo html_writer::tag(
    'div',
    '<strong>Wali Kelas</strong> : ' . s($namaguruwali),
    ['class' => 'info-cetak']
);

echo html_writer::end_div();

echo html_writer::start_div(
    'absensi-scroll'
);

echo html_writer::start_tag(
    'table',
    [
        'class' => 'absensi-table'
    ]
);


// ======================================================
// HEADER TABEL
// ======================================================

echo html_writer::start_tag('thead');


// Baris pertama.
echo html_writer::start_tag('tr');

echo html_writer::tag(
    'th',
    'NO',
    [
        'rowspan' => 2,
        'class' => 'judul-kolom'
    ]
);

echo html_writer::tag(
    'th',
    'NIS',
    [
        'rowspan' => 2,
        'class' => 'judul-kolom'
    ]
);

echo html_writer::tag(
    'th',
    'NAMA SISWA',
    [
        'rowspan' => 2,
        'class' => 'judul-kolom'
    ]
);

echo html_writer::tag(
    'th',
    'L/P',
    [
        'rowspan' => 2,
        'class' => 'judul-kolom'
    ]
);


// Tanggal 1 sampai akhir bulan.
for (
    $hari = 1;
    $hari <= $jumlahhari;
    $hari++
) {

    echo html_writer::tag(
        'th',
        $hari,
        [
            'class' =>
                'tanggal judul-kolom'
        ]
    );
}


// Rekap.
echo html_writer::end_tag('tr');


// Baris kedua: nama hari.
echo html_writer::start_tag('tr');

for (
    $hari = 1;
    $hari <= $jumlahhari;
    $hari++
) {

    $timestamp =
        strtotime(
            $bulan . '-' .
            sprintf('%02d', $hari) .
            ' 12:00:00'
        );

    $nama_hari =
        absensi_bulanan_nama_hari(
            $timestamp
        );

    $singkat = substr(
        $nama_hari,
        0,
        3
    );

    echo html_writer::tag(
        'th',
        $singkat,
        [
            'class' =>
                'tanggal hari'
        ]
    );
}

echo html_writer::end_tag('tr');

echo html_writer::end_tag('thead');


// ======================================================
// BODY
// ======================================================

echo html_writer::start_tag('tbody');

$no = 1;

foreach ($users as $uid => $user) {

    echo html_writer::start_tag('tr');


    // No.
    echo html_writer::tag(
        'td',
        $no++
    );


    // NIS.
    $nis = trim(
        (string)($user->nis ?? '')
    );

    if ($nis === '') {
        $nis = '-';
    }

    echo html_writer::tag(
        'td',
        s($nis),
        [
            'class' => 'nis'
        ]
    );


    // Nama.
    $namasiswa =
        ucwords(
            strtolower(
                trim(
                    $user->lastname
                )
            )
        );

    if ($namasiswa === '') {
        $namasiswa =
            fullname($user);
    }

    echo html_writer::tag(
        'td',
        s($namasiswa),
        [
            'class' =>
                'nama-siswa'
        ]
    );


    // L/P.
    $lp = strtoupper(
        trim(
            (string)(
                $user->jeniskelamin ?? ''
            )
        )
    );

    if ($lp === '') {
        $lp = '-';
    }

    echo html_writer::tag(
        'td',
        s($lp),
        [
            'class' => 'lp'
        ]
    );


    // Tanggal.
    for (
        $hari = 1;
        $hari <= $jumlahhari;
        $hari++
    ) {

        $status =
            $absensi[$uid][$hari]
            ?? null;


        if ($status === null) {

            $tampil = '';

        } else {

            $tampil = $status;
        }


        $class = 'tanggal';

        switch ($status) {

            case 'H':
                $class .= ' status-hadir';
                break;

            case 'S':
                $class .= ' status-sakit';
                break;

            case 'I':
                $class .= ' status-ijin';
                break;

            case 'A':
                $class .= ' status-alpa';
                break;

            case 'D':
                $class .= ' status-dispensasi';
                break;
        }


        echo html_writer::tag(
            'td',
            $tampil,
            [
                'class' => $class
            ]
        );
    }


    echo html_writer::end_tag('tr');

}


echo html_writer::end_tag('tbody');

echo html_writer::end_tag('table');
echo html_writer::end_div();


// ======================================================
// KETERANGAN
// ======================================================

echo html_writer::start_div(
    'keterangan-status'
);

echo '<strong>Keterangan:</strong> ';
echo 'H = Hadir &nbsp;&nbsp; ';
echo 'S = Sakit &nbsp;&nbsp; ';
echo 'I = Ijin &nbsp;&nbsp; ';
echo 'A = Alpa &nbsp;&nbsp; ';
echo 'D = Dispensasi';

echo html_writer::end_div();


// ======================================================
// TANDA TANGAN
// ======================================================

echo html_writer::start_div('ttd-wrapper');

echo html_writer::start_tag(
    'table',
    ['class' => 'tanda-tangan']
);

// Baris tempat dan tanggal: berada di kolom kanan.
echo html_writer::start_tag('tr');

echo html_writer::tag(
    'td',
    ''
);

echo html_writer::tag(
    'td',
    s($tanggal_ttd_text),
    ['class' => 'tanggal-ttd']
);

echo html_writer::end_tag('tr');

// Baris jabatan.
echo html_writer::start_tag('tr');

echo html_writer::tag(
    'td',
    'Mengetahui,<br>Kepala Sekolah'
);

echo html_writer::tag(
    'td',
    'Wali Kelas'
);

echo html_writer::end_tag('tr');

echo html_writer::start_tag('tr');

echo html_writer::tag(
    'td',
    '<div class="spasi-ttd"></div>' .
    '<strong>' . s($nama_kepsek) . '</strong><br>' .
    'NIP. ' . s($nip_kepsek)
);

echo html_writer::tag(
    'td',
    '<div class="spasi-ttd"></div>' .
    '<strong>' . s($namaguruwali) . '</strong><br>' .
    'NIP. ' . s($nipwalikelas)
);

echo html_writer::end_tag('tr');

echo html_writer::end_tag('table');

echo html_writer::end_div();


// ======================================================
// END
// ======================================================

echo html_writer::end_div();
echo '
<script>
function cetakAbsensi() {

    var kelas = ' . json_encode($namakelas) . ';
    var bulan = ' . json_encode($namabulan) . ';
    var tahun = ' . json_encode($tahun) . ';

    // Ubah nama kelas agar X C menjadi X-C.
    kelas = kelas.replace(/\\s+/g, "-");

    // Nama file PDF.
    document.title =
        "Daftar Hadir Siswa _Kelas_" +
        kelas +
        "_bulan_" +
        bulan +
        "_" +
        tahun +
        "_ SiM SMA2";

    window.print();
}
</script>
';

echo $OUTPUT->footer();
