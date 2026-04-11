<?php
require_once('../../config.php');
require_once($CFG->libdir.'/pdflib.php');
require_once($CFG->dirroot.'/local/jurnalmengajar/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/jurnalmengajar:viewallsuratizin', $context);

$bulan = required_param('bulan', PARAM_INT);
$tahun = date('Y');

if ($bulan < 1 || $bulan > 12) {
    throw new moodle_exception('Bulan tidak valid.');
}

// Range waktu
$starttime = strtotime("{$tahun}-{$bulan}-01 00:00:00");
$endtime   = strtotime('+1 month', $starttime);

// Query
$sql = "SELECT s.*, u.firstname, u.lastname, d.data as nip
        FROM {local_jurnalmengajar_suratizinguru} s
        JOIN {user} u ON u.id = s.userid
        LEFT JOIN {user_info_data} d ON d.userid = u.id
        WHERE d.fieldid = (SELECT id FROM {user_info_field} WHERE shortname = 'nip')
          AND s.waktuinput >= :start AND s.waktuinput < :end
        ORDER BY s.waktuinput DESC";

$params = ['start' => $starttime, 'end' => $endtime];
$records = $DB->get_records_sql($sql, $params);

// Nama bulan dari fungsi sendiri
$namabulan = tanggal_indo($starttime, 'bulan');

// PDF
$pdf = new pdf();
$pdf->AddPage('P', 'F4');
$pdf->SetFont('helvetica', '', 10);

// HTML
$html = "<h3 style='text-align:center; margin-top:10px; margin-bottom:10px;'>
Rekap Surat Izin Guru/Pegawai<br>Bulan {$namabulan}
</h3>";

$html .= "<table cellpadding='4' cellspacing='0' style='font-size:9px; width:100%; border-collapse: collapse;' border='1'>";
$html .= "<thead>
<tr style='font-weight:bold; text-align:center;'>
    <th style='width:4%;'>No</th>
    <th style='width:20%;'>Hari, Tanggal</th>
    <th style='width:20%;'>Nama</th>
    <th style='width:16%;'>NIP</th>
    <th style='width:20%;'>Alasan</th>
    <th style='width:20%;'>Keperluan</th>
</tr>
</thead><tbody>";

$no = 1;
foreach ($records as $r) {

    $tanggal  = tanggal_indo($r->waktuinput, 'judul');
    $namaguru = $r->lastname;

    $html .= "<tr>
        <td style='text-align:center;'>{$no}</td>
        <td>{$tanggal}</td>
        <td>{$namaguru}</td>
        <td>{$r->nip}</td>
        <td>{$r->alasan}</td>
        <td>{$r->keperluan}</td>
    </tr>";
    $no++;
}

// Jika kosong
if ($no === 1) {
    $html .= "<tr><td colspan='6' style='text-align:center;'>Tidak ada data</td></tr>";
}

$html .= "</tbody></table>";

// Output PDF
$pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
$pdf->Output("rekap_surat_izin_guru_{$bulan}_{$tahun}.pdf", 'I');
