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
 * Ambil nomor WA guru penginput jurnal
 */
function get_nomor_guru_penginput($userid) {

    if (empty($userid)) {
        return null;
    }

    return get_user_nowa($userid);
}

/**
 * Ambil nomor wali kelas dari mapping
 */
function get_nomor_wali_kelas($kelas) {
    global $DB;

    if (!is_numeric($kelas)) {
        $kelas = $DB->get_field(
            'cohort',
            'id',
            ['name' => $kelas]
        );
    }

    $json = get_config('local_jurnalmengajar', 'wali_kelas_mapping');
    $mapping = json_decode($json, true);

    if (empty($mapping[$kelas])) {
        return null;
    }

    return get_user_nowa($mapping[$kelas]);
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

    $map = [
        'jurnal'           => 'template_jurnal',
        'guruwali'         => 'template_guru_wali',
        'izinmurid'        => 'template_izin_murid',
        'izinguru'         => 'template_izin_guru',
        'pembinaan'        => 'template_pembinaan',
        'layanan_bk'       => 'template_layanan_bk',
        'reminder_jurnal'  => 'template_reminder_jurnal',
        'rekap_reminder'   => 'template_rekap_reminder',
    ];

    $configkey = $map[$kode] ?? ('template_' . $kode);

    $template = get_config(
        'local_jurnalmengajar',
        $configkey
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

/**
 * Ambil daftar tujuan notifikasi dari config
 */
function jm_get_tujuan_notifikasi($kode) {

    $config = get_config(
        'local_jurnalmengajar',
        'tujuan_' . $kode
    );

    if (empty($config)) {
        return [];
    }

    return array_filter(array_map('trim', explode(',', $config)));
}

/**
 * Mengubah tujuan menjadi daftar nomor WA
 */
function jm_get_nomor_tujuan($kode, array $data = []) {

    $nomor = [];

    $tujuan = jm_get_tujuan_notifikasi($kode);

    foreach ($tujuan as $role) {

        switch ($role) {

            case 'kepsek':
                $wa = get_nomor_kepala_sekolah();
                if ($wa) {
                    $nomor[] = $wa;
                }
                break;

            case 'wakasek_kesiswaan':
                $wa = get_nomor_wakasek_kesiswaan();
                if ($wa) {
                    $nomor[] = $wa;
                }
                break;

	   case 'wakasek_kurikulum':
	        $wa = get_nomor_wakasek_kurikulum();
     	        if ($wa) {
		    $nomor[] = $wa;
	        }
	        break;
    
            case 'walikelas':
                if (!empty($data['kelas'])) {
                    $wa = get_nomor_wali_kelas($data['kelas']);
                    if ($wa) {
                        $nomor[] = $wa;
                    }
                }
                break;

	case 'guruwali':
	    if (!empty($data['kelas'])) {

		$list = get_nomor_guru_wali($data['kelas']);

		if (!empty($list)) {
		    $nomor = array_merge($nomor, $list);
		}
	    }
	    break;

	case 'gurubk':
	    $list = get_nomor_guru_bk();

	    if (!empty($list)) {
		$nomor = array_merge($nomor, $list);
	    }
	    break;

	case 'guru_penginput':

	    if (!empty($data['userid'])) {

		$wa = get_nomor_guru_penginput($data['userid']);

		if (!empty($wa)) {
		    $nomor[] = $wa;
		}
	    }

	    break;

	default:
	    break;
        }
    }

    return array_unique(array_filter($nomor));
}

function jm_kirim_template_auto(
    $kode,
    array $data
) {

    $nomor = jm_get_nomor_tujuan(
        $kode,
        $data
    );

    if (empty($nomor)) {
        return false;
    }

    return jm_kirim_template(
        $kode,
        $nomor,
        $data
    );
}

//fungsi nomor jabatan
function get_nomor_jabatan($configkey) {

    $userid = get_config(
        'local_jurnalmengajar',
        $configkey
    );

    if (empty($userid)) {
        return null;
    }

    return get_user_nowa($userid);
}

// fungsi baru
function get_nomor_wakasek_kesiswaan() {
    return get_nomor_jabatan(
        'wakasek_kesiswaan_userid'
    );
}

function get_nomor_wakasek_kurikulum() {
    return get_nomor_jabatan(
        'wakasek_kurikulum_userid'
    );
}

// get nomor bk
function get_nomor_guru_bk($kelas = null) {

    $json = get_config(
        'local_jurnalmengajar',
        'guru_bk_mapping'
    );

    $mapping = json_decode($json, true);

    if (!is_array($mapping)) {
        return [];
    }

    $nomor = [];

    foreach ($mapping as $userid) {

        $wa = get_user_nowa($userid);

        if (!empty($wa)) {
            $nomor[] = $wa;
        }
    }

    return array_unique($nomor);
}

/**
 * Ambil nomor WA Guru Wali berdasarkan kelas
 */
function get_nomor_guru_wali($kelas) {
    global $CFG, $DB;

    if (is_numeric($kelas)) {

    $namakelas = $DB->get_field(
        'cohort',
        'name',
        ['id' => $kelas]
    );

    if (!empty($namakelas)) {
        $kelas = $namakelas;
    }
}

    $file = $CFG->dataroot . '/binaan.csv';

    if (!file_exists($file)) {
        return [];
    }

    $nomor = [];

    if (($handle = fopen($file, 'r')) !== false) {

        fgetcsv($handle); // Header

        while (($row = fgetcsv($handle)) !== false) {

            if (count($row) < 5) {
                continue;
            }

            if (trim($row[4]) !== trim($kelas)) {
                continue;
            }

            $wa = get_user_nowa((int)$row[0]);

            if (!empty($wa)) {
                $nomor[] = $wa;
            }
        }

        fclose($handle);
    }

    return array_unique($nomor);
}

function jm_kirim_rekap_pending($pending, $tanggal) {

    if (empty($pending)) {
        return;
    }

    $isi = "";
    $no  = 1;

    foreach ($pending as $guru) {

        $isi .= $no++ . ". {$guru['lastname']}\n";

        foreach ($guru['kelasjam'] as $kelas => $jam) {

            sort($jam);

            $isi .= "   - {$kelas} jam " .
                implode(',', $jam) . "\n";
        }

        $isi .= "\n";
    }

    $data = [
        '{tanggal}' => $tanggal,
        '{jumlah}'  => count($pending),
        '{daftar}'  => trim($isi)
    ];

    return jm_kirim_template_auto(
        'rekap_reminder',
        $data
    );
}
