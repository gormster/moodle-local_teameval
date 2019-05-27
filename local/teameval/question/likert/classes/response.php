<?php

namespace teamevalquestion_likert;

use stdClass;
use local_teameval\team_evaluation;
use coding_exception;

class response implements \local_teameval\response {

    public $question;

    protected $teameval;
    protected $userid;
    protected $responses;
    protected $teammates;

    public function __construct(team_evaluation $teameval, $question, $userid, $responses = null) {
        global $DB;

        $this->teameval = $teameval;
        $this->question = $question;
        $this->userid = $userid;
        $this->teammates = $teameval->teammates($userid);

        if (is_null($responses)) {
            $records = $DB->get_records("teamevalquestion_likert_resp", array("questionid" => $question->id, "fromuser" => $userid), '', 'id,touser,mark,markdate');

            //rearrange responses to be keyed by touser
            $this->responses = [];
            foreach($records as $r) {
                $this->responses[$r->touser] = $r;
            }
        } else {
            $this->responses = $responses;
        }

        $this->fix_responses();

    }

    /**
     * Update responses from given user data
     * @param array $formdata userid => mark
     * @return null
     */
    public function update_response($formdata) {
        global $DB;

        foreach($formdata as $userid => $mark) {
            if (isset($this->responses[$userid])) {
                $record = $this->responses[$userid];
                $record->mark = $mark;
                $record->markdate = time();
                $DB->update_record("teamevalquestion_likert_resp", $record);
                $this->responses[$userid] = $record;
            } else {
                $record = new stdClass;
                $record->fromuser = $this->userid;
                $record->questionid = $this->question->id;
                $record->touser = $userid;
                $record->mark = $mark;
                $record->markdate = time();
                $id = $DB->insert_record("teamevalquestion_likert_resp", $record);
                $record->id = $id;
                $this->responses[$userid] = $record;
            }
        }
    }

    public function raw_marks() {
        $context = [];
        foreach($this->responses as $k => $r) {
            $context[$k] = $r->mark;
        }
        return $context;
    }

    public function marks_given() {
        return count($this->responses) >= count($this->teammates) ? true : false;
    }

    public function opinion_of($userid) {

        $minval = $this->question->minimum_value();
        $maxval = $this->question->maximum_value();

        return ($this->responses[$userid]->mark - $minval) / ($maxval - $minval);
    }

    public function opinion_of_readable($userid, $source = null) {

        $val = null;
        $max = null;
        if (isset($this->responses[$userid])) {
            $val = $this->responses[$userid]->mark;
            $max = $this->question->maximum_value();
        }
        return new output\opinion_readable($val, $max);
    }

    /**
     * Constrains the given responses to the actual teammates of this user
     */
    protected function fix_responses() {
        if (count($this->responses) > 0) {
            $teammates = $this->teameval->teammates($this->userid);
            foreach($this->responses as $k => $v) {
                if (!isset($teammates[$k])) {
                    unset($this->responses[$k]);
                }
            }
        }
    }

}
