<?php

namespace teamevalquestion_split100;

use coding_exception;
use stdClass;
use renderer_base;
use local_teameval\team_evaluation;

class response implements \local_teameval\response {

    protected $splits;

    protected $teameval;

    protected $question;

    protected $userid;

    protected $_marks_given;

    public function __construct(team_evaluation $teameval, $question, $userid, $records = null) {
        global $DB;

        $this->teameval = $teameval;
        $this->question = $question;
        $this->userid = $userid;

        $this->splits = [];

        if (is_null($records)) {
            $records = $DB->get_records('teamevalquestion_split100_rs', ['questionid' => $question->id, 'fromuser' => $userid]);
        }

        if (count($records) == 0) {
            $this->_marks_given = false;
        } else {
            foreach ($records as $record) {
                $this->splits[$record->touser] = $record->pct;
            }
            $this->_marks_given = true;
        }

        $this->fix_marks();
    }

    public function __get($name) {
        if ($name == 'question') {
            return $this->$name;
        }
        throw new coding_exception('Property "' . $name . '" doesn\'t exist');
    }

    // since there's no such thing as a partially complete split100, just check if there's any response at all
    public function marks_given() {
        return $this->_marks_given;
    }

    public function opinion_of($userid) {
        if ($this->_marks_given) {
            return $this->splits[$userid];
        }
        return null;
    }

    public function opinion_of_readable($userid, $source = null) {
        if ($this->_marks_given) {
            return new output\opinion($this->splits[$userid]);
        }
        return new output\opinion();
    }

    public function update_splits($splits) {
        global $DB;

        $this->splits = $splits;
        $this->fix_marks();

        $records = $DB->get_records('teamevalquestion_split100_rs', ['questionid' => $this->question->id, 'fromuser' => $this->userid], '', 'touser, id');
        foreach ($this->splits as $userid => $pct) {
            if(isset($records[$userid])) {
                $record = $records[$userid];
                $record->pct = $pct;
                $DB->update_record('teamevalquestion_split100_rs', $record);
            } else {
                $record = new stdClass;
                $record->questionid = $this->question->id;
                $record->fromuser = $this->userid;
                $record->touser = $userid;
                $record->pct = $pct;
                $DB->insert_record('teamevalquestion_split100_rs', $record);
            }
        }
    }

    private function fix_marks() {
        if ($this->marks_given()) {
            $teammates = $this->teameval->teammates($this->userid);

            $total = 0;
            foreach ($this->splits as $id => $split) {
                if (empty($teammates[$id])) {
                    unset($this->splits[$id]);
                } else {
                    $total += $split;
                }
            }

            $missing = array_diff_key($teammates, $this->splits);

            if (count($missing) > 0) {

                $pct = (100 - $total) / count($missing);

                foreach ($missing as $userid => $user) {
                    $this->splits[$userid] = $pct;
                }

            } else if ($total != 100) {

                $scale = 100 / $total;

                foreach ($this->splits as $id => $split) {
                    $this->splits[$id] = $split * $scale;
                }

            }
        }
    }

}
