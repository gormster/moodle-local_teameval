<?php

use local_teameval\team_evaluation;
use local_teameval\question_info;
use local_teameval\evaluation_context;

require_once(dirname(__FILE__) . '/mocks/mock_question.php');
require_once(dirname(__FILE__) . '/mocks/mock_evaluation_context.php');

class local_teameval_submission_testcase extends advanced_testcase {

    private $course;

    private $assign;

    private $teameval;

    private $questions;

    private $teacher;

    private $students;

    private $groups;

    private $members;

    public function setUp() {

        $this->resetAfterTest();

        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();

        // we use assign because it's one of the default implementers
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $this->assign = $generator->create_instance(array('course'=>$this->course->id, 'teamsubmission' => true));

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

    }

    private function add_questions($numQuestions = 3) {
        global $USER;

        for ($i = 0; $i < $numQuestions; $i++) {
            $id = $i + 1;

            $tx = $this->teameval->should_update_question('mock', 0, $USER->id);
            $this->questions[] = new mock_question($this->teameval, $id);
            $tx->id = $id;
            $this->teameval->update_question($tx, $i);
        }

    }

    private function mock_teameval($methods = []) {
        // we make a teameval first, then we'll reload it as a mock

        $teameval = team_evaluation::from_cmid($this->assign->cmid);

        $methods = array_merge(['get_question_plugins'], $methods);

        $this->teameval = $this->getMock(team_evaluation::class, $methods, [$teameval->id]);

        $this->teameval->method('get_question_plugins')
            ->willReturn(['mock' => mock_question::mock_question_plugininfo($this)]);

    }

    public function test_user_completion() {

        $this->mock_teameval();

        $evalcontext = mock_evaluation_context::install_mock($this->teameval);

        // with no questions user completion should be 100% for everyone

        foreach($this->students as $id => $user) {
            $rslt = $this->teameval->user_completion($id);
            $this->assertEquals(1, $rslt);
        }

        $this->add_questions(3);

        foreach($this->students as $id => $user) {
            $rslt = $this->teameval->user_completion($id);
            $this->assertEquals(0, $rslt);
        }

        // pick someone and get them to respond

        $student = current(next($this->members));
        list($question0, $question1, $question2) = $this->questions;

        $response = new mock_response($this->teameval, $question0, $student->id);

        $response->opinions = [1,2,3,4,5];

        $rslt = $this->teameval->user_completion($student->id);
        $this->assertEquals(1/3.0, $rslt);

        $response = new mock_response($this->teameval, $question1, $student->id);
        $response->opinions = [1,2,3,4,5];
        $response = new mock_response($this->teameval, $question2, $student->id);
        $response->opinions = [1,2,3,4,5];

        $rslt = $this->teameval->user_completion($student->id);
        $this->assertEquals(1, $rslt);

    }

    public function test_group_ready() {

        $this->mock_teameval();

        $evalcontext = mock_evaluation_context::install_mock($this->teameval);

        // no questions, all groups are ready
        foreach($this->groups as $groupid => $group) {
            $rslt = $this->teameval->group_ready($groupid);
            $this->assertTrue($rslt);
        }

        $this->add_questions(3);
        foreach($this->groups as $groupid => $group) {
            $rslt = $this->teameval->group_ready($groupid);
            $this->assertFalse($rslt);
        }

        $rawresponses = [
            // Group A
            [
                [[1,1,1,1,1],[2,2,2,2,2],[3,3,3,3,3]],
                [[1,1,1,1,1],[2,2,2,2,2],[3,3,3,3,3]],
                [[1,1,1,1,1],[2,2,2,2,2],[3,3,3,3,3]],
                [[1,1,1,1,1],[2,2,2,2,2],[3,3,3,3,3]],
                [[1,1,1,1,1],[2,2,2,2,2],[3,3,3,3,3]]
            ],
            // Group B
            [
                [[1,1,1,1,1],[2,2,2,2,2],[3,3,3,3,3]],
                [[1,1,1,1,1],[2,2,2,2,2],[3,3,3,3,3]],
                [[1,1,1,1,1],[2,2,2,2,2],[3,3,3,3,3]],
                [[1,1,1,1,1],[2,2,2,2,2],[3,3,3,3,3]],
                [[1,1,1,1,1],[2,2,2,2,2],[]]
            ],
            // Group C
            []
        ];

        $responses = mock_response::get_responses($this->teameval, $this->members, $this->questions, $rawresponses);

        $groupA = reset($this->groups);
        $groupB = next($this->groups);
        $groupC = next($this->groups);

        $rslt = $this->teameval->group_ready($groupA->id);
        $this->assertTrue($rslt);
        
        $rslt = $this->teameval->group_ready($groupB->id);
        $this->assertFalse($rslt);

        $question3 = end($this->questions);
        $student5 = end($this->members[$groupB->id]);
        $response = new mock_response($this->teameval, $question3, $student5->id);
        $response->opinions = [3,3,3,3,3];

        $rslt = $this->teameval->group_ready($groupB->id);
        $this->assertTrue($rslt);

        $rslt = $this->teameval->group_ready($groupC->id);
        $this->assertFalse($rslt);

    }

}