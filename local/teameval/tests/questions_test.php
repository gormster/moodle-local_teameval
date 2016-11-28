<?php

use local_teameval\team_evaluation;
use local_teameval\question_info;
use local_teameval\polymorph_transaction;

require_once(dirname(__FILE__) . '/mocks/mock_question.php');

class local_teameval_questions_testcase extends advanced_testcase {

    private $course;

    private $assign;

    private $teameval;

    private $questions;

    public function setUp() {

        $this->resetAfterTest();

        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();

        // we use assign because it's one of the default implementers
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $this->assign = $generator->create_instance(array('course'=>$this->course->id));

        // we make a teameval first, then we'll reload it as a mock
        $teameval = team_evaluation::from_cmid($this->assign->cmid);

        $this->teameval = $this->getMock(team_evaluation::class, ['get_question_plugins'], [$teameval->id]);

        $this->teameval->method('get_question_plugins')
            ->willReturn(['mock' => mock_question::mock_question_plugininfo($this)]);

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

    /**
     * @covers local_teameval\team_evaluation::get_questions
     */
    public function test_get_questions() {

        $questions = $this->teameval->get_questions();

        $this->assertEquals(0, count($questions));

        $this->add_questions(5);

        $questions = $this->teameval->get_questions();

        $this->assertEquals(5, count($questions));        

        $questionInfo = next($questions); // get the SECOND question in the list. this makes sure ordinals are working correctly.

        $this->assertInstanceOf(question_info::class, $questionInfo);
        $this->assertEquals('mock', $questionInfo->type);
        $this->assertEquals(2, $questionInfo->questionid);
        $this->assertEquals('teamevalquestion_mock/submission_view', $questionInfo->submissiontemplate);
        $this->assertEquals('teamevalquestion_mock/editing_view', $questionInfo->editingtemplate);

        $question = $questionInfo->question;

        $this->assertInstanceOf(mock_question::class, $question);
        $this->assertEquals(2, $question->id);
        $this->assertEquals('mock', $question->plugin_name());

    }

    /**
     * Even though this covered by questionnaire_set_order, changing the ordinal is about the only thing you can do with update_question
     * @covers local_teameval\team_evaluation::should_update_question
     * @covers local_teameval\team_evaluation::update_question
     */
    public function test_update_question() {
        global $USER;

        $this->add_questions(5);

        $reorder = [5, 2, 1, 3, 4]; 

        foreach($this->questions as $question) {
            $tx = $this->teameval->should_update_question('mock', $question->id, $USER->id);
            $ordinal = array_search($question->id, $reorder);
            $this->assertNotFalse($ordinal);
            $this->teameval->update_question($tx, $ordinal);
        }

        $questions = $this->teameval->get_questions();

        foreach(array_map(null, $reorder, $questions) as list($expected, $actual)) {
            $this->assertEquals($expected, $actual->questionid);
        }

    }

    /**
     * @expectedException coding_exception
     * @expectedExceptionMessage type of transaction
     */
    public function test_update_question_fail() {
        global $USER;

        $this->add_questions(1);

        $question = current($this->questions);

        $tx = $this->teameval->should_delete_question('mock', $question->id, $USER->id);

        $this->teameval->update_question($tx, 0);
    }

    /**
     * @expectedException coding_exception
     * @expectedExceptionMessage type of transaction
     */
    public function test_update_question_fail_2() {
        $this->add_questions(1);

        $question = current($this->questions);

        $tx = new polymorph_transaction(null, 'plumbus', 'hizzard', $question->id, SQL_QUERY_UPDATE);

        $this->teameval->update_question($tx, 0);
    }

    /**
     * @covers local_teameval\team_evaluation::questionnaire_set_order
     */
    public function test_questionnaire_set_order() {
        global $USER;

        $this->add_questions(5);

        $reorder = [5, 2, 1, 3, 4]; 
        $setorder = array_map(function($i) {
            return ['type' => 'mock', 'id' => $i];
        }, $reorder);

        $this->teameval->questionnaire_set_order($setorder);

        $questions = $this->teameval->get_questions();

        foreach(array_map(null, $reorder, $questions) as list($expected, $actual)) {
            $this->assertEquals($expected, $actual->questionid);
        }

    }

    /**
     * @expectedException moodle_exception
     */
    public function test_questionnaire_set_order_fail_1() {
        global $USER;

        $this->add_questions(5);

        $reorder = [5, 2, 1, 3]; 
        $setorder = array_map(function($i) {
            return ['type' => 'mock', 'id' => $i];
        }, $reorder);

        $this->teameval->questionnaire_set_order($setorder);
    }
    
    /**
     * @expectedException moodle_exception
     */
    public function test_questionnaire_set_order_fail_2() {
        global $USER;

        $this->add_questions(5);

        $reorder = [6, 2, 1, 3, 4]; 
        $setorder = array_map(function($i) {
            return ['type' => 'mock', 'id' => $i];
        }, $reorder);

        $this->teameval->questionnaire_set_order($setorder);
    }

    /**
     * @covers local_teameval\team_evaluation::num_questions
     * @covers local_teameval\team_evaluation::should_delete_question
     * @covers local_teameval\team_evaluation::delete_question
     * @covers local_teameval\team_evaluation::last_ordinal
     */
    public function test_delete_questions() {
        global $USER;

        $this->add_questions(3);

        $this->assertEquals(3, $this->teameval->num_questions());

        $tx = $this->teameval->should_delete_question('mock', 2, $USER->id);
        // we don't need to actually remove the question from $this->questions
        $this->teameval->delete_question($tx);

        $this->assertEquals(2, $this->teameval->num_questions());

        $questions = $this->teameval->get_questions();

        $question1 = current($questions);
        $question3 = next($questions);

        $this->assertEquals(1, $question1->questionid);
        $this->assertEquals(3, $question3->questionid);

        $lastordinal = $this->teameval->last_ordinal();

        // this is liable to break, but question_info doesn't have ordinal information
        $this->assertEquals(2, $lastordinal);

    }

    /**
     * @expectedException coding_exception
     * @expectedExceptionMessage type of transaction
     */
    public function test_delete_question_fail() {
        global $USER;

        $this->add_questions(1);

        $question = current($this->questions);

        $tx = $this->teameval->should_update_question('mock', $question->id, $USER->id);

        $this->teameval->delete_question($tx);
    }

    /**
     * @expectedException coding_exception
     * @expectedExceptionMessage type of transaction
     */
    public function test_delete_question_fail_2() {
        $this->add_questions(1);

        $question = current($this->questions);

        $tx = new polymorph_transaction(null, 'plumbus', 'hizzard', $question->id, SQL_QUERY_DELETE);

        $this->teameval->delete_question($tx, 0);
    }


}