<?php

namespace teamevalquestion_comment;

use stdClass;

class response implements \local_teameval\response_feedback {

    public $question;

    protected $teameval;

    protected $userid;

    protected $comments;

    public function __construct(\local_teameval\team_evaluation $teameval, $question, $userid, $comments = null) {
        global $DB;

        $this->userid = $userid;
        $this->question = $question;
        $this->teameval = $teameval;

        if (is_null($comments)) {
            $comments = $DB->get_records('teamevalquestion_comment_res', ['fromuser' => $userid, 'questionid' => $question->id]);
            $this->comments = [];
            foreach ($comments as $comment) {
                $this->comments[$comment->touser] = $comment;
            }
        } else {
            $this->comments = $comments;
        }

        $this->fix_responses();
    }

    public function marks_given() {
        return (count($this->comments) > 0);
    }

    /**
     * Set comments
     * @param [int => string] $comments userid => comment text
     */
    public function update_comments($comments) {
        global $DB;

        foreach($comments as $touser => $comment) {
            if (isset($this->comments[$touser])) {
                $record = $this->comments[$touser];
                $record->comment = $comment;
                $DB->update_record('teamevalquestion_comment_res', $record);
            } else {
                $record = new stdClass;
                $record->questionid = $this->question->id;
                $record->fromuser = $this->userid;
                $record->touser = $touser;
                $record->comment = $comment;
                $DB->insert_record('teamevalquestion_comment_res', $record);
            }
        }
    }

    public function comment_on($userid) {
        if (isset($this->comments[$userid])) {
            return $this->comments[$userid]->comment;
        }
        return null;
    }

    public function opinion_of($userid) {
        return null;
    }

    public function opinion_of_readable($userid, $source = null) {
        if ($source == 'teamevalreport_responses') {
            $comment = null;
            if (isset($this->comments[$userid])) {
                $comment = $this->comments[$userid]->comment;
            }
            return new output\opinion_readable_short($comment);
        }
        return $this->feedback_for_readable($userid);
    }

    public function feedback_for($userid) {
        // todo: clean up HTML in this
        return $this->comment_on($userid);
    }

    public function feedback_for_readable($userid) {
        $comment = $this->comment_on($userid);

        return new output\feedback_readable($this->userid, $userid, $comment);
    }

    /**
     * Constrains the given responses to the actual teammates of this user
     */
    protected function fix_responses() {
        if ($this->marks_given()) {
            $teammates = $this->teameval->teammates($this->userid);
            foreach($this->comments as $k => $v) {
                if (!isset($teammates[$k])) {
                    unset($this->comments[$k]);
                }
            }
        }
    }

}
