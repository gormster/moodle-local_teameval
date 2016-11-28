<?php

use local_teameval\team_evaluation;

class local_teameval_core_testcase extends advanced_testcase {

    private $course;

    private $assign;

    private $teameval;

    public function setUp() {

        $this->resetAfterTest();

        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();

        // we use assign because it's one of the default implementers
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $this->assign = $generator->create_instance(array('course'=>$this->course->id, 'name' => 'Test Assign'));

        // i guess this counts as a test but we do it every time so put it in setUp
        $this->teameval = team_evaluation::from_cmid($this->assign->cmid);

    }

    public function test_exists() {

        $rslt = team_evaluation::exists($this->teameval->id);
        $this->assertTrue($rslt);

        $rslt = team_evaluation::exists($this->teameval->id + 1); // this should be the only one in existence
        $this->assertFalse($rslt);

        $rslt = team_evaluation::exists(null, $this->assign->cmid);
        $this->assertTrue($rslt);

        $rslt = team_evaluation::exists(null, $this->assign->cmid + 1);  // again, this should be the only assign
        $this->assertFalse($rslt);

    }

    public function test_new() {

        // we're going to test settings later, so change them to not be defaults

        $settings = new stdClass;
        $settings->self = false;
        $settings->fraction = 1.0;
        $settings->noncompletionpenalty = 0.0;
        $settings->deadline = time();

        $this->teameval->update_settings($settings);

        // now we do the real test

        $teameval = new team_evaluation($this->teameval->id);

        // Now this is important - these two aren't actually the same object. PHP doesn't support that.
        // But their properties should be the same.

        $asettings = $this->teameval->get_settings();
        $bsettings = $teameval->get_settings();

        $this->assertEquals($asettings, $bsettings);

        $this->assertEquals($this->teameval->id, $teameval->id);
        $this->assertEquals($this->teameval->get_coursemodule(), $teameval->get_coursemodule());
        $this->assertEquals($this->teameval->get_context(), $teameval->get_context());

        // and we do it again with from_cmid

        $teameval = team_evaluation::from_cmid($this->assign->cmid);

        $asettings = $this->teameval->get_settings();
        $bsettings = $teameval->get_settings();

        $this->assertEquals($asettings, $bsettings);

        $this->assertEquals($this->teameval->id, $teameval->id);
        $this->assertEquals($this->teameval->get_coursemodule(), $teameval->get_coursemodule());
        $this->assertEquals($this->teameval->get_context(), $teameval->get_context());


        // and now we test the fail condition
        
        $this->setExpectedException('coding_exception');

        $notateameval = new team_evaluation(null);
    }

    public function test_settings() {
        
        // make sure we got our default settings right
        $settings = $this->teameval->get_settings();

        $this->assertEquals($settings->enabled, true);
        $this->assertEquals($settings->public, false);
        $this->assertEquals($settings->autorelease, true);
        $this->assertEquals($settings->self, true);
        $this->assertEquals($settings->fraction, 0.5);
        $this->assertEquals($settings->noncompletionpenalty, 0.1);
        $this->assertEquals($settings->deadline, null);
        $this->assertObjectNotHasAttribute('title', $settings, 'Team evaluation with CMID must not have title');

        // change them
        $settings = new stdClass;
        $settings->enabled = false;
        $settings->public = true;
        $settings->autorelease = false;
        $settings->self = false;
        $settings->fraction = 1.0;
        $settings->noncompletionpenalty = 0.0;
        $settings->deadline = time();
        $settings->title = "Should not be set"; // trying to set this shouldn't work
        $settings->notaproperty = "Should not be set";

        $this->teameval->update_settings($settings);

        $newsettings = $this->teameval->get_settings();

        $this->assertObjectNotHasAttribute('title', $newsettings);
        $this->assertObjectNotHasAttribute('notaproperty', $newsettings);

        unset($settings->title);
        unset($settings->notaproperty);

        $this->assertEquals($newsettings, $settings);

    }

    /**
     * @expectedException coding_exception
     * @expectedExceptionMessage does not exist
     */
    public function test_settings_fail() {

        // technically this loop should execute zero times, but we're going to make sure
        $n = 1;
        while (team_evaluation::exists($this->teameval->id + $n)) {
            $n += 1;
        }

        $notateameval = new team_evaluation($this->teameval->id + $n);
    }

    /** 
     * @expectedException coding_exception
     * @expectedExceptionMessage Undefined
     */
    public function test_property_access_fail() {
        $nothing = $this->teameval->plumbus;
    }

    public function test_context_and_cm() {

        $context = $this->teameval->get_context();

        $this->assertEquals($context->contextlevel, CONTEXT_MODULE);
        $this->assertEquals($context->instanceid, $this->assign->cmid);


        $cm = $this->teameval->get_coursemodule();

        $this->assertInstanceOf('cm_info', $cm);
        $this->assertEquals($cm->id, $this->assign->cmid);
        $this->assertEquals($cm->course, $this->course->id);

        $this->assertEquals($cm->context, $context);

    }

    public function test_title() {
        $title = $this->teameval->get_title();
        $this->assertEquals('Test Assign', $title);
    }

    public function test_deadline() {

        // by default deadline is unset
        $rslt = $this->teameval->deadline_passed();
        $this->assertFalse($rslt);

        // change it to a future date
        $settings = new stdClass;
        $settings->deadline = time() + 1e6;
        $this->teameval->update_settings($settings);

        $rslt = $this->teameval->deadline_passed();
        $this->assertFalse($rslt);

        // change it to a past date
        $settings = new stdClass;
        $settings->deadline = time() - 1;
        $this->teameval->update_settings($settings);

        $rslt = $this->teameval->deadline_passed();
        $this->assertTrue($rslt);

        // unset the deadline
        $settings = new stdClass;
        $settings->deadline = 0;
        $this->teameval->update_settings($settings);

        $rslt = $this->teameval->deadline_passed();
        $this->assertFalse($rslt);

    }

}