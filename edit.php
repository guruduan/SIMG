<?php
require_once(__DIR__ . '/../../config.php');
require_login();

use local_jurnalmengajar\form\jurnal_form;

$id = required_param('id', PARAM_INT); // ✅ Ambil ID dari URL
$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/edit.php', ['id' => $id]));
$PAGE->set_title('Edit Jurnal Mengajar');
$PAGE->set_heading('Edit Jurnal Mengajar');

// 🔥 WAJIB: load jQuery
$PAGE->requires->jquery();

// 🔥 JS utama (mirror index.php + preload JSON)
$PAGE->requires->js_init_code(<<<JS
$(document).ready(function() {

let daftarPembinaan = [];
let editIndex = -1;

function loadSiswa(kelas, absenData = {}) {

    if (!kelas) {
        console.warn("Kelas tidak valid:", kelas);
        return;
    }

    $.get("/local/jurnalmengajar/get_students.php", {kelas: kelas}, function(data) {

        $("#absen-area").html(data);

        $('.absen-checkbox').each(function() {

            const userid = $(this).data('userid');

            if (absenData[userid]) {

                $(this).prop('checked', true);

                const parent = $(this).closest('.absen-item');
                const dropdown = parent.find('.absen-alasan');

                dropdown.prop('disabled', false);
                dropdown.val(absenData[userid]);
            }
        });

        bindAbsenEvent();
        updateAbsenField();
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

    $('.absen-alasan').on('change', updateAbsenField);
}

function updateAbsenField() {

    const hasil = {};
    const hasilid = {};

    $('.absen-checkbox:checked').each(function() {

        const nama = $(this).data('nama');
        const userid = $(this).data('userid');

        const alasan = $(this)
            .closest('.absen-item')
            .find('.absen-alasan')
            .val();

        if (alasan) {

            hasil[nama] = alasan;
            hasilid[userid] = alasan;
        }
    });

$('textarea[name="absen"]')
    .val(JSON.stringify(hasil));

$('input[name="absenid"]')
    .val(JSON.stringify(hasilid));
}


// 🔥 load pertama (setelah form benar-benar siap)
 $(window).on('load', function() {

let absenData = {};

try {
    absenData = JSON.parse(
        $('input[name="absenid"]').val() || '{}'
    );
} catch(e) {
    absenData = {};
}

    const kelas = $('select[name=kelas]').val();

    loadSiswa(kelas, absenData);
    loadDropdownMurid(kelas);

try {

    daftarPembinaan = JSON.parse(
        $('input[name="pembinaanjson"]').val() || '[]'
    );

    $('input[name="pembinaanjson"]')
        .val(JSON.stringify(daftarPembinaan));

} catch (e) {

    daftarPembinaan = [];

}

	renderPembinaan();

});

function resetFormPembinaan() {

    $('select[name="murid_pembinaan"]').val('');
    $('select[name="jenis_pembinaan"]').val('');
    $('textarea[name="catatan_pembinaan"]').val('');
    $('textarea[name="tindaklanjut_pembinaan"]').val('');

    editIndex = -1;

    $('#tambah-pembinaan')
        .text('Tambah Pembinaan')
        .removeClass('btn-warning')
        .addClass('btn-info');
}

function renderPembinaan() {

    let html = '';

    if (daftarPembinaan.length === 0) {
        html = 'Belum ada pembinaan.';
    } else {

        daftarPembinaan.forEach(function(item, index) {

            html += '<div style="margin-bottom:10px;">';

            html += '<strong>' + (index + 1) + '. ' + item.murid + '</strong><br>';
            html += 'Jenis: ' + item.jenis + '<br>';
            html += 'Catatan: ' + item.catatan + '<br>';
            html += 'Tindak lanjut: ' + item.tindaklanjut + '<br>';

            html += '<button type="button" class="btn btn-warning btn-sm edit-pembinaan" data-index="' + index + '">Edit</button> ';

            html += '<button type="button" class="btn btn-danger btn-sm hapus-pembinaan" data-index="' + index + '">Hapus</button>';

            html += '</div><hr>';
        });
    }

    $('#daftar-pembinaan').html(html);
}

$(document).on('click', '.edit-pembinaan', function() {

    const index = $(this).data('index');

    const item = daftarPembinaan[index];

    editIndex = index;

	loadDropdownMurid(
	    $('select[name=kelas]').val(),
	    item.muridid
	);

    $('select[name="jenis_pembinaan"]')
        .val(item.jenis);

    $('textarea[name="catatan_pembinaan"]')
        .val(item.catatan);

    $('textarea[name="tindaklanjut_pembinaan"]')
        .val(item.tindaklanjut);

    $('#tambah-pembinaan')
        .text('Simpan Perubahan')
        .removeClass('btn-info')
        .addClass('btn-warning');
});

$(document).on('click', '.hapus-pembinaan', function() {

    const index = $(this).data('index');

    if (!confirm('Hapus pembinaan ini?')) {
        return;
    }

	daftarPembinaan.splice(index, 1);

	if (editIndex === index) {
	    editIndex = -1;
	} else if (editIndex > index) {
	    editIndex--;
	}

	resetFormPembinaan();

	$('input[name="pembinaanjson"]')
	    .val(JSON.stringify(daftarPembinaan));

	renderPembinaan();
});

// 🔁 saat ganti kelas
$('select[name=kelas]').on('change', function() {

    const kelas = $(this).val();

    loadSiswa(kelas, {});
    loadDropdownMurid(kelas);

});

function loadDropdownMurid(kelas, selectedid = '') {

    if (!kelas) {
        return;
    }

    $.get(
        "/local/jurnalmengajar/get_students_dropdown.php",
        {kelas: kelas},
        function(data) {

            $('select[name="murid_pembinaan"]').html(data);

            if (selectedid) {
                $('select[name="murid_pembinaan"]').val(selectedid);
            }
        }
    );
}

$('#tambah-pembinaan').on('click', function() {

    const muridid = $('select[name="murid_pembinaan"]').val();
    const murid = $('select[name="murid_pembinaan"] option:selected').text();

    const jenis = $('select[name="jenis_pembinaan"]').val();

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

    const dataBaru = {
    muridid: muridid,
    murid: murid,
    jenis: jenis,
    catatan: catatan,
    tindaklanjut: tindaklanjut
};

if (editIndex >= 0) {

    daftarPembinaan[editIndex] = dataBaru;

} else {

    daftarPembinaan.push(dataBaru);
}

$('input[name="pembinaanjson"]')
    .val(JSON.stringify(daftarPembinaan));

renderPembinaan();

resetFormPembinaan();

});

});

JS
);

global $DB, $USER;

// ✅ Ambil data entri jurnal sesuai ID & user login
$record = $DB->get_record('local_jurnalmengajar', [
    'id' => $id,
    'userid' => $USER->id
], '*', MUST_EXIST);

// ✅ Hindari null pada field aktivitas
$record->aktivitas = $record->aktivitas ?? '';
$record->absenid = $record->absenid ?? '{}';

$record->tanggaldibuat = $record->timecreated;

// ✅ Tambahkan ID ke record agar masuk ke dalam form sebagai hidden input
$record->id = $id;

$pembinaanlama = $DB->get_records(
    'local_jurnalmengajar_pembinaanmapel',
    ['jurnalid' => $id]
);

$pembinaanjson = [];

foreach ($pembinaanlama as $p) {

	$user = $DB->get_record(
	    'user',
	    ['id' => $p->muridid],
	    'id,lastname'
	);

    $pembinaanjson[] = [
        'muridid' => $p->muridid,
        'murid' => $user ? $user->lastname : '',
        'jenis' => $p->jenis,
        'catatan' => $p->catatan,
        'tindaklanjut' => $p->tindaklanjut
    ];
}

$record->pembinaanjson = json_encode($pembinaanjson);

// ✅ Kirim data awal ke form
$mform = new jurnal_form(null, [
    'mode' => 'edit'
]);

if ($mform->is_cancelled()) {
    // ✅ Redirect jika batal
    redirect(new moodle_url('/local/jurnalmengajar/index.php'));

} else if ($data = $mform->get_data()) {

if (!preg_match('/^\d+(,\d+)*$/', $data->jamke)) {
    print_error(
        'Isian "Jam Pelajaran Ke" hanya boleh angka dan koma, contoh: 2,3'
    );
}

    // ✅ Update data jika disubmit
    $record->kelas = $data->kelas;
    $record->jamke = $data->jamke;
    $record->matapelajaran = $data->matapelajaran;
    $record->materi = $data->materi;
    $record->aktivitas = $data->aktivitas;
    $record->absen = $data->absen;
    $record->absenid = $data->absenid ?? '{}';
    $record->keterangan = $data->keterangan;
    if (isset($data->tanggaldibuat)) {
    $record->timecreated = $data->tanggaldibuat;
	}
    $record->timemodified = time();
    $record->modifiedby = $USER->id;
    
    $DB->update_record('local_jurnalmengajar', $record);

// hapus data pembinaan lama
$DB->delete_records(
    'local_jurnalmengajar_pembinaanmapel',
    ['jurnalid' => $id]
);

$pembinaan = json_decode(
    $data->pembinaanjson ?? '[]',
    true
);

if (!is_array($pembinaan)) {
    $pembinaan = [];
}

if (!empty($pembinaan)) {

    foreach ($pembinaan as $p) {

        $pb = new stdClass();

        $pb->jurnalid = $id;
        $pb->userid = $record->userid;

        if (empty($p['muridid'])) {
	    continue;
	}

  	$pb->muridid = (int)$p['muridid'];
        $pb->kelas = (int)$record->kelas;

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

    // ✅ Tampilkan pesan sukses
    redirect(new moodle_url('/local/jurnalmengajar/index.php'), 'Jurnal berhasil diperbarui.', 2);
}

// ✅ Tampilkan form dengan data lama (termasuk id)
$mform->set_data($record);

echo $OUTPUT->header();
echo $OUTPUT->heading('Edit Jurnal Mengajar');
$mform->display();
echo $OUTPUT->footer();
