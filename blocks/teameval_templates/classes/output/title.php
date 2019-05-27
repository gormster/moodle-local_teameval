<?php

namespace block_teameval_templates\output;

use renderable;
use templatable;
use stdClass;
use renderer_base;
use local_teameval\team_evaluation;

class title implements templatable, renderable {

    protected $title;
    protected $contextid;
    protected $editable;

    public function __construct(team_evaluation $teameval) {
        $this->editable = team_evaluation::check_capability($teameval, ['local/teameval:changesettings']);
        $this->title = $teameval->title;
        $this->id = $teameval->id;
    }

    public function export_for_template(renderer_base $output) {
        $c = new stdClass;
        $c->title = $this->title;
        $c->id = $this->id;
        $c->editable = $this->editable;
        return $c;
    }

}
