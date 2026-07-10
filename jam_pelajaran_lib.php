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

function jurnalmengajar_generate_jam_preview(array $cfg) {

    $jumlah = $cfg['jumlah_jam'] ?? 0;

    $mulai = $cfg['jam_mulai'] ?? '07:30';

    $durasi = $cfg['durasi_jam'] ?? 0;

    if ($jumlah <= 0 || $durasi <= 0) {
        return [];
    }

    $ist1_after = $cfg['ist1_setelah'] ?? 0;
    $ist1_dur   = $cfg['ist1_durasi'] ?? 0;

    $ist2_after = $cfg['ist2_setelah'] ?? 0;
    $ist2_dur   = $cfg['ist2_durasi'] ?? 0;

    $jam = [];

    $current = strtotime(date('Y-m-d') . ' ' . $mulai);

    for ($i=1;$i<=$jumlah;$i++) {

        $start = $current;
        $end = strtotime("+{$durasi} minutes",$start);

        $jam[$i]=[
            'mulai'=>date('H:i',$start),
            'selesai'=>date('H:i',$end),
            'istirahat_setelah'=>false
        ];

        $current=$end;

        if ($i==$ist1_after) {
            $jam[$i]['istirahat_setelah']=$ist1_dur;
            $current=strtotime("+{$ist1_dur} minutes",$current);
        }

        if ($i==$ist2_after) {
            $jam[$i]['istirahat_setelah']=$ist2_dur;
            $current=strtotime("+{$ist2_dur} minutes",$current);
        }

    }

    return $jam;
}

function jurnalmengajar_get_jam_ke() {

    $jam = jurnalmengajar_generate_jam();

    if (empty($jam)) {
        return 0;
    }

    $sekarang = time();
    $hariini = date('Y-m-d');

    foreach ($jam as $nomor => $w) {



$mulai = strtotime($hariini . ' ' . $w['mulai']);
$selesai = strtotime($hariini . ' ' . $w['selesai']);

        if ($sekarang >= $mulai && $sekarang < $selesai) {
            return $nomor;
        }
    }

    return 0;
}

function jurnalmengajar_is_istirahat() {

    $jam = jurnalmengajar_generate_jam();

    if (empty($jam)) {
        return false;
    }

    $sekarang = time();
    $hariini = date('Y-m-d');

    foreach ($jam as $w) {

        if (empty($w['istirahat_setelah'])) {
            continue;
        }

        $selesaijp = strtotime($hariini . ' ' . $w['selesai']);

        $akhiristirahat = strtotime(
            '+' . $w['istirahat_setelah'] . ' minutes',
            $selesaijp
        );

        if ($sekarang >= $selesaijp && $sekarang < $akhiristirahat) {
            return true;
        }
    }

    return false;
}
function jurnalmengajar_generate_jam() {

    $config = jurnalmengajar_get_config_jam();

    if (empty($config)) {
        return [];
    }

    $mode = $config['mode_aktif'] ?? 'normal';

    // 1=Senin ... 7=Minggu
    $hari = (int)date('N');

    // Jumlah hari sekolah dari settings plugin.
    $harisekolah = (int)get_config(
        'local_jurnalmengajar',
        'harisekolah'
    );

    if ($harisekolah <= 0) {
        $harisekolah = 5;
    }

    // Di luar hari sekolah.
    if ($hari > $harisekolah) {
        return [];
    }

    if ($hari == 5) {

        // Jumat selalu memakai konfigurasi Normal Jumat.
        $cfg = $config['normal']['jumat'] ?? [];

    } else {

        // Senin-Kamis (dan Sabtu bila harisekolah=6).
        if ($mode == 'rapat') {
            $cfg = $config['rapat']['senin_kamis'] ?? [];
        } else {
            $cfg = $config['normal']['senin_kamis'] ?? [];
        }
    }

    return jurnalmengajar_generate_jam_preview($cfg);
}
?>
