<?php

require('../../config.php');
require_once(__DIR__ . '/classes/form/banner_form.php');

require_login();

if (!is_siteadmin()) {
    throw new moodle_exception('accessdenied');
}

$context = context_system::instance();

$PAGE->set_url(
    new moodle_url(
        '/local/jurnalmengajar/banner.php'
    )
);

$PAGE->set_context($context);

$PAGE->set_pagelayout('admin');

$PAGE->set_title(
    'Kelola Banner TV'
);

$PAGE->set_heading(
    'Kelola Banner TV'
);

/*
=====================================================
FILE MANAGER OPTIONS
=====================================================
*/

$filemanageroptions = [
    'subdirs' => 0,
    'maxfiles' => 50,
    'maxbytes' => 0,
    'accepted_types' => [
        '.png',
        '.jpg',
        '.jpeg',
        '.webp',
        '.mp4'
    ]
];

/*
=====================================================
DRAFT AREA
=====================================================
*/

$draftitemid = file_get_submitted_draft_itemid(
    'bannerfiles'
);

file_prepare_draft_area(
    $draftitemid,
    $context->id,
    'local_jurnalmengajar',
    'banner',
    0,
    $filemanageroptions
);

$data = new stdClass();

$data->bannerfiles = $draftitemid;

$mform =
    new \local_jurnalmengajar\form\banner_form(
        null,
        [
            'filemanageroptions' =>
                $filemanageroptions
        ]
    );

$mform->set_data($data);

/*
=====================================================
SAVE
=====================================================
*/

if ($mform->is_cancelled()) {

    redirect(
        new moodle_url(
            '/local/jurnalmengajar/banner.php'
        )
    );
}

if ($formdata = $mform->get_data()) {

    file_save_draft_area_files(
        $formdata->bannerfiles,
        $context->id,
        'local_jurnalmengajar',
        'banner',
        0,
        $filemanageroptions
    );

    redirect(
        new moodle_url(
            '/local/jurnalmengajar/banner.php'
        ),
        'Banner berhasil disimpan',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

echo $OUTPUT->heading(
    'Kelola Banner TV'
);

echo html_writer::div(
    'Upload banner yang akan digunakan pada SiM TV.',
    'alert alert-info'
);

$mform->display();

echo $OUTPUT->footer();
