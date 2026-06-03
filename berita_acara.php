<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__.'/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

global $DB, $USER;

$PAGE->set_context($context);
$PAGE->set_url(
    new moodle_url('/local/jurnalmengajar/berita_acara.php')
);
$PAGE->set_title('Berita Acara Asesmen');
$PAGE->set_heading('Berita Acara Asesmen');

$tanggal    = optional_param('tanggal', 0, PARAM_INT);
if (
    optional_param('simpan', 0, PARAM_INT)
    && confirm_sesskey()
) {

    $asesmenid   = required_param('asesmenid', PARAM_INT);
    $tanggal     = required_param('tanggal', PARAM_INT);
    $sesiaktual  = required_param('sesiaktual', PARAM_INT);

    $statuslist = optional_param_array(
        'status',
        [],
        PARAM_ALPHA
    );

    $catatan = optional_param(
    'catatan',
    '',
    PARAM_TEXT
    );
    
    $statusvalid = ['H','S','I','A','D'];

foreach ($statuslist as $userid => $status) {

    if (!in_array($status, $statusvalid)) {
        $status = 'H';
    }

    $sudahada = $DB->get_record(
        'local_jurnalmengajar_asesmen_detail',
        [
            'asesmenid'  => $asesmenid,
            'userid'     => $userid,
            'tanggal'    => $tanggal,
            'sesiaktual' => $sesiaktual
        ]
    );

    if ($sudahada) {

        $sudahada->status = $status;
        $sudahada->pengawasid = $USER->id;

        $DB->update_record(
            'local_jurnalmengajar_asesmen_detail',
            $sudahada
        );

    } else {

        $detail = new stdClass();

        $detail->asesmenid  = $asesmenid;
        $detail->userid     = $userid;
        $detail->pengawasid = $USER->id;

        $detail->tanggal    = $tanggal;
        $detail->sesiaktual = $sesiaktual;

        $detail->status     = $status;

        $detail->keterangan = '';

        $detail->timecreated = time();

        $DB->insert_record(
            'local_jurnalmengajar_asesmen_detail',
            $detail
        );
    }
}

/*
=========================================
SIMPAN CATATAN BERITA ACARA
=========================================
*/

$catatanlama = $DB->get_record(
    'local_jurnalmengajar_asesmen_catatan',
    [
        'asesmenid'  => $asesmenid,
        'tanggal'    => $tanggal,
        'sesiaktual' => $sesiaktual
    ]
);

if ($catatanlama) {

    $catatanlama->catatan = $catatan;

    $catatanlama->pengawasid = $USER->id;

    $catatanlama->timemodified = time();

    $DB->update_record(
        'local_jurnalmengajar_asesmen_catatan',
        $catatanlama
    );

} else {

    $rec = new stdClass();

    $rec->asesmenid = $asesmenid;

    $rec->tanggal = $tanggal;

    $rec->sesiaktual = $sesiaktual;

    $rec->pengawasid = $USER->id;

    $rec->catatan = $catatan;

    $rec->timecreated = time();

    $rec->timemodified = time();

    $DB->insert_record(
        'local_jurnalmengajar_asesmen_catatan',
        $rec
    );
}

redirect(
    new moodle_url(
        '/local/jurnalmengajar/berita_acara.php'
    ),
    'Berita acara berhasil disimpan'
);

} // <-- PENUTUP if (optional_param('simpan'...))

$sesiaktual = optional_param('sesiaktual', 0, PARAM_INT);
$ruang      = optional_param('ruang', '', PARAM_TEXT);

echo $OUTPUT->header();

echo html_writer::tag(
    'h3',
    'Berita Acara Asesmen'
);

/*
==================================================
AMBIL PILIHAN FILTER
==================================================
*/

$tanggals = $DB->get_records_sql("
    SELECT DISTINCT tanggal
    FROM {local_jurnalmengajar_asesmen_jadwal}
    ORDER BY tanggal
");

$sesis = $DB->get_records_sql("
    SELECT DISTINCT sesiaktual
    FROM {local_jurnalmengajar_asesmen_jadwal}
    ORDER BY sesiaktual
");

$ruangs = $DB->get_records_sql("
    SELECT DISTINCT ruang
    FROM {local_jurnalmengajar_asesmen}
    ORDER BY ruang
");

/*
==================================================
FORM FILTER
==================================================
*/
echo '<form id="filterform" method="get">';

echo '<table class="table table-bordered" style="max-width:700px;">';

echo '<tr>';
echo '<td width="180">Tanggal</td>';
echo '<td>';

echo '<select
        name="tanggal"
        id="tanggal"
        class="form-control">';

echo '<option value="">-- Pilih Tanggal --</option>';

foreach ($tanggals as $t) {

    $selected = '';

    if ($tanggal == $t->tanggal) {
        $selected = 'selected';
    }

    echo '<option value="' . $t->tanggal . '" ' . $selected . '>';

    echo tanggal_indo($t->tanggal, 'tanggal');

    echo '</option>';
}

echo '</select>';

echo '</td>';
echo '</tr>';

echo '<tr>';
echo '<td>Sesi Aktual</td>';
echo '<td>';

echo '<select
        name="sesiaktual"
        id="sesiaktual"
        class="form-control">';

echo '<option value="">-- Pilih Sesi --</option>';

foreach ($sesis as $s) {

    $selected = '';

    if ($sesiaktual == $s->sesiaktual) {
        $selected = 'selected';
    }

    echo '<option value="' .
        $s->sesiaktual .
        '" ' .
        $selected .
        '>';

    echo 'Sesi ' . $s->sesiaktual;

    echo '</option>';
}

echo '</select>';

echo '</td>';
echo '</tr>';

echo '<tr>';
echo '<td>Ruang</td>';
echo '<td>';

echo '<select
        name="ruang"
        id="ruang"
        class="form-control">';

echo '<option value="">-- Pilih Ruang --</option>';

foreach ($ruangs as $r) {

    $selected = '';

    if ($ruang == $r->ruang) {
        $selected = 'selected';
    }

    echo '<option value="' .
        s($r->ruang) .
        '" ' .
        $selected .
        '>';

    echo s($r->ruang);

    echo '</option>';
}

echo '</select>';

echo '</td>';
echo '</tr>';

echo '</table>';

echo '</form>';

echo '<hr>';

/*
==================================================
TAMPILKAN PESERTA
==================================================
*/

if (!empty($tanggal)
    && !empty($sesiaktual)
    && !empty($ruang)) {

    $sql = "
        SELECT a.*
        FROM {local_jurnalmengajar_asesmen_jadwal} j
        JOIN {local_jurnalmengajar_asesmen} a
          ON a.id = j.asesmenid
        WHERE j.tanggal = :tanggal
          AND j.sesiaktual = :sesi
          AND a.ruang = :ruang
    ";

    $asesmen = $DB->get_record_sql(
        $sql,
        [
            'tanggal' => $tanggal,
            'sesi'    => $sesiaktual,
            'ruang'   => $ruang
        ]
    );

    if (!$asesmen) {

        echo $OUTPUT->notification(
            'Data asesmen tidak ditemukan',
            'notifyproblem'
        );

    } else {

        echo html_writer::tag(
            'h4',
            $asesmen->namaasesmen
        );

        echo '<p>';
        echo '<b>Tanggal:</b> ' .
	    tanggal_indo($tanggal, 'judul');
        echo '<br>';

        echo '<b>Sesi:</b> ' .
            $sesiaktual;
        echo '<br>';

	echo '<b>Ruang:</b> ' .
	    s($ruang);
	echo '<br>';

	echo '<b>Pengawas:</b> ' .
	    s($USER->lastname);
        echo '</p>';

        $sql = "
            SELECT
                p.*,
                u.lastname,
                c.name AS kelas
            FROM
                {local_jurnalmengajar_asesmen_peserta} p
            JOIN
                {user} u
                    ON u.id = p.userid
            LEFT JOIN
                {cohort} c
                    ON c.id = p.kelasid
            WHERE
                p.asesmenid = :asesmenid
            ORDER BY
                p.nomeja
        ";

        $peserta = $DB->get_records_sql(
            $sql,
            [
                'asesmenid' => $asesmen->id
            ]
        );
echo '<form method="post">';

echo '<input
        type="hidden"
        name="tanggal"
        value="' . $tanggal . '">';

echo '<input
        type="hidden"
        name="sesiaktual"
        value="' . $sesiaktual . '">';

echo '<input
        type="hidden"
        name="ruang"
        value="' . s($ruang) . '">';

echo '<input
        type="hidden"
        name="asesmenid"
        value="' . $asesmen->id . '">';

echo '<input
        type="hidden"
        name="sesskey"
        value="' . sesskey() . '">';
        echo '<table class="table table-striped table-bordered">';

        echo '<tr>';
	echo '<th>No</th>';
	echo '<th>Nama</th>';
	echo '<th>Kelas</th>';
	echo '<th>Meja</th>';
	echo '<th>Status</th>';
	echo '</tr>';

        $no = 1;
	$statuslama = $DB->get_records_menu(
    'local_jurnalmengajar_asesmen_detail',
    [
        'asesmenid'  => $asesmen->id,
        'tanggal'    => $tanggal,
        'sesiaktual' => $sesiaktual
    ],
    '',
    'userid,status'
	);
        foreach ($peserta as $p) {

            echo '<tr>';

            echo '<td>' . $no++ . '</td>';

            echo '<td>' .
		    s(
		        ucwords(
		            strtolower(
	                $p->lastname
        	    )
	        )
    	) .
    	'</td>';

            echo '<td>' .
                s($p->kelas) .
                '</td>';

            echo '<td>' . $p->nomeja . '</td>';

echo '<td>';

$currentstatus = $statuslama[$p->userid] ?? 'H';

echo '<select
        name="status[' . $p->userid . ']"
        class="form-control status-select">';

echo '<option value="H" ' . ($currentstatus == 'H' ? 'selected' : '') . '>Hadir</option>';

echo '<option value="S" ' . ($currentstatus == 'S' ? 'selected' : '') . '>Sakit</option>';

echo '<option value="I" ' . ($currentstatus == 'I' ? 'selected' : '') . '>Izin</option>';

echo '<option value="A" ' . ($currentstatus == 'A' ? 'selected' : '') . '>Alpa</option>';

echo '<option value="D" ' . ($currentstatus == 'D' ? 'selected' : '') . '>Dispensasi</option>';

echo '</select>';

echo '</td>';

            echo '</tr>';
        }

        echo '</table>';

echo '<br>';
/*
=========================================
REKAP KEHADIRAN
=========================================
*/

$rekap = [
    'H' => 0,
    'S' => 0,
    'I' => 0,
    'A' => 0,
    'D' => 0
];

foreach ($peserta as $p) {

    $st = $statuslama[$p->userid] ?? 'H';

    if (isset($rekap[$st])) {
        $rekap[$st]++;
    }
}

echo '<div class="alert alert-info">';

echo '<b>Rekap Kehadiran</b><br>';

echo 'Jumlah Peserta : <b id="jmlpeserta">' . count($peserta) . '</b><br>';

echo 'Hadir : <b id="rekap_h">' . $rekap['H'] . '</b><br>';

echo 'Sakit : <b id="rekap_s">' . $rekap['S'] . '</b><br>';

echo 'Izin : <b id="rekap_i">' . $rekap['I'] . '</b><br>';

echo 'Alpa : <b id="rekap_a">' . $rekap['A'] . '</b><br>';

echo 'Dispensasi : <b id="rekap_d">' . $rekap['D'] . '</b>';

echo '</div>';
//

$isi_catatan = '';

if (!empty($asesmen)) {

    $catatanlama = $DB->get_record(
        'local_jurnalmengajar_asesmen_catatan',
        [
            'asesmenid'  => $asesmen->id,
            'tanggal'    => $tanggal,
            'sesiaktual' => $sesiaktual
        ]
    );

    if ($catatanlama) {
        $isi_catatan = $catatanlama->catatan;
    }
}
echo '<div class="form-group">';

echo '<label><b>Catatan Pelaksanaan</b></label>';

echo '<textarea
        name="catatan"
        rows="4"
        class="form-control"
        placeholder="Tuliskan catatan pelaksanaan asesmen">'
        . s($isi_catatan) .
     '</textarea>';

echo '</div>';

echo '<br>';

echo '<button
        type="submit"
        name="simpan"
        value="1"
        class="btn btn-success">
        Simpan Berita Acara
      </button>';

echo '</form>';
    }
}

/*
=========================================
AUTO SUBMIT FILTER + REKAP OTOMATIS
=========================================
*/
$PAGE->requires->js_init_code("
function updateRekap() {

    let h = 0;
    let s = 0;
    let i = 0;
    let a = 0;
    let d = 0;

    document.querySelectorAll('.status-select').forEach(function(el) {

        switch (el.value) {

            case 'H':
                h++;
                break;

            case 'S':
                s++;
                break;

            case 'I':
                i++;
                break;

            case 'A':
                a++;
                break;

            case 'D':
                d++;
                break;
        }
    });

    const rh = document.getElementById('rekap_h');
    const rs = document.getElementById('rekap_s');
    const ri = document.getElementById('rekap_i');
    const ra = document.getElementById('rekap_a');
    const rd = document.getElementById('rekap_d');

    if (rh) rh.textContent = h;
    if (rs) rs.textContent = s;
    if (ri) ri.textContent = i;
    if (ra) ra.textContent = a;
    if (rd) rd.textContent = d;
}

document.addEventListener('DOMContentLoaded', function() {

    const tanggal = document.getElementById('tanggal');
    const sesi    = document.getElementById('sesiaktual');
    const ruang   = document.getElementById('ruang');
    const form    = document.getElementById('filterform');

    if (tanggal && form) {
        tanggal.addEventListener('change', function() {
            form.submit();
        });
    }

    if (sesi && form) {
        sesi.addEventListener('change', function() {
            form.submit();
        });
    }

    if (ruang && form) {
        ruang.addEventListener('change', function() {
            form.submit();
        });
    }

    document.querySelectorAll('.status-select').forEach(function(el) {

        el.addEventListener('change', updateRekap);
    });

    updateRekap();
});
");

echo $OUTPUT->footer();
