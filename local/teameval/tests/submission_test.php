<?php

use local_teameval\team_evaluation;
use local_teameval\question_info;
use local_teameval\evaluation_context;

require_once(dirname(__FILE__) . '/mocks/mock_question.php');
require_once(dirname(__FILE__) . '/mocks/mock_evaluation_context.php');
require_once(dirname(__FILE__) . '/mocks/mock_evaluator.php');

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

        team_evaluation::_clear_groups_members_cache();

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

    private function add_questions($numQuestions = 3, $start = 0) {
        global $USER;

        for ($i = $start; $i < $start + $numQuestions; $i++) {
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

    private function add_responses($userid, $questions, $opinions = [1,2,3,4,5]) {
        foreach($questions as $q) {
            $response = new mock_response($this->teameval, $q, $userid);
            $response->opinions = $opinions;
        }
    }

    public function test_user_completion() {

        $evalcontext = mock_evaluation_context::install_mock($this->teameval);

        // with no questions user completion should be 100% for everyone

        foreach($this->students as $id => $user) {
            $rslt = $this->teameval->user_completion($id);
            $this->assertEquals(1, $rslt);
        }

        // now add a question with no completion
        
        $this->add_questions(1);

        $question = current($this->questions);

        $question->completion = false;
        $question->value = false;

        // user completion should still be 100%
        
        foreach($this->students as $id => $user) {
            $rslt = $this->teameval->user_completion($id);
            $this->assertEquals(1, $rslt);
        }

        $this->add_questions(3, 1);

        foreach($this->students as $id => $user) {
            $rslt = $this->teameval->user_completion($id);
            $this->assertEquals(0, $rslt);
        }

        // pick someone and get them to respond

        $student = current(next($this->members));
        list(, $question0, $question1, $question2) = $this->questions;

        $this->add_responses($student->id, [$question0]);

        $rslt = $this->teameval->user_completion($student->id);
        $this->assertEquals(1/3.0, $rslt);

        $this->add_responses($student->id, [$question1, $question2]);

        $rslt = $this->teameval->user_completion($student->id);
        $this->assertEquals(1, $rslt);

    }

    public function test_group_ready() {

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

    public function test_multipliers() {

        $mock_scores = [1.53,1.23,1.65,0.67,0.21,0.34,1.42,0.46,0.75,1.61,1.60,1.00,0.83,0.93,0.64];
        $scores = [];
        foreach(array_map(null, $this->students, $mock_scores) as list($user, $score)) {
            $scores[$user->id] = $score;
        }

        $evaluator = mock_evaluator::install_mock($this->teameval);
        $evaluator->scores = ($scores);

        $settings = new stdClass;
        $settings->noncompletionpenalty = 0;
        $settings->fraction = 1;
        $this->teameval->update_settings($settings);

        $multis = $this->teameval->multipliers();

        // multis should be equal to scores
        
        foreach ($multis as $key => $value) {
            $this->assertEquals($value, $scores[$key]);
        }

        // now add a non completion penalty
        
        $this->add_questions();

        $settings->noncompletionpenalty = 0.1;
        $settings->fraction = 0.5;
        $this->teameval->update_settings($settings);

        $multis = $this->teameval->multipliers();
        foreach ($multis as $key => $value) {
            $this->assertEquals($value, ($scores[$key] * 0.5 + 0.5) - 0.1);
        }

        // now get a user to fill out the questionnaire        
        
        list($question0, $question1, $question2) = $this->questions;

        $group = reset($this->groups);
        $user = reset($this->members[$group->id]);
        $id = $user->id;

        $this->add_responses($id, [$question0]);
        $value = $this->teameval->multiplier_for_user($id);
        $this->assertEquals($value, ($scores[$id] * 0.5 + 0.5) - (0.1 * 2 / 3));

        $multis = $this->teameval->multipliers();
        $this->assertEquals($multis[$id], $value);

        $multis = $this->teameval->multipliers_for_group($group->id);
        $this->assertEquals($multis[$id], $value);        

        $this->add_responses($id, [$question1]);
        $value = $this->teameval->multiplier_for_user($id);
        $this->assertEquals($value, ($scores[$id] * 0.5 + 0.5) - (0.1 * 1 / 3));

        $this->add_responses($id, [$question2]);
        $value = $this->teameval->multiplier_for_user($id);
        $this->assertEquals($value, ($scores[$id] * 0.5 + 0.5));

        // try and get a score for someone not in the teameval
        
        $notascore = $this->teameval->multiplier_for_user($this->teacher->id);
        $this->assertNull($notascore);

    }

    public function test_teammates() {

        $group = reset($this->groups);
        $members = $this->members[$group->id];
        $user = reset($members);

        $teammates = $this->teameval->teammates($user->id);

        $self = current($teammates);
        $this->assertEquals($user->id, $self->id);

        $this->assertEquals(count($members), count($teammates));
        foreach($teammates as $t) {
            $this->assertArrayHasKey($t->id, $members);
        }

        $teammates = $this->teameval->teammates($user->id, false);

        $this->assertEquals(count($teammates), count($members) - 1);
        foreach($teammates as $t) {
            $this->assertArrayHasKey($t->id, $members);
        }

        $this->assertNotContains($user->id, array_keys($teammates));

        $settings = new stdClass;
        $settings->self = false;
        $this->teameval->update_settings($settings);

        $teammates2 = $this->teameval->teammates($user->id);

        $this->assertEquals($teammates, $teammates2);

    }

    public function test_adjusted_grade() {

        $mock_scores = [1.53,1.23,1.65,0.67,0.21,0.34,1.42,0.46,0.75,1.61,1.60,1.00,0.83,0.93,0.64];
        $scores = [];
        foreach(array_map(null, $this->students, $mock_scores) as list($user, $score)) {
            $scores[$user->id] = $score;
        }

        $evaluator = mock_evaluator::install_mock($this->teameval);
        $evaluator->scores = ($scores);

        $evalcontext = mock_evaluation_context::install_mock($this->teameval);

        foreach (array_map(null, $this->groups, [40, 60, 90]) as list($group, $grade)) {
            $evalcontext->groupgrades[$group->id] = $grade;
        }

        $expected_results = [50.60,44.60,53.00,33.40,24.20,40.20,72.60,43.80,52.50,78.30,117.00,90.00,82.35,86.85,73.80];

        foreach(array_map(null, $this->students, $expected_results) as list($user, $expected)) {
            $grade = $this->teameval->adjusted_grade($user->id);
            $this->assertEquals($expected, $grade);
        }

        // and finally check the adjusted grade of someone not in the group
        
        $notagrade = $this->teameval->adjusted_grade($this->teacher->id);
        $this->assertNull($notagrade);


    }

}