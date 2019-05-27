<?php

require_once('mocks/mock_report.php');

use local_teameval\team_evaluation;

class local_teameval_reports_testcase extends advanced_testcase {

    private $course;

    private $assign;

    private $teameval;

    public function setUp() {

        $this->resetAfterTest();

        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();

        // we use assign because it's one of the default implementers
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $this->assign = $generator->create_instance(array('course'=>$this->course->id, 'name' => 'Test Assign', 'duedate' => time() - 3600));

        // i guess this counts as a test but we do it every time so put it in setUp
        $this->teameval = team_evaluation::from_cmid($this->assign->cmid);

    }

    private function mock_get_report_plugin() {
        $methods = ['get_report_plugin'];
        $this->teameval = $this->getMockBuilder(team_evaluation::class)
                               ->setMethods($methods)
                               ->setConstructorArgs([$this->teameval->id])
                               ->getMock();
        $this->teameval->method('get_report_plugin')
            ->willReturn(mock_report::mock_report_plugininfo($this));
    }

    public function test_report_plugin_preferences() {
        $plugininfo = $this->teameval->get_report_plugin();

        $this->assertEquals("scores", $plugininfo->name);

        $this->teameval->set_report_plugin("responses");
        $plugininfo = $this->teameval->get_report_plugin();

        $this->assertEquals("responses", $plugininfo->name);

        $plugininfo = $this->teameval->get_report_plugin("scores");

        $this->assertEquals("scores", $plugininfo->name);

        // sign in as a different user
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'teacher');

        $this->setUser($user);

        $plugininfo = $this->teameval->get_report_plugin();

        $this->assertEquals("scores", $plugininfo->name);
    }

    public function test_get_report() {
        $this->mock_get_report_plugin();

        $report = $this->teameval->get_report();

        $this->assertInstanceOf(mock_report::class, $report);
        $this->assertEquals($this->teameval->id, $report->teameval->id);
        $this->assertTrue($report->generated);
    }

    public function test_report_download_link() {
        $this->mock_get_report_plugin();

        $link = $this->teameval->report_download_link('mock', 'report.txt');
        $this->assertInstanceOf(moodle_url::class, $link);
        $this->assertContains('report.txt', $link->get_path());
    }

    /**
     * Templates cannot have reports
     * @expectedException coding_exception
     * @expectedExceptionMessage template
     */
    public function test_report_download_link_fail() {
        $context = context_course::instance($this->course->id);
        $template = team_evaluation::new_with_contextid($context->id);

        $template->report_download_link('mock', 'report.txt');
    }

}