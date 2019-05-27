<?php

use local_teameval\team_evaluation;
use local_teameval\question_info;
use local_teameval\evaluation_context;

require_once(dirname(__FILE__) . '/base_test.php');

class local_teameval_mark_release_testcase extends advanced_testcase {

    use local_teameval_base_testcase {
        local_teameval_base_testcase::setUp as baseSetUp;
    }

    public function setUp() {
        $this->baseSetUp();

        $settings = new stdClass;
        $settings->autorelease = false;
        $this->teameval->update_settings($settings);
    }

    function test_release_for_all() {
        $this->add_questions();
        $this->add_all_responses();

        $this->assertCount(0, $this->teameval->get_releases());

        $this->teameval->release_marks_for_all();

        $this->assertCount(1, $this->teameval->get_releases());

        foreach ($this->students as $user) {
            $this->assertTrue($this->teameval->marks_released($user->id), 'Release all marks');
        }

        $this->teameval->release_marks_for_all(false);

        $this->assertCount(0, $this->teameval->get_releases());

        foreach ($this->students as $user) {
            $this->assertFalse($this->teameval->marks_released($user->id), 'Unrelease all marks');
        }
    }

    function test_release_for_group() {
        $this->add_questions();
        $this->add_all_responses();

        list($group0, $group1, $group2) = array_values($this->groups);
        $this->teameval->release_marks_for_group($group0->id);
        $this->teameval->release_marks_for_group($group1->id, false);

        $this->assertCount(1, $this->teameval->get_releases());

        foreach ($this->members[$group0->id] as $user) {
            $this->assertTrue($this->teameval->marks_released($user->id));
        }

        foreach ($this->members[$group1->id] as $user) {
            $this->assertFalse($this->teameval->marks_released($user->id));
        }

        foreach ($this->members[$group2->id] as $user) {
            $this->assertFalse($this->teameval->marks_released($user->id));
        }

    }

}