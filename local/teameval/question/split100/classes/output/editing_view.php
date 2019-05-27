<?php

namespace teamevalquestion_split100\output;

use teamevalquestion_split100\question;
use teamevalquestion_split100\forms\edit_form;
use renderable;
use stdClass;
use renderer_base;

class editing_view implements renderable {

    function __construct(question $question, $locked) {
        $this->form = new edit_form(null, ['locked' => $locked]);
        $this->form->set_data($question->edit_form_data());
    }

}
