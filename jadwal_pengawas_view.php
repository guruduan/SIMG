<?php

require('../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);
require_once(__DIR__ . '/jadwal_asesmen_lib.php');
require_once(__DIR__ . '/lib.php');

global $USER;

$namaguru_login = trim(
    fullname($USER)
);

$PAGE->set_url(new moodle_url('/local/jurnalmengajar/jadwal_pengawas_view.php'));
$PAGE->set_context($context);
$PAGE->set_title('Jadwal Pengawas');
//$PAGE->set_heading('Jadwal Pengawas');

/*
=====================================================
FILTER TANGGAL
=====================================================
*/
$tanggal = optional_param('tanggal', date('Y-m-d'), PARAM_TEXT);

/*
=====================================================
LOAD JSON
=====================================================
*/
$jadwalpengawas = [];
$jsonfile = __DIR__ . '/jadwal_pengawas.json';

if (file_exists($jsonfile)) {
    $json = file_get_contents($jsonfile);
    $jadwalpengawas = json_decode($json, true);
}

/*
=====================================================
SESI
=====================================================
*/
$hari =
    strtolower(
        date(
            'l',
            strtotime($tanggal)
        )
    );

if ($hari == 'friday') {

    $mulaipukul =
        get_config(
            'local_jurnalmengajar',
            'asesmen_mulai_jumat'
        ) ?: '07:30';

} else {

    $mulaipukul =
        get_config(
            'local_jurnalmengajar',
            'asesmen_mulai'
        ) ?: '08:00';
}

$sesiasesmen =
    jurnalmengajar_generate_sesi_asesmen(
        $mulaipukul
    );

function tampil_pengawas($data, $userlogin) {

    if (empty($data) || !is_array($data)) {
        return '-';
    }

    $guru = $data['guru'] ?? '-';
    $username = $data['username'] ?? '';

    if ($username === $userlogin) {
        return '<span style="color:#dc3545;font-weight:bold;">'
            . s($guru)
            . '</span>';
    }

    return s($guru);
}

echo $OUTPUT->header();

?>

<div class="container-fluid mb-4">
    <h2>Jadwal Pengawas Asesmen</h2>

    <form method="get" id="filterform" class="form-inline my-3">
    <div class="form-group">
        <label for="tanggal" class="mr-2 font-weight-bold">Hari / Tanggal:</label>
        <input 
            type="date" 
            id="tanggal"
            name="tanggal" 
            class="form-control"
            value="<?= s($tanggal); ?>" 
            onchange="document.getElementById('filterform').submit();"
        >
    </div>
</form>

<p class="mt-2 mb-3 text-muted">
    <?= tanggal_indo(strtotime($tanggal), 'judul'); ?>
</p>

    <div class="table-responsive">
        <table class="generaltable table table-striped table-bordered table-hover">
            <thead class="thead-dark">
                <tr>
                    <th scope="col" class="text-center">No</th>
                    <th scope="col">Sesi</th>
                    <th scope="col">Waktu</th>
                    <th scope="col">Ruang 1</th>
                    <th scope="col">Ruang 2</th>
                    <th scope="col">Ruang 3</th>
                </tr>
            </thead>
            <tbody>

            <?php
            $no = 1;

            if (!empty($jadwalpengawas[$tanggal])) {
                foreach ($jadwalpengawas[$tanggal] as $sesi => $ruang) {
                    $waktu = '-';

                    if (!empty($sesiasesmen[$sesi])) {
                        $waktu_mulai = substr($sesiasesmen[$sesi]['mulai'], 0, 5);
                        $waktu_selesai = substr($sesiasesmen[$sesi]['selesai'], 0, 5);
                        $waktu = $waktu_mulai . ' s/d ' . $waktu_selesai;
                    }
                    ?>
                    <tr>
                        <td class="text-center"><?= $no++; ?></td>
                        <td><?= s($sesi); ?></td>
                        <td><?= s($waktu); ?></td>
			<td><?= tampil_pengawas($ruang['R1'] ?? null, $USER->username); ?></td>
			<td><?= tampil_pengawas($ruang['R2'] ?? null, $USER->username); ?></td>
			<td><?= tampil_pengawas($ruang['R3'] ?? null, $USER->username); ?></td>
                    </tr>
                    <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="6" class="text-center text-muted">
                        <em>Tidak ada jadwal pengawas untuk tanggal ini.</em>
                    </td>
                </tr>
                <?php
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<?php
echo $OUTPUT->footer();
