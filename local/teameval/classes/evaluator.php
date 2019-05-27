<?php

namespace local_teameval;

interface evaluator {

    /**
     * Constructor.
     * @param team_evaluation $teameval The team evaluation object this evaluator is evaluating
     * @param array $responses [userid => [response object]]
     */
    public function __construct(team_evaluation $teameval, $responses);

    /**
     * The team evaluator scores, which are the basis for adjusting marks.
     * @return array [userid => float]
     */
    public function scores();

}
