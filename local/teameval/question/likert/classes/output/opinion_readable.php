<?php

namespace teamevalquestion_likert\output;

use stdClass;
use renderable;
use local_teameval\templatable;
use renderer_base;
use teamevalquestion_likert;

class opinion_readable extends templatable implements renderable {

    protected $val;
    protected $max;

    public function __construct($val, $max) {
        $this->val = $val;
        $this->max = $max;
    }

    public function export_for_template(renderer_base $output) {
        $c = new stdClass;
        $c->mark = !is_null($this->val);
        $c->val = $this->val;
        $c->max = $this->max;
        return $c;
    }

    public function export_for_plaintext() {
        return "$this->val / $this->max";
    }

    public function export_for_csv() {
        return $this->val;
    }

}
