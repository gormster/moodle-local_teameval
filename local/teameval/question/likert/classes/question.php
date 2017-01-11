<?php

namespace teamevalquestion_likert;

use coding_exception;
use stdClass;
use renderer_base;
use local_teameval\team_evaluation;
    
class question implements \local_teameval\question {
    
    public $id;
    
    protected $teameval;
    protected $_title;
    protected $_description;
    protected $_minval;
    protected $_maxval;
    protected $_meanings;

    public function __construct(team_evaluation $teameval, $questionid = null) {
        global $DB;

        $this->id               = $questionid;
        $this->teameval         = $teameval;

        if ($questionid > 0) {
            $record = $DB->get_record('teamevalquestion_likert', array("id" => $questionid));

            $this->_title            = $record->title;
            $this->_description      = $record->description;
            $this->_minval           = $record->minval;
            $this->_maxval           = $record->maxval;
            $this->_meanings         = json_decode($record->meanings);
        }
    }
    
    public function __get($name) {
        if (in_array($name, ["title", "description", "minval", "maxval", "meanings"])) {
            $priv = "_$name";
            return $this->$priv;
        }
        throw new coding_exception("Bad access ($name)");
    }

    public function submission_view($locked = false) {
        return new output\submission_view($this, $this->teameval, $locked);
    }
    
    public function editing_view() {
        return new output\editing_view($this, $this->any_response_submitted());
    }

    public function edit_form_data() {
        $data = new stdClass;

        $data->id = $this->id;
        $data->teameval = $this->teameval->id;
        $data->title = $this->title;
        $data->description = ['text' => $this->description, 'format' => FORMAT_HTML];
        $data->range = ['min' => $this->minval, 'max' => $this->maxval];
        $data->meanings = $this->meanings;

        return $data;
    }

    public function context_data(renderer_base $output, $locked = false) {
        $context = new stdClass;

        if (team_evaluation::check_capability($this->teameval, ['local/teameval:createquestionnaire'])) {
            $context->editingcontext = $this->edit_form_data();
            $context->editinglocked = $this->any_response_submitted();
            $context->submissioncontext = $this->submission_view($locked)->export_for_template($output);
        } else if (team_evaluation::check_capability($this->teameval, ['local/teameval:submitquestionnaire'], ['doanything' => false])) {
            $context->submissioncontext = $this->submission_view($locked)->export_for_template($output);
        }

        return $context;
        
    }

    public function any_response_submitted() {
        global $DB;
        return $DB->record_exists('teamevalquestion_likert_resp', ['questionid' => $this->id]);
    }

    public function plugin_name() {
        return 'likert';
    }

    public function has_value() {
        return true;
    }

    public function has_completion() {
        return true;
    }

    public function minimum_value() {
        return 0; // even if $minval == 1, return 0; it's what users expect
    }

    public function maximum_value() {
        return $this->maxval;
    }
    
    public function get_title() {
        return $this->title;
    }

    public function has_feedback() {
        return false;
    }

    public function is_feedback_anonymous() {
        return false;
    }

    public static function supported_renderer_subtypes() {
        return ['csv', 'plaintext'];
    }

    public static function duplicate_question($questionid, $newteameval) {
        global $DB;

        $record = $DB->get_record('teamevalquestion_likert', ['id' => $questionid]);
        unset($record->id);
        $newid = $DB->insert_record('teamevalquestion_likert', $record);

        return $newid;
    }

    public static function delete_questions($ids) {
        global $DB;

        self::reset_userdata($ids);

        $DB->delete_records_list('teamevalquestion_likert', 'id', $ids);
    }

    public static function reset_userdata($ids) {
        global $DB;

        $DB->delete_records_list('teamevalquestion_likert_resp', 'questionid', $ids);
    }
    
}