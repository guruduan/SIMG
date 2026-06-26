<?php

require('../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());


$PAGE->set_url(new moodle_url('/local/jurnalmengajar/template_notifikasi.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Template Notifikasi');
$PAGE->set_heading('Template Notifikasi');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {

    set_config(
        'template_jurnal',
        trim($_POST['template_jurnal']),
        'local_jurnalmengajar'
    );
    
    set_config(
        'template_guru_wali',
        trim($_POST['template_guru_wali']),
        'local_jurnalmengajar'
    );

    set_config(
        'template_izin_murid',
        trim($_POST['template_izin_murid']),
        'local_jurnalmengajar'
    );
    
    set_config(
    'template_pembinaan',
    trim($_POST['template_pembinaan']),
    'local_jurnalmengajar'
    );

	set_config(
	    'template_layanan_bk',
	    trim($_POST['template_layanan_bk']),
	    'local_jurnalmengajar'
	);

    set_config(
        'template_izin_guru',
        trim($_POST['template_izin_guru']),
        'local_jurnalmengajar'
    );
    
	set_config(
	    'template_reminder_jurnal',
	    trim($_POST['template_reminder_jurnal']),
	    'local_jurnalmengajar'
	);
	
	set_config(
	    'template_rekap_reminder',
	    trim($_POST['template_rekap_reminder']),
	    'local_jurnalmengajar'
    );

    redirect(
        new moodle_url('/local/jurnalmengajar/template_notifikasi.php'),
        'Template berhasil disimpan',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$template_jurnal = get_config(
    'local_jurnalmengajar',
    'template_jurnal'
);

$template_guru_wali = get_config(
    'local_jurnalmengajar',
    'template_guru_wali'
);

$template_izin_murid = get_config(
    'local_jurnalmengajar',
    'template_izin_murid'
);

$template_pembinaan = get_config(
    'local_jurnalmengajar',
    'template_pembinaan'
);

$template_layanan_bk = get_config(
    'local_jurnalmengajar',
    'template_layanan_bk'
);

$template_izin_guru = get_config(
    'local_jurnalmengajar',
    'template_izin_guru'
);

$template_reminder_jurnal = get_config(
    'local_jurnalmengajar',
    'template_reminder_jurnal'
);

$template_rekap_reminder = get_config(
    'local_jurnalmengajar',
    'template_rekap_reminder'
);

if (empty($template_jurnal)) {
    $template_jurnal =
"📘 JURNAL KBM

Guru : {guru}
Kelas : {kelas}
Jam Ke : {jamke}

Materi :
{materi}

Aktivitas :
{aktivitas}

Tidak Hadir :
{absen}

Tanggal : {tanggal}";
}

if (empty($template_guru_wali)) {
    $template_guru_wali =
"📋 Jurnal Guru Wali

📅 Waktu: {waktu}
👤 Murid: {murid}
🏫 Kelas: {kelas}
🧩 Topik: {topik}
💡 Tindak Lanjut: {tindaklanjut}
📝 Keterangan: {keterangan}
👨‍🏫 Guru Wali: {guruwali}

_Dikirim kepada Wali Kelas sebagai laporan_";
}

if (empty($template_izin_murid)) {
    $template_izin_murid =
"📄 Surat Izin Murid

📅 Waktu: {waktu}
👤 Nama: {nama}
🏫 Kelas: {kelas}
🎓 Guru Pengajar: {guru}
📝 Alasan: {alasan}
📌 Keperluan: {keperluan}
✍️ Pengawas Hari Ini: {pengawas}

_Dikirim kepada Wali kelas sebagai laporan_";
}

if (empty($template_pembinaan)) {
    $template_pembinaan =
"📋 LAPORAN PEMBINAAN SISWA

📅 Waktu :
{waktu}

👥 Murid :
{murid}

🏫 Kelas :
{kelas}

📌 Permasalahan :
{permasalahan}

🔧 Upaya :
{upaya}

👤 Guru BK :
{gurubk}

_Dikirim kepada Wali Kelas sebagai laporan_";
}

if (empty($template_layanan_bk)) {
    $template_layanan_bk =
"📋 LAPORAN LAYANAN BK

📅 Hari :
{waktu}

👥 Murid :
{murid}

🏫 Kelas :
{kelas}

📝 Jenis Layanan :
{jenislayanan}

📌 Topik :
{topik}

🔧 Tindak Lanjut :
{tindaklanjut}

📑 Catatan :
{catatan}

👤 Guru BK :
{gurubk}";
}

if (empty($template_izin_guru)) {
    $template_izin_guru =
"📄 SURAT IZIN GURU/PEGAWAI

👤 Nama: {guru}

🆔 NIP: {nip}

📝 Alasan: {alasan}

📌 Keperluan: {keperluan}

📅 Tanggal: {tanggal}

🕒 Pukul: {jam}
📝 Diinput oleh: {penginput}";
}

if (empty($template_reminder_jurnal)) {
    $template_reminder_jurnal =
"Notifikasi SiM ❗

Bpk/Ibu Guru {guru},
mohon mengisi jurnal mengajar hari ini ({tanggal}) untuk:

{kelasjam}

Terima kasih.
_abaikan jika sudah mengisi_";
}

if (empty($template_rekap_reminder)) {
    $template_rekap_reminder =
"📋 REKAP GURU BELUM MENGISI JURNAL

📅 Tanggal:
{tanggal}

{daftar}

📊 Total Guru:
{jumlah}";
}

echo $OUTPUT->header();

?>

<div class="container-fluid">


<h3>Template Notifikasi WhatsApp</h3>

<form method="post">

    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

    <div class="card mb-4">
        <div class="card-header">
            <strong>Jurnal Mengajar</strong>
        </div>
        <div class="card-body">

            <p>
                Placeholder:
                <code>{guru}</code>
                <code>{kelas}</code>
                <code>{jamke}</code>
                <code>{materi}</code>
                <code>{aktivitas}</code>
                <code>{absen}</code>
                <code>{tanggal}</code>
                <code>{mapel}</code>
		<code>{keterangan}</code>
		<code>{jam}</code>
		<code>{sekolah}</code>
            </p>

            <textarea
                name="template_jurnal"
                class="form-control"
                rows="12"><?php echo s($template_jurnal); ?></textarea>

        </div>
    </div>

	<div class="card mb-4">
	    <div class="card-header">
		<strong>Jurnal Guru Wali</strong>
	    </div>

	    <div class="card-body">

		<p>
		    Placeholder:
		    <code>{waktu}</code>
		    <code>{murid}</code>
		    <code>{kelas}</code>
		    <code>{topik}</code>
		    <code>{tindaklanjut}</code>
		    <code>{keterangan}</code>
		    <code>{guruwali}</code>
		</p>

		<textarea
		    name="template_guru_wali"
		    class="form-control"
		    rows="12"><?php echo s($template_guru_wali); ?></textarea>

	    </div>
	</div>
	
    <div class="card mb-4">
        <div class="card-header">
            <strong>Surat Izin Murid</strong>
        </div>
        <div class="card-body">

            <p>
                Placeholder:
                <code>{waktu}</code>
		<code>{nama}</code>
		<code>{kelas}</code>
		<code>{guru}</code>
		<code>{alasan}</code>
		<code>{keperluan}</code>
		<code>{pengawas}</code>
            </p>

            <textarea
                name="template_izin_murid"
                class="form-control"
                rows="10"><?php echo s($template_izin_murid); ?></textarea>

        </div>
    </div>

	<div class="card mb-4">
	    <div class="card-header">
		<strong>Laporan Pembinaan Siswa oleh BK</strong>
	    </div>

	    <div class="card-body">

		<p>
		    Placeholder:
		    <code>{waktu}</code>
		    <code>{murid}</code>
		    <code>{kelas}</code>
		    <code>{permasalahan}</code>
		    <code>{upaya}</code>
		    <code>{gurubk}</code>
		</p>

		<textarea
		    name="template_pembinaan"
		    class="form-control"
		    rows="12"><?php echo s($template_pembinaan); ?></textarea>

	    </div>
	</div>
	<div class="card mb-4">
	    <div class="card-header">
		<strong>Laporan Layanan BK</strong>
	    </div>

	    <div class="card-body">

		<p>
		    Placeholder:
		    <code>{waktu}</code>
		    <code>{murid}</code>
		    <code>{kelas}</code>
		    <code>{jenislayanan}</code>
		    <code>{topik}</code>
		    <code>{tindaklanjut}</code>
		    <code>{catatan}</code>
		    <code>{gurubk}</code>
		</p>

		<textarea
		    name="template_layanan_bk"
		    class="form-control"
		    rows="12"><?php echo s($template_layanan_bk); ?></textarea>

	    </div>
	</div>

    <div class="card mb-4">
        <div class="card-header">
            <strong>Surat Izin Guru</strong>
        </div>
        <div class="card-body">

            <p>
                Placeholder:
                <code>{guru}</code>
		<code>{nip}</code>
		<code>{alasan}</code>
		<code>{keperluan}</code>
		<code>{tanggal}</code>
		<code>{jam}</code>
		<code>{penginput}</code>
            </p>

            <textarea
                name="template_izin_guru"
                class="form-control"
                rows="10"><?php echo s($template_izin_guru); ?></textarea>

        </div>
    </div>
	<div class="card mb-4">
	    <div class="card-header">
		<strong>Notifikasi Reminder Jurnal Mengajar</strong>
	    </div>

	    <div class="card-body">

		<p>
		    Placeholder:
		    <code>{guru}</code>
		    <code>{tanggal}</code>
		    <code>{kelasjam}</code>
		</p>

		<textarea
		    name="template_reminder_jurnal"
		    class="form-control"
		    rows="10"><?php echo s($template_reminder_jurnal); ?></textarea>

	    </div>
	</div>

	<div class="card mb-4">
	    <div class="card-header">
		<strong>Rekap Guru Belum Mengisi Jurnal</strong>
	    </div>

	    <div class="card-body">

		<p>
		    Placeholder:
		    <code>{tanggal}</code>
		    <code>{daftar}</code>
		    <code>{jumlah}</code>
		</p>

		<textarea
		    name="template_rekap_reminder"
		    class="form-control"
		    rows="12"><?php echo s($template_rekap_reminder); ?></textarea>

	    </div>
	</div>


    <button type="submit" class="btn btn-primary">
        Simpan Template
    </button>

</form>


</div>

<?php
echo $OUTPUT->footer();
