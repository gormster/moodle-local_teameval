<?php

use local_teameval\team_evaluation;
use local_teameval\evaluation_context;
use local_teameval\evaluator;

require_once('mocks/mock_evaluator.php');
require_once('mocks/mock_feedback_question.php');
require_once('mocks/mock_question_info.php');

class local_teameval_evaluation_context_testcase extends advanced_testcase {

    private $course;

    private $assign;

    private $teameval;

    private $evalcontext;

    private $users;

    private $groups;

    private $members;

    public function setUp() {

        $this->resetAfterTest();

        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();

        // we use assign because it's one of the default implementers
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $this->assign = $generator->create_instance(array('course'=>$this->course->id));

        // make some users & some groups

        for($i = 0; $i < 3; $i++) {
            $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
            $this->groups[$group->id] = $group;
            $this->members[$group->id] = [];

            for($j = 0; $j < 5; $j++) {
                $user = $this->getDataGenerator()->create_user();
                $this->users[$user->id] = $user;

                $this->getDataGenerator()->enrol_user($user->id, $this->course->id);
                $this->getDataGenerator()->create_group_member(['userid' => $user->id, 'groupid' => $group->id]);
                $this->members[$group->id][$user->id] = $user;
            }

        }

        $this->teameval = team_evaluation::from_cmid($this->assign->cmid);
        $this->teameval = $this->getMockBuilder(team_evaluation::class)
                               ->setMethods(['all_feedback', 'reset_userdata', 'delete_questionnaire',
                                    'reset_questionnaire', 'get_question_plugins'])
                               ->setConstructorArgs([$this->teameval->id])
                               ->getMock();

        $this->teameval->method('get_question_plugins')
            ->willReturn(['mockback' => mock_feedback_question::mock_question_plugininfo($this)]);

        // because the purpose of this class is to test the default implementation
        // of evaluation context, and not the methods in mod_assign\evaluation_context
        // we're going to mock the context

        $this->evalcontext = $this->getMockBuilder(evaluation_context::class)
                                  ->setConstructorArgs([$this->teameval->get_coursemodule()])
                                  ->getMockForAbstractClass();

        // now we have to replace the private internal evalcontext ivar
        // there's no way to do this with moodle's phpunit implementation (i.e. override the classloader)

        $reflection = new ReflectionClass(team_evaluation::class);
        $prop = $reflection->getProperty('evalcontext');
        $prop->setAccessible(true);
        $prop->setValue($this->teameval, $this->evalcontext);

        $reflection = new ReflectionClass(evaluation_context::class);
        $prop = $reflection->getProperty('teameval');
        $prop->setAccessible(true);
        $prop->setValue($this->evalcontext, $this->teameval);

        // this creates a retain cycle so we have to be sure to unset both of these guys in tearDown

    }

    public function tearDown() {

        unset($this->teameval);
        unset($this->evalcontext);

    }

    public function test_get_evaluation_context() {

        // this isn't even a test of teameval code, it's just a test of whether our mock worked
        $evalcontext = $this->teameval->get_evaluation_context();
        $this->assertEquals($evalcontext, $this->evalcontext);

    }

    public function test_evaluation_permitted() {

        // there's a bug in moodle that means cm->visible is not dynamically checked by is_user_visible
        // if availability is not enabled. which means we would always get the value we had when we created
        // the cm_info object (i.e. true).
        set_config('enableavailability', true);

        $rslt = $this->evalcontext->evaluation_permitted();
        $this->assertTrue($rslt);

        $rslt = $this->evalcontext->evaluation_permitted(key($this->users));
        $this->assertTrue($rslt);

        // hide the activity
        set_coursemodule_visible($this->assign->cmid, 0);

        $rslt = $this->evalcontext->evaluation_permitted(key($this->users));
        $this->assertFalse($rslt);

    }

    public function test_format_grade() {

        $item = \grade_item::fetch(['itemtype' => 'mod',
            'itemmodule' => 'assign',
            'iteminstance' => $this->assign->id,
            'itemnumber' => 0]);

        $item->decimals = 2;
        $item->update();

        $rslt = $this->evalcontext->format_grade(10);
        $this->assertEquals($rslt, "10.00");

        $rslt = $this->evalcontext->format_grade(12.345);
        $this->assertEquals($rslt, "12.35"); // pretty sure moodle rounds 0.5 up

        $item->decimals = 0;
        $item->update();

        $rslt = $this->evalcontext->format_grade(10.1);
        $this->assertEquals($rslt, "10");

        $rslt = $this->evalcontext->format_grade(12.5);
        $this->assertEquals($rslt, "13"); // pretty sure moodle rounds 0.5 up

        // data generator creates a grade item when assign is created,
        // but there are some situations when the grade item might not
        // exist but the evalcontext does.

        $item->delete();

        $rslt = $this->evalcontext->format_grade(10);
        $this->assertEquals($rslt, "10.00");

        $rslt = $this->evalcontext->format_grade(12.345);
        $this->assertEquals($rslt, "12.35"); // pretty sure moodle rounds 0.5 up


    }

    public function test_evaluation_enabled() {

        $rslt = $this->evalcontext->evaluation_enabled();
        $this->assertTrue($rslt);

        // change the setting
        $settings = new stdClass;
        $settings->enabled = false;
        $this->teameval->update_settings($settings);

        $rslt = $this->evalcontext->evaluation_enabled();
        $this->assertFalse($rslt);

        // try with a module without teameval

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $this->assign = $generator->create_instance(array('course'=>$this->course->id));

        $cm = get_course_and_cm_from_cmid($this->assign->cmid, 'assign', $this->course)[1];
        $evalcontext = $this->getMockBuilder(evaluation_context::class)
                            ->setConstructorArgs([$cm])
                            ->getMockForAbstractClass();

        $rslt = $evalcontext->evaluation_enabled();
        $this->assertFalse($rslt);

    }

    private function _mock_scores($mock_scores, $mock_grades) {
        $scores = [];
        foreach(array_map(null, $this->users, $mock_scores) as list($user, $score)) {
            $scores[$user->id] = $score;
        }

        $evaluator = mock_evaluator::install_mock($this->teameval);
        $evaluator->scores = $scores;

        $grades = [];
        foreach(array_map(null, $this->members, $mock_grades) as list($users, $rawgrade)) {
            foreach($users as $user) {
                $grade = new stdClass;
                $grade->userid = $user->id;
                $grade->rawgrade = $rawgrade;
                $grade->feedback = '';
                $grades[$user->id] = $grade;
            }
        }

        return $grades;
    }

    private function _assert_expected_grades($users, $grades, $expected_results) {
        foreach(array_map(null, $users, $expected_results) as list($user, $expected)) {
            $grade = $grades[$user->id];
            $this->assertEquals($expected, $grade->rawgrade);
        }
    }

    public function test_update_grades() {

        $this->teameval->method('all_feedback')->willReturn([]);

        $mock_scores = [1.53,1.23,1.65,0.67,0.21,0.34,1.42,0.46,0.75,1.61,1.60,1.00,0.83,0.93,0.64];
        $mock_grades = [40, 60, 90];
        $unadjusted = $this->_mock_scores($mock_scores, $mock_grades);

        // we're going to ignore noncompletion for now because it makes life easier
        // with no questions everyone's completion is 100%

        $settings = new stdClass;
        $settings->fraction = 0.5;
        $settings->deadline = time() - 1;
        $this->teameval->update_settings($settings);

        $expected_results = [50.60,44.60,53.00,33.40,24.20,40.20,72.60,43.80,52.50,78.30,100.00,90.00,82.35,86.85,73.80];

        // single object
        $student = clone reset($unadjusted);
        $grade = $this->evalcontext->update_grades($student);
        $this->assertEquals(reset($grade)->rawgrade, 50.60);

        // single array
        $group0 = reset($this->members);
        $student = end($group0);
        $grade = $this->evalcontext->update_grades(['userid' => $student->id, 'rawgrade' => 40, 'feedback' => '']);
        $this->assertEquals(reset($grade)->rawgrade, 24.20);

        // array of arrays
        $groupN = end($this->members);
        $students = array_map(function($student) {
            return ['userid' => $student->id, 'rawgrade' => 90, 'feedback' => ''];
            }, $groupN);

        $grades = $this->evalcontext->update_grades($students);
        $this->_assert_expected_grades($groupN, $grades, array_slice($expected_results, 10));

        reset($this->members);
        reset($this->users);

        // array of objects, the intended behaviour
        $grades = $this->evalcontext->update_grades($unadjusted);
        $this->_assert_expected_grades($this->users, $grades, $expected_results);

    }


    public function test_update_grades_fail() {
        $this->teameval->method('all_feedback')->willReturn([]);

        $mock_scores = [0,0,0,0,5,0,0,0,0,5,0,0,0,0,5];
        $mock_grades = [40, 60, 90];

        $settings = new stdClass;
        $settings->fraction = 0.5;
        $settings->deadline = time() - 1;
        $this->teameval->update_settings($settings);

        // control: the scores should be 20, 20, 20, 20, 100
        $grades = $this->_mock_scores($mock_scores, $mock_grades);
        $this->evalcontext->update_grades($grades);
        $expected_results = [20,20,20,20,100,30,30,30,30,100,45,45,45,45,100];

        $this->_assert_expected_grades($this->users, $grades, $expected_results);

        // disable teameval
        $settings->enabled = false;
        $this->teameval->update_settings($settings);

        $grades = $this->_mock_scores($mock_scores, $mock_grades);
        $this->evalcontext->update_grades($grades);
        $expected_results = [40,40,40,40,40,60,60,60,60,60,90,90,90,90,90];

        $this->_assert_expected_grades($this->users, $grades, $expected_results);

        // marks not available for groups 2 & 3

        $settings->enabled = true;
        $settings->autorelease = false;
        $this->teameval->update_settings($settings);

        $group0 = reset($this->members);
        foreach ($group0 as $user) {
            $this->teameval->release_marks_for_user($user->id);
        }

        $grades = $this->_mock_scores($mock_scores, $mock_grades);
        $this->evalcontext->update_grades($grades);
        $expected_results = [20,20,20,20,100,null,null,null,null,null,null,null,null,null,null];

        $this->_assert_expected_grades($this->users, $grades, $expected_results);

    }

    public function test_feedback() {
        $mock_scores = [0,0,0,0,5,0,0,0,0,5,0,0,0,0,5];
        $mock_grades = [40, 60, 90];
        $mock_feedback = [];

        $question_object = new mock_feedback_question($this->teameval);
        $question_info = new mock_question_info($this->teameval, 0, 'mockback', 0);

        $question_info->set_plugininfo(mock_feedback_question::mock_question_plugininfo($this));
        $question_info->set_question($question_object);

        $group0 = reset($this->groups);
        $user0 = reset($this->members[$group0->id]);

        $q = new stdClass;
        $q->question = $question_info;
        $q->teammates = [];

        $expected = [];
        foreach($this->members[$group0->id] as $userid => $user) {
            $fb = new stdClass;
            $fb->from = $user;

            $responsetext = "Test feedback from user {$userid}";
            if ($userid == $user0->id) {
                $expected[] = ['Yourself', $responsetext];
            } else {
                $expected[] = [fullname($user), $responsetext];
            }

            $response = new mock_feedback_response($this->teameval, $question_object, $userid);
            $response->update_opinions([$user0->id => $responsetext]);
            $fb->feedback = $response;

            $q->teammates[] =  $fb;
        }

        $mock_feedback[] = [(int)$user0->id, false, [$q]];

        $this->teameval->method('all_feedback')->willReturnCallback(function($userid, $render) use($user0, $q) {
            if ($userid == $user0->id) {
                return [$q];
            }
            return [];
        });

        $grades = $this->_mock_scores($mock_scores, $mock_grades);
        $this->evalcontext->update_grades($grades);

        // TODO: this is extremely fragile, as it breaks with every change of the template
        // Is there a better way to do this?
        $grade = $grades[$user0->id];
        $expectedstring = "<h3>Team evaluation</h3><h4>Mock feedback question</h4><ul>";
        foreach ($expected as $e) {
            $expectedstring .= "<li><strong>$e[0]:</strong> $e[1]</li>";
        }
        $expectedstring .= '</ul>';
        $test = preg_replace('/>\s+</', '><', $grade->feedback);

        $this->assertEquals($expectedstring, $test);
    }


    public function test_default_imps() {

        $ns = evaluation_context::plugin_namespace();

        $this->assertEquals("local_teameval", $ns);

        $component = evaluation_context::component_string();

        $this->assertEquals("Team evaluation", $component);

        $component = \mod_assign\evaluation_context::component_string();

        $this->assertEquals("Assignments", $component);

        $grade = $this->evalcontext->format_grade(12.3456);

        $this->assertEquals('12.35', $grade);

    }

    /**
     * Test failing call when a module does not support team evaluation
     */
    public function test_context_for_module_fail() {

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_url');
        $module = $generator->create_instance(array('course'=>$this->course->id));

        $cm = get_fast_modinfo($this->course)->cms[$module->cmid];

        $result = evaluation_context::context_for_module($cm, false);

        $this->assertEmpty($result);

        $this->expectException('moodle_exception');

        $result = evaluation_context::context_for_module($cm);

        // Fail with moodle_exception
    }

    /**
     * Test getting team evaluation instance via context_for_module
     */
    public function test_get_teameval() {
        $cm = get_fast_modinfo($this->course)->cms[$this->assign->cmid];

        $evalcontext = evaluation_context::context_for_module($cm);

        $teameval = $evalcontext->team_evaluation();

        $this->assertEquals($this->teameval->id, $teameval->id);

        // make a new assign with no teameval
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $assign = $generator->create_instance(array('course'=>$this->course->id));

        $cm = get_fast_modinfo($this->course)->cms[$assign->cmid];

        $evalcontext = evaluation_context::context_for_module($cm);

        $teameval = $evalcontext->team_evaluation();

        $this->assertEmpty($teameval);
    }

    /**
     * TODO
     * This is going to be pretty bloody difficult to mock up.
     */
    public function test_userdata_reset() {

        $evalclass = get_class($this->evalcontext);
        $ns = $evalclass::plugin_namespace();

        $this->teameval->method('reset_userdata')->willReturn(
            ['component' => 'local_teameval', 'item' => get_string('resetresponses', 'local_teameval'), 'error' => false]);

        $this->teameval->method('delete_questionnaire')->willReturn(
            ['component' => 'local_teameval', 'item' => get_string('resetquestionnaire', 'local_teameval'), 'error' => false]);

        $this->teameval->method('reset_questionnaire')->willReturn(null);

        $options = new stdClass;
        $options->{$ns.'_reset_teameval_responses'} = true;
        $options->{$ns.'_reset_teameval_questionnaire'} = false;

        $status = $this->evalcontext->reset_userdata($options);

        $this->assertEquals([['component' => 'local_teameval', 'item' => get_string('resetresponses', 'local_teameval'), 'error' => false]], $status);

        $options->{$ns.'_reset_teameval_questionnaire'} = true;

        $status = $this->evalcontext->reset_userdata($options);

        $this->assertEquals([['component' => 'local_teameval', 'item' => get_string('resetresponses', 'local_teameval'), 'error' => false], ['component' => 'local_teameval', 'item' => get_string('resetquestionnaire', 'local_teameval'), 'error' => false]], $status);

    }




}
