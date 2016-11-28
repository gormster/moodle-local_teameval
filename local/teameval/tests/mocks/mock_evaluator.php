<?php

use local_teameval\evaluator;
use local_teameval\team_evaluation;

class mock_evaluator implements evaluator {

    public $scores;

    public function __construct(team_evaluation $teameval, $responses) {

    }

    public function scores() {
        return $this->scores;
    }

    public static function install_mock($teameval) {
        $evaluator = new self($teameval, null);

        $reflection = new ReflectionClass(team_evaluation::class);
        $prop = $reflection->getProperty('evaluator');
        $prop->setAccessible(true);
        $prop->setValue($teameval, $evaluator);

        return $evaluator;
    }

}