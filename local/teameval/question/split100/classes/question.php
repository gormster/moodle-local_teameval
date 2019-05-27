<?php

namespace teamevalquestion_split100;

use coding_exception;
use renderer_base;
use local_teameval\team_evaluation;

define('MIN_SIZE', 5);

class question implements \local_teameval\question_response_preparing {

    protected $id;

    protected $teameval;

    protected $title;

    protected $description;

    public function __construct(team_evaluation $teameval, $questionid = null) {
        global $DB;
        $this->teameval = $teameval;
        $this->id = $questionid;

        if ($questionid) {
            $record = $DB->get_record('teamevalquestion_split100', ['id' => $questionid]);
            if ($record) {
                $this->title = $record->title;
                $this->description = $record->description;
            }
        }
    }

    private static $name_keys = ['id', 'title', 'description'];
    public function __get($name) {
        if (in_array($name, self::$name_keys)) {
            return $this->$name;
        }
        throw new coding_exception('Property "' . $name . '" doesn\'t exist');
    }

    public function submission_view($locked = false) {
        return new output\submission_view($this, $this->teameval, $locked);
    }

    public function editing_view() {
        return new output\editing_view($this, $this->teameval);
    }

    public function context_data(renderer_base $output, $locked = false) {
        return $this->submission_view($locked)->export_for_template($output);
    }

    // Convert between display width (in percentage) and real percentage values
    // In display widths, a 0% score means a 5% width
    public static function display_to_real($display, $n) {
        $m = 100 / (100 - MIN_SIZE * $n);
        return $m * $display - (MIN_SIZE * m);
    }

    public static function real_to_display($real, $n) {
        $m = 100 / (100 - MIN_SIZE * $n);
        return ($real + MIN_SIZE * $m) / $m;
    }

    public function plugin_name() {
        return 'split100';
    }

    public function has_value() {
        return true;
    }

    public function has_completion() {
        return true;
    }

    public function minimum_value() {
        return 0;
    }

    public function maximum_value() {
        return 100;
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
        $recordset = $DB->get_recordset_select("teamevalquestion_split100_rs", "questionid = :questionid AND fromuser $sql", ["questionid" => $this->id] + $params);

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

    public static function duplicate_question($questionid, $newteameval) {
        global $DB;

        $record = $DB->get_record('teamevalquestion_split100', ['id' => $questionid]);
        unset($record->id);

        return $DB->insert_record('teamevalquestion_split100', $record);
    }

    public static function delete_questions($questionids) {
        global $DB;

        $DB->delete_records_list('teamevalquestion_split100', 'id', $questionids);
        $DB->delete_records_list('teamevalquestion_split100_rs', 'questionid', $questionids);
    }

    public static function reset_userdata($questionids) {
        global $DB;
        $DB->delete_records_list('teamevalquestion_split100_rs', 'questionid', $questionids);
    }

}
