<?php
require('../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/online_users.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Aktivitas Guru Hari Ini');
$PAGE->set_heading('Aktivitas Guru Hari Ini');

// ==========================================================
// FILE LOG
// ==========================================================

$logfile = $CFG->dataroot . '/logs/jurnalmengajar_access.log';

// Tanggal hari ini mengikuti waktu server Moodle.
$tanggalhariini = date('Y-m-d');

// ==========================================================
// BACA LOG HARI INI
// ==========================================================

$aktivitas = [];

if (file_exists($logfile) && is_readable($logfile)) {

    $lines = file(
        $logfile,
        FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
    );

    if ($lines !== false) {

        foreach ($lines as $line) {

            /*
             * Format log:
             *
             * 2026-09-11 14:00:09 |
             * userid=2 |
             * guru=Guru Noor Ridhwan, S.Pd, M.A |
             * file=index.php |
             * ip=... |
             * url=... |
             * useragent=...
             */

            $parts = array_map('trim', explode('|', $line));

            if (count($parts) < 4) {
                continue;
            }

            // ==================================================
            // HANYA AMBIL LOG HARI INI
            // ==================================================

            $waktu = $parts[0];

            if (substr($waktu, 0, 10) !== $tanggalhariini) {
                continue;
            }

            // ==================================================
            // PARSING DATA
            // ==================================================

            $data = [];

            foreach (array_slice($parts, 1) as $part) {

                $pos = strpos($part, '=');

                if ($pos === false) {
                    continue;
                }

                $key = trim(substr($part, 0, $pos));
                $value = trim(substr($part, $pos + 1));

                $data[$key] = $value;
            }

            if (empty($data['userid'])) {
                continue;
            }

            $userid = (int)$data['userid'];

            $guru = $data['guru'] ?? ('User ID ' . $userid);
            $file = $data['file'] ?? '-';
            $ip   = $data['ip'] ?? '-';

            $timestamp = strtotime($waktu);

            // ==================================================
            // KELOMPOKKAN BERDASARKAN GURU
            // ==================================================

            if (!isset($aktivitas[$userid])) {

                $aktivitas[$userid] = [
                    'userid' => $userid,
                    'guru' => $guru,
                    'ip' => $ip,
                    'akses' => []
                ];
            }

            $aktivitas[$userid]['akses'][] = [
                'waktu' => $waktu,
                'jam' => date('H:i:s', $timestamp),
                'file' => $file,
                'timestamp' => $timestamp
            ];
        }
    }
}

// ==========================================================
// URUTKAN GURU BERDASARKAN AKSES TERAKHIR
// ==========================================================

foreach ($aktivitas as &$guru) {

    usort($guru['akses'], function($a, $b) {

        return $a['timestamp'] <=> $b['timestamp'];

    });
}

unset($guru);

uasort($aktivitas, function($a, $b) {

    $lastA = end($a['akses']);
    $lastB = end($b['akses']);

    return ($lastB['timestamp'] ?? 0) <=> ($lastA['timestamp'] ?? 0);
});

// ==========================================================
// STATISTIK
// ==========================================================

$totalguru = count($aktivitas);

$totalakses = 0;
$sekarang = time();
$batasaktif = 5 * 60;
$jumlahaktif = 0;

foreach ($aktivitas as $guru) {

    $totalakses += count($guru['akses']);

    $terakhir = end($guru['akses']);

    if ($terakhir && ($sekarang - $terakhir['timestamp']) <= $batasaktif) {
        $jumlahaktif++;
    }
}

// ==========================================================
// OUTPUT
// ==========================================================

echo $OUTPUT->header();

?>

<style>

.aktivitas-wrapper {
    max-width: 1200px;
    margin: 0 auto;
}

/* ==========================================================
   STATISTIK
   ========================================================== */

.aktivitas-stat {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.stat-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px 20px;
    background: #fff;
    min-width: 180px;
    box-shadow: 0 2px 5px rgba(0,0,0,.08);
}

.stat-card .label {
    font-size: 14px;
    color: #666;
}

.stat-card .angka {
    font-size: 28px;
    font-weight: bold;
}

/* ==========================================================
   BLOK GURU
   ========================================================== */

.guru-block {
    margin-bottom: 25px;
    border: 1px solid #d9d9d9;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
}

.guru-header {
    padding: 12px 15px;
    background: #f2f2f2;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.guru-nama {
    font-size: 16px;
    font-weight: bold;
}

.guru-info {
    font-size: 13px;
    color: #666;
}

.guru-status-aktif {
    color: #198754;
    font-weight: bold;
}

.guru-status-tidak {
    color: #777;
    font-weight: bold;
}

/* ==========================================================
   TABEL
   ========================================================== */

.aktivitas-table {
    width: 100%;
    border-collapse: collapse;
}

.aktivitas-table th,
.aktivitas-table td {
    border: 1px solid #ddd;
    padding: 8px 10px;
}

.aktivitas-table th {
    background: #343a40;
    color: #fff;
    text-align: center;
}

.aktivitas-table td {
    background: #fff;
}

.aktivitas-table tr:hover td {
    background: #f7f7f7;
}

.nomor {
    width: 50px;
    text-align: center;
}

.jam {
    width: 120px;
    text-align: center;
    white-space: nowrap;
    font-family: monospace;
}

.file-page {
    font-family: monospace;
    font-size: 13px;
}

.ip {
    width: 150px;
    font-size: 12px;
}

/* ==========================================================
   HEADER
   ========================================================== */

.info-hari {
    margin-bottom: 20px;
}

.refresh-info {
    margin-top: 15px;
    color: #666;
    font-size: 13px;
}

/* ==========================================================
   MOBILE
   ========================================================== */

@media (max-width: 768px) {

    .guru-header {
        display: block;
    }

    .guru-info {
        margin-top: 5px;
    }

    .aktivitas-table {
        font-size: 12px;
    }

    .aktivitas-table th,
    .aktivitas-table td {
        padding: 6px 5px;
    }

    .ip {
        display: none;
    }

}

</style>

<div class="aktivitas-wrapper">

    <!-- =====================================================
         INFORMASI
         ===================================================== -->

    <div class="alert alert-info info-hari">

        <strong>Aktivitas Guru Hari Ini</strong><br>

        Menampilkan seluruh halaman Jurnal Mengajar
        yang diakses setiap guru pada tanggal
        <strong><?php echo s($tanggalhariini); ?></strong>.

    </div>


    <!-- =====================================================
         STATISTIK
         ===================================================== -->

    <div class="aktivitas-stat">

        <div class="stat-card">

            <div class="label">
                Guru yang Mengakses
            </div>

            <div class="angka">
                <?php echo $totalguru; ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="label">
                Total Akses Hari Ini
            </div>

            <div class="angka">
                <?php echo $totalakses; ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="label">
                Aktif ≤ 5 Menit
            </div>

            <div class="angka text-success">
                <?php echo $jumlahaktif; ?>
            </div>

        </div>

    </div>


    <!-- =====================================================
         DAFTAR AKTIVITAS GURU
         ===================================================== -->

    <?php if (empty($aktivitas)): ?>

        <div class="alert alert-warning">

            Belum ada aktivitas guru yang tercatat hari ini.

        </div>

    <?php else: ?>


        <?php foreach ($aktivitas as $guru): ?>

            <?php

            $akses = $guru['akses'];

            $terakhir = end($akses);

            $selisih = $sekarang - $terakhir['timestamp'];

            if ($selisih < 0) {
                $selisih = 0;
            }

            if ($selisih <= $batasaktif) {

                $status = '<span class="guru-status-aktif">🟢 Aktif</span>';

            } else {

                $status = '<span class="guru-status-tidak">⚪ Tidak aktif</span>';

            }

            ?>

            <div class="guru-block">

                <!-- HEADER GURU -->

                <div class="guru-header">

                    <div>

                        <div class="guru-nama">

                            <?php echo s($guru['guru']); ?>

                        </div>

                        <div class="guru-info">

                            <?php echo count($akses); ?>
                            akses hari ini
                            &nbsp; | &nbsp;

                            Akses terakhir:
                            <?php echo s($terakhir['jam']); ?>

                            &nbsp; | &nbsp;

                            <?php echo $status; ?>

                        </div>

                    </div>

                </div>


                <!-- TABEL AKTIVITAS -->

                <div class="table-responsive">

                    <table class="aktivitas-table">

                        <thead>

                            <tr>

                                <th class="nomor">
                                    No
                                </th>

                                <th class="jam">
                                    Waktu
                                </th>

                                <th>
                                    File / Halaman
                                </th>

                                <th class="ip">
                                    IP
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php

                        $no = 1;

                        foreach ($akses as $item):

                        ?>

                            <tr>

                                <td class="nomor">
                                    <?php echo $no++; ?>
                                </td>

                                <td class="jam">
                                    <?php echo s($item['jam']); ?>
                                </td>

                                <td class="file-page">
                                    <?php echo s($item['file']); ?>
                                </td>

                                <td class="ip">
                                    <?php echo s($guru['ip']); ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        <?php endforeach; ?>


    <?php endif; ?>


    <div class="refresh-info">

        🔄 Halaman diperbarui otomatis setiap 30 detik.

    </div>

</div>


<script>

setTimeout(function() {

    location.reload();

}, 30000);

</script>


<?php

echo $OUTPUT->footer();
