<?php

namespace teamevalquestion_likert\forms;

use local_teameval\forms\ajaxform;

use HTML_QuickForm_Rule;
use HTML_QuickForm;

class settings_form extends ajaxform {

    function definition() {

        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'ordinal');
        $mform->setType('ordinal', PARAM_INT);

        $mform->addElement('hidden', 'teameval');
        $mform->setType('teameval', PARAM_INT);

        $mform->addElement('text', 'title', get_string('title', 'teamevalquestion_likert'));
        $mform->setType('title', PARAM_RAW_TRIMMED);

        $mform->addElement('editor', 'description', get_string('description', 'teamevalquestion_likert'));
        $mform->setType('description', PARAM_RAW);

        $minval = $mform->createElement('select', 'min', get_string('minval', 'teamevalquestion_likert'), [0 => '0', 1 => '1']);
        $maxval = $mform->createElement('select', 'max', get_string('maxval', 'teamevalquestion_likert'), [3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10']);

        $group =& $mform->addGroup([$minval, $maxval], 'range', get_string('scorerange', 'teamevalquestion_likert'), get_string('minmaxconjunction', 'teamevalquestion_likert'));
        $mform->setDefault('range', ['min' => 1, 'max' => 5]);

        $mform->addElement('header', 'meanings_header', get_string('meanings', 'teamevalquestion_likert'));

        //repeat_elements doesn't actually do what we want, so we'll manually loop
        for ($i=0; $i <= 10; $i++) {
            $mform->addElement('text', "meanings[$i]", $i, ['class' => 'hidden']);
            $mform->setType("meanings[$i]", PARAM_RAW_TRIMMED);
        }
    }

    function definition_after_data() {
        $mform = $this->_form;

        $range = $mform->getElementValue('range');

        for ($i=$range['min'][0]; $i <= $range['max'][0]; $i++) {
            $el = $mform->getElement("meanings[$i]");
            $el->removeAttribute('class');
        }

        if (!empty($this->_customdata['locked'])) {
            $mform->freeze('range');
        }
    }

}
