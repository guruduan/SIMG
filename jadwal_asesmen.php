<?php

require('../../config.php');

require_login();

require_capability(
    'moodle/site:config',
    context_system::instance()
);

$PAGE->set_url(
    new moodle_url(
        '/local/jurnalmengajar/jadwal_asesmen.php'
    )
);

$PAGE->set_context(
    context_system::instance()
);

$PAGE->set_title('Jadwal Asesmen');

$PAGE->set_heading('Jadwal Asesmen');

require_once(__DIR__.'/jadwal_asesmen_lib.php');

/*
=====================================================
SIMPAN
=====================================================
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_sesskey();

    set_config(
        'asesmen_jumlah_sesi',
        (int)$_POST['jumlahsesi'],
        'local_jurnalmengajar'
    );

    set_config(
        'asesmen_durasi_sesi',
        (int)$_POST['durasi'],
        'local_jurnalmengajar'
    );

    set_config(
        'asesmen_mulai',
        trim($_POST['mulaipukul']),
        'local_jurnalmengajar'
    );

    /*
    =============================================
    KHUSUS JUMAT
    =============================================
    */

    set_config(
        'asesmen_mulai_jumat',
        trim($_POST['mulaijumat']),
        'local_jurnalmengajar'
    );
   set_config(
        'asesmen_jumlah_sesi_jumat',
        (int)$_POST['jumlahsesijumat'],
        'local_jurnalmengajar'
   );

    set_config(
        'asesmen_istirahat_setelah',
        (int)$_POST['istirahatsetelah'],
        'local_jurnalmengajar'
    );

    set_config(
        'asesmen_durasi_istirahat',
        (int)$_POST['durasiistirahat'],
        'local_jurnalmengajar'
    );

    redirect(
        new moodle_url(
            '/local/jurnalmengajar/jadwal_asesmen.php'
        ),
        'Pengaturan berhasil disimpan'
    );
}

/*
=====================================================
AMBIL CONFIG
=====================================================
*/

$jumlahsesi =
    get_config(
        'local_jurnalmengajar',
        'asesmen_jumlah_sesi'
    ) ?: 10;

$durasi =
    get_config(
        'local_jurnalmengajar',
        'asesmen_durasi_sesi'
    ) ?: 40;

$mulaipukul =
    get_config(
        'local_jurnalmengajar',
        'asesmen_mulai'
    ) ?: '08:00';

$mulaijumat =
    get_config(
        'local_jurnalmengajar',
        'asesmen_mulai_jumat'
    ) ?: '07:30';
$jumlahsesijumat =
    get_config(
        'local_jurnalmengajar',
        'asesmen_jumlah_sesi_jumat'
    ) ?: 5;
    
$istirahatsetelah =
    get_config(
        'local_jurnalmengajar',
        'asesmen_istirahat_setelah'
    ) ?: 6;

$durasiistirahat =
    get_config(
        'local_jurnalmengajar',
        'asesmen_durasi_istirahat'
    ) ?: 60;

/*
=====================================================
PREVIEW
=====================================================
*/

$sesi =
    jurnalmengajar_generate_sesi_asesmen(
        $mulaipukul
    );

$sesijumat =
    jurnalmengajar_generate_sesi_asesmen(
        $mulaijumat,
        $jumlahsesijumat
    );

echo $OUTPUT->header();

?>

<h2>
    Pengaturan Jadwal Asesmen
</h2>

<form method="post">

    <input
        type="hidden"
        name="sesskey"
        value="<?= sesskey(); ?>"
    >

    <table class="generaltable">

        <tr>

            <td>
                Jumlah sesi
            </td>

            <td>

                <input
                    type="number"
                    name="jumlahsesi"
                    value="<?= $jumlahsesi; ?>"
                >

            </td>

        </tr>

        <tr>

            <td>
                Durasi per sesi (menit)
            </td>

            <td>

                <input
                    type="number"
                    name="durasi"
                    value="<?= $durasi; ?>"
                >

            </td>

        </tr>

        <tr>

            <td>
                Mulai Senin-Kamis
            </td>

            <td>

                <input
                    type="time"
                    name="mulaipukul"
                    value="<?= s($mulaipukul); ?>"
                >

            </td>

        </tr>

        <tr>

            <td>
                Mulai Hari Jumat
            </td>

            <td>

                <input
                    type="time"
                    name="mulaijumat"
                    value="<?= s($mulaijumat); ?>"
                >

            </td>

        </tr>
<tr>

    <td>
        Jumlah sesi Jumat
    </td>

    <td>

        <input
            type="number"
            name="jumlahsesijumat"
            value="<?= $jumlahsesijumat; ?>"
        >

    </td>

</tr>
        <tr>

            <td>
                Istirahat setelah sesi
            </td>

            <td>

                <input
                    type="number"
                    name="istirahatsetelah"
                    value="<?= $istirahatsetelah; ?>"
                >

            </td>

        </tr>

        <tr>

            <td>
                Waktu istirahat (menit)
            </td>

            <td>

                <input
                    type="number"
                    name="durasiistirahat"
                    value="<?= $durasiistirahat; ?>"
                >

            </td>

        </tr>

    </table>

    <br>

    <button
        type="submit"
        class="btn btn-primary"
    >
        Simpan
    </button>

</form>

<hr>

<h3>
    Preview Senin-Kamis
</h3>

<table class="generaltable">

    <thead>

        <tr>

            <th>Sesi</th>
            <th>Mulai</th>
            <th>Selesai</th>

        </tr>

    </thead>

    <tbody>

    <?php foreach ($sesi as $s): ?>

        <tr>

            <td>
                <?= $s['sesi']; ?>
            </td>

            <td>
                <?= substr($s['mulai'],0,5); ?>
            </td>

            <td>
                <?= substr($s['selesai'],0,5); ?>
            </td>

        </tr>

    <?php endforeach; ?>

    </tbody>

</table>

<br>

<h3>
    Preview Jumat
</h3>

<table class="generaltable">

    <thead>

        <tr>

            <th>Sesi</th>
            <th>Mulai</th>
            <th>Selesai</th>

        </tr>

    </thead>

    <tbody>

    <?php foreach ($sesijumat as $s): ?>

        <tr>

            <td>
                <?= $s['sesi']; ?>
            </td>

            <td>
                <?= substr($s['mulai'],0,5); ?>
            </td>

            <td>
                <?= substr($s['selesai'],0,5); ?>
            </td>

        </tr>

    <?php endforeach; ?>

    </tbody>

</table>

<?php

echo $OUTPUT->footer();
?>
