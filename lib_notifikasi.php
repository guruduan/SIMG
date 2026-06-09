<?php
defined('MOODLE_INTERNAL') || die();
// ini lib_notifikasi.php
// membutuhkan fungsi umum plugin
require_once(__DIR__ . '/lib.php');

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
        return null; // jangan debugging
    }

    return preg_replace('/[^0-9]/', '', $nowa);
}

/**
 * Ambil nomor wali kelas dari mapping
 */
function get_nomor_wali_kelas($kelasid) {
    $json = get_config('local_jurnalmengajar', 'wali_kelas_mapping');
    $mapping = json_decode($json, true);

    if (empty($mapping[$kelasid])) {
        return null;
    }

    return get_user_nowa($mapping[$kelasid]);
}

/**
 * Ambil nomor kepala sekolah dari setting plugin
 */
function get_nomor_kepala_sekolah() {
    $nowa = get_config('local_jurnalmengajar', 'nomor_kepsek');

    if (empty($nowa)) {
        return null;
    }

    // bersihkan selain angka
    return preg_replace('/[^0-9]/', '', $nowa);
}

/**
 * Cek boleh kirim WA atau tidak
 */
function jurnalmengajar_boleh_kirim_wa() {

    $hariIndoList = [
        1=>'Senin',2=>'Selasa',3=>'Rabu',
        4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'
    ];

$hariSekolah = get_config('local_jurnalmengajar', 'harisekolah');

if (empty($hariSekolah)) {
    $hariSekolah = 'Senin,Selasa,Rabu,Kamis,Jumat';
}

$hariSekolah = array_map('trim', explode(',', $hariSekolah));

    if (!in_array($hariIndoList[(int)date('N')], $hariSekolah)) {
        return false;
    }

    if (jurnalmengajar_cek_libur(date('Y-m-d'))) {
        return false;
    }

    return true;
}

/**
 * Kirim WhatsApp via Wablas
 */
function jurnalmengajar_kirim_wa($tujuan, $pesan) {
    global $CFG;

    // Pastikan array
    if (!is_array($tujuan)) {
        $tujuan = [$tujuan];
    }

    // Hapus duplikat
    $tujuan = array_unique($tujuan);

    // Siapkan log
    $logdir = $CFG->dataroot . '/logs';
    if (!file_exists($logdir)) {
        mkdir($logdir, 0755, true);
    }

    $logfile = $logdir . '/wa_debug.log';

    // Cek boleh kirim WA
    if (!jurnalmengajar_boleh_kirim_wa()) {
        file_put_contents($logfile,
            date('Y-m-d H:i:s') . " | DIBATALKAN: Hari libur / bukan hari sekolah\n",
            FILE_APPEND
        );
        return false;
    }

    // Ambil config Wablas
    $apikey = get_config('local_jurnalmengajar', 'apikey');
    $secret = get_config('local_jurnalmengajar', 'secretkey');
    $wablas_url = get_config('local_jurnalmengajar', 'wablas_url');

    if (empty($apikey) || empty($secret)) {
        file_put_contents($logfile,
            date('Y-m-d H:i:s') . " | ERROR API KEY\n",
            FILE_APPEND
        );
        return false;
    }

    $token = $apikey . '.' . $secret;
file_put_contents($logfile,
    "----------------------------------------\n" .
    date('Y-m-d H:i:s') . " | Mulai kirim notifikasi\n",
    FILE_APPEND
);
file_put_contents($logfile,
    date('Y-m-d H:i:s') . " | Pesan: " . str_replace("\n"," | ",$pesan) . "\n",
    FILE_APPEND
);
foreach ($tujuan as $nomor) {

    if (empty($nomor)) {
        file_put_contents($logfile,
            date('Y-m-d H:i:s') . " | Nomor kosong, dilewati\n",
            FILE_APPEND
        );
        continue;
    }

    file_put_contents($logfile,
        date('Y-m-d H:i:s') . " | Kirim WA ke $nomor\n",
        FILE_APPEND
    );

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
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            "Authorization: $token",
            "Content-Type: application/json"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);

	$response = curl_exec($ch);
	$error = curl_error($ch);

	curl_close($ch);
if (!empty($error)) {
    file_put_contents(
        $logfile,
        date('Y-m-d H:i:s') .
        " | CURL ERROR: $error\n",
        FILE_APPEND
    );
}
        file_put_contents($logfile,
        date('Y-m-d H:i:s') . " | Response: $response\n",
        FILE_APPEND
    );
}

return true;
} // <-- PENUTUP fungsi jurnalmengajar_kirim_wa()

/**
 * Ambil template notifikasi
 */
function jm_get_template($kode) {

    $template = get_config(
        'local_jurnalmengajar',
        'template_' . $kode
    );

    return $template ?: '';
}

/**
 * Render template dengan placeholder
 */
function jm_render_template($kode, array $data) {

    $template = jm_get_template($kode);

    if (empty($template)) {
        return '';
    }

    return strtr($template, $data);
}

/**
 * Kirim notifikasi berdasarkan template
 */
function jm_kirim_template(
    $kode,
    $tujuan,
    array $data
) {

    global $CFG;

    $pesan = jm_render_template(
        $kode,
        $data
    );

	if (empty($pesan)) {

	    file_put_contents(
		$CFG->dataroot . '/logs/wa_debug.log',
		date('Y-m-d H:i:s') .
		" | TEMPLATE KOSONG: $kode\n",
		FILE_APPEND
	    );

	    return false;
	}
    return jurnalmengajar_kirim_wa(
        $tujuan,
        $pesan
    );
}

//preview
function jm_preview_template(
    $kode,
    array $data
) {
    return jm_render_template(
        $kode,
        $data
    );
}
