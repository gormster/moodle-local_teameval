<?php

use local_teameval\team_evaluation;
use local_teameval\question_info;
use local_teameval\polymorph_transaction;

require_once(dirname(__FILE__) . '/mocks/mock_feedback_question.php');

class local_teameval_feedback_testcase extends advanced_testcase {

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

        mock_feedback_question::clear_questions();
        mock_feedback_response::clear_responses();

        $this->questions = [];

        $this->mock_teameval();

    }

    private function mock_teameval($methods = []) {
        // we make a teameval first, then we'll reload it as a mock

        $teameval = team_evaluation::from_cmid($this->assign->cmid);

        $methods = array_merge(['get_question_plugins'], $methods);

        $this->teameval = $this->getMockBuilder(team_evaluation::class)
                               ->setMethods($methods)
                               ->setConstructorArgs([$teameval->id])
                               ->getMock();

        $this->teameval->method('get_question_plugins')
            ->willReturn(['mockback' => mock_feedback_question::mock_question_plugininfo($this)]);

    }

    private function add_questions($numQuestions = 3, $start = 0) {
        global $USER;

        for ($i = $start; $i < $start + $numQuestions; $i++) {
            $id = $i + 1;

            $tx = $this->teameval->should_update_question('mockback', 0, $USER->id);
            $this->questions[] = new mock_feedback_question($this->teameval, $id);
            $tx->id = $id;
            $this->teameval->update_question($tx, $i);
        }

    }

    private function add_responses($userid, $group, $questions, $opinions = []) {
        foreach($questions as $q) {
            $response = new mock_feedback_response($this->teameval, $q, $userid);
            $ops = [];
            foreach($group as $to) {
                if (isset($opinions[$to->id])) {
                    $ops[$to->id] = $opinions[$to->id];
                } else {
                    $ops[$to->id] = "Feedback from $userid to {$to->id}";
                }
            }
            $response->update_opinions($ops);
        }
    }

    public function test_all_feedback() {
        $this->add_questions(3);

        $group0 = reset($this->members);
        foreach($group0 as $user) {
            $this->add_responses($user->id, $group0, $this->questions);
        }

        $student0 = reset($group0);

        $feedbacks = $this->teameval->all_feedback($student0->id);

        $this->assertCount(3, $feedbacks);
        foreach ($feedbacks as $question) {
            $this->assertEquals("Mock feedback question", $question->question->question->get_title());
            $this->assertCount(5, $question->teammates);

            $first = current($question->teammates);
            $this->assertEquals($student0->id, $first->from->id);
        }

    }

    public function test_no_feedback() {
        $this->add_questions(1);
        $this->questions[0]->feedback = false;

        $group0 = reset($this->members);
        foreach($group0 as $user) {
            $this->add_responses($user->id, $group0, $this->questions);
        }

        $student0 = reset($group0);

        $feedbacks = $this->teameval->all_feedback($student0->id);

        $this->assertCount(0, $feedbacks);
    }

    public function test_empty_feedback() {
        global $PAGE;

        $this->add_questions(1);

        $group0 = reset($this->members);
        foreach($group0 as $user) {
            $this->add_responses($user->id, $group0, $this->questions, [$user->id => ""]);
        }

        $student0 = reset($group0);

        $feedback = new \local_teameval\output\feedback($this->teameval, $student0->id);
        $exported = $feedback->export_for_template($PAGE->get_renderer('core'), false);

        $this->assertCount(1, $exported->questions);
        $question = reset($exported->questions);

        $this->assertCount(4, $question->teammates);

        $first = current($question->teammates);
        $this->assertNotEquals(get_string('yourself', 'local_teameval'), $first->name);
    }

    private function _get_property($object, $name) {
        $mirror = new ReflectionClass($object);
        $prop = $mirror->getProperty('feedback');
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    public function test_rescind_feedback() {
        $rescinds = $this->teameval->all_rescind_states();
        $this->assertEmpty($rescinds);

        $this->add_questions(3);

        $group0 = reset($this->members);
        foreach($group0 as $user) {
            $this->add_responses($user->id, $group0, $this->questions);
        }

        list($student0, $student1, $student2, $student3, $student4) = array_values($group0);

        // bad actor = student4, student4, student1
        // targets = student0, student1, student0
        list($question0, $question1, $question2) = $this->teameval->get_questions();
        $this->teameval->rescind_feedback_for($question0->id, $student4->id, $student0->id);
        $this->teameval->rescind_feedback_for($question0->id, $student4->id, $student1->id);
        $this->teameval->rescind_feedback_for($question0->id, $student1->id, $student0->id);

        $feedback = new local_teameval\output\feedback($this->teameval, $student0->id);
        $questions = $this->_get_property($feedback, 'feedback');
        $this->assertCount(3, $questions);

        $question = reset($questions);
        $this->assertCount(3, $question->teammates);

        $feedback = new local_teameval\output\feedback($this->teameval, $student1->id);
        $questions = $this->_get_property($feedback, 'feedback');
        $this->assertCount(3, $questions);

        $question = reset($questions);
        $this->assertCount(4, $question->teammates);

        $feedback = new local_teameval\output\feedback($this->teameval, $student4->id);
        $questions = $this->_get_property($feedback, 'feedback');
        $this->assertCount(3, $questions);

        $question = reset($questions);
        $this->assertCount(5, $question->teammates);

        // now what if student 2 has nasty comments from EVERYONE (including themselves I guess)

        $this->teameval->rescind_feedback_for($question1->id, $student0->id, $student2->id);
        $this->teameval->rescind_feedback_for($question1->id, $student1->id, $student2->id);
        $this->teameval->rescind_feedback_for($question1->id, $student2->id, $student2->id);
        $this->teameval->rescind_feedback_for($question1->id, $student3->id, $student2->id);
        $this->teameval->rescind_feedback_for($question1->id, $student4->id, $student2->id);

        $feedback = new local_teameval\output\feedback($this->teameval, $student2->id);
        $questions = $this->_get_property($feedback, 'feedback');

        $this->assertCount(2, $questions);

        $this->assertEquals($question0->id, reset($questions)->question->id);
        $this->assertEquals($question2->id, next($questions)->question->id);

        // get all rescind states

        $rescinded = $this->teameval->all_rescind_states();
        $this->assertCount(8, $rescinded);

        // manually approve some feedback

        $this->teameval->rescind_feedback_for($question0->id, $student1->id, $student0->id, local_teameval\FEEDBACK_APPROVED);
        $this->teameval->rescind_feedback_for($question0->id, $student0->id, $student4->id, local_teameval\FEEDBACK_APPROVED);

        $rescinded = $this->teameval->all_rescind_states();
        $this->assertCount(9, $rescinded);

        $approves = array_filter($rescinded, function($v) {
            return $v->state == local_teameval\FEEDBACK_APPROVED;
        });

        $this->assertCount(2, $approves);

    }


}