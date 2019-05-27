<?php

use local_teameval\team_evaluation;
use local_teameval\question_info;

class mock_question_info extends question_info {
    public function set_plugininfo($plugininfo) {
        return $this->_plugininfo = $plugininfo;
    }

    public function set_question($question) {
        return $this->_question = $question;
    }
}