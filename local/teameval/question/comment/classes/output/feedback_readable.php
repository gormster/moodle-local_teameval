<?php

namespace teamevalquestion_comment\output;

use stdClass;
use renderable;
use local_teameval\templatable;
use renderer_base;

class feedback_readable extends templatable implements renderable {

    protected $from;

    protected $to;

    protected $comment;

    public function __construct($from, $to, $comment) {
        $this->from = $from;
        $this->to = $to;
        $this->comment = $comment;
    }

    public function export_for_template(renderer_base $output) {
        $c = new stdClass;
        $c->from = $this->from;
        $c->to = $this->to;
        $c->comment = $this->comment;
        return $c;
    }

}
