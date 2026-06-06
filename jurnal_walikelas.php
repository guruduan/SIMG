<?php

require_once('../../config.php');
require_once(__DIR__.'/lib.php');

require_login();

global $DB, $USER, $PAGE, $OUTPUT;

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/jurnal_walikelas.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Jurnal Wali Kelas');
$PAGE->set_heading('Jurnal Wali Kelas');

$kelasid = jurnalmengajar_get_kelas_wali($USER->id);

if (!$kelasid) {

    echo $OUTPUT->header();

    echo $OUTPUT->notification(
        'Akun Anda belum ditetapkan sebagai wali kelas. Silakan hubungi administrator atau operator untuk melakukan pengaturan wali kelas.',
        'warning'
    );

    echo html_writer::link(
        new moodle_url('/my/'),
        'Kembali ke Dashboard',
        ['class' => 'btn btn-primary']
    );

    echo $OUTPUT->footer();
    exit;
}

$kelasnama = get_nama_kelas($kelasid);

// Ambil siswa kelas wali.
$siswa = $DB->get_records_sql("
    SELECT u.id, u.lastname
    FROM {cohort_members} cm
    JOIN {user} u ON u.id = cm.userid
    WHERE cm.cohortid = ?
    ORDER BY u.lastname
", [$kelasid]);

// Simpan data.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && confirm_sesskey()
) {

    $record = new stdClass();

    $record->userid = $USER->id;
    $record->kelas = $kelasid;
    $record->jenis = required_param(
        'jenis',
        PARAM_ALPHA
    );

    $record->muridid = 0;
    $record->topik = null;
    $record->tindaklanjut = null;
    $record->uraian = null;

    if ($record->jenis === 'umum') {

        $record->uraian = trim(
            required_param(
                'uraian',
                PARAM_TEXT
            )
        );

        if ($record->uraian === '') {
            throw new moodle_exception(
                'Uraian kegiatan wajib diisi.'
            );
        }

    } else {

        $record->muridid = required_param(
            'muridid',
            PARAM_INT
        );

        if (empty($record->muridid)) {
            throw new moodle_exception(
                'Pilih murid yang dibina.'
            );
        }

        $record->topik = trim(
            required_param(
                'topik',
                PARAM_TEXT
            )
        );

        if ($record->topik === '') {
            throw new moodle_exception(
                'Topik/permasalahan wajib diisi.'
            );
        }

        $record->tindaklanjut = trim(
            required_param(
                'tindaklanjut',
                PARAM_TEXT
            )
        );

        if ($record->tindaklanjut === '') {
            throw new moodle_exception(
                'Tindak lanjut/solusi wajib diisi.'
            );
        }
    }

    $record->timecreated = time();
    $record->timemodified = time();

    $DB->insert_record(
        'local_jurnalwalikelas',
        $record
    );

    redirect(
        new moodle_url(
            '/local/jurnalmengajar/jurnal_walikelas.php'
        ),
        'Jurnal berhasil disimpan.',
        2
    );
}

echo $OUTPUT->header();
?>

<div class="alert alert-info">
    <strong>Kelas:</strong> <?php echo s($kelasnama); ?>
</div>

<div class="alert alert-info mb-3">

    <strong>Petunjuk Pengisian</strong>

    <ul class="mb-0 mt-2">
        <li>
            <strong><em>Umum</em></strong>
            digunakan untuk kegiatan wali kelas yang bersifat umum dan berlaku untuk seluruh murid.
        </li>

        <li>
            <strong><em>Pembinaan Murid</em></strong>
            digunakan untuk mencatat pembinaan, permasalahan, dan tindak lanjut terhadap murid tertentu.
        </li>
    </ul>

</div>

<form method="post">

    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

    <div class="form-group">

        <label>
            <input
                type="radio"
                name="jenis"
                value="umum"
                checked
                onchange="toggleJenis()"
            >
            Umum
        </label>

        <label class="ml-3">
            <input
                type="radio"
                name="jenis"
                value="pembinaan"
                onchange="toggleJenis()"
            >
            Pembinaan Murid
        </label>

    </div>

    <div id="blok-umum">

        <div class="form-group">

            <label>
                Uraian Kegiatan
                <span class="text-danger">*</span>
            </label>

            <textarea
                name="uraian"
                rows="5"
                class="form-control"
                required
            ></textarea>

        </div>

    </div>

    <div id="blok-pembinaan" style="display:none;">

        <div class="form-group">

            <label>
                Murid
                <span class="text-danger">*</span>
            </label>

            <select
                name="muridid"
                class="form-control"
            >

                <option value="">Pilih Murid</option>

                <?php foreach ($siswa as $s): ?>

                    <option value="<?php echo $s->id; ?>">
                        <?php
                        echo format_nama_siswa(
                            $s->lastname
                        );
                        ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div class="form-group">

            <label>
                Topik / Permasalahan
                <span class="text-danger">*</span>
            </label>

            <textarea
                name="topik"
                rows="4"
                class="form-control"
            ></textarea>

        </div>

        <div class="form-group">

            <label>
                Tindak Lanjut / Solusi
                <span class="text-danger">*</span>
            </label>

            <textarea
                name="tindaklanjut"
                rows="4"
                class="form-control"
            ></textarea>

        </div>

    </div>

<button
    type="submit"
    class="btn btn-primary mt-2"
>
    Simpan
</button>

</form>

<script>
function toggleJenis() {

    const jenis =
        document.querySelector(
            'input[name="jenis"]:checked'
        ).value;

    const uraian =
        document.querySelector(
            'textarea[name="uraian"]'
        );

    const murid =
        document.querySelector(
            'select[name="muridid"]'
        );

    const topik =
        document.querySelector(
            'textarea[name="topik"]'
        );

    const tindaklanjut =
        document.querySelector(
            'textarea[name="tindaklanjut"]'
        );

    if (jenis === 'umum') {

        document.getElementById(
            'blok-umum'
        ).style.display = 'block';

        document.getElementById(
            'blok-pembinaan'
        ).style.display = 'none';

        uraian.required = true;
        murid.required = false;
        topik.required = false;
        tindaklanjut.required = false;

    } else {

        document.getElementById(
            'blok-umum'
        ).style.display = 'none';

        document.getElementById(
            'blok-pembinaan'
        ).style.display = 'block';

        uraian.required = false;
        murid.required = true;
        topik.required = true;
        tindaklanjut.required = true;
    }
}

document.addEventListener(
    'DOMContentLoaded',
    toggleJenis
);
</script>

<?php
echo $OUTPUT->footer();
