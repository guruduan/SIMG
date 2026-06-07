<?php

require('../../config.php');

require_login();

require_capability(
    'moodle/site:config',
    context_system::instance()
);

$PAGE->set_url(
    new moodle_url(
        '/local/jurnalmengajar/jadwal_pengawas.php'
    )
);

$PAGE->set_context(
    context_system::instance()
);

$PAGE->set_title('Import Jadwal Pengawas');

$PAGE->set_heading('Import Jadwal Pengawas');

echo $OUTPUT->header();

/*
=====================================================
UPLOAD CSV
=====================================================
*/

if (
    !empty($_FILES['csv']['tmp_name'])
) {

    $tmp =
        $_FILES['csv']['tmp_name'];

    $handle =
        fopen($tmp, 'r');

    $data = [];

    $header = true;

    while (($row = fgetcsv($handle, 1000, ',')) !== false) {

        /*
        =============================================
        SKIP HEADER
        =============================================
        */

        if ($header) {

            $header = false;
            continue;
        }

        /*
        =============================================
        VALIDASI
        =============================================
        */

        if (count($row) < 6) {
            continue;
        }

        $hari =
            trim($row[0]);

        $tanggal =
            trim($row[1]);

        $sesi =
            trim($row[2]);

        $ruang =
            trim($row[3]);

        $guru =
            trim($row[4]);
        
        $username =
	    trim($row[5]);

        /*
        =============================================
        SIMPAN
        =============================================
        */

        if (!isset($data[$tanggal])) {
            $data[$tanggal] = [];
        }

        if (!isset($data[$tanggal][$sesi])) {
            $data[$tanggal][$sesi] = [];
        }

        $data[$tanggal][$sesi][$ruang] = [
    'guru'     => $guru,
    'username' => $username
	];
    }

    fclose($handle);

    /*
    =============================================
    SAVE JSON
    =============================================
    */

    $jsonfile =
        __DIR__.'/jadwal_pengawas.json';

    file_put_contents(
        $jsonfile,
        json_encode(
            $data,
            JSON_PRETTY_PRINT
            |
            JSON_UNESCAPED_UNICODE
        )
    );

    echo $OUTPUT->notification(
        'Import berhasil',
        'notifysuccess'
    );
}

?>

<h2>
Import CSV Jadwal Pengawas
</h2>

<form
    method="post"
    enctype="multipart/form-data"
>

    <input
        type="file"
        name="csv"
        accept=".csv"
        required
    >

    <br><br>

    <button
        type="submit"
        class="btn btn-primary"
    >
        Import CSV
    </button>

</form>

<hr>

<p>

Format CSV:

</p>

<pre>
Hari;Tanggal;Sesi;Ruang;Guru;Username
Senin;2026-06-02;1;R1;Guru A;gurua
Senin;2026-06-02;1;R2;Guru B;gurub
</pre>

<?php

echo $OUTPUT->footer();
