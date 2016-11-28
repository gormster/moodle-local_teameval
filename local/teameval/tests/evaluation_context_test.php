<?php

use local_teameval\team_evaluation;
use local_teameval\evaluation_context;
use local_teameval\evaluator;

require_once('mocks/mock_evaluator.php');

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

        // because the purpose of this class is to test the default implementation
        // of evaluation context, and not the methods in mod_assign\evaluation_context
        // we're going to mock the context

        $this->evalcontext = $this->getMockForAbstractClass(evaluation_context::class, [$this->teameval->get_coursemodule()]);

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
        $evalcontext = $this->getMockForAbstractClass(evaluation_context::class, [$cm]);

        $rslt = $evalcontext->evaluation_enabled();
        $this->assertFalse($rslt);

    }

    public function test_update_grades() {

        $mock_scores = [1.53,1.23,1.65,0.67,0.21,0.34,1.42,0.46,0.75,1.61,1.60,1.00,0.83,0.93,0.64];
        $scores = [];
        foreach(array_map(null, $this->users, $mock_scores) as list($user, $score)) {
            $scores[$user->id] = $score;
        }

        $evaluator = mock_evaluator::install_mock($this->teameval);
        $evaluator->scores = $scores;

        $mock_grades = [40, 60, 90];
        $grades = [];
        foreach(array_map(null, $this->members, $mock_grades) as list($users, $rawgrade)) {
            foreach($users as $user) {
                $grade = new stdClass;
                $grade->rawgrade = $rawgrade;
                $grade->feedback = '';
                $grades[$user->id] = $grade;
            }
        }

        // we're going to ignore noncompletion for now because it makes life easier
        // with no questions everyone's completion is 100%

        $settings = new stdClass;
        $settings->fraction = 0.5;
        $settings->deadline = time() - 1;
        $this->teameval->update_settings($settings);

        $grades = $this->evalcontext->update_grades($grades);

        $expected_results = [50.60,44.60,53.00,33.40,24.20,40.20,72.60,43.80,52.50,78.30,100.00,90.00,82.35,86.85,73.80];

        foreach(array_map(null, $this->users, $expected_results) as list($user, $expected)) {
            $grade = $grades[$user->id];
            $this->assertEquals($expected, $grade->rawgrade);
        }

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

        $this->setExpectedException('moodle_exception');

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
    }

    /**
     * TODO
     * This is going to be pretty bloody difficult to mock up.
     */
    public function test_userdata_reset() {

    }

    

}