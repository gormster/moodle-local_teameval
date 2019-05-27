<?php

namespace teamevaluator_loughborough;

use local_teameval\team_evaluation;

class evaluator implements \local_teameval\evaluator {

    protected $teameval;

    protected $responses;

    public function __construct(team_evaluation $teameval, $responses) {

        $this->teameval = $teameval;
        $this->responses = $responses;

    }

    private $_scores;
    public function scores() {

        if (isset($this->_scores)) {
            return $this->_scores;
        }

        $user_opinions = [];

        foreach ($this->responses as $userid => $responses) {
            $user_opinions[$userid] = [];
            foreach($responses as $response) {
                if ($response->question->has_value() && $response->marks_given()) {
                    $key = $response->question->plugin_name() . "/{$response->question->id}";
                    $user_opinions[$userid][$key] = [];
                    $teammates = $this->teameval->teammates($userid);
                    foreach($teammates as $teammate) {
                        $user_opinions[$userid][$key][$teammate->id] = $response->opinion_of($teammate->id);
                    }
                }
            }
        }

        //1. Find the maximum value given by each of our users.

        $max_scores = [];

        foreach($user_opinions as $userid => $opinions) {
            if (count($opinions) == 0) {
                continue;
            }

            $max_scores[$userid] = [];

            foreach($opinions as $question => $scores) {
                $max_score = array_sum($scores);

                if ($max_score == 0) { // we need to avoid a divide by zero. all zeroes = all ones so do that instead.
                    $max_score = count($scores);
                    foreach(array_keys($scores) as $k) {
                        $user_opinions[$userid][$question][$k] = 1;
                    }
                }

                $max_scores[$userid][$question] = $max_score;
            }

        }

        //2. Get the number of users marked vs. the number of users who marked them

        $user_team_count = [];
        $user_marked_count = [];

        foreach($user_opinions as $userid => $opinions) {

            $user_team_count[$userid] = count($this->teameval->teammates($userid));

            foreach($opinions as $question => $scores) {
                foreach($scores as $markeduser => $score) {

                    if (! isset($user_marked_count[$markeduser])) {
                        $user_marked_count[$markeduser] = [];
                    }

                    if (! isset($user_marked_count[$markeduser][$question])) {
                        $user_marked_count[$markeduser][$question] = 0;
                    }

                    $user_marked_count[$markeduser][$question] += 1;
                }
            }
        }

        //3. Get the scores for each user

        $user_scores = [];

        foreach($user_opinions as $userid => $opinions) {
            if (count($opinions) == 0) {
                continue;
            }

            foreach($opinions as $question => $scores) {
                $max_score = $max_scores[$userid][$question];

                foreach($scores as $markeduser => $score) {

                    if (!isset($user_scores[$markeduser])) {
                        $user_scores[$markeduser] = 0.0;
                    }

                    $fudge = $user_team_count[$markeduser] / $user_marked_count[$markeduser][$question];

                    $user_scores[$markeduser] += $fudge * ($score / $max_score);
                }
            }
        }

        //4. Divide through by the number of questions

        $final_scores = [];

        foreach($user_scores as $markeduser => $score) {
            $question_count = count($user_marked_count[$markeduser]);
            $final_scores[$markeduser] = $score / $question_count;
        }

        $this->_scores = $final_scores;

        return $final_scores;

    }



}
