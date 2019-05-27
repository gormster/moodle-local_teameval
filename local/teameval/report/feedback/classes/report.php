<?php

namespace teamevalreport_feedback;

use stdClass;
use local_teameval\traits;

class report implements \local_teameval\report {

    use traits\report\delegated_export;

    protected $teameval;

    public function __construct(\local_teameval\team_evaluation $teameval) {

        $this->teameval = $teameval;

    }

    public function generate_report() {

        $questions = $this->teameval->get_questions();

        $feedback_questions = [];

        foreach($questions as $q) {
            if ($q->question->has_feedback()) {
                $feedback_questions[] = $q;
            }
        }

        $groups = $this->teameval->get_evaluation_context()->all_groups();

        return new output\feedback_report($this->teameval, $groups, $feedback_questions);

    }

}
