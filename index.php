<?php
require_once(__DIR__ . '/../../config.php');
require_login();

use local_jurnalmengajar\form\jurnal_form;

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/index.php'));
$PAGE->set_title('Isi Jurnal Mengajar');
$PAGE->set_heading('Jurnal Mengajar');

require_once(__DIR__ . '/lib.php');

// ================= JS (Tetap dipertahankan, namun disarankan pindah ke AMD) =================
$PAGE->requires->jquery();
$PAGE->requires->js_init_code(<<<JS
$(document).ready(function() {

    $('input[name="jamke"]').on('input', function () {
        const val = $(this).val();
        const valid = /^(\d+(,\d+)*)?$/.test(val);
        if (!valid) {
            this.setCustomValidity("Isian hanya boleh angka dan koma, misalnya: 2,3");
        } else {
            this.setCustomValidity("");
        }
    });

    function loadSiswa(kelas) {
        if (!kelas) return;
        $.get("/local/jurnalmengajar/get_students.php", {kelas: kelas}, function(data) {
            $("#absen-area").html(data);
            bindAbsenEvent();
        });
    }

    function bindAbsenEvent() {

    $('.absen-checkbox').on('change', function() {

        const parent = $(this).closest('.absen-item');
        const dropdown = parent.find('.absen-alasan');

        if ($(this).is(':checked')) {

            dropdown.prop('disabled', false);

        } else {

            dropdown.prop('disabled', true).val('');
        }

        updateAbsenField();
    });

    $('.absen-alasan').on(
        'change',
        updateAbsenField
    );

}

    function updateAbsenField() {

    const data = {};
    const dataid = {};

    $('.absen-checkbox:checked').each(function() {

        const nama = $(this).data('nama');
        const userid = $(this).data('userid');

        const alasan = $(this)
            .closest('.absen-item')
            .find('.absen-alasan')
            .val();

        if (alasan) {

            data[nama] = alasan;
            dataid[userid] = alasan;
        }
    });

    $('textarea[name="absen"]')
        .val(JSON.stringify(data));

    $('input[name="absenid"]')
        .val(JSON.stringify(dataid));
}

	$('select[name=kelas]').on('change', function() {

	    const kelas = $(this).val();

	    loadSiswa(kelas);
	    loadDropdownMurid(kelas);

	});

function loadDropdownMurid(kelas) {

    if (!kelas) {
        return;
    }

    $.get(
        "/local/jurnalmengajar/get_students_dropdown.php",
        {kelas: kelas},
        function(data) {

            $('select[name="murid_pembinaan"]').html(data);

        }
    );
}

let daftarPembinaan = [];

$('#tambah-pembinaan').on('click', function() {

    const muridid = $('select[name="murid_pembinaan"]').val();
    const murid = $('select[name="murid_pembinaan"] option:selected').text();

    const jenis = $('select[name="jenis_pembinaan"]').val();
    const jenisText = $('select[name="jenis_pembinaan"] option:selected').text();

    const catatan = $('textarea[name="catatan_pembinaan"]').val();
    const tindaklanjut = $('textarea[name="tindaklanjut_pembinaan"]').val();

    if (!muridid) {
        alert('Pilih murid terlebih dahulu');
        return;
    }

    if (!jenis) {
        alert('Pilih jenis pembinaan');
        return;
    }

    daftarPembinaan.push({
        muridid: muridid,
        murid: murid,
        jenis: jenis,
        catatan: catatan,
        tindaklanjut: tindaklanjut
    });

    $('input[name="pembinaanjson"]')
        .val(JSON.stringify(daftarPembinaan));

    let html = '';

    daftarPembinaan.forEach(function(item, index) {

        html += '<div style="margin-bottom:10px;">';
        html += '<strong>' + (index + 1) + '. ' + item.murid + '</strong><br>';
        html += 'Jenis: ' + item.jenis + '<br>';
        html += 'Catatan: ' + item.catatan + '<br>';
        html += 'Tindak lanjut: ' + item.tindaklanjut;
        html += '</div><hr>';
    });

    $('#daftar-pembinaan').html(html);

    $('textarea[name="catatan_pembinaan"]').val('');
    $('textarea[name="tindaklanjut_pembinaan"]').val('');
});

    const kelasawal = $('select[name=kelas]').val();

	loadSiswa(kelasawal);
	loadDropdownMurid(kelasawal);

});
JS);

// ================= FORM =================
$mform = new jurnal_form();

// ================= PROSES SIMPAN =================
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/my'));

} else if ($data = $mform->get_data()) {

    // Validasi jam ke
    if (!preg_match('/^\d+(,\d+)*$/', $data->jamke)) {
        print_error('Isian "Jam Pelajaran Ke" hanya boleh angka dan koma, contoh: 2,3');
    }

    // Validasi kelas
    if (empty($data->kelas)) {
        print_error('Kelas tidak boleh kosong');
    }

    $record = new stdClass();
    $record->userid = $USER->id;

    // Nomor otomatis
    $last = $DB->get_record_sql("
        SELECT MAX(nomor) AS maxnomor
        FROM {local_jurnalmengajar}
        WHERE userid = ?
    ", [$USER->id]);

    $record->nomor = ($last && $last->maxnomor) ? $last->maxnomor + 1 : 1;

    $record->kelas = (int)$data->kelas;
    $record->jamke = $data->jamke;
    $record->matapelajaran = $data->matapelajaran;
    $record->materi = $data->materi;
    $record->aktivitas = $data->aktivitas;
    $record->absen = $data->absen ?? '{}';
    $record->absenid = $data->absenid ?? '{}';
    $record->keterangan = $data->keterangan ?: '-';
    $record->timecreated = time();

    // Simpan ke database
	$jurnalid = $DB->insert_record(
	    'local_jurnalmengajar',
	    $record
	);

$pembinaan = json_decode(
    $data->pembinaanjson ?? '[]',
    true
);

if (!empty($pembinaan)) {

    foreach ($pembinaan as $p) {

        $pb = new stdClass();

        $pb->jurnalid = $jurnalid;
        $pb->userid = $USER->id;

        $pb->muridid = (int)$p['muridid'];

        $pb->kelas = (int)$data->kelas;

        $pb->jenis = $p['jenis'] ?? '';
        $pb->catatan = $p['catatan'] ?? '';
        $pb->tindaklanjut = $p['tindaklanjut'] ?? '';

        $pb->timecreated = time();
        $pb->timemodified = time();

        $DB->insert_record(
            'local_jurnalmengajar_pembinaanmapel',
            $pb
        );
    }
}

    // ================= KIRIM NOTIF WA =================
    $kelasid = $record->kelas ?? null;

    if ($kelasid) {
        $namaguru = !empty($USER->lastname) ? $USER->lastname : $USER->firstname;
        $kelas = get_nama_kelas($kelasid);

        $jamke = $record->jamke ?? '-';
        $mapel = $record->matapelajaran ?? '-';
        $materi = $record->materi ?? '-';
        $aktivitas = $record->aktivitas ?? '-';

        $absenjson = $record->absen ?? '{}';
        $absenarr = json_decode($absenjson, true);

        $absen = '-';
        if (!empty($absenarr)) {
            $formatted = [];
            $no = 1;
            foreach ($absenarr as $nama => $alasan) {
                $formatted[] = $no++ . ". {$nama}: {$alasan}";
            }
            $absen = implode("\n", $formatted);
        }

        $keterangan = $record->keterangan ?? '-';
        $sekolah = get_config('local_jurnalmengajar', 'nama_sekolah') ?: 'Nama Sekolah';
        $tanggal = tanggal_indo(time(), 'judul');
        $jam = tanggal_indo(time(), 'jam');

        $pesan = "*📘 Jurnal KBM _{$tanggal}_*\n\n"
               . "👤 Guru: $namaguru\n"
               . "🏫 Kelas: $kelas\n"
               . "⏰ Jam ke: $jamke\n"
               . "📚 Mata Pelajaran: $mapel\n"
               . "📒 Materi: $materi\n"
               . "📝 Aktivitas:\n$aktivitas\n\n"
               . "🔴 Murid tidak hadir:\n$absen\n\n"
               . "Keterangan tambahan:\n$keterangan\n\n"
               . "🕒 Waktu: $jam WITA\n"
               . "📌 Tercatat di eJurnal KBM $sekolah\n\n"
               . "_Dikirim ke Wali kelas dan Guru ybs sebagai laporan_";

        // Tujuan
        $tujuan = [];
        $nowaguru = get_user_nowa($USER->id);
        $nowawali = get_nomor_wali_kelas($kelasid);

        if (!empty($nowaguru)) { $tujuan[] = $nowaguru; }
        if (!empty($nowawali)) { $tujuan[] = $nowawali; }

        // Kirim WA
        jurnalmengajar_kirim_wa($tujuan, $pesan);
    }

    redirect(
        new moodle_url('/local/jurnalmengajar/index.php'),
        '✅ Jurnal berhasil disimpan. Cek Riwayat Jurnal di bawah.',
        2
    );
}

$PAGE->requires->js_call_amd('local_jurnalmengajar/absen', 'init');

// ================= TAMPILAN MCOODLE =================
echo $OUTPUT->header();
echo $OUTPUT->heading('Input Jurnal Mengajar', 2, 'mb-4');

// Form ditaruh di dalam Card Bootstrap agar lebih rapi & fokus
echo html_writer::start_div('card mb-4 shadow-sm');
echo html_writer::start_div('card-body');
$mform->display();
echo html_writer::end_div();
echo html_writer::end_div();

// ================= SECTION RIWAYAT =================
echo html_writer::tag('h3', 'Riwayat Jurnal Saya');

$sql = "SELECT * FROM {local_jurnalmengajar} WHERE userid = :userid ORDER BY id DESC LIMIT 7";
$params = ['userid' => $USER->id];
$entries = $DB->get_records_sql($sql, $params);

if ($entries) {
    // Penambahan class 'table-responsive' agar aman dibuka di HP
    echo html_writer::start_div('table-responsive shadow-sm rounded');
    echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover generaltable mb-0']);
    echo html_writer::start_tag('thead', ['class' => 'thead-dark']); // Header tabel gelap agar kontras
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', '#', ['scope' => 'col', 'style' => 'width: 5%;']);
    echo html_writer::tag('th', 'Kelas', ['scope' => 'col']);
    echo html_writer::tag('th', 'Jam Ke', ['scope' => 'col']);
    echo html_writer::tag('th', 'Mapel', ['scope' => 'col']);
    echo html_writer::tag('th', 'Materi', ['scope' => 'col']);
    echo html_writer::tag('th', 'Absen', ['scope' => 'col']);
    echo html_writer::tag('th', 'Waktu', ['scope' => 'col']);
    echo html_writer::tag('th', 'Aksi', ['scope' => 'col', 'class' => 'text-center']);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    $no = 1;
    foreach ($entries as $e) {
        $absendata = json_decode($e->absen, true);
        $absentext = '';

        if (is_array($absendata)) {
            foreach ($absendata as $nama => $alasan) {
                $absentext .= "$nama ($alasan), ";
            }
            $absentext = rtrim($absentext, ', ');
        } else {
            $absentext = $e->absen;
        }

        $namakelas = get_nama_kelas($e->kelas);
        $editurl = new moodle_url('/local/jurnalmengajar/edit.php', ['id' => $e->id]);
        
        // Tombol edit dengan style Bootstrap primary kecil (btn-sm) + Icon Pensil
        $editicon = $OUTPUT->pix_icon('t/edit', 'Edit');
        $editlink = html_writer::link($editurl, $editicon . ' Edit', ['class' => 'btn btn-outline-primary btn-sm']);

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $no++);
        echo html_writer::tag('td', html_writer::tag('strong', $namakelas));
        echo html_writer::tag('td', $e->jamke);
        echo html_writer::tag('td', $e->matapelajaran);
        echo html_writer::tag('td', shorten_text($e->materi, 30), ['title' => $e->materi]);
        echo html_writer::tag('td', $absentext ? shorten_text($absentext, 25) : '-', ['title' => $absentext]);
        echo html_writer::tag('td', html_writer::tag('small', tanggal_indo($e->timecreated)));
        echo html_writer::tag('td', $editlink, ['class' => 'text-center']);
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div(); // close table-responsive

} else {
    echo html_writer::div('Belum ada riwayat jurnal.', 'alert alert-info shadow-sm');
}

// ================= TOMBOL NAVIGASI BAWAH =================
// Menggunakan utilitas flexbox d-flex Bootstrap untuk perataan tombol
echo html_writer::start_div('d-flex justify-content-between align-items-center mt-4 mb-5');

echo html_writer::link(
    '#',
    '<i class="fa fa-arrow-left"></i> Kembali',
    [
        'class' => 'btn btn-secondary',
        'onclick' => 'history.back(); return false;'
    ]
);

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/riwayat_jurnal.php'),
    '📚 Riwayat Jurnal Bulanan',
    ['class' => 'btn btn-primary shadow-sm']
);

echo html_writer::end_div();

echo $OUTPUT->footer();
