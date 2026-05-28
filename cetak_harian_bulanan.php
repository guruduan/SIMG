<?php
require_once('../../config.php');
require_once(__DIR__.'/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

global $DB, $OUTPUT;

// ================= PARAMETER =================
$bulan = optional_param('bulan', date('n'), PARAM_INT);
$tahun = optional_param('tahun', date('Y'), PARAM_INT);
$hari  = optional_param('hari', 5, PARAM_INT);

$kelasfilter      = optional_param('kelas', 0, PARAM_INT);
$siswafilter      = optional_param('siswaid', 0, PARAM_INT);
$keperluanfilter  = optional_param('keperluan', '', PARAM_TEXT);

// ================= PAGE =================
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/cetak_harian_bulanan.php'));
$PAGE->set_context($context);
$PAGE->set_title('Cetak Surat Izin');
$PAGE->set_heading('Cetak Surat Izin');

// ================= FUNGSI =================
function get_tanggal_hari_dalam_bulan($bulan, $tahun, $hari) {

    $hasil = [];

    $jumlahhari = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);

    for ($tgl = 1; $tgl <= $jumlahhari; $tgl++) {

        $timestamp = strtotime("$tahun-$bulan-$tgl");

        if (date('w', $timestamp) == $hari) {
            $hasil[] = date('Y-m-d', $timestamp);
        }
    }

    return $hasil;
}

// ================= DAFTAR TANGGAL =================
$tanggallist = get_tanggal_hari_dalam_bulan($bulan, $tahun, $hari);

// ================= SQL =================
$params = [];

$sql = "SELECT
            si.*,
            u.lastname AS siswa,
            gp.lastname AS gurupengajar,
            pi.lastname AS penginput
        FROM {local_jurnalmengajar_suratizin} si
        JOIN {user} u ON u.id = si.userid
        LEFT JOIN {user} gp ON gp.id = si.guru_pengajar
        LEFT JOIN {user} pi ON pi.id = si.penginput
        WHERE 1=1";

// FILTER KELAS
if ($kelasfilter) {
    $sql .= " AND si.kelasid = :kelas";
    $params['kelas'] = $kelasfilter;
}

// FILTER SISWA
if ($siswafilter) {
    $sql .= " AND si.userid = :siswa";
    $params['siswa'] = $siswafilter;
}

// FILTER KEPERLUAN
if ($keperluanfilter) {
    $sql .= " AND si.keperluan = :keperluan";
    $params['keperluan'] = $keperluanfilter;
}

// ================= FILTER HARI =================
$orconditions = [];
$i = 0;

foreach ($tanggallist as $tgl) {

    $start = strtotime($tgl . ' 00:00:00');
    $end   = strtotime($tgl . ' 23:59:59');

    $orconditions[] =
        "(si.timecreated BETWEEN :start$i AND :end$i)";

    $params["start$i"] = $start;
    $params["end$i"]   = $end;

    $i++;
}

if ($orconditions) {
    $sql .= " AND (" . implode(' OR ', $orconditions) . ")";
}

$sql .= " ORDER BY si.timecreated ASC";

// ================= AMBIL DATA =================
$results = $DB->get_records_sql($sql, $params);

// ================= NAMA HARI =================
$namahari = [
    0 => 'Minggu',
    1 => 'Senin',
    2 => 'Selasa',
    3 => 'Rabu',
    4 => 'Kamis',
    5 => 'Jumat',
    6 => 'Sabtu'
];

$namabulan = [
    1=>'Januari',
    2=>'Februari',
    3=>'Maret',
    4=>'April',
    5=>'Mei',
    6=>'Juni',
    7=>'Juli',
    8=>'Agustus',
    9=>'September',
    10=>'Oktober',
    11=>'November',
    12=>'Desember'
];

// ================= TAMPILKAN =================
echo $OUTPUT->header();

echo "<style>
table {
    width:100%;
    border-collapse:collapse;
    font-size:12px;
}

table th,
table td {
    border:1px solid #000;
    padding:5px;
}

@media print {
    .noprint {
        display:none;
    }
}
</style>";

echo "<div class='noprint' style='margin-bottom:15px'>";
echo "<button onclick='window.print()'>🖨 Cetak</button>";
echo "</div>";

echo "<h3 style='text-align:center'>
REKAP SURAT IZIN
<br>
" . $namahari[$hari] . "
Bulan " . $namabulan[$bulan] . " $tahun
</h3>";

echo "<table>";

echo "<tr>
<th>No</th>
<th>Hari Tanggal</th>
<th>Nama Murid</th>
<th>Kelas</th>
<th>Guru Pengajar</th>
<th>Alasan</th>
<th>Keperluan</th>
<th>Guru Piket</th>
</tr>";

$no = 1;

if ($results) {

    foreach ($results as $row) {

        $tanggal = tanggal_indo($row->timecreated);
        $kelas   = get_nama_kelas($row->kelasid);

        switch (strtolower($row->keperluan)) {

            case 'izin masuk':
                $warna = '#b7d3b6';
                $label = 'Izin Masuk';
                break;

            case 'izin keluar':
                $warna = '#f3d6a4';
                $label = 'Izin Keluar';
                break;

            case 'izin pulang':
                $warna = '#e6b0bd';
                $label = 'Izin Pulang';
                break;

            default:
                $warna = '#ffffff';
                $label = $row->keperluan;
        }

        echo "<tr style='background:$warna'>";

        echo "<td>$no</td>";
        echo "<td>$tanggal</td>";
        echo "<td>" . format_nama_siswa($row->siswa) . "</td>";
        echo "<td>$kelas</td>";
        echo "<td>" . format_nama_siswa($row->gurupengajar) . "</td>";
        echo "<td>{$row->alasan}</td>";
        echo "<td><b>$label</b></td>";
        //echo "<td>" . format_nama_siswa($row->penginput) . "</td>";
        echo "<td>{$row->penginput}</td>";

        echo "</tr>";

        $no++;
    }

} else {

    echo "<tr>";
    echo "<td colspan='8' style='text-align:center'>";
    echo "Tidak ada data.";
    echo "</td>";
    echo "</tr>";
}

echo "</table>";

echo $OUTPUT->footer();
