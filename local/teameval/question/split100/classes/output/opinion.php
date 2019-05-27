<?php

namespace teamevalquestion_split100\output;

use local_teameval\templatable;

class opinion extends templatable implements \renderable {

    public $mark;

    function __construct($mark = null) {
        $this->emtpy = is_null($mark);
        $this->mark = round($mark, 2);
    }

    function export_for_template(\renderer_base $output) {
        return $this;
    }

}
