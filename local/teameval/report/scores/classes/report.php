<?php

namespace teamevalreport_scores;

use stdClass;
use local_teameval;
use local_teameval\traits;

class report implements local_teameval\report {

    use traits\report\delegated_export;

    protected $teameval;

    public function __construct(\local_teameval\team_evaluation $teameval) {

        $this->teameval = $teameval;

    }

    public function generate_report() {
        $scores = $this->teameval->get_evaluator()->scores();
        $evalcontext = $this->teameval->get_evaluation_context();

        $data = [];
        foreach ($scores as $uid => $score) {
        	$group = $this->teameval->get_evaluation_context()->group_for_user($uid);
            $datum = new stdClass;
            $datum->group = $group;
            $datum->completion = $this->teameval->user_completion($uid);

            if ($this->teameval->deadline_passed() || $this->teameval->group_ready($group->id)) {
                // only show scores for groups who are finished, or if the deadline has passed
                $datum->score = $score;

            	$grade = $this->teameval->get_evaluation_context()->grade_for_group($group->id);
                $datum->grade = $grade;

                if (!is_null($grade)) {
                	$fraction = $this->teameval->get_settings()->fraction;
                	$multiplier = (1 - $fraction) + ($score * $fraction);
                	$intermediategrade = $grade * $multiplier;
                	$noncompletionpenalty = $this->teameval->non_completion_penalty($uid);
                	$finalgrade = $grade * $this->teameval->multiplier_for_user($uid);
            	
                	$datum->intermediategrade = $evalcontext->format_grade($intermediategrade);
                	$datum->noncompletionpenalty = $noncompletionpenalty;
                	$datum->finalgrade = $evalcontext->format_grade($finalgrade);
                }
            }

        	$data[$uid] = $datum;
        }

        return new output\scores_report($data);
    }

}