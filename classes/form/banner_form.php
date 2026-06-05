<?php

namespace local_jurnalmengajar\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

class banner_form extends \moodleform {

    public function definition() {

        $mform = $this->_form;

        $mform->addElement(
            'filemanager',
            'bannerfiles',
            'Banner TV',
            null,
            [
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
            ]
        );

        $this->add_action_buttons(
            false,
            'Simpan Banner'
        );
    }
}
