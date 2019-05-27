<?php

use local_teameval\team_evaluation;
use local_teameval\question;
use local_teameval\response;

class mock_question implements question {

    private $_min = 0;
    private $_max = 5;
    private $_completion = true;
    private $_value = true;

    public $id;

    static $questions = [];
    private $underlying;

    // if true, duplication always fails
    static $failduplicate = false;

    public function __construct(team_evaluation $teameval, $questionid = null) {
        $this->id = $questionid;
        if (empty(static::$questions[$questionid])) {
            static::$questions[$questionid] = $this;
        }
        $this->underlying = static::$questions[$questionid];
    }

    public function __get($name) {
        if (in_array($name, ['min', 'max', 'completion', 'value'])) {
            $name = '_'.$name;
            return $this->underlying->$name;
        }
    }

    public function __set($name, $value) {
        if (in_array($name, ['min', 'max', 'completion', 'value'])) {
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
        return 'mock';
    }

    public function has_value() {
        return $this->underlying->value;
    }

    public function has_completion() {
        return $this->underlying->completion;
    }

    public function minimum_value() {
        return $this->underlying->min;
    }

    public function maximum_value() {
        return $this->underlying->max;
    }

    public function get_title() {
        return "Mock question";
    }

    public function has_feedback() {
        return false;
    }

    public function is_feedback_anonymous() {
        return false;
    }

    public static function duplicate_question($questionid, $newteameval) {
        if (static::$failduplicate) {
            return;
        }

        $question = static::$questions[$questionid];
        $id = max(array_keys(static::$questions));
        $id++;
        $q = new static($newteameval, $id);
        $q->min = $question->min;
        $q->max = $question->max;
        $q->completion = $question->completion;
        $q->value = $question->value;
        return $id;
    }

    public static function delete_questions($questionids) {
        static::clear_questions();
    }

    public static function reset_userdata($questionids) {
        mock_response::clear_responses();
    }

    public static function mock_question_plugininfo($phpunit) {

        $plugininfo = $phpunit->getMockBuilder(\local_teameval\plugininfo\teamevalquestion::class)
                              ->setMethods(['get_question_class', 'get_response_class'])
                              ->getMock();

        $plugininfo->type = 'teamevalquestion';
        $plugininfo->typerootdir = core_component::get_plugin_types()['teamevalquestion'];
        $plugininfo->name = 'mock';
        $plugininfo->rootdir = null; // there is no root directory

        $plugininfo->method('get_question_class')
            ->willReturn('mock_question');

        $plugininfo->method('get_response_class')
            ->willReturn('mock_response');

        return $plugininfo;

    }

    public static function clear_questions() {
        static::$questions = [];
    }

}

class mock_response implements response {

    public $question;

    private $_opinions = [];

    private $needed;

    static $responses = [];
    private $underlying;

    public $teameval;

    public function __construct(team_evaluation $teameval, $question, $userid) {

        $this->question = $question;
        $this->teameval = $teameval;
        $this->userid = $userid;

        $this->needed = count($teameval->teammates($userid));

        if (empty(static::$responses[$question->id])) {
            static::$responses[$question->id] = [];
        }
        if (empty(static::$responses[$question->id][$userid])) {
            static::$responses[$question->id][$userid] = $this;
        }

        $this->underlying = static::$responses[$question->id][$userid];

    }

    public function __get($name) {
        if (in_array($name, ['opinions'])) {
            $name = '_'.$name;
            return $this->underlying->$name;
        }
    }

    // public function __set($name, $value) {
    //     if (in_array($name, ['opinions'])) {
    //         $name = '_'.$name;
    //         $this->underlying->$name = $value;
    //     }
    // }

    /**
     * Update the opinions array, and optionally trigger did_submit_response
     * @param  [type]  $opinions  [description]
     * @param  boolean $didsubmit [description]
     * @return [type]             [description]
     */
    public function update_opinions($opinions, $didsubmit = true) {
        $this->underlying->_opinions = $opinions;
        if ($didsubmit) {
            $this->teameval->did_submit_response('mock', $this->question->id, $this->userid);
        }
    }

    public function marks_given() {
        return count(array_filter($this->underlying->opinions, function($x) { return !is_null($x); } )) == $this->needed;
    }

    public function opinion_of($userid) {
        return ($this->underlying->opinions[$userid] - $this->question->minimum_value()) / ($this->question->maximum_value() - $this->question->minimum_value());
    }

    public function opinion_of_readable($userid, $source = null) {

    }

    /**
     * Convenience function for turning array structure generated by markstoresponses.py into mock_response objects
     * @param team_evaluation $teameval
     * @param array $members [$groupid => [$userid => $user]]
     * @param array $questions array of mock_question objects in correct order
     * @param array $rawresponses nested array returned from markstoresponses.py
     * @return type
     */
    public static function get_responses($teameval, $members, $questions, $rawresponses) {

        // This next bit just walks the arrays in sync to create the actual
        // response objects.
        $responses = [];

        foreach($members as $groupid => $groupmembers) {
            $groupresponses = current($rawresponses);
            next($rawresponses);
            if (empty($groupresponses)) {
                continue;
            }

            foreach($groupmembers as $markerid => $marker) {
                $markerresponses = current($groupresponses);
                next($groupresponses);
                if (empty($markerresponses)) {
                    continue;
                }

                $responses[$markerid] = [];
                foreach($questions as $q => $question) {
                    $response = new mock_response($teameval, $question, $markerid);
                    $rawopinions = $markerresponses[$q];
                    if (empty($rawopinions)) {
                        continue;
                    }

                    $opinions = [];
                    foreach($groupmembers as $markedid => $marked) {
                        $v = current($rawopinions);
                        next($rawopinions);
                        $opinions[$markedid] = $v;
                    }
                    $response->update_opinions($opinions);
                    $responses[$markerid][] = $response;
                }
            }
        }

        return $responses;

    }

    public static function clear_responses() {
        static::$responses = [];
    }

}
