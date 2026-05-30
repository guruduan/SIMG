<?php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();

require_capability(
    'local/jurnalmengajar:view',
    $context
);

$PAGE->set_context($context);
$PAGE->set_url(
    new moodle_url(
        '/local/jurnalmengajar/export_jurnalwalikelas_form.php'
    )
);

$PAGE->set_title('Ekspor Jurnal Wali Kelas');
$PAGE->set_heading('Ekspor Jurnal Wali Kelas');

$kelasid = jurnalmengajar_get_kelas_wali(
    $USER->id
);

echo $OUTPUT->header();

if (!$kelasid) {

    echo $OUTPUT->notification(
        'Akun Anda belum ditetapkan sebagai wali kelas.',
        'warning'
    );

    echo $OUTPUT->footer();
    exit;
}

$kelasnama = get_nama_kelas($kelasid);

echo $OUTPUT->heading(
    'Ekspor Jurnal Wali Kelas'
);

echo '<div class="alert alert-info">';
echo '<strong>Kelas:</strong> ' . s($kelasnama);
echo '</div>';

$bulan = [
    '01' => 'Januari',
    '02' => 'Februari',
    '03' => 'Maret',
    '04' => 'April',
    '05' => 'Mei',
    '06' => 'Juni',
    '07' => 'Juli',
    '08' => 'Agustus',
    '09' => 'September',
    '10' => 'Oktober',
    '11' => 'November',
    '12' => 'Desember',
];

$tahunini = date('Y');

echo '<div style="margin-top:10px;">';

echo '<form method="get" action="export_jurnalwalikelas_xlsx.php">';

echo '<label for="bulan">Pilih Bulan: </label>';

echo '<select name="bulan" id="bulan">';

$bulansekarang = date('m');

foreach ($bulan as $num => $nama) {

    $selected =
        ($num == $bulansekarang)
        ? 'selected'
        : '';

    echo '<option value="' .
        s($num) .
        '" ' .
        $selected .
        '>' .
        s($nama) .
        '</option>';
}

echo '</select> ';

echo '<label for="tahun">Tahun: </label>';

echo '<select name="tahun" id="tahun">';

for (
    $t = $tahunini - 2;
    $t <= $tahunini + 3;
    $t++
) {

    $selected =
        ($t == $tahunini)
        ? 'selected'
        : '';

    echo '<option value="' .
        $t .
        '" ' .
        $selected .
        '>' .
        $t .
        '</option>';
}

echo '</select> ';

echo '<button
        type="submit"
        class="btn btn-primary"
      >
        Ekspor ke File Excel
      </button>';

echo '</form>';

echo '</div>';

echo $OUTPUT->footer();
