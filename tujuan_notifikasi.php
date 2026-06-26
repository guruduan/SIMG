<?php
require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/tujuan_notifikasi.php');
$PAGE->set_title('Tujuan Notifikasi WhatsApp');
$PAGE->set_heading('Tujuan Notifikasi WhatsApp');

echo $OUTPUT->header();

$jenis = [
    'jurnal'        => 'Jurnal Mengajar',
    'guruwali'      => 'Jurnal Guru Wali',
    'izinmurid'     => 'Surat Izin Murid',
    'izinguru'      => 'Surat Izin Guru',
    'layanan_bk'    => 'Layanan BK',
    'pembinaan'     => 'Laporan Pembinaan BK',
];

$roles = [
    'kepsek'                => 'Kepala Sekolah',
    'wakasek_kesiswaan'     => 'Wakil Kepala Sekolah Bidang Kesiswaan',
    'wakasek_kurikulum'     => 'Wakil Kepala Sekolah Bidang Kurikulum',
    'walikelas'             => 'Wali Kelas',
    'guruwali'              => 'Guru Wali',
    'gurubk'                => 'Guru BK',
];

if (optional_param('save', 0, PARAM_BOOL) && confirm_sesskey()) {

    foreach ($jenis as $kode => $judul) {

        $value = optional_param_array(
            'tujuan_'.$kode,
            [],
            PARAM_ALPHAEXT
        );

        set_config(
            'tujuan_'.$kode,
            implode(',', $value),
            'local_jurnalmengajar'
        );
    }

    \core\notification::success(
        'Pengaturan tujuan notifikasi berhasil disimpan.'
    );
}

echo $OUTPUT->notification(
    'Centang tujuan penerima notifikasi WhatsApp untuk setiap jenis jurnal.',
    \core\output\notification::NOTIFY_INFO
);

echo html_writer::start_tag('form', [
    'method' => 'post'
]);

echo html_writer::empty_tag('input', [
    'type'  => 'hidden',
    'name'  => 'sesskey',
    'value' => sesskey()
]);

echo html_writer::empty_tag('input', [
    'type'  => 'hidden',
    'name'  => 'save',
    'value' => 1
]);

foreach ($jenis as $kode => $judul) {

	$config = get_config(
	    'local_jurnalmengajar',
	    'tujuan_'.$kode
	);

	$selected = empty($config)
	    ? []
	    : explode(',', $config);
    
    echo html_writer::start_div('card mb-3');

    echo html_writer::div(
        html_writer::tag('h5', $judul),
        'card-header bg-primary text-white'
    );

    echo html_writer::start_div('card-body');

    foreach ($roles as $value => $label) {

        $checked = in_array($value, $selected, true);

        echo html_writer::start_div('form-check');

        echo html_writer::empty_tag('input', [
            'class'   => 'form-check-input',
            'type'    => 'checkbox',
            'name'    => 'tujuan_'.$kode.'[]',
            'value'   => $value,
            'id'      => $kode.'_'.$value,
            'checked' => $checked ? 'checked' : null
        ]);

        echo html_writer::tag(
            'label',
            $label,
            [
                'class' => 'form-check-label',
                'for'   => $kode.'_'.$value
            ]
        );

        echo html_writer::end_div();
    }

    echo html_writer::end_div();

    echo html_writer::end_div();
}

echo html_writer::start_div('text-end mt-3');

echo html_writer::tag(
    'button',
    '<i class="fa fa-save"></i> Simpan Pengaturan',
    [
        'type'  => 'submit',
        'class' => 'btn btn-success'
    ]
);

echo html_writer::end_div();

echo html_writer::end_tag('form');

echo $OUTPUT->footer();
