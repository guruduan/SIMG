<?php
require_once('../../config.php');
require_login();
$context = context_system::instance();
$PAGE->set_context($context);

global $CFG, $DB, $USER;
require_once($CFG->libdir.'/pdflib.php');
require_once($CFG->dirroot.'/local/jurnalmengajar/lib.php');

$namasekolah = get_config('local_jurnalmengajar', 'nama_sekolah');
$tempatttd   = get_config('local_jurnalmengajar', 'tempat_ttd');
$namakepsek  = get_config('local_jurnalmengajar', 'nama_kepsek');
$nipkepsek   = get_config('local_jurnalmengajar', 'nip_kepsek');

$id = required_param('id', PARAM_INT);

// Ambil data surat izin guru
$surat = $DB->get_record('local_jurnalmengajar_suratizinguru', ['id' => $id], '*', MUST_EXIST);
$guru = $DB->get_record('user', ['id' => $surat->userid], '*', MUST_EXIST);

// Ambil NIP guru dari custom profile field 'nip'
$fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => 'nip']);
$nip = '';
if ($fieldid) {
    $nip = $DB->get_field('user_info_data', 'data', ['userid' => $guru->id, 'fieldid' => $fieldid]);
}

// Setting locale dan timezone
$tanggal = tanggal_indo($surat->waktuinput, 'judul');
$nama = format_string($guru->lastname);
$alasan     = format_string($surat->alasan);
$keperluan  = format_string($surat->keperluan);

// Fungsi untuk membentuk HTML surat izin guru
function suratizin_guru_html($nama, $nip, $alasan, $keperluan, $tanggal) {

    $namasekolah = get_config('local_jurnalmengajar', 'nama_sekolah');
    $tempatttd   = get_config('local_jurnalmengajar', 'tempat_ttd');
    $namakepsek  = get_config('local_jurnalmengajar', 'nama_kepsek');
    $nipkepsek   = get_config('local_jurnalmengajar', 'nip_kepsek');
    $namasekolah_upper = strtoupper($namasekolah);
    
    $html = <<<HTML
    <div style="page-break-inside: avoid; margin-bottom: 10px; font-size:10px; line-height:1.1;">
    <h3 style="text-align:center; line-height:1.1; font-size:12px; margin-bottom: 5px;">
        SURAT IZIN KELUAR<br>
        TENAGA PENDIDIK DAN TENAGA KEPENDIDIKAN<br>
        {$namasekolah_upper}
    </h3>
    <table cellpadding="2" style="font-size:10px; width:100%;">
        <tr><td width="90">Nama</td><td>: {$nama}</td></tr>
        <tr><td>NIP</td><td>: {$nip}</td></tr>
        <tr><td>Alasan</td><td>: {$alasan}</td></tr>
<tr><td>Keperluan</td><td>: {$keperluan}</td></tr>
    </table>
    <table width="100%" style="font-size:10px;">
        <tr>
            <td width="50%"></td>
            <td width="50%" style="text-align:left; padding-left:5px;">
                {$tempatttd}, {$tanggal}<br>
                Kepala {$namasekolah},<br><br><br><br>
                {$namakepsek}<br>
                NIP. {$nipkepsek}
            </td>
        </tr>
    </table>
    </div>
HTML;
    return $html;
}

// Siapkan PDF
$pdf = new pdf();
$pdf->AddPage('P', 'F4');
$pdf->SetFont('helvetica', '', 10);


$ttd = jurnalmengajar_get_ttd_path();

// Cetak 2 surat per halaman
$htmlsurat = suratizin_guru_html($nama, $nip, $alasan, $keperluan, $tanggal);
$separator = '<hr style="border-top: 1px dashed #000; margin:8px 0;">';

for ($i = 0; $i < 2; $i++) {

    $pdf->writeHTML($htmlsurat, true, false, true, false, '');

    if (!empty($ttd) && file_exists($ttd)) {
        $pdf->Image($ttd, 110, $pdf->GetY() - 36, 20);
    }

    if ($i < 1) {
        $pdf->writeHTML($separator, true, false, true, false, '');
    }
}
// 👉 TAMBAHKAN INI (garis bawah terakhir)
$pdf->writeHTML($separator, true, false, true, false, '');

// Output PDF
$pdf->Output('surat_izin_guru.pdf', 'I');
