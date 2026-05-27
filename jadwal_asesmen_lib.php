<?php

defined('MOODLE_INTERNAL') || die();

/*
=====================================================
GENERATE SESI ASESMEN
=====================================================
*/

function jurnalmengajar_generate_sesi_asesmen(
    $mulaipukul = null,
    $jumlahsesi_custom = null
) {

    /*
    =============================================
    JUMLAH SESI
    =============================================
    */

    if ($jumlahsesi_custom !== null) {

        $jumlahsesi =
            (int)$jumlahsesi_custom;

    } else {

        $jumlahsesi =
            (int)get_config(
                'local_jurnalmengajar',
                'asesmen_jumlah_sesi'
            );
    }

    /*
    =============================================
    DURASI
    =============================================
    */

    $durasi =
        (int)get_config(
            'local_jurnalmengajar',
            'asesmen_durasi_sesi'
        );

    /*
    =============================================
    MULAI PUKUL
    =============================================
    */

    if ($mulaipukul === null) {

        $mulaipukul =
            get_config(
                'local_jurnalmengajar',
                'asesmen_mulai'
            );
    }

    /*
    =============================================
    ISTIRAHAT
    =============================================
    */

    $istirahatsetelah =
        (int)get_config(
            'local_jurnalmengajar',
            'asesmen_istirahat_setelah'
        );

    $durasiistirahat =
        (int)get_config(
            'local_jurnalmengajar',
            'asesmen_durasi_istirahat'
        );

    /*
    =============================================
    DEFAULT
    =============================================
    */

    if (!$jumlahsesi) {
        $jumlahsesi = 10;
    }

    if (!$durasi) {
        $durasi = 40;
    }

    if (!$mulaipukul) {
        $mulaipukul = '08:00';
    }

    if (!$istirahatsetelah) {
        $istirahatsetelah = 6;
    }

    if (!$durasiistirahat) {
        $durasiistirahat = 60;
    }

    /*
    =============================================
    GENERATE
    =============================================
    */

    $hasil = [];

    $current =
        strtotime($mulaipukul);

    for ($i = 1; $i <= $jumlahsesi; $i++) {

        $mulai =
            date('H:i:s', $current);

        $selesai =
            date(
                'H:i:s',
                strtotime(
                    "+$durasi minutes",
                    $current
                )
            );

        $hasil[$i] = [

            'sesi' => $i,

            'mulai' => $mulai,

            'selesai' => $selesai
        ];

        /*
        =========================================
        PINDAH KE SESI BERIKUTNYA
        =========================================
        */

        $current =
            strtotime($selesai);

        /*
        =========================================
        ISTIRAHAT
        =========================================
        */

        if ($i == $istirahatsetelah) {

            $current =
                strtotime(
                    "+$durasiistirahat minutes",
                    $current
                );
        }
    }

    return $hasil;
}
