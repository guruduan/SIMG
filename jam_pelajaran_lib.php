<?php
defined('MOODLE_INTERNAL') || die();

function jurnalmengajar_get_config_jam() {
    global $CFG;
    $file = $CFG->dataroot . '/jam_pelajaran.json';

    if (!file_exists($file)) {
        return [];
    }

    return json_decode(file_get_contents($file), true);
}

function jurnalmengajar_save_config_jam($data) {
    global $CFG;
    $file = $CFG->dataroot . '/jam_pelajaran.json';
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function jurnalmengajar_generate_jam() {
    $config = jurnalmengajar_get_config_jam();

    if (empty($config)) return [];

    $jumlah = $config['jumlah_jam'];
    $durasi = $config['durasi_jam'];
    $mulai  = $config['jam_mulai'];

    $ist1_after = $config['ist1_setelah'];
    $ist1_dur   = $config['ist1_durasi'];

    $ist2_after = $config['ist2_setelah'];
    $ist2_dur   = $config['ist2_durasi'];

    $jam = [];
    $current = strtotime($mulai);

    for ($i = 1; $i <= $jumlah; $i++) {
    $start = $current;
    $end   = strtotime("+$durasi minutes", $start);

    $jam[$i] = [
        'mulai' => date('H:i', $start),
        'selesai' => date('H:i', $end),
        'istirahat_setelah' => false
    ];

    $current = $end;

    if ($i == $ist1_after) {
        $jam[$i]['istirahat_setelah'] = $ist1_dur;
        $current = strtotime("+$ist1_dur minutes", $current);
    }

    if ($i == $ist2_after) {
        $jam[$i]['istirahat_setelah'] = $ist2_dur;
        $current = strtotime("+$ist2_dur minutes", $current);
    }
}

    return $jam;
}
