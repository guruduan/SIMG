<?php
defined('MOODLE_INTERNAL') || die();

function jurnalmengajar_get_jadwal_acuan() {
    global $CFG;

    $file = $CFG->dataroot . '/acuan.csv';
    if (!file_exists($file)) {
        return [];
    }

    $rows = array_map('str_getcsv', file($file));
    $header = array_shift($rows);

    $jadwal = [];

    foreach ($rows as $row) {
        if (count($row) < 5) continue;

        $data = array_combine($header, $row);

        $hari     = trim($data['hari']);
        $userid   = trim($data['userid']);
        $lastname = trim($data['lastname']);
        $kelas    = trim($data['kelas']);
        $jamke    = trim($data['jamke']);

        $jamlist = explode(',', $jamke);

        foreach ($jamlist as $jam) {
            $jadwal[] = [
                'hari'     => $hari,
                'userid'   => $userid,
                'lastname' => $lastname,
                'kelas'    => $kelas,
                'jamke'    => trim($jam)
            ];
        }
    }

    return $jadwal;
}

function jurnalmengajar_get_hari_ini() {
    $map = [
        'Monday'    => 'Senin',
        'Tuesday'   => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday'  => 'Kamis',
        'Friday'    => 'Jumat',
        'Saturday'  => 'Sabtu',
        'Sunday'    => 'Minggu'
    ];

    $today = date('l');
    return $map[$today] ?? '';
}
