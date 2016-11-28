<?php

use local_teameval\team_evaluation;
use local_teameval\question_info;
use local_teameval\evaluation_context;

require_once(dirname(__FILE__) . '/mocks/mock_question.php');
require_once(dirname(__FILE__) . '/mocks/mock_evaluation_context.php');

class local_teameval_access_testcase extends advanced_testcase {

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

        team_evaluation::_clear_groups_members_cache();

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

    public function test_guard_capability() {

        $this->mock_teameval();

        $contextid = context_module::instance($this->assign->cmid)->id;

        $this->setUser($this->teacher);
        $context = team_evaluation::guard_capability($this->teameval, ['local/teameval:createquestionnaire']);

        $this->assertEquals($context->id, $contextid);

        $this->setUser($this->teacher);
        $context = team_evaluation::guard_capability($this->teameval->id, ['local/teameval:createquestionnaire']);

        $this->assertEquals($context->id, $contextid);

    }

    public function test_check_capability() {

        $this->mock_teameval();

        $this->setUser($this->teacher);
        $result = team_evaluation::check_capability($this->teameval, ['local/teameval:createquestionnaire']);

        $this->assertNotEmpty($result);

        $result = team_evaluation::check_capability($this->teameval, ['local/teameval:submitquestionnaire']);

        $this->assertEmpty($result);

    }

    public function test_guard_capability_basic_1() {

        $this->mock_teameval();

        $this->setUser($this->teacher);

        $this->setExpectedException('required_capability_exception');
        team_evaluation::guard_capability($this->teameval, ['local/teameval:submitquestionnaire']);

    }

    public function test_guard_capability_basic_2() {

        $this->mock_teameval();

        $this->setUser(current($this->students));

        $this->setExpectedException('required_capability_exception');
        team_evaluation::guard_capability($this->teameval, ['local/teameval:createquestionnaire']);

    }

    public function test_guard_capability_do_anything() {

        $this->mock_teameval();

        $contextid = context_module::instance($this->assign->cmid)->id;

        $this->setAdminUser();
        $context = team_evaluation::guard_capability($this->teameval, ['local/teameval:submitquestionnaire']);

        $this->assertEquals($context->id, $contextid);

        $this->setExpectedException('required_capability_exception');

        $context = team_evaluation::guard_capability($this->teameval, ['local/teameval:submitquestionnaire'], ['doanything' => false]);

        // Fails with exception

    }

    public function test_guard_capability_must_exist_1() {
        $this->mock_teameval();

        $contextid = context_module::instance($this->assign->cmid)->id;

        $this->setUser($this->teacher);

        $context = team_evaluation::guard_capability(['cmid' => $this->assign->cmid], ['local/teameval:createquestionnaire'], ['must_exist' => true]);

        $this->assertEquals($context->id, $contextid);

        // create a new assign that doesn't have a teameval associated with it
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $assign2 = $generator->create_instance(array('course'=>$this->course->id, 'teamsubmission' => true));
        $contextid2 = context_module::instance($assign2->cmid)->id;

        $context = team_evaluation::guard_capability(['cmid' => $assign2->cmid], ['local/teameval:createquestionnaire'], ['must_exist' => false]);

        $this->assertEquals($context->id, $contextid2);

        $this->setExpectedException('invalid_parameter_exception');

        $context = team_evaluation::guard_capability(['cmid' => $assign2->cmid], ['local/teameval:createquestionnaire'], ['must_exist' => true]);

        // Fails with exception

    }

    public function test_guard_capability_must_exist_2() {
        $this->mock_teameval();

        $this->setUser($this->teacher);

        $this->setExpectedException('invalid_parameter_exception');

        $context = team_evaluation::guard_capability(['id' => $this->teameval->id + 1], ['local/teameval:createquestionnaire'], ['must_exist' => true]);

        // Fails with exception

    }

    public function test_guard_capability_must_exist_3() {
        $this->mock_teameval();

        $this->setUser($this->teacher);

        $this->setExpectedException('coding_exception');

        $context = team_evaluation::guard_capability(['plumbus' => $this->teameval->id], ['local/teameval:createquestionnaire'], ['must_exist' => true]);
    }

    public function test_guard_capability_child_context() {
        $this->mock_teameval();

        $coursecontext = context_course::instance($this->course->id);

        $template = team_evaluation::new_with_contextid($coursecontext->id);

        $this->setUser($this->teacher);

        $context = team_evaluation::guard_capability($template, ['local/teameval:createquestionnaire'], ['child_context' => $this->teameval->get_context()]);

        $this->assertEquals($context->id, $this->teameval->get_context()->id);

        $context = team_evaluation::guard_capability(['contextid' => $coursecontext->id], ['local/teameval:createquestionnaire'], ['child_context' => $this->teameval->get_context()]);

        $this->assertEquals($context->id, $this->teameval->get_context()->id);        

        // now create a second course, to test the fail condition
        
        $course2 = $this->getDataGenerator()->create_course();
        $course2context = context_course::instance($course2->id);
        $template2 = team_evaluation::new_with_contextid($course2context->id);

        $this->setExpectedException('required_capability_exception');
        $context = team_evaluation::guard_capability($template2, ['local/teameval:createquestionnaire'], ['child_context' => $this->teameval->get_context()]);

    }

    public function test_guard_capability_dashboard_context() {

        $user = current($this->students);

        $this->setUser($user->id);

        $usercontext = context_user::instance($user->id);

        $context = team_evaluation::guard_capability($usercontext, ['local/teameval:submitquestionnaire']);

        $this->assertEquals($context->id, $usercontext->id);

        $this->setUser(next($this->students));

        $this->setExpectedException('required_capability_exception');
        
        team_evaluation::guard_capability($usercontext, ['local/teameval:submitquestionnaire']);
    }
    

    public function test_should_update_question() {
        global $USER;

        $this->mock_teameval();

        // should work for admin user
        $tx = $this->teameval->should_update_question('mock', 0, $USER->id);
        $this->assertNotEmpty($tx);
        // do nothing
        $tx->transaction->allow_commit();

        // should work for teacher
        $this->setUser($this->teacher);
        
        $tx = $this->teameval->should_update_question('mock', 0, $USER->id);
        $this->assertNotEmpty($tx);
        // do nothing
        $tx->transaction->allow_commit();

        // should NOT work for student
        $this->setUser(current($this->students));

        $tx = $this->teameval->should_update_question('mock', 0, $USER->id);
        $this->assertEmpty($tx);

        // go back to admin user
        $this->setAdminUser();

        // add a question we might want to hypothetically update
        $this->add_questions(1);

        // mock questionnaire being locked
        $this->mock_teameval(['questionnaire_locked']);

        $this->teameval->method('questionnaire_locked')
            ->willReturn(true);

        // make sure that stub worked
        $this->assertTrue($this->teameval->questionnaire_locked());

        // should always fail to add question with a locked questionnaire
        $tx = $this->teameval->should_update_question('mock', 0, $USER->id);
        $this->assertEmpty($tx);

        // should work to update existing questions 
        $tx = $this->teameval->should_update_question('mock', 1, $USER->id);
        $this->assertNotEmpty($tx);
        $tx->transaction->allow_commit();

    }

    public function test_should_delete_question() {

        global $USER;

        $this->mock_teameval();

        // add the question we want to delete
        // even though we never actually will
        $this->add_questions(1);

        // should work for admin user
        $tx = $this->teameval->should_delete_question('mock', 1, $USER->id);
        $this->assertNotEmpty($tx);
        $tx->transaction->allow_commit();

        // should work for teacher
        $this->setUser($this->teacher);
        
        $tx = $this->teameval->should_delete_question('mock', 1, $USER->id);
        $this->assertNotEmpty($tx);
        $tx->transaction->allow_commit();

        // should NOT work for student
        $this->setUser(current($this->students));

        $tx = $this->teameval->should_delete_question('mock', 1, $USER->id);
        $this->assertEmpty($tx);

        // mock questionnaire being locked
        $this->mock_teameval(['questionnaire_locked']);

        $this->teameval->method('questionnaire_locked')
            ->willReturn(true);

        // make sure that stub worked
        $this->assertTrue($this->teameval->questionnaire_locked());

        $this->setAdminUser();

        // should always fail with a locked questionnaire
        $tx = $this->teameval->should_delete_question('mock', 1, $USER->id);
        $this->assertEmpty($tx);

    }

    public function test_can_submit() {
        global $USER;

        $this->mock_teameval(['group_ready']);

        $this->add_questions(3);

        $lastgroup = end($this->groups);

        // mock that group C is ready
        $this->teameval->method('group_ready')
            ->willReturnCallback(function($i) use ($lastgroup) {
                if ($i == $lastgroup->id) {
                    return true;
                }
                return false;
            });

        // admin cannot submit
        $rslt = $this->teameval->can_submit($USER->id);
        $this->assertFalse($rslt);

        // teacher cannot submit
        $rslt = $this->teameval->can_submit($this->teacher->id);
        $this->assertFalse($rslt);

        $studentid = key($this->students);

        // students can submit
        $rslt = $this->teameval->can_submit($studentid);
        $this->assertTrue($rslt);

        // can submit to a question in this teameval
        $rslt = $this->teameval->can_submit_response('mock', 1, $studentid);
        $this->assertTrue($rslt);

        // can't submit to a question that isn't in this teameval
        $rslt = $this->teameval->can_submit_response('mock', 10, $studentid);
        $this->assertFalse($rslt);

        $rslt = $this->teameval->can_submit_response('other', 1, $studentid);
        $this->assertFalse($rslt);

        // get a student in group C
        $lastgroup = end($this->groups);
        $studentid = key($this->members[$lastgroup->id]);

        // should fail
        $rslt = $this->teameval->can_submit_response('mock', 1, $studentid);
        $this->assertFalse($rslt, 'student\'s group is ready');

        // set the deadline to the past
        $settings = new stdClass;
        $settings->deadline = time() - 1;
        $this->teameval->update_settings($settings);

        $rslt = $this->teameval->can_submit_response('mock', 1, $studentid);
        $this->assertFalse($rslt, 'deadline has passed');

    }

    public function test_questionnaire_locking() {

        // we're gonna try to do this without stubbing any methods on teameval
        // except for get_evaluation_context
        $this->mock_teameval();

        $evalcontext = mock_evaluation_context::install_mock($this->teameval);

        $rslt = $this->teameval->questionnaire_locked();
        $this->assertFalse($rslt);

        $this->add_questions(3);

        $rslt = $this->teameval->questionnaire_locked();
        $this->assertFalse($rslt);

        // now pretend a user can see the teameval
        $evalcontext->uservisible[key($this->students)] = true;

        $rslt = $this->teameval->questionnaire_locked();
        $this->assertNotFalse($rslt);

        list($reason, $user) = $rslt;

        $this->assertEquals(\local_teameval\LOCKED_REASON_VISIBLE, $reason);

        // now add a response
        // use the first student in the last group to avoid false passes

        $student = current(end($this->members));

        $response = new mock_response($this->teameval, current($this->questions), $student->id);
        $response->opinions = [1, 2, 3, 4, 5];

        // one should be enough

        $completion = $this->teameval->user_completion($student->id);

        $rslt = $this->teameval->questionnaire_locked();
        $this->assertNotFalse($rslt);

        list($reason, $user) = $rslt;

        $this->assertEquals(\local_teameval\LOCKED_REASON_MARKED, $reason);
        $this->assertEquals($student->id, $user->id);

    }

}