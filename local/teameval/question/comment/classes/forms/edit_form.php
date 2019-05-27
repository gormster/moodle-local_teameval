<?php

namespace teamevalquestion_comment\forms;

use local_teameval\forms\ajaxform;

class edit_form extends ajaxform {

    function definition() {

        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'ordinal');
        $mform->setType('ordinal', PARAM_INT);

        $mform->addElement('text', 'title', get_string('title', 'teamevalquestion_comment'));
        $mform->setType('title', PARAM_RAW_TRIMMED);

        $mform->addElement('editor', 'description', get_string('description', 'teamevalquestion_comment'));
        $mform->setType('description', PARAM_RAW);

        $mform->addElement('advcheckbox', 'anonymous', get_string('anonymous', 'teamevalquestion_comment'), null, null, [false, true]);
        $mform->setDefault('anonymous', false);

        $mform->addElement('advcheckbox', 'optional', get_string('optional', 'teamevalquestion_comment'), null, null, [false, true]);
        $mform->setDefault('optional', false);

        if (!empty($this->_customdata['locked'])) {
            $mform->freeze(['anonymous', 'optional']);
        }

        $this->fix_ids();

    }

}
