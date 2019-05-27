<?php

namespace teamevalreport_scores\output;

use core_user;
use stdClass;
use user_picture;

class scores_report implements \renderable, \templatable {

    public $scores;

    public function __construct($data) {
        global $DB;
        $display_scores = [];

        $users = $DB->get_records_list('user', 'id', array_keys($data), '', user_picture::fields());
        foreach($data as $userid => $datum) {
            $user = $users[$userid];
            $c = clone $datum;
            $c->user = $user;
            $display_scores[] = $c;
        }
        $this->scores = $display_scores;
    }

    public function export_for_template(\renderer_base $output) {
        $ctx = [];
        foreach($this->scores as $score) {
            $userpic = $output->render(new user_picture($score->user));
            $fullname = fullname($score->user);
            $group = $score->group->name;
            $completion = round($score->completion * 100, 2) . '%';
            $evalscore = isset($score->score) ? round($score->score, 2) : '-';
            $intergrade = isset($score->intermediategrade) ? $score->intermediategrade : '-';
            $noncomplete = !empty($score->noncompletionpenalty) ? round($score->noncompletionpenalty * 100, 2) . '%' : '-';
            $finalgrade = isset($score->finalgrade) ? $score->finalgrade : '-';
            $ctx[] = ['userpic' => $userpic, 'fullname' => $fullname, 'group' => $group, 'completion' => $completion, 'score' => $evalscore, 'intermediategrade' => $intergrade, 'noncompletionpenalty' => $noncomplete, 'finalgrade' => $finalgrade];
        }
        return ['scores' => $ctx];
    }

}
