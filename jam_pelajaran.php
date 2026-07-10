<?php
require('../../config.php');
require_once(__DIR__.'/jam_pelajaran_lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$context = context_system::instance();

$PAGE->set_context($context);

$PAGE->set_url('/local/jurnalmengajar/jam_pelajaran.php');

$PAGE->set_pagelayout('standard');

$PAGE->set_title('Jam Pelajaran');

$PAGE->set_heading('Jam Pelajaran (JP)');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
$data = [

    'mode_aktif' => $_POST['mode_aktif'],

    // ==========================
    // MODE NORMAL
    // ==========================
    'normal' => [

        // Senin - Kamis
        'senin_kamis' => [

            'jumlah_jam' => $_POST['normal_sk_jumlah_jam'],
            'jam_mulai'  => $_POST['normal_sk_jam_mulai'],

            'durasi_jam' => $_POST['normal_sk_durasi_jam'],

            'ist1_setelah' => $_POST['normal_sk_ist1_setelah'],
            'ist1_durasi'  => $_POST['normal_sk_ist1_durasi'],

            'ist2_setelah' => $_POST['normal_sk_ist2_setelah'],
            'ist2_durasi'  => $_POST['normal_sk_ist2_durasi']

        ],

        // Jumat
        'jumat' => [

            'jumlah_jam' => $_POST['normal_jumat_jumlah_jam'],
            'jam_mulai'  => $_POST['normal_jumat_jam_mulai'],

            'durasi_jam' => $_POST['normal_jumat_durasi_jam'],

            'ist1_setelah' => $_POST['normal_jumat_ist1_setelah'],
            'ist1_durasi'  => $_POST['normal_jumat_ist1_durasi']

        ]

    ],

    // ==========================
    // MODE RAPAT
    // ==========================
    'rapat' => [

        // hanya Senin-Kamis
        'senin_kamis' => [

            'jumlah_jam' => $_POST['rapat_jumlah_jam'],
            'jam_mulai'  => $_POST['rapat_jam_mulai'],

            'durasi_jam' => $_POST['rapat_durasi_jam'],

            'ist1_setelah' => $_POST['rapat_ist1_setelah'],
            'ist1_durasi'  => $_POST['rapat_ist1_durasi'],

            'ist2_setelah' => $_POST['rapat_ist2_setelah'],
            'ist2_durasi'  => $_POST['rapat_ist2_durasi']

        ]

    ]

];
    jurnalmengajar_save_config_jam($data);
    redirect($PAGE->url, 'Pengaturan berhasil disimpan.', 1);
}

$config = jurnalmengajar_get_config_jam();

/*
=====================================================
FUNGSI PREVIEW
=====================================================
*/

function tampilkan_preview_jam($judul, $jam) {

    if (empty($jam)) {
        return;
    }

    echo "<h3>$judul</h3>";

    echo "<table class='generaltable'>";

    echo "<tr>";
    echo "<th>Jam</th>";
    echo "<th>Mulai</th>";
    echo "<th>Selesai</th>";
    echo "</tr>";

    foreach ($jam as $j => $w) {

        echo "<tr>";

        echo "<td>$j</td>";
        echo "<td>{$w['mulai']}</td>";
        echo "<td>{$w['selesai']}</td>";

        echo "</tr>";

        if (!empty($w['istirahat_setelah'])) {

            echo "<tr style='background:#ffeeba;font-weight:bold;'>";

            echo "<td colspan='3'>";

            echo "Istirahat {$w['istirahat_setelah']} menit";

            echo "</td>";

            echo "</tr>";
        }
    }

    echo "</table>";
}


echo $OUTPUT->header();

echo "<form method='post'>";
echo "<input type='hidden' name='sesskey' value='".sesskey()."'>";

echo "<table class='generaltable'>";

/*
=====================================================
UMUM
=====================================================
*/

echo "<tr>";
echo "<td>Mode Aktif</td>";
echo "<td>";

echo "<select name='mode_aktif'>";

$selected_normal = '';
$selected_rapat = '';

if (($config['mode_aktif'] ?? 'normal') == 'normal') {
    $selected_normal = 'selected';
} else {
    $selected_rapat = 'selected';
}

echo "<option value='normal' $selected_normal>Normal</option>";

echo "<option value='rapat' $selected_rapat>Rapat Guru</option>";

echo "</select>";

echo "</td>";
echo "</tr>";

echo "<div class='alert alert-info'>";
echo "Mode Rapat hanya digunakan untuk hari Senin–Kamis. Hari Jumat tetap menggunakan konfigurasi Normal Jumat.";
echo "</div>";

/*
=====================================================
NORMAL SENIN-KAMIS
=====================================================
*/

echo "<tr style='background:#d4edda; font-weight:bold;'>";
echo "<td colspan='2'>NORMAL SENIN-KAMIS</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Jumlah JP</td>";
echo "<td>";
echo "<input type='number' name='normal_sk_jumlah_jam' value='".($config['normal']['senin_kamis']['jumlah_jam'] ?? '11')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>JP pertama mulai pukul</td>";
echo "<td>";
echo "<input type='time' name='normal_sk_jam_mulai' value='".($config['normal']['senin_kamis']['jam_mulai'] ?? '07:30')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Durasi JP</td>";
echo "<td>";
echo "<input type='number' name='normal_sk_durasi_jam' value='".($config['normal']['senin_kamis']['durasi_jam'] ?? '45')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Istirahat pertama setelah JP ke</td>";
echo "<td>";
echo "<input type='number' name='normal_sk_ist1_setelah' value='".($config['normal']['senin_kamis']['ist1_setelah'] ?? '4')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Durasi istirahat pertama</td>";
echo "<td>";
echo "<input type='number' name='normal_sk_ist1_durasi' value='".($config['normal']['senin_kamis']['ist1_durasi'] ?? '20')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Istirahat kedua setelah JP ke</td>";
echo "<td>";
echo "<input type='number' name='normal_sk_ist2_setelah' value='".($config['normal']['senin_kamis']['ist2_setelah'] ?? '7')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Durasi istirahat kedua</td>";
echo "<td>";
echo "<input type='number' name='normal_sk_ist2_durasi' value='".($config['normal']['senin_kamis']['ist2_durasi'] ?? '30')."'>";
echo "</td>";
echo "</tr>";

/*
=====================================================
NORMAL JUMAT
=====================================================
*/

echo "<tr style='background:#d1ecf1; font-weight:bold;'>";
echo "<td colspan='2'>NORMAL JUMAT</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Jumlah JP</td>";
echo "<td>";
echo "<input type='number' name='normal_jumat_jumlah_jam' value='".($config['normal']['jumat']['jumlah_jam'] ?? '6')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>JP pertama mulai pukul</td>";
echo "<td>";
echo "<input type='time' name='normal_jumat_jam_mulai' value='".($config['normal']['jumat']['jam_mulai'] ?? '07:30')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Durasi JP</td>";
echo "<td>";
echo "<input type='number' name='normal_jumat_durasi_jam' value='".($config['normal']['jumat']['durasi_jam'] ?? '35')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Istirahat setelah JP ke</td>";
echo "<td>";
echo "<input type='number' name='normal_jumat_ist1_setelah' value='".($config['normal']['jumat']['ist1_setelah'] ?? '4')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Durasi istirahat</td>";
echo "<td>";
echo "<input type='number' name='normal_jumat_ist1_durasi' value='".($config['normal']['jumat']['ist1_durasi'] ?? '30')."'>";
echo "</td>";
echo "</tr>";

/*
=====================================================
RAPAT SENIN-KAMIS
=====================================================
*/

echo "<tr style='background:#fff3cd; font-weight:bold;'>";
echo "<td colspan='2'>RAPAT SENIN-KAMIS</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Jumlah JP</td>";
echo "<td>";
echo "<input type='number' name='rapat_jumlah_jam' value='".($config['rapat']['senin_kamis']['jumlah_jam'] ?? '8')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>JP pertama mulai pukul</td>";
echo "<td>";
echo "<input type='time' name='rapat_jam_mulai' value='".($config['rapat']['senin_kamis']['jam_mulai'] ?? '08:00')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Durasi JP</td>";
echo "<td>";
echo "<input type='number' name='rapat_durasi_jam' value='".($config['rapat']['senin_kamis']['durasi_jam'] ?? '30')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Istirahat pertama setelah JP ke</td>";
echo "<td>";
echo "<input type='number' name='rapat_ist1_setelah' value='".($config['rapat']['senin_kamis']['ist1_setelah'] ?? '4')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Durasi istirahat pertama</td>";
echo "<td>";
echo "<input type='number' name='rapat_ist1_durasi' value='".($config['rapat']['senin_kamis']['ist1_durasi'] ?? '15')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Istirahat kedua setelah JP ke</td>";
echo "<td>";
echo "<input type='number' name='rapat_ist2_setelah' value='".($config['rapat']['senin_kamis']['ist2_setelah'] ?? '0')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Durasi istirahat kedua</td>";
echo "<td>";
echo "<input type='number' name='rapat_ist2_durasi' value='".($config['rapat']['senin_kamis']['ist2_durasi'] ?? '0')."'>";
echo "</td>";
echo "</tr>";

echo "</table>";

echo "<br>";

echo "<input type='submit' value='Simpan & Generate' class='btn btn-primary'>";

echo "</form>";

/*
=====================================================
HASIL GENERATE JAM
=====================================================
*/

if (($config['mode_aktif'] ?? 'normal') == 'normal') {

tampilkan_preview_jam(
    'Preview Normal Senin-Kamis',
    jurnalmengajar_generate_jam_preview(
        $config['normal']['senin_kamis'] ?? []
    )
);

    tampilkan_preview_jam(
        'Preview Normal Jumat',
        jurnalmengajar_generate_jam_preview(
            $config['normal']['jumat'] ?? []
        )
    );

} else {

    tampilkan_preview_jam(
        'Preview Rapat Senin-Kamis',
        jurnalmengajar_generate_jam_preview(
            $config['rapat']['senin_kamis'] ?? []
        )
    );

    tampilkan_preview_jam(
        'Preview Normal Jumat',
        jurnalmengajar_generate_jam_preview(
            $config['normal']['jumat'] ?? []
        )
    );
}

echo $OUTPUT->footer();
?>
