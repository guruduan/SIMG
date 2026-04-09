<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_login();

global $DB, $PAGE, $OUTPUT;

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/rekap_jurnal_ekstra.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Rekap Jurnal Ekstrakurikuler');
$PAGE->set_heading('Rekap Jurnal Ekstrakurikuler');

echo $OUTPUT->header();

echo '<h3>Rekap Kehadiran Ekstrakurikuler</h3>';

// Tombol dashboard
echo '<div style="margin-bottom:10px;">
<a class="btn btn-secondary" href="/my">⬅️ Dashboard</a>
</div>';

// ================= FILTER =================
$ekstraid = optional_param('ekstraid', 0, PARAM_INT);
$dari     = optional_param('dari', '', PARAM_TEXT);
$sampai   = optional_param('sampai', '', PARAM_TEXT);

// Ambil daftar ekstra
$ekstra = $DB->get_records('local_jm_ekstra', null, 'namaekstra ASC');

// Form filter
echo '<form method="get">';
echo 'Ekstrakurikuler: <select name="ekstraid">';
echo '<option value="0">Semua</option>';
foreach ($ekstra as $e) {
    $sel = ($ekstraid == $e->id) ? 'selected' : '';
    echo '<option value="'.$e->id.'" '.$sel.'>'.$e->namaekstra.'</option>';
}
echo '</select> ';

echo 'Dari: <input type="date" name="dari" value="'.$dari.'"> ';
echo 'Sampai: <input type="date" name="sampai" value="'.$sampai.'"> ';
echo '<button type="submit" class="btn btn-primary">Filter</button>';
echo '</form>';

echo '<br>';

// ================= QUERY =================
$where = " WHERE 1=1 ";
$params = [];

if ($ekstraid) {
    $where .= " AND j.ekstraid = :ekstraid";
    $params['ekstraid'] = $ekstraid;
}

if ($dari) {
    $where .= " AND j.tanggal >= :dari";
    $params['dari'] = strtotime($dari);
}

if ($sampai) {
    $where .= " AND j.tanggal <= :sampai";
    $params['sampai'] = strtotime($sampai . ' 23:59:59');
}

// Ambil semua siswa peserta ekstra
$sql = "SELECT 
            u.id, 
            u.firstname, 
            u.lastname,
            e.namaekstra,
            GROUP_CONCAT(DISTINCT g.lastname SEPARATOR ', ') AS nama_guru
        FROM {local_jm_ekstra_absen} a
        JOIN {user} u ON u.id = a.userid
        JOIN {local_jm_ekstra_jurnal} j ON j.id = a.jurnalid
        JOIN {local_jm_ekstra} e ON e.id = j.ekstraid
        JOIN {user} g ON g.id = j.pembinaid
        $where
        GROUP BY u.id, u.firstname, u.lastname, e.namaekstra
        ORDER BY u.lastname ASC";

$siswa = $DB->get_records_sql($sql, $params);

// ================= TABEL =================
if ($siswa) {

    echo html_writer::start_tag('table', [
    'class' => 'generaltable',
    'style' => 'width:100%;'
]);

    // ===== HEADER =====
    echo html_writer::start_tag('thead');
echo html_writer::tag('tr', implode('', [
    html_writer::tag('th', 'No'),
    html_writer::tag('th', 'Nama Murid', ['style'=>'min-width:200px']),
    html_writer::tag('th', 'Ekstra', ['style'=>'min-width:200px']),
    html_writer::tag('th', 'Hadir'),
    html_writer::tag('th', 'Sakit'),
    html_writer::tag('th', 'Ijin'),
    html_writer::tag('th', 'Alpa'),
    html_writer::tag('th', 'Dispensasi'),
    html_writer::tag('th', 'Persentase'),
    html_writer::tag('th', 'Guru', ['style'=>'min-width:200px']),
]));
    echo html_writer::end_tag('thead');


    // ===== BODY =====
    echo html_writer::start_tag('tbody');

    $no = 1;

    foreach ($siswa as $s) {

        $sql2 = "SELECT a.status, COUNT(a.id) as jumlah
                 FROM {local_jm_ekstra_absen} a
                 JOIN {local_jm_ekstra_jurnal} j ON j.id = a.jurnalid
                 $where
                 AND a.userid = :userid
                 GROUP BY a.status";

        $params2 = $params;
        $params2['userid'] = $s->id;

        $rekap = $DB->get_records_sql($sql2, $params2);

        $hadir = $rekap['Hadir']->jumlah ?? 0;
        $sakit = $rekap['Sakit']->jumlah ?? 0;
        $izin  = $rekap['Izin']->jumlah ?? 0;
        $alpa  = $rekap['Alpa']->jumlah ?? 0;
        $disp  = $rekap['Dispensasi']->jumlah ?? 0;

        $total = $hadir + $sakit + $izin + $alpa + $disp;
        $persen = $total ? round(($hadir / $total) * 100) : 0;

        echo html_writer::tag('tr', implode('', [
            html_writer::tag('td', $no++),
            html_writer::tag('td', $s->firstname.' '.$s->lastname),
            html_writer::tag('td', $s->namaekstra),
            html_writer::tag('td', $hadir),
            html_writer::tag('td', $sakit),
            html_writer::tag('td', $izin),
            html_writer::tag('td', $alpa),
            html_writer::tag('td', $disp),
            html_writer::tag('td', $persen.' %'),
            html_writer::tag('td', $s->nama_guru),
        ]));
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');

} else {
    echo 'Tidak ada data.';
}

echo $OUTPUT->footer();
