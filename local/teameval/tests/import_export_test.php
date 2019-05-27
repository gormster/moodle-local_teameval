<?php

use local_teameval\team_evaluation;
use teamevalquestion_likert\question;

class local_teameval_import_export_testcase extends advanced_testcase {

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
        $this->teameval = team_evaluation::from_cmid($this->assign->cmid);

        $this->coursecontext = context_course::instance($this->course->id);

        $this->template = team_evaluation::new_with_contextid($this->coursecontext->id);

    }

    private function add_questions($teameval, $numQuestions = 3) {
        global $USER, $DB;

        $questions = [];

        for ($i = 0; $i < $numQuestions; $i++) {
            $id = $teameval->id * 100 + $i + 1;

            $tx = $teameval->should_update_question('likert', 0, $USER->id);
            $record = new stdClass;
            $record->title = "Test Question " . ($i + 1);
            $record->description = "";
            $record->minval = 1;
            $record->maxval = 5;
            $record->meanings = "{}";
            $record->id = $DB->insert_record('teamevalquestion_likert', $record);
            $tx->id = $record->id;
            $teameval->update_question($tx, $i);
            $questions[] = $record;
        }

        return $questions;

    }

    /**
     * This is kind of a spurious test, as it doesn't actually test the file format
     * But that's probably far too difficult, and this will still flag for problematic
     * (i.e. asymettrical) format changes.
     *
     * Because this test interacts with the backup/restore system, we have to use real
     * question type plugins.
     */
    public function test_export_import() {

        $templatequestions = $this->add_questions($this->template);

        $file = $this->template->export_questionnaire();

        $this->teameval->import_questionnaire($file);

        $questions = $this->teameval->get_questions();

        // Same tests as test_add_questions_from_template
        $this->assertCount(3, $questions);

        $templateids = array_map(function($v) { return $v->id; }, $templatequestions);
        $questionids = array_map(function($v) { return $v->questionid; }, $questions);

        $this->assertCount(0, array_intersect($templateids, $questionids), 'Questions not copied on add_questions_from_template');

        foreach (array_map(null, $templatequestions, $questions) as list($expected, $actual)) {
            $this->assertEquals('likert', $actual->type);
            $this->assertEquals($expected->minval, $actual->question->minval);
            $this->assertEquals($expected->maxval, $actual->question->maxval);
        }

    }

}