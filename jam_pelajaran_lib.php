<?php
defined('MOODLE_INTERNAL') || die();

function jurnalmengajar_get_config_jam() {

    global $CFG;

    $file = $CFG->dataroot . '/jam_pelajaran.json';

    if (!file_exists($file)) {
        return [];
    }

    return json_decode(
        file_get_contents($file),
        true
    );
}

function jurnalmengajar_save_config_jam($data) {

    global $CFG;

    $file = $CFG->dataroot . '/jam_pelajaran.json';

    file_put_contents(
        $file,
        json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        )
    );
}

function jurnalmengajar_generate_jam() {

    $config = jurnalmengajar_get_config_jam();

    if (empty($config)) {
        return [];
    }

    $jumlah = $config['jumlah_jam'] ?? 0;

    $mulai = $config['jam_mulai'] ?? '07:30';

    $modeaktif = $config['mode_aktif'] ?? '';

    $mode = $config['mode'][$modeaktif] ?? [];

    $durasi = $mode['durasi_jam'] ?? 0;

    if ($durasi <= 0) {
        return [];
    }

    $ist1_after = $mode['ist1_setelah'] ?? 0;

    $ist1_dur = $mode['ist1_durasi'] ?? 0;

    $ist2_after = $mode['ist2_setelah'] ?? 0;

    $ist2_dur = $mode['ist2_durasi'] ?? 0;

    $jam = [];

    $current = strtotime($mulai);

    for ($i = 1; $i <= $jumlah; $i++) {

        $start = $current;

        $end = strtotime(
            "+$durasi minutes",
            $start
        );

        $jam[$i] = [
            'mulai' => date('H:i', $start),
            'selesai' => date('H:i', $end),
            'istirahat_setelah' => false
        ];

        $current = $end;

        if ($i == $ist1_after) {

            $jam[$i]['istirahat_setelah'] = $ist1_dur;

            $current = strtotime(
                "+$ist1_dur minutes",
                $current
            );
        }

        if ($i == $ist2_after) {

            $jam[$i]['istirahat_setelah'] = $ist2_dur;

            $current = strtotime(
                "+$ist2_dur minutes",
                $current
            );
        }
    }

    return $jam;
}
?>
