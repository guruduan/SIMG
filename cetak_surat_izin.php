<?php

require_once('../../config.php');
require_once($CFG->libdir.'/pdflib.php');
require_once($CFG->dirroot.'/local/jurnalmengajar/lib.php');

require_login();

global $DB;

// Dukung ?id=123 (lama, satu murid) dan ?ids=1,2,3 (baru, gabungan).
$id_single = optional_param('id', 0, PARAM_INT);
$ids_raw   = optional_param('ids', '', PARAM_TEXT);

$ids = [];
if ($id_single > 0) {
    $ids[] = $id_single;
}
if ($ids_raw !== '') {
    foreach (explode(',', $ids_raw) as $tok) {
        $tok = (int)trim($tok);
        if ($tok > 0) {
            $ids[] = $tok;
        }
    }
}
$ids = array_values(array_unique($ids));

if (empty($ids)) {
    throw new moodle_exception(
        'generalexceptionmessage',
        'error',
        '',
        'Parameter "id" atau "ids" wajib diisi. Contoh: ?id=123 atau ?ids=1,2,3'
    );
}

// Ambil semua record surat (lewati ID yang tidak ada).
$records = [];
foreach ($ids as $sid) {
    $rec = $DB->get_record('local_jurnalmengajar_suratizin', ['id' => $sid]);
    if ($rec) {
        $records[] = $rec;
    }
}

if (empty($records)) {
    throw new moodle_exception(
        'generalexceptionmessage',
        'error',
        '',
        'Tidak ada data surat izin yang valid untuk dicetak.'
    );
}

// ==========================
// SETTINGS SEKOLAH
// ==========================
$sekolah = get_config('local_jurnalmengajar', 'nama_sekolah');
$tempat  = get_config('local_jurnalmengajar', 'tempat_ttd');

// Ambil NIP (aman jika field belum ada)
$fieldid_nip = $DB->get_field('user_info_field', 'id', ['shortname' => 'nip']);

function cetak_izin_nip($userid) {
    global $DB, $fieldid_nip;

    if (empty($fieldid_nip) || empty($userid)) {
        return '-';
    }

    return $DB->get_field('user_info_data', 'data', [
        'userid' => $userid,
        'fieldid' => $fieldid_nip
    ]) ?: '-';
}

function cetak_izin_gabung_nama(array $nama) {
    $nama = array_values(array_filter($nama));

    if (empty($nama)) {
        return '-';
    }

    if (count($nama) === 1) {
        return $nama[0];
    }

    if (count($nama) === 2) {
        return $nama[0] . ' dan ' . $nama[1];
    }

    return implode(', ', array_slice($nama, 0, -1)) . ', dan ' . end($nama);
}

// ==========================
// KELOMPOKKAN: SATU SURAT GABUNGAN ATAU SATU HALAMAN PER SURAT
// ==========================
// Kalau semua record sama kelas, keperluan, alasan, guru pengajar
// dan penginputnya (inilah yang dikirim izin_murid.php untuk satu
// surat gabungan), cetak SATU surat dengan nama digabung.
// Kalau tidak sama (mis. ID dicampur dari surat yang berbeda),
// jatuhkan ke satu halaman per surat seperti sebelumnya.
$gabung = true;
$pertama = $records[0];
foreach ($records as $rec) {
    if ($rec->kelasid !== $pertama->kelasid
        || $rec->keperluan !== $pertama->keperluan
        || $rec->alasan !== $pertama->alasan
        || $rec->guru_pengajar !== $pertama->guru_pengajar
        || $rec->penginput !== $pertama->penginput) {
        $gabung = false;
        break;
    }
}

$halaman = [];
if ($gabung) {
    $nama = [];
    foreach ($records as $rec) {
        $siswa = $DB->get_record('user', ['id' => $rec->userid], 'id, lastname');
        if ($siswa) {
            $nama[] = ucwords(strtolower($siswa->lastname));
        }
    }

    $halaman[] = [
        'nama'      => cetak_izin_gabung_nama($nama),
        'record'    => $pertama,
    ];
} else {
    foreach ($records as $rec) {
        $siswa = $DB->get_record('user', ['id' => $rec->userid], 'id, lastname');
        $halaman[] = [
            'nama'      => $siswa ? ucwords(strtolower($siswa->lastname)) : '-',
            'record'    => $rec,
        ];
    }
}

// ==========================
// SIAPKAN PDF
// ==========================
$tanggalfile = tanggal_indo($pertama->timecreated, 'tanggal');

$pdf = new pdf();
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetAuthor($sekolah);
$pdf->SetTitle('Surat Izin Murid - ' . $sekolah . ' - ' . $tanggalfile);
$pdf->SetSubject('Surat Izin Murid');

foreach ($halaman as $hal) {
    $data = $hal['record'];

    // Nama sudah digabung di $hal['nama'] (satu nama atau "Ahmad dan Budi").
    $kelas = $DB->get_record('cohort', ['id' => $data->kelasid], 'id, name');
    $penginput = $DB->get_record('user', ['id' => $data->penginput], 'id, lastname');
    $guru = $DB->get_record('user', ['id' => $data->guru_pengajar], 'id, lastname');

    $tanggal = tanggal_indo($data->timecreated, 'judul');

    $alasan = htmlspecialchars($data->alasan);
    $keperluan = htmlspecialchars($data->keperluan);
    $namasiswa = htmlspecialchars($hal['nama']);
    $namaguru  = htmlspecialchars($guru->lastname ?? '-');
    $namapenginput = htmlspecialchars($penginput->lastname ?? '-');
    $namakelas = htmlspecialchars($kelas->name ?? '-');

    $nip_guru = cetak_izin_nip($data->guru_pengajar);
    $nip_penginput = cetak_izin_nip($data->penginput);

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
<tr><td width="120">Nama</td><td>: {$namasiswa}</td></tr>
<tr><td>Kelas</td><td>: {$namakelas}</td></tr>
<tr><td>Alasan</td><td>: {$alasan}</td></tr>
<tr><td>Keperluan</td><td>: {$keperluan}</td></tr>
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
    <td><u>{$namaguru}</u></td>
    <td><u>{$namapenginput}</u></td>
</tr>
<tr>
    <td>NIP {$nip_guru}</td>
    <td>NIP {$nip_penginput}</td>
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

    // Tambahkan stempel setelah HTML
    if (!empty($stempel_path) && file_exists($stempel_path)) {
        // Posisi stempel (atur sesuai kebutuhan)
        $pdf->Image($stempel_path, 70, 42, 38, 38, 'PNG');
    }
}

// Output PDF
if (ob_get_length()) {
    ob_clean();
}
$namafile = 'surat_izin_' . str_replace(' ', '_', $tanggalfile) . '.pdf';
$pdf->Output($namafile, 'I');
