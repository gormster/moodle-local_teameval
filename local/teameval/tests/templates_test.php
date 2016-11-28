<?php

use local_teameval\team_evaluation;

require_once(dirname(__FILE__) . '/mocks/mock_question.php');

class local_teameval_templates_testcase extends advanced_testcase {

    private $course;

    private $courseontext;

    private $assign;

    private $teameval;

    private $template;

    private $template2;

    private $template3;

    public function setUp() {

        $this->resetAfterTest();

        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();

        // we use assign because it's one of the default implementers
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $this->assign = $generator->create_instance(array('course'=>$this->course->id, 'name' => 'Test Assign'));

        // i guess this counts as a test but we do it every time so put it in setUp
        $teameval = team_evaluation::from_cmid($this->assign->cmid);

        $this->teameval = $this->getMock(team_evaluation::class, ['get_question_plugins'], [$teameval->id]);

        $this->teameval->method('get_question_plugins')
            ->willReturn(['mock' => mock_question::mock_question_plugininfo($this)]);

        $this->coursecontext = context_course::instance($this->course->id);

        $this->template = team_evaluation::new_with_contextid($this->coursecontext->id);
        $this->template2 = team_evaluation::new_with_contextid($this->coursecontext->id);
        $this->template3 = team_evaluation::new_with_contextid($this->coursecontext->id);

        mock_question::clear_questions();
        mock_response::clear_responses();

    }

    private function add_questions($teameval, $numQuestions = 3) {
        global $USER;

        $questions = [];

        for ($i = 0; $i < $numQuestions; $i++) {
            $id = $teameval->id * 100 + $i + 1;

            $tx = $teameval->should_update_question('mock', 0, $USER->id);
            $questions[] = new mock_question($teameval, $id);
            $tx->id = $id;
            $teameval->update_question($tx, $i);
        }

        return $questions;

    }

    public function test_get_evaluation_context_fail() {
        $notacontext = $this->template->get_evaluation_context();

        $this->assertEmpty($notacontext);
    }

    public function test_new() {

        $template = new team_evaluation($this->template->id);

        // check that the contexts match up
        
        $this->assertEquals($template->get_context()->id, $this->coursecontext->id);

    }

    public function test_settings() {

        // the only difference between a regular teameval and a template is that
        // templates have titles
        
        $settings = $this->template->get_settings();

        $this->assertEquals($settings->title, 'New Template');

        $newsettings = new stdClass;
        $newsettings->title = 'New Title';

        $this->template->update_settings($newsettings);
        $settings = $this->template->get_settings();
        $this->assertEquals($settings->title, 'New Title');

        // Do it again to check the shortcut pathway in available_title
        $this->template->update_settings($newsettings);
        $settings = $this->template->get_settings();
        $this->assertEquals($settings->title, 'New Title');

        $title = $this->template->get_title();
        $this->assertEquals($title, 'New Title');

        // Test that saving the same title increments the numbers

        $this->template2->update_settings($newsettings);
        $settings2 = $this->template2->get_settings();
        $this->assertEquals($settings2->title, 'New Title 2');        

        $this->template3->update_settings($newsettings);
        $settings3 = $this->template3->get_settings();
        $this->assertEquals($settings3->title, 'New Title 3');

    }

    public function test_get_coursemodule_fail() {
        $notacm = $this->template->get_coursemodule();

        $this->assertEmpty($notacm);
    }

    public function test_can_submit_fail() {

        $this->add_questions($this->teameval);
        $this->add_questions($this->template);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id);

        $this->setUser($student);

        $result = $this->teameval->can_submit($student->id);

        $this->assertTrue($result);

        $result = $this->template->can_submit($student->id);

        $this->assertFalse($result);
    }


}