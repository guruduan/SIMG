<?php

require_once('../../config.php');
require_once($CFG->libdir.'/pdflib.php');
require_once($CFG->dirroot.'/local/jurnalmengajar/lib.php');

require_login();

$id = required_param('id', PARAM_INT);
global $DB;

// Ambil data surat
$data = $DB->get_record('local_jurnalmengajar_suratizin', ['id' => $id], '*', MUST_EXIST);
$siswa = $DB->get_record('user', ['id' => $data->userid], 'id, lastname');
$kelas = $DB->get_record('cohort', ['id' => $data->kelasid], 'id, name');
$penginput = $DB->get_record('user', ['id' => $data->penginput], 'id, lastname');
$guru = $DB->get_record('user', ['id' => $data->guru_pengajar], 'id, lastname');

// ==========================
// SETTINGS SEKOLAH
// ==========================
$sekolah = get_config('local_jurnalmengajar', 'nama_sekolah');
$tempat  = get_config('local_jurnalmengajar', 'tempat_ttd');

// Ambil NIP
$fieldid_nip = $DB->get_field('user_info_field', 'id', ['shortname' => 'nip']);

$nip_guru = $DB->get_field('user_info_data', 'data', [
    'userid' => $guru->id,
    'fieldid' => $fieldid_nip
]);

$nip_penginput = $DB->get_field('user_info_data', 'data', [
    'userid' => $penginput->id,
    'fieldid' => $fieldid_nip
]);

// Format tanggal Indonesia
$fmt = new IntlDateFormatter(
    'id_ID',
    IntlDateFormatter::FULL,
    IntlDateFormatter::NONE,
    'Asia/Makassar',
    null,
    'EEEE, dd MMMM yyyy'
);

$tanggal = $fmt->format($data->timecreated);

// ==========================
// TANGGAL UNTUK NAMA FILE
// ==========================
$bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$tanggalfile = date('j') . ' ' . $bulan[date('n')-1] . ' ' . date('Y');

// ==========================
// SIAPKAN PDF
// ==========================
$pdf = new pdf();
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetAuthor($sekolah);
$pdf->SetTitle('Surat Izin Murid - ' . $sekolah . ' - ' . $tanggalfile);
$pdf->SetSubject('Surat Izin Murid');

$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// Isi surat
$sekolah_upper = mb_strtoupper($sekolah);
$html = <<<HTML
<h3 style="text-align: center;">
SURAT IZIN KELUAR/MASUK MURID<br>
{$sekolah_upper}
</h3>

<br><br>

<table>
<tr><td width="120">Nama</td><td>: {$siswa->lastname}</td></tr>
<tr><td>Kelas</td><td>: {$kelas->name}</td></tr>
<tr><td>Alasan</td><td>: {$data->alasan}</td></tr>
<tr><td>Keperluan</td><td>: {$data->keperluan}</td></tr>
</table>

<br><br>

<table width="100%">
<tr>
    <td width="50%" style="text-align:left;">Guru Pengajar</td>
    <td width="50%" style="text-align:left;">{$tempat}, {$tanggal}</td>
</tr>
<tr>
    <td></td>
    <td style="text-align:left;">Pengawas Harian</td>
</tr>
<tr><td colspan="2"><br><br><br></td></tr>
<tr>
    <td><u>{$guru->lastname}</u></td>
    <td><u>{$penginput->lastname}</u></td>
</tr>
<tr>
    <td>NIP: {$nip_guru}</td>
    <td>NIP: {$nip_penginput}</td>
</tr>
</table>
HTML;

// Garis potong
$separator = <<<HTML
<br>
<hr style="border-top: 1px dashed #000;">
<br><br>
HTML;

// Tulis HTML
$htmloutput = $html . $separator;
$pdf->writeHTML($htmloutput);

// Ambil stempel dari settings
$stempel_path = jurnalmengajar_get_stempel_path();
if (!empty($stempel_path) && file_exists($stempel_path)) {
    $pdf->Image($stempel_path, 70, 42, 38, 38, 'PNG');
}

// Output PDF
$pdf->Output('surat_izin-' . $tanggalfile . '.pdf', 'I');
