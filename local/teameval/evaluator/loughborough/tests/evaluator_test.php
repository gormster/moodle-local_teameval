<?php

use teamevaluator_loughborough\evaluator;
use local_teameval\question;
use local_teameval\response;
use local_teameval\team_evaluation;

$include = core_plugin_manager::instance()->get_plugin_info('local_teameval')->full_path('tests/mocks/mock_question.php');
require_once($include);

class teamevaluator_loughborough_evaluator_testcase extends advanced_testcase {

    private $course;

    private $groups;

    private $users;

    private $members;

    private $teameval;

    private $questions;

    private function set_up_course($groupnums) {

        $data = $this->getDataGenerator();

        $this->course = $data->create_course();

        team_evaluation::_clear_groups_members_cache();
        
        $this->groups = [];
        $this->users = [];
        $this->members = [];
        foreach($groupnums as $num) {
            $group = $data->create_group(array('courseid' => $this->course->id));
            $this->groups[$group->id] = $group;
            $this->members[$group->id] = [];

            for($j = 0; $j < $num; $j++) {
                $user = $data->create_user();
                $data->enrol_user($user->id, $this->course->id);
                $this->users[$user->id] = $user;
                $data->create_group_member(['userid' => $user->id, 'groupid' => $group->id]);
                $this->members[$group->id][$user->id] = $user;
            }
        }

    }

    private function set_up_teameval($questions) {
        global $USER;

        $this->setAdminUser();

        $data = $this->getDataGenerator();
        
        $generator = $data->get_plugin_generator('mod_assign');
        $assign = $generator->create_instance(array('course'=>$this->course->id));

        // create teameval
        $teameval = team_evaluation::from_cmid($assign->cmid);
        $this->teameval = $this->getMock(team_evaluation::class, ['get_question_plugins'], [$teameval->id]);

        $this->teameval->method('get_question_plugins')
            ->willReturn(['mock' => mock_question::mock_question_plugininfo($this)]);

        mock_question::clear_questions();
        mock_response::clear_responses();

        // add questions
        $this->questions = [];
        foreach($questions as $i => $q) {
            $question = new mock_question($this->teameval, $i);
            $question->min = $q['min'];
            $question->max = $q['max'];

            $tx = $this->teameval->should_update_question('mock', 0, $USER->id);
            $this->assertNotEmpty($tx);
            $tx->id = $question->id;
            $this->teameval->update_question($tx, $i);

            $this->questions[] = $question;
        }

    }

    public function test_simple() {

        $this->resetAfterTest(true);

        // three groups of four
        $this->set_up_course([4, 4, 4]);

        $this->set_up_teameval([
            ['min' => 0, 'max' => 5],
            ['min' => 1, 'max' => 3],
            ['min' => 10, 'max' => 100]
        ]);

        // This next bit looks like dark magic, but it's really pretty simple:
        // This is an array of the marks that each user awarded in each question
        // to each of their teammates (including themselves) in each group.
        // In other words, $rawresponses[group][marker][question][markee] = mark.
        
        $rawresponses = 
            [[[[5, 0, 3, 0], [3, 1, 3, 3], [23, 67, 35, 90]],
              [[0, 0, 0, 1], [2, 3, 3, 2], [28, 34, 15, 64]],
              [[2, 1, 2, 5], [3, 1, 1, 3], [100, 36, 31, 56]],
              [[1, 3, 2, 3], [1, 3, 3, 1], [29, 98, 31, 84]]],
             [[[1, 4, 2, 3], [2, 3, 1, 2], [65, 26, 38, 13]],
              [[4, 0, 3, 4], [1, 1, 1, 3], [99, 79, 27, 29]],
              [[1, 4, 0, 5], [1, 3, 1, 3], [17, 33, 97, 44]],
              [[0, 3, 4, 2], [3, 2, 3, 1], [70, 93, 82, 43]]],
             [[[5, 1, 4, 1], [3, 2, 3, 3], [76, 50, 30, 48]],
              [[1, 0, 4, 3], [3, 1, 3, 3], [74, 26, 74, 70]],
              [[2, 3, 4, 0], [3, 1, 2, 3], [75, 96, 25, 26]],
              [[5, 4, 4, 0], [1, 1, 2, 3], [77, 64, 97, 42]]]];

        $responses = mock_response::get_responses($this->teameval, $this->members, $this->questions, $rawresponses);

        // Finally, we can actually hand our data to our evaluator.

        $evaluator = new evaluator($this->teameval, $responses);

        // And we get our scores!

        $scores = $evaluator->scores();

        // And test them against our reference spreadsheet.

        $referencescores = [
            0.924825777269374,
            0.802574927281955,
            0.791655158880447,
            1.48094413656822,
            0.83330267875794,
            1.11095382722457,
            0.84859730270506,
            1.20714619131243,
            1.18596823960884,
            0.631551052964252,
            1.21624945342664,
            0.966231254000264
        ];

        foreach($this->users as $userid => $user) {
            $score = $scores[$userid];
            list($_, $refscore) = each($referencescores);
            $this->assertEquals($score, $refscore);
        }

    }

    public function test_fudge() {

        $this->resetAfterTest(true);

        // three groups of four
        $this->set_up_course([4, 4, 4]);

        $this->set_up_teameval([
            ['min' => 0, 'max' => 5],
            ['min' => 1, 'max' => 3],
            ['min' => 10, 'max' => 100]
        ]);

        $rawresponses = 
            [[[[5, 0, 3, 0], [3, 1, 3, 3], [23, 67, 35, 90]],
              [[0, 0, 0, 1], [2, 3, 3, 2], [28, 34, 15, 64]],
              [[2, 1, 2, 5], [3, 1, 1, 3], [100, 36, 31, 56]],
              [[null, null, null, null],
               [null, null, null, null],
               [null, null, null, null]]],
             [[[1, 4, 2, 3], [2, 3, 1, 2], [65, 26, 38, 13]],
              [[4, 0, 3, 4], [1, 1, 1, 3], [null, null, null, null]],
              [[1, 4, 0, 5], [1, 3, 1, 3], [17, 33, 97, 44]],
              [[0, 3, 4, 2], [3, 2, 3, 1], [70, 93, 82, 43]]],
             [[[null, null, null, null],
               [null, null, null, null],
               [null, null, null, null]],
              [[null, null, null, null], [3, 1, 3, 3], [74, 26, 74, 70]],
              [[null, null, null, null], [3, 1, 2, 3], [75, 96, 25, 26]],
              [[5, 4, 4, 0], [1, 1, 2, 3], [77, 64, 97, 42]]]];

        $responses = mock_response::get_responses($this->teameval, $this->members, $this->questions, $rawresponses);

        // Finally, we can actually hand our data to our evaluator.

        $evaluator = new evaluator($this->teameval, $responses);

        // And we get our scores!

        $scores = $evaluator->scores();

        // And test them against our reference spreadsheet.

        $referencescores = [
            1.14191413989174,
            0.506110170736043,
            0.688347937057562,
            1.66362775231465,
            0.772327137229685,
            1.06393690899892,
            0.946164494596831,
            1.21757145917456,
            1.26098422274893,
            0.755127008068185,
            1.132616294381,
            0.851272474801887
        ];

        foreach($this->users as $userid => $user) {
            $score = $scores[$userid];
            list($_, $refscore) = each($referencescores);
            $this->assertEquals($score, $refscore);
        }

    }

    public function test_div_zero() {
        $this->resetAfterTest(true);

        // three groups of four
        $this->set_up_course([4, 4]);

        $this->set_up_teameval([
            ['min' => 0, 'max' => 5],
            ['min' => 1, 'max' => 3],
            ['min' => 10, 'max' => 100]
        ]);

        $rawresponses = 
            [[[[5, 0, 3, 0], [3, 1, 3, 3], [23, 67, 35, 90]],
              [[0, 0, 0, 0], [2, 3, 3, 2], [28, 34, 15, 64]],
              [[2, 1, 2, 5], [3, 1, 1, 3], [100, 36, 31, 56]],
              [[0, 0, 0, 0], [1, 1, 1, 1], [100, 100, 100, 100]]],
             [[[1, 4, 2, 3], [2, 3, 1, 2], [10, 10, 10, 10]],
              [[4, 0, 3, 4], [1, 1, 1, 3], [null, null, null, null]],
              [[1, 4, 0, 5], [1, 3, 1, 3], [17, 33, 97, 44]],
              [[0, 3, 4, 2], [3, 2, 3, 1], [70, 93, 82, 43]]]];

        $responses = mock_response::get_responses($this->teameval, $this->members, $this->questions, $rawresponses);

        // Finally, we can actually hand our data to our evaluator.

        $evaluator = new evaluator($this->teameval, $responses);

        // And we get our scores!

        $scores = $evaluator->scores();

        // And test them against our reference spreadsheet.

        $referencescores = [
            1.18976893825214,
            0.712915961385366,
            0.849594286126505,
            1.24772081423599,
            0.643786832218791,
            1.10533124451091,
            0.935271248409467,
            1.31561067486084
        ];

        foreach($this->users as $userid => $user) {
            $score = $scores[$userid];
            list($_, $refscore) = each($referencescores);
            $this->assertEquals($score, $refscore);
        }

    }

}