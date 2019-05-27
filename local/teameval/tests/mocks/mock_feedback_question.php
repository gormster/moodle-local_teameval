<?php

use local_teameval\team_evaluation;
use local_teameval\question;
use local_teameval\response;
use local_teameval\response_feedback;

require_once('mock_question.php');

class mock_feedback_question implements question {

    private $_anon = false;
    private $_feedback = true;

    public $id;

    static $questions = [];
    private $underlying;

    public function __construct(team_evaluation $teameval, $questionid = null) {
        $this->id = $questionid;
        if (empty(self::$questions[$questionid])) {
            self::$questions[$questionid] = $this;
        }
        $this->underlying = self::$questions[$questionid];
    }

    public function __get($name) {
        if (in_array($name, ['anon', 'feedback'])) {
            $name = '_'.$name;
            return $this->underlying->$name;
        }
    }

    public function __set($name, $value) {
        if (in_array($name, ['anon', 'feedback'])) {
            $name = '_'.$name;
            $this->underlying->$name = $value;
        }
    }

    public function submission_view($locked = false) {

    }

    public function editing_view() {

    }

    public function context_data(\renderer_base $output, $locked = false) {

    }

    public function plugin_name() {
        return 'mockback';
    }

    public function has_value() {
        return false;
    }

    public function has_completion() {
        return true;
    }

    public function minimum_value() {

    }

    public function maximum_value() {

    }

    public function get_title() {
        return "Mock feedback question";
    }

    public function has_feedback() {
        return $this->underlying->feedback;
    }

    public function is_feedback_anonymous() {
        return $this->underlying->anon;
    }

    public static function duplicate_question($questionid, $newteameval) {

    }

    public static function delete_questions($questionids) {

    }

    public static function reset_userdata($questionids) {

    }

    public static function mock_question_plugininfo($phpunit) {

        $plugininfo = $phpunit->getMockBuilder(\local_teameval\plugininfo\teamevalquestion::class)
                              ->setMethods(['get_question_class', 'get_response_class'])
                              ->getMock();

        $plugininfo->type = 'teamevalquestion';
        $plugininfo->typerootdir = core_component::get_plugin_types()['teamevalquestion'];
        $plugininfo->name = 'mockback';
        $plugininfo->rootdir = null; // there is no root directory

        $plugininfo->method('get_question_class')
            ->willReturn('mock_feedback_question');

        $plugininfo->method('get_response_class')
            ->willReturn('mock_feedback_response');

        return $plugininfo;

    }

    public static function clear_questions() {
        self::$questions = [];
    }

}

class mock_feedback_response extends mock_response implements response_feedback, renderable {

    static $responses = [];

    public function opinion_of_readable($userid, $source = null) {
        return $this;
    }

    public function feedback_for($userid) {
        return isset($this->opinions[$userid]) ? $this->opinions[$userid] : null;
    }

    public function feedback_for_readable($userid) {
        return $this;
    }

}