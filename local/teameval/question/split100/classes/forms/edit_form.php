<?php

namespace teamevalquestion_split100\forms;

use local_teameval\forms\ajaxform;

class edit_form extends ajaxform {

    function definition() {

        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'ordinal');
        $mform->setType('ordinal', PARAM_INT);

        $mform->addElement('text', 'title', get_string('title', 'teamevalquestion_split100'));
        $mform->setType('title', PARAM_RAW_TRIMMED);

        $mform->addElement('editor', 'description', get_string('description', 'teamevalquestion_split100'));
        $mform->setType('description', PARAM_RAW);

        $this->fix_ids();

    }

}
