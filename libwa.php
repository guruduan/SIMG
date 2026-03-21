<?php
defined('MOODLE_INTERNAL') || die();

function get_nomor_kepsek(): string {
    return get_config('local_jurnalmengajar', 'nomor_kepsek');
}

function jurnalmengajar_kirim_wa($nomor, $pesan): void {
    $apikey = get_config('local_jurnalmengajar', 'apikey');
    $secret = get_config('local_jurnalmengajar', 'secretkey');
    $wablas_url = get_config('local_jurnalmengajar', 'wablas_url');

    if (empty($apikey) || empty($secret) || empty($wablas_url)) {
        debugging('Config Wablas belum lengkap', DEBUG_DEVELOPER);
        return;
    }

    $token = $apikey . '.' . $secret;

    $data = ['data' => [[
        'phone' => $nomor,
        'message' => $pesan,
        'secret' => false,
        'priority' => false
    ]]];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $wablas_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: $token",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        debugging('cURL error: ' . curl_error($ch), DEBUG_DEVELOPER);
    }

    curl_close($ch);
}

function kirim_wa_izin_guru($guru, $nip, $alasan, $keperluan, $tanggal, $dicatatoleh): void {
    $nomor_kepsek = get_nomor_kepsek();
    $jam = date('H:i');

    $pesan = "*-Surat Izin Keluar Guru/Pegawai-*\n"
           . "👮‍Nama: {$guru->lastname}\n"
           . "NIP/NIPPPK: {$nip}\n"
           . "Alasan: {$alasan}\n"
           . "Keperluan: {$keperluan}\n"
           . "Hari/Tanggal: {$tanggal}\n"
           . "🕒Pukul: {$jam}\n"
           . "📝Diinput oleh: {$dicatatoleh}";

    jurnalmengajar_kirim_wa($nomor_kepsek, $pesan);
}
