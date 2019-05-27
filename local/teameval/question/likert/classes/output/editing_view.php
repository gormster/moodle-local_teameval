<?php

namespace teamevalquestion_likert\output;

use teamevalquestion_likert\question;
use teamevalquestion_likert\forms\settings_form;
use renderable;
use stdClass;
use renderer_base;

class editing_view implements renderable {

    function __construct(question $question, $locked) {
        $this->form = new settings_form(null, ['locked' => $locked]);
        $this->form->set_data($question->edit_form_data());
    }

}
