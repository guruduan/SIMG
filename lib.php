<?php
defined('MOODLE_INTERNAL') || die();
date_default_timezone_set('Asia/Makassar');

/**
 * Ambil nama kelas dari ID cohort
 */
function get_nama_kelas($id) {
    global $DB;
    return $DB->get_field('cohort', 'name', ['id' => $id]) ?? "Kelas #$id";
}

/**
 * Ambil nomor WA user dari profile field nowa
 */
function get_user_nowa($userid) {
    global $DB;

    $sql = "SELECT d.data
              FROM {user_info_data} d
              JOIN {user_info_field} f ON f.id = d.fieldid
             WHERE d.userid = :userid
               AND f.shortname = :shortname";

    $nowa = $DB->get_field_sql($sql, [
        'userid' => $userid,
        'shortname' => 'nowa'
    ]);

    if (empty($nowa)) {
        debugging("Field nowa kosong untuk user: $userid", DEBUG_DEVELOPER);
        return null;
    }

    return preg_replace('/[^0-9]/', '', $nowa);
}

/**
 * Format waktu Indonesia
 */
function format_waktu_indo($timestamp) {
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    return $hari[date('w',$timestamp)] . ', ' .
           date('j',$timestamp) . ' ' .
           $bulan[date('n',$timestamp)] . ' ' .
           date('Y',$timestamp) .
           ' Pukul ' . date('H:i',$timestamp) . ' WITA';
}

/**
 * Format tanggal judul
 */
function format_tanggal_judul($timestamp = null) {
    $timestamp = $timestamp ?: time();

    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    return $hari[date('w',$timestamp)] . ' ' .
           date('j',$timestamp) . ' ' .
           $bulan[date('n',$timestamp)] . ' ' .
           date('Y',$timestamp);
}

/**
 * Format jam saja
 */
function format_jam($timestamp = null) {
    if (!$timestamp) {
        $timestamp = time();
    }
    return date('H:i', $timestamp);
}

/**
 * Cek boleh kirim WA atau tidak
 */
function jurnalmengajar_boleh_kirim_wa() {

    // ======================
    // Cek hari sekolah
    // ======================
    $hariIndoList = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu'
    ];

    $hariIndo = $hariIndoList[(int)date('N')];

    $hariSekolah = get_config('local_jurnalmengajar', 'harisekolah');
    $hariSekolah = array_map('trim', explode(',', $hariSekolah));

    if (!in_array($hariIndo, $hariSekolah)) {
        return false;
    }

    // ======================
    // Cek tanggal libur
    // ======================
    $tanggal = date('Y-m-d');

    if (function_exists('jurnalmengajar_cek_libur') && jurnalmengajar_cek_libur($tanggal)) {
        return false;
    }

    return true;
}

/**
 * Kirim WhatsApp via Wablas
 */
function jurnalmengajar_kirim_wa($nomor, $pesan) {
    global $CFG;

    $logdir = $CFG->dataroot . '/logs';
    if (!file_exists($logdir)) {
        mkdir($logdir, 0777, true);
    }

    $logfile = $logdir . '/wa_debug.log';

    file_put_contents($logfile,
        date('Y-m-d H:i:s') . " | Mulai kirim WA ke $nomor\n",
        FILE_APPEND
    );

    // CEK BOLEH KIRIM
    if (!jurnalmengajar_boleh_kirim_wa()) {
        file_put_contents($logfile,
            date('Y-m-d H:i:s') . " | DIBATALKAN: Hari libur / bukan hari sekolah\n",
            FILE_APPEND
        );
        return false;
    }

    file_put_contents($logfile,
        date('Y-m-d H:i:s') . " | Lolos filter hari sekolah\n",
        FILE_APPEND
    );

    $apikey = get_config('local_jurnalmengajar', 'apikey');
    $secret = get_config('local_jurnalmengajar', 'secretkey');
    $wablas_url = get_config('local_jurnalmengajar', 'wablas_url');

    file_put_contents($logfile,
        date('Y-m-d H:i:s') . " | URL: $wablas_url\n",
        FILE_APPEND
    );

    if (empty($apikey) || empty($secret)) {
        file_put_contents($logfile,
            date('Y-m-d H:i:s') . " | ERROR: API key kosong\n",
            FILE_APPEND
        );
        return false;
    }

    $token = $apikey . '.' . $secret;

    $data = [
        'data' => [[
            'phone' => $nomor,
            'message' => $pesan,
            'secret' => false,
            'priority' => false
        ]]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $wablas_url,
        CURLOPT_HTTPHEADER => [
            "Authorization: $token",
            "Content-Type: application/json"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        file_put_contents($logfile,
            date('Y-m-d H:i:s') . " | CURL ERROR: " . curl_error($ch) . "\n",
            FILE_APPEND
        );
    } else {
        file_put_contents($logfile,
            date('Y-m-d H:i:s') . " | RESPONSE: $response\n",
            FILE_APPEND
        );
    }

    curl_close($ch);

    return $response;
}

/**
 * Ambil nomor wali kelas dari mapping
 */
function get_nomor_wali_kelas($kelasid) {
    $json = get_config('local_jurnalmengajar', 'wali_kelas_mapping');
    $mapping = json_decode($json, true);

    if (empty($mapping[$kelasid])) {
        debugging("Mapping tidak ditemukan untuk kelas ID: $kelasid", DEBUG_DEVELOPER);
        return null;
    }

    return get_user_nowa($mapping[$kelasid]);
}

/**
 * Notifikasi WA Jurnal KBM
 */
function jurnalmengajar_notifikasi_wa($data, $user) {

    $kelasid = $data->kelas ?? null;
    if (!$kelasid) return;

    $namaguru = !empty($user->lastname) ? $user->lastname : $user->firstname;
    $kelas = get_nama_kelas($kelasid);

    $jamke = $data->jamke ?? '-';
    $mapel = $data->matapelajaran ?? '-';
    $materi = $data->materi ?? '-';
    $aktivitas = $data->aktivitas ?? '-';
    $keterangan = $data->keterangan ?? '-';

    $sekolah = get_config('local_jurnalmengajar', 'nama_sekolah') ?: 'Nama Sekolah';

    $tanggal_judul = format_tanggal_judul();
    $jam = format_jam();

    $pesan = "*📘 Jurnal KBM $tanggal_judul*\n\n"
       . "👤 Guru yang mengajar: $namaguru\n"
       . "🏫 Kelas: $kelas\n"
       . "⏰ Jam ke: $jamke\n"
       . "📚 Mata Pelajaran: $mapel\n"
       . "📒 Materi: $materi\n"
       . "📝 Aktivitas:\n$aktivitas\n\n"
       . "🕒 Waktu: $jam WITA\n"
       . "📌 Tercatat di eJurnal KBM $sekolah";

    $nomor_guru = get_user_nowa($user->id);
    $nomor_wali = get_nomor_wali_kelas($kelasid);

    if ($nomor_guru && $nomor_guru === $nomor_wali) {
        jurnalmengajar_kirim_wa($nomor_guru, $pesan);
    } else {
        if ($nomor_guru) jurnalmengajar_kirim_wa($nomor_guru, $pesan);
        if ($nomor_wali) jurnalmengajar_kirim_wa($nomor_wali, $pesan);
    }
}

/**
 * Ambil URL logo
 */
function jurnalmengajar_get_logo_url() {
    global $CFG;

    $context = context_system::instance();
    $fs = get_file_storage();

    $files = $fs->get_area_files(
        $context->id,
        'local_jurnalmengajar',
        'logo',
        0,
        'itemid, filepath, filename',
        false
    );

    foreach ($files as $file) {
        return moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            0,
            $file->get_filepath(),
            $file->get_filename()
        );
    }

    return '';
}

/**
 * Ambil path stempel
 */
function jurnalmengajar_get_stempel_path() {
    global $CFG;

    $context = context_system::instance();
    $fs = get_file_storage();

    $files = $fs->get_area_files(
        $context->id,
        'local_jurnalmengajar',
        'stempel',
        0,
        'itemid, filepath, filename',
        false
    );

    foreach ($files as $file) {
        $temp = $CFG->tempdir . '/' . $file->get_filename();
        $file->copy_content_to($temp);
        return $temp;
    }

    return '';
}
