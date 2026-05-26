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

    $data = [

        'jumlah_jam' => $_POST['jumlah_jam'],

        'jam_mulai' => $_POST['jam_mulai'],

        'mode_aktif' => $_POST['mode_aktif'],

        'mode' => [

            'normal' => [

                'durasi_jam' => $_POST['durasi_normal'],

                'ist1_setelah' => $_POST['normal_ist1_setelah'],
                'ist1_durasi' => $_POST['normal_ist1_durasi'],

                'ist2_setelah' => $_POST['normal_ist2_setelah'],
                'ist2_durasi' => $_POST['normal_ist2_durasi']

            ],

            'rapat' => [

                'durasi_jam' => $_POST['durasi_rapat'],

                'ist1_setelah' => $_POST['rapat_ist1_setelah'],
                'ist1_durasi' => $_POST['rapat_ist1_durasi'],

                'ist2_setelah' => $_POST['rapat_ist2_setelah'],
                'ist2_durasi' => $_POST['rapat_ist2_durasi']

            ]

        ]

    ];

    jurnalmengajar_save_config_jam($data);
}

$config = jurnalmengajar_get_config_jam();

$jam = jurnalmengajar_generate_jam();

echo $OUTPUT->header();

echo "<form method='post'>";

echo "<table class='generaltable'>";

/*
=====================================================
UMUM
=====================================================
*/

echo "<tr>";
echo "<td>Jumlah JP</td>";
echo "<td>";
echo "<input type='number' name='jumlah_jam' value='".($config['jumlah_jam'] ?? '')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>JP pertama mulai pukul</td>";
echo "<td>";
echo "<input type='time' name='jam_mulai' value='".($config['jam_mulai'] ?? '')."'>";
echo "</td>";
echo "</tr>";

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

/*
=====================================================
MODE NORMAL
=====================================================
*/

echo "<tr style='background:#d4edda; font-weight:bold;'>";
echo "<td colspan='2'>MODE NORMAL</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Durasi JP Mode Normal</td>";
echo "<td>";
echo "<input type='number' name='durasi_normal' value='".($config['mode']['normal']['durasi_jam'] ?? '45')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Istirahat pertama setelah JP ke (Mode Normal)</td>";
echo "<td>";
echo "<input type='number' name='normal_ist1_setelah' value='".($config['mode']['normal']['ist1_setelah'] ?? '4')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Durasi istirahat pertama Mode Normal</td>";
echo "<td>";
echo "<input type='number' name='normal_ist1_durasi' value='".($config['mode']['normal']['ist1_durasi'] ?? '15')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Istirahat kedua setelah JP ke (Mode Normal)</td>";
echo "<td>";
echo "<input type='number' name='normal_ist2_setelah' value='".($config['mode']['normal']['ist2_setelah'] ?? '7')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Durasi istirahat kedua Mode Normal</td>";
echo "<td>";
echo "<input type='number' name='normal_ist2_durasi' value='".($config['mode']['normal']['ist2_durasi'] ?? '30')."'>";
echo "</td>";
echo "</tr>";

/*
=====================================================
MODE RAPAT
=====================================================
*/

echo "<tr style='background:#fff3cd; font-weight:bold;'>";
echo "<td colspan='2'>MODE RAPAT GURU</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Durasi JP Mode Rapat</td>";
echo "<td>";
echo "<input type='number' name='durasi_rapat' value='".($config['mode']['rapat']['durasi_jam'] ?? '30')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Istirahat pertama setelah JP ke (Mode Rapat)</td>";
echo "<td>";
echo "<input type='number' name='rapat_ist1_setelah' value='".($config['mode']['rapat']['ist1_setelah'] ?? '6')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Durasi istirahat pertama Mode Rapat</td>";
echo "<td>";
echo "<input type='number' name='rapat_ist1_durasi' value='".($config['mode']['rapat']['ist1_durasi'] ?? '15')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Istirahat kedua setelah JP ke (Mode Rapat)</td>";
echo "<td>";
echo "<input type='number' name='rapat_ist2_setelah' value='".($config['mode']['rapat']['ist2_setelah'] ?? '0')."'>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Durasi istirahat kedua Mode Rapat</td>";
echo "<td>";
echo "<input type='number' name='rapat_ist2_durasi' value='".($config['mode']['rapat']['ist2_durasi'] ?? '0')."'>";
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

if (!empty($jam)) {

    echo "<h3>Hasil Jam Pelajaran</h3>";

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

            echo "<tr style='background:#ffeeba; font-weight:bold;'>";

            echo "<td colspan='3'>";

            echo "Istirahat {$w['istirahat_setelah']} menit";

            echo "</td>";

            echo "</tr>";
        }
    }

    echo "</table>";
}

echo $OUTPUT->footer();
?>
