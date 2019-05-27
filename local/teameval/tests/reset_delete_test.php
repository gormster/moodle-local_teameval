<?php

use local_teameval\team_evaluation;

require_once(dirname(__FILE__) . '/base_test.php');

class local_teameval_reset_delete_testcase extends advanced_testcase {

    use local_teameval_base_testcase;

    function test_delete_teameval() {
        global $DB;

        $this->add_questions();
        $this->add_all_responses();

        $group0 = reset($this->groups);
        $this->teameval->release_marks_for_group($group0->id);
        list($student0, $student1, $student2) = array_values($this->members[$group0->id]);
        $this->teameval->release_marks_for_user($student0->id);
        $this->teameval->release_marks_for_user($student1->id);
        $this->teameval->release_marks_for_user($student2->id);

        $questions = $this->teameval->get_questions();
        $question0 = reset($questions);
        $this->teameval->rescind_feedback_for($question0->id, $student2->id, $student0->id);

        // $this->teameval will be potentially unstable, delete it
        $teamevalid = $this->teameval->id;
        $questionids = array_map(function($v) { return $v->id; }, $questions);
        $team_evaluation = get_class($this->teameval);
        unset($this->teameval);

        $team_evaluation::delete_teameval(null, $this->assign->cmid);

        $this->assertFalse($DB->record_exists('teameval', ['cmid' => $teamevalid]));
        $this->assertFalse($DB->record_exists('teameval', ['cmid' => $this->assign->cmid]));
        $this->assertFalse($DB->record_exists('teameval_questions', ['id' => $teamevalid]));
        $this->assertFalse($DB->record_exists('teameval_release', ['cmid' => $this->assign->cmid]));
        $rescinds = $DB->get_records_list('teameval_rescind', 'questionid', $questionids);
        $this->assertCount(0, $rescinds);

    }

    /**
     * @expectedException coding_exception
     * @expectedExceptionMessage id or cmid
     */
    function test_delete_fail() {
        $nope = team_evaluation::delete_teameval(42);

        $this->assertFalse($nope);

        // fires an exception
        team_evaluation::delete_teameval();
    }

    function test_reset_delete_questionnaire() {
        global $DB;

        $this->add_questions();
        $this->add_all_responses();

        $this->assertCount(3, mock_response::$responses);
        foreach(mock_response::$responses as $question) {
            $this->assertCount(15, $question);
        }

        $this->teameval->reset_questionnaire();

        $this->assertCount(0, mock_response::$responses);

        $this->assertEquals(3, $DB->count_records('teameval_questions', ['teamevalid' => $this->teameval->id]));
        $this->assertCount(3, mock_question::$questions);

        $this->teameval->delete_questionnaire();

        $this->assertEquals(0, $DB->count_records('teameval_questions', ['teamevalid' => $this->teameval->id]));
        $this->assertCount(0, mock_question::$questions);

    }

    function test_reset_userdata() {
        $this->add_questions();
        $this->add_all_responses();

        $group0 = reset($this->groups);
        $this->teameval->release_marks_for_group($group0->id);
        list($student0, $student1, $student2) = array_values($this->members[$group0->id]);
        $this->teameval->release_marks_for_user($student0->id);
        $this->teameval->release_marks_for_user($student1->id);
        $this->teameval->release_marks_for_user($student2->id);

        $questions = $this->teameval->get_questions();
        $question0 = reset($questions);
        $this->teameval->rescind_feedback_for($question0->id, $student2->id, $student0->id);

        $this->teameval->reset_userdata();

        $releases = $this->teameval->get_releases();
        $this->assertCount(0, $releases);

        $rescinds = $this->teameval->all_rescind_states();
        $this->assertCount(0, $releases);
    }

}