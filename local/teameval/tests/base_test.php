<?php

use local_teameval\team_evaluation;
use local_teameval\question_info;
use local_teameval\evaluation_context;

require_once(dirname(__FILE__) . '/mocks/mock_question.php');
require_once(dirname(__FILE__) . '/mocks/mock_evaluation_context.php');
require_once(dirname(__FILE__) . '/mocks/mock_evaluator.php');

trait local_teameval_base_testcase {

    protected $course;

    protected $assign;

    protected $teameval;

    protected $questions;

    protected $teacher;

    protected $students;

    protected $groups;

    protected $members;

    public function setUp() {

        $this->resetAfterTest();

        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();

        // we use assign because it's one of the default implementers
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $this->assign = $generator->create_instance(array('course'=>$this->course->id, 'teamsubmission' => true));

        team_evaluation::_clear_groups_members_cache();
        team_evaluation::_clear_response_cache();

        // make some users & some groups

        for($i = 0; $i < 3; $i++) {
            $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
            $this->groups[$group->id] = $group;
            $this->members[$group->id] = [];

            for($j = 0; $j < 5; $j++) {
                $user = $this->getDataGenerator()->create_user();
                $this->students[$user->id] = $user;

                $this->getDataGenerator()->enrol_user($user->id, $this->course->id);
                $this->getDataGenerator()->create_group_member(['userid' => $user->id, 'groupid' => $group->id]);
                $this->members[$group->id][$user->id] = $user;
            }

        }

        // make a teacher role

        $this->teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, 'teacher');

        mock_question::clear_questions();
        mock_response::clear_responses();

        $this->questions = [];

        $this->mock_teameval();

    }

    protected function add_questions($numQuestions = 3, $start = 0) {
        global $USER;

        for ($i = $start; $i < $start + $numQuestions; $i++) {
            $id = $i + 1;

            $tx = $this->teameval->should_update_question('mock', 0, $USER->id);
            $this->assertNotEmpty($tx);
            $this->questions[] = new mock_question($this->teameval, $id);
            $tx->id = $id;
            $this->teameval->update_question($tx, $i);
        }

    }

    protected function mock_teameval($methods = []) {
        // we make a teameval first, then we'll reload it as a mock

        $teameval = team_evaluation::from_cmid($this->assign->cmid);

        if (empty($methods)) {
            $methods = null;
        }

        $this->teameval = $this->getMockBuilder(testable_team_evaluation::class)
                               ->setMethods($methods)
                               ->setConstructorArgs([$this, $teameval->id])
                               ->getMock();

    }

    protected function add_responses($userid, $group, $questions, $opinions = [1,2,3,4,5]) {
        foreach($questions as $q) {
            $response = new mock_response($this->teameval, $q, $userid);
            $ops = [];
            reset($opinions);
            foreach($group as $to) {
                if (isset($opinions[$to->id])) {
                    $ops[$to->id] = $opinions[$to->id];
                } else {
                    $ops[$to->id] = current($opinions);
                }
                next($opinions);
            }
            $response->update_opinions($ops);
        }
    }

    protected function add_all_responses() {
        foreach ($this->members as $group) {
            foreach ($group as $student) {
                $this->add_responses($student->id, $group, $this->questions);
            }
        }
    }

}

class testable_team_evaluation extends team_evaluation {

    static private $phpunit;

    public function __construct($phpunit, $id) {
        self::$phpunit = $phpunit;
        parent::__construct($id);
    }

    public function get_question_plugins() {
        return ['mock' => mock_question::mock_question_plugininfo(self::$phpunit)];
    }

    public static function get_question_plugins_static() {
        return ['mock' => mock_question::mock_question_plugininfo(self::$phpunit)];
    }

}