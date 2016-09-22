<?php

namespace local_teameval\forms;

defined('MOODLE_INTERNAL') || die();

use moodleform;

class settings_form extends ajaxform {

    public function definition() {

        $mform = $this->_form;

        $mform->updateAttributes(['data-ajaxforms-methodname' => 'local_teameval_update_settings']);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('checkbox', 'enabled', get_string('enabled', 'local_teameval'));

        $mform->addElement('checkbox', 'self', get_string('selfassessment', 'local_teameval'));
        $mform->addHelpButton('self', 'selfassessment', 'local_teameval');

        $mform->addElement('checkbox', 'autorelease', get_string('autorelease', 'local_teameval'));
        $mform->setDefault('autorelease', true);
        $mform->addHelpButton('autorelease', 'autorelease', 'local_teameval');

        $mform->addElement('checkbox', 'public', get_string('public', 'local_teameval'));
        $mform->addHelpButton('public', 'public', 'local_teameval');

        $percents = [];
        for($i = 0; $i <= 100; $i += 5) {
            $percents[$i] = "$i%";
        }
        $mform->addElement('select', 'fraction', get_string('fraction', 'local_teameval'), $percents);

        $mform->addElement('select', 'noncompletionpenalty', get_string('noncompletionpenalty', 'local_teameval'), $percents);

        $mform->addElement('date_time_selector', 'deadline', get_string('deadline', 'local_teameval'), ['optional' => true]);

        $mform->addElement('submit', 'submit', get_string('save', 'local_teameval'));

    }

}