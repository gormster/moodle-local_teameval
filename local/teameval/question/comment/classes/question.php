<?php

namespace teamevalquestion_comment;

use coding_exception;
use stdClass;
use renderer_base;
use local_teameval\team_evaluation;

use local_teameval\traits;

class question implements \local_teameval\question_response_preparing {

    use traits\question\no_value;

    public $id;

    protected $teameval;

    protected $_title;

    protected $_description;

    protected $_anonymous;

    protected $_optional;

    public function __construct(\local_teameval\team_evaluation $teameval, $questionid = null) {
        global $DB;

        $this->id               = $questionid;
        $this->teameval         = $teameval;

        if ($questionid > 0) {
            $record = $DB->get_record('teamevalquestion_comment', array("id" => $questionid));

            $this->_title            = $record->title;
            $this->_description      = $record->description;
            $this->_anonymous        = (bool)$record->anonymous;
            $this->_optional         = (bool)$record->optional;

        } else {

            // set defaults
            $this->_anonymous        = false;
            $this->_optional         = false;

        }
    }

    private static $name_keys = ["title", "description", "anonymous", "optional"];
    public function __get($name) {
        if (in_array($name, self::$name_keys)) {
            $priv = "_$name";
            return $this->$priv;
        }
        throw new coding_exception("Bad access ($name)");
    }

    public function submission_view($locked = false) {
        return new output\submission_view($this, $this->teameval, $locked);
    }

    public function editing_view() {
        return new output\editing_view($this->edit_form_data(), $this->any_response_submitted());
    }

    public function edit_form_data() {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'description' => ['text' => $this->description, 'format' => FORMAT_HTML],
        ];
        if ($this->anonymous) $data['anonymous'] = true;
        if ($this->optional) $data['optional'] = true;
        return $data;
    }

    public function context_data(renderer_base $output, $locked = false) {
        $context = new stdClass;

        if (team_evaluation::check_capability($this->teameval, ['local/teameval:createquestionnaire'])) {
            $context->editingcontext = $this->edit_form_data();
            $context->editinglocked = $this->any_response_submitted();
            $context->submissioncontext = $this->submission_view($locked)->export_for_template($output);
        } else if (team_evaluation::check_capability($this->teameval, ['local/teameval:createquestionnaire'], ['doanything' => false])) {
            $context->submissioncontext = $this->submission_view($locked)->export_for_template($output);
        }

        return $context;

    }

    public function any_response_submitted() {
        global $DB;
        return $DB->record_exists_select('teamevalquestion_comment_res', 'questionid = :questionid AND comment != :emptystring', ['questionid' => $this->id, 'emptystring' => '']);
    }

    public function plugin_name() {
        return 'comment';
    }

    public function has_completion() {
        return $this->optional == false;
    }

    public function has_feedback() {
        return true;
    }

    public function is_feedback_anonymous() {
        return $this->anonymous;
    }

    public function get_title() {
        return $this->title;
    }

    public static function supported_renderer_subtypes() {
        return ['plaintext'];
    }

    protected $response_data = [];
    public function prepare_responses($users) {
        global $DB;

        $unprepared = array_diff_key($users, $this->response_data);

        if (empty($unprepared)) {
            return;
        }

        // prepare_responses might be called more than once
        foreach ($unprepared as $uid => $_) {
            $this->response_data[$uid] = [];
        }

        list($sql, $params) = $DB->get_in_or_equal(array_keys($unprepared), SQL_PARAMS_NAMED, 'user');
        $recordset = $DB->get_recordset_select("teamevalquestion_comment_res", "questionid = :questionid AND fromuser $sql", ["questionid" => $this->id] + $params);

        foreach ($recordset as $record) {
            $this->response_data[$record->fromuser][$record->touser] = $record;
        }

    }

    public function get_response($userid) {
        if (isset($this->response_data[$userid])) {
            return new response($this->teameval, $this, $userid, $this->response_data[$userid]);
        }
        return new response($this->teameval, $this, $userid);
    }

    public static function delete_questions($ids) {
        global $DB;

        self::reset_userdata($ids);

        $DB->delete_records_list('teamevalquestion_comment', 'id', $ids);
    }

    public static function reset_userdata($ids) {
        global $DB;

        $DB->delete_records_list('teamevalquestion_comment_res', 'questionid', $ids);
    }

    public static function duplicate_question($questionid, $newteameval) {
        global $DB;

        $record = $DB->get_record('teamevalquestion_comment', ['id' => $questionid]);
        unset($record->id);
        $newid = $DB->insert_record('teamevalquestion_comment', $record);

        return $newid;
    }

}
