<?php

namespace teamevalquestion_comment\output;

use teamevalquestion_comment\question;
use local_teameval\team_evaluation;
use renderable;
use templatable;
use stdClass;
use renderer_base;

class editing_view implements renderable {

    function __construct($formdata, $locked = false) {
        $this->formdata = $formdata;
        $this->locked = $locked;
    }

}
